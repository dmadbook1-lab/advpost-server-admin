<?php
$this->load->view('landing/sidebar');
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="content-wrapper">
  <div class="dashboard-header">
    <h2>Dashboard</h2>
    <p class="sub">Overview of users, posts, stories, and boost activity.</p>
  </div>

  <div class="row">
    <div class="col-lg-3 col-md-6 col-12 mb-4">
      <?php if ($user_role !== 'user'): ?>
      <a href="<?= base_url('welcome/users'); ?>" style="color:inherit;text-decoration:none">
      <?php endif; ?>
        <div class="adv-stat tone-users <?= ($user_role === 'user') ? 'disabled-box' : '' ?>">
          <div class="label">Users</div>
          <p class="value"><?= $users_total ?></p>
          <p class="hint">Total registered</p>
          <div class="stat-box">
            <div><h6><?= $users_today ?></h6><p>Today</p></div>
            <div><h6><?= $users_week ?></h6><p>Week</p></div>
            <div><h6><?= $users_month ?></h6><p>Month</p></div>
          </div>
          <div class="icon"><i class="fa fa-users"></i></div>
        </div>
      <?php if ($user_role !== 'user'): ?>
      </a>
      <?php endif; ?>
    </div>

    <div class="col-lg-3 col-md-6 col-12 mb-4">
      <?php if ($user_role !== 'user'): ?>
      <a href="<?= base_url('welcome/event'); ?>" style="color:inherit;text-decoration:none">
      <?php endif; ?>
        <div class="adv-stat tone-posts <?= ($user_role === 'user') ? 'disabled-box' : '' ?>">
          <div class="label">Posts</div>
          <p class="value"><?= $posts_total ?></p>
          <p class="hint">Published content</p>
          <div class="stat-box">
            <div><h6><?= $posts_today ?></h6><p>Today</p></div>
            <div><h6><?= $posts_week ?></h6><p>Week</p></div>
            <div><h6><?= $posts_month ?></h6><p>Month</p></div>
          </div>
          <div class="icon"><i class="fa fa-file-alt"></i></div>
        </div>
      <?php if ($user_role !== 'user'): ?>
      </a>
      <?php endif; ?>
    </div>

    <div class="col-lg-3 col-md-6 col-12 mb-4">
      <?php if ($user_role !== 'user'): ?>
      <a href="<?= base_url('welcome/report'); ?>" style="color:inherit;text-decoration:none">
      <?php endif; ?>
        <div class="adv-stat tone-story <?= ($user_role === 'user') ? 'disabled-box' : '' ?>">
          <div class="label">Story</div>
          <p class="value"><?= $story_total ?></p>
          <p class="hint">Active stories</p>
          <div class="stat-box">
            <div><h6><?= $story_today ?></h6><p>Today</p></div>
            <div><h6><?= $story_week ?></h6><p>Week</p></div>
            <div><h6><?= $story_month ?></h6><p>Month</p></div>
          </div>
          <div class="icon"><i class="fa fa-book-open"></i></div>
        </div>
      <?php if ($user_role !== 'user'): ?>
      </a>
      <?php endif; ?>
    </div>

    <div class="col-lg-3 col-md-6 col-12 mb-4">
      <?php if ($user_role !== 'user'): ?>
      <a href="<?= base_url('welcome/boost_post'); ?>" style="color:inherit;text-decoration:none">
      <?php endif; ?>
        <div class="adv-stat tone-boost <?= ($user_role === 'user') ? 'disabled-box' : '' ?>">
          <div class="label">Boost</div>
          <p class="value"><?= $boost_total ?></p>
          <p class="hint">Promoted posts</p>
          <div class="stat-box">
            <div><h6><?= $boost_today ?></h6><p>Today</p></div>
            <div><h6><?= $boost_week ?></h6><p>Week</p></div>
            <div><h6><?= $boost_month ?></h6><p>Month</p></div>
          </div>
          <div class="icon"><i class="fa fa-rocket"></i></div>
        </div>
      <?php if ($user_role !== 'user'): ?>
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="adv-chart-panel">
    <h3 class="chart-title">7-day activity trend</h3>
    <canvas id="activityChart"></canvas>
  </div>
</div>

<script>
new Chart(document.getElementById('activityChart'), {
  type: 'line',
  data: {
    labels: <?= $trend_days ?>,
    datasets: [
      { label: 'Users', data: <?= $trend_users ?>, borderColor: '#0284c7', backgroundColor: 'rgba(2,132,199,0.12)', borderWidth: 2.5, tension: 0.35, fill: true },
      { label: 'Posts', data: <?= $trend_posts ?>, borderColor: '#e85d04', backgroundColor: 'rgba(232,93,4,0.1)', borderWidth: 2.5, tension: 0.35, fill: true },
      { label: 'Story', data: <?= $trend_story ?>, borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,0.1)', borderWidth: 2.5, tension: 0.35, fill: true },
      { label: 'Boost', data: <?= $trend_boost ?>, borderColor: '#65a30d', backgroundColor: 'rgba(101,163,13,0.1)', borderWidth: 2.5, tension: 0.35, fill: true }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: { usePointStyle: true, boxWidth: 8, font: { family: 'Outfit', weight: '600' } }
      }
    },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(15,39,64,0.06)' } },
      x: { grid: { display: false } }
    }
  }
});
</script>

<?php $this->load->view('landing/footerone'); ?>
