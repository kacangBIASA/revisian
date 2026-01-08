<?php
// app/services/QueueService.php

class QueueService
{
    public function take(int $branchId, string $source): array
    {
        $pdo = DB::pdo();
        $today = date('Y-m-d');

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
            return ['id' => $ticketId, 'number' => $next, 'date' => $today];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

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

        // reset counter
        $row = DB::fetchOne("SELECT id FROM branch_daily_counters WHERE branch_id=? AND queue_date=?", [$branchId, $today]);
        if ($row) {
            DB::exec("UPDATE branch_daily_counters SET last_number=0, reset_at=NOW() WHERE id=?", [(int)$row['id']]);
        } else {
            DB::exec(
                "INSERT INTO branch_daily_counters (branch_id, queue_date, last_number, reset_at)
                 VALUES (?, ?, 0, NOW())",
                [$branchId, $today]
            );
        }
    }
}
