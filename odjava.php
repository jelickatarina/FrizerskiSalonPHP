<?php
require_once 'sesija.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="mojstil.css">
    <title>Odjava</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="auth-page">
  <div class="auth-card" style="max-width:380px;text-align:center;">
    <div class="auth-header">
      <h1 class="auth-title">Odjava</h1>
    </div>
    <p style="color:rgba(255,255,255,0.45);font-family:'Jost',sans-serif;font-size:0.9rem;">
      Da li ste sigurni da želite da se odjavite?
    </p>
    <form method="post">
      <button type="submit" class="auth-btn">Potvrdi odjavu</button>
    </form>
    <p class="auth-switch"><a href="index.php">Odustani</a></p>
  </div>
</div>
<script src="/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
