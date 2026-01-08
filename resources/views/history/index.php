<?php
// resources/views/history/index.php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<section class="page">
  <div class="page-head">
    <div>
      <div class="muted">Riwayat Antrean</div>
      <h2 class="page-title">History</h2>
      <div class="muted">Paket: <b><?= $isPro ? 'PRO' : 'FREE' ?></b>
        <?php if (!$isPro): ?>
          • Batas tampilan: <b>1 bulan terakhir</b>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="card card-soft history-filter">
    <form method="GET" action="<?= h(base_url('/history')) ?>" class="history-form">
      <div class="history-row">
        <div>
          <label class="label">Cabang</label>
          <select class="input" name="branch_id">
            <?php foreach ($branches as $br): ?>
              <option value="<?= (int)$br['id'] ?>" <?= ((int)$br['id'] === (int)$branchId) ? 'selected' : '' ?>>
                <?= h($br['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="label">Mode</label>
          <select class="input" name="mode" onchange="this.form.submit()">
            <option value="daily" <?= $mode==='daily'?'selected':'' ?>>Harian</option>
            <option value="monthly" <?= $mode==='monthly'?'selected':'' ?>>Bulanan</option>
          </select>
        </div>

        <?php if ($mode === 'daily'): ?>
          <div>
            <label class="label">Tanggal</label>
            <input class="input" type="date" name="date"
                   value="<?= h($selectedDate) ?>"
                   <?= (!$isPro && $minDate) ? 'min="'.h($minDate).'"' : '' ?>>
          </div>
        <?php else: ?>
          <div>
            <label class="label">Bulan</label>
            <input class="input" type="month" name="ym"
                   value="<?= h($ym) ?>">
          </div>
        <?php endif; ?>

        <div class="history-actions">
          <label class="label">&nbsp;</label>
          <button class="btn btn-primary" type="submit">Terapkan</button>
        </div>
      </div>
    </form>
  </div>

  <?php if ($mode === 'daily'): ?>
    <div class="card card-soft table-wrap" style="margin-top:14px;">
      <table class="table">
        <thead>
          <tr>
            <th>No</th>
            <th>Source</th>
            <th>Status</th>
            <th>Diambil</th>
            <th>Dipanggil</th>
            <th>Selesai</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($dailyRows)): ?>
            <tr><td colspan="6" class="muted">Tidak ada data pada tanggal ini.</td></tr>
          <?php else: ?>
            <?php foreach ($dailyRows as $r): ?>
              <tr>
                <td><b><?= 'A-' . (int)$r['queue_number'] ?></b></td>
                <td><?= h($r['source']) ?></td>
                <td><?= h($r['status']) ?></td>
                <td class="muted"><?= h($r['taken_at']) ?></td>
                <td class="muted"><?= h($r['called_at'] ?? '-') ?></td>
                <td class="muted"><?= h($r['finished_at'] ?? '-') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php else: ?>
    <div class="history-grid" style="margin-top:14px;">
      <div class="card card-soft table-wrap">
        <div class="card-title">Ringkasan per Hari</div>
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
            <?php if (empty($monthlySummary)): ?>
              <tr><td colspan="5" class="muted">Tidak ada data bulan ini.</td></tr>
            <?php else: ?>
              <?php foreach ($monthlySummary as $s): ?>
                <tr>
                  <td><b><?= h($s['queue_date']) ?></b></td>
                  <td><?= (int)$s['total'] ?></td>
                  <td><?= (int)$s['done_count'] ?></td>
                  <td><?= (int)$s['skipped_count'] ?></td>
                  <td><?= (int)$s['cancelled_count'] ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="card card-soft table-wrap">
        <div class="card-title">Detail (maks 300 terakhir)</div>
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
            <?php if (empty($monthlyRows)): ?>
              <tr><td colspan="4" class="muted">Tidak ada detail bulan ini.</td></tr>
            <?php else: ?>
              <?php foreach ($monthlyRows as $r): ?>
                <tr>
                  <td><?= h($r['queue_date']) ?></td>
                  <td><b><?= 'A-' . (int)$r['queue_number'] ?></b></td>
                  <td><?= h($r['status']) ?></td>
                  <td class="muted"><?= h($r['taken_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!$isPro): ?>
    <div class="card card-soft hint" style="margin-top:14px;">
      <b>Free:</b> riwayat hanya 1 bulan terakhir.
      <a class="link" href="<?= h(base_url('/subscription/pricing')) ?>">Upgrade Pro →</a>
    </div>
  <?php endif; ?>
</section>
