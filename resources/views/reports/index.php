<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rupiah($n){ return 'Rp ' . number_format((int)$n, 0, ',', '.'); }
?>
<section class="page">
  <div class="page-head">
    <div>
      <div class="muted">Laporan (PRO)</div>
      <h2 class="page-title">Report Antrean</h2>
      <div class="muted">Periode: <b><?= h($monthStart) ?></b> s/d <b><?= h($monthEnd) ?></b></div>
    </div>
  </div>

  <?php if (!empty($error)): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

  <div class="card card-soft" style="padding:14px;">
    <form method="GET" action="<?= h(base_url('/reports')) ?>" class="history-form">
      <div class="history-row" style="grid-template-columns: 1.2fr .8fr auto; align-items:end;">
        <div>
          <label class="label">Cabang</label>
          <select class="input" name="branch_id">
            <?php foreach ($branches as $br): ?>
              <option value="<?= (int)$br['id'] ?>" <?= ((int)$br['id']===(int)$branchId)?'selected':'' ?>>
                <?= h($br['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="label">Bulan</label>
          <input class="input" type="month" name="ym" value="<?= h($ym) ?>">
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button class="btn btn-primary" type="submit">Terapkan</button>

          <a class="btn btn-secondary"
             href="<?= h(base_url('/reports/excel?branch_id='.$branchId.'&ym='.$ym)) ?>">
             Export Excel (CSV)
          </a>

          <a class="btn btn-secondary"
             target="_blank"
             href="<?= h(base_url('/reports/pdf?branch_id='.$branchId.'&ym='.$ym)) ?>">
             Export PDF
          </a>
        </div>
      </div>
    </form>
  </div>

  <div class="dash-grid" style="margin-top:14px; grid-template-columns: repeat(4, 1fr);">
    <div class="card card-soft dash-card">
      <div class="muted">Total antrean</div>
      <div class="dash-num"><?= (int)$report['summary']['total'] ?></div>
    </div>

    <div class="card card-soft dash-card">
      <div class="muted">Selesai</div>
      <div class="dash-num"><?= (int)$report['summary']['done'] ?></div>
    </div>

    <div class="card card-soft dash-card">
      <div class="muted">Di-skip</div>
      <div class="dash-num"><?= (int)$report['summary']['skipped'] ?></div>
    </div>

    <div class="card card-glow dash-card">
      <div class="muted">Jam ramai</div>
      <div class="dash-num" style="font-size:20px;"><?= h($report['peak']['hour_label']) ?></div>
      <div class="muted" style="margin-top:6px;">Total: <b><?= (int)$report['peak']['count'] ?></b></div>
    </div>
  </div>

  <div class="history-grid" style="margin-top:14px;">
    <div class="card card-soft table-wrap">
      <div class="card-title">Antrean per Hari</div>
      <table class="table" style="margin-top:8px;">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Total</th>
            <th>Done</th>
            <th>Skipped</th>
            <th>Cancelled</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($report['perDay'])): ?>
            <tr><td colspan="5" class="muted">Tidak ada data.</td></tr>
          <?php else: ?>
            <?php foreach ($report['perDay'] as $r): ?>
              <tr>
                <td><b><?= h($r['date']) ?></b></td>
                <td><?= (int)$r['total'] ?></td>
                <td><?= (int)$r['done'] ?></td>
                <td><?= (int)$r['skipped'] ?></td>
                <td><?= (int)$r['cancelled'] ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card card-soft table-wrap">
      <div class="card-title">Detail (maks 500)</div>
      <table class="table" style="margin-top:8px;">
        <thead>
          <tr>
            <th>Tgl</th>
            <th>No</th>
            <th>Status</th>
            <th>Diambil</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($report['detail'])): ?>
            <tr><td colspan="4" class="muted">Tidak ada data.</td></tr>
          <?php else: ?>
            <?php foreach ($report['detail'] as $r): ?>
              <tr>
                <td><?= h($r['queue_date']) ?></td>
                <td><b><?= 'A-'.(int)$r['queue_number'] ?></b></td>
                <td><?= h($r['status']) ?></td>
                <td class="muted"><?= h($r['taken_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
