<?php
// app/controllers/DashboardController.php

class DashboardController extends Controller
{
    private function cfg(): array
    {
        if (function_exists('config')) return config('midtrans');
        return require __DIR__ . '/../config/midtrans.php';
    }

    public function index()
    {
        $ownerId = (int)Auth::id();

        // 1) selalu refresh owner dari DB (bukan session lama)
        $owner = DB::fetchOne("SELECT * FROM owners WHERE id=?", [$ownerId]);
        if (!$owner) {
            Session::flash('error', 'Owner tidak ditemukan.');
            redirect('/login');
        }

        // 2) kalau masih FREE, coba sync status transaksi terakhir (fallback jika webhook tidak masuk)
        if (($owner['plan'] ?? 'FREE') !== 'PRO') {
            $this->syncLatestTransactionIfNeeded($ownerId);
            $owner = DB::fetchOne("SELECT * FROM owners WHERE id=?", [$ownerId]) ?: $owner;
        }

        // 3) update session supaya view/dashboard pakai data baru
        if (class_exists('Session') && method_exists('Session', 'set')) {
            Session::set('owner', $owner);
        } else {
            $_SESSION['owner'] = $owner;
        }

        // business utama (ambil 1)
        $business = DB::fetchOne(
            "SELECT id, name FROM businesses WHERE owner_id=? ORDER BY id ASC LIMIT 1",
            [$ownerId]
        ) ?: [];

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        // total antrean hari ini (semua cabang milik owner)
        $totalToday = DB::fetchOne("
            SELECT COUNT(*) AS c
            FROM queue_tickets qt
            JOIN branches b ON b.id=qt.branch_id
            JOIN businesses bs ON bs.id=b.business_id
            WHERE bs.owner_id=? AND qt.queue_date=?
        ", [$ownerId, $today]);
        $totalToday = (int)($totalToday['c'] ?? 0);

        // total antrean bulan ini
        $totalMonth = DB::fetchOne("
            SELECT COUNT(*) AS c
            FROM queue_tickets qt
            JOIN branches b ON b.id=qt.branch_id
            JOIN businesses bs ON bs.id=b.business_id
            WHERE bs.owner_id=? AND qt.queue_date BETWEEN ? AND ?
        ", [$ownerId, $monthStart, $monthEnd]);
        $totalMonth = (int)($totalMonth['c'] ?? 0);

        // sedang dipanggil (latest CALLED hari ini)
        $calledNow = DB::fetchOne("
            SELECT qt.queue_number, b.name AS branch_name
            FROM queue_tickets qt
            JOIN branches b ON b.id=qt.branch_id
            JOIN businesses bs ON bs.id=b.business_id
            WHERE bs.owner_id=? AND qt.queue_date=? AND qt.status='CALLED'
            ORDER BY qt.called_at DESC
            LIMIT 1
        ", [$ownerId, $today]) ?: [];

        return View::render('dashboard/index', [
            'title' => 'Dashboard - QueueNow',
            'owner' => $owner,
            'business' => $business,
            'totalToday' => $totalToday,
            'totalMonth' => $totalMonth,
            'calledNow' => $calledNow,
        ], 'layouts/app');
    }

    private function syncLatestTransactionIfNeeded(int $ownerId): void
    {
        // ambil transaksi terakhir yang masih mungkin perlu sync
        $trx = DB::fetchOne("
            SELECT order_id, status
            FROM transactions
            WHERE owner_id=?
            ORDER BY id DESC
            LIMIT 1
        ", [$ownerId]);

        if (!$trx) return;

        $orderId = (string)($trx['order_id'] ?? '');
        if ($orderId === '') return;

        // kalau sudah final, skip
        $status = (string)($trx['status'] ?? 'PENDING');
        if (in_array($status, ['SETTLEMENT', 'CAPTURE', 'CANCEL', 'DENY', 'EXPIRE', 'FAILURE', 'REFUND', 'CHARGEBACK'], true)) {
            // kalau settlement/capture tapi plan masih free, kita tetap coba upgrade
            if (in_array($status, ['SETTLEMENT', 'CAPTURE'], true)) {
                $this->activateProIfNotYet($ownerId, $orderId);
            }
            return;
        }

        // panggil status API midtrans
        $data = $this->midtransStatus($orderId);
        if (!$data) return;

        $transactionStatus = strtolower((string)($data['transaction_status'] ?? ''));
        $fraudStatus = (string)($data['fraud_status'] ?? '');
        $mapped = $this->mapStatus($transactionStatus);

        DB::exec(
            "UPDATE transactions SET status=?, raw_notification=? WHERE order_id=?",
            [$mapped, json_encode($data), $orderId]
        );

        $isPaid = false;
        if ($mapped === 'SETTLEMENT') $isPaid = true;
        if ($mapped === 'CAPTURE' && ($fraudStatus === '' || $fraudStatus === 'accept')) $isPaid = true;

        if ($isPaid) {
            $this->activateProIfNotYet($ownerId, $orderId);
        }
    }

    private function activateProIfNotYet(int $ownerId, string $orderId): void
    {
        $owner = DB::fetchOne("SELECT plan FROM owners WHERE id=?", [$ownerId]);
        if (($owner['plan'] ?? 'FREE') === 'PRO') return;

        // nonaktifkan subscription aktif lama
        DB::exec("UPDATE subscriptions SET status='INACTIVE', ended_at=NOW() WHERE owner_id=? AND status='ACTIVE'", [$ownerId]);

        // insert subscription PRO ACTIVE (pakai PDO agar dapat lastInsertId)
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("INSERT INTO subscriptions (owner_id, plan, status, started_at) VALUES (?, 'PRO', 'ACTIVE', NOW())");
        $stmt->execute([$ownerId]);
        $subId = (int)$pdo->lastInsertId();

        // link transaksi -> subscription
        DB::exec("UPDATE transactions SET subscription_id=? WHERE order_id=?", [$subId, $orderId]);

        // upgrade owner
        DB::exec("UPDATE owners SET plan='PRO', pro_since=NOW(), pro_until=NULL WHERE id=?", [$ownerId]);
    }

    private function midtransStatus(string $orderId): ?array
    {
        $cfg = $this->cfg();
        $serverKey = $cfg['server_key'];
        $isProd = (bool)$cfg['is_production'];

        $url = $isProd
            ? 'https://api.midtrans.com/v2/' . rawurlencode($orderId) . '/status'
            : 'https://api.sandbox.midtrans.com/v2/' . rawurlencode($orderId) . '/status';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($serverKey . ':'),
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res === false || $code < 200 || $code >= 300) return null;

        $json = json_decode($res, true);
        return is_array($json) ? $json : null;
    }

    private function mapStatus(string $s): string
    {
        $map = [
            'pending' => 'PENDING',
            'settlement' => 'SETTLEMENT',
            'capture' => 'CAPTURE',
            'deny' => 'DENY',
            'cancel' => 'CANCEL',
            'expire' => 'EXPIRE',
            'failure' => 'FAILURE',
            'refund' => 'REFUND',
            'chargeback' => 'CHARGEBACK',
            'partial_refund' => 'PARTIAL_REFUND',
            'partial_chargeback' => 'PARTIAL_CHARGEBACK',
        ];
        return $map[$s] ?? 'PENDING';
    }

    public function stats()
    {
        $ownerId = (int)Auth::id();

        // ambil plan terbaru
        $owner = DB::fetchOne("SELECT plan FROM owners WHERE id=?", [$ownerId]);
        $isPro = (($owner['plan'] ?? 'FREE') === 'PRO');

        if (!$isPro) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'PRO only']);
            return;
        }

        $today = date('Y-m-d');

        // === 14 hari terakhir (harian) ===
        $startDaily = date('Y-m-d', strtotime('-13 days'));
        $daily = DB::pdo()->prepare("
        SELECT qt.queue_date AS d, COUNT(*) AS c
        FROM queue_tickets qt
        JOIN branches b ON b.id=qt.branch_id
        JOIN businesses bs ON bs.id=b.business_id
        WHERE bs.owner_id=? AND qt.queue_date BETWEEN ? AND ?
        GROUP BY qt.queue_date
        ORDER BY qt.queue_date ASC
    ");
        $daily->execute([$ownerId, $startDaily, $today]);
        $rowsDaily = $daily->fetchAll() ?: [];

        // isi tanggal kosong supaya chart mulus
        $dailyLabels = [];
        $dailyValues = [];
        $map = [];
        foreach ($rowsDaily as $r) $map[$r['d']] = (int)$r['c'];

        for ($i = 0; $i < 14; $i++) {
            $d = date('Y-m-d', strtotime($startDaily . " +$i day"));
            $dailyLabels[] = $d;
            $dailyValues[] = $map[$d] ?? 0;
        }

        // === 6 bulan terakhir (bulanan) ===
        $monthLabels = [];
        $monthValues = [];

        // ambil 6 bulan termasuk bulan ini
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-$i month"));
            $months[] = $ym;
            $monthLabels[] = $ym;
        }

        // query range dari awal 6 bulan sampai akhir bulan ini
        $monthStart = $months[0] . "-01";
        $monthEnd   = date('Y-m-t');

        $monthly = DB::pdo()->prepare("
        SELECT DATE_FORMAT(qt.queue_date, '%Y-%m') AS ym, COUNT(*) AS c
        FROM queue_tickets qt
        JOIN branches b ON b.id=qt.branch_id
        JOIN businesses bs ON bs.id=b.business_id
        WHERE bs.owner_id=? AND qt.queue_date BETWEEN ? AND ?
        GROUP BY ym
        ORDER BY ym ASC
    ");
        $monthly->execute([$ownerId, $monthStart, $monthEnd]);
        $rowsMonthly = $monthly->fetchAll() ?: [];

        $mmap = [];
        foreach ($rowsMonthly as $r) $mmap[$r['ym']] = (int)$r['c'];

        foreach ($months as $ym) {
            $monthValues[] = $mmap[$ym] ?? 0;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'daily' => [
                'labels' => $dailyLabels,
                'values' => $dailyValues,
            ],
            'monthly' => [
                'labels' => $monthLabels,
                'values' => $monthValues,
            ],
        ]);
    }
}
