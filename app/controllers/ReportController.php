<?php
// app/controllers/ReportController.php

class ReportController extends Controller
{
    private function requirePro(): void
    {
        $ownerId = (int)Auth::id();
        $o = DB::fetchOne("SELECT plan FROM owners WHERE id=?", [$ownerId]);
        if (($o['plan'] ?? 'FREE') !== 'PRO') {
            Session::flash('error', 'Fitur laporan hanya untuk akun PRO.');
            redirect('/subscription/pricing');
        }
    }

    public function index()
    {
        $this->requirePro();

        $ownerId = (int)Auth::id();

        // branches milik owner
        $stmt = DB::pdo()->prepare("
            SELECT b.id, b.name
            FROM branches b
            JOIN businesses bs ON bs.id=b.business_id
            WHERE bs.owner_id=?
            ORDER BY b.id ASC
        ");
        $stmt->execute([$ownerId]);
        $branches = $stmt->fetchAll() ?: [];

        if (!$branches) {
            Session::flash('error', 'Buat cabang dulu untuk melihat laporan.');
            redirect('/branches');
        }

        $branchId = (int)($_GET['branch_id'] ?? 0);
        if ($branchId <= 0) $branchId = (int)$branches[0]['id'];

        // validasi ownership
        $ok = DB::fetchOne("
            SELECT b.id FROM branches b
            JOIN businesses bs ON bs.id=b.business_id
            WHERE b.id=? AND bs.owner_id=?
        ", [$branchId, $ownerId]);
        if (!$ok) {
            Session::flash('error', 'Cabang tidak valid.');
            redirect('/reports');
        }

        $ym = (string)($_GET['ym'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');

        $monthStart = $ym . '-01';
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        $data = $this->buildReport($branchId, $monthStart, $monthEnd);

        return View::render('reports/index', [
            'title' => 'Laporan - QueueNow',
            'branches' => $branches,
            'branchId' => $branchId,
            'ym' => $ym,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'report' => $data,
            'error' => Session::flash('error'),
            'success' => Session::flash('success'),
        ], 'layouts/app');
    }

    public function excel()
    {
        $this->requirePro();

        $ownerId  = (int)Auth::id();
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $ym       = (string)($_GET['ym'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');

        // validasi ownership
        $ok = DB::fetchOne("
            SELECT b.id FROM branches b
            JOIN businesses bs ON bs.id=b.business_id
            WHERE b.id=? AND bs.owner_id=?
        ", [$branchId, $ownerId]);
        if (!$ok) {
            http_response_code(403);
            echo "Cabang tidak valid.";
            return;
        }

        $monthStart = $ym . '-01';
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        $data = $this->buildReport($branchId, $monthStart, $monthEnd);

        // CSV (dibuka Excel)
        $filename = "QueueNow_Report_{$branchId}_{$ym}.csv";
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        $out = fopen('php://output', 'w');
        // BOM agar Excel Indonesia aman
        fwrite($out, "\xEF\xBB\xBF");

        // Sheet 1: Summary
        fputcsv($out, ['REPORT SUMMARY']);
        fputcsv($out, ['Branch ID', $branchId]);
        fputcsv($out, ['Period', $monthStart . ' s/d ' . $monthEnd]);
        fputcsv($out, ['Total Tickets', $data['summary']['total']]);
        fputcsv($out, ['Done', $data['summary']['done']]);
        fputcsv($out, ['Skipped', $data['summary']['skipped']]);
        fputcsv($out, ['Cancelled', $data['summary']['cancelled']]);
        fputcsv($out, ['Peak Hour', $data['peak']['hour_label']]);
        fputcsv($out, ['Peak Count', $data['peak']['count']]);
        fputcsv($out, []);

        // Sheet 2: Per day
        fputcsv($out, ['PER DAY']);
        fputcsv($out, ['Date', 'Total', 'Done', 'Skipped', 'Cancelled']);
        foreach ($data['perDay'] as $r) {
            fputcsv($out, [$r['date'], $r['total'], $r['done'], $r['skipped'], $r['cancelled']]);
        }
        fputcsv($out, []);

        // Sheet 3: Detail
        fputcsv($out, ['DETAIL (LIMIT 500)']);
        fputcsv($out, ['Date', 'Queue No', 'Status', 'Source', 'Taken At', 'Called At', 'Finished At']);
        foreach ($data['detail'] as $r) {
            fputcsv($out, [
                $r['queue_date'],
                'A-'.$r['queue_number'],
                $r['status'],
                $r['source'],
                $r['taken_at'],
                $r['called_at'] ?: '-',
                $r['finished_at'] ?: '-',
            ]);
        }

        fclose($out);
    }

    public function pdf()
    {
        $this->requirePro();

        $ownerId  = (int)Auth::id();
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $ym       = (string)($_GET['ym'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');

        // validasi ownership
        $ok = DB::fetchOne("
            SELECT b.id FROM branches b
            JOIN businesses bs ON bs.id=b.business_id
            WHERE b.id=? AND bs.owner_id=?
        ", [$branchId, $ownerId]);
        if (!$ok) {
            http_response_code(403);
            echo "Cabang tidak valid.";
            return;
        }

        $monthStart = $ym . '-01';
        $monthEnd   = date('Y-m-t', strtotime($monthStart));
        $data = $this->buildReport($branchId, $monthStart, $monthEnd);

        // Tanpa composer: opsi paling stabil -> render HTML printable
        // User bisa "Print -> Save as PDF"
        // Kalau nanti kamu mau dompdf manual, aku bisa kasih versi integrasinya.
        return View::render('reports/pdf', [
            'title' => 'Laporan PDF - QueueNow',
            'branchId' => $branchId,
            'ym' => $ym,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'report' => $data,
        ], 'layouts/print');
    }

    private function buildReport(int $branchId, string $from, string $to): array
    {
        // SUMMARY
        $sum = DB::fetchOne("
            SELECT
              COUNT(*) AS total,
              SUM(status='DONE') AS done_count,
              SUM(status='SKIPPED') AS skipped_count,
              SUM(status='CANCELLED') AS cancelled_count
            FROM queue_tickets
            WHERE branch_id=? AND queue_date BETWEEN ? AND ?
        ", [$branchId, $from, $to]) ?: [];

        $summary = [
            'total' => (int)($sum['total'] ?? 0),
            'done' => (int)($sum['done_count'] ?? 0),
            'skipped' => (int)($sum['skipped_count'] ?? 0),
            'cancelled' => (int)($sum['cancelled_count'] ?? 0),
        ];

        // PEAK HOUR (ambil jam dari taken_at)
        $peakRow = DB::fetchOne("
            SELECT HOUR(taken_at) AS h, COUNT(*) AS c
            FROM queue_tickets
            WHERE branch_id=? AND queue_date BETWEEN ? AND ?
            GROUP BY HOUR(taken_at)
            ORDER BY c DESC
            LIMIT 1
        ", [$branchId, $from, $to]);

        $peak = [
            'hour' => $peakRow ? (int)$peakRow['h'] : null,
            'count' => $peakRow ? (int)$peakRow['c'] : 0,
            'hour_label' => $peakRow ? sprintf('%02d:00 - %02d:59', (int)$peakRow['h'], (int)$peakRow['h']) : '-',
        ];

        // PER DAY
        $stmt = DB::pdo()->prepare("
            SELECT queue_date,
                   COUNT(*) AS total,
                   SUM(status='DONE') AS done_count,
                   SUM(status='SKIPPED') AS skipped_count,
                   SUM(status='CANCELLED') AS cancelled_count
            FROM queue_tickets
            WHERE branch_id=? AND queue_date BETWEEN ? AND ?
            GROUP BY queue_date
            ORDER BY queue_date ASC
        ");
        $stmt->execute([$branchId, $from, $to]);
        $rows = $stmt->fetchAll() ?: [];

        $perDay = [];
        foreach ($rows as $r) {
            $perDay[] = [
                'date' => $r['queue_date'],
                'total' => (int)$r['total'],
                'done' => (int)$r['done_count'],
                'skipped' => (int)$r['skipped_count'],
                'cancelled' => (int)$r['cancelled_count'],
            ];
        }

        // DETAIL (LIMIT 500)
        $d = DB::pdo()->prepare("
            SELECT queue_date, queue_number, source, status, taken_at, called_at, finished_at
            FROM queue_tickets
            WHERE branch_id=? AND queue_date BETWEEN ? AND ?
            ORDER BY queue_date DESC, queue_number DESC
            LIMIT 500
        ");
        $d->execute([$branchId, $from, $to]);
        $detail = $d->fetchAll() ?: [];

        return [
            'summary' => $summary,
            'peak' => $peak,
            'perDay' => $perDay,
            'detail' => $detail,
        ];
    }
}
