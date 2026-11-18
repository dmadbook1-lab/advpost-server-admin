<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Post</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

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
            <h1>Edit Post</h1>
        </div>
    </section>
    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-md-6">

                    <div class="card card-primary shadow">
                        <div class="card-header">
                            <h3 class="card-title">Update Post Details</h3>
                        </div>

                        <form method="post" action="<?= base_url('welcome/update_post') ?>">
                            <div class="card-body">

                                <input type="hidden" name="post_id" value="<?= $post->post_id ?>">

                                <div class="form-group mb-3">
                                    <label>Post Text</label>
                                    <textarea name="text"
                                              class="form-control"
                                              rows="4"
                                              placeholder="Enter post text"><?= $post->text ?></textarea>
                                </div>

                                <div class="form-group mb-3">
                                    <label>Location</label>
                                    <input type="text"
                                           name="location"
                                           value="<?= $post->location ?>"
                                           class="form-control"
                                           placeholder="Enter location">
                                </div>

                                <div class="form-group mb-3">
                                    <label>Post Type</label>
                                    <input type="text"
                                           name="post_type"
                                           value="<?= $post->post_type ?>"
                                           class="form-control"
                                           placeholder="Image / Video / Text">
                                </div>

                            </div>

                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Update Post
                                </button>
                            </div>
                        </form>

                    </div>

                </div>
            </div>
        </div>
    </section>
</div>