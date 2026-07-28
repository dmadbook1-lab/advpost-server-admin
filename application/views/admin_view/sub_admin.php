<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SubAdmin List</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</head>
<body>

<!-- SIDEBAR -->
<?php $this->load->view('landing/sidebar'); ?>

<div class="content-wrapper">

    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
          <h1>SubAdmin List</h1>
        </div>
    </section>


    <div class="container-fluid">
   <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-11">

                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">➕ Add User</h5>
                    </div>
 <?php if ($this->session->flashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $this->session->flashdata('success'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ❌ ERROR MESSAGE -->
    <?php if ($this->session->flashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $this->session->flashdata('error'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

                    <div class="card-body">

                        <!-- ✅ ACTION ADDED -->
                        <form method="post" action="<?= base_url('welcome/store'); ?>">

                            <!-- BASIC INFO -->
                            <h5 class="text-primary mb-3">Basic Information</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Name</label>
                                    <input type="text" name="name" class="form-control">
                                </div>
                              
                                <div class="col-md-6 mb-3">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Username</label>
                                    <input type="text" name="username" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Password</label>
                                    <input type="password" name="password" class="form-control">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>Mobile</label>
                                    <input type="text" name="mobile" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Country Code</label>
                                    <input type="text" name="country_code" class="form-control">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="">Select</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>Country</label>
                                    <input type="text" name="country" class="form-control">
                                </div>


                         <div class="d-flex justify-content-center mt-4">
    <button type="submit" class="btn btn-success px-4">
        💾 Save User
    </button>
</div>


                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<?php $this->load->view('landing/footerone.php'); ?>

</body>
</html>