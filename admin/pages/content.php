<?php
/**
 * 产品管理页面
 */

// 定义操作和ID
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$page = isset($_GET['page_num']) ? intval($_GET['page_num']) : 1;
$limit = 10; // 每页显示的产品数量
$offset = ($page - 1) * $limit;

// 获取分类列表
try {
    $conn = new PDO("sqlite:" . $db['file']);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取所有分类
    $categoryStmt = $conn->query("SELECT * FROM categories ORDER BY name");
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
    $categoryMap = [];
    foreach ($categories as $category) {
        $categoryMap[$category['id']] = $category;
    }
    
    // 获取所有用户
    $userStmt = $conn->query("SELECT id, username FROM users");
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    $userMap = [];
    foreach ($users as $user) {
        $userMap[$user['id']] = $user;
    }
} catch (PDOException $e) {
    $message = '获取分类失败: ' . $e->getMessage();
    $messageType = 'error';
}

// 处理添加/编辑/删除操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            $conn = new PDO("sqlite:" . $db['file']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            if ($_POST['action'] === 'add') {
                // 添加产品
                echo "<!-- 添加产品，提交的内容长度: " . strlen($_POST['content']) . " -->";
                
                // 准备所有字段，确保都有值
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $content = isset($_POST['content']) ? $_POST['content'] : '';
                $summary = isset($_POST['summary']) ? trim($_POST['summary']) : '';
                $link = isset($_POST['link']) ? trim($_POST['link']) : '';
                $icon = isset($_POST['icon']) ? trim($_POST['icon']) : '';
                $gallery = isset($_POST['gallery']) ? trim($_POST['gallery']) : '';
                $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
                $status = isset($_POST['status']) ? $_POST['status'] : 'draft';
                $is_top = isset($_POST['is_top']) ? 1 : 0;
                
                // 使用提交的创建时间或当前时间
                if (isset($_POST['create_time']) && !empty($_POST['create_time'])) {
                    $create_time = $_POST['create_time']; // 直接使用表单提交的日期时间
                } else {
                    $create_time = date('Y-m-d H:i:s'); // 当前时间格式化为日期字符串
                }
                
                // 处理上传的图片
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
                    $uploadResult = handleImageUpload($_FILES['product_image'], $create_time);
                    if ($uploadResult['success']) {
                        // 如果已经有图片集，添加到末尾
                        if (!empty($gallery)) {
                            $gallery .= ',' . $uploadResult['path'];
                        } else {
                            $gallery = $uploadResult['path'];
                        }
                        
                        echo "<!-- 图片上传成功，路径: " . $uploadResult['path'] . " -->";
                    } else {
                        echo "<!-- 图片上传失败 -->";
                    }
                }
                
                // 添加产品SQL
                $stmt = $conn->prepare("INSERT INTO posts (title, content, summary, link, icon, gallery, category_id, user_id, status, is_top, create_time) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $title,
                    $content,
                    $summary,
                    $link,
                    $icon,
                    $gallery,
                    $category_id,
                    $_SESSION['admin_id'], // 使用当前登录的管理员ID
                    $status,
                    $is_top,
                    $create_time
                ]);
                
                $message = '产品添加成功！';
                $messageType = 'success';
                // 使用JavaScript重定向而非header函数
                echo '<script>window.location.href="?page=content&message=' . urlencode($message) . '&type=' . $messageType . '";</script>';
                exit;
            } elseif ($_POST['action'] === 'edit' && $id > 0) {
                // 更新产品信息
                echo "<!-- 更新产品ID: $id, 提交的内容长度: " . strlen($_POST['content']) . " -->";
                
                // 准备所有字段，确保都有值
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $content = isset($_POST['content']) ? $_POST['content'] : '';
                $summary = isset($_POST['summary']) ? trim($_POST['summary']) : '';
                $link = isset($_POST['link']) ? trim($_POST['link']) : '';
                $icon = isset($_POST['icon']) ? trim($_POST['icon']) : '';
                $gallery = isset($_POST['gallery']) ? trim($_POST['gallery']) : '';
                $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
                $status = isset($_POST['status']) ? $_POST['status'] : 'draft';
                $is_top = isset($_POST['is_top']) ? 1 : 0;
                
                // 更新时间
                $update_time = date('Y-m-d H:i:s'); // 当前时间格式化为日期字符串
                
                // 使用提交的创建时间或保持原有创建时间
                if (isset($_POST['create_time']) && !empty($_POST['create_time'])) {
                    $create_time = $_POST['create_time']; // 直接使用表单提交的日期时间
                } else {
                    // 查询现有的创建时间
                    $timeStmt = $conn->prepare("SELECT create_time FROM posts WHERE id = ?");
                    $timeStmt->execute([$id]);
                    $timeResult = $timeStmt->fetch(PDO::FETCH_ASSOC);
                    $create_time = $timeResult ? $timeResult['create_time'] : date('Y-m-d H:i:s');
                }
                    
                // 处理上传的图片
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
                    $uploadResult = handleImageUpload($_FILES['product_image'], $create_time);
                    if ($uploadResult['success']) {
                        // 如果已经有图片集，添加到末尾
                        if (!empty($gallery)) {
                            $gallery .= ',' . $uploadResult['path'];
                        } else {
                            $gallery = $uploadResult['path'];
                        }
                        
                        echo "<!-- 图片上传成功，路径: " . $uploadResult['path'] . " -->";
                    } else {
                        echo "<!-- 图片上传失败 -->";
                    }
                }
                
                // 更新产品SQL
                    $stmt = $conn->prepare("UPDATE posts SET title = ?, content = ?, summary = ?, link = ?, icon = ?, gallery = ?, category_id = ?, status = ?, is_top = ?, update_time = ?, create_time = ? WHERE id = ?");
                    $stmt->execute([
                        $title,
                        $content,
                        $summary,
                        $link,
                        $icon,
                        $gallery,
                        $category_id,
                        $status,
                        $is_top,
                    $update_time,
                    $create_time,
                        $id
                    ]);
                
                $message = '产品更新成功！';
                $messageType = 'success';
                // 使用JavaScript重定向而非header函数
                echo '<script>window.location.href="?page=content&message=' . urlencode($message) . '&type=' . $messageType . '";</script>';
                exit;
            } elseif ($_POST['action'] === 'delete' && $id > 0) {
                // 在删除产品前先获取产品信息
                $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
                $stmt->execute([$id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // 产品删除前的准备工作
                $deleteImageSuccess = false;
                if ($product) {
                    try {
                        // 如果存在链接，尝试删除对应的图片文件夹
                        if (!empty($product['link'])) {
                            $parsedUrl = parse_url($product['link']);
                            if (isset($parsedUrl['host'])) {
                                $domain = preg_replace('/[^a-zA-Z0-9-_\.]/', '-', $parsedUrl['host']);
                                
                                // 查找网站截图目录（使用绝对路径确保正确）
                                $uploadsDir = dirname(dirname(dirname(__FILE__))) . '/uploads/websites/';
                                
                                if (is_dir($uploadsDir)) {
                                    $dateDirs = scandir($uploadsDir);
                                    foreach ($dateDirs as $dateDir) {
                                        if ($dateDir != '.' && $dateDir != '..' && is_dir($uploadsDir . $dateDir)) {
                                            $domainDir = $uploadsDir . $dateDir . '/' . $domain;
                                            if (is_dir($domainDir)) {
                                                // 删除目录下的所有文件
                                                $files = scandir($domainDir);
                                                foreach ($files as $file) {
                                                    if ($file != '.' && $file != '..') {
                                                        if (file_exists($domainDir . '/' . $file)) {
                                                            @unlink($domainDir . '/' . $file);
                                                        }
                                                    }
                                                }
                                                // 删除目录
                                                if (@rmdir($domainDir)) {
                                                    $deleteImageSuccess = true;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // 记录错误但继续执行删除产品的操作
                        error_log("删除产品图片时出错: " . $e->getMessage());
                    }
                }
                
                // 删除产品
                $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = '产品删除成功！';
                if ($deleteImageSuccess) {
                    $message .= '产品图片已一并删除。';
                }
                $messageType = 'success';
                // 使用JavaScript重定向而非header函数
                echo '<script>window.location.href="?page=content&message=' . urlencode($message) . '&type=' . $messageType . '";</script>';
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

// 获取产品列表或单个产品
try {
    $conn = new PDO("sqlite:" . $db['file']);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($action === 'edit' && $id > 0) {
        // 获取单个产品
        echo "<!-- 准备查询ID: $id 的产品 -->";
        
        // 直接输出查询到的所有列
        $columnsStmt = $conn->prepare("PRAGMA table_info(posts)");
        $columnsStmt->execute();
        $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<!-- posts表结构: ";
        print_r($columns);
        echo " -->";
        
        $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$post) {
            $message = '产品不存在！';
            $messageType = 'error';
            $action = 'list'; // 回到列表页
        } else {
            // 输出调试信息
            echo "<!-- 查询到的产品信息: ";
            print_r($post);
            echo " -->";
            
            // 确保所有必需的字段都存在
            if (!isset($post['summary'])) {
                $post['summary'] = '';
                echo "<!-- 警告: summary字段不存在 -->";
            }
            if (!isset($post['link'])) {
                $post['link'] = '';
                echo "<!-- 警告: link字段不存在 -->";
            }
            if (!isset($post['icon'])) {
                $post['icon'] = '';
                echo "<!-- 警告: icon字段不存在 -->";
            }
            if (!isset($post['gallery'])) {
                $post['gallery'] = '';
                echo "<!-- 警告: gallery字段不存在 -->";
            }
            if (!isset($post['content'])) {
                $post['content'] = '';
                echo "<!-- 严重警告: content字段不存在！ -->";
                
                // 尝试直接查询content列
                try {
                    $contentStmt = $conn->prepare("SELECT content FROM posts WHERE id = ?");
                    $contentStmt->execute([$id]);
                    $contentResult = $contentStmt->fetch(PDO::FETCH_ASSOC);
                    echo "<!-- 单独查询content结果: ";
                    print_r($contentResult);
                    echo " -->";
                    
                    if ($contentResult && isset($contentResult['content'])) {
                        $post['content'] = $contentResult['content'];
                        echo "<!-- 已找到content内容，长度: " . strlen($contentResult['content']) . " -->";
                    }
                } catch (PDOException $e) {
                    echo "<!-- 查询content时出错: " . $e->getMessage() . " -->";
                }
            } else {
                echo "<!-- content字段存在，长度: " . strlen($post['content']) . " -->";
            }
        }
    } elseif ($action === 'list') {
        // 定义搜索条件
        $searchTitle = isset($_GET['title']) ? $_GET['title'] : '';
        $searchCategory = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        $searchStatus = isset($_GET['status']) ? $_GET['status'] : '';
        
        // 构建WHERE子句
        $where = [];
        $params = [];
        
        if (!empty($searchTitle)) {
            $where[] = "title LIKE ?";
            $params[] = "%$searchTitle%";
        }
        
        if ($searchCategory > 0) {
            $where[] = "category_id = ?";
            $params[] = $searchCategory;
        }
        
        if (!empty($searchStatus)) {
            $where[] = "status = ?";
            $params[] = $searchStatus;
        }
        
        $whereClause = '';
        if (!empty($where)) {
            $whereClause = "WHERE " . implode(' AND ', $where);
        }
        
        // 获取总数
        $countStmt = $conn->prepare("SELECT COUNT(*) FROM posts $whereClause");
        $countStmt->execute($params);
        $totalCount = $countStmt->fetchColumn();
        
        // 计算总页数
        $totalPages = ceil($totalCount / $limit);
        
        // 获取分页数据
        $stmt = $conn->prepare("SELECT * FROM posts $whereClause ORDER BY create_time DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $message = '数据库错误: ' . $e->getMessage();
    $messageType = 'error';
}

// 处理图片上传，按创建时间组织存储
function handleImageUpload($file, $create_time) {
    $uploadSuccess = false;
    $filePath = '';
    $relativePath = '';
    
    // 如果没有文件上传，直接返回
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => ''];
    }
    
    try {
        // 检查文件类型
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            error_log("不支持的图片类型: " . $file['type']);
            return ['success' => false, 'path' => ''];
        }
        
        // 检查文件大小，限制为5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            error_log("文件过大: " . $file['size'] . " 字节");
            return ['success' => false, 'path' => ''];
        }
        
        // 根据创建时间生成文件夹路径
        $date = date('Y-m-d', strtotime($create_time));
        $time = date('His', strtotime($create_time));
        
        // 创建基础上传目录
        $baseUploadDir = dirname(dirname(dirname(__FILE__))) . '/uploads/';
        $productImagesDir = $baseUploadDir . 'products/';
        $dateDir = $productImagesDir . $date . '/';
        $timeDir = $dateDir . $time . '/';
        
        // 尝试创建文件夹结构
        $dirCreated = false;
        $uploadDir = $baseUploadDir; // 默认使用基础目录，如果创建失败
        
        // 检查基础目录
        if (!is_dir($baseUploadDir)) {
            if (!mkdir($baseUploadDir, 0777, true)) {
                // 基础目录创建失败，直接使用根目录
                $uploadDir = dirname(dirname(dirname(__FILE__))) . '/';
            } else {
                $dirCreated = true;
            }
        } else {
            $dirCreated = true;
        }
        
        // 如果基础目录已存在或创建成功，继续创建子目录
        if ($dirCreated) {
            if (!is_dir($productImagesDir)) {
                if (!mkdir($productImagesDir, 0777)) {
                    // 产品目录创建失败，使用基础目录
                    $uploadDir = $baseUploadDir;
                    $dirCreated = false;
                } else {
                    $uploadDir = $productImagesDir;
                }
            } else {
                $uploadDir = $productImagesDir;
            }
        }
        
        // 创建日期和时间目录
        if ($dirCreated) {
            if (!is_dir($dateDir)) {
                if (!mkdir($dateDir, 0777)) {
                    // 日期目录创建失败，使用产品目录
                    $uploadDir = $productImagesDir;
                } else {
                    $uploadDir = $dateDir;
                    
                    // 尝试创建时间目录
                    if (!is_dir($timeDir)) {
                        if (mkdir($timeDir, 0777)) {
                            $uploadDir = $timeDir;
                        }
                    } else {
                        $uploadDir = $timeDir;
                    }
                }
            } else {
                $uploadDir = $dateDir;
                
                // 尝试创建时间目录
                if (!is_dir($timeDir)) {
                    if (mkdir($timeDir, 0777)) {
                        $uploadDir = $timeDir;
                    }
                } else {
                    $uploadDir = $timeDir;
                }
            }
        }
        
        // 生成唯一文件名
        $originalName = basename($file['name']);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $originalName);
        $uploadPath = $uploadDir . $filename;
        
        // 上传文件
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $uploadSuccess = true;
            $filePath = $uploadPath;
            
            // 尝试转换为WebP格式以节省空间
            if (function_exists('imagewebp') && $extension !== 'webp') {
                $webpFilename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
                $webpPath = $uploadDir . $webpFilename;
                
                // 根据原始图片类型加载图像
                $image = null;
                if ($extension === 'jpg' || $extension === 'jpeg') {
                    $image = @imagecreatefromjpeg($uploadPath);
                } elseif ($extension === 'png') {
                    $image = @imagecreatefrompng($uploadPath);
                } elseif ($extension === 'gif') {
                    $image = @imagecreatefromgif($uploadPath);
                }
                
                // 如果成功加载图像，尝试保存为WebP
                if ($image) {
                    // 保存为WebP格式
                    if (imagewebp($image, $webpPath, 80)) {
                        // 检查WebP文件是否比原始文件小
                        if (filesize($webpPath) < filesize($uploadPath)) {
                            // 使用WebP文件替代原始文件
                            @unlink($uploadPath); // 删除原始文件
                            $filePath = $webpPath;
                            $filename = $webpFilename;
                        } else {
                            // WebP文件不比原始文件小，保留原始文件
                            @unlink($webpPath);
                        }
                    }
                    
                    // 释放内存
                    imagedestroy($image);
                }
            }
            
            // 计算相对路径，用于存储到数据库
            $relativePath = str_replace(dirname(dirname(dirname(__FILE__))), '', $filePath);
        }
    } catch (Exception $e) {
        error_log("图片上传处理失败: " . $e->getMessage());
    }
    
    return [
        'success' => $uploadSuccess,
        'path' => $relativePath
    ];
}
?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 fw-bold mb-0">产品管理</h1>
        <?php if ($action === 'list'): ?>
        <a href="?page=content&action=add" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>添加产品
        </a>
        <?php endif; ?>
    </div>
    
    <?php if (isset($message)): ?>
    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : ($messageType === 'success' ? 'success' : 'info'); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($action === 'list'): ?>
    <!-- 搜索表单 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="" class="row g-3">
                <input type="hidden" name="page" value="content">
                
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="title" class="form-control" placeholder="产品名称" value="<?php echo isset($_GET['title']) ? htmlspecialchars($_GET['title']) : ''; ?>">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <select name="category_id" class="form-select">
                        <option value="0">所有分类</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo isset($_GET['category_id']) && $_GET['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">所有状态</option>
                        <option value="published" <?php echo isset($_GET['status']) && $_GET['status'] === 'published' ? 'selected' : ''; ?>>已发布</option>
                        <option value="draft" <?php echo isset($_GET['status']) && $_GET['status'] === 'draft' ? 'selected' : ''; ?>>草稿</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel-fill me-2"></i>筛选
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 产品列表 -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 text-nowrap">产品名称</th>
                            <th class="text-nowrap">分类</th>
                            <th class="text-nowrap">链接</th>
                            <th class="text-center text-nowrap">状态</th>
                            <th class="text-nowrap">创建时间</th>
                            <th class="text-end pe-3 text-nowrap">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contents)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">暂无产品</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($contents as $item): ?>
                        <tr>
                            <td class="ps-3" style="max-width: 280px;">
                                <div class="d-flex align-items-center">
                                    <div class="d-inline-block rounded bg-primary bg-opacity-10 p-2 text-primary me-2">
                                        <?php if (!empty($item['icon'])): ?>
                                            <?php if (strpos($item['icon'], '/') !== false): ?>
                                                <!-- 如果icon是图片URL路径 -->
                                                <img src="<?php echo htmlspecialchars($item['icon']); ?>" alt="图标" style="width: 20px; height: 20px; object-fit: contain;">
                                            <?php elseif (strpos($item['icon'], 'bi-') === 0): ?>
                                                <!-- 如果icon是Bootstrap图标类名 -->
                                                <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i>
                                            <?php else: ?>
                                                <!-- 默认显示 -->
                                                <i class="bi bi-box-seam"></i>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <i class="bi bi-box-seam"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-truncate">
                                        <?php if (isset($item['is_top']) && $item['is_top']): ?>
                                        <span class="badge bg-danger me-1">置顶</span>
                                        <?php endif; ?>
                                        <a href="?page=content&action=edit&id=<?php echo $item['id']; ?>" class="text-decoration-none text-reset fw-medium">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (isset($item['category_id']) && isset($categoryMap[$item['category_id']])): ?>
                                    <span class="badge bg-light text-dark border">
                                        <?php echo htmlspecialchars($categoryMap[$item['category_id']]['name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">未分类</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-truncate" style="max-width: 150px;">
                                <?php if (!empty($item['link'])): ?>
                                <a href="<?php echo htmlspecialchars($item['link']); ?>" target="_blank" class="text-decoration-none text-primary">
                                    <i class="bi bi-link-45deg"></i> <?php echo htmlspecialchars($item['link']); ?>
                                </a>
                                <?php else: ?>
                                <span class="text-muted"><i class="bi bi-dash"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($item['status'] === 'published'): ?>
                                <span class="badge bg-success">已发布</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">草稿</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php echo $item['create_time']; ?>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end">
                                    <a href="?page=content&action=edit&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary me-2">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-item" 
                                        data-id="<?php echo $item['id']; ?>" 
                                        data-title="<?php echo htmlspecialchars($item['title']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
                    <p class="mb-2">您确定要删除以下产品吗？</p>
                    <p class="mb-0 fw-bold" id="deleteTitle"></p>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>此操作无法撤销！
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <form method="post" id="deleteForm">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger">删除</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- 添加/编辑产品表单 -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 fw-bold">
                <?php echo $action === 'add' ? '添加新产品' : '编辑产品'; ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="post" action="?page=content&action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $id : ''; ?>" class="needs-validation" id="postForm" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="action" value="<?php echo $action; ?>">
                
                <div class="mb-4">
                    <label for="title" class="form-label">产品名称</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="bi bi-box-seam"></i></span>
                        <input type="text" name="title" id="title" class="form-control" value="<?php echo isset($post) ? htmlspecialchars($post['title']) : ''; ?>" required>
                        <div class="invalid-feedback">请输入产品名称</div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="summary" class="form-label">产品简介</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                        <input type="text" name="summary" id="summary" class="form-control" value="<?php echo isset($post) && isset($post['summary']) ? htmlspecialchars($post['summary']) : ''; ?>" required>
                        <div class="invalid-feedback">请输入产品简介</div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="link" class="form-label">链接</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                        <input type="url" name="link" id="link" class="form-control" value="<?php echo isset($post) && isset($post['link']) ? htmlspecialchars($post['link']) : ''; ?>">
                        <button type="button" id="fetchWebsiteBtn" class="btn btn-outline-primary">
                            <i class="bi bi-cloud-download"></i> 获取网站信息
                        </button>
                        <div class="invalid-feedback">请输入有效的URL</div>
                    </div>
                    <div id="fetchStatus" class="mt-2 d-none">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">加载中...</span>
                        </div>
                        <span class="ms-2 text-muted" id="fetchStatusText">正在获取网站信息...</span>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="icon" class="form-label">图标</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="bi bi-image"></i></span>
                        <input type="text" name="icon" id="icon" class="form-control" value="<?php echo isset($post) && isset($post['icon']) ? htmlspecialchars($post['icon']) : ''; ?>">
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#iconSelectorModal">
                            <i class="bi bi-grid"></i> 选择图标
                        </button>
                        <div class="invalid-feedback">请输入图标URL或类名</div>
                    </div>
                    <div class="icon-preview mt-2 mb-2">
                        <p class="form-text">图标预览：</p>
                        <div class="d-inline-block rounded bg-primary bg-opacity-10 p-2 text-primary me-2" id="iconPreviewContainer">
                            <?php if (isset($post) && !empty($post['icon'])): ?>
                                <?php if (strpos($post['icon'], '/') !== false): ?>
                                    <!-- 如果icon是图片URL路径 -->
                                    <img src="<?php echo htmlspecialchars($post['icon']); ?>" alt="图标预览" style="width: 30px; height: 30px; object-fit: contain;">
                                <?php elseif (strpos($post['icon'], 'bi-') === 0): ?>
                                    <!-- 如果icon是Bootstrap图标类名 -->
                                    <i class="<?php echo htmlspecialchars($post['icon']); ?>" style="font-size: 1.5rem;"></i>
                                <?php else: ?>
                                    <i class="bi bi-question-circle" style="font-size: 1.5rem;"></i>
                                <?php endif; ?>
                            <?php else: ?>
                                <i class="bi bi-image" style="font-size: 1.5rem;"></i>
                            <?php endif; ?>
                        </div>
                        <small class="d-block mt-2 text-muted">支持Bootstrap图标类名(如: bi-box-seam)或图片URL</small>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">分类</label>
                        <div class="input-group mb-3">
                            <span class="input-group-text"><i class="bi bi-folder"></i></span>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <option value="">选择分类</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo isset($post) && $post['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">请选择产品分类</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="status" class="form-label">状态</label>
                        <div class="input-group mb-3">
                            <span class="input-group-text"><i class="bi bi-toggle-on"></i></span>
                            <select name="status" id="status" class="form-select" required>
                                <option value="draft" <?php echo isset($post) && $post['status'] === 'draft' ? 'selected' : ''; ?>>草稿</option>
                                <option value="published" <?php echo isset($post) && $post['status'] === 'published' ? 'selected' : ''; ?>>发布</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="create_time" class="form-label">创建时间</label>
                        <div class="input-group mb-3">
                            <span class="input-group-text"><i class="bi bi-calendar-date"></i></span>
                            <input type="datetime-local" name="create_time" id="create_time" class="form-control" 
                                value="<?php echo isset($post) ? (substr($post['create_time'], 0, 10) . 'T' . substr($post['create_time'], 11, 5)) : date('Y-m-d\TH:i'); ?>">
                            <div class="invalid-feedback">请选择创建时间</div>
                        </div>
                        <small class="text-muted">可修改创建时间，影响文章排序</small>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-check form-switch" style="margin-top: 2.5rem;">
                            <input class="form-check-input" type="checkbox" name="is_top" id="is_top" value="1" <?php echo isset($post) && isset($post['is_top']) && $post['is_top'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_top">
                                <span class="badge bg-danger">置顶</span> 将此内容置顶显示（重要内容）
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="gallery" class="form-label">图片集</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="bi bi-images"></i></span>
                        <input type="text" name="gallery" id="gallery" class="form-control" value="<?php echo isset($post) && isset($post['gallery']) ? htmlspecialchars($post['gallery']) : ''; ?>">
                        <div class="invalid-feedback">请输入图片URL，多张图片用英文逗号分隔</div>
                    </div>
                    <small class="text-muted">多张图片请用英文逗号(,)分隔</small>
                </div>
                
                <div class="mb-4">
                    <label for="product_image" class="form-label">上传产品图片</label>
                    <div class="input-group">
                        <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
                        <label class="input-group-text" for="product_image"><i class="bi bi-upload"></i></label>
                    </div>
                    <small class="text-muted">支持JPG、PNG、WebP等常见图片格式，图片将按创建时间自动分类存储</small>
                    <?php if (isset($post) && !empty($post['gallery'])): ?>
                    <div class="mt-3">
                        <p class="form-text d-flex justify-content-between align-items-center">
                            <span>已上传图片：</span>
                            <span class="badge bg-info" id="galleryCounter"></span>
                        </p>
                        <div class="row g-2 mt-1" id="galleryContainer">
                            <?php
                            $galleryImages = explode(',', $post['gallery']);
                            $index = 0;
                            foreach($galleryImages as $img): 
                                $img = trim($img);
                                if(empty($img)) continue;
                                $index++;
                            ?>
                            <div class="col-md-3 col-sm-4 col-6 gallery-item" data-url="<?php echo htmlspecialchars($img); ?>">
                                <div class="card h-100 position-relative">
                                    <img src="<?php echo $img; ?>" class="card-img-top" alt="产品图片" style="height: 120px; object-fit: contain;">
                                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 delete-image" title="删除图片">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    <div class="card-footer bg-light p-2 small text-center" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        图片 #<?php echo $index; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-4">
                    <label for="content" class="form-label">详情介绍</label>
                    <div class="alert alert-info small mb-2">
                        <i class="bi bi-info-circle me-1"></i> 支持HTML格式，可以插入表格、列表、图片等。
                    </div>
                    <textarea name="content" id="content" class="form-control code-editor" rows="15" style="font-family: monospace; font-size: 14px;" required><?php echo isset($post) && isset($post['content']) ? htmlspecialchars($post['content']) : ''; ?></textarea>
                    
                    
                    <!-- 预览区域 -->
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">内容预览</h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="previewBtn">
                                <i class="bi bi-eye me-1"></i>刷新预览
                            </button>
                        </div>
                        <div id="contentPreview" class="border rounded p-3 bg-light">
                            <?php if (isset($post) && !empty($post['content'])): ?>
                            <?php echo $post['content']; ?>
                            <?php else: ?>
                            <div class="text-muted text-center py-3">内容预览区域</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="?page=content" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>返回列表
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i><?php echo $action === 'add' ? '添加产品' : '更新产品'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>

<!-- 图片删除确认模态框 -->
<div class="modal fade" id="deleteImageModal" tabindex="-1" aria-labelledby="deleteImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteImageModalLabel">确认删除</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>您确定要删除这张图片吗？</p>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>此操作无法撤销！
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteImage">删除</button>
            </div>
        </div>
    </div>
</div>

<!-- 初始化脚本，移到页面底部 -->
<script>
    // 删除操作确认
    document.addEventListener('DOMContentLoaded', function() {
        // 确保Bootstrap已加载
        if (typeof bootstrap !== 'undefined') {
            const deleteModalElement = document.getElementById('deleteModal');
            if (deleteModalElement) {
                const deleteModal = new bootstrap.Modal(deleteModalElement);
                const deleteTitle = document.getElementById('deleteTitle');
                const deleteForm = document.getElementById('deleteForm');
                
                document.querySelectorAll('.delete-item').forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const title = this.getAttribute('data-title');
                        
                        deleteTitle.textContent = title;
                        deleteForm.action = `?page=content&action=delete&id=${id}`;
                        deleteModal.show();
                    });
                });
            }
        }
    });

    // 表单验证
    (function() {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })();

    // 网站信息获取功能
    document.addEventListener('DOMContentLoaded', function() {
        const fetchWebsiteBtn = document.getElementById('fetchWebsiteBtn');
        const linkInput = document.getElementById('link');
        const titleInput = document.getElementById('title');
        const iconInput = document.getElementById('icon');
        const galleryInput = document.getElementById('gallery');
        const fetchStatus = document.getElementById('fetchStatus');
        const fetchStatusText = document.getElementById('fetchStatusText');
        
        fetchWebsiteBtn.addEventListener('click', function() {
            const url = linkInput.value.trim();
            if (!url) {
                alert('请先输入网站链接');
                linkInput.focus();
                return;
            }
            
            // 显示加载状态
            fetchStatus.classList.remove('d-none');
            fetchStatusText.textContent = '正在获取网站信息...';
            
            // 发送请求获取网站信息
            fetch('saveWebsiteInfo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'url=' + encodeURIComponent(url)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络请求失败，状态码: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('获取网站信息结果:', data); // 添加调试信息
                if (data.success) {
                    // 更新表单字段
                    if (data.title && !titleInput.value) titleInput.value = data.title;
                    if (data.description && document.getElementById('summary')) document.getElementById('summary').value = data.description;
                    if (data.favicon) iconInput.value = data.favicon;
                    if (data.screenshots) galleryInput.value = data.screenshots.join(',');
                    
                    fetchStatusText.textContent = '获取成功！';
                    fetchStatusText.classList.add('text-success');
                    // 3秒后隐藏状态
                    setTimeout(() => {
                        fetchStatus.classList.add('d-none');
                        fetchStatusText.classList.remove('text-success');
                    }, 3000);
                } else {
                    console.error('获取失败:', data.message); // 添加调试信息
                    fetchStatusText.textContent = '获取失败: ' + data.message;
                    fetchStatusText.classList.add('text-danger');
                }
            })
            .catch(error => {
                console.error('获取网站信息出错:', error);
                fetchStatusText.textContent = '获取失败，请检查网络连接或URL是否有效';
                fetchStatusText.classList.add('text-danger');
            });
        });
    });

    // 内容预览功能
    document.addEventListener('DOMContentLoaded', function() {
        const contentTextarea = document.getElementById('content');
        const previewBtn = document.getElementById('previewBtn');
        const contentPreview = document.getElementById('contentPreview');
        
        if (contentTextarea && previewBtn && contentPreview) {
            previewBtn.addEventListener('click', function() {
                contentPreview.innerHTML = contentTextarea.value;
            });
            
            // 自动调整textarea高度
            contentTextarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            
            // 初始调整高度
            if (contentTextarea.value) {
                setTimeout(function() {
                    contentTextarea.style.height = 'auto';
                    contentTextarea.style.height = (contentTextarea.scrollHeight) + 'px';
                }, 100);
            }
        }
        
        // 图标实时预览功能
        const iconInput = document.getElementById('icon');
        const iconPreviewContainer = document.getElementById('iconPreviewContainer');
        
        if (iconInput && iconPreviewContainer) {
            iconInput.addEventListener('input', function() {
                updateIconPreview(this.value);
            });
            
            // 初始化时执行一次更新
            if (iconInput.value) {
                updateIconPreview(iconInput.value);
            }
        }
        
        // 图标搜索功能
        const iconSearchInput = document.getElementById('iconSearchInput');
        if (iconSearchInput) {
            iconSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const iconItems = document.querySelectorAll('.icon-item');
                
                iconItems.forEach(item => {
                    const iconName = item.getAttribute('data-icon').toLowerCase();
                    if (iconName.includes(searchTerm)) {
                        item.style.display = 'inline-block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
        
        // 常用Bootstrap图标数组
        const bootstrapIcons = [
            'bi-box-seam', 'bi-cart', 'bi-bag', 'bi-gift', 'bi-shop', 'bi-star',
            'bi-award', 'bi-bookmark', 'bi-briefcase', 'bi-building', 'bi-camera',
            'bi-check-circle', 'bi-clipboard', 'bi-cloud', 'bi-code', 'bi-collection',
            'bi-cpu', 'bi-credit-card', 'bi-cup', 'bi-currency-dollar', 'bi-diagram-3',
            'bi-display', 'bi-document', 'bi-envelope', 'bi-file-earmark', 'bi-folder',
            'bi-gear', 'bi-gem', 'bi-graph-up', 'bi-grid', 'bi-hammer', 'bi-heart',
            'bi-house', 'bi-image', 'bi-key', 'bi-lamp', 'bi-laptop', 'bi-lightning',
            'bi-map', 'bi-megaphone', 'bi-mic', 'bi-music-note', 'bi-palette', 'bi-people',
            'bi-pin', 'bi-puzzle', 'bi-safe', 'bi-send', 'bi-server', 'bi-shield',
            'bi-signpost', 'bi-speedometer', 'bi-tag', 'bi-telephone', 'bi-tools', 'bi-trophy',
            'bi-truck', 'bi-wallet', 'bi-wifi', 'bi-window'
        ];
        
        // 生成图标选择器内容
        const iconSelectorContent = document.getElementById('iconSelectorContent');
        if (iconSelectorContent) {
            let iconsHtml = '';
            bootstrapIcons.forEach(icon => {
                iconsHtml += `
                <div class="icon-item d-inline-block m-2 text-center" data-icon="${icon}">
                    <div class="d-flex align-items-center justify-content-center rounded bg-light p-3 mb-1" style="width: 60px; height: 60px; cursor: pointer;">
                        <i class="${icon} fs-4"></i>
                    </div>
                    <small class="d-block text-truncate" style="width: 60px;">${icon}</small>
                </div>`;
            });
            iconSelectorContent.innerHTML = iconsHtml;
            
            // 绑定图标点击事件
            iconSelectorContent.querySelectorAll('.icon-item').forEach(item => {
                item.addEventListener('click', function() {
                    const icon = this.getAttribute('data-icon');
                    iconInput.value = icon;
                    updateIconPreview(icon);
                    
                    // 关闭模态框
                    const modal = bootstrap.Modal.getInstance(document.getElementById('iconSelectorModal'));
                    if (modal) modal.hide();
                });
            });
        }
        
        // 更新图标预览函数
        function updateIconPreview(iconValue) {
            iconValue = iconValue.trim();
            let previewHtml = '';
            
            if (iconValue) {
                if (iconValue.indexOf('/') !== -1) {
                    // 图片URL
                    previewHtml = `<img src="${iconValue}" alt="图标预览" style="width: 30px; height: 30px; object-fit: contain;">`;
                } else if (iconValue.indexOf('bi-') === 0) {
                    // Bootstrap图标
                    previewHtml = `<i class="${iconValue}" style="font-size: 1.5rem;"></i>`;
                } else {
                    // 未识别格式
                    previewHtml = `<i class="bi bi-question-circle" style="font-size: 1.5rem;"></i>`;
                }
            } else {
                // 空值时显示默认图标
                previewHtml = `<i class="bi bi-image" style="font-size: 1.5rem;"></i>`;
            }
            
            iconPreviewContainer.innerHTML = previewHtml;
        }
    });

    // 图片库管理
    $(document).ready(function() {
        // 初始化图片计数器
        updateGalleryCounter();
        
        // 变量用于存储当前要删除的图片元素和URL
        let currentImageItem = null;
        let currentImageUrl = '';
        
        // 删除图片按钮点击事件
        $(document).on('click', '.delete-image', function(e) {
            e.preventDefault();
            
            // 保存当前要删除的图片信息
            currentImageItem = $(this).closest('.gallery-item');
            currentImageUrl = currentImageItem.data('url');
            
            // 显示删除确认模态框
            const deleteImageModal = new bootstrap.Modal(document.getElementById('deleteImageModal'));
            deleteImageModal.show();
        });
        
        // 确认删除按钮点击事件
        $('#confirmDeleteImage').on('click', function() {
            if(currentImageItem && currentImageUrl) {
                // 从gallery字段中移除该图片URL
                let galleryValue = $('#gallery').val();
                const galleryUrls = galleryValue.split(',').map(url => url.trim()).filter(url => url !== '');
                const updatedUrls = galleryUrls.filter(url => url !== currentImageUrl);
                $('#gallery').val(updatedUrls.join(','));
                
                // 移除图片元素并更新计数
                currentImageItem.fadeOut(300, function() {
                    $(this).remove();
                    updateGalleryCounter();
                });
                
                // 关闭模态框
                bootstrap.Modal.getInstance(document.getElementById('deleteImageModal')).hide();
                
                // 清除当前选中的图片
                currentImageItem = null;
                currentImageUrl = '';
            }
        });
        
        // 更新图片计数器
        function updateGalleryCounter() {
            const count = $('.gallery-item').length;
            $('#galleryCounter').text(`${count} 张图片`);
        }
    });
</script>

<!-- 图标选择器模态框 -->
<div class="modal fade" id="iconSelectorModal" tabindex="-1" aria-labelledby="iconSelectorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="iconSelectorModalLabel">选择Bootstrap图标</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="iconSearchInput" placeholder="搜索图标..." autocomplete="off">
                </div>
                <div class="icons-container" id="iconSelectorContent">
                    <!-- 图标将通过JavaScript动态生成 -->
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">加载中...</span>
                        </div>
                        <p class="mt-2">正在加载图标...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
            </div>
        </div>
    </div>
</div> 
</body>
</html> 