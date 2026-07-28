<?php
$payment_id = $_GET['payment_id'] ?? '';

$intentUrl =
    "intent://payment-success?payment_id={$payment_id}" .
    "#Intent;scheme=advpost;package=com.advpost.app;end;";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Redirecting…</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        // Best possible redirect from browser
        window.location.replace("<?= $intentUrl ?>");
    </script>
</head>
<body></body>
</html>