<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Story List</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="<?php echo base_url('assetsNew/dist/css/admin-theme.css') ?>?v=4">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

    <style>
        table img {
            max-width: 100px;
            cursor: pointer;
            border-radius: 5px;
        }

        video {
            max-width: 100px;
            border-radius: 5px;
        }

        .modal-img {
            width: 50%;
            height: 100vh;
            object-fit: contain;
        }

        .modal-content {
            background: transparent;
            border: none;
        }
    </style>
</head>
<body>

<?php $this->load->view('landing/sidebar'); ?>

<div class="content-wrapper">

    <section class="content-header">
        <div class="container-fluid">
            <h1>Story List</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card shadow p-3">

                <?php if (!empty($stories)) : ?>
                <div class="table-responsive">
                    <table id="myDataTable_list" class="display nowrap table table-bordered w-100">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Story</th>
                            <th>Type</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($stories as $i => $row): ?>

                            <?php
                                // Main file URL
                                $fileUrl = filter_var($row->url, FILTER_VALIDATE_URL)
                                    ? $row->url
                                    : base_url('assetsNew/images/story_image/' . $row->url);

                                // Video thumbnail
                                $thumbUrl = !empty($row->video_thumbnail)
                                    ? (filter_var($row->video_thumbnail, FILTER_VALIDATE_URL)
                                        ? $row->video_thumbnail
                                        : base_url('assets/images/story_thumbnails/' . $row->video_thumbnail))
                                    : '';
                            ?>

                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($row->first_name ?? 'N/A') ?></td>

                                <!-- STORY COLUMN -->
                                <td>
                                    <?php if ($row->type === 'image'): ?>

                                        <img src="<?= $fileUrl ?>"
                                             class="img-click"
                                             data-bs-toggle="modal"
                                             data-bs-target="#imageModal"
                                             data-src="<?= $fileUrl ?>">

                                    <?php elseif ($row->type === 'video'): ?>

                                        <video controls poster="<?= $thumbUrl ?>">
                                            <source src="<?= $fileUrl ?>" type="video/mp4">
                                            Your browser does not support video.
                                        </video>

                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>

                                <td><?= ucfirst($row->type ?? 'N/A') ?></td>
                            </tr>

                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php else: ?>
                    <div class="alert alert-warning">No data available</div>
                <?php endif; ?>

            </div>
        </div>
    </section>
</div>

<?php $this->load->view('landing/footerone.php'); ?>

<!-- FULLSCREEN IMAGE MODAL -->
<div class="modal fade" id="imageModal" tabindex="-1">
  <div class="modal-dialog modal-fullscreen modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0 d-flex justify-content-center align-items-center">
        <img src="" id="modalImage" class="modal-img">
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {

    var isAdmin = <?= ($user_role === 'Admin') ? 'true' : 'false'; ?>;

    var options = {
        pageLength: 10,
        dom: 'Bfrtip',
        buttons: []
    };

    if (isAdmin) {
        options.buttons.push({
            extend: 'excelHtml5',
            text: 'Export to Excel',
            className: 'btn btn-success btn-sm mb-2',
            exportOptions: {
                columns: [0,1,3,4]
            }
        });
    }

    $('#myDataTable_list').DataTable(options);

    // Image modal preview
    $(document).on('click', '.img-click', function () {
        $('#modalImage').attr('src', $(this).data('src'));
    });

});
</script>

</body>
</html>
