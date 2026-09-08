<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Post List | ADvPOST</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="<?php echo base_url('assetsNew/dist/css/admin-theme.css') ?>?v=7">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</head>
<body>

<?php $this->load->view('landing/sidebar'); ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid page-hero">
      <div>
        <h1>Post List</h1>
        <p class="sub">Review, search, and manage all published posts.</p>
      </div>
    </div>
  </section>

  <div class="container-fluid">
    <div class="card shadow p-3">
      <div class="panel-head">
        <h3>All posts</h3>
        <div>
          <?php if (!empty($only_local)): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('welcome/event?only_local=0') ?>">Show all posts</a>
          <?php else: ?>
            <a class="btn btn-sm btn-primary" href="<?= site_url('welcome/event?only_local=1') ?>">Only with local media</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="alert alert-info py-2 px-3" style="font-size:0.9rem;">
        <b>Media status:</b>
        <?= (int) ($local_media_posts ?? 0) ?> posts have images/videos on this computer,
        out of <?= (int) ($total_posts ?? 0) ?> total posts.
        DB has many file paths (e.g. <code>69b1049f7290e.jpg</code>) that are not in
        <code>assetsNew/images/new_post/</code> — that is why they don’t show.
        <?php if (!empty($only_local)): ?>
          Currently filtering to posts with local files only.
        <?php endif; ?>
      </div>
      <?php if (!empty($posts)) : ?>
      <div class="table-responsive">
        <table id="myDataTable_list" class="table display nowrap w-100">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Text</th>
              <th>Location</th>
              <th>Post Type</th>
              <th>Images</th>
              <th>Videos</th>
              <?php if ($user_role === 'Admin'): ?>
              <th>Action</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($posts as $index => $post): ?>
            <tr>
              <td><?= $index + 1 ?></td>
              <td><?= htmlspecialchars($post->first_name ?? 'N/A') ?></td>
              <td><?= htmlspecialchars($post->text) ?></td>
              <td><?= htmlspecialchars($post->location) ?></td>
              <td><?= htmlspecialchars($post->post_type) ?></td>
              <td>
                <?php if (!empty($post->images)): ?>
                  <?php foreach (explode(',', $post->images) as $img): ?>
                    <?php
                      $img = trim($img);
                      if ($img === '') continue;
                      $src = adv_media_url($img);
                    ?>
                    <img src="<?= htmlspecialchars($src) ?>"
                         class="media-click border me-1 mb-1"
                         data-type="image"
                         data-src="<?= htmlspecialchars($src) ?>"
                         data-bs-toggle="modal"
                         data-bs-target="#mediaModal"
                         alt="post"
                         onerror="this.onerror=null;this.src='data:image/svg+xml,<?= rawurlencode('<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"56\" height=\"56\"><rect width=\"56\" height=\"56\" rx=\"8\" fill=\"#eef2f7\"/><text x=\"28\" y=\"32\" text-anchor=\"middle\" fill=\"#627d98\" font-size=\"9\" font-family=\"sans-serif\">No file</text></svg>') ?>';"
                         style="width:56px;height:56px;object-fit:cover;cursor:pointer;">
                  <?php endforeach; ?>
                <?php else: ?>
                  <span class="text-muted">N/A</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($post->videos)): ?>
                  <?php foreach (explode(',', $post->videos) as $vid): ?>
                    <?php
                      $vid = trim($vid);
                      if ($vid === '') continue;
                      $vsrc = adv_media_url($vid);
                    ?>
                    <video class="media-click border mb-1"
                           data-type="video"
                           data-src="<?= htmlspecialchars($vsrc) ?>"
                           data-bs-toggle="modal"
                           data-bs-target="#mediaModal"
                           width="80" height="56"
                           style="cursor:pointer;object-fit:cover;">
                      <source src="<?= htmlspecialchars($vsrc) ?>" type="video/mp4">
                    </video>
                  <?php endforeach; ?>
                <?php else: ?>
                  <span class="text-muted">N/A</span>
                <?php endif; ?>
              </td>
              <?php if ($user_role === 'Admin'): ?>
              <td>
                <a href="<?= base_url('welcome/edit_post/'.$post->post_id) ?>" class="btn btn-sm btn-primary">Edit</a>
                <a href="<?= base_url('welcome/event_delete/'.$post->post_id) ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Are you sure?')">Delete</a>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <div class="alert alert-warning mb-0">No data available</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php $this->load->view('landing/footerone.php'); ?>

<div class="modal fade" id="mediaModal" tabindex="-1">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content bg-dark">
      <div class="modal-body d-flex justify-content-center align-items-center p-0">
        <img id="modalImage" class="img-fluid d-none" alt="">
        <video id="modalVideo" class="d-none w-100 h-100" controls>
          <source src="" type="video/mp4">
        </video>
      </div>
      <div class="modal-footer justify-content-center bg-dark">
        <button class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).on('click', '.media-click', function () {
  var type = $(this).data('type');
  var src  = $(this).data('src');
  $('#modalImage').addClass('d-none');
  $('#modalVideo').addClass('d-none')[0].pause();
  if (type === 'image') {
    $('#modalImage').attr('src', src).removeClass('d-none');
  } else {
    $('#modalVideo source').attr('src', src);
    $('#modalVideo')[0].load();
    $('#modalVideo').removeClass('d-none');
  }
});

$('#mediaModal').on('hidden.bs.modal', function () {
  $('#modalVideo')[0].pause();
});

$(document).ready(function () {
  var isAdmin = <?= ($user_role === 'Admin') ? 'true' : 'false'; ?>;
  var dtOptions = { pageLength: 10, dom: 'Bfrtip', buttons: [] };
  if (isAdmin) {
    dtOptions.buttons.push({
      extend: 'excelHtml5',
      text: 'Export to Excel',
      title: 'Post_List',
      className: 'btn btn-sm mb-2',
      exportOptions: { columns: ':visible:not(:last-child)' }
    });
  }
  $('#myDataTable_list').DataTable(dtOptions);
});
</script>
</body>
</html>
