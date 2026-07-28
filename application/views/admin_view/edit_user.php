<?php $this->load->view('landing/sidebar'); ?>

<div class="content-wrapper">
    <section class="content-header text-center">
        <div class="container-fluid">
            <h1>Edit User</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-md-5">

                    <div class="card shadow p-4">
                        <form method="post" action="<?= base_url('index.php/welcome/update') ?>">

                            <input type="hidden" name="id" value="<?= $user->id ?>">

                            <div class="mb-3">
                                <label class="form-label"><b>First Name</b></label>
                                <input type="text" name="first_name"
                                       class="form-control"
                                       value="<?= $user->first_name ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><b>UserName</b></label>
                                <input type="text" name="username"
                                       class="form-control"
                                       value="<?= $user->username ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><b>Email</b></label>
                                <input type="email" name="email"
                                       class="form-control"
                                       value="<?= $user->email ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><b>Mobile</b></label>
                                <input type="text" name="mobile"
                                       class="form-control"
                                       value="<?= $user->mobile ?>">
                            </div>
                            
                             <div class="mb-3">
                                <label class="form-label"><b>Gender</b></label>
                                <input type="text" name="gender"
                                       class="form-control"
                                       value="<?= $user->gender ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><b>Country</b></label>
                                <input type="text" name="country"
                                       class="form-control"
                                       value="<?= $user->country ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><b>Address</b></label>
                                <input type="text" name="address"
                                       class="form-control"
                                       value="<?= $user->address ?>">
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary px-4">
                                    Update User
                                </button>
                            </div>

                        </form>
                    </div>

                </div>
            </div>
        </div>
    </section>
</div>

<?php $this->load->view('landing/footerone'); ?>
