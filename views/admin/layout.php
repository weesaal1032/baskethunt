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
<div class="flex min-h-screen">
    <aside class="w-64 bg-gray-800 text-white p-4 space-y-2">
        <div class="text-2xl font-bold mb-4"><?php echo e($settings['brand_name'] ?? 'Brand');?></div>
        <nav class="space-y-2">
            <a href="/admin.php" class="block p-2 rounded hover:bg-gray-700">Dashboard</a>
        </nav>
    </aside>
    <main class="flex-1 p-6">
        <?php echo $content ?? ''; ?>
    </main>
</div>
</body>
</html>
