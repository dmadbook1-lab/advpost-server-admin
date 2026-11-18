<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Credits</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="<?php echo base_url('assetsNew/dist/css/admin-theme.css') ?>?v=7">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
  .stat-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:14px; margin-bottom:18px; }
  .stat { background:#fff; border-radius:16px; padding:16px 18px; box-shadow:0 4px 16px rgba(80,50,160,.08);
          animation: rise .4s ease both; }
  .stat b { display:block; font-size:28px; line-height:1.1; color:#1f2340; }
  .stat span { font-size:13px; color:#6b6f8a; }
  .stat.accent { background:linear-gradient(135deg,#5b21b6,#8b5cf6); }
  .stat.accent b, .stat.accent span { color:#fff; }
  @keyframes rise { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
  .nav-tabs .nav-link { color:#6b6f8a; font-weight:600; border-radius:12px 12px 0 0; }
  .nav-tabs .nav-link.active { color:#5b21b6; border-bottom:3px solid #6d3df0; }
  .tx { padding:3px 10px; border-radius:999px; color:#fff; font-size:12px; font-weight:600; white-space:nowrap; }
  .tx-purchase { background:#16a34a; } .tx-admin_grant { background:#6d3df0; } .tx-bonus { background:#0ea5e9; }
  .tx-spend { background:#f59e0b; } .tx-refund { background:#14b8a6; } .tx-admin_deduct { background:#ef4444; }
  .pos { color:#16a34a; font-weight:700; } .neg { color:#dc2626; font-weight:700; }
  #userResults { position:absolute; z-index:20; background:#fff; width:100%; border-radius:12px;
                 box-shadow:0 10px 30px rgba(40,20,100,.18); max-height:260px; overflow:auto; }
  #userResults button { display:block; width:100%; text-align:left; padding:9px 14px; border:0; background:none; }
  #userResults button:hover { background:#f1ecff; }
  .pkg-off { opacity:.5; }
</style>
</head>
<body>
<?php $this->load->view('landing/sidebar'); ?>
<?php $labels = ['purchase'=>'Purchase','admin_grant'=>'Admin added','admin_deduct'=>'Admin removed','spend'=>'Used','refund'=>'Refund','bonus'=>'Bonus']; ?>
<div class="content-wrapper">
<section class="content-header"><div class="container-fluid">
  <h1>Credits</h1>
  <p class="text-muted mb-0">Packages users can buy, what each AI action costs, and who holds how many credits</p>
</div></section>
<section class="content"><div class="container-fluid">

<?php if ($this->session->flashdata('msg')): ?>
  <div class="alert alert-<?= htmlspecialchars($this->session->flashdata('msg_kind') ?: 'success') ?>"><?= htmlspecialchars($this->session->flashdata('msg')) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3" id="creditTabs">
  <?php foreach (['overview'=>'Overview','assign'=>'Assign credits','packages'=>'Packages','pricing'=>'Pricing','ledger'=>'Ledger'] as $k=>$l): ?>
    <li class="nav-item"><button class="nav-link <?= $tab===$k?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#tab-<?= $k ?>" type="button"><?= $l ?></button></li>
  <?php endforeach; ?>
</ul>

<div class="tab-content">

<!-- OVERVIEW -->
<div class="tab-pane fade <?= $tab==='overview'?'show active':'' ?>" id="tab-overview">
  <div class="stat-row">
    <div class="stat accent"><b><?= number_format($stats['in_circulation']) ?></b><span>Credits held by users</span></div>
    <div class="stat"><b><?= number_format($stats['ever_issued']) ?></b><span>Credits ever issued</span></div>
    <div class="stat"><b><?= number_format($stats['ever_used']) ?></b><span>Credits used</span></div>
    <div class="stat"><b>₹<?= number_format($stats['revenue_inr'], 2) ?></b><span>Revenue (<?= (int)$stats['paid_orders'] ?> paid orders)</span></div>
  </div>
  <div class="card shadow"><div class="card-body">
    <h6 class="mb-3">How it works</h6>
    <ul class="mb-0 text-muted">
      <li>Users buy a <b>package</b> in the app (Razorpay). Credits are added after the payment is verified.</li>
      <li>Each AI Photo / AI Video costs the <b>price</b> set in the Pricing tab and is taken before generation starts. A failed generation is refunded.</li>
      <li>Use <b>Assign credits</b> to give or take credits for any user, with a note. Everything is recorded in the <b>Ledger</b>.</li>
    </ul>
  </div></div>
</div>

<!-- ASSIGN -->
<div class="tab-pane fade <?= $tab==='assign'?'show active':'' ?>" id="tab-assign">
  <div class="card shadow" style="max-width:640px"><div class="card-body">
    <h5 class="mb-3">Give or take credits</h5>
    <form method="post" action="<?= base_url('creditadmin/assign') ?>" autocomplete="off">
      <div class="mb-3 position-relative">
        <label class="form-label">User (name, mobile, email or ID)</label>
        <input type="text" id="userSearch" class="form-control" placeholder="Start typing to search…">
        <input type="hidden" name="user_id" id="userId">
        <div id="userResults" class="d-none"></div>
        <div class="form-text" id="userPicked"></div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="form-label">Action</label>
          <select name="mode" class="form-select">
            <option value="grant">Add credits</option>
            <option value="deduct">Remove credits</option>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Credits</label>
          <input type="number" name="credits" min="1" max="100000" class="form-control" required>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Note (shown in the ledger and the user's history)</label>
        <input type="text" name="note" maxlength="200" class="form-control" placeholder="e.g. Welcome bonus, refund for failed video">
      </div>
      <button class="btn btn-primary" id="assignBtn" disabled>Apply</button>
    </form>
  </div></div>
</div>

<!-- PACKAGES -->
<div class="tab-pane fade <?= $tab==='packages'?'show active':'' ?>" id="tab-packages">
  <div class="card shadow mb-3"><div class="card-body">
    <h5 class="mb-3">Add a package</h5>
    <form method="post" action="<?= base_url('creditadmin/save_package') ?>" class="row g-2 align-items-end">
      <input type="hidden" name="id" value="0">
      <div class="col-md-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
      <div class="col-md-2"><label class="form-label">Credits</label><input type="number" name="credits" min="1" class="form-control" required></div>
      <div class="col-md-2"><label class="form-label">Bonus</label><input type="number" name="bonus_credits" min="0" value="0" class="form-control"></div>
      <div class="col-md-2"><label class="form-label">Price (₹)</label><input type="number" step="0.01" min="1" name="price_inr" class="form-control" required></div>
      <div class="col-md-2"><label class="form-label">Badge</label><input name="badge" maxlength="40" class="form-control" placeholder="Popular"></div>
      <div class="col-md-1"><input type="hidden" name="is_active" value="1"><button class="btn btn-primary w-100">Add</button></div>
    </form>
  </div></div>

  <div class="card shadow"><div class="card-body"><div class="table-responsive">
    <table class="table align-middle">
      <thead style="background:#e8e0ff"><tr><th>Order</th><th>Name</th><th>Credits</th><th>Bonus</th><th>Price ₹</th><th>Badge</th><th>Visible</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($packages as $p): ?>
        <tr class="<?= $p->is_active ? '' : 'pkg-off' ?>">
          <form method="post" action="<?= base_url('creditadmin/save_package') ?>">
            <input type="hidden" name="id" value="<?= (int)$p->id ?>">
            <td style="width:80px"><input type="number" name="sort_order" value="<?= (int)$p->sort_order ?>" class="form-control form-control-sm"></td>
            <td><input name="name" value="<?= htmlspecialchars($p->name) ?>" class="form-control form-control-sm" required></td>
            <td style="width:100px"><input type="number" name="credits" min="1" value="<?= (int)$p->credits ?>" class="form-control form-control-sm"></td>
            <td style="width:100px"><input type="number" name="bonus_credits" min="0" value="<?= (int)$p->bonus_credits ?>" class="form-control form-control-sm"></td>
            <td style="width:120px"><input type="number" step="0.01" min="1" name="price_inr" value="<?= htmlspecialchars($p->price_inr) ?>" class="form-control form-control-sm"></td>
            <td><input name="badge" value="<?= htmlspecialchars((string)$p->badge) ?>" class="form-control form-control-sm"></td>
            <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= $p->is_active ? 'checked' : '' ?>></div></td>
            <td class="text-nowrap"><button class="btn btn-sm btn-primary">Save</button></td>
          </form>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <small class="text-muted">Turn "Visible" off to hide a package from the app. Past purchases are kept.</small>
  </div></div>
</div>

<!-- PRICING -->
<div class="tab-pane fade <?= $tab==='pricing'?'show active':'' ?>" id="tab-pricing">
  <div class="card shadow" style="max-width:520px"><div class="card-body">
    <h5 class="mb-3">Credits per action</h5>
    <form method="post" action="<?= base_url('creditadmin/save_costs') ?>">
      <div class="mb-3"><label class="form-label">AI Photo (one image)</label>
        <input type="number" min="0" name="cost_ai_photo" value="<?= (int)$costs['ai_photo'] ?>" class="form-control"></div>
      <div class="mb-3"><label class="form-label">AI Video (one video)</label>
        <input type="number" min="0" name="cost_ai_video" value="<?= (int)$costs['ai_video'] ?>" class="form-control"></div>
      <p class="text-muted small">Set 0 to make an action free.</p>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="videoEnabled" name="video_enabled" value="1" <?= $video_enabled ? 'checked' : '' ?>>
        <label class="form-check-label" for="videoEnabled"><b>AI Video is available</b><br>
          <span class="text-muted small">When off, the app shows "We will get back to you soon" and nobody can generate or be charged for videos.</span></label>
      </div>
      <button class="btn btn-primary">Save prices</button>
    </form>
  </div></div>
</div>

<!-- LEDGER -->
<div class="tab-pane fade <?= $tab==='ledger'?'show active':'' ?>" id="tab-ledger">
  <form method="get" action="<?= base_url('creditadmin') ?>" class="d-flex gap-2 mb-3 flex-wrap">
    <input type="hidden" name="tab" value="ledger">
    <select name="type" class="form-select" style="max-width:200px">
      <option value="">All types</option>
      <?php foreach ($labels as $k=>$l): ?><option value="<?= $k ?>" <?= $filter_type===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?>
    </select>
    <input type="number" name="user" value="<?= $filter_user ?: '' ?>" class="form-control" style="max-width:160px" placeholder="User ID">
    <button class="btn btn-outline-primary">Filter</button>
  </form>
  <div class="card shadow"><div class="card-body"><div class="table-responsive">
    <table id="ledgerTable" class="table table-bordered table-striped" style="width:100%">
      <thead style="background:#e8e0ff"><tr><th>#</th><th>When</th><th>User</th><th>Type</th><th>Credits</th><th>Balance after</th><th>Note</th></tr></thead>
      <tbody>
      <?php foreach ($ledger as $t): ?>
        <tr>
          <td><?= (int)$t->id ?></td>
          <td><?= htmlspecialchars($t->created_at) ?></td>
          <td><?= htmlspecialchars(trim(($t->first_name ?: 'User') . ' ' . ($t->mobile ?: ''))) ?><br><small class="text-muted">#<?= (int)$t->user_id ?></small></td>
          <td><span class="tx tx-<?= htmlspecialchars($t->type) ?>"><?= $labels[$t->type] ?? $t->type ?></span></td>
          <td class="<?= $t->credits >= 0 ? 'pos' : 'neg' ?>"><?= $t->credits > 0 ? '+' : '' ?><?= (int)$t->credits ?></td>
          <td><?= (int)$t->balance_after ?></td>
          <td><?= htmlspecialchars((string)$t->note) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div></div></div>
</div>

</div>
</div></section></div>

<script>
$(function () {
  if ($('#ledgerTable tbody tr').length) {
    $('#ledgerTable').DataTable({ order: [[0, 'desc']], pageLength: 25, scrollX: true });
  }
  // user picker
  var timer;
  $('#userSearch').on('input', function () {
    clearTimeout(timer);
    var q = this.value.trim();
    $('#userId').val(''); $('#assignBtn').prop('disabled', true); $('#userPicked').text('');
    if (!q) { $('#userResults').addClass('d-none'); return; }
    timer = setTimeout(function () {
      $.getJSON('<?= base_url('creditadmin/find_users') ?>', { q: q }, function (rows) {
        var box = $('#userResults').empty().removeClass('d-none');
        if (!rows.length) { box.append('<div class="p-3 text-muted">No users found</div>'); return; }
        rows.forEach(function (u) {
          $('<button type="button"></button>').text(u.label + ' — balance ' + u.balance)
            .on('click', function () {
              $('#userId').val(u.id); $('#userSearch').val(u.label);
              $('#userPicked').text('Current balance: ' + u.balance + ' credits');
              $('#assignBtn').prop('disabled', false); box.addClass('d-none');
            }).appendTo(box);
        });
      });
    }, 250);
  });
});
</script>
</body>
</html>
