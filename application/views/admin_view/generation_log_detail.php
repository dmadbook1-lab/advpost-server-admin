<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Generation Log #<?= (int) $log->id ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?php echo base_url('assetsNew/dist/css/admin-theme.css') ?>?v=7">
<style>
  pre.log-block {
    background: #0f172a;
    color: #e2e8f0;
    padding: 14px;
    border-radius: 10px;
    max-height: 420px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 12px;
  }
  .meta-label { color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
</style>
</head>
<body>
<?php $this->load->view('landing/sidebar'); ?>

<div class="content-wrapper">
<section class="content-header">
  <div class="container-fluid d-flex justify-content-between align-items-center">
    <div>
      <h1>Generation Log #<?= (int) $log->id ?></h1>
      <p class="text-muted mb-0">
        <?= htmlspecialchars($log->media_kind) ?> · <?= htmlspecialchars($log->status) ?> · job <?= htmlspecialchars($log->job_id) ?>
      </p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= base_url('welcome/generation_logs') ?>">Back to list</a>
  </div>
</section>

<section class="content">
<div class="container-fluid">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="meta-label">User</div>
          <div class="mb-3">
            #<?= (int) $log->user_id ?>
            <?= htmlspecialchars(trim(($log->first_name ?? '') . ' / ' . ($log->username ?? '') . ' / ' . ($log->mobile ?? ''), ' /')) ?>
          </div>

          <div class="meta-label">Language</div>
          <div class="mb-3"><?= htmlspecialchars($log->language ?: '—') ?></div>

          <?php if ($log->media_kind === 'image'): ?>
            <div class="meta-label">Size / Quality</div>
            <div class="mb-3"><?= htmlspecialchars(($log->size ?: '—') . ' / ' . ($log->quality ?: '—')) ?></div>
          <?php else: ?>
            <div class="meta-label">Duration / Camera / Start type</div>
            <div class="mb-3">
              <?= htmlspecialchars(($log->duration_seconds ?: '—') . 's / ' . ($log->camera_motion ?: '—') . ' / ' . ($log->starting_image_type ?: '—')) ?>
            </div>
          <?php endif; ?>

          <div class="meta-label">Progress</div>
          <div class="mb-3"><?= htmlspecialchars($log->progress ?: '—') ?></div>

          <div class="meta-label">Timestamps</div>
          <div class="mb-3">
            Started: <?= htmlspecialchars($log->started_at ?: '—') ?><br>
            Completed: <?= htmlspecialchars($log->completed_at ?: '—') ?><br>
            Created: <?= htmlspecialchars($log->created_at ?: '—') ?>
          </div>

          <?php if (!empty($log->error_message)): ?>
            <div class="alert alert-danger"><?= nl2br(htmlspecialchars($log->error_message)) ?></div>
          <?php endif; ?>

          <?php if (!empty($log->output_url)): ?>
            <div class="meta-label">Output</div>
            <div class="mb-2">
              <a href="<?= htmlspecialchars($log->output_url) ?>" target="_blank" rel="noopener">Open media</a>
            </div>
            <?php if ($log->media_kind === 'image'): ?>
              <img src="<?= htmlspecialchars($log->output_url) ?>" alt="output" class="img-fluid rounded border">
            <?php else: ?>
              <video src="<?= htmlspecialchars($log->output_url) ?>" controls class="w-100 rounded border"></video>
            <?php endif; ?>
            <div class="small text-muted mt-2"><?= htmlspecialchars($log->s3_key ?: '') ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>App prompt / ad_text</strong></div>
        <div class="card-body">
          <pre class="log-block"><?= htmlspecialchars($log->user_prompt ?: '—') ?></pre>
        </div>
      </div>

      <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Final model prompt</strong> (GPT Image wrap / Gemini plan prompt)</div>
        <div class="card-body">
          <pre class="log-block"><?= htmlspecialchars($log->final_prompt ?: '—') ?></pre>
        </div>
      </div>

      <?php if ($log->media_kind === 'video'): ?>
        <div class="card shadow-sm mb-3">
          <div class="card-header"><strong>Voiceover script</strong></div>
          <div class="card-body">
            <pre class="log-block"><?= htmlspecialchars($log->voiceover_script ?: '—') ?></pre>
          </div>
        </div>

        <div class="card shadow-sm mb-3">
          <div class="card-header"><strong>Gemini plan JSON</strong></div>
          <div class="card-body">
            <pre class="log-block"><?php
              $plan = $log->plan_json ?: '—';
              $decoded = json_decode((string) $log->plan_json, true);
              echo htmlspecialchars($decoded ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $plan);
            ?></pre>
          </div>
        </div>

        <div class="card shadow-sm mb-3">
          <div class="card-header"><strong>Veo scene prompts</strong></div>
          <div class="card-body">
            <pre class="log-block"><?php
              $scenes = $log->scene_prompts ?: '—';
              $decodedScenes = json_decode((string) $log->scene_prompts, true);
              echo htmlspecialchars($decodedScenes ? json_encode($decodedScenes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $scenes);
            ?></pre>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($log->meta_json)): ?>
        <div class="card shadow-sm mb-3">
          <div class="card-header"><strong>Meta</strong></div>
          <div class="card-body">
            <pre class="log-block"><?php
              $meta = json_decode((string) $log->meta_json, true);
              echo htmlspecialchars($meta ? json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $log->meta_json);
            ?></pre>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</section>
</div>
</body>
</html>
