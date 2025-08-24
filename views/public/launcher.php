<?php
ob_start();
?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="app-grid">
<?php foreach ($apps as $app): ?>
    <a href="<?php echo e($app['url']);?>" target="_blank" class="bg-white p-4 rounded-2xl shadow" data-app-id="<?php echo $app['id']; ?>">
        <?php if($app['logo_path']): ?><img src="<?php echo e($app['logo_path']);?>" class="h-12 mb-2" alt="logo"><?php endif; ?>
        <div class="font-semibold"><?php echo e($app['name']);?></div>
        <?php if($app['description']): ?><div class="text-sm text-gray-500"><?php echo e($app['description']);?></div><?php endif; ?>
    </a>
<?php endforeach; ?>
</div>
<script>
const selector=document.getElementById('department');
if(selector){
 selector.addEventListener('change',e=>{const slug=e.target.value;localStorage.setItem('dept',slug);const url=slug?`/?dept=${slug}`:'/';window.location=url;});
}
</script>
<?php
$content = ob_get_clean();
require __DIR__.'/layout.php';
