<?php
// app/services/QueueService.php

class QueueService
{
    /**
     * Dipakai oleh admin/owner (manage queue).
     * Tetap dipertahankan agar tidak memutus flow yang sudah ada.
     */
    public function take(int $branchId, string $source): array
    {
        $pdo = DB::pdo();
        $today = date('Y-m-d');

        $source = strtoupper(trim($source));
        if (!in_array($source, ['QR', 'ONLINE'], true)) $source = 'QR';

        $pdo->beginTransaction();
        try {
            // lock counter row (buat kalau belum ada)
            $row = DB::fetchOne(
                "SELECT * FROM branch_daily_counters WHERE branch_id=? AND queue_date=? FOR UPDATE",
                [$branchId, $today]
            );

            if (!$row) {
                DB::exec(
                    "INSERT INTO branch_daily_counters (branch_id, queue_date, last_number, reset_at)
                     VALUES (?, ?, 0, NULL)",
                    [$branchId, $today]
                );
                $row = DB::fetchOne(
                    "SELECT * FROM branch_daily_counters WHERE branch_id=? AND queue_date=? FOR UPDATE",
                    [$branchId, $today]
                );
            }

            $next = (int)$row['last_number'] + 1;

            // update counter
            DB::exec(
                "UPDATE branch_daily_counters SET last_number=? WHERE id=?",
                [$next, (int)$row['id']]
            );

            // insert ticket
            $ticketId = DB::exec(
                "INSERT INTO queue_tickets (branch_id, queue_date, queue_number, source, status, taken_at)
                 VALUES (?, ?, ?, ?, 'WAITING', NOW())",
                [$branchId, $today, $next, $source]
            );

            $pdo->commit();
            return ['id' => (int)$ticketId, 'number' => $next, 'date' => $today];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Dipakai untuk customer (public queue) — versi aman:
     * - Rate limit per device & IP
     * - 1 device hanya boleh punya 1 tiket aktif per cabang per hari
     * - Nomor antrian dibuat aman via counter FOR UPDATE
     *
     * Return format dibuat gampang dipakai controller/AJAX.
     */
    public function takePublic(int $branchId, string $source, string $clientUuid, string $clientIp, int $startNo = 1): array
    {
        $pdo   = DB::pdo();
        $today = date('Y-m-d');

        $source = strtoupper(trim($source));
        if (!in_array($source, ['QR', 'ONLINE'], true)) $source = 'QR';

        $startNo = max(1, (int)$startNo);

        $pdo->beginTransaction();
        try {
            // =========================
            // (A) Rate limit
            // =========================

            // Per device: max 2 request / 60 detik
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM queue_tickets
                WHERE client_uuid = ?
                  AND taken_at >= (NOW() - INTERVAL 60 SECOND)
            ");
            $stmt->execute([$clientUuid]);
            if ((int)$stmt->fetchColumn() >= 2) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'http' => 429,
                    'message' => 'Terlalu sering ambil antrean. Coba lagi sebentar.'
                ];
            }

            // Per IP: max 5 request / 60 detik
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM queue_tickets
                WHERE client_ip = ?
                  AND taken_at >= (NOW() - INTERVAL 60 SECOND)
            ");
            $stmt->execute([$clientIp]);
            if ((int)$stmt->fetchColumn() >= 5) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'http' => 429,
                    'message' => 'Terlalu banyak permintaan dari jaringan ini. Coba lagi nanti.'
                ];
            }

            // ==========================================================
            // (B) 1 device = 1 tiket aktif per cabang per hari
            // ==========================================================
            $stmt = $pdo->prepare("
                SELECT id, queue_number, status, taken_at
                FROM queue_tickets
                WHERE branch_id = ?
                  AND queue_date = ?
                  AND client_uuid = ?
                  AND status IN ('WAITING','CALLED')
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$branchId, $today, $clientUuid]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $pdo->commit();
                $no = (int)$existing['queue_number'];

