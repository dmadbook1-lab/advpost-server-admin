<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<title>Report List</title>

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
    img, video {
        cursor: pointer;
    }

    /* Modal center */
    #mediaModal .modal-body {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* VIDEO FIX */
    #modalVideo {
        max-height: 80vh;     /* 👈 screen limit */
        width: 100%;
        object-fit: contain; /* 👈 stretch/crop nahi hoga */
    }

    /* IMAGE FIX */
    #modalImage {
        max-height: 80vh;
        width: auto;
    }

    /* Mobile optimization */
    @media (max-width: 768px) {
        #modalVideo,
        #modalImage {
            max-height: 65vh;
        }
    }
</style>

</head>

<body>

<?php $this->load->view('landing/sidebar'); ?>

<div class="content-wrapper">

<section class="content-header">
<div class="container-fluid">
<h1>Report List</h1>
</div>
</section>

<section class="content">
<div class="container-fluid">
<div class="card shadow p-3">
<div class="card-body">
<div class="table-responsive">

<table id="reportTable" class="display nowrap table table-bordered table-striped" style="width:100%">

<thead style="background-color:#d4edda;">
<tr>
<th>ID</th>
<th>User ID</th>
<th>Media</th>
<th>Content</th>
<th>User Reported Name</th>
<th>Report</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php if(!empty($report_post)): ?>
<?php $i=1; foreach($report_post as $row): ?>
<tr>

<td><?= $i++ ?></td>
<td><?= $row->post_user_name ?? 'Unknown' ?></td>

<!-- MEDIA COLUMN -->
<td>
<?php if (!empty($row->media_file)): ?>

<?php
$ext = strtolower(pathinfo($row->media_file, PATHINFO_EXTENSION));
$imageExt = ['jpg','jpeg','png','gif','webp'];
$videoExt = ['mp4','webm','ogg','mov'];
$mediaUrl = base_url($row->media_file);
?>

<?php if (in_array($ext, $imageExt)): ?>
    <img src="<?= $mediaUrl ?>"
         width="70"
         style="border-radius:8px"
         onclick="openMedia('image','<?= $mediaUrl ?>')">

<?php elseif (in_array($ext, $videoExt)): ?>
    <video width="70"
           muted
           style="border-radius:8px"
           onclick="openMedia('video','<?= $mediaUrl ?>')">
        <source src="<?= $mediaUrl ?>">
    </video>
<?php else: ?>
    N/A
<?php endif; ?>

<?php else: ?>
    N/A
<?php endif; ?>
</td>

<td><?= $row->post_text ?? '' ?></td>
<td><?= $row->report_user_name ?? 'Unknown' ?></td>
<td>
<?php
    $rid = $row->report_text_id ?? '';
    echo $reportTextMap[$rid] ?? 'Unknown Report';
?>
</td>
<td>
<a href="<?= base_url('welcome/restore_post/'.$row->post_id) ?>"
class="btn btn-primary btn-sm"
onclick="return confirm('Are you sure you want to restore this post?');">
Restore
</a>
</td>

</tr>
<?php endforeach; ?>
<?php endif; ?>

</tbody>
</table>

</div>
</div>
</div>
</div>
</section>
</div>

<?php $this->load->view('landing/footerone'); ?>

<!-- MODAL -->
<div class="modal fade" id="mediaModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-body">

        <img id="modalImage" class="img-fluid d-none">

        <video id="modalVideo" class="d-none" controls autoplay>
            <source id="modalVideoSrc">
        </video>

      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
    $('#reportTable').DataTable({
        pageLength: 10,
        dom: 'Bfrtip',
        buttons: [{
            extend: 'excelHtml5',
            text: 'Export to Excel',
            title: 'Report_List',
            className: 'btn btn-success btn-sm'
        }]
    });
});

// OPEN MEDIA
function openMedia(type, src) {

    let img = document.getElementById('modalImage');
    let video = document.getElementById('modalVideo');
    let videoSrc = document.getElementById('modalVideoSrc');

    img.classList.add('d-none');
    video.classList.add('d-none');

    if (type === 'image') {
        img.src = src;
        img.classList.remove('d-none');
    } else {
        videoSrc.src = src;
        video.load();
        video.muted = false; // 🔊 SOUND ON
        video.volume = 1.0;
        video.classList.remove('d-none');
        video.play();
    }

    new bootstrap.Modal(document.getElementById('mediaModal')).show();
}

// STOP VIDEO ON CLOSE
document.getElementById('mediaModal').addEventListener('hidden.bs.modal', function () {
    let video = document.getElementById('modalVideo');
    video.pause();
    video.currentTime = 0;
});
</script>

</body>
</html>