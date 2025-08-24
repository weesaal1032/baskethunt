<?php
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo e($settings['brand_name'] ?? 'Basket Hunt');?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<style>:root{--brand: <?php echo e($settings['primary_color'] ?? '#0d6efd');?>}</style>
</head>
<body class="bg-light">
<header class="d-flex justify-content-between align-items-center p-3" style="background:var(--brand);color:#fff;">
    <div>
        <?php if(!empty($settings['logo_path'])): ?>
            <img src="<?php echo e($settings['logo_path']);?>" alt="logo" style="height:40px;">
        <?php endif; ?>
    </div>
    <div class="fs-4 fw-bold text-center flex-grow-1"><?php echo e($settings['brand_name'] ?? 'Basket Hunt');?></div>
    <div><?php echo $departmentSelector ?? '';?></div>
</header>
<main class="p-4">
<?php echo $content ?? ''; ?>
</main>
</body>
</html>
