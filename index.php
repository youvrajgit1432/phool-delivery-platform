<?php
// Root entry point for the Phool Delivery platform.
// Presents a small landing page linking to the four experiences and the
// storefront, instead of exposing an Apache directory listing.

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$links = [
    'Customer storefront' => $base . '/public_html/',
    'Admin panel'         => $base . '/admin/public/login.php',
    'Vendor panel'        => $base . '/vendor-panel/public/',
    'Rider panel'         => $base . '/delivery-panel/public/',
    'Demo database guide' => $base . '/database/README.md',
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Phool Delivery — Multi-Sided Commerce &amp; Last-Mile Delivery Platform</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#101820;color:#eef3f6}
    .wrap{max-width:760px;margin:0 auto;padding:56px 24px}
    h1{margin:0 0 6px;font-size:30px}
    p.sub{margin:0 0 32px;color:#9fb3c0}
    a.card{display:block;padding:16px 20px;margin:12px 0;background:#182430;border:1px solid #26394a;border-radius:12px;color:#eef3f6;text-decoration:none}
    a.card:hover{background:#1e2d3b;border-color:#3a5a75}
    .note{margin-top:28px;font-size:13px;color:#7f95a4}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Phool Delivery</h1>
    <p class="sub">Multi-Sided Commerce &amp; Last-Mile Delivery Platform — public demo edition</p>
    <?php foreach ($links as $label => $href): ?>
      <a class="card" href="<?php echo htmlspecialchars($href, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label); ?></a>
    <?php endforeach; ?>
    <p class="note">All data shown is fictional demo data. See the project README for setup and demo credentials.</p>
  </div>
</body>
</html>
