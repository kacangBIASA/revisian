<?php
// app/controllers/QueueController.php

class QueueController extends Controller
{
    /**
     * Ambil counter row (LOCK FOR UPDATE).
     * Kalau belum ada, create dengan last_number = max(start-1, max existing ticket hari ini).
     * Kalau sudah ada tapi last_number ketinggalan dari data ticket, auto sync.
     */
    private function lockOrCreateCounter(PDO $pdo, int $branchId, string $dateYmd, int $startQueueNumber): array
    {
        // 1) cek max existing ticket (buat migrasi dari versi lama MAX+1 biar aman)
        $mxStmt = $pdo->prepare("
            SELECT MAX(queue_number) AS mx
            FROM queue_tickets
            WHERE branch_id=? AND queue_date=?
        ");
        $mxStmt->execute([$branchId, $dateYmd]);
        $maxExisting = (int)($mxStmt->fetch(PDO::FETCH_ASSOC)['mx'] ?? 0);

        // 2) coba ambil counter row + lock
        $sel = $pdo->prepare("
            SELECT id, last_number
            FROM branch_daily_counters
            WHERE branch_id=? AND queue_date=?
            FOR UPDATE
        ");
        $sel->execute([$branchId, $dateYmd]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // sync jika counter tertinggal
            $last = (int)$row['last_number'];
            if ($maxExisting > $last) {
                $upd = $pdo->prepare("
                    UPDATE branch_daily_counters
                    SET last_number=?, updated_at=NOW()
                    WHERE id=?
                ");
                $upd->execute([$maxExisting, (int)$row['id']]);
                $row['last_number'] = $maxExisting;
            }
            return $row;
        }

        // 3) kalau belum ada -> buat baru
        $init = max(0, $startQueueNumber - 1);
        if ($maxExisting > $init) $init = $maxExisting;

        $ins = $pdo->prepare("
            INSERT INTO branch_daily_counters (branch_id, queue_date, last_number, reset_at)
            VALUES (?, ?, ?, NULL)
        ");
        $ins->execute([$branchId, $dateYmd, $init]);

        return [
            'id' => (int)$pdo->lastInsertId(),
            'last_number' => $init,
        ];
    }

    // =========================
    // PUBLIC PAGE: /q?token=...
    // =========================
    public function publicPage()
    {
        $token = trim((string)($_GET['token'] ?? ''));
        if ($token === '') {
            http_response_code(404);
            echo "Token tidak valid.";
            return;
        }

        $branch = DB::fetchOne("
            SELECT b.id, b.name, b.address, b.start_queue_number, b.qr_token, bs.name AS business_name
            FROM branches b
            JOIN businesses bs ON bs.id=b.business_id
            WHERE b.qr_token=?
            LIMIT 1
        ", [$token]);

        if (!$branch) {
            http_response_code(404);
            echo "QR token tidak ditemukan.";
            return;
        }

        $today    = date('Y-m-d');
        $branchId = (int)$branch['id'];
        $startNo  = (int)($branch['start_queue_number'] ?? 1);
        if ($startNo <= 0) $startNo = 1;

        // sedang dipanggil (CALLED terbaru hari ini)
        $called = DB::fetchOne("
            SELECT queue_number, called_at
            FROM queue_tickets
            WHERE branch_id=? AND queue_date=? AND status='CALLED'
            ORDER BY called_at DESC
            LIMIT 1
        ", [$branchId, $today]);

        // preview "nomor terakhir diambil" dari counter
        $counter = DB::fetchOne("
            SELECT last_number
            FROM branch_daily_counters
            WHERE branch_id=? AND queue_date=?
            LIMIT 1
        ", [$branchId, $today]);

        $lastNumberRaw = (int)($counter['last_number'] ?? 0);
        $lastDisplay   = ($lastNumberRaw >= $startNo) ? $lastNumberRaw : 0;

        $calledNum = $called ? (int)$called['queue_number'] : null;

        $sessionKey = 'public_ticket_' . $branchId;
        $myTicket   = $_SESSION[$sessionKey] ?? null;

        return View::render('queue/public', [
            'title'        => 'Ambil Antrean - QueueNow',
            'token'        => $token,
            'branch'       => $branch,

            // biar kompatibel sama view kamu yang mungkin pakai current/calledNumber/lastNumber
            'current'      => ['called' => $calledNum, 'last' => $lastDisplay],
            'calledNumber' => $calledNum ? ('A-' . $calledNum) : '-',
            'lastNumber'   => $lastDisplay,

            'myTicket'     => $myTicket,
            'error'        => Session::flash('error'),
            'success'      => Session::flash('success'),
        ], 'layouts/public');
    }

    // =========================
    // PUBLIC TAKE: POST /q/take
    // =========================
    public function publicTake()
    {
        if (!CSRF::verify($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'CSRF token tidak valid.');
            redirect('/q?token=' . urlencode((string)($_POST['token'] ?? '')));
        }

        $token  = trim((string)($_POST['token'] ?? ''));
        $source = strtoupper(trim((string)($_POST['source'] ?? 'QR')));
        if (!in_array($source, ['QR', 'ONLINE'], true)) $source = 'QR';

        if ($token === '') {
            Session::flash('error', 'Token tidak valid.');
            redirect('/q');
        }

        $branch = DB::fetchOne("
            SELECT id, start_queue_number
            FROM branches
            WHERE qr_token=?
            LIMIT 1
        ", [$token]);

        if (!$branch) {
            Session::flash('error', 'QR token tidak ditemukan.');
            redirect('/q');
        }

        $branchId = (int)$branch['id'];
        $today    = date('Y-m-d');

        $startNo = (int)($branch['start_queue_number'] ?? 1);
        if ($startNo <= 0) $startNo = 1;

        $pdo = DB::pdo();

        try {
            $pdo->beginTransaction();

            // lock/create counter (dan auto-sync dari data ticket existing)
            $counter = $this->lockOrCreateCounter($pdo, $branchId, $today, $startNo);
            $last    = (int)$counter['last_number'];

            $next = $last + 1;
            if ($next < $startNo) $next = $startNo;

            // insert ticket (AMAN dari duplicate karena counter di-lock)
            $ins = $pdo->prepare("
                INSERT INTO queue_tickets
                    (branch_id, queue_date, queue_number, source, status, taken_at)
                VALUES
                    (?, ?, ?, ?, 'WAITING', NOW())
            ");
            $ins->execute([$branchId, $today, $next, $source]);

            // update counter
            $upd = $pdo->prepare("
                UPDATE branch_daily_counters
                SET last_number=?, updated_at=NOW()
                WHERE branch_id=? AND queue_date=?
            ");
            $upd->execute([$next, $branchId, $today]);

            $pdo->commit();

            // simpan ke session agar balik tampil di halaman
            $_SESSION['public_ticket_' . $branchId] = 'A-' . $next;

            Session::flash('success', 'Berhasil ambil antrean: A-' . $next);
            redirect('/q?token=' . urlencode($token));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();

            // tampilkan error singkat tapi jelas
            Session::flash('error', 'Gagal ambil antrean: ' . $e->getMessage());
            redirect('/q?token=' . urlencode($token));
        }
    }

    // =========================
    // OWNER MANAGE: GET /queues/manage
    // =========================
    public function manage()
    {
        $ownerId = (int)Auth::id();
        $today   = date('Y-m-d');

        // semua cabang milik owner
        $stmt = DB::pdo()->prepare("
            SELECT b.*
            FROM branches b
            JOIN businesses bs ON bs.id=b.business_id
            WHERE bs.owner_id=?
            ORDER BY b.id ASC
        ");
        $stmt->execute([$ownerId]);
        $branches = $stmt->fetchAll() ?: [];

        if (empty($branches)) {
            Session::flash('error', 'Belum ada cabang. Buat cabang dulu.');
            redirect('/branches');
        }

        $branchId = (int)($_GET['branch_id'] ?? 0);
        if ($branchId <= 0) $branchId = (int)$branches[0]['id'];

        // validasi cabang milik owner
        $branch = DB::fetchOne("
            SELECT b.*
            FROM branches b
            JOIN businesses bs ON bs.id=b.business_id
            WHERE b.id=? AND bs.owner_id=?
            LIMIT 1
        ", [$branchId, $ownerId]);

        if (!$branch) {
            Session::flash('error', 'Cabang tidak valid.');
            redirect('/queues/manage');
        }

        // WAITING
        $waitingStmt = DB::pdo()->prepare("
            SELECT *
            FROM queue_tickets
            WHERE branch_id=? AND queue_date=? AND status='WAITING'
            ORDER BY queue_number ASC
        ");
        $waitingStmt->execute([$branchId, $today]);
        $waiting = $waitingStmt->fetchAll() ?: [];

        // CALLED
        $calledStmt = DB::pdo()->prepare("
            SELECT *
            FROM queue_tickets
            WHERE branch_id=? AND queue_date=? AND status='CALLED'
            ORDER BY called_at DESC, queue_number ASC
        ");
        $calledStmt->execute([$branchId, $today]);
        $called = $calledStmt->fetchAll() ?: [];

        // SKIPPED
        $skippedStmt = DB::pdo()->prepare("
            SELECT *
            FROM queue_tickets
            WHERE branch_id=? AND queue_date=? AND status='SKIPPED'
            ORDER BY finished_at DESC, queue_number ASC
        ");
        $skippedStmt->execute([$branchId, $today]);
        $skipped = $skippedStmt->fetchAll() ?: [];

        // DONE
        $doneStmt = DB::pdo()->prepare("
            SELECT *
            FROM queue_tickets
            WHERE branch_id=? AND queue_date=? AND status='DONE'
            ORDER BY finished_at DESC, queue_number DESC
            LIMIT 50
        ");
        $doneStmt->execute([$branchId, $today]);
        $done = $doneStmt->fetchAll() ?: [];

        // sedang dipanggil (CALLED terbaru) untuk display besar
        $calledNow = $called[0]['queue_number'] ?? null;

        return View::render('queue/manage', [
            'title'     => 'Kelola Antrean - QueueNow',
            'branches'  => $branches,
            'branch'    => $branch,
            'waiting'   => $waiting,
            'called'    => $called,
            'skipped'   => $skipped,
            'done'      => $done,
            'calledNow' => $calledNow,

            'error'     => Session::flash('error'),
            'success'   => Session::flash('success'),

            'publicUrl' => base_url('/q?token=' . urlencode($branch['qr_token'])),
        ], 'layouts/app');
    }

    // POST /queues/action?branch_id=..&id=..
    public function action()
    {
        if (!CSRF::verify($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'CSRF token tidak valid.');
            redirect('/queues/manage?branch_id=' . (int)($_GET['branch_id'] ?? 0));
        }

        $ownerId  = (int)Auth::id();
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $ticketId = (int)($_GET['id'] ?? 0);
        $act      = (string)($_POST['action'] ?? '');

        if ($branchId <= 0 || $ticketId <= 0) redirect('/queues/manage');

        $ok = DB::fetchOne("
            SELECT b.id
            FROM branches b
            JOIN businesses bs ON bs.id=b.business_id
            WHERE b.id=? AND bs.owner_id=?
        ", [$branchId, $ownerId]);

        if (!$ok) {
            Session::flash('error', 'Akses ditolak.');
            redirect('/queues/manage');
        }

        $today = date('Y-m-d');

        $ticket = DB::fetchOne("
            SELECT *
            FROM queue_tickets
            WHERE id=? AND branch_id=? AND queue_date=?
        ", [$ticketId, $branchId, $today]);

        if (!$ticket) {
            Session::flash('error', 'Antrean tidak ditemukan.');
            redirect('/queues/manage?branch_id=' . $branchId);
        }

        $status = (string)$ticket['status'];

        if ($act === 'call') {
            if ($status !== 'WAITING') {
                Session::flash('error', 'Hanya antrean WAITING yang bisa dipanggil.');
                redirect('/queues/manage?branch_id=' . $branchId);
            }
            DB::exec("UPDATE queue_tickets SET status='CALLED', called_at=NOW() WHERE id=?", [$ticketId]);
            Session::flash('success', 'Memanggil A-' . (int)$ticket['queue_number']);
        } elseif ($act === 'skip') {
            if (!in_array($status, ['WAITING', 'CALLED'], true)) {
                Session::flash('error', 'Antrean ini tidak bisa di-skip.');
                redirect('/queues/manage?branch_id=' . $branchId);
            }
            DB::exec("UPDATE queue_tickets SET status='SKIPPED', finished_at=NOW() WHERE id=?", [$ticketId]);
            Session::flash('success', 'Skip A-' . (int)$ticket['queue_number']);
        } elseif ($act === 'done') {
            if ($status !== 'CALLED') {
                Session::flash('error', 'Hanya antrean CALLED yang bisa diselesaikan.');
                redirect('/queues/manage?branch_id=' . $branchId);
            }
            DB::exec("UPDATE queue_tickets SET status='DONE', finished_at=NOW() WHERE id=?", [$ticketId]);
            Session::flash('success', 'Selesai A-' . (int)$ticket['queue_number']);
        } else {
            Session::flash('error', 'Aksi tidak valid.');
        }

        redirect('/queues/manage?branch_id=' . $branchId);
    }

    // POST /queues/call-next?branch_id=..
    public function callNext()
    {
        if (!CSRF::verify($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'CSRF token tidak valid.');
            redirect('/queues/manage?branch_id=' . (int)($_GET['branch_id'] ?? 0));
        }

        $ownerId  = (int)Auth::id();
        $branchId = (int)($_GET['branch_id'] ?? 0);
        if ($branchId <= 0) redirect('/queues/manage');

        $ok = DB::fetchOne("
            SELECT b.id
            FROM branches b
            JOIN businesses bs ON bs.id=b.business_id
            WHERE b.id=? AND bs.owner_id=?
        ", [$branchId, $ownerId]);

        if (!$ok) {
            Session::flash('error', 'Akses ditolak.');
            redirect('/queues/manage');
        }

        $today = date('Y-m-d');

        $next = DB::fetchOne("
            SELECT id, queue_number
            FROM queue_tickets
            WHERE branch_id=? AND queue_date=? AND status='WAITING'
            ORDER BY queue_number ASC
            LIMIT 1
        ", [$branchId, $today]);

        if (!$next) {
            Session::flash('error', 'Tidak ada antrean yang menunggu.');
            redirect('/queues/manage?branch_id=' . $branchId);
        }

        DB::exec("UPDATE queue_tickets SET status='CALLED', called_at=NOW() WHERE id=?", [(int)$next['id']]);
        Session::flash('success', 'Memanggil berikutnya: A-' . (int)$next['queue_number']);
        redirect('/queues/manage?branch_id=' . $branchId);
    }
}
