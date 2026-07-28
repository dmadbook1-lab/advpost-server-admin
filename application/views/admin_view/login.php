<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login | ADvPOST</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    :root {
      --ink: #0f2740;
      --accent: #e85d04;
      --line: #dce5ee;
      --muted: #64748b;
      --soft: #f4f7fb;
      --font: "Outfit", system-ui, sans-serif;
      --display: "Source Serif 4", Georgia, serif;
    }

    * { box-sizing: border-box; }

    html, body {
      margin: 0;
      min-height: 100%;
    }

    body {
      font-family: var(--font);
      color: var(--ink);
      min-height: 100vh;
      background: #eef2f6;
    }

    .login-page {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1.2fr 1fr;
      background: #fff;
    }

    .poster {
      position: relative;
      min-height: 100vh;
      overflow: hidden;
      background: #102a43;
    }

    .poster > img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      display: block;
    }

    .poster-shade {
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, rgba(15, 39, 64, 0.12) 0%, rgba(15, 39, 64, 0.58) 100%);
      pointer-events: none;
    }

    .poster-caption {
      position: absolute;
      left: 2rem;
      right: 2rem;
      bottom: 2rem;
      color: #fff;
      z-index: 1;
    }

    .poster-caption h2 {
      margin: 0 0 0.45rem;
      font-family: var(--display);
      font-size: clamp(1.55rem, 2.5vw, 2.1rem);
      font-weight: 700;
      letter-spacing: -0.02em;
      line-height: 1.2;
      max-width: 15ch;
    }

    .poster-caption p {
      margin: 0;
      max-width: 34ch;
      opacity: 0.92;
      font-size: 0.95rem;
      line-height: 1.5;
      font-weight: 500;
    }

    .form-side {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2.75rem 2.25rem;
      background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }

    .form-wrap {
      width: min(400px, 100%);
    }

    .brand {
      margin-bottom: 1.85rem;
      padding-bottom: 1.25rem;
      border-bottom: 1px solid var(--line);
    }

    .brand .logo {
      display: block;
      width: min(240px, 100%);
      height: auto;
      object-fit: contain;
      object-position: left center;
      background: #fff;
    }

    .brand .tag {
      display: block;
      margin-top: 0.65rem;
      color: var(--muted);
      font-size: 0.88rem;
      font-weight: 600;
      letter-spacing: 0.02em;
    }

    .form-wrap h1 {
      margin: 0 0 0.4rem;
      font-family: var(--display);
      font-size: 2rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      color: var(--ink);
    }

    .form-wrap .lead {
      margin: 0 0 1.55rem;
      color: var(--muted);
      font-size: 0.95rem;
      line-height: 1.5;
      font-weight: 500;
    }

    .field { margin-bottom: 1rem; }

    .field label {
      display: block;
      margin-bottom: 0.4rem;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      color: var(--muted);
    }

    .control {
      display: flex;
      align-items: center;
      gap: 0.55rem;
      min-height: 52px;
      padding: 0 0.95rem;
      border: 1px solid var(--line);
      border-radius: 12px;
      background: var(--soft);
      transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }

    .control:focus-within {
      background: #fff;
      border-color: rgba(232, 93, 4, 0.5);
      box-shadow: 0 0 0 4px rgba(232, 93, 4, 0.12);
    }

    .control i {
      color: var(--muted);
      width: 1rem;
      text-align: center;
    }

    .control input {
      flex: 1;
      border: 0;
      outline: 0;
      background: transparent;
      height: 50px;
      font: inherit;
      font-size: 0.95rem;
      color: var(--ink);
    }

    .control input::placeholder { color: #94a3b8; }

    .btn-login {
      width: 100%;
      margin-top: 0.55rem;
      height: 52px;
      border: 0;
      border-radius: 12px;
      cursor: pointer;
      color: #fff;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 700;
      background: linear-gradient(135deg, var(--accent), #ff7a24);
      box-shadow: 0 10px 22px rgba(232, 93, 4, 0.25);
      transition: transform 0.15s ease, filter 0.15s ease;
    }

    .btn-login:hover {
      filter: brightness(1.04);
      transform: translateY(-1px);
    }

    .secure-note {
      margin-top: 1.25rem;
      text-align: center;
      color: var(--muted);
      font-size: 0.8rem;
      font-weight: 500;
    }

    @media (max-width: 920px) {
      .login-page {
        grid-template-columns: 1fr;
        min-height: 100vh;
      }

      .poster {
        min-height: 220px;
        max-height: 38vh;
      }

      .poster-caption {
        left: 1.15rem;
        right: 1.15rem;
        bottom: 1.1rem;
      }

      .poster-caption h2 {
        font-size: 1.35rem;
        max-width: none;
      }

      .poster-caption p {
        font-size: 0.86rem;
        max-width: none;
      }

      .form-side {
        padding: 1.5rem 1.15rem 2rem;
        align-items: flex-start;
      }

      .form-wrap {
        width: 100%;
      }

      .brand .logo {
        width: min(200px, 78vw);
      }

      .form-wrap h1 {
        font-size: 1.55rem;
      }
    }

    @media (max-width: 480px) {
      .poster {
        min-height: 180px;
        max-height: 32vh;
      }

      .poster-caption p {
        display: none;
      }

      .control,
      .btn-login {
        min-height: 48px;
        height: 48px;
      }
    }
  </style>
</head>
<body>
  <div class="login-page">
    <aside class="poster">
      <img src="<?= base_url('assetsNew/logo/login_background.jpeg') ?>" alt="ADvPOST admin poster">
      <div class="poster-shade"></div>
      <div class="poster-caption">
        <h2>Business social, managed simply.</h2>
        <p>Review posts, users, boosts, and reports from one admin console.</p>
      </div>
    </aside>

    <section class="form-side">
      <div class="form-wrap">
        <div class="brand">
          <img class="logo" src="<?= base_url('assetsNew/logo/advpost_logo.jpeg') ?>" alt="ADvPOST">
          <span class="tag">Admin Console</span>
        </div>

        <h1>Welcome back</h1>
        <p class="lead">Sign in with your admin email and password to continue.</p>

        <form action="<?= site_url('welcome/login') ?>" method="post" autocomplete="on">
          <div class="field">
            <label for="email">Email</label>
            <div class="control">
              <i class="fas fa-envelope" aria-hidden="true"></i>
              <input id="email" type="email" name="email" placeholder="admin@advpost.in" required>
            </div>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <div class="control">
              <i class="fas fa-lock" aria-hidden="true"></i>
              <input id="password" type="password" name="password" placeholder="Enter your password" required>
            </div>
          </div>

          <button type="submit" class="btn-login">Login</button>
        </form>

        <p class="secure-note">Secure access · ADvPOST Admin</p>
      </div>
    </section>
  </div>
</body>
</html>
