<?php
// app/controllers/BranchController.php

class BranchController extends Controller
{
    private function getOwnerBusinessId(): ?int
    {
        $ownerId = Auth::id();
        $biz = DB::fetchOne("SELECT id FROM businesses WHERE owner_id=? ORDER BY id ASC LIMIT 1", [$ownerId]);
        return $biz ? (int)$biz['id'] : null;
    }

    private function isPro(): bool
    {
        $u = Auth::user();
        return isset($u['plan']) && $u['plan'] === 'PRO';
    }

    private function enforceBranchLimit(int $businessId): void
    {
        if ($this->isPro()) return;

        $count = DB::fetchOne("SELECT COUNT(*) AS c FROM branches WHERE business_id=?", [$businessId]);
        $max = (int)(config('plans.free.branch_limit', 1));
        if ((int)($count['c'] ?? 0) >= $max) {
            Session::flash('error', 'Akun Free hanya boleh membuat 1 cabang. Upgrade ke Pro untuk cabang tanpa batas.');
            redirect('/branches');
        }
    }

    private function ensureOwnBranch(int $branchId, int $businessId): ?array
    {
        return DB::fetchOne("SELECT * FROM branches WHERE id=? AND business_id=?", [$branchId, $businessId]);
    }

    private function generateQrToken(): string
    {
        // 32 bytes => 64 hex chars
        return bin2hex(random_bytes(32));
    }

    public function index()
    {
        $businessId = $this->getOwnerBusinessId();
        if (!$businessId) {
            Session::flash('error', 'Business tidak ditemukan. Silakan daftar ulang / cek data.');
            redirect('/dashboard');
        }

        $rows = DB::pdo()->prepare("SELECT * FROM branches WHERE business_id=? ORDER BY id DESC");
        $rows->execute([$businessId]);
        $branches = $rows->fetchAll() ?: [];

        return View::render('branches/index', [
            'title' => 'Cabang - QueueNow',
            'branches' => $branches,
            'error' => Session::flash('error'),
            'success' => Session::flash('success'),
            'isPro' => $this->isPro(),
        ], 'layouts/app');
    }

    public function create()
    {
        $businessId = $this->getOwnerBusinessId();
        if (!$businessId) redirect('/dashboard');

        // enforce limit Free
        $this->enforceBranchLimit($businessId);

        return View::render('branches/create', [
            'title' => 'Tambah Cabang - QueueNow',
            'error' => Session::flash('error'),
            'old' => Session::flash('old') ? json_decode(Session::flash('old'), true) : [],
        ], 'layouts/app');
    }

    public function store()
    {
        if (!CSRF::verify($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'CSRF token tidak valid.');
            redirect('/branches/create');
        }

        $businessId = $this->getOwnerBusinessId();
        if (!$businessId) redirect('/dashboard');

        $this->enforceBranchLimit($businessId);

        $name   = trim((string)($_POST['name'] ?? ''));
        $addr   = trim((string)($_POST['address'] ?? ''));
        $start  = (int)($_POST['start_queue_number'] ?? 1);

        // operational_hours (simple) - kita simpan dari 2 input jam buka/tutup
        $open  = trim((string)($_POST['open_time'] ?? ''));
        $close = trim((string)($_POST['close_time'] ?? ''));

        Session::flash('old', json_encode([
            'name' => $name,
            'address' => $addr,
            'start_queue_number' => $start,
            'open_time' => $open,
            'close_time' => $close,
        ]));

        if ($name === '') {
            Session::flash('error', 'Nama cabang wajib diisi.');
            redirect('/branches/create');
        }

        if ($start < 1) $start = 1;

        $hours = null;
        if ($open !== '' && $close !== '') {
            // simpan pola sederhana untuk semua hari
            $hours = json_encode([
                'mon' => [$open, $close],
                'tue' => [$open, $close],
                'wed' => [$open, $close],
                'thu' => [$open, $close],
                'fri' => [$open, $close],
                'sat' => [$open, $close],
                'sun' => [$open, $close],
            ]);
        }

        // generate unique qr_token (retry kalau tabrakan)
        $token = $this->generateQrToken();
        for ($i=0; $i<3; $i++) {
            $exist = DB::fetchOne("SELECT id FROM branches WHERE qr_token=?", [$token]);
            if (!$exist) break;
            $token = $this->generateQrToken();
        }

        DB::exec(
            "INSERT INTO branches (business_id, name, address, start_queue_number, is_active, operational_hours, qr_token, qr_image_path)
             VALUES (?, ?, ?, ?, 1, ?, ?, NULL)",
            [$businessId, $name, ($addr !== '' ? $addr : null), $start, $hours, $token]
        );

        Session::flash('success', 'Cabang berhasil ditambahkan.');
        redirect('/branches');
    }

