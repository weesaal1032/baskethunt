<?php
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin - Basket Hunt</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<style>:root{--brand: <?php echo e($settings['primary_color'] ?? '#0d6efd');?>}</style>
</head>
<body class="bg-light">
<?php echo $content ?? ''; ?>
</body>
</html>
