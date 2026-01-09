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
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($branch): ?>
      <div class="queue-public-grid">
        <!-- Live Preview -->
        <div class="card card-glow queue-box">
          <div class="card-title">Live Queue Preview</div>

          <div class="preview-big" style="margin-top:10px;">
            <div class="muted">Sedang dipanggil</div>
            <div class="queue-number" id="calledNumber">
              <?= !empty($current['called']) ? 'A-' . (int)$current['called'] : '-' ?>
            </div>
          </div>

          <div class="preview-stats">
            <div class="stat card card-soft">
              <div class="muted">Antrean terakhir hari ini</div>
              <div class="stat-value" id="lastNumber"><?= (int)($current['last'] ?? 0) ?></div>
            </div>
            <div class="stat card card-soft">
              <div class="muted">Mode</div>
              <div class="stat-value">QR / Online</div>
            </div>
          </div>
        </div>

        <!-- Tiket Saya -->
        <div class="card tiket-card" id="myTicketCard" style="display:none;">
          <div class="ticket-head">
            <div class="ticket-title">Tiket Saya</div>
            <div class="ticket-badge">TERSIMPAN</div>
          </div>

          <div class="tiket-number" id="myTicketNumber">A-0</div>
          <div class="ticket-meta" id="myTicketMeta"></div>

          <div class="ticket-actions">
            <button type="button" id="btnCopyTicket" class="btn btn-primary btn-sm">Salin Nomor</button>
            <button type="button" id="btnClearTicket" class="btn btn-ghost btn-sm">Hapus</button>
          </div>

          <div class="ticket-hint">
            Nomor ini tersimpan di perangkat kamu. Kalau halaman ke-refresh, tiket tetap muncul.
          </div>
        </div>

        <!-- Take Queue -->
        <div class="card card-soft queue-box">
          <div class="card-title">Ambil Antrean Sekarang</div>
          <div class="card-text">Klik tombol untuk mendapatkan nomor antrean.</div>

          <form id="takeQueueForm" method="POST" action="<?= htmlspecialchars(base_url('/q/take')) ?>" style="margin-top:14px;">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CSRF::token()) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">

            <button id="btnTakeQueue" class="btn btn-primary btn-lg w-full" type="submit" name="source" value="QR">
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
    // ====== Konfigurasi ======
    const QN = {
      branchId: <?= (int)$branch['id'] ?>,
      token: <?= json_encode((string)($token ?? '')) ?>,
      takeUrl: <?= json_encode(base_url('/q/take')) ?>,
      statusUrl: <?= json_encode(base_url('/q/status')) ?>,
      storageKey: `qn_ticket_${<?= (int)$branch['id'] ?>}`,
      csrf: <?= json_encode(CSRF::token()) ?>,
      // kalau kamu sudah set session ticket di controller publicTake/publicPage:
      sessionTicket: <?= json_encode($myTicket ?? null) ?> // contoh: "A-17"
    };

    function renderMyTicket(ticket) {
      const card = document.getElementById('myTicketCard');
      if (!card) return;

      document.getElementById('myTicketNumber').textContent = ticket.display || (`A-${ticket.queue_number}`);
      document.getElementById('myTicketMeta').textContent =
        `Status: ${ticket.status || 'WAITING'} • Tanggal: ${ticket.queue_date || ''}`;

      card.style.display = 'block';

      const btnTake = document.getElementById('btnTakeQueue');
      if (btnTake && (ticket.status === 'WAITING' || ticket.status === 'CALLED' || !ticket.status)) {
        btnTake.disabled = true;
        btnTake.textContent = 'Tiket sudah diambil';
      }
    }

    function loadTicketFromStorage() {
      const raw = localStorage.getItem(QN.storageKey);
      if (!raw) return null;
      try {
        return JSON.parse(raw);
      } catch (e) {
        localStorage.removeItem(QN.storageKey);
        return null;
      }
    }

    function saveTicketToStorage(ticket) {
      localStorage.setItem(QN.storageKey, JSON.stringify(ticket));
    }

    function initTicket() {
      // 1) prioritas: localStorage
      const stored = loadTicketFromStorage();
      if (stored) {
        renderMyTicket(stored);
        return;
      }

      // 2) fallback: session ticket dari server (kalau ada)
      if (QN.sessionTicket && typeof QN.sessionTicket === 'string') {
        // parse "A-17" => 17
        const m = QN.sessionTicket.match(/(\d+)/);
        const num = m ? parseInt(m[1], 10) : null;
        if (num) {
          const t = {
            queue_number: num,
            display: QN.sessionTicket,
            status: 'WAITING',
            queue_date: (new Date()).toISOString().slice(0, 10)
          };
          saveTicketToStorage(t);
          renderMyTicket(t);
        }
      }
    }

    async function takeQueueAjax(source = 'QR') {
      const btn = document.getElementById('btnTakeQueue');
      const form = document.getElementById('takeQueueForm');

      btn.disabled = true;
      const oldText = btn.textContent;
      btn.textContent = 'Memproses...';

      const body = new FormData();
      body.append('_csrf', QN.csrf);
      body.append('token', QN.token);
      body.append('source', source);

      const res = await fetch(QN.takeUrl, {
        method: 'POST',
        body,
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      // kalau server tidak balikin JSON (mis. masih redirect HTML), fallback submit normal
      const ct = res.headers.get('content-type') || '';
      if (!ct.includes('application/json')) {
        btn.disabled = false;
        btn.textContent = oldText;
        form.submit();
        return;
      }

      const data = await res.json();

      if (!res.ok || !data.ok) {
        alert(data.message || 'Gagal ambil antrean');
        btn.disabled = false;
        btn.textContent = oldText;
        return;
      }

      // simpan & tampilkan tiket
      const ticket = data.ticket || {};
      if (!ticket.display && ticket.queue_number) ticket.display = `A-${ticket.queue_number}`;
      saveTicketToStorage(ticket);
      renderMyTicket(ticket);

      alert(`Nomor antrean kamu: ${ticket.display}`);
    }

    async function refreshStatus() {
      if (!QN.token) return;

      try {
        const url = new URL(QN.statusUrl, window.location.origin);
        url.searchParams.set('token', QN.token);

        const res = await fetch(url.toString(), {
          headers: {
            'Accept': 'application/json'
          }
        });
        if (!res.ok) return;

        const data = await res.json();
        if (!data.ok) return;

        document.getElementById('calledNumber').textContent = data.called_display || '-';
        document.getElementById('lastNumber').textContent = data.last_number ?? 0;
      } catch (e) {
        // silent
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      initTicket();

      // intercept submit -> AJAX
      const form = document.getElementById('takeQueueForm');
      if (form) {
        form.addEventListener('submit', (e) => {
          e.preventDefault();
          takeQueueAjax('QR');
        });
      }

      // tombol copy/hapus
      const btnCopy = document.getElementById('btnCopyTicket');
      if (btnCopy) btnCopy.addEventListener('click', async () => {
        const t = loadTicketFromStorage();
        if (!t) return;
        await navigator.clipboard.writeText(String(t.display || t.queue_number || ''));
        alert('Nomor antrean disalin.');
      });

      const btnClear = document.getElementById('btnClearTicket');
      if (btnClear) btnClear.addEventListener('click', () => {
        localStorage.removeItem(QN.storageKey);
        location.reload();
      });

      // polling status tiap 5 detik TANPA reload halaman
      refreshStatus();
      setInterval(refreshStatus, 5000);
    });
  </script>
</section>