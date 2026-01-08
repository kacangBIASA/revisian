<?php
// resources/views/auth/login.php
?>
<section class="auth">
  <div class="container auth-wrap">
    <div class="auth-card card card-glow">
      <div class="auth-head">
        <div class="auth-title">Login Owner</div>
        <div class="auth-sub">Masuk untuk kelola cabang & antrean.</div>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= htmlspecialchars(base_url('/login')) ?>" class="form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CSRF::token()) ?>">

        <label class="label">Email</label>
        <input class="input" type="email" name="email" placeholder="owner@email.com" required>

        <label class="label">Password</label>
        <input class="input" type="password" name="password" placeholder="••••••••" required>

        <button class="btn btn-primary w-full btn-lg" type="submit">Login</button>
      </form>

      <div class="divider"><span>atau</span></div>

      <a class="btn btn-google w-full btn-lg" href="<?= htmlspecialchars(base_url('/auth/google')) ?>">
        <span class="g-icon">G</span>
        <span>Login dengan Google</span>
      </a>

      <div class="auth-foot">
        Belum punya akun? <a class="link" href="<?= htmlspecialchars(base_url('/register')) ?>">Daftar</a>
      </div>
    </div>
  </div>
</section>
