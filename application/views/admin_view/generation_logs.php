<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Generation Logs</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="<?php echo base_url('assetsNew/dist/css/admin-theme.css') ?>?v=4">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
  .prompt-preview { max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .badge-image { background:#7C3AED; }
  .badge-video { background:#2563EB; }
  .thumb { max-width: 72px; max-height: 72px; object-fit: cover; border-radius: 8px; }
</style>
</head>
<body>
<?php $this->load->view('landing/sidebar'); ?>

<div class="content-wrapper">
<section class="content-header">
  <div class="container-fluid">
    <h1>Generation Logs</h1>
    <p class="text-muted mb-0">AI image &amp; video prompts from the media service</p>
  </div>
</section>

<section class="content">
<div class="container-fluid">
<div class="card shadow p-3">
<div class="card-body">
<div class="table-responsive">
<table id="generationLogsTable" class="display nowrap table table-bordered table-striped" style="width:100%">
<thead style="background-color:#e8e0ff;">
<tr>
  <th>ID</th>
  <th>Job</th>
  <th>User</th>
  <th>Type</th>
  <th>Status</th>
  <th>Prompt / Ad text</th>
  <th>Output</th>
  <th>Created</th>
  <th>Action</th>
</tr>
</thead>
<tbody>
<?php if (!empty($logs)): foreach ($logs as $row): ?>
<tr>
  <td><?= (int) $row->id ?></td>
  <td><code><?= htmlspecialchars(substr((string) $row->job_id, 0, 12)) ?>…</code></td>
  <td>
    #<?= (int) $row->user_id ?>
    <?php if (!empty($row->first_name) || !empty($row->username)): ?>
      <br><small><?= htmlspecialchars(trim(($row->first_name ?? '') . ' ' . ($row->username ?? ''))) ?></small>
    <?php endif; ?>
  </td>
  <td>
    <span class="badge <?= $row->media_kind === 'video' ? 'badge-video' : 'badge-image' ?>">
      <?= htmlspecialchars($row->media_kind) ?>
    </span>
  </td>
  <td>
    <?php
      $statusClass = [
        'completed' => 'success',
        'failed' => 'danger',
        'processing' => 'warning',
        'queued' => 'secondary',
      ][$row->status] ?? 'secondary';
    ?>
    <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($row->status) ?></span>
  </td>
  <td>
    <div class="prompt-preview" title="<?= htmlspecialchars((string) $row->user_prompt) ?>">
      <?= htmlspecialchars(mb_strimwidth((string) ($row->user_prompt ?: $row->final_prompt ?: '—'), 0, 80, '…')) ?>
    </div>
  </td>
  <td>
    <?php if (!empty($row->output_url)): ?>
      <?php if ($row->media_kind === 'video'): ?>
        <a href="<?= htmlspecialchars($row->output_url) ?>" target="_blank" rel="noopener">Open video</a>
      <?php else: ?>
        <a href="<?= htmlspecialchars($row->output_url) ?>" target="_blank" rel="noopener">
          <img class="thumb" src="<?= htmlspecialchars($row->output_url) ?>" alt="output">
        </a>
      <?php endif; ?>
    <?php else: ?>
      —
    <?php endif; ?>
  </td>
  <td><?= htmlspecialchars((string) $row->created_at) ?></td>
  <td>
    <a class="btn btn-sm btn-primary" href="<?= base_url('welcome/generation_log_detail/' . (int) $row->id) ?>">
      View
    </a>
  </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="9" class="text-center">No generation logs yet</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</section>
</div>

<script>
$(function () {
  if ($('#generationLogsTable tbody tr').length && !$('#generationLogsTable tbody tr td[colspan]').length) {
    $('#generationLogsTable').DataTable({
      order: [[0, 'desc']],
      pageLength: 25,
      scrollX: true,
      dom: 'Bfrtip',
      buttons: ['excelHtml5', 'csvHtml5']
    });
  }
});
</script>
</body>
</html>
