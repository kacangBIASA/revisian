<?php
// resources/views/branches/create.php
$old = $old ?? [];
function ov($k,$old){ return htmlspecialchars((string)($old[$k] ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<section class="page">
  <div class="page-head">
    <div>
      <div class="muted">Manajemen Cabang</div>
      <h2 class="page-title">Tambah Cabang</h2>
    </div>
    <div class="page-actions">
      <a class="btn btn-ghost" href="<?= htmlspecialchars(base_url('/branches')) ?>">← Kembali</a>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card card-glow form-card">
    <form method="POST" action="<?= htmlspecialchars(base_url('/branches')) ?>" class="form">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CSRF::token()) ?>">

      <label class="label">Nama Cabang</label>
      <input class="input" name="name" placeholder="Contoh: Cabang Dago" value="<?= ov('name',$old) ?>" required>

      <label class="label">Alamat</label>
      <textarea class="input" name="address" rows="3" placeholder="Alamat cabang..."><?= ov('address',$old) ?></textarea>

      <div class="grid-2">
        <div>
          <label class="label">Nomor antrean awal</label>
          <input class="input" type="number" name="start_queue_number" min="1" value="<?= ov('start_queue_number',$old) ?: '1' ?>">
        </div>
        <div>
          <label class="label">Jam operasional (opsional)</label>
          <div class="grid-2">
            <input class="input" type="time" name="open_time" value="<?= ov('open_time',$old) ?>">
            <input class="input" type="time" name="close_time" value="<?= ov('close_time',$old) ?>">
          </div>
        </div>
      </div>

      <button class="btn btn-primary btn-lg w-full" type="submit">Simpan Cabang</button>
    </form>
  </div>
</section>
