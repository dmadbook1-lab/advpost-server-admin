<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notification List</title>

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
            height: auto;
            cursor: pointer;
            border: 1px solid #ddd;
            padding: 2px;
            border-radius: 4px;
        }
        .modal-img {
            width: 100%;
            height: 100vh;
            object-fit: contain;
        }
        .modal-content {
            background-color: transparent;
            border: none;
        }
        .modal-footer {
            background-color: transparent;
            border: none;
        }
    </style>
</head>
<body>

<?php $this->load->view('landing/sidebar'); ?>

<div class="content-wrapper">

    <!-- Content Header -->
 <section class="content-header">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between">
            
            <h1 class="mb-0">Notification List</h1>

            <?php if ($user_role === 'Admin'): ?>
                <a href="<?= base_url('welcome/notification'); ?>" class="btn btn-primary">
                    + Create New
                </a>
            <?php endif; ?>

        </div>
    </div>
</section>



    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card shadow p-3">
                <?php if (!empty($notification)) : ?>
                <div class="table-responsive">
                    <table id="myDataTable_list" class="display nowrap table table-bordered" style="width:100%">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Targrt Type</th>
                            <th>Notification</th>
                            <th>Date</th>
                         
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($notification as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
<td><?= htmlspecialchars($row->first_name ?? 'N/A') ?></td>

                                <td><?= htmlspecialchars($row->target_type ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row->notification ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row->created_at ?? 'N/A') ?></td>

                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning">No data available.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>

</div>

<?php $this->load->view('landing/footerone.php'); ?>

<!-- Fullscreen Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0 d-flex justify-content-center align-items-center">
        <img src="" class="modal-img" id="modalImage" alt="Large Image">
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {

    // PHP → JS
    var isAdmin = <?= ($user_role === 'Admin') ? 'true' : 'false'; ?>;

    var dtOptions = {
        pageLength: 10,
        dom: 'Bfrtip',
        buttons: []
    };

    // Sirf ADMIN ko Export Excel
    if (isAdmin) {
        dtOptions.buttons.push({
            extend: 'excelHtml5',
            text: 'Export to Excel',
            title: 'Story_List',
            className: 'btn btn-success btn-sm mb-2',
            exportOptions: {
                columns: [0,1,4,5] // image & video exclude
            }
        });
    }

    $('#myDataTable_list').DataTable(dtOptions);

    // Image fullscreen modal
    $(document).on('click', '.img-click', function () {
        $('#modalImage').attr('src', $(this).data('src'));
    });
});
</script>


</body>
</html>
