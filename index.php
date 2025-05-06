<?php
/**
 * 网站首页
 */

// 启用输出缓冲，解决header已发送问题
ob_start();

// 启用会话
session_start();

// 定义根目录
define('__ROOT_DIR__', dirname(__FILE__));

// 检查配置文件是否存在
if (!file_exists(__ROOT_DIR__ . '/config.inc.php')) {
    header('Location: install.php');
    exit;
}

// 加载配置文件
require_once __ROOT_DIR__ . '/config.inc.php';

// 获取当前页码
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($db['per_page']) ? intval($db['per_page']) : 10;
$offset = ($page - 1) * $limit;

// 获取当前分类
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;

// 连接数据库
try {
    $db_file = __ROOT_DIR__ . '/data/data.db';
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取分类
    $categories = [];
    $stmt = $pdo->query('SELECT id, name, slug FROM categories ORDER BY id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[$row['id']] = $row;
    }
    
    // 构建查询条件
    $where = "WHERE p.status = 'published'"; // 只显示已发布的文章
    $params = [];
    
    if ($category_id > 0) {
        $where .= ' AND p.category_id = ?';
        $params[] = $category_id;
    }
    
    // 获取文章总数
    $countSql = "SELECT COUNT(*) FROM posts p $where";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    $total_pages = ceil($total / $limit);
    
    // 获取最新的创建日期（不考虑具体时间）
    $latestDateSql = "SELECT MAX(date(create_time)) as latest_date FROM posts p $where";
    $stmt = $pdo->prepare($latestDateSql);
    $stmt->execute($params);
    $latestDateRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $latestDate = $latestDateRow ? $latestDateRow['latest_date'] : '';
    
    // 构建查询条件
    if ($category_id > 0) {
        // 如果是分类页面，显示该分类下的所有产品
        $latestWhere = $where;
        $latestParams = $params;
    } else {
        // 如果是首页，只显示最新日期的产品
        $latestWhere = $where . " AND date(p.create_time) = ?";
        $latestParams = $params;
        $latestParams[] = $latestDate;
    }
    
    // 单独查询置顶产品，不限制日期（实现置顶产品始终可见）
    $topProductsSql = "SELECT p.*, u.username, c.name as category_name 
                FROM posts p 
                LEFT JOIN users u ON p.user_id = u.id 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.status = 'published' AND p.is_top = 1 ";
    
    // 如果有分类筛选，也应用到置顶产品查询
    if ($category_id > 0) {
        $topProductsSql .= " AND p.category_id = ? ";
        $topProductsParams = [$category_id];
    } else {
        $topProductsParams = [];
    }
    
    $topProductsSql .= " ORDER BY p.create_time DESC LIMIT 10";
    $stmt = $pdo->prepare($topProductsSql);
    $stmt->execute($topProductsParams);
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取最新日期非置顶产品
    $latestNonTopSql = "SELECT p.*, u.username, c.name as category_name 
                FROM posts p 
                LEFT JOIN users u ON p.user_id = u.id 
                LEFT JOIN categories c ON p.category_id = c.id 
                $latestWhere AND p.is_top = 0
                ORDER BY p.create_time DESC 
                LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($latestNonTopSql);
    $stmt->execute($latestParams);
    $latestNonTopProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 组合置顶产品和最新产品
    $posts = array_merge($topProducts, $latestNonTopProducts);
    
    // 获取最新日期产品总数（用于分页计算）
    $latestCountSql = "SELECT COUNT(*) FROM posts p $latestWhere";
    $stmt = $pdo->prepare($latestCountSql);
    $stmt->execute($latestParams);
    $latestTotal = $stmt->fetchColumn();
    $total_pages = ceil($latestTotal / $limit);
    
    // 最新日期格式化为可读形式（只显示日期，不显示时间）
    if (!empty($latestDate)) {
        $latestDateObj = new DateTime($latestDate);
        $latestDateFormatted = $latestDateObj->format('Y年m月d日');
    } else {
        $latestDateFormatted = '';
    }
    
    // 用于调试
    echo "<!-- 最新创建日期: $latestDate ($latestDateFormatted), 找到: $latestTotal 个产品 -->";
    echo "<!-- 当前分页设置: 每页 $limit 条, 当前第 $page 页, 共 $total_pages 页 -->";
    
    // 获取热门文章（简单实现，实际可能需要基于浏览量等）
    $hotPostsSql = "SELECT p.id, p.title 
                   FROM posts p 
                   WHERE p.status = 'published' 
                   ORDER BY RANDOM() 
                   LIMIT 10";
    $stmt = $pdo->query($hotPostsSql);
    $hotPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = '数据库错误: ' . $e->getMessage();
}

