<?php
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin - Basket Hunt</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>:root{--brand: <?php echo e($settings['primary_color'] ?? '#4f46e5');?>}</style>
</head>
<body class="bg-gray-100 text-gray-800">
<div class="min-h-screen flex">
    <nav class="bg-gray-800 text-white w-64 flex flex-col p-6">
        <div class="text-2xl font-bold mb-8 text-center"><?php echo e($settings['brand_name'] ?? 'Brand');?></div>
        <ul class="flex-1 space-y-2">
            <li><a class="block px-3 py-2 rounded hover:bg-gray-700" href="/admin.php">Dashboard</a></li>
            <li><a class="block px-3 py-2 rounded hover:bg-gray-700" href="/admin.php?r=apps.index">Applications</a></li>
            <li><a class="block px-3 py-2 rounded hover:bg-gray-700" href="/admin.php?r=depts.index">Departments</a></li>
            <li><a class="block px-3 py-2 rounded hover:bg-gray-700" href="/admin.php?r=branding.index">Branding</a></li>
            <li><a class="block px-3 py-2 rounded hover:bg-gray-700" href="/admin.php?r=updates.index">Updates</a></li>
        </ul>
        <a class="mt-auto block text-center px-3 py-2 rounded bg-gray-700 hover:bg-gray-600" href="/admin.php?r=auth.logout">Logout</a>
    </nav>
    <main class="flex-1 p-8">
        <?php echo $content ?? ''; ?>
    </main>
</div>
</body>
</html>
