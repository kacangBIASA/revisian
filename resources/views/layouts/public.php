<?php
// resources/views/layouts/public.php
?>
<!doctype html>
<html lang="id" data-theme="dark">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'QueueNow') ?></title>
  <link rel="stylesheet" href="/public/assets/css/app.css">
  <script src="/public/assets/js/app.js"></script>

</head>

<body>
  <header class="topbar">
    <div class="container">
      <div class="nav">
        <a class="brand">
          <span class="brand-badge">Q</span>
          <span class="brand-text">QueueNow</span>
        </a>

        <div class="nav-actions">
          <div class="theme-toggle" role="group" aria-label="Theme toggle">
            <button type="button" class="theme-btn" data-set-theme="dark" title="Tema Gelap">🌙</button>
            <button type="button" class="theme-btn" data-set-theme="light" title="Tema Terang">☀️</button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="app-main">
    <div class="container">
      <?= $content ?? '' ?>
    </div>
  </main>

  <div id="theme-fade" aria-hidden="true"></div>
  <script src="<?= htmlspecialchars(config('app.base_url')) ?>/public/assets/js/app.js"></script>
</body>

</html>