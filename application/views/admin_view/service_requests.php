<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Service Requests</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="<?php echo base_url('assetsNew/dist/css/admin-theme.css') ?>?v=7">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
  .stat-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:18px; }
  .stat { display:block; text-decoration:none; color:#1f2340; background:#fff; border-radius:16px; padding:14px 16px;
          box-shadow:0 4px 16px rgba(80,50,160,.08); border:2px solid transparent;
          transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
  .stat:hover { transform:translateY(-3px); box-shadow:0 10px 24px rgba(80,50,160,.16); }
  .stat.active { border-color:#6d3df0; }
  .stat b { display:block; font-size:26px; line-height:1.1; }
  .stat span { font-size:13px; color:#6b6f8a; }
  .st { padding:4px 10px; border-radius:999px; color:#fff; font-size:12px; font-weight:600; white-space:nowrap; }
  .st-new { background:#6d3df0; } .st-in_progress { background:#f59e0b; }
  .st-done { background:#16a34a; } .st-rejected { background:#9ca3af; }
  .msg { max-width:320px; white-space:normal; }
  tbody tr { animation: rowIn .35s ease both; }
  @keyframes rowIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }
</style>
</head>
<body>
<?php $this->load->view('landing/sidebar'); ?>
<div class="content-wrapper">
<section class="content-header">
  <div class="container-fluid">
    <h1>Service Requests</h1>
    <p class="text-muted mb-0">Help requests sent from the app's All Services grid</p>
  </div>
</section>
<section class="content">
<div class="container-fluid">

<?php if ($this->session->flashdata('msg')): ?>
  <div class="alert alert-success"><?= htmlspecialchars($this->session->flashdata('msg')) ?></div>
<?php endif; ?>

<?php
  $labels = ['new' => 'New', 'in_progress' => 'In progress', 'done' => 'Done', 'rejected' => 'Rejected'];
?>
<div class="stat-row">
  <a class="stat <?= $status === '' ? 'active' : '' ?>" href="<?= base_url('welcome/service_requests') ?>">
    <b><?= array_sum($counts) ?></b><span>All requests</span>
  </a>
  <?php foreach ($labels as $k => $label): ?>
    <a class="stat <?= $status === $k ? 'active' : '' ?>" href="<?= base_url('welcome/service_requests?status=' . $k) ?>">
      <b><?= (int) $counts[$k] ?></b><span><?= $label ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="card shadow p-3"><div class="card-body"><div class="table-responsive">
<table id="srTable" class="table table-bordered table-striped" style="width:100%">
<thead style="background-color:#e8e0ff;">
<tr><th>ID</th><th>Service</th><th>Customer</th><th>Phone</th><th>Business</th><th>Message</th><th>Status</th><th>Received</th><th>Update</th></tr>
</thead>
<tbody>
<?php foreach ($requests as $r): ?>
<tr>
  <td><?= (int) $r->id ?></td>
  <td><strong><?= htmlspecialchars($r->service_name) ?></strong></td>
  <td><?= htmlspecialchars($r->name) ?><br><small class="text-muted">user #<?= (int) $r->user_id ?></small></td>
  <td><a href="tel:<?= htmlspecialchars($r->phone) ?>"><?= htmlspecialchars($r->phone) ?></a></td>
  <td><?= htmlspecialchars((string) $r->business_name) ?></td>
  <td class="msg"><?= nl2br(htmlspecialchars((string) $r->message)) ?></td>
  <td><span class="st st-<?= htmlspecialchars($r->status) ?>"><?= $labels[$r->status] ?? $r->status ?></span></td>
  <td><?= htmlspecialchars($r->created_at) ?></td>
  <td>
    <form method="post" action="<?= base_url('welcome/update_service_request') ?>" class="d-flex gap-1 flex-column" style="min-width:190px">
      <input type="hidden" name="id" value="<?= (int) $r->id ?>">
      <select name="status" class="form-select form-select-sm">
        <?php foreach ($labels as $k => $label): ?>
          <option value="<?= $k ?>" <?= $r->status === $k ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <input name="admin_note" class="form-control form-control-sm" placeholder="Note" value="<?= htmlspecialchars((string) $r->admin_note) ?>">
      <button class="btn btn-sm btn-primary">Save</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div></div>
</div></section></div>
<script>
$(function () {
  if ($('#srTable tbody tr').length) {
    $('#srTable').DataTable({ order: [[0, 'desc']], pageLength: 25, scrollX: true });
  }
});
</script>
</body>
</html>
