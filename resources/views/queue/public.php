<?php
// resources/views/queue/public.php
?>
<section class="page">
  <div class="container">
    <div class="page-head">
      <div>
        <div class="muted">Ambil Antrean</div>
        <h2 class="page-title"><?= $branch ? htmlspecialchars($branch['name']) : 'QueueNow' ?></h2>
        <?php if ($branch && !empty($branch['address'])): ?>
          <div class="muted"><?= htmlspecialchars($branch['address']) ?></div>
        <?php endif; ?>
      </div>
      <div class="page-actions">
        <a class="btn btn-ghost" href="<?= htmlspecialchars(base_url('/')) ?>">Beranda</a>
      </div>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($branch): ?>
      <div class="queue-public-grid">
        <div class="card card-glow queue-box">
          <div class="card-title">Live Queue Preview</div>

          <div class="preview-big" style="margin-top:10px;">
            <div class="muted">Sedang dipanggil</div>
            <div class="queue-number">
              <?= !empty($current['called']) ? 'A-' . (int)$current['called'] : '-' ?>
            </div>
          </div>

          <div class="preview-stats">
            <div class="stat card card-soft">
              <div class="muted">Antrean terakhir hari ini</div>
              <div class="stat-value"><?= (int)($current['last'] ?? 0) ?></div>
            </div>
            <div class="stat card card-soft">
              <div class="muted">Mode</div>
              <div class="stat-value">QR / Online</div>
            </div>
          </div>
        </div>

        <div class="card card-soft queue-box">
          <div class="card-title">Ambil Antrean Sekarang</div>
          <div class="card-text">Klik tombol untuk mendapatkan nomor antrean.</div>

          <form method="POST" action="<?= htmlspecialchars(base_url('/q/take')) ?>" style="margin-top:14px;">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CSRF::token()) ?>">
            <!-- PAKAI TOKEN DARI URL, bukan dari $branch['qr_token'] -->
            <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">

            <button class="btn btn-primary btn-lg w-full" type="submit" name="source" value="QR">
              Ambil Antrean
            </button>
          </form>

          <div class="muted" style="margin-top:12px;font-size:12px;">
            Setelah ambil antrean, tunggu panggilan di layar atau dari petugas.
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // refresh setiap 5 detik (ringan)
    setInterval(() => {
      const url = new URL(window.location.href);
      // tetap di halaman yang sama, tapi reload agar "Sedang dipanggil" & "Antrean terakhir" update
      window.location.replace(url.toString());
    }, 5000);
  </script>

</section>