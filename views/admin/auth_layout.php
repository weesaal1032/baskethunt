<?php
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin - Basket Hunt</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>:root{--brand: <?php echo e($settings['primary_color'] ?? '#4f46e5');?>}</style>
</head>
<body class="bg-gray-100">
<?php echo $content ?? ''; ?>
</body>
</html>
