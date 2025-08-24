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
<body>
<div class="d-flex">
    <nav class="bg-dark text-white p-3" style="min-width:200px; min-height:100vh;">
        <div class="fs-4 fw-bold mb-4 text-center"><?php echo e($settings['brand_name'] ?? 'Brand');?></div>
        <ul class="nav flex-column mb-4">
            <li class="nav-item"><a class="nav-link text-white" href="/admin.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin.php?r=apps.index">Applications</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin.php?r=depts.index">Departments</a></li>
              <li class="nav-item"><a class="nav-link text-white" href="/admin.php?r=branding.index">Branding</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/admin.php?r=updates.index">Updates</a></li>
        </ul>
        <a class="btn btn-outline-light w-100" href="/admin.php?r=auth.logout">Logout</a>
    </nav>
    <main class="flex-grow-1 p-4">
        <?php echo $content ?? ''; ?>
    </main>
</div>
</body>
</html>
