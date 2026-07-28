<?php
if (!$this->session->userdata('email')) {
    redirect('welcome/index');
}

$role   = $this->session->userdata('role');
$isUser = ($role === 'user');
$email  = (string) $this->session->userdata('email');
$initial = strtoupper(substr($email !== '' ? $email : 'A', 0, 1));

$current = uri_string();
$isActive = function ($needle) use ($current) {
    return (strpos($current, $needle) !== false) ? 'active' : '';
};
$storyActive = (preg_match('#(^|/)report$#', $current)) ? 'active' : '';
$reportPostActive = (strpos($current, 'report_post') !== false) ? 'active' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ADvPOST Admin</title>

  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="<?php echo base_url('assetsNew/plugins/fontawesome-free/css/all.min.css') ?>">
  <link rel="stylesheet" href="<?php echo base_url('assetsNew/dist/css/adminlte.min.css') ?>">
  <link rel="stylesheet" href="<?php echo base_url('assetsNew/plugins/overlayScrollbars/css/OverlayScrollbars.min.css') ?>">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="<?php echo base_url('assetsNew/dist/css/admin-theme.css') ?>?v=4">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Toggle menu">
          <i class="fas fa-bars"></i>
        </a>
      </li>
    </ul>

    <ul class="navbar-nav ml-auto align-items-center" style="gap: 0.7rem;">
      <li class="nav-item">
        <span class="adv-user-chip">
          <span class="avatar"><?= htmlspecialchars($initial) ?></span>
          <?= htmlspecialchars($email) ?>
        </span>
      </li>
      <li class="nav-item">
        <a href="<?= base_url('welcome/logout') ?>" class="btn-adv-logout" title="Logout">
          <i class="fas fa-sign-out-alt"></i><span> Logout</span>
        </a>
      </li>
    </ul>
  </nav>

  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="<?= base_url('welcome/dashboard') ?>" class="brand-link text-center">
      <img class="brand-logo" src="<?= base_url('assetsNew/logo/advpost_logo.jpeg') ?>" alt="ADvPOST">
    </a>

    <div class="sidebar">
      <div class="nav-section-label">Main menu</div>
      <nav class="mt-1">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item">
            <a href="<?= base_url('welcome/dashboard') ?>" class="nav-link <?= $isActive('dashboard') ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>

          <li class="nav-item <?= $isUser ? 'menu-disabled' : '' ?>">
            <a href="<?= $isUser ? 'javascript:void(0)' : base_url('welcome/users') ?>" class="nav-link <?= $isActive('users') || $isActive('edit') ?>">
              <i class="nav-icon fas fa-users"></i>
              <p>User List</p>
            </a>
          </li>

          <li class="nav-item <?= $isUser ? 'menu-disabled' : '' ?>">
            <a href="<?= $isUser ? 'javascript:void(0)' : base_url('welcome/event') ?>" class="nav-link <?= $isActive('event') || $isActive('edit_post') ?>">
              <i class="nav-icon fas fa-newspaper"></i>
              <p>Posts</p>
            </a>
          </li>

          <li class="nav-item <?= $isUser ? 'menu-disabled' : '' ?>">
            <a href="<?= $isUser ? 'javascript:void(0)' : base_url('welcome/report') ?>" class="nav-link <?= $storyActive ?>">
              <i class="nav-icon fas fa-book-open"></i>
              <p>Story</p>
            </a>
          </li>

          <li class="nav-item <?= $isUser ? 'menu-disabled' : '' ?>">
            <a href="<?= $isUser ? 'javascript:void(0)' : base_url('welcome/boost_post') ?>" class="nav-link <?= $isActive('boost_post') ?>">
              <i class="nav-icon fas fa-rocket"></i>
              <p>Boost Posts</p>
            </a>
          </li>

          <li class="nav-item <?= $isUser ? 'menu-disabled' : '' ?>">
            <a href="<?= $isUser ? 'javascript:void(0)' : base_url('welcome/sub_admin_list') ?>" class="nav-link <?= $isActive('sub_admin') ?>">
              <i class="nav-icon fas fa-users-cog"></i>
              <p>Sub Admin</p>
            </a>
          </li>

          <li class="nav-item <?= $isUser ? 'menu-disabled' : '' ?>">
            <a href="<?= $isUser ? 'javascript:void(0)' : base_url('welcome/notification_list') ?>" class="nav-link <?= $isActive('notification') ?>">
              <i class="nav-icon fas fa-bell"></i>
              <p>Notification</p>
            </a>
          </li>

          <li class="nav-item <?= $isUser ? 'menu-disabled' : '' ?>">
            <a href="<?= $isUser ? 'javascript:void(0)' : base_url('welcome/report_post') ?>" class="nav-link <?= $reportPostActive ?>">
              <i class="nav-icon fas fa-flag"></i>
              <p>Report Post</p>
            </a>
          </li>

          <li class="nav-item nav-logout">
            <a href="<?= base_url('welcome/logout') ?>" class="nav-link">
              <i class="nav-icon fas fa-sign-out-alt"></i>
              <p>Logout</p>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </aside>
