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
        $mx = (int)($mxStmt->fetchColumn() ?? 0);

        // 2) lock counter row
        $stmt = $pdo->prepare("
            SELECT id, last_number
            FROM branch_daily_counters
            WHERE branch_id=? AND queue_date=?
            FOR UPDATE
        ");
        $stmt->execute([$branchId, $dateYmd]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $minInit = max(0, $startQueueNumber - 1);

        if ($row) {
            // auto sync jika counter ketinggalan
            $last = (int)$row['last_number'];
            if ($mx > $last) {
                $upd = $pdo->prepare("UPDATE branch_daily_counters SET last_number=?, updated_at=NOW() WHERE id=?");
                $upd->execute([$mx, (int)$row['id']]);
                $row['last_number'] = $mx;
            }
            // pastikan minimal mengikuti startQueueNumber-1
            if ((int)$row['last_number'] < $minInit) {
                $upd = $pdo->prepare("UPDATE branch_daily_counters SET last_number=?, updated_at=NOW() WHERE id=?");
                $upd->execute([$minInit, (int)$row['id']]);
                $row['last_number'] = $minInit;
            }
            return $row;
        }

        // create counter baru
        $init = max($minInit, $mx);
        $ins = $pdo->prepare("
            INSERT INTO branch_daily_counters (branch_id, queue_date, last_number, updated_at)
            VALUES (?, ?, ?, NOW())
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

        $branchId = (int)$branch['id'];
        $today    = date('Y-m-d');

        // dipanggil sekarang (CALLED terbaru)
        $called = DB::fetchOne("
            SELECT queue_number
            FROM queue_tickets
            WHERE branch_id=? AND queue_date=? AND status='CALLED'
            ORDER BY called_at DESC, id DESC
            LIMIT 1
        ", [$branchId, $today]);

        $calledNum = $called ? (int)$called['queue_number'] : null;

        // counter harian
        $counter = DB::fetchOne("
            SELECT last_number
            FROM branch_daily_counters
            WHERE branch_id=? AND queue_date=?
            LIMIT 1
        ", [$branchId, $today]);

        $startNo = (int)($branch['start_queue_number'] ?? 1);
        if ($startNo <= 0) $startNo = 1;

        $lastRaw     = (int)($counter['last_number'] ?? 0);
        $lastDisplay = ($lastRaw >= $startNo) ? $lastRaw : 0;

        // tiket customer (disimpan di session oleh publicTake)
        $sessionKey = 'public_ticket_' . $branchId;
        $myTicket   = $_SESSION[$sessionKey] ?? null;

        return View::render('queue/public', [
            'title'        => 'Ambil Antrean - QueueNow',
            'token'        => $token,
            'branch'       => $branch,

            // kompatibel dengan view lama & view baru
            'current'      => ['called' => $calledNum, 'last' => $lastDisplay],
            'calledNumber' => $calledNum ? ('A-' . $calledNum) : '-',
            'lastNumber'   => $lastDisplay,

            'myTicket'     => $myTicket,
            'error'        => Session::flash('error'),
            'success'      => Session::flash('success'),
        ], 'layouts/public');
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return (str_contains($accept, 'application/json') || strtolower($xhr) === 'xmlhttprequest');
    }

    private function json(array $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($body);
        exit;
    }

    // =========================
    // PUBLIC STATUS: GET /q/status?token=...
    // =========================
    public function publicStatus()
    {
        header('Content-Type: application/json');

        $token = trim((string)($_GET['token'] ?? ''));
        if ($token === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Token tidak valid.']);
            return;
        }

        $branch = DB::fetchOne("
            SELECT id, start_queue_number
            FROM branches
            WHERE qr_token=?
            LIMIT 1
        ", [$token]);

        if (!$branch) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'QR token tidak ditemukan.']);
            return;
        }

        $branchId = (int)$branch['id'];
        $today    = date('Y-m-d');

        $called = DB::fetchOne("
            SELECT queue_number
            FROM queue_tickets
            WHERE branch_id=? AND queue_date=? AND status='CALLED'
            ORDER BY called_at DESC, id DESC
            LIMIT 1
        ", [$branchId, $today]);

        $counter = DB::fetchOne("
            SELECT last_number
            FROM branch_daily_counters
            WHERE branch_id=? AND queue_date=?
            LIMIT 1
        ", [$branchId, $today]);

        $startNo = (int)($branch['start_queue_number'] ?? 1);
        if ($startNo <= 0) $startNo = 1;

        $lastRaw     = (int)($counter['last_number'] ?? 0);
        $lastDisplay = ($lastRaw >= $startNo) ? $lastRaw : 0;

        $calledNum = $called ? (int)$called['queue_number'] : null;

        echo json_encode([
            'ok'             => true,
            'called_number'  => $calledNum,
            'called_display' => $calledNum ? ('A-' . $calledNum) : '-',
            'last_number'    => $lastDisplay,
        ]);
    }

    // =========================
    // PUBLIC TAKE: POST /q/take
    // =========================
    public function publicTake()
    {
        $isJson = $this->wantsJson();

        // CSRF
        if (!CSRF::verify($_POST['_csrf'] ?? null)) {
            if ($isJson) {
                $this->json(['ok' => false, 'message' => 'CSRF token tidak valid.'], 419);
            }
            Session::flash('error', 'CSRF token tidak valid.');
            redirect('/q?token=' . urlencode((string)($_POST['token'] ?? '')));
        }

        $token  = trim((string)($_POST['token'] ?? ''));
        $source = strtoupper(trim((string)($_POST['source'] ?? 'QR')));
        if (!in_array($source, ['QR', 'ONLINE'], true)) $source = 'QR';

        if ($token === '') {
            if ($isJson) $this->json(['ok' => false, 'message' => 'Token tidak valid.'], 400);
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
            if ($isJson) $this->json(['ok' => false, 'message' => 'QR token tidak ditemukan.'], 404);
            Session::flash('error', 'QR token tidak ditemukan.');
            redirect('/q');
        }

        $branchId = (int)$branch['id'];
        $startNo  = (int)($branch['start_queue_number'] ?? 1);
        if ($startNo <= 0) $startNo = 1;

        $clientUuid = qn_client_uuid();
        $clientIp   = qn_client_ip();

        // gunakan QueueService (anti-spam + 1 device 1 tiket aktif)
        $service = new QueueService();
        $res     = $service->takePublic($branchId, $source, $clientUuid, $clientIp, $startNo);

        $http = (int)($res['http'] ?? 200);

        if (!($res['ok'] ?? false)) {
            if ($isJson) $this->json(['ok' => false, 'message' => $res['message'] ?? 'Gagal ambil antrean'], $http ?: 500);
            Session::flash('error', $res['message'] ?? 'Gagal ambil antrean');
            redirect('/q?token=' . urlencode($token));
        }

        $ticket = $res['ticket'] ?? null;

        // simpan tiket ke session supaya terlihat juga tanpa localStorage
        if (is_array($ticket) && isset($ticket['queue_number'])) {
            $display = $ticket['display'] ?? ('A-' . (int)$ticket['queue_number']);
            $_SESSION['public_ticket_' . $branchId] = $display;
        }

        // mode AJAX
        if ($isJson) {
            $this->json([
                'ok'          => true,
                'message'     => $res['message'] ?? 'OK',
                'is_existing' => (bool)($res['is_existing'] ?? false),
                'ticket'      => $ticket,
            ], 200);
        }

        // mode non-AJAX
        if (!empty($ticket['queue_number'])) {
            Session::flash('success', 'Nomor antrean kamu: ' . ($ticket['display'] ?? ('A-' . (int)$ticket['queue_number'])));
        } else {
            Session::flash('success', $res['message'] ?? 'Berhasil ambil antrean.');
        }

        redirect('/q?token=' . urlencode($token));
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

        $branchId = (int)($_GET['branch_id'] ?? 0);
        if ($branchId <= 0 && !empty($branches)) {
            $branchId = (int)$branches[0]['id'];
        }

        $branch = null;
        if ($branchId > 0) {
            $branch = DB::fetchOne("
                SELECT b.*
                FROM branches b
                JOIN businesses bs ON bs.id=b.business_id
                WHERE b.id=? AND bs.owner_id=?
                LIMIT 1
            ", [$branchId, $ownerId]);
        }

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
            LIMIT 50
        ");
        $skippedStmt->execute([$branchId, $today]);
        $skipped = $skippedStmt->fetchAll() ?: [];

        // DONE
        $doneStmt = DB::pdo()->prepare("
            SELECT *
            FROM queue_tickets
            WHERE branch_id=? AND queue_date=? AND status='DONE'
            ORDER BY finished_at DESC, queue_number ASC
            LIMIT 50
        ");
        $doneStmt->execute([$branchId, $today]);
        $done = $doneStmt->fetchAll() ?: [];

        // sedang dipanggil (CALLED terbaru) untuk display besar
        $calledNow = $called[0]['queue_number'] ?? null;

        $publicUrl = base_url('/q?token=' . urlencode((string)($branch['qr_token'] ?? '')));

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

            // ✅ kirim 2 key supaya aman jika View::render() mengubah key jadi lowercase
            'publicUrl' => $publicUrl,
            'publicurl' => $publicUrl,
        ], 'layouts/app');
    }

    // POST /queues/update-status?id=..&branch_id=..&act=skip|done
    public function updateStatus()
    {
        if (!CSRF::verify($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'CSRF token tidak valid.');
            redirect('/queues/manage?branch_id=' . (int)($_GET['branch_id'] ?? 0));
        }

        $ownerId  = (int)Auth::id();
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $ticketId = (int)($_GET['id'] ?? 0);
        $act      = (string)($_GET['act'] ?? '');

        if ($branchId <= 0 || $ticketId <= 0) {
            Session::flash('error', 'Parameter tidak valid.');
            redirect('/queues/manage');
        }

        // pastikan branch milik owner
        $branch = DB::fetchOne("
            SELECT b.id
            FROM branches b
            JOIN businesses bs ON bs.id=b.business_id
            WHERE b.id=? AND bs.owner_id=?
            LIMIT 1
        ", [$branchId, $ownerId]);

        if (!$branch) {
            Session::flash('error', 'Cabang tidak valid.');
            redirect('/queues/manage');
        }

        $ticket = DB::fetchOne("SELECT * FROM queue_tickets WHERE id=? LIMIT 1", [$ticketId]);
        if (!$ticket) {
            Session::flash('error', 'Antrean tidak ditemukan.');
            redirect('/queues/manage?branch_id=' . $branchId);
        }

        $status = (string)$ticket['status'];

        if ($act === 'skip') {
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
        $today    = date('Y-m-d');

        if ($branchId <= 0) {
            Session::flash('error', 'Cabang tidak valid.');
            redirect('/queues/manage');
        }

        // pastikan branch milik owner
        $branch = DB::fetchOne("
            SELECT b.id
            FROM branches b
            JOIN businesses bs ON bs.id=b.business_id
            WHERE b.id=? AND bs.owner_id=?
            LIMIT 1
        ", [$branchId, $ownerId]);

        if (!$branch) {
            Session::flash('error', 'Cabang tidak valid.');
            redirect('/queues/manage');
        }

        $next = DB::fetchOne("
            SELECT *
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

    // POST /queues/action?branch_id=..&id=..
    public function action()
    {
        // CSRF
        if (!CSRF::verify($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'CSRF token tidak valid.');
            redirect('/queues/manage?branch_id=' . (int)($_GET['branch_id'] ?? 0));
        }

        $ownerId  = (int)Auth::id();
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $ticketId = (int)($_GET['id'] ?? 0);
        $act      = (string)($_POST['action'] ?? ''); // call | skip | done

        if ($branchId <= 0 || $ticketId <= 0) {
            Session::flash('error', 'Parameter tidak valid.');
            redirect('/queues/manage');
        }

        // Pastikan branch milik owner
        $branch = DB::fetchOne("
        SELECT b.id
        FROM branches b
        JOIN businesses bs ON bs.id=b.business_id
        WHERE b.id=? AND bs.owner_id=?
        LIMIT 1
    ", [$branchId, $ownerId]);

        if (!$branch) {
            Session::flash('error', 'Akses ditolak.');
            redirect('/queues/manage');
        }

        $today = date('Y-m-d');

        // Ticket harus branch ini + hari ini
        $ticket = DB::fetchOne("
        SELECT *
        FROM queue_tickets
        WHERE id=? AND branch_id=? AND queue_date=?
        LIMIT 1
    ", [$ticketId, $branchId, $today]);

        if (!$ticket) {
            Session::flash('error', 'Antrean tidak ditemukan.');
            redirect('/queues/manage?branch_id=' . $branchId);
        }

        $status = (string)$ticket['status'];
        $qno    = 'A-' . (int)$ticket['queue_number'];

        if ($act === 'call') {
            if ($status !== 'WAITING') {
                Session::flash('error', 'Hanya antrean WAITING yang bisa dipanggil.');
                redirect('/queues/manage?branch_id=' . $branchId);
            }
            DB::exec("UPDATE queue_tickets SET status='CALLED', called_at=NOW() WHERE id=?", [$ticketId]);
            Session::flash('success', 'Memanggil: ' . $qno);
        } elseif ($act === 'skip') {
            if (!in_array($status, ['WAITING', 'CALLED'], true)) {
                Session::flash('error', 'Antrean ini tidak bisa di-skip.');
                redirect('/queues/manage?branch_id=' . $branchId);
            }
            DB::exec("UPDATE queue_tickets SET status='SKIPPED', finished_at=NOW() WHERE id=?", [$ticketId]);
            Session::flash('success', 'Skip: ' . $qno);
        } elseif ($act === 'done') {
            if ($status !== 'CALLED') {
                Session::flash('error', 'Hanya antrean CALLED yang bisa diselesaikan.');
                redirect('/queues/manage?branch_id=' . $branchId);
            }
            DB::exec("UPDATE queue_tickets SET status='DONE', finished_at=NOW() WHERE id=?", [$ticketId]);
            Session::flash('success', 'Selesai: ' . $qno);
        } else {
            Session::flash('error', 'Aksi tidak valid.');
        }

        redirect('/queues/manage?branch_id=' . $branchId);
    }
}
