<?php
// app/controllers/MidtransController.php

class MidtransController extends Controller
{
    private function cfg(): array
    {
        if (function_exists('config')) return config('midtrans');
        return require __DIR__ . '/../config/midtrans.php';
    }

    public function notify()
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
            return;
        }

        $cfg = $this->cfg();
        $serverKey = $cfg['server_key'];

        $orderId      = (string)($data['order_id'] ?? '');
        $statusCode   = (string)($data['status_code'] ?? '');
        $grossAmount  = (string)($data['gross_amount'] ?? '');
        $signatureKey = (string)($data['signature_key'] ?? '');

        // Signature validate: SHA512(order_id + status_code + gross_amount + serverkey)
        $localSig = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        if ($orderId === '' || $signatureKey === '' || !hash_equals($localSig, $signatureKey)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Invalid signature']);
            return;
        }

        // map status midtrans -> enum schema kamu (UPPERCASE)
        $trxStatusRaw = (string)($data['transaction_status'] ?? '');
        $trxStatus = $this->mapStatus($trxStatusRaw);

        $paymentType = (string)($data['payment_type'] ?? '');
        $trxTime     = $data['transaction_time'] ?? null;
        $fraudStatus = (string)($data['fraud_status'] ?? '');

        // update transaksi (kalau belum ada, bisa ignore atau insert)
        $existing = DB::fetchOne("SELECT id, owner_id, status, subscription_id FROM transactions WHERE order_id=?", [$orderId]);
        if (!$existing) {
            // kalau kamu mau strict: kalau tidak ada transaksi di DB, tetap balas OK supaya midtrans gak retry terus
            http_response_code(200);
            echo json_encode(['ok' => true, 'message' => 'order_id not found, ignored']);
            return;
        }

        DB::exec(
            "UPDATE transactions
             SET status=?, payment_type=?, transaction_time=?, raw_notification=?
             WHERE order_id=?",
            [
                $trxStatus,
                $paymentType ?: null,
                $trxTime,
                json_encode($data),
                $orderId
            ]
        );

        $ownerId = (int)$existing['owner_id'];

        // paid rules:
        // settlement => paid
        // capture => paid (umumnya CC), dan aman anggap sukses (fraud accept) :contentReference[oaicite:4]{index=4}
        $isPaid = false;
        if ($trxStatus === 'SETTLEMENT') $isPaid = true;
        if ($trxStatus === 'CAPTURE' && ($fraudStatus === '' || $fraudStatus === 'accept')) $isPaid = true;

        if ($isPaid) {
            // idempotent: kalau transaksi sudah punya subscription_id => jangan create ulang
            $subId = $existing['subscription_id'] ?? null;

            if (!$subId) {
                // 1) nonaktifkan subscription aktif lama
                DB::exec(
                    "UPDATE subscriptions SET status='INACTIVE', ended_at=NOW()
                     WHERE owner_id=? AND status='ACTIVE'",
                    [$ownerId]
                );

                // 2) create subscription PRO ACTIVE
                $newSubId = DB::exec(
                    "INSERT INTO subscriptions (owner_id, plan, status, started_at)
                     VALUES (?, 'PRO', 'ACTIVE', NOW())",
                    [$ownerId]
                );

                // 3) link ke transaksi
                DB::exec(
                    "UPDATE transactions SET subscription_id=? WHERE order_id=?",
                    [$newSubId, $orderId]
                );
            }

            // 4) update owner plan
            DB::exec(
                "UPDATE owners
                 SET plan='PRO', pro_since=NOW(), pro_until=NULL
                 WHERE id=?",
                [$ownerId]
            );
        }

        http_response_code(200);
        echo json_encode(['ok' => true]);
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
}
