<?php
// app/controllers/SubscriptionController.php

class SubscriptionController extends Controller
{
    private function cfg(): array
    {
        // kalau kamu punya helper config(), pakai itu
        if (function_exists('config')) return config('midtrans');
        return require __DIR__ . '/../config/midtrans.php';
    }

    public function pricing()
    {
        $owner = Auth::user(); // dari tabel owners
        return View::render('subscription/pricing', [
            'title' => 'Upgrade Pro - QueueNow',
            'owner' => $owner,
            'proPrice' => (int)$this->cfg()['pro_price'],
            'error' => Session::flash('error'),
            'success' => Session::flash('success'),
        ], 'layouts/app');
    }

    public function checkout()
    {
        if (!CSRF::verify($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'CSRF token tidak valid.');
            redirect('/subscription/pricing');
        }

        $owner = Auth::user();
        $ownerId = (int)Auth::id();

        // sudah PRO? stop
        if (($owner['plan'] ?? 'FREE') === 'PRO') {
            Session::flash('success', 'Akun kamu sudah PRO.');
            redirect('/dashboard');
        }

        $cfg = $this->cfg();
        $amountInt = (int)$cfg['pro_price'];       // untuk midtrans (integer)
        $amountDb  = number_format($amountInt, 2, '.', ''); // untuk DB decimal (e.g. 99000.00)

        // order id unik
        $orderId = 'QN-PRO-' . $ownerId . '-' . date('YmdHis') . '-' . random_int(1000, 9999);

        // 1) insert transaksi PENDING
        DB::exec(
            "INSERT INTO transactions (owner_id, order_id, gross_amount, currency, status)
             VALUES (?, ?, ?, 'IDR', 'PENDING')",
            [$ownerId, $orderId, $amountDb]
        );

        // 2) create snap transaction (redirect mode)
        $finishUrl = base_url('/subscription/finish?order_id=' . urlencode($orderId));

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amountInt,
            ],
            'item_details' => [[
                'id' => 'PRO-PLAN',
                'price' => $amountInt,
                'quantity' => 1,
                'name' => 'QueueNow Pro Subscription',
            ]],
            'customer_details' => [
                'first_name' => $owner['business_name'] ?? ('Owner #' . $ownerId),
                'email' => $owner['email'] ?? '',
                'phone' => $owner['phone'] ?? '',
            ],
            'callbacks' => [
                'finish' => $finishUrl,
            ],
        ];

        $snap = $this->snapCreate($payload);
        if (!$snap || empty($snap['redirect_url'])) {
            Session::flash('error', 'Gagal membuat transaksi Midtrans. Cek Server Key / koneksi.');
            redirect('/subscription/pricing');
        }

        // 3) simpan token (dan redirect_url kalau kolom ada)
        $token = $snap['token'] ?? null;
        $redirectUrl = $snap['redirect_url'];

        // cek apakah kolom redirect_url ada (biar aman)
        $hasRedirectColumn = $this->columnExists('transactions', 'redirect_url');

        if ($hasRedirectColumn) {
            DB::exec(
                "UPDATE transactions SET snap_token=?, redirect_url=? WHERE order_id=?",
                [$token, $redirectUrl, $orderId]
            );
        } else {
            DB::exec(
                "UPDATE transactions SET snap_token=? WHERE order_id=?",
                [$token, $orderId]
            );
        }

        // 4) redirect ke Midtrans
        header('Location: ' . $redirectUrl);
        exit;
    }

    public function finish()
    {
        $orderId = (string)($_GET['order_id'] ?? '');
        $ownerId = (int)Auth::id();

        $trx = null;

        if ($orderId !== '') {
            // 1) ambil transaksi dari DB
            $trx = DB::fetchOne("SELECT * FROM transactions WHERE order_id=? AND owner_id=?", [$orderId, $ownerId]);

            // 2) kalau masih PENDING, coba sync ke Midtrans pakai Status API
            if ($trx) {
                $this->syncFromMidtransStatus($orderId);
                // refresh trx setelah sync
                $trx = DB::fetchOne("SELECT * FROM transactions WHERE order_id=? AND owner_id=?", [$orderId, $ownerId]);
            }
        }

        // refresh owner plan dari DB (biar gak ke-cache session lama)
        $owner = DB::fetchOne("SELECT plan FROM owners WHERE id=?", [$ownerId]);

        return View::render('subscription/finish', [
            'title' => 'Status Pembayaran - QueueNow',
            'orderId' => $orderId,
            'trx' => $trx,
            'plan' => $owner['plan'] ?? 'FREE',
            'error' => Session::flash('error'),
            'success' => Session::flash('success'),
        ], 'layouts/app');
    }

    private function syncFromMidtransStatus(string $orderId): void
    {
        $cfg = $this->cfg();
        $urlTpl = $cfg['is_production'] ? ($cfg['status_url_prod'] ?? '') : ($cfg['status_url_sandbox'] ?? '');
        if ($urlTpl === '') return;

        $url = sprintf($urlTpl, rawurlencode($orderId));
        $serverKey = $cfg['server_key'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($serverKey . ':'), // Basic auth :contentReference[oaicite:3]{index=3}
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res === false || $code < 200 || $code >= 300) return;

        $data = json_decode($res, true);
        if (!is_array($data)) return;

        $transactionStatus = strtolower((string)($data['transaction_status'] ?? ''));
        $fraudStatus = (string)($data['fraud_status'] ?? '');

        // update transactions.status sesuai enum kamu
        $mapped = $this->mapStatus($transactionStatus);
        DB::exec(
            "UPDATE transactions SET status=?, raw_notification=? WHERE order_id=?",
            [$mapped, json_encode($data), $orderId]
        );

        // kalau paid -> upgrade
        $isPaid = false;
        if ($mapped === 'SETTLEMENT') $isPaid = true;
        if ($mapped === 'CAPTURE' && ($fraudStatus === '' || $fraudStatus === 'accept')) $isPaid = true;

        if ($isPaid) {
            // ambil transaksi untuk owner_id + subscription_id
            $trx = DB::fetchOne("SELECT owner_id, subscription_id FROM transactions WHERE order_id=?", [$orderId]);
            if (!$trx) return;

            $ownerId = (int)$trx['owner_id'];
            $subId = $trx['subscription_id'] ?? null;

            if (!$subId) {
                DB::exec("UPDATE subscriptions SET status='INACTIVE', ended_at=NOW() WHERE owner_id=? AND status='ACTIVE'", [$ownerId]);

                $newSubId = DB::exec(
                    "INSERT INTO subscriptions (owner_id, plan, status, started_at) VALUES (?, 'PRO', 'ACTIVE', NOW())",
                    [$ownerId]
                );

                DB::exec("UPDATE transactions SET subscription_id=? WHERE order_id=?", [$newSubId, $orderId]);
            }

            DB::exec("UPDATE owners SET plan='PRO', pro_since=NOW(), pro_until=NULL WHERE id=?", [$ownerId]);
        }
    }

    private function mapStatus(string $s): string
    {
        $s = strtolower(trim($s));
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


    private function snapCreate(array $payload): ?array
    {
        $cfg = $this->cfg();
        $url = $cfg['is_production'] ? $cfg['snap_url_prod'] : $cfg['snap_url_sandbox'];

        $serverKey = $cfg['server_key'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                // Basic Auth: Base64(serverKey + ":")
                'Authorization: Basic ' . base64_encode($serverKey . ':'),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 20,
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res === false || $code < 200 || $code >= 300) return null;

        $json = json_decode($res, true);
        return is_array($json) ? $json : null;
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $row = DB::fetchOne(
                "SELECT COUNT(*) AS c
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?",
                [$table, $column]
            );
            return (int)($row['c'] ?? 0) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}
