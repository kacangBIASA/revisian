<?php
// resources/views/auth/register.php

$old    = $old ?? [];
$google = $google ?? null;

if (!function_exists('oldv')) {
  function oldv(string $key, array $old, string $fallback = ''): string
  {
    $val = $old[$key] ?? $fallback;
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
  }
}

$isGoogle = is_array($google) && !empty($google['email']);
$titleText = $isGoogle ? 'Lengkapi Data Owner' : 'Daftar Owner';
$subText = $isGoogle
  ? 'Kamu daftar via Google. Lengkapi data bisnis untuk melanjutkan (email sudah terisi otomatis).'
  : 'Buat akun untuk mulai kelola antrean.';
?>

<section class="auth">
  <div class="container auth-wrap">
    <div class="auth-card card card-glow">

      <div class="auth-head">
        <div class="auth-title"><?= htmlspecialchars($titleText) ?></div>
        <div class="auth-sub"><?= htmlspecialchars($subText) ?></div>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= htmlspecialchars(base_url('/register')) ?>" class="form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CSRF::token()) ?>">

        <label class="label">Nama Bisnis</label>
        <input class="input"
          type="text"
          name="business_name"
          placeholder="Contoh: Kopi Senja"
          value="<?= oldv('business_name', $old) ?>"
          required>

        <label class="label">Kategori Bisnis</label>
        <input class="input"
          type="text"
          name="business_category"
          placeholder="Contoh: F&B / Klinik / Barbershop"
          value="<?= oldv('business_category', $old) ?>">

        <label class="label">Nomor Telepon</label>
        <input class="input"
          type="text"
          name="phone"
          placeholder="08xxxxxxxxxx"
          value="<?= oldv('phone', $old) ?>">

        <label class="label">Email</label>
        <input class="input"
          type="email"
          name="email"
          placeholder="owner@email.com"
          value="<?= oldv('email', $old) ?>"
          required
          <?= $isGoogle ? 'readonly' : '' ?>>

        <?php if ($isGoogle): ?>
          <div class="muted" style="margin-top:6px;font-size:12px;">
            Password akan dibuat otomatis karena kamu mendaftar via Google.
          </div>
        <?php else: ?>
          <label class="label">Password</label>
          <input class="input"
            type="password"
            name="password"
            placeholder="Minimal 8 karakter"
            required>

          <label class="label">Konfirmasi Password</label>
          <input class="input"
            type="password"
            name="password_confirm"
            placeholder="Ulangi password"
            required>
        <?php endif; ?>

        <button class="btn btn-primary w-full btn-lg" type="submit">
          <?= $isGoogle ? 'Simpan & Lanjutkan' : 'Daftar' ?>
        </button>
      </form>

      <?php if (!$isGoogle): ?>
        <div class="divider"><span>atau</span></div>

        <a class="btn btn-google w-full btn-lg" href="<?= htmlspecialchars(base_url('/auth/google')) ?>">
          <span class="g-icon">G</span>
          <span>Daftar dengan Google</span>
        </a>

        <div class="auth-foot">
          Sudah punya akun? <a class="link" href="<?= htmlspecialchars(base_url('/login')) ?>">Login</a>
        </div>
      <?php else: ?>
        <div class="auth-foot">
          Batal? <a class="link" href="<?= htmlspecialchars(base_url('/login')) ?>">Kembali ke Login</a>
        </div>
      <?php endif; ?>

    </div>
  </div>
</section>