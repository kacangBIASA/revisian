<?php
function rupiah($n){ return 'Rp ' . number_format((int)$n, 0, ',', '.'); }
$plan = $owner['plan'] ?? 'FREE';
?>
<section class="page">
  <div class="page-head">
    <div>
      <div class="muted">Subscription</div>
      <h2 class="page-title">Upgrade ke Pro</h2>
      <div class="muted">Plan saat ini: <b><?= htmlspecialchars($plan) ?></b></div>
    </div>
  </div>

  <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="pricing-grid">
    <div class="card card-soft pricing-card">
      <div class="card-title">Free</div>
      <div class="price"><?= rupiah(0) ?></div>
      <ul class="pricing-list">
        <li>Max 1 cabang</li>
        <li>Riwayat antrean 1 bulan terakhir</li>
        <li>Tanpa export PDF/Excel</li>
      </ul>
      <div style="margin-top:12px;">
        <button class="btn btn-ghost w-full" disabled><?= $plan==='FREE' ? 'Aktif' : '—' ?></button>
      </div>
    </div>

    <div class="card card-glow pricing-card">
      <div class="card-title">Pro</div>
      <div class="price"><?= rupiah($proPrice) ?></div>
      <ul class="pricing-list">
        <li>Cabang tanpa batas</li>
        <li>Riwayat antrean tanpa batas</li>
        <li>Grafik dashboard (Chart.js)</li>
        <li>Export PDF/Excel</li>
      </ul>

      <?php if ($plan === 'PRO'): ?>
        <button class="btn btn-primary btn-lg w-full" disabled>Sudah PRO</button>
      <?php else: ?>
        <form method="POST" action="<?= htmlspecialchars(base_url('/subscription/checkout')) ?>" style="margin-top:12px;">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CSRF::token()) ?>">
          <button class="btn btn-primary btn-lg w-full" type="submit">Upgrade via Midtrans</button>
        </form>
      <?php endif; ?>

      <div class="muted" style="margin-top:10px;font-size:12px;">
        Upgrade aktif otomatis setelah notifikasi Midtrans diterima (settlement/capture).
      </div>
    </div>
  </div>
</section>
