<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Boost Post List</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="<?php echo base_url('assetsNew/dist/css/admin-theme.css') ?>?v=7">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

    <!-- JSZip -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</head>
<body>

<?php $this->load->view('landing/sidebar'); ?>

<div class="content-wrapper">

    <!-- Header -->
    <section class="content-header">
        <div class="container-fluid">
            <h1>Boost Post List</h1>
        </div>
    </section>

    <!-- Content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card shadow p-3">

                <?php if (!empty($boost)) : ?>
                <div class="table-responsive">
                    <table id="myDataTable_list" class="table table-bordered table-striped nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Days</th>
                                <th>Price</th>
                                <th>Tax</th>
                                <th>Total Price</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($boost as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
<td><?= htmlspecialchars($row->first_name ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row->start_date) ?></td>
                                <td><?= htmlspecialchars($row->end_date) ?></td>
                                <td><?= htmlspecialchars($row->days) ?></td>
                                <td>₹ <?= htmlspecialchars($row->price) ?></td>
                                <td><?= htmlspecialchars($row->tax) ?>%</td>
                                <td><b>₹ <?= htmlspecialchars($row->total_price) ?></b></td>
                                <td><?= htmlspecialchars($row->payment_method) ?></td>
                              <td><?= htmlspecialchars($row->status) ?></td>
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

<script>
$(document).ready(function () {

    // PHP → JS
    var isAdmin = <?= ($user_role === 'Admin') ? 'true' : 'false'; ?>;

    var dtOptions = {
        pageLength: 10,
        dom: 'Bfrtip',
        buttons: []
    };

    // Only ADMIN can see Export button
    if (isAdmin) {
        dtOptions.buttons.push({
            extend: 'excelHtml5',
            text: 'Export to Excel',
            title: 'Boost_Post_List',
            className: 'btn btn-success btn-sm mb-2'
        });
    }

    $('#myDataTable_list').DataTable(dtOptions);
});
</script>



</body>
</html>
