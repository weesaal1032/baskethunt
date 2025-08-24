<?php
ob_start();
?>
<div class="flex flex-wrap justify-center items-start gap-4 p-5 max-w-5xl mx-auto" id="app-grid">
<?php foreach ($apps as $app): ?>
    <a href="<?php echo e($app['url']);?>" target="_blank" data-app-id="<?php echo $app['id'];?>" class="bg-white w-32 h-32 rounded shadow flex flex-col items-center justify-center">
        <?php if($app['logo_path']): ?>
            <img src="<?php echo e($app['logo_path']);?>" alt="<?php echo e($app['name']);?>" class="w-16 h-16">
        <?php endif; ?>
        <div class="mt-2 text-sm font-semibold text-center"><?php echo e($app['name']);?></div>
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
?>
