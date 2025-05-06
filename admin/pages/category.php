<?php
/**
 * 分类管理页面
 */

// 定义操作和ID
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 处理添加/编辑/删除操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            $conn = new PDO("sqlite:" . $db['file']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            if ($_POST['action'] === 'add') {
                // 添加分类
                $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, create_time, update_time) VALUES (?, ?, ?, ?, ?)");
                $now = time();
                $stmt->execute([
                    $_POST['name'],
                    $_POST['slug'],
                    $_POST['description'],
                    $now,
                    $now
                ]);
                $message = '分类添加成功！';
                $messageType = 'success';
                // 重定向到列表页
                header('Location: ?page=category&message=' . urlencode($message) . '&type=' . $messageType);
                exit;
            } elseif ($_POST['action'] === 'edit' && $id > 0) {
                // 更新分类
                $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, update_time = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['slug'],
                    $_POST['description'],
                    time(),
                    $id
                ]);
                $message = '分类更新成功！';
                $messageType = 'success';
                // 重定向到列表页
                header('Location: ?page=category&message=' . urlencode($message) . '&type=' . $messageType);
                exit;
            } elseif ($_POST['action'] === 'delete' && $id > 0) {
                // 检查是否有文章使用此分类
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE category_id = ?");
                $checkStmt->execute([$id]);
                $count = $checkStmt->fetchColumn();
                
                if ($count > 0) {
                    $message = '无法删除此分类，因为它关联了 ' . $count . ' 篇文章';
                    $messageType = 'error';
                } else {
                    // 删除分类
                    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = '分类删除成功！';
                    $messageType = 'success';
                }
                
                // 重定向到列表页
                header('Location: ?page=category&message=' . urlencode($message) . '&type=' . $messageType);
                exit;
            }
        } catch (PDOException $e) {
            $message = '操作失败: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// 从URL获取消息
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $messageType = isset($_GET['type']) ? $_GET['type'] : 'info';
}

// 获取分类列表或单个分类
try {
    $conn = new PDO("sqlite:" . $db['file']);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取所有分类（用于父分类选择）
    $allCategoriesStmt = $conn->query("SELECT id, name FROM categories ORDER BY name");
    $allCategories = $allCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($action === 'edit' && $id > 0) {
        // 获取单个分类
        $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            $message = '分类不存在！';
            $messageType = 'error';
            $action = 'list'; // 回到列表页
        }
    } elseif ($action === 'list') {
        // 处理搜索关键词
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $viewMode = isset($_GET['view']) ? $_GET['view'] : 'table'; // 默认表格视图
        
        // 构建基本查询
        $sql = "SELECT c.* FROM categories c";
        
        // 添加搜索条件
        if (!empty($search)) {
            $sql .= " WHERE c.name LIKE :search OR c.slug LIKE :search OR c.description LIKE :search";
        }
        
        $sql .= " ORDER BY c.name";
        
        $stmt = $conn->prepare($sql);
        
        // 绑定搜索参数
        if (!empty($search)) {
            $searchParam = '%' . $search . '%';
            $stmt->bindParam(':search', $searchParam);
        }
        
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取每个分类下的文章数量
        $contentCountsStmt = $conn->query("SELECT category_id, COUNT(*) as count FROM posts GROUP BY category_id");
        $contentCounts = [];
        while ($row = $contentCountsStmt->fetch(PDO::FETCH_ASSOC)) {
            $contentCounts[$row['category_id']] = $row['count'];
        }
    }
} catch (PDOException $e) {
    $message = '数据库错误: ' . $e->getMessage();
    $messageType = 'error';
}
?>

