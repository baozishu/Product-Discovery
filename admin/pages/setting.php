<?php
/**
 * 系统设置页面
 */

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        // 保存设置到配置文件
        $config_file = __ROOT_DIR__ . '/config.inc.php';
        if (!is_writable($config_file)) {
            $message = '配置文件不可写，请检查文件权限: ' . $config_file;
            $messageType = 'error';
        } else {
            // 读取当前配置
            include($config_file);
            
            // 更新站点设置
            $db['name'] = $_POST['site_name'];
            $db['subtitle'] = $_POST['site_subtitle'];
            $db['description'] = $_POST['site_description'];
            $db['keywords'] = $_POST['site_keywords'];
            
            // 添加favicon URL设置
            $db['favicon'] = $_POST['site_favicon'];
            
            // 更新分页设置
            $db['per_page'] = intval($_POST['per_page']);
            
            // 更新广告设置
            $db['ad_enable'] = isset($_POST['ad_enable']) ? 1 : 0;
            $db['ad_code'] = $_POST['ad_code'];
            $db['ad_position'] = $_POST['ad_position'];
            
            // 构建配置文件内容
            $config_content = "<?php\n";
            $config_content .= "// 数据库配置\n";
            $config_content .= "\$db = " . var_export($db, true) . ";\n";
            $config_content .= "?>";
            
            // 写入配置文件
            if (file_put_contents($config_file, $config_content)) {
                $message = '设置已成功保存！';
                $messageType = 'success';
            } else {
                $message = '保存设置失败！';
                $messageType = 'error';
            }
        }
    } catch (Exception $e) {
        $message = '操作失败: ' . $e->getMessage();
        $messageType = 'error';
    }
}
?>

