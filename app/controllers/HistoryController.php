<?php
// app/controllers/HistoryController.php

class HistoryController extends Controller
{
    private function isPro(): bool
    {
        $u = Auth::user();
        return isset($u['plan']) && $u['plan'] === 'PRO';
    }

    public function index()
    {
        $ownerId = Auth::id();
        $isPro = $this->isPro();

        // branches owner
        $stmt = DB::pdo()->prepare("
            SELECT b.id, b.name
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

        // validate ownership
        $ok = DB::fetchOne("
            SELECT b.id FROM branches b
            JOIN businesses bs ON bs.id=b.business_id
            WHERE b.id=? AND bs.owner_id=?
        ", [$branchId, $ownerId]);

        if (!$ok) {
            Session::flash('error', 'Cabang tidak valid.');
            redirect('/history');
        }

        // filter mode
        $mode = (string)($_GET['mode'] ?? 'daily'); // daily | monthly
        if (!in_array($mode, ['daily', 'monthly'], true)) $mode = 'daily';

        $today = date('Y-m-d');

        // batas FREE: 1 bulan terakhir
        $minDate = null;
        if (!$isPro) {
            $minDate = date('Y-m-d', strtotime('-30 days'));
        }

        // ===== DAILY =====
        $selectedDate = (string)($_GET['date'] ?? $today);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) $selectedDate = $today;

        // apply free limit
        if ($minDate && $selectedDate < $minDate) {
            $selectedDate = $minDate;
        }

        // ===== MONTHLY =====
        $ym = (string)($_GET['ym'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');

        $monthStart = $ym . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        if ($minDate && $monthEnd < $minDate) {
            // paksa ke bulan yang masih allowed
            $ym = date('Y-m', strtotime($minDate));
            $monthStart = $ym . '-01';
            $monthEnd = date('Y-m-t', strtotime($monthStart));
        }

        // ==== Fetch data ====
        $dailyRows = [];
        $monthlySummary = [];
        $monthlyRows = [];

        if ($mode === 'daily') {
            $sql = "
                SELECT id, queue_number, source, status, taken_at, called_at, finished_at
                FROM queue_tickets
                WHERE branch_id=? AND queue_date=?
            ";

            $params = [$branchId, $selectedDate];

            // limit FREE: jangan ambil data sebelum minDate (extra safety)
            if ($minDate) {
                $sql .= " AND queue_date >= ? ";
                $params[] = $minDate;
            }

            $sql .= " ORDER BY queue_number ASC";

            $q = DB::pdo()->prepare($sql);
            $q->execute($params);
            $dailyRows = $q->fetchAll() ?: [];
        } else {
            // summary per hari dalam bulan itu
            $sqlSum = "
                SELECT queue_date,
                       COUNT(*) AS total,
                       SUM(status='DONE') AS done_count,
                       SUM(status='SKIPPED') AS skipped_count,
                       SUM(status='CANCELLED') AS cancelled_count
                FROM queue_tickets
                WHERE branch_id=? AND queue_date BETWEEN ? AND ?
            ";
            $paramsSum = [$branchId, $monthStart, $monthEnd];

            if ($minDate) {
                $sqlSum .= " AND queue_date >= ? ";
                $paramsSum[] = $minDate;
            }

            $sqlSum .= " GROUP BY queue_date ORDER BY queue_date ASC";

            $s = DB::pdo()->prepare($sqlSum);
            $s->execute($paramsSum);
            $monthlySummary = $s->fetchAll() ?: [];

            // detail list bulan itu (opsional tampil terbatas 300 terakhir)
            $sqlList = "
                SELECT id, queue_date, queue_number, source, status, taken_at, called_at, finished_at
                FROM queue_tickets
                WHERE branch_id=? AND queue_date BETWEEN ? AND ?
            ";
            $paramsList = [$branchId, $monthStart, $monthEnd];

            if ($minDate) {
                $sqlList .= " AND queue_date >= ? ";
                $paramsList[] = $minDate;
            }

            $sqlList .= " ORDER BY queue_date DESC, queue_number DESC LIMIT 300";

            $l = DB::pdo()->prepare($sqlList);
            $l->execute($paramsList);
            $monthlyRows = $l->fetchAll() ?: [];
        }

        return View::render('history/index', [
            'title' => 'Riwayat Antrean - QueueNow',
            'branches' => $branches,
            'branchId' => $branchId,
            'mode' => $mode,
            'selectedDate' => $selectedDate,
            'ym' => $ym,
            'dailyRows' => $dailyRows,
            'monthlySummary' => $monthlySummary,
            'monthlyRows' => $monthlyRows,
            'isPro' => $isPro,
            'minDate' => $minDate,
            'error' => Session::flash('error'),
            'success' => Session::flash('success'),
        ], 'layouts/app');
    }
}
