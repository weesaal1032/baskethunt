<?php
ob_start();
?>
<div class="row row-cols-2 row-cols-md-4 g-4" id="app-grid">
<?php foreach ($apps as $app): ?>
    <div class="col">
        <a href="<?php echo e($app['url']);?>" target="_blank" class="text-decoration-none" data-app-id="<?php echo $app['id']; ?>">
            <div class="card h-100 text-center">
                <?php if($app['logo_path']): ?><img src="<?php echo e($app['logo_path']);?>" class="card-img-top p-3" style="height:80px;object-fit:contain;" alt="logo"><?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title"><?php echo e($app['name']);?></h5>
                    <?php if($app['description']): ?><p class="card-text small text-muted"><?php echo e($app['description']);?></p><?php endif; ?>
                </div>
            </div>
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
