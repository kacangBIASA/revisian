<?php
$status = $trx['status'] ?? '—';
?>
<section class="page">
  <div class="page-head">
    <div>
      <div class="muted">Pembayaran</div>
      <h2 class="page-title">Status Pembayaran</h2>
      <div class="muted">Order ID: <span class="mono"><?= htmlspecialchars($orderId) ?></span></div>
      <div class="muted">Status DB: <b><?= htmlspecialchars($status) ?></b></div>
      <div class="muted">Plan saat ini: <b><?= htmlspecialchars($plan) ?></b></div>
    </div>
    <div class="page-actions">
      <a class="btn btn-secondary" href="<?= htmlspecialchars(base_url('/dashboard')) ?>">Ke Dashboard</a>
      <a class="btn btn-ghost" href="<?= htmlspecialchars(base_url('/subscription/pricing')) ?>">Cek Pricing</a>
    </div>
  </div>

  <div class="card card-soft" style="padding:16px;">
    <div class="card-title">Catatan</div>
    <div class="muted" style="margin-top:6px;">
      Plan akan menjadi <b>PRO</b> setelah webhook Midtrans mengirim status <b>SETTLEMENT</b> / <b>CAPTURE</b>.
      Jika masih FREE, tunggu beberapa detik lalu refresh halaman.
    </div>
  </div>
</section>
