<?php
// resources/views/queue/manage.php
$branchId = (int)$branch['id'];
function qnum($n)
{
  return 'A-' . (int)$n;
}

// ✅ FIX: pastikan $publicUrl ada
$publicUrl = $publicUrl ?? (isset($branch['qr_token']) ? base_url('/q?token=' . $branch['qr_token']) : '');
?>

<section class="page">
  <div class="page-head">
    <div>
      <div class="muted">Kelola Antrean</div>
      <h2 class="page-title"><?= htmlspecialchars($branch['name']) ?></h2>
      <div class="muted">
        Public link:
        <span class="mono"><?= $publicUrl !== '' ? htmlspecialchars($publicUrl ?? $publicurl ?? '') : '-' ?></span>
      </div>

    </div>

    <div class="page-actions">
      <form method="POST" action="<?= htmlspecialchars(base_url('/queues/call-next?branch_id=' . $branchId)) ?>" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CSRF::token()) ?>">
        <button class="btn btn-primary" type="submit">
          Panggil Berikutnya
        </button>
      </form>
      <!-- RESET MANUAL DIHAPUS -->
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="queue-manage-top">
    <div class="card card-glow queue-now">
      <div class="muted">Sedang dipanggil</div>
      <div class="queue-number"><?= $calledNow ? qnum($calledNow) : '-' ?></div>
    </div>

    <div class="card card-soft queue-branch-picker">
      <div class="card-title">Pilih Cabang</div>
      <div class="card-text">Ganti cabang untuk mengelola antrean.</div>
      <div style="margin-top:10px;">
        <?php foreach ($branches as $br): ?>
          <a class="btn <?= ((int)$br['id'] === $branchId) ? 'btn-primary' : 'btn-secondary' ?>"
            href="<?= htmlspecialchars(base_url('/queues/manage?branch_id=' . (int)$br['id'])) ?>">
            <?= htmlspecialchars($br['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- BOARD 4 KOLOM -->
  <div class="queue-board queue-board-4">

    <!-- WAITING -->
    <div class="card card-soft queue-col">
      <div class="queue-col-head">
        <div class="queue-col-title">Menunggu</div>
        <div class="queue-col-badge"><?= count($waiting) ?></div>
      </div>

      <?php if (empty($waiting)): ?>
        <div class="muted">Tidak ada antrean menunggu.</div>
      <?php else: ?>
        <div class="queue-list">
          <?php foreach ($waiting as $t): ?>
            <div class="queue-item">
              <div class="queue-left">
                <div class="queue-no"><?= qnum($t['queue_number']) ?></div>
                <div class="muted queue-meta"><?= htmlspecialchars($t['source']) ?> • <?= htmlspecialchars($t['taken_at']) ?></div>
              </div>

              <div class="queue-actions">
                <form method="POST"
                  action="<?= htmlspecialchars(base_url('/queues/action?branch_id=' . $branchId . '&id=' . (int)$t['id'])) ?>">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CSRF::token()) ?>">
                  <button class="btn btn-secondary btn-sm" type="submit" name="action" value="call">Panggil</button>
                  <button class="btn btn-ghost btn-sm" type="submit" name="action" value="skip">Skip</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- CALLED -->
    <div class="card card-soft queue-col">
      <div class="queue-col-head">
        <div class="queue-col-title">Dipanggil</div>
        <div class="queue-col-badge"><?= count($called) ?></div>
      </div>

      <?php if (empty($called)): ?>
        <div class="muted">Belum ada yang dipanggil.</div>
      <?php else: ?>
        <div class="queue-list">
          <?php foreach ($called as $t): ?>
            <div class="queue-item">
              <div class="queue-left">
                <div class="queue-no"><?= qnum($t['queue_number']) ?></div>
                <div class="muted queue-meta"><?= htmlspecialchars($t['source']) ?> • called: <?= htmlspecialchars($t['called_at'] ?? '-') ?></div>
              </div>

              <div class="queue-actions">
                <form method="POST"
                  action="<?= htmlspecialchars(base_url('/queues/action?branch_id=' . $branchId . '&id=' . (int)$t['id'])) ?>">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CSRF::token()) ?>">
                  <button class="btn btn-primary btn-sm" type="submit" name="action" value="done">Selesai</button>
                  <button class="btn btn-ghost btn-sm" type="submit" name="action" value="skip">Skip</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- SKIPPED -->
    <div class="card card-soft queue-col">
      <div class="queue-col-head">
        <div class="queue-col-title">Di-skip</div>
        <div class="queue-col-badge"><?= count($skipped) ?></div>
      </div>

      <?php if (empty($skipped)): ?>
        <div class="muted">Belum ada yang di-skip.</div>
      <?php else: ?>
        <div class="queue-list">
          <?php foreach ($skipped as $t): ?>
            <div class="queue-item">
              <div class="queue-left">
                <div class="queue-no"><?= qnum($t['queue_number']) ?></div>
                <div class="muted queue-meta"><?= htmlspecialchars($t['source']) ?> • finished: <?= htmlspecialchars($t['finished_at'] ?? '-') ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- DONE -->
    <div class="card card-soft queue-col">
      <div class="queue-col-head">
        <div class="queue-col-title">Selesai</div>
        <div class="queue-col-badge"><?= count($done) ?></div>
      </div>

      <?php if (empty($done)): ?>
        <div class="muted">Belum ada yang selesai.</div>
      <?php else: ?>
        <div class="queue-list">
          <?php foreach ($done as $t): ?>
            <div class="queue-item">
              <div class="queue-left">
                <div class="queue-no"><?= qnum($t['queue_number']) ?></div>
                <div class="muted queue-meta"><?= htmlspecialchars($t['source']) ?> • finished: <?= htmlspecialchars($t['finished_at'] ?? '-') ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</section>