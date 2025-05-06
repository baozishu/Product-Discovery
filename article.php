<?php
/**
 * 文章详情页
 */

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

// 获取文章ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// 连接数据库
try {
    $db_file = __ROOT_DIR__ . '/data/data.db';
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取文章详情
    $stmt = $pdo->prepare("SELECT p.*, u.username, c.name as category_name 
                          FROM posts p 
                          LEFT JOIN users u ON p.user_id = u.id 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.id = ? AND p.status = 'published'");
    $stmt->execute([$id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        header('Location: index.php');
        exit;
    }
    
    // 获取分类
    $categories = [];
    $stmt = $pdo->query('SELECT id, name, slug FROM categories ORDER BY id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[$row['id']] = $row;
    }
    
    // 获取相关文章（同分类的其他文章）
    $relatedPosts = [];
    if ($post['category_id'] > 0) {
        $stmt = $pdo->prepare("SELECT id, title, create_time 
                              FROM posts 
                              WHERE category_id = ? AND id != ? AND status = 'published' 
                              ORDER BY create_time DESC 
                              LIMIT 5");
        $stmt->execute([$post['category_id'], $id]);
        $relatedPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 获取上一篇和下一篇文章
    $prevPost = null;
    $stmt = $pdo->prepare("SELECT id, title 
                          FROM posts 
                          WHERE id < ? AND status = 'published' 
                          ORDER BY id DESC 
                          LIMIT 1");
    $stmt->execute([$id]);
    $prevPost = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextPost = null;
    $stmt = $pdo->prepare("SELECT id, title 
                          FROM posts 
                          WHERE id > ? AND status = 'published' 
                          ORDER BY id ASC 
                          LIMIT 1");
    $stmt->execute([$id]);
    $nextPost = $stmt->fetch(PDO::FETCH_ASSOC);
    
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

// 网站信息
$site = [
    'name' => $db['name'],
    'subtitle' => isset($db['subtitle']) ? $db['subtitle'] : '',
    'description' => $db['description'],
    'keywords' => $db['keywords'],
    'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
    'favicon' => isset($db['favicon']) ? $db['favicon'] : '',
];

// 页面标题
$pageTitle = $post['title'] . ' - ' . $site['name'];
if (!empty($site['subtitle'])) {
    $pageTitle .= ' - ' . $site['subtitle'];
}

// 加载主题的article.php文件
if (file_exists($themeDir . '/article.php')) {
    include($themeDir . '/article.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(mb_substr(strip_tags($post['content']), 0, 150)); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars(isset($post['tags']) ? $post['tags'] : ''); ?>">
    <?php if (!empty($site['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($site['favicon']); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($site['favicon']); ?>" type="image/x-icon">
    <?php endif; ?>
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            background: linear-gradient(to right, #ffffff, #f8f9fa);
        }
        .navbar-brand {
            font-weight: 700;
        }
        .nav-link {
            font-weight: 500;
            margin: 0 3px;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            transform: translateY(-2px);
        }
        
        /* 文章内容样式 */
        .article-content {
            line-height: 1.8;
        }
        .article-content p {
            margin-bottom: 1.2rem;
        }
        .article-content h1, .article-content h2, .article-content h3, 
        .article-content h4, .article-content h5, .article-content h6 {
            font-weight: bold;
            margin-top: 1.8rem;
            margin-bottom: 1.2rem;
            color: #212529;
        }
        .article-content h1 { font-size: 2rem; }
        .article-content h2 { font-size: 1.5rem; }
        .article-content h3 { font-size: 1.25rem; }
        .article-content a {
            color: #0d6efd;
            text-decoration: none;
            border-bottom: 1px solid rgba(13, 110, 253, 0.2);
            transition: all 0.2s ease;
        }
        .article-content a:hover {
            color: #0a58ca;
            border-bottom-color: rgba(10, 88, 202, 0.4);
        }
        .article-content ul, .article-content ol {
            margin-left: 1.5rem;
            margin-bottom: 1.2rem;
        }
        .article-content ul { list-style-type: disc; }
        .article-content ol { list-style-type: decimal; }
        .article-content img {
            max-width: 100%;
            height: auto;
            margin: 1.2rem 0;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        .article-content blockquote {
            border-left: 4px solid #0d6efd;
            padding: 1rem 1.5rem;
            margin: 1.5rem 0;
            background-color: rgba(13, 110, 253, 0.05);
            border-radius: 0 8px 8px 0;
        }
        .article-content code {
            background-color: #f3f4f6;
            padding: 0.2rem 0.4rem;
            border-radius: 0.25rem;
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.875em;
            color: #d63384;
        }
        .article-content pre {
            background-color: #212529;
            color: #f8f9fa;
            padding: 1.2rem;
            border-radius: 8px;
            overflow-x: auto;
            margin-bottom: 1.2rem;
        }
        .article-content pre code {
            background-color: transparent;
            padding: 0;
            color: inherit;
        }
        
        /* 卡片样式 */
        .card {
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
        }
        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.25rem 1.5rem;
        }
        .card-body {
            padding: 1.5rem;
        }
        
        /* 按钮样式增强 */
        .btn {
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary {
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.2);
        }
        .btn-primary:hover {
            box-shadow: 0 6px 12px rgba(13, 110, 253, 0.3);
            transform: translateY(-2px);
        }
        .btn-outline-danger {
            border-width: 2px;
        }
        .btn-outline-danger:hover {
            box-shadow: 0 6px 12px rgba(220, 53, 69, 0.2);
            transform: translateY(-2px);
        }
        
        /* 徽章样式 */
        .badge {
            font-weight: 500;
            letter-spacing: 0.5px;
            padding: 0.5em 0.8em;
        }
        
        /* 自定义分类标签 */
        .category-badge {
            background-color: #e7f0ff;
            color: #0d6efd;
            font-weight: normal;
            font-size: 0.85rem;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            border: 1px solid #d0e0fb;
            transition: all 0.3s ease;
        }
        .category-badge:hover {
            background-color: #d0e0fb;
            border-color: #0d6efd;
            transform: translateY(-2px);
        }
        
        /* 响应式优化 */
        @media (max-width: 768px) {
            .card-body {
                padding: 1.25rem;
            }
            .h3 {
                font-size: 1.5rem;
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
                    <!-- 文章内容 -->
                    <div class="card shadow-sm mb-4 rounded-4">
                        <div class="card-body p-4">
                            <?php if (!empty($post['icon'])): ?>
                            <div class="d-flex align-items-start mb-4">
                                <div class="me-3 d-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-3" style="width: 80px; height: 80px; min-width: 80px;">
                                    <?php if (strpos($post['icon'], '/') !== false): ?>
                                        <img src="<?php echo htmlspecialchars($post['icon']); ?>" alt="图标" style="width: 50px; height: 50px; object-fit: contain;">
                                    <?php elseif (strpos($post['icon'], 'bi-') === 0): ?>
                                        <i class="<?php echo htmlspecialchars($post['icon']); ?> text-primary fs-1"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h1 class="h3 fw-bold mb-1">
                                        <?php if ($post['is_top']): ?>
                                        <span class="badge bg-danger me-2">置顶</span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </h1>
                                    <p class="text-muted mb-0">
                                        <?php 
                                        // 提取内容前30个字符作为简介
                                        $intro = strip_tags($post['content']);
                                        echo mb_substr($intro, 0, 30) . (mb_strlen($intro) > 30 ? '...' : '');
                                        ?>
                                    </p>
                                    <div class="mt-2">
                                        <?php 
                                        // 显示分类
                                        if ($post['category_id'] && isset($categories[$post['category_id']])) {
                                            echo '<a href="index.php?category='.$post['category_id'].'" class="category-badge text-decoration-none">';
                                            echo '<i class="bi bi-folder me-1"></i>';
                                            echo htmlspecialchars($post['category_name']);
                                            echo '</a>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <h1 class="h3 fw-bold mb-1">
                                <?php if ($post['is_top']): ?>
                                <span class="badge bg-danger me-2">置顶</span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($post['title']); ?>
                            </h1>
                            <p class="text-muted mb-3">
                                <?php 
                                // 提取内容前30个字符作为简介
                                $intro = strip_tags($post['content']);
                                echo mb_substr($intro, 0, 30) . (mb_strlen($intro) > 30 ? '...' : '');
                                ?>
                            </p>
                            <div class="mb-3">
                                <?php 
                                // 显示分类
                                if ($post['category_id'] && isset($categories[$post['category_id']])) {
                                    echo '<a href="index.php?category='.$post['category_id'].'" class="category-badge text-decoration-none">';
                                    echo '<i class="bi bi-folder me-1"></i>';
                                    echo htmlspecialchars($post['category_name']);
                                    echo '</a>';
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- 产品图片集 -->
                            <?php 
                            // 从post表的gallery字段获取图片，多张图片用逗号分隔
                            if (!empty($post['gallery'])):
                                $galleryImages = explode(',', $post['gallery']);
                                // 过滤空图片
                                $galleryImages = array_filter($galleryImages, function($img) {
                                    return !empty(trim($img));
                                });
                                
                                if (!empty($galleryImages)): 
                            ?>
                            <div class="mb-4">
                                <div class="row g-3">
                                    <?php foreach(array_slice($galleryImages, 0, 4) as $index => $image): ?>
                                    <div class="col-12">
                                        <div class="card border overflow-hidden">
                                            <img src="<?php echo trim($image); ?>" 
                                                 alt="产品图片<?php echo $index+1; ?>" 
                                                 class="img-fluid w-100" 
                                                 style="max-height: 500px; object-fit: contain;">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php 
                                endif;
                            else:
                                // 如果gallery为空，尝试从内容中提取图片
                                preg_match_all('/<img.+?src=[\"\'](.+?)[\"\'].*?>/i', $post['content'], $matches);
                                $images = isset($matches[1]) ? $matches[1] : [];
                                
                                if (!empty($images)): 
                            ?>
                            <div class="mb-4">
                                <div class="row g-3">
                                    <?php foreach(array_slice($images, 0, 4) as $index => $image): ?>
                                    <div class="col-12">
                                        <div class="card border overflow-hidden">
                                            <img src="<?php echo $image; ?>" 
                                                 alt="产品图片<?php echo $index+1; ?>" 
                                                 class="img-fluid w-100" 
                                                 style="max-height: 500px; object-fit: contain;">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <?php 
                                endif;
                            endif; 
                            ?>

                            <!-- 详情介绍 -->
                            <h5 class="fw-bold mb-3">详情介绍</h5>
                            <div class="article-content mb-4">
                                <?php echo $post['content']; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                                <button id="copyLinkBtn" class="btn btn-light border-0 ripple-effect shadow-sm" style="font-size: 0.85rem;">
                                    <i class="bi bi-link-45deg me-1 text-primary"></i>复制本页链接
                                </button>
                                <div class="text-muted d-flex align-items-center small">
                                    <i class="bi bi-clock me-1 text-primary"></i>发布于 <span id="relativeTime" class="ms-1 fw-medium">加载中...</span>
                                </div>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    // 复制链接功能
                                    const copyLinkBtn = document.getElementById('copyLinkBtn');
                                    copyLinkBtn.addEventListener('click', function() {
                                        const currentUrl = window.location.href;
                                        navigator.clipboard.writeText(currentUrl).then(function() {
                                            const originalText = copyLinkBtn.innerHTML;
                                            copyLinkBtn.innerHTML = '<i class="bi bi-check-circle-fill me-1 text-success"></i>已复制链接';
                                            copyLinkBtn.classList.remove('btn-light');
                                            copyLinkBtn.classList.add('btn-success', 'text-white');
                                            
                                            // 添加复制成功的涟漪效果
                                            copyLinkBtn.classList.add('copied');
                                            
                                            setTimeout(function() {
                                                copyLinkBtn.innerHTML = originalText;
                                                copyLinkBtn.classList.remove('btn-success', 'text-white', 'copied');
                                                copyLinkBtn.classList.add('btn-light');
                                            }, 2000);
                                        });
                                    });
                                    
                                    // 相对时间显示
                                    const relativeTimeSpan = document.getElementById('relativeTime');
                                    // 创建日期对象
                                    const publishTime = new Date('<?php echo $post['create_time']; ?>').getTime();
                                    const now = new Date().getTime();
                                    const diff = now - publishTime;
                                    
                                    // 计算相对时间
                                    const seconds = Math.floor(diff / 1000);
                                    const minutes = Math.floor(seconds / 60);
                                    const hours = Math.floor(minutes / 60);
                                    const days = Math.floor(hours / 24);
                                    const months = Math.floor(days / 30);
                                    const years = Math.floor(days / 365);
                                    
                                    let relativeTime = '';
                                    if (years > 0) {
                                        relativeTime = years + '年前';
                                    } else if (months > 0) {
                                        relativeTime = months + '个月前';
                                    } else if (days > 0) {
                                        relativeTime = days + '天前';
                                    } else if (hours > 0) {
                                        relativeTime = hours + '小时前';
                                    } else if (minutes > 0) {
                                        relativeTime = minutes + '分钟前';
                                    } else {
                                        relativeTime = '刚刚';
                                    }
                                    
                                    relativeTimeSpan.textContent = relativeTime;
                                });
                            </script>
                            <style>
                                /* 复制按钮效果 */
                                .ripple-effect {
                                    position: relative;
                                    overflow: hidden;
                                    transform: translate3d(0, 0, 0);
                                }
                                .ripple-effect:after {
                                    content: "";
                                    display: block;
                                    position: absolute;
                                    width: 100%;
                                    height: 100%;
                                    top: 0;
                                    left: 0;
                                    pointer-events: none;
                                    background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
                                    background-repeat: no-repeat;
                                    background-position: 50%;
                                    transform: scale(10, 10);
                                    opacity: 0;
                                    transition: transform .5s, opacity 1s;
                                }
                                .ripple-effect:active:after {
                                    transform: scale(0, 0);
                                    opacity: .3;
                                    transition: 0s;
                                }
                                .copied:after {
                                    background-image: radial-gradient(circle, #28a745 10%, transparent 10.01%);
                                }
                                
                                /* 热门文章效果 */
                                .hover-effect {
                                    transition: all 0.3s ease;
                                }
                                .hover-effect:hover {
                                    background-color: rgba(13, 110, 253, 0.05);
                                    border-radius: 8px;
                                    padding-left: 0.5rem;
                                    transform: translateX(5px);
                                }
                            </style>
                        </div>
                    </div>
                </div>
                
                <!-- 右侧侧边栏 -->
                <div class="col-lg-4">
                    <!-- 互动模块 -->
                    <div class="card shadow-sm mb-4 rounded-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-hand-thumbs-up me-2"></i>互动</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-center">
                                <a href="<?php 
                                    // 按优先级使用合适的URL
                                    if (!empty($post['url'])) {
                                        echo htmlspecialchars($post['url']);
                                    } elseif (!empty($post['link'])) {
                                        echo htmlspecialchars($post['link']);
                                    } elseif (!empty($post['source_url'])) {
                                        echo htmlspecialchars($post['source_url']);
                                    } else {
                                        echo '#';
                                    }
                                ?>" target="_blank" class="btn btn-primary btn-lg me-3" <?php if (empty($post['url']) && empty($post['link']) && empty($post['source_url'])) echo 'disabled'; ?>>
                                    <i class="bi bi-box-arrow-up-right me-2"></i>访问
                                </a>
                                <button id="likeButton" class="btn btn-outline-danger btn-lg position-relative">
                                    <i class="bi bi-heart-fill me-2"></i>喜欢 
                                    <span id="likeCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        0
                                    </span>
                                </button>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const likeButton = document.getElementById('likeButton');
                                    const likeCount = document.getElementById('likeCount');
                                    let likes = parseInt(localStorage.getItem('post_likes_<?php echo $post['id']; ?>') || 0);
                                    
                                    // 显示初始点赞数
                                    likeCount.textContent = likes;
                                    if (likes === 0) {
                                        likeCount.style.display = 'none';
                                    }
                                    
                                    likeButton.addEventListener('click', function() {
                                        likes++;
                                        likeCount.textContent = likes;
                                        likeCount.style.display = 'inline-block';
                                        localStorage.setItem('post_likes_<?php echo $post['id']; ?>', likes);
                                        
                                        // 点赞效果
                                        likeButton.classList.add('animate-like');
                                        
                                        // 添加飘心动画
                                        const heart = document.createElement('div');
                                        heart.className = 'floating-heart';
                                        heart.innerHTML = '<i class="bi bi-heart-fill"></i>';
                                        likeButton.appendChild(heart);
                                        
                                        setTimeout(() => {
                                            likeButton.classList.remove('animate-like');
                                            heart.remove();
                                        }, 1000);
                                    });
                                });
                            </script>
                            <style>
                                .animate-like {
                                    transform: scale(1.1);
                                    transition: transform 0.3s ease;
                                }
                                .floating-heart {
                                    position: absolute;
                                    color: #dc3545;
                                    animation: float-up 1s ease-out forwards;
                                    opacity: 0.8;
                                    font-size: 1.5rem;
                                    pointer-events: none;
                                }
                                @keyframes float-up {
                                    0% {
                                        transform: translateY(0) scale(1);
                                        opacity: 0.8;
                                    }
                                    100% {
                                        transform: translateY(-50px) scale(1.5);
                                        opacity: 0;
                                    }
                                }
                            </style>
                        </div>
                    </div>
                    
                    <!-- 相关文章 -->
                    <?php if (!empty($relatedPosts)): ?>
                    <div class="card shadow-sm mb-4 rounded-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-link-45deg me-2"></i>热门</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($relatedPosts as $relatedPost): ?>
                                <li class="list-group-item px-0 border-bottom py-3">
                                    <a href="article.php?id=<?php echo $relatedPost['id']; ?>" class="d-flex justify-content-between align-items-center text-decoration-none text-body hover-effect">
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-2">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </span>
                                            <div><?php echo htmlspecialchars($relatedPost['title']); ?></div>
                                        </div>
                                        <small class="text-muted ms-2 badge bg-light"><?php echo substr($relatedPost['create_time'], 5, 5); ?></small>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 