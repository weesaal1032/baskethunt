<?php
ob_start();
?>
<div class="app-container" id="app-grid">
<?php foreach ($apps as $app): ?>
    <div class="app-card">
        <a href="<?php echo e($app['url']);?>" target="_blank" data-app-id="<?php echo $app['id'];?>">
            <?php if($app['logo_path']): ?>
                <img src="<?php echo e($app['logo_path']);?>" alt="<?php echo e($app['name']);?>">
            <?php endif; ?>
            <br><?php echo e($app['name']);?>
        </a>
    </div>
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