// 获取当前主题
$theme = isset($db['theme']) ? $db['theme'] : 'default';
$themeDir = __ROOT_DIR__ . '/themes/' . $theme;

// 如果主题目录不存在，使用默认主题
if (!is_dir($themeDir)) {
    $theme = 'default';
    $themeDir = __ROOT_DIR__ . '/themes/default';
}

// 加载主题设置
$themeConfig = [];
if (file_exists($themeDir . '/config.php')) {
    include($themeDir . '/config.php');
}

// 网站信息
$site = [
    'name' => $db['name'],
    'subtitle' => isset($db['subtitle']) ? $db['subtitle'] : '',
    'description' => $db['description'],
    'keywords' => $db['keywords'],
    'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
    'favicon' => isset($db['favicon']) ? $db['favicon'] : '',
];

// 广告配置
$ad_enable = isset($db['ad_enable']) ? (bool)$db['ad_enable'] : false;
$ad_position = isset($db['ad_position']) ? $db['ad_position'] : 'sidebar_bottom';
$ad_code = isset($db['ad_code']) ? $db['ad_code'] : '';

// 检查是否有主题的header.php和footer.php文件
$hasThemeHeader = file_exists($themeDir . '/header.php');
$hasThemeFooter = file_exists($themeDir . '/footer.php');

