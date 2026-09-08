<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User List</title>
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
        <div class="container-fluid">
            <h1>User List</h1>
            <p class="sub">Manage registered users and account details.</p>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card shadow p-3">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="myDataTable_list" class="display nowrap table table-bordered" style="width:100%">
                            <thead>
<tr>
    <th>Sr No</th>
    <th style="display:none;">ID</th>
    <th>Name</th>
    <th>Email</th>
    <th>Number</th>
    <th>Username</th>
    <th>Gender</th>
    <th>Country</th>
    <th>Address</th>
<?php if ($user_role === 'Admin'): ?>
    <th>Action</th>
<?php endif; ?>
</tr>
</thead>

                         <tbody>
<?php if (!empty($users)): ?>
<?php foreach ($users as $user): ?>
<tr>
    <td></td> <!-- 🔥 Auto serial here -->
    <td style="display:none;"><?= $user->id; ?></td>

    <td><?= htmlspecialchars($user->name); ?></td>
    <td><?= htmlspecialchars($user->email); ?></td>
    <td><?= htmlspecialchars($user->mobile); ?></td>
    <td><?= $user->username ?? 'N/A'; ?></td>
    <td><?= $user->gender ?? 'N/A'; ?></td>
    <td><?= $user->country ?? 'N/A'; ?></td>
    <td><?= $user->address ?? 'N/A'; ?></td>

<?php if ($user_role === 'Admin'): ?>
<td>
    <a href="<?= base_url('welcome/edit/'.$user->id) ?>" class="btn btn-sm btn-primary">Edit</a>
    <a href="<?= base_url('welcome/delete/'.$user->id) ?>" class="btn btn-sm btn-danger"
       onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
</td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="10">No users found</td></tr>
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

<script>
$(document).ready(function () {

    var isAdmin = <?= ($user_role === 'Admin') ? 'true' : 'false'; ?>;

    var table = $('#myDataTable_list').DataTable({
        pageLength: 10,
        order: [[1, 'desc']], // 🔥 hidden ID column DESC
        columnDefs: [
            { targets: 1, visible: false } // hide ID column
        ],
        dom: 'Bfrtip',
        buttons: isAdmin ? [{
            extend: 'excelHtml5',
            text: 'Export to Excel',
            title: 'User_List',
            className: 'btn btn-success btn-sm mb-2',
            exportOptions: {
                columns: ':visible:not(:last-child)'
            }
        }] : []
    });

    // 🔥 Auto serial number logic
    table.on('order.dt search.dt draw.dt', function () {
        table.column(0, { search: 'applied', order: 'applied' })
            .nodes()
            .each(function (cell, i) {
                cell.innerHTML = i + 1;
            });
    }).draw();
});
</script>

</body>
</html>