<div class="container-fluid px-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-cogs me-2 text-primary"></i>系统设置
        </h1>
    </div>
    
    <?php if (isset($message)): ?>
    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : ($messageType === 'success' ? 'success' : 'info'); ?> alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-center">
            <?php if ($messageType === 'error'): ?>
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php elseif ($messageType === 'success'): ?>
            <i class="fas fa-check-circle me-2"></i>
            <?php else: ?>
            <i class="fas fa-info-circle me-2"></i>
            <?php endif; ?>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header py-3 bg-white">
            <h6 class="m-0 font-weight-bold text-primary d-flex align-items-center">
                <i class="fas fa-sliders-h me-2"></i>
                网站配置
            </h6>
        </div>
        <div class="card-body">
            <form method="post" action="?page=setting">
                <div class="row">
                    <!-- 左侧导航 -->
                    <div class="col-md-3 mb-4 mb-md-0">
                        <div class="nav flex-column nav-pills sticky-top" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                            <button class="nav-link active text-start mb-2" id="v-pills-general-tab" data-bs-toggle="pill" data-bs-target="#v-pills-general" type="button" role="tab" aria-controls="v-pills-general" aria-selected="true">
                                <i class="fas fa-globe me-2"></i>基本设置
                            </button>
                            <button class="nav-link text-start mb-2" id="v-pills-content-tab" data-bs-toggle="pill" data-bs-target="#v-pills-content" type="button" role="tab" aria-controls="v-pills-content" aria-selected="false">
                                <i class="fas fa-newspaper me-2"></i>内容设置
                            </button>
                            <button class="nav-link text-start mb-2" id="v-pills-advanced-tab" data-bs-toggle="pill" data-bs-target="#v-pills-advanced" type="button" role="tab" aria-controls="v-pills-advanced" aria-selected="false">
                                <i class="fas fa-tools me-2"></i>高级设置
                            </button>
                        </div>
                    </div>
                    
                    <!-- 右侧内容 -->
                    <div class="col-md-9">
                        <div class="tab-content" id="v-pills-tabContent">
                            <!-- 基本设置 -->
                            <div class="tab-pane fade show active" id="v-pills-general" role="tabpanel" aria-labelledby="v-pills-general-tab">
                                <h5 class="border-bottom pb-2 mb-4">站点信息</h5>
                                
                                <div class="mb-4">
                                    <label for="site_name" class="form-label">站点名称</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-heading"></i></span>
                                        <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($db['name']); ?>" class="form-control" required>
                                    </div>
                                    <div class="form-text">显示在浏览器标题和站点页头的名称</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="site_subtitle" class="form-label">站点小标题</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-font"></i></span>
                                        <input type="text" id="site_subtitle" name="site_subtitle" value="<?php echo isset($db['subtitle']) ? htmlspecialchars($db['subtitle']) : ''; ?>" class="form-control">
                                    </div>
                                    <div class="form-text">显示在站点名称下方的副标题或简短口号</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="site_description" class="form-label">站点描述</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                                        <textarea id="site_description" name="site_description" rows="3" class="form-control"><?php echo htmlspecialchars($db['description']); ?></textarea>
                                    </div>
                                    <div class="form-text">简短描述网站的主要内容和目的，将用于SEO优化。支持HTML格式，可以插入表格、列表、图片等。</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="site_keywords" class="form-label">站点关键词</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-tags"></i></span>
                                        <input type="text" id="site_keywords" name="site_keywords" value="<?php echo htmlspecialchars($db['keywords']); ?>" class="form-control" placeholder="关键词1,关键词2,关键词3">
                                    </div>
                                    <div class="form-text">用逗号分隔多个关键词，用于SEO优化</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="site_favicon" class="form-label">网站图标 (Favicon)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-image"></i></span>
                                        <input type="text" id="site_favicon" name="site_favicon" value="<?php echo isset($db['favicon']) ? htmlspecialchars($db['favicon']) : ''; ?>" class="form-control" placeholder="https://example.com/favicon.ico">
                                    </div>
                                    <div class="form-text">网站图标的URL地址，将显示在浏览器标签页上（建议使用.ico或.png格式）</div>
                                </div>
                            </div>
                            
                            <!-- 内容设置 -->
                            <div class="tab-pane fade" id="v-pills-content" role="tabpanel" aria-labelledby="v-pills-content-tab">
                                <h5 class="border-bottom pb-2 mb-4">分页设置</h5>
                                
                                <div class="mb-4">
                                    <label for="per_page" class="form-label">每页显示内容数量</label>
                                    <div class="input-group" style="max-width: 200px;">
                                        <span class="input-group-text"><i class="fas fa-list-ol"></i></span>
                                        <input type="number" id="per_page" name="per_page" value="<?php echo isset($db['per_page']) ? intval($db['per_page']) : 10; ?>" min="1" max="100" class="form-control" required>
                                    </div>
                                    <div class="form-text">设置在博客列表页面每页显示的文章数量</div>
                                </div>
                                
                            </div>
                            
                            <!-- 高级设置 -->
                            <div class="tab-pane fade" id="v-pills-advanced" role="tabpanel" aria-labelledby="v-pills-advanced-tab">
                                <h5 class="border-bottom pb-2 mb-4">广告设置</h5>
                                
                                <div class="mb-4">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="ad_enable" name="ad_enable" value="1" <?php echo isset($db['ad_enable']) && $db['ad_enable'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="ad_enable">启用广告</label>
                                    </div>
                                    <div class="form-text mb-3">开启或关闭全站广告显示</div>
                                    
                                    <label for="ad_position" class="form-label">广告位置</label>
                                    <div class="input-group mb-3">
                                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                        <select id="ad_position" name="ad_position" class="form-select">
                                            <option value="sidebar_bottom" <?php echo (isset($db['ad_position']) && $db['ad_position'] === 'sidebar_bottom') ? 'selected' : ''; ?>>侧边栏底部</option>
                                            <option value="content_top" <?php echo (isset($db['ad_position']) && $db['ad_position'] === 'content_top') ? 'selected' : ''; ?>>内容区顶部</option>
                                            <option value="content_bottom" <?php echo (isset($db['ad_position']) && $db['ad_position'] === 'content_bottom') ? 'selected' : ''; ?>>内容区底部</option>
                                        </select>
                                    </div>
                                    
                                    <label for="ad_code" class="form-label">广告代码</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-code"></i></span>
                                        <textarea id="ad_code" name="ad_code" rows="5" class="form-control" placeholder="请输入广告HTML代码"><?php echo isset($db['ad_code']) ? htmlspecialchars($db['ad_code']) : ''; ?></textarea>
                                    </div>
                                    <div class="form-text">支持HTML代码，可以放入任何广告联盟的代码或自定义广告内容</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="text-end">
                    <button type="reset" class="btn btn-light me-2">
                        <i class="fas fa-undo me-1"></i>重置
                    </button>
                    <button type="submit" name="update_settings" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>保存设置
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 激活Bootstrap Tab
    var triggerTabList = [].slice.call(document.querySelectorAll('#v-pills-tab button'))
    triggerTabList.forEach(function (triggerEl) {
        var tabTrigger = new bootstrap.Tab(triggerEl)
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault()
            tabTrigger.show()
        })
    })
    
    // 保存成功后显示提示，并自动关闭
    var alertList = [].slice.call(document.querySelectorAll('.alert'))
    alertList.forEach(function (alert) {
        if (alert.classList.contains('alert-success')) {
            setTimeout(function() {
                var bsAlert = new bootstrap.Alert(alert)
                bsAlert.close()
            }, 3000)
        }
    })
})
</script> 