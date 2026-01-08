<?php
// resources/views/home/landing.php
?>
<section class="hero">
    <div class="container hero-grid">
        <!-- LEFT -->
        <div class="hero-left">
            <div class="pill">SaaS Manajemen Antrian</div>

            <h1 class="hero-title">
                QueueNow — Kelola antrean bisnis lebih cepat & rapi
            </h1>

            <p class="hero-desc">
                Buat cabang, generate QR, terima antrean online/onsite, panggil/skip/selesai,
                lihat riwayat, dan upgrade ke Pro untuk laporan PDF/Excel & grafik.
            </p>

            <div class="hero-cta">
                <a class="btn btn-primary btn-lg" href="<?= htmlspecialchars(config('app.base_url')) ?>/register">Mulai Gratis</a>
                <a class="btn btn-secondary btn-lg" href="<?= htmlspecialchars(config('app.base_url')) ?>/login">Login Owner</a>
                <a class="btn btn-ghost btn-lg" href="<?= htmlspecialchars(config('app.base_url')) ?>/subscription/pricing">Lihat Pricing</a>
            </div>

            <div class="feature-mini-grid">
                <div class="card card-soft">
                    <div class="card-title">QR + Antrean Online</div>
                    <div class="card-text">
                        Customer scan QR untuk antrean di tempat atau ambil antrean online dari halaman publik.
                    </div>
                </div>

                <div class="card card-soft">
                    <div class="card-title">Dashboard Owner</div>
                    <div class="card-text">
                        Kelola cabang, panggil/skip/selesai, riwayat, dan upgrade ke Pro untuk grafik & laporan.
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="hero-right">
            <div class="card card-glow">
                <div class="card-head">
                    <div class="card-head-left">Live Queue Preview</div>
                    <div class="card-head-right">Demo</div>
                </div>

                <div class="preview-big">
                    <div class="muted">Sedang dipanggil</div>
                    <div class="queue-number">A-12</div>
                </div>

                <div class="preview-stats">
                    <div class="stat card card-soft">
                        <div class="muted">Antrean hari ini</div>
                        <div class="stat-value">48</div>
                    </div>
                    <div class="stat card card-soft">
                        <div class="muted">Status Paket</div>
                        <div class="stat-value">Free</div>
                    </div>
                </div>

                <div class="card card-soft pro-box">
                    <div class="card-title">Fitur Pro</div>
                    <ul class="list">
                        <li>Riwayat tanpa batas</li>
                        <li>Export PDF/Excel</li>
                        <li>Grafik harian/bulanan</li>
                    </ul>
                    <a class="link" href="<?= htmlspecialchars(config('app.base_url')) ?>/subscription/pricing">Upgrade Pro →</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container steps">
        <div class="steps-grid">
            <div class="card card-soft">
                <div class="step-title">1) Buat Cabang</div>
                <div class="card-text">Tambah cabang, alamat, jam operasional, dan nomor antrean awal.</div>
            </div>
            <div class="card card-soft">
                <div class="step-title">2) Share QR</div>
                <div class="card-text">Tempel QR di lokasi, customer scan untuk ambil antrean.</div>
            </div>
            <div class="card card-soft">
                <div class="step-title">3) Kelola Antrean</div>
                <div class="card-text">Panggil, skip, selesai, reset harian, dan lihat riwayat.</div>
            </div>
        </div>
    </div>
</section>