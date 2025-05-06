<?php if(!defined('__ROOT_DIR__')) exit; ?>
<?php $defaultDir = __ROOT_DIR__ . '/data/' . uniqid() . '.db'; ?>
<div class="mb-4">
    <label class="block text-gray-700 mb-1" for="dbFile">数据库文件路径</label>
    <input type="text" class="w-full border rounded px-3 py-2" name="db_file" id="dbFile" value="<?php echo $defaultDir; ?>"/>
    <p class="text-sm text-gray-600 mt-1">
        推荐使用绝对路径。"<?php echo $defaultDir; ?>" 是系统为您自动生成的路径。
    </p>
</div>
