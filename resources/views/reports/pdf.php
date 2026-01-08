<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<main class="print">
  <div class="print-head">
    <h1>QueueNow - Laporan Antrean</h1>
    <div>Cabang ID: <b><?= (int)$branchId ?></b></div>
    <div>Periode: <b><?= h($monthStart) ?></b> s/d <b><?= h($monthEnd) ?></b></div>
    <div style="margin-top:10px;">
      <button onclick="window.print()">Print / Save as PDF</button>
    </div>
  </div>

  <section class="print-cards">
    <div class="card"><div class="k">Total</div><div class="v"><?= (int)$report['summary']['total'] ?></div></div>
    <div class="card"><div class="k">Done</div><div class="v"><?= (int)$report['summary']['done'] ?></div></div>
    <div class="card"><div class="k">Skipped</div><div class="v"><?= (int)$report['summary']['skipped'] ?></div></div>
    <div class="card"><div class="k">Jam Ramai</div><div class="v"><?= h($report['peak']['hour_label']) ?></div></div>
  </section>

  <h2>Antrean per Hari</h2>
  <table class="tbl">
    <thead>
      <tr><th>Tanggal</th><th>Total</th><th>Done</th><th>Skipped</th><th>Cancelled</th></tr>
    </thead>
    <tbody>
      <?php if (empty($report['perDay'])): ?>
        <tr><td colspan="5">Tidak ada data.</td></tr>
      <?php else: ?>
        <?php foreach ($report['perDay'] as $r): ?>
          <tr>
            <td><?= h($r['date']) ?></td>
            <td><?= (int)$r['total'] ?></td>
            <td><?= (int)$r['done'] ?></td>
            <td><?= (int)$r['skipped'] ?></td>
            <td><?= (int)$r['cancelled'] ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <h2>Detail (maks 200)</h2>
  <table class="tbl">
    <thead>
      <tr><th>Tgl</th><th>No</th><th>Status</th><th>Source</th><th>Diambil</th><th>Dipanggil</th><th>Selesai</th></tr>
    </thead>
    <tbody>
      <?php
        $detail = array_slice($report['detail'], 0, 200);
        if (empty($detail)):
      ?>
        <tr><td colspan="7">Tidak ada data.</td></tr>
      <?php else: ?>
        <?php foreach ($detail as $r): ?>
          <tr>
            <td><?= h($r['queue_date']) ?></td>
            <td><?= 'A-'.(int)$r['queue_number'] ?></td>
            <td><?= h($r['status']) ?></td>
            <td><?= h($r['source']) ?></td>
            <td><?= h($r['taken_at']) ?></td>
            <td><?= h($r['called_at'] ?: '-') ?></td>
            <td><?= h($r['finished_at'] ?: '-') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <style>
    body{ font-family: Arial, sans-serif; color:#111; }
    .print{ max-width: 980px; margin: 20px auto; padding: 0 14px; }
    .print-head{ border-bottom:1px solid #ddd; padding-bottom:12px; }
    .print-cards{ display:grid; grid-template-columns: repeat(4,1fr); gap:10px; margin:14px 0; }
    .card{ border:1px solid #ddd; border-radius:10px; padding:10px; }
    .k{ font-size:12px; color:#666; }
    .v{ font-size:18px; font-weight:800; margin-top:6px; }
    .tbl{ width:100%; border-collapse: collapse; margin-top:10px; }
    .tbl th, .tbl td{ border:1px solid #ddd; padding:8px; font-size:12px; }
    .tbl th{ background:#f5f5f5; text-align:left; }
    @media print{
      button{ display:none; }
      .print{ margin:0; }
    }
  </style>
</main>
