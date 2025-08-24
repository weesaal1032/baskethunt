<?php
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo e($settings['brand_name'] ?? 'Basket Hunt');?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>:root{--brand: <?php echo e($settings['primary_color'] ?? '#f7931e');?>}</style>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">
<header class="bg-[var(--brand)] sticky top-0 z-50">
    <div class="max-w-7xl mx-auto flex items-center justify-center p-3 relative">
        <?php if(!empty($settings['logo_path'])): ?>
            <img src="<?php echo e($settings['logo_path']);?>" alt="logo" class="h-12">
        <?php endif; ?>
        <div class="absolute right-3">
            <?php echo $departmentSelector ?? ''; ?>
        </div>
    </div>
</header>
<?php echo $content ?? ''; ?>
</body>
</html>