                return [
                    'ok' => true,
                    'http' => 200,
                    'is_existing' => true,
                    'message' => 'Kamu masih punya tiket aktif.',
                    'ticket' => [
                        'id' => (int)$existing['id'],
                        'queue_number' => $no,
                        'display' => 'A-' . $no,
                        'status' => (string)$existing['status'],
                        'queue_date' => $today,
                        'branch_id' => $branchId,
                        'taken_at' => $existing['taken_at'],
                    ]
                ];
            }

            // =========================
            // (C) Lock counter harian
            // =========================
            $stmt = $pdo->prepare("
                SELECT *
                FROM branch_daily_counters
                WHERE branch_id = ? AND queue_date = ?
                FOR UPDATE
            ");
            $stmt->execute([$branchId, $today]);
            $counter = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$counter) {
                DB::exec(
                    "INSERT INTO branch_daily_counters (branch_id, queue_date, last_number, reset_at)
                     VALUES (?, ?, 0, NULL)",
                    [$branchId, $today]
                );

                $stmt->execute([$branchId, $today]);
                $counter = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // Optional safety: sync jika counter ketinggalan dari data ticket
            $stmtMax = $pdo->prepare("
                SELECT COALESCE(MAX(queue_number), 0) AS mx
                FROM queue_tickets
                WHERE branch_id = ? AND queue_date = ?
            ");
            $stmtMax->execute([$branchId, $today]);
            $mxTicket = (int)$stmtMax->fetchColumn();

            $last = (int)$counter['last_number'];
            if ($mxTicket > $last) {
                $last = $mxTicket;
                DB::exec("UPDATE branch_daily_counters SET last_number=? WHERE id=?", [$last, (int)$counter['id']]);
            }

            $next = $last + 1;
            if ($next < $startNo) $next = $startNo;

            // Insert ticket (BUTUH kolom client_uuid & client_ip ada)
            $ticketId = DB::exec(
                "INSERT INTO queue_tickets (branch_id, queue_date, queue_number, source, client_uuid, client_ip, status, taken_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'WAITING', NOW())",
                [$branchId, $today, $next, $source, $clientUuid, $clientIp]
            );

            // Update counter
            DB::exec(
                "UPDATE branch_daily_counters SET last_number=? WHERE id=?",
                [$next, (int)$counter['id']]
            );

            $pdo->commit();

            return [
                'ok' => true,
                'http' => 200,
                'is_existing' => false,
                'message' => 'Tiket berhasil dibuat.',
                'ticket' => [
                    'id' => (int)$ticketId,
                    'queue_number' => $next,
                    'display' => 'A-' . $next,
                    'status' => 'WAITING',
                    'queue_date' => $today,
                    'branch_id' => $branchId,
                ]
            ];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return [
                'ok' => false,
                'http' => 500,
                'message' => 'Server error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reset antrian harian (dipakai admin).
     * BUG query yang kepotong sudah diperbaiki.
     */
    public function reset(int $branchId): void
    {
        $today = date('Y-m-d');

        // cancel sisa antrean hari ini (biar bersih)
        DB::exec(
            "UPDATE queue_tickets
             SET status='CANCELLED', finished_at=NOW()
             WHERE branch_id=? AND queue_date=? AND status IN ('WAITING','CALLED')",
            [$branchId, $today]
        );

        // reset counter (FIXED)
        $row = DB::fetchOne(
            "SELECT id FROM branch_daily_counters WHERE branch_id=? AND queue_date=?",
            [$branchId, $today]
        );

        if ($row) {
            DB::exec(
                "UPDATE branch_daily_counters SET last_number=0, reset_at=NOW() WHERE id=?",
                [(int)$row['id']]
            );
        } else {
            DB::exec(
                "INSERT INTO branch_daily_counters (branch_id, queue_date, last_number, reset_at)
                 VALUES (?, ?, 0, NOW())",
                [$branchId, $today]
            );
        }
    }
}