<div class="container-fluid py-4">
    <?php if (isset($message)): ?>
    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : ($messageType === 'success' ? 'success' : 'info'); ?> alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center">
            <?php if ($messageType === 'success'): ?>
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php elseif ($messageType === 'error'): ?>
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php else: ?>
            <i class="bi bi-info-circle-fill me-2"></i>
            <?php endif; ?>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
    <!-- 分类列表 -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 d-flex align-items-center">
                <i class="bi bi-folder me-2 text-primary"></i>
                分类管理
            </h5>
            <a href="?page=category&action=add" class="btn btn-primary btn-sm d-flex align-items-center">
                <i class="bi bi-plus-lg me-1"></i>
                添加新分类
            </a>
        </div>
        
        <div class="card-body p-0">
            <!-- 搜索和视图切换 -->
            <div class="p-3 border-bottom bg-light">
                <div class="row g-3">
                    <div class="col-md-6">
                        <form method="get" action="" class="d-flex">
                            <input type="hidden" name="page" value="category">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control form-control-sm" 
                                    placeholder="搜索分类..." 
                                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <button type="submit" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-search"></i> 搜索
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6 d-flex justify-content-md-end align-items-center">
                        <span class="text-muted small me-3">
                            共 <?php echo count($categories); ?> 个分类
                        </span>
                        <div class="btn-group" role="group">
                            <a href="?page=category<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&view=card" class="btn btn-sm <?php echo $viewMode === 'card' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                                <i class="bi bi-grid-3x3-gap-fill"></i>
                            </a>
                            <a href="?page=category<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&view=table" class="btn btn-sm <?php echo $viewMode === 'table' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                                <i class="bi bi-list-ul"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($categories)): ?>
            <div class="p-5 text-center">
                <div class="mb-3">
                    <i class="bi bi-folder-x fs-1 text-muted"></i>
                </div>
                <?php if (isset($_GET['search'])): ?>
                <h5>没有找到匹配"<?php echo htmlspecialchars($_GET['search']); ?>"的分类</h5>
                <p class="text-muted">尝试不同的搜索词或查看所有分类</p>
                <div class="mt-3">
                    <a href="?page=category" class="btn btn-outline-primary">查看所有分类</a>
                </div>
                <?php else: ?>
                <h5>尚未添加任何分类</h5>
                <p class="text-muted">分类用于组织您的内容</p>
                <div class="mt-3">
                    <a href="?page=category&action=add" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> 添加第一个分类
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>

            <?php if ($viewMode === 'card'): ?>
            <!-- 卡片视图 -->
            <div class="row p-3 g-3">
                <?php foreach ($categories as $cat): ?>
                <div class="col-md-6 col-lg-4 col-xl-3 mb-3">
                    <div class="card h-100 shadow-sm transition-all hover-shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0 text-truncate" title="<?php echo htmlspecialchars($cat['name']); ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </h5>
                                <span class="badge bg-primary rounded-pill ms-1">
                                    <?php echo isset($contentCounts[$cat['id']]) ? $contentCounts[$cat['id']] : 0; ?>
                                </span>
                            </div>
                            <p class="card-text small text-muted mb-2">
                                <span class="d-inline-block">
                                    <i class="bi bi-link-45deg me-1"></i>
                                    <?php echo htmlspecialchars($cat['slug']); ?>
                                </span>
                            </p>
                            <div class="d-flex justify-content-end mt-2">
                                <a href="?page=category&action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-outline-secondary me-2" title="编辑分类">
                                    <i class="bi bi-pencil"></i> 编辑
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="<?php echo $cat['id']; ?>" data-name="<?php echo htmlspecialchars($cat['name']); ?>" title="删除分类">
                                    <i class="bi bi-trash"></i> 删除
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <!-- 表格视图 -->
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>名称</th>
                            <th>别名</th>
                            <th class="text-center">文章数</th>
                            <th class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td class="align-middle">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-folder text-primary me-2"></i>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </div>
                            </td>
                            <td class="align-middle text-muted small"><?php echo htmlspecialchars($cat['slug']); ?></td>
                            <td class="align-middle text-center">
                                <span class="badge bg-primary rounded-pill"><?php echo isset($contentCounts[$cat['id']]) ? $contentCounts[$cat['id']] : 0; ?></span>
                            </td>
                            <td class="align-middle text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="?page=category&action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-outline-secondary" title="编辑分类">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger delete-item" data-id="<?php echo $cat['id']; ?>" data-name="<?php echo htmlspecialchars($cat['name']); ?>" title="删除分类">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 删除确认模态框 -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">确认删除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center text-danger mb-3">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                        <strong>此操作无法撤销</strong>
                    </div>
                    <p>您确定要删除分类 "<span id="deleteName" class="fw-bold"></span>" 吗？</p>
                    <p class="mb-0 d-flex align-items-center">
                        <i class="bi bi-info-circle-fill text-primary me-2"></i>
                        删除此分类将会移除所有关联，但不会删除相关的文章。
                    </p>
                </div>
                <div class="modal-footer">
                    <button id="cancelDelete" type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> 取消
                    </button>
                    <form id="deleteForm" method="post" action="">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> 确认删除
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 删除操作确认
        document.addEventListener('DOMContentLoaded', function() {
            // 确保Bootstrap已加载
            if (typeof bootstrap !== 'undefined') {
                const deleteModalElement = document.getElementById('deleteModal');
                if (deleteModalElement) {
                    const deleteModal = new bootstrap.Modal(deleteModalElement);
                    const deleteName = document.getElementById('deleteName');
                    const deleteForm = document.getElementById('deleteForm');
                    
                    document.querySelectorAll('.delete-item').forEach(button => {
                        button.addEventListener('click', function() {
                            const id = this.getAttribute('data-id');
                            const name = this.getAttribute('data-name');
                            
                            deleteName.textContent = name;
                            deleteForm.action = `?page=category&action=delete&id=${id}`;
                            deleteModal.show();
                        });
                    });
                }
            } else {
                console.error('Bootstrap未加载，模态框功能将不可用');
            }
        });
    </script>
    
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- 添加/编辑分类表单 -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0 d-flex align-items-center">
                <?php if ($action === 'add'): ?>
                <i class="bi bi-plus-circle text-primary me-2"></i>
                添加新分类
                <?php else: ?>
                <i class="bi bi-pencil-square text-primary me-2"></i>
                编辑分类：<?php echo isset($category) ? htmlspecialchars($category['name']) : ''; ?>
                <?php endif; ?>
            </h5>
        </div>
        <form method="post" action="?page=category&action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $id : ''; ?>" id="categoryForm" class="needs-validation">
            <input type="hidden" name="action" value="<?php echo $action; ?>">
            
            <div class="card-body">
                <div class="row mb-4">
                    <!-- 名称字段 -->
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="form-group">
                            <label for="name" class="form-label d-flex align-items-center">
                                <i class="bi bi-tag-fill text-primary me-2"></i>
                                名称 <span class="text-danger ms-1">*</span>
                            </label>
                            <input type="text" name="name" id="name" 
                                class="form-control"
                                value="<?php echo isset($category) ? htmlspecialchars($category['name']) : ''; ?>" 
                                required minlength="2" maxlength="50" 
                                placeholder="输入分类名称">
                            <div class="invalid-feedback" id="name-error"></div>
                            <small class="form-text text-muted mt-1">分类名称会显示在网站前端和后台管理中</small>
                        </div>
                    </div>

                    <!-- 别名字段 -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="slug" class="form-label d-flex align-items-center">
                                <i class="bi bi-link text-primary me-2"></i>
                                别名 <span class="text-danger ms-1">*</span>
                            </label>
                            <input type="text" name="slug" id="slug" 
                                class="form-control"
                                value="<?php echo isset($category) ? htmlspecialchars($category['slug']) : ''; ?>" 
                                required pattern="^[a-zA-Z0-9_-]+$" 
                                minlength="2" maxlength="50" 
                                placeholder="unique-category-slug">
                            <div class="invalid-feedback" id="slug-error"></div>
                            <small class="form-text text-muted mt-1 d-flex align-items-center">
                                <i class="bi bi-info-circle me-1"></i>
                                用于URL的唯一标识符，仅允许字母、数字、短横线和下划线
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- 描述文本域 -->
                <div class="form-group">
                    <label for="description" class="form-label d-flex align-items-center">
                        <i class="bi bi-file-text text-primary me-2"></i>
                        描述信息
                    </label>
                    <textarea name="description" id="description" rows="4" 
                        class="form-control" 
                        maxlength="500" 
                        placeholder="分类的描述信息（可选）"><?php echo isset($category) ? htmlspecialchars($category['description']) : ''; ?></textarea>
                    <div class="d-flex justify-content-end">
                        <small class="text-muted mt-1" id="description-counter">0/500</small>
                    </div>
                    <small class="form-text text-muted">添加对此分类的描述，帮助用户更好地理解分类内容</small>
                </div>
            </div>
            
            <div class="card-footer bg-light d-flex align-items-center justify-content-between">
                <a href="?page=category" class="btn btn-secondary d-flex align-items-center">
                    <i class="bi bi-arrow-left me-1"></i>
                    返回列表
                </a>
                
                <div class="d-flex">
                    <?php if ($action === 'edit'): ?>
                    <a href="?page=category" class="btn btn-outline-secondary me-2 d-flex align-items-center">
                        <i class="bi bi-x-circle me-1"></i>
                        取消
                    </a>
                    <?php endif; ?>
                    
                    <button type="submit" id="submitBtn" class="btn btn-primary d-flex align-items-center">
                        <i class="bi bi-<?php echo $action === 'add' ? 'plus-lg' : 'check-lg'; ?> me-1"></i>
                        <span id="submitText"><?php echo $action === 'add' ? '添加分类' : '保存更改'; ?></span>
                        <span id="loadingIndicator" class="d-none">
                            <span class="spinner-border spinner-border-sm ms-1" role="status" aria-hidden="true"></span>
                            处理中...
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- 引入拼音转换库 -->
    <script src="https://cdn.jsdelivr.net/gh/zh-lx/pinyin-pro@latest/dist/pinyin-pro.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 别名自动生成
        const nameInput = document.getElementById('name');
        const slugInput = document.getElementById('slug');
        const descInput = document.getElementById('description');
        const descCounter = document.getElementById('description-counter');
        const form = document.getElementById('categoryForm');
        const submitBtn = document.getElementById('submitBtn');
        const submitText = document.getElementById('submitText');
        const loadingIndicator = document.getElementById('loadingIndicator');
        
        // 初始化字数统计
        updateCharacterCount();
        
        // 监听输入事件
        descInput.addEventListener('input', updateCharacterCount);
        
        function updateCharacterCount() {
            const length = descInput.value.length;
            descCounter.textContent = `${length}/500`;
            
            if (length > 450) {
                descCounter.classList.add('text-warning');
                if (length > 490) {
                    descCounter.classList.remove('text-warning');
                    descCounter.classList.add('text-danger');
                }
            } else {
                descCounter.classList.remove('text-warning', 'text-danger');
            }
        }
        
        // 别名自动生成
        nameInput.addEventListener('input', function() {
            // 只有在编辑模式下且slug已有值，或者slug字段已被用户手动修改过，才不自动生成
            if ((slugInput.dataset.userModified === 'true') || 
                ('<?php echo $action; ?>' === 'edit' && slugInput.value)) {
                return;
            }
            
            // 检查是否包含中文字符
            const containsChinese = /[\u4e00-\u9fa5]/.test(nameInput.value);
            
            if (containsChinese && typeof pinyinPro !== 'undefined') {
                // 将中文转换为拼音
                try {
                    const { pinyin } = pinyinPro;
                    // 生成不带声调的拼音，用短横线连接
                    const pinyinStr = pinyin(nameInput.value, { 
                        toneType: 'none', 
                        type: 'string',
                        separator: '-'
                    });
                    
                    // 清理生成的拼音字符串，确保只包含有效字符
                    slugInput.value = pinyinStr
                        .toLowerCase()
                        .replace(/[^\w-]+/g, '-') // 将非单词字符替换为短横线
                        .replace(/-+/g, '-') // 将多个连续短横线替换为单个
                        .replace(/^-+|-+$/g, '');  // 移除开头和结尾的短横线
                } catch (e) {
                    console.error('拼音转换失败:', e);
                    // 如果拼音转换失败，使用默认的别名生成方法
                    defaultSlugGeneration();
                }
            } else {
                // 对于非中文内容或拼音库未加载时，使用默认的别名生成方法
                defaultSlugGeneration();
            }
        });
        
        // 默认别名生成方法
        function defaultSlugGeneration() {
            slugInput.value = nameInput.value
                .trim()
                .toLowerCase()
                .replace(/[\s\W]+/g, '-') // 将空白和非单词字符替换为短横线
                .replace(/^-+|-+$/g, ''); // 移除开头和结尾的短横线
        }
        
        // 标记slug是否被手动修改过
        slugInput.addEventListener('input', function() {
            slugInput.dataset.userModified = 'true';
        });
        
        // 表单验证
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            let valid = true;
            const nameError = document.getElementById('name-error');
            const slugError = document.getElementById('slug-error');
            
            // 重置错误提示
            nameError.textContent = '';
            nameInput.classList.remove('is-invalid');
            slugError.textContent = '';
            slugInput.classList.remove('is-invalid');
            
            // 名称验证
            if (!nameInput.value.trim()) {
                nameError.textContent = '请输入分类名称';
                nameInput.classList.add('is-invalid');
                valid = false;
            } else if (nameInput.value.length < 2) {
                nameError.textContent = '分类名称至少需要2个字符';
                nameInput.classList.add('is-invalid');
                valid = false;
            }
            
            // 别名验证
            if (!slugInput.value.trim()) {
                slugError.textContent = '请输入分类别名';
                slugInput.classList.add('is-invalid');
                valid = false;
            } else if (!/^[a-zA-Z0-9_-]+$/.test(slugInput.value)) {
                slugError.textContent = '别名只能包含字母、数字、短横线和下划线';
                slugInput.classList.add('is-invalid');
                valid = false;
            }
            
            if (valid) {
                // 显示加载状态
                submitText.classList.add('d-none');
                loadingIndicator.classList.remove('d-none');
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75');
                
                // 提交表单
                setTimeout(() => form.submit(), 300);
            } else {
                // 表单验证失败，添加抖动效果
                form.classList.add('animate__animated', 'animate__shakeX');
                setTimeout(() => {
                    form.classList.remove('animate__animated', 'animate__shakeX');
                }, 800);
            }
        });
    });
    </script>
    <?php endif; ?>
</div>

<style>
/* 卡片悬停效果 */
.transition-all {
    transition: all 0.2s ease-in-out;
}
.hover-shadow:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    transform: translateY(-2px);
}

/* 表单验证样式 */
.was-validated .form-control:invalid,
.form-control.is-invalid {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

/* 动画 */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}
.animate__shakeX {
    animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
}
</style> 