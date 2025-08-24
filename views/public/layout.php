<?php
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Basket Hunt</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>:root{--brand: <?php echo e($settings['primary_color'] ?? '#4f46e5');?>}</style>
</head>
<body class="bg-gray-100">
<header class="flex justify-between items-center p-4 bg-[var(--brand)] text-white">
    <div class="flex items-center space-x-2">
        <?php if(!empty($settings['logo_path'])): ?>
            <img src="<?php echo e($settings['logo_path']);?>" alt="logo" class="h-8">
        <?php endif; ?>
        <span class="font-bold"><?php echo e($settings['brand_name'] ?? 'Brand');?></span>
    </div>
    <div class="text-xl font-semibold">Basket Hunt</div>
    <div><?php echo $departmentSelector ?? '';?></div>
</header>
<main class="p-4">
<?php echo $content ?? ''; ?>
</main>
</body>
</html>