    public function edit()
    {
        $businessId = $this->getOwnerBusinessId();
        if (!$businessId) redirect('/dashboard');

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) redirect('/branches');

        $branch = $this->ensureOwnBranch($id, $businessId);
        if (!$branch) {
            Session::flash('error', 'Cabang tidak ditemukan.');
            redirect('/branches');
        }

        // extract jam dari JSON (kalau ada)
        $open = '';
        $close = '';
        if (!empty($branch['operational_hours'])) {
            $j = json_decode($branch['operational_hours'], true);
            if (is_array($j) && isset($j['mon'][0], $j['mon'][1])) {
                $open = $j['mon'][0];
                $close = $j['mon'][1];
            }
        }

        return View::render('branches/edit', [
            'title' => 'Edit Cabang - QueueNow',
            'error' => Session::flash('error'),
            'branch' => $branch,
            'open' => $open,
            'close' => $close,
        ], 'layouts/app');
    }

    public function update()
    {
        if (!CSRF::verify($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'CSRF token tidak valid.');
            redirect('/branches');
        }

        $businessId = $this->getOwnerBusinessId();
        if (!$businessId) redirect('/dashboard');

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) redirect('/branches');

        $branch = $this->ensureOwnBranch($id, $businessId);
        if (!$branch) {
            Session::flash('error', 'Cabang tidak ditemukan.');
            redirect('/branches');
        }

        $name  = trim((string)($_POST['name'] ?? ''));
        $addr  = trim((string)($_POST['address'] ?? ''));
        $start = (int)($_POST['start_queue_number'] ?? 1);
        $open  = trim((string)($_POST['open_time'] ?? ''));
        $close = trim((string)($_POST['close_time'] ?? ''));

        if ($name === '') {
            Session::flash('error', 'Nama cabang wajib diisi.');
            redirect('/branches/edit?id=' . $id);
        }
        if ($start < 1) $start = 1;

        $hours = null;
        if ($open !== '' && $close !== '') {
            $hours = json_encode([
                'mon' => [$open, $close],
                'tue' => [$open, $close],
                'wed' => [$open, $close],
                'thu' => [$open, $close],
                'fri' => [$open, $close],
                'sat' => [$open, $close],
                'sun' => [$open, $close],
            ]);
        }

        DB::exec(
            "UPDATE branches
             SET name=?, address=?, start_queue_number=?, operational_hours=?
             WHERE id=? AND business_id=?",
            [$name, ($addr !== '' ? $addr : null), $start, $hours, $id, $businessId]
        );

        Session::flash('success', 'Cabang berhasil diperbarui.');
        redirect('/branches');
    }

    public function delete()
    {
        if (!CSRF::verify($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'CSRF token tidak valid.');
            redirect('/branches');
        }

        $businessId = $this->getOwnerBusinessId();
        if (!$businessId) redirect('/dashboard');

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) redirect('/branches');

        DB::exec("DELETE FROM branches WHERE id=? AND business_id=?", [$id, $businessId]);

        Session::flash('success', 'Cabang berhasil dihapus.');
        redirect('/branches');
    }
}
