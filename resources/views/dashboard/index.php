<?php
// resources/views/dashboard/index.php

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$plan   = $owner['plan'] ?? 'FREE';
$isPro  = ($plan === 'PRO');
$bizName = $business['name'] ?? 'Bisnis';

$calledNumber = !empty($calledNow['queue_number']) ? ('A-' . (int)$calledNow['queue_number']) : '-';
$calledBranch = !empty($calledNow['branch_name']) ? $calledNow['branch_name'] : 'Belum ada';
?>
<section class="dash">
  <div class="dash-head">
    <div>
      <div class="muted">Dashboard Owner</div>
      <h2 class="dash-title"><?= h($bizName) ?></h2>
      <div class="dash-sub muted">
        Status paket: <b><?= h($plan) ?></b>
        <?php if ($isPro): ?>
          <span class="pill" style="margin-left:8px;">PRO ACTIVE</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="dash-actions">
      <a class="btn btn-secondary" href="<?= h(base_url('/branches')) ?>">Kelola Cabang</a>
      <a class="btn btn-primary" href="<?= h(base_url('/queues/manage')) ?>">Kelola Antrean</a>

      <?php if (!$isPro): ?>
        <a class="btn btn-primary" href="<?= h(base_url('/subscription/pricing')) ?>">Upgrade Pro</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="dash-grid">
    <div class="card card-soft dash-card">
      <div class="muted">Antrean hari ini</div>
      <div class="dash-value"><?= (int)$totalToday ?></div>
    </div>

    <div class="card card-soft dash-card">
      <div class="muted">Total antrean bulan ini</div>
      <div class="dash-value"><?= (int)$totalMonth ?></div>
    </div>

    <div class="card card-soft dash-card">
      <div class="muted">Sedang dipanggil</div>
      <div class="dash-value"><?= h($calledNumber) ?></div>
      <div class="muted" style="margin-top:6px;"><?= h($calledBranch) ?></div>
    </div>

    <div class="card card-glow dash-card">
      <div class="card-title">Shortcut</div>
      <div class="card-text">Akses cepat fitur utama untuk owner.</div>

      <div class="dash-shortcuts">
        <a class="btn btn-secondary w-full" href="<?= h(base_url('/history')) ?>">Riwayat</a>
        <a class="btn btn-secondary w-full" href="<?= h(base_url('/subscription/pricing')) ?>">Pricing</a>

        <?php if ($isPro): ?>
          <a class="btn btn-secondary w-full" href="<?= h(base_url('/reports')) ?>">Laporan</a>
        <?php else: ?>
          <a class="btn btn-ghost w-full" href="<?= h(base_url('/subscription/pricing')) ?>">Laporan -> Upgrade</a>
        <?php endif; ?>
      </div>

      <?php if (!$isPro): ?>
        <div class="muted" style="font-size:12px;margin-top:10px;">
          Grafik & export laporan hanya tersedia untuk <b>PRO</b>.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isPro): ?>
    <!-- GRAFIK PRO -->
    <div class="dash-charts" style="margin-top:14px;">
      <div class="card card-soft chart-card">
        <div class="card-title">Antrean Harian (14 hari)</div>
        <canvas id="chartDaily" height="110"></canvas>
        <div class="muted" style="font-size:12px;margin-top:8px;">
          Data diambil dari semua cabang.
        </div>
      </div>

      <div class="card card-soft chart-card">
        <div class="card-title">Antrean Bulanan (6 bulan)</div>
        <canvas id="chartMonthly" height="110"></canvas>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      (async function(){
        try{
          const res = await fetch("<?= h(base_url('/dashboard/stats')) ?>", { credentials: "same-origin" });
          const json = await res.json();
          if(!json || !json.ok) return;

          const dailyCtx = document.getElementById('chartDaily');
          if (dailyCtx) {
            new Chart(dailyCtx, {
              type: 'line',
              data: {
                labels: json.daily.labels,
                datasets: [{
                  label: 'Total antrean',
                  data: json.daily.values,
                  tension: 0.35
                }]
              },
              options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
              }
            });
          }

          const monthlyCtx = document.getElementById('chartMonthly');
          if (monthlyCtx) {
            new Chart(monthlyCtx, {
              type: 'bar',
              data: {
                labels: json.monthly.labels,
                datasets: [{
                  label: 'Total antrean',
                  data: json.monthly.values
                }]
              },
              options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
              }
            });
          }
        }catch(e){
          console.log(e);
        }
      })();
    </script>
  <?php endif; ?>
</section>