// 加载主题的index.php文件
if (file_exists($themeDir . '/index.php')) {
    include($themeDir . '/index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site['name']); ?><?php echo !empty($site['subtitle']) ? ' - ' . htmlspecialchars($site['subtitle']) : ''; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($site['description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($site['keywords']); ?>">
    <?php if (!empty($site['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($site['favicon']); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($site['favicon']); ?>" type="image/x-icon">
    <?php endif; ?>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="admin/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* 全局样式 */
        body {
            font-family: 'Segoe UI', 'Microsoft YaHei', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        /* 头部导航栏样式 */
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 700;
        }
        .nav-link {
            font-weight: 500;
            margin: 0 3px;
        }
        
        /* 卡片样式 */
        .card {
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
            border-radius: 16px !important;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }
        .card-img-overlay {
            background: linear-gradient(to bottom, rgba(0,0,0,0), rgba(0,0,0,0.8));
        }
        
        /* 产品图标样式 */
        .product-icon {
            width: 50px; 
            height: 50px; 
            object-fit: contain;
        }
        
        .product-icon-text {
            font-size: 2rem;
        }
        
        /* 应用卡片样式 */
        .app-icon {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            margin-right: 1rem;
            font-size: 2rem;
            color: #fff;
        }
        
        .app-card .card-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        
        .app-card .card-text {
            color: #6c757d;
            font-size: 0.85rem;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .app-tag {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            background-color: #f0f0f0;
            color: #666;
        }
        
        /* 老的文章卡片样式 */
        .article-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .article-card .card-body {
            flex: 1;
        }
        .article-info {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .article-content {
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        /* 徽章样式 */
        .badge-category {
            color: #fff;
            background-color: #0d6efd;
            font-weight: 400;
            padding: 0.25rem 0.5rem;
        }
        
        /* 侧边栏 */
        .sidebar-card {
            margin-bottom: 1.5rem;
        }
        .sidebar-card .card-header {
            background-color: #fff;
            border-bottom: 2px solid #0d6efd;
            padding: 0.75rem 1rem;
        }
        .sidebar-card h5 {
            margin-bottom: 0;
            font-weight: 600;
        }
        
        /* 分类卡片样式 */
        .category-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 1rem;
        }
        .category-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: calc(33.333% - 0.5rem);
            padding: 0.75rem 0.5rem;
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s ease;
            color: #333;
        }
        .category-card:hover {
            background-color: #e9ecef;
            transform: translateY(-3px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }
        .category-card.active {
            background-color: #0d6efd;
            color: white;
        }
        .category-card i {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }
        .category-card .badge {
            margin-top: 0.25rem;
            background-color: rgba(0,0,0,0.1);
            color: inherit;
        }
        .category-card.active .badge {
            background-color: rgba(255,255,255,0.2);
        }
        .category-card-name {
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }
        
        /* 热门文章样式 */
        .hot-posts-list {
            counter-reset: post-counter;
        }
        .hot-posts-list .list-group-item {
            position: relative;
            padding-left: 2.5rem;
            border: none;
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
            transition: background-color 0.2s ease;
        }
        .hot-posts-list .list-group-item:hover {
            background-color: #f8f9fa;
        }
        .hot-posts-list .list-group-item::before {
            counter-increment: post-counter;
            content: counter(post-counter);
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            background-color: #e9ecef;
            color: #495057;
            font-size: 0.85rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 3px;
        }
        .hot-posts-list .list-group-item:nth-child(-n+3)::before {
            background-color: #0d6efd;
            color: white;
        }
        
        /* 分页样式 */
        .pagination .page-link {
            color: #0d6efd;
            border: none;
            border-radius: 0.25rem;
            margin: 0 2px;
        }
        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            color: #fff;
        }
        
        /* 轮播图样式 */
        .carousel-item {
            height: 350px;
            background-size: cover;
            background-position: center;
        }
        .carousel-caption {
            background: linear-gradient(to top, rgba(0,0,0,0.8), rgba(0,0,0,0));
            left: 0;
            right: 0;
            bottom: 0;
            padding-bottom: 2rem;
            padding-top: 3rem;
        }
        
        /* 页脚样式 */
        footer {
            background-color: #343a40;
            color: #f8f9fa;
            padding: 2rem 0;
        }
        footer a {
            color: #e9ecef;
            text-decoration: none;
        }
        footer a:hover {
            color: #fff;
            text-decoration: underline;
        }
        
        /* 置顶徽章动画效果 */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.8; }
            100% { opacity: 1; }
        }
        
        /* 响应式优化 */
        @media (max-width: 767.98px) {
            .list-group-item {
                padding: 0.75rem 0.5rem;
            }
            
            .category-card {
                width: calc(50% - 0.5rem);
            }
            
            .carousel-item {
                height: 250px;
            }
            
            .app-icon, 
            .me-3.rounded-3.d-flex {
                width: 60px !important;
                height: 60px !important;
                min-width: 60px !important;
            }
            
            .app-icon .fs-1,
            .me-3.rounded-3.d-flex .fs-1 {
                font-size: 1.5rem !important;
            }
            
            .hot-posts-list .list-group-item {
                padding-left: 2rem;
            }
            
            .hot-posts-list .list-group-item::before {
                width: 20px;
                height: 20px;
                font-size: 0.75rem;
                left: 0.5rem;
            }
            
            h5.mb-1 {
                font-size: 1rem;
            }
            
            .badge {
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 575.98px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .list-group-item {
                padding: 0.5rem;
            }
            
            .category-card {
                width: calc(50% - 0.5rem);
                padding: 0.5rem 0.25rem;
            }
            
            .category-card i {
                font-size: 1rem;
                margin-bottom: 0.25rem;
            }
            
            .category-card-name {
                font-size: 0.7rem;
            }
            
            .badge {
                padding: 0.15rem 0.4rem;
            }
            
            .app-icon, 
            .me-3.rounded-3.d-flex {
                width: 50px !important;
                height: 50px !important;
                min-width: 50px !important;
                margin-right: 0.5rem !important;
            }
            
            .app-icon .fs-1,
            .me-3.rounded-3.d-flex .fs-1 {
                font-size: 1.25rem !important;
            }
            
            .mb-0.text-muted.small.me-3.text-truncate {
                max-width: 120px !important;
            }
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <div class="bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; border-radius: 8px;">
                    <i class="bi bi-layers-half"></i>
                </div>
                <?php echo htmlspecialchars($site['name']); ?>
                <?php if (!empty($site['subtitle'])): ?>
                <small class="ms-2 text-muted fw-normal d-none d-md-inline"><?php echo htmlspecialchars($site['subtitle']); ?></small>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                    <li class="nav-item">
                        <a class="nav-link text-primary" href="admin/index.php" title="管理后台">
                            <i class="bi bi-gear"></i>
                    </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/index.php" title="登录">
                            <i class="bi bi-box-arrow-in-right"></i>
                    </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- 主要内容 -->
    <main class="py-5">
        <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger mb-4 rounded-4">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
            <div class="row">
            <!-- 左侧内容区域 -->
                <div class="col-lg-8">
                <?php /* 删除了轮播图/置顶产品部分 */ ?>
                
                <!-- 内容顶部广告 -->
                <?php 
                if (isset($db['ad_enable']) && $db['ad_enable'] && 
                    isset($db['ad_position']) && $db['ad_position'] === 'content_top' && 
                    !empty($db['ad_code'])): 
                ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex align-items-center">
                        <h5 class="mb-0"><i class="bi bi-megaphone me-2"></i>广告</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                            <?php echo $db['ad_code']; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                    
                    <!-- 显示置顶产品标题，如果有置顶产品 -->
                    <?php 
                    // 把$posts分成置顶和非置顶两部分
                    $topPosts = [];
                    $normalPosts = [];
                    
                    foreach($posts as $post) {
                        if(isset($post['is_top']) && $post['is_top'] == 1) {
                            $topPosts[] = $post;
                        } else {
                            $normalPosts[] = $post;
                        }
                    }
                    ?>
                    
                    <?php if(!empty($topPosts)): ?>
                    <div class="d-flex align-items-center mb-3 mt-2">
                        <h5 class="mb-0">
                            <i class="bi bi-bookmark-star-fill me-2 text-danger"></i>置顶产品
                        </h5>
                        <div class="ms-auto">
                            <span class="badge bg-danger"><?php echo count($topPosts); ?></span>
                        </div>
                    </div>
                    
                    <div class="list-group shadow-sm rounded-3 overflow-hidden mb-4">
                        <?php foreach($topPosts as $post): 
                            // 获取随机背景色
                            $colors = ['primary', 'info', 'success', 'warning', 'danger', 'secondary'];
                            $color = $colors[array_rand($colors)];
                            // 获取首字母
                            $firstLetter = mb_substr(trim($post['title']), 0, 1);
                        ?>
                        <a href="article.php?id=<?php echo $post['id']; ?>" class="list-group-item list-group-item-action py-3 px-4 border-bottom" style="background-color: rgba(220, 53, 69, 0.03);">
                            <div class="d-flex align-items-center">
                                <!-- 文章图标 -->
                                <div class="me-3 rounded-3 d-flex align-items-center justify-content-center <?php echo empty($post['icon']) ? 'text-'.$color.' bg-'.$color.' bg-opacity-10' : 'bg-primary bg-opacity-10'; ?>" style="width: 80px; height: 80px; min-width: 80px;">
                                    <?php if (!empty($post['icon'])): ?>
                                        <?php if (strpos($post['icon'], '/') !== false): ?>
                                            <!-- 如果icon是图片URL路径 -->
                                            <img src="<?php echo htmlspecialchars($post['icon']); ?>" alt="图标" class="product-icon" style="width: 50px; height: 50px; object-fit: contain;">
                                        <?php elseif (strpos($post['icon'], 'bi-') === 0): ?>
                                            <!-- 如果icon是Bootstrap图标类名 -->
                                            <i class="<?php echo htmlspecialchars($post['icon']); ?> fs-1 product-icon-text"></i>
                                        <?php else: ?>
                                            <!-- 未识别格式，显示首字母 -->
                                            <span class="fs-1 product-icon-text"><?php echo htmlspecialchars($firstLetter); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- 无图标时显示首字母 -->
                                        <span class="fs-1 product-icon-text"><?php echo htmlspecialchars($firstLetter); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">
                                        <span class="badge bg-danger me-2 fw-bold" style="animation: pulse 2s infinite;">置顶</span>
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </h5>
                                    <div class="d-flex align-items-center">
                                        <p class="mb-0 text-muted small me-3 text-truncate" style="max-width: 200px;">
                                        <?php 
                                        // 显示文章摘要，去除HTML标签
                                        $content = strip_tags($post['content']);
                                        echo mb_substr($content, 0, 30) . (mb_strlen($content) > 30 ? '...' : '');
                                        ?>
                                        </p>
                                        <?php if ($post['category_id'] && isset($categories[$post['category_id']])): ?>
                                        <span class="badge bg-light text-secondary small">
                                            <i class="bi bi-folder me-1"></i><?php echo htmlspecialchars($post['category_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="ms-3 text-primary">
                                    <i class="bi bi-chevron-right"></i>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 最新日期产品标题 -->
                    <div class="d-flex align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar-check me-2 text-primary"></i>
                            <?php if ($category_id > 0): ?>
                                <?php echo htmlspecialchars($categories[$category_id]['name']); ?>产品
                            <?php else: ?>
                                最新产品
                            <?php endif; ?>
                        </h5>
                        <div class="ms-auto">
                            <span class="badge bg-primary"><?php echo count($normalPosts); ?></span>
                        </div>
                    </div>
                    
                    <!-- 非置顶产品列表 -->
                    <div class="list-group shadow-sm rounded-3 overflow-hidden">
                        <?php if(empty($normalPosts)): ?>
                        <div class="list-group-item py-4 text-center text-muted">
                            <i class="bi bi-inbox fs-3 mb-2 d-block"></i>
                            暂无最新日期产品
                        </div>
                        <?php else: ?>
                        <?php foreach($normalPosts as $post): 
                            // 获取随机背景色
                            $colors = ['primary', 'info', 'success', 'warning', 'danger', 'secondary'];
                            $color = $colors[array_rand($colors)];
                            // 获取首字母
                            $firstLetter = mb_substr(trim($post['title']), 0, 1);
                        ?>
                        <a href="article.php?id=<?php echo $post['id']; ?>" class="list-group-item list-group-item-action py-3 px-4 border-bottom">
                            <div class="d-flex align-items-center">
                                <!-- 文章图标 -->
                                <div class="me-3 rounded-3 d-flex align-items-center justify-content-center <?php echo empty($post['icon']) ? 'text-'.$color.' bg-'.$color.' bg-opacity-10' : 'bg-primary bg-opacity-10'; ?>" style="width: 80px; height: 80px; min-width: 80px;">
                                    <?php if (!empty($post['icon'])): ?>
                                        <?php if (strpos($post['icon'], '/') !== false): ?>
                                            <!-- 如果icon是图片URL路径 -->
                                            <img src="<?php echo htmlspecialchars($post['icon']); ?>" alt="图标" class="product-icon" style="width: 50px; height: 50px; object-fit: contain;">
                                        <?php elseif (strpos($post['icon'], 'bi-') === 0): ?>
                                            <!-- 如果icon是Bootstrap图标类名 -->
                                            <i class="<?php echo htmlspecialchars($post['icon']); ?> fs-1 product-icon-text"></i>
                                        <?php else: ?>
                                            <!-- 未识别格式，显示首字母 -->
                                            <span class="fs-1 product-icon-text"><?php echo htmlspecialchars($firstLetter); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- 无图标时显示首字母 -->
                                        <span class="fs-1 product-icon-text"><?php echo htmlspecialchars($firstLetter); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </h5>
                                    <div class="d-flex align-items-center">
                                        <p class="mb-0 text-muted small me-3 text-truncate" style="max-width: 200px;">
                                        <?php 
                                        // 显示文章摘要，去除HTML标签
                                        $content = strip_tags($post['content']);
                                        echo mb_substr($content, 0, 30) . (mb_strlen($content) > 30 ? '...' : '');
                                        ?>
                                        </p>
                                        <?php if ($post['category_id'] && isset($categories[$post['category_id']])): ?>
                                        <span class="badge bg-light text-secondary small">
                                            <i class="bi bi-folder me-1"></i><?php echo htmlspecialchars($post['category_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="ms-3 text-muted">
                                    <i class="bi bi-chevron-right"></i>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 历史日期发布的产品 -->
                    <?php
                    // 只有在首页（没有选择分类）时才显示历史日期产品
                    if ($category_id == 0):
                    
                    // 获取所有有产品的日期
                    try {
                        // 添加调试信息
                        echo "<!-- 最新日期: $latestDate -->";
                        
                        // 构建查询条件
                        $historicalWhere = "WHERE p.status = 'published'";
                        $historicalParams = [];
                        
                        if ($category_id > 0) {
                            $historicalWhere .= ' AND p.category_id = ?';
                            $historicalParams[] = $category_id;
                        }
                        
                        // 排除最新日期（已在最上方显示）
                        $historicalWhere .= " AND date(p.create_time) != ?";
                        $historicalParams[] = $latestDate;
                        
                        // 查询所有不同的发布日期（按日期分组）
                        $dateSql = "SELECT DISTINCT date(p.create_time) as post_date 
                                   FROM posts p 
                                   $historicalWhere 
                                   ORDER BY post_date DESC";
                        $stmt = $pdo->prepare($dateSql);
                        $stmt->execute($historicalParams);
                        $allDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        // 循环显示每个日期的产品
                        foreach ($allDates as $postDate) {
                            // 构建查询条件：显示特定日期的产品
                            $dayWhere = "WHERE p.status = 'published' AND date(p.create_time) = ?";
                            $dayParams = [$postDate];
                            
                            if ($category_id > 0) {
                                $dayWhere .= ' AND p.category_id = ?';
                                $dayParams[] = $category_id;
                            }
                            
                            // 获取特定日期的产品列表
                            $dayPostsSql = "SELECT p.*, u.username, c.name as category_name 
                                           FROM posts p 
                                           LEFT JOIN users u ON p.user_id = u.id 
                                           LEFT JOIN categories c ON p.category_id = c.id 
                                           $dayWhere 
                                           ORDER BY p.create_time DESC";
                            $stmt = $pdo->prepare($dayPostsSql);
                            $stmt->execute($dayParams);
                            $dayPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // 如果有产品，显示列表
                            if (!empty($dayPosts)) {
                                // 格式化日期为 "X月X日"
                                $dateObj = new DateTime($postDate);
                                $month = $dateObj->format('n');  // 不带前导零的月份
                                $day = $dateObj->format('j');    // 不带前导零的日期
                                ?>
                                <div class="mt-4 mb-3">
                                    <div class="d-flex align-items-center mb-3">
                                        <h5 class="mb-0">
                                            <i class="bi bi-calendar-check me-2 text-info"></i>
                                            <?php echo $month . '月' . $day . '日'; ?>
                                        </h5>
                                        <div class="ms-auto">
                                            <span class="badge bg-info"><?php echo count($dayPosts); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="list-group shadow-sm rounded-3 overflow-hidden">
                                        <?php foreach ($dayPosts as $post): 
                                            // 为每篇文章生成随机背景色
                                            $colors = ['primary', 'info', 'success', 'warning', 'danger', 'secondary'];
                                            $color = $colors[array_rand($colors)];
                                            // 获取文章首字母作为图标
                                            $firstLetter = mb_substr(trim($post['title']), 0, 1);
                                        ?>
                                        <a href="article.php?id=<?php echo $post['id']; ?>" class="list-group-item list-group-item-action py-3 px-4 border-bottom">
                                            <div class="d-flex align-items-center">
                                                <!-- 文章图标 -->
                                                <div class="me-3 rounded-3 d-flex align-items-center justify-content-center <?php echo empty($post['icon']) ? 'text-'.$color.' bg-'.$color.' bg-opacity-10' : 'bg-primary bg-opacity-10'; ?>" style="width: 80px; height: 80px; min-width: 80px;">
                                                    <?php if (!empty($post['icon'])): ?>
                                                        <?php if (strpos($post['icon'], '/') !== false): ?>
                                                            <!-- 如果icon是图片URL路径 -->
                                                            <img src="<?php echo htmlspecialchars($post['icon']); ?>" alt="图标" style="width: 50px; height: 50px; object-fit: contain;">
                                                        <?php elseif (strpos($post['icon'], 'bi-') === 0): ?>
                                                            <!-- 如果icon是Bootstrap图标类名 -->
                                                            <i class="<?php echo htmlspecialchars($post['icon']); ?> fs-1"></i>
                                                        <?php else: ?>
                                                            <!-- 未识别格式，显示首字母 -->
                                                            <span class="fs-1"><?php echo htmlspecialchars($firstLetter); ?></span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <!-- 无图标时显示首字母 -->
                                                        <span class="fs-1"><?php echo htmlspecialchars($firstLetter); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-1">
                                                    <?php echo htmlspecialchars($post['title']); ?>
                                                    </h5>
                                                    <div class="d-flex align-items-center">
                                                        <p class="mb-0 text-muted small me-3 text-truncate" style="max-width: 200px;">
                                                <?php 
                                                // 显示文章摘要，去除HTML标签
                                                $content = strip_tags($post['content']);
                                                            echo mb_substr($content, 0, 30) . (mb_strlen($content) > 30 ? '...' : '');
                                                            ?>
                                                        </p>
                                                        <?php if ($post['category_id'] && isset($categories[$post['category_id']])): ?>
                                                        <span class="badge bg-light text-secondary small">
                                                            <i class="bi bi-folder me-1"></i><?php echo htmlspecialchars($post['category_name']); ?>
                                                        </span>
                                                        <?php endif; ?>
                                                    </div>
                                            </div>
                                                <div class="ms-3 text-muted">
                                                    <i class="bi bi-chevron-right"></i>
                                                </div>
                                            </div>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                    } catch (Exception $e) {
                        // 出错时不显示任何内容，可以添加调试信息
                        echo "<!-- 查询历史产品出错: " . $e->getMessage() . " -->";
                    }
                    
                    // 结束历史日期产品的条件判断
                    endif;
                    ?>
                    
                    <!-- 分页 -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="my-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link rounded-circle mx-1" href="?<?php echo $category_id ? 'category=' . $category_id . '&' : ''; ?>page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // 显示分页链接
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $start_page + 4);
                            
                            // 如果页码范围不足5页，调整起始位置
                            if ($end_page - $start_page < 4) {
                                $start_page = max(1, $end_page - 4);
                            }
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link rounded-circle mx-1" href="?<?php echo $category_id ? 'category=' . $category_id . '&' : ''; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link rounded-circle mx-1" href="?<?php echo $category_id ? 'category=' . $category_id . '&' : ''; ?>page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                                    <?php endif; ?>
                        </ul>
                                </nav>
                    <?php endif; ?>
                    
                    <!-- 内容底部广告 -->
                    <?php 
                    if (isset($db['ad_enable']) && $db['ad_enable'] && 
                        isset($db['ad_position']) && $db['ad_position'] === 'content_bottom' && 
                        !empty($db['ad_code'])): 
                    ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex align-items-center">
                            <h5 class="mb-0"><i class="bi bi-megaphone me-2"></i>广告</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center">
                                <?php echo $db['ad_code']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
            </div>
            
            <!-- 右侧侧边栏 -->
                <div class="col-lg-4 mt-4 mt-lg-0">
                <!-- 站点信息 -->
                    <div class="card shadow-sm sidebar-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>关于</h5>
                    </div>
                        <div class="card-body">
                            <p class="card-text"><?php echo $site['description']; ?></p>
                    </div>
                </div>
                
                <!-- 分类列表 -->
                    <div class="card shadow-sm sidebar-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-folder me-2"></i>分类</h5>
                    </div>
                        <div class="card-body p-0">
                            <div class="category-cards">
                                <a href="index.php" class="category-card <?php echo !$category_id ? 'active' : ''; ?>">
                                    <i class="bi bi-archive"></i>
                                    <span class="category-card-name">全部</span>
                                    <?php
                                    // 获取所有已发布产品数量
                                    try {
                                        $allCountStmt = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'published'");
                                        $allCount = $allCountStmt->fetchColumn();
                                    } catch (Exception $e) {
                                        $allCount = $total;
                                    }
                                    ?>
                                    <span class="badge rounded-pill"><?php echo $allCount; ?></span>
                                </a>
                                    <?php
                                $categoryCount = 0;
                                foreach ($categories as $cat): 
                                    // 获取该分类下的文章数量
                                    try {
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE status = 'published' AND category_id = ?");
                                        $stmt->execute([$cat['id']]);
                                        $count = $stmt->fetchColumn();
                                    } catch (Exception $e) {
                                        $count = 0;
                                    }
                                    $categoryCount++;
                                    $class = $categoryCount > 5 ? 'category-card more-category d-none' : 'category-card';
                                    ?>
                                <a href="?category=<?php echo $cat['id']; ?>" class="<?php echo $class; ?> <?php echo $category_id == $cat['id'] ? 'active' : ''; ?>">
                                    <i class="bi bi-folder"></i>
                                    <span class="category-card-name"><?php echo htmlspecialchars($cat['name']); ?></span>
                                    <span class="badge rounded-pill"><?php echo $count; ?></span>
                                </a>
                            <?php endforeach; ?>
                                
                                <?php if (count($categories) > 5): ?>
                                <div class="text-center w-100 mt-2">
                                    <button id="show-more-categories" class="btn btn-sm btn-outline-primary rounded-pill my-2">
                                        <i class="bi bi-plus-circle me-1"></i>查看更多
                                    </button>
                                    <button id="show-less-categories" class="btn btn-sm btn-outline-secondary rounded-pill my-2 d-none">
                                        <i class="bi bi-dash-circle me-1"></i>收起
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                    </div>
                </div>
                
                <!-- 热门 -->
                    <div class="card shadow-sm sidebar-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-fire me-2"></i>热门</h5>
                        </div>
                        <div class="card-body p-0">
                        <?php if (empty($hotPosts)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-emoji-frown mb-2 fs-4"></i>
                                <p class="mb-0">暂无热门</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush hot-posts-list">
                            <?php foreach ($hotPosts as $index => $post): ?>
                                <a href="article.php?id=<?php echo $post['id']; ?>" class="list-group-item list-group-item-action">
                                    <h6 class="mb-0 text-truncate"><?php echo htmlspecialchars($post['title']); ?></h6>
                                </a>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    </div>
                
                <!-- 广告模块 -->
                <?php 
                // 只有启用了广告且选择了侧边栏底部位置时才显示
                if (isset($db['ad_enable']) && $db['ad_enable'] && 
                    isset($db['ad_position']) && $db['ad_position'] === 'sidebar_bottom' && 
                    !empty($db['ad_code'])): 
                ?>
                    <div class="card shadow-sm sidebar-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-megaphone me-2"></i>广告</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center">
                                <?php echo $db['ad_code']; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 初始化轮播
        document.addEventListener('DOMContentLoaded', function() {
            // 如果存在轮播元素，初始化轮播
            if (document.querySelector('#carouselTopPosts')) {
                new bootstrap.Carousel(document.querySelector('#carouselTopPosts'), {
                    interval: 5000,
                    touch: true
                });
            }
            
            // 分类展开收起功能
            const showMoreBtn = document.getElementById('show-more-categories');
            const showLessBtn = document.getElementById('show-less-categories');
            const moreCategories = document.querySelectorAll('.more-category');
            
            if (showMoreBtn) {
                showMoreBtn.addEventListener('click', function() {
                    moreCategories.forEach(item => {
                        item.classList.remove('d-none');
                    });
                    showMoreBtn.classList.add('d-none');
                    showLessBtn.classList.remove('d-none');
                });
            }
            
            if (showLessBtn) {
                showLessBtn.addEventListener('click', function() {
                    moreCategories.forEach(item => {
                        item.classList.add('d-none');
                    });
                    showLessBtn.classList.add('d-none');
                    showMoreBtn.classList.remove('d-none');
                });
            }
        });
    </script>
</body>
</html>
