<?php
// resources/views/branches/index.php
?>
<section class="page">
  <div class="page-head">
    <div>
      <div class="muted">Manajemen Cabang</div>
      <h2 class="page-title">Daftar Cabang</h2>
      <div class="muted">Akun: <b><?= $isPro ? 'PRO' : 'FREE' ?></b></div>
    </div>

    <div class="page-actions">
      <a class="btn btn-primary" href="<?= htmlspecialchars(base_url('/branches/create')) ?>">+ Tambah Cabang</a>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="card card-soft table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Cabang</th>
          <th>Alamat</th>
          <th>No. awal</th>
          <th>QR</th>
          <th>QR Token</th>
          <th style="width:220px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($branches)): ?>
        <tr><td colspan="6" class="muted">Belum ada cabang.</td></tr>
      <?php else: ?>
        <?php foreach ($branches as $b): ?>
          <tr>
            <td><b><?= htmlspecialchars($b['name']) ?></b></td>
            <td class="muted"><?= htmlspecialchars($b['address'] ?? '-') ?></td>
            <td><?= (int)$b['start_queue_number'] ?></td>

            <!-- QR Preview + Links -->
            <td>
              <div class="qr-cell">
                <img
                  class="qr-img"
                  src="<?= htmlspecialchars(base_url('/qr?token=' . $b['qr_token'])) ?>"
                  alt="QR"
                >
                <div class="qr-actions">
                  <a class="link" target="_blank"
                     href="<?= htmlspecialchars(base_url('/q?token=' . $b['qr_token'])) ?>">
                    Buka Link
                  </a>
                  <a class="link" target="_blank"
                     href="<?= htmlspecialchars(base_url('/qr?token=' . $b['qr_token'])) ?>">
                    Buka QR
                  </a>
                </div>
              </div>
            </td>

            <td class="mono"><?= htmlspecialchars(substr($b['qr_token'], 0, 12)) ?>...</td>

            <td>
              <a class="btn btn-secondary"
                 href="<?= htmlspecialchars(base_url('/branches/edit?id=' . (int)$b['id'])) ?>">
                Edit
              </a>

              <form method="POST"
                    action="<?= htmlspecialchars(base_url('/branches/delete?id=' . (int)$b['id'])) ?>"
                    style="display:inline">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CSRF::token()) ?>">
                <button class="btn btn-ghost" type="submit"
                        onclick="return confirm('Hapus cabang ini?')">
                  Hapus
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (!$isPro): ?>
    <div class="card card-soft hint">
      <b>Free:</b> maksimal 1 cabang.
      <a class="link" href="<?= htmlspecialchars(base_url('/subscription/pricing')) ?>">Upgrade Pro →</a>
    </div>
  <?php endif; ?>
</section>
