<?php
ob_start();
?>
<div class="row row-cols-2 row-cols-md-4 g-4" id="app-grid">
<?php foreach ($apps as $app): ?>
    <div class="col">
        <a href="<?php echo e($app['url']);?>" target="_blank" class="text-decoration-none" data-app-id="<?php echo $app['id']; ?>">
            <div class="card h-100 text-center shadow-sm">
                <?php if($app['logo_path']): ?>
                    <div class="p-4"><img src="<?php echo e($app['logo_path']);?>" style="height:64px;width:64px;object-fit:contain;" alt="logo"></div>
                <?php endif; ?>
                <div class="card-body pt-0">
                    <h6 class="card-title mb-0"><?php echo e($app['name']);?></h6>
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
