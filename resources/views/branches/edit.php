<?php
// resources/views/branches/edit.php
$b = $branch;
?>
<section class="page">
  <div class="page-head">
    <div>
      <div class="muted">Manajemen Cabang</div>
      <h2 class="page-title">Edit Cabang</h2>
    </div>
    <div class="page-actions">
      <a class="btn btn-ghost" href="<?= htmlspecialchars(base_url('/branches')) ?>">← Kembali</a>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card card-glow form-card">
    <form method="POST"
          action="<?= htmlspecialchars(base_url('/branches/update?id=' . (int)$b['id'])) ?>"
          class="form">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CSRF::token()) ?>">

      <label class="label">Nama Cabang</label>
      <input class="input" name="name" value="<?= htmlspecialchars($b['name']) ?>" required>

      <label class="label">Alamat</label>
      <textarea class="input" name="address" rows="3"><?= htmlspecialchars($b['address'] ?? '') ?></textarea>

      <div class="grid-2">
        <div>
          <label class="label">Nomor antrean awal</label>
          <input class="input" type="number" name="start_queue_number" min="1"
                 value="<?= (int)$b['start_queue_number'] ?>">
        </div>
        <div>
          <label class="label">Jam operasional (opsional)</label>
          <div class="grid-2">
            <input class="input" type="time" name="open_time" value="<?= htmlspecialchars($open ?? '') ?>">
            <input class="input" type="time" name="close_time" value="<?= htmlspecialchars($close ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="muted" style="margin-top:8px;">
        QR Token: <span class="mono"><?= htmlspecialchars($b['qr_token']) ?></span>
      </div>

      <!-- QR Preview Besar + Link Publik -->
      <div style="margin-top:14px;">
        <div class="muted">QR Cabang</div>
        <img class="qr-img-lg"
             src="<?= htmlspecialchars(base_url('/qr?token=' . $b['qr_token'])) ?>"
             alt="QR Cabang">
        <div class="muted" style="margin-top:8px;">
          Link publik:
          <span class="mono"><?= htmlspecialchars(base_url('/q?token=' . $b['qr_token'])) ?></span>
        </div>
        <div style="margin-top:8px; display:flex; gap:10px; flex-wrap:wrap;">
          <a class="btn btn-secondary" target="_blank"
             href="<?= htmlspecialchars(base_url('/q?token=' . $b['qr_token'])) ?>">
            Buka Halaman Publik
          </a>
          <a class="btn btn-secondary" target="_blank"
             href="<?= htmlspecialchars(base_url('/qr?token=' . $b['qr_token'])) ?>">
            Buka QR PNG
          </a>
        </div>
      </div>

      <button class="btn btn-primary btn-lg w-full" type="submit" style="margin-top:14px;">
        Update Cabang
      </button>
    </form>
  </div>
</section>
