<?php
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo e($settings['brand_name'] ?? 'Basket Hunt');?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<style>
    :root{--brand: <?php echo e($settings['primary_color'] ?? '#f7931e');?>}
    body{margin:0;font-family:Arial,sans-serif;background-color:#f1f1f1;display:flex;flex-direction:column;min-height:100vh;}
    .header{background-color:var(--brand);padding:10px 0;text-align:center;position:sticky;top:0;width:100%;z-index:1000;display:flex;align-items:center;justify-content:center;}
    .header img{height:50px;}
    .app-container{display:flex;flex-wrap:wrap;justify-content:center;align-content:flex-start;padding:20px;background:#e6e6e6;border-radius:10px;margin:20px;flex:1;gap:10px;}
    .app-card{background:white;padding:15px;border-radius:10px;text-align:center;box-shadow:0 2px 5px rgba(0,0,0,0.1);width:120px;height:120px;display:flex;flex-direction:column;align-items:center;justify-content:center;margin-bottom:5px;}
    .app-card img{width:60px;height:60px;}
    .app-card a{text-decoration:none;color:black;font-size:16px;font-weight:bold;}
    @media (max-width:600px){
        .app-container{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;justify-content:center;}
        .app-card{width:auto;height:auto;}
    }
</style>
</head>
<body>
<div class="header">
    <?php if(!empty($settings['logo_path'])): ?>
        <img src="<?php echo e($settings['logo_path']);?>" alt="logo">
    <?php endif; ?>
    <div style="position:absolute;right:10px;">
        <?php echo $departmentSelector ?? ''; ?>
    </div>
</div>
<?php echo $content ?? ''; ?>
</body>
</html>
