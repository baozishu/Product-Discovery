<?php
// 启用输出缓冲，解决header已发送问题
ob_start();

/**
 * 后台入口文件
 */

// 启用会话
session_start();

// 定义根目录
define('__ROOT_DIR__', dirname(dirname(__FILE__)));

// 检查配置文件是否存在
if (!file_exists(__ROOT_DIR__ . '/config.inc.php')) {
    header('Location: ../install.php');
    exit;
}

// 加载配置文件
require_once __ROOT_DIR__ . '/config.inc.php';

// 检查是否已登录
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// 处理登录请求
if (!$isLoggedIn && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (!empty($username) && !empty($password)) {
        try {
            // 连接到数据库
            $conn = new PDO("sqlite:" . $db['file']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 查询用户
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // 登录成功
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                
                // 重定向到管理面板
                header('Location: index.php');
                exit;
            } else {
                $loginError = '用户名或密码错误';
            }
        } catch (PDOException $e) {
            $loginError = '数据库错误: ' . $e->getMessage();
        }
    } else {
        $loginError = '请输入用户名和密码';
    }
}

// 处理登出请求
if ($isLoggedIn && isset($_GET['logout'])) {
    // 清除会话
    session_unset();
    session_destroy();
    
    // 重定向到登录页面
    header('Location: index.php');
    exit;
}

// 获取当前页面
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// 网站信息
include_once(__ROOT_DIR__ . '/config.inc.php');
$siteName = isset($db['name']) ? $db['name'] : '后台管理系统';
$siteSubtitle = isset($db['subtitle']) ? $db['subtitle'] : '';
$siteFavicon = isset($db['favicon']) ? $db['favicon'] : '';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?><?php echo !empty($siteSubtitle) ? ' - ' . htmlspecialchars($siteSubtitle) : ''; ?> - 后台管理系统</title>
    <?php if (!empty($siteFavicon)): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($siteFavicon); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($siteFavicon); ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* 全局CSS样式，使用纯Bootstrap */
        .sidebar {
            height: 100vh;
            overflow-y: auto;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            background: #f8f9fa;
            border-right: 1px solid rgba(0, 0, 0, 0.05);
            z-index: 1020;
        }
        .content {
            min-height: 100vh;
        }
        .nav-link.active {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            font-weight: 500;
            border-left: 4px solid #0d6efd;
            padding-left: calc(0.75rem - 1px) !important;
        }
        .nav-link:hover:not(.active) {
            background-color: rgba(243, 244, 246, 0.8);
            color: #0d6efd;
            transform: translateX(3px);
        }
        .nav-link {
            color: #495057;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
            padding-left: calc(0.75rem - 1px) !important;
            margin-bottom: 0.5rem;
            border-radius: 0.25rem;
        }
        .card-stats {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-stats:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .sidebar-brand {
            padding: 1.25rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, #0d6efd, #0a58ca);
            color: white;
            height: 60px; /* 固定高度 */
            display: flex;
            align-items: center;
        }
        .sidebar-logo {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.2rem;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.16);
        }
        .sidebar-menu-header {
            text-transform: uppercase;
            font-size: 0.7rem;
            font-weight: 600;
            color: #6c757d;
            margin: 1.5rem 0 0.5rem 1rem;
            letter-spacing: 0.05rem;
        }
        .sidebar-divider {
            margin: 1rem 0;
            height: 0;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        .nav-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 1.75rem;
            height: 1.75rem;
            margin-right: 0.75rem;
            opacity: 0.8;
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .nav-link:hover .nav-icon {
            background-color: rgba(13, 110, 253, 0.2);
            color: #0a58ca;
        }
        .nav-link.active .nav-icon {
            background-color: #0d6efd;
            color: white;
            opacity: 1;
        }
        .sidebar-footer {
            padding: 1rem;
            font-size: 0.8rem;
            color: #6c757d;
            text-align: center;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            margin-top: auto;
            background-color: rgba(0, 0, 0, 0.02);
        }
        /* 顶部导航栏样式，确保与侧边栏管理后台高度一致 */
        .top-header {
            height: 60px; /* 与侧边栏品牌区域相同高度 */
            display: flex;
            align-items: center;
            padding-left: 1.25rem;
            padding-right: 1.25rem;
            box-sizing: border-box;
            width: 100%;
            margin: 0;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                z-index: 1030;
                width: 280px;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .sidebar-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1020;
                display: none;
            }
            .sidebar-backdrop.show {
                display: block;
            }
        }

        /* 添加一些Bootstrap兼容类 */
        .btn-icon {
            padding: 0.375rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .avatar {
            width: 2rem;
            height: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .bg-primary-subtle {
            background-color: rgba(13, 110, 253, 0.1);
        }
        .bg-success-subtle {
            background-color: rgba(25, 135, 84, 0.1);
        }
        .bg-warning-subtle {
            background-color: rgba(255, 193, 7, 0.1);
        }
        .text-primary {
            color: #0d6efd !important;
        }
        .text-success {
            color: #198754 !important;
        }
        .text-warning {
            color: #ffc107 !important;
        }
    </style>
</head>
<body class="bg-light">
    <?php if (!$isLoggedIn): ?>
    <!-- 登录页面 -->
    <div class="min-vh-100 d-flex align-items-center justify-content-center bg-light py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <h2 class="h3 fw-bold text-dark mb-3">管理员登录</h2>
                                <p class="text-muted">请输入您的管理员账号和密码</p>
                            </div>
                            
                            <?php if (isset($loginError)): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                                </svg>
                                <div><?php echo htmlspecialchars($loginError); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <form class="mt-4" action="" method="POST">
                                <input type="hidden" name="action" value="login">
                                <div class="mb-3">
                                    <label for="username" class="form-label">用户名</label>
                                    <input id="username" name="username" type="text" required class="form-control form-control-lg" placeholder="输入管理员用户名">
                                </div>
                                <div class="mb-4">
                                    <label for="password" class="form-label">密码</label>
                                    <input id="password" name="password" type="password" required class="form-control form-control-lg" placeholder="输入管理员密码">
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>登录
                                    </button>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <a href="../index.php" class="text-decoration-none">
                                        <i class="bi bi-arrow-left"></i> 返回首页
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- 移动端遮罩层 -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    
    <div class="container-fluid px-0">
        <div class="row g-0">
            <!-- 侧边栏 -->
            <div class="col-lg-2 bg-white sidebar d-flex flex-column" id="sidebar">
                <!-- 添加移动端切换按钮 -->
                <div class="d-flex align-items-center justify-content-between p-3 d-lg-none border-bottom">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-sm btn-link p-0 text-primary border-0 me-2" type="button" id="sidebarClose">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                                <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"/>
                            </svg>
                        </button>
                        <span class="fw-semibold">菜单</span>
                    </div>
                    <div>
                        <a href="?page=dashboard" class="text-decoration-none text-primary fw-semibold">
                            <?php echo ucfirst($page); ?>
                        </a>
                    </div>
                </div>

                <div class="sidebar-brand d-flex align-items-center">
                    <div class="sidebar-logo me-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-display" viewBox="0 0 16 16">
                            <path d="M0 4s0-2 2-2h12s2 0 2 2v6s0 2-2 2h-4c0 .667.083 1.167.25 1.5H11a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1h.75c.167-.333.25-.833.25-1.5H2s-2 0-2-2V4zm1.398-.855a.758.758 0 0 0-.254.302A1.46 1.46 0 0 0 1 4.01V10c0 .325.078.502.145.602.07.105.17.188.302.254a1.464 1.464 0 0 0 .538.143L2.01 11H14c.325 0 .502-.078.602-.145a.758.758 0 0 0 .254-.302 1.464 1.464 0 0 0 .143-.538L15 9.99V4c0-.325-.078-.502-.145-.602a.757.757 0 0 0-.302-.254A1.46 1.46 0 0 0 13.99 3H2c-.325 0-.502.078-.602.145z"/>
                        </svg>
                    </div>
                    <div class="ms-3">
                        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($siteName); ?></h5>
                        <?php if (!empty($siteSubtitle)): ?>
                        <div class="text-white-50 small"><?php echo htmlspecialchars($siteSubtitle); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sidebar-menu-header">
                    主要功能
                </div>
                <div class="px-3">
                    <nav class="nav flex-column">
                        <a href="?page=dashboard" class="nav-link py-2 <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                            <div class="d-flex align-items-center">
                                <div class="nav-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-speedometer2" viewBox="0 0 16 16">
                                        <path d="M8 4a.5.5 0 0 1 .5.5V6a.5.5 0 0 1-1 0V4.5A.5.5 0 0 1 8 4zM3.732 5.732a.5.5 0 0 1 .707 0l.915.914a.5.5 0 1 1-.708.708l-.914-.915a.5.5 0 0 1 0-.707zM2 10a.5.5 0 0 1 .5-.5h1.586a.5.5 0 0 1 0 1H2.5A.5.5 0 0 1 2 10zm9.5 0a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 0 1H12a.5.5 0 0 1-.5-.5zm.754-4.246a.389.389 0 0 0-.527-.02L7.547 9.31a.91.91 0 1 0 1.302 1.258l3.434-4.297a.389.389 0 0 0-.029-.518z"/>
                                        <path fill-rule="evenodd" d="M0 10a8 8 0 1 1 15.547 2.661c-.442 1.253-1.845 1.602-2.932 1.25C11.309 13.488 9.475 13 8 13c-1.474 0-3.31.488-4.615.911-1.087.352-2.49.003-2.932-1.25A7.988 7.988 0 0 1 0 10zm8-7a7 7 0 0 0-6.603 9.329c.203.575.923.876 1.68.63C4.397 12.533 6.358 12 8 12s3.604.532 4.923.96c.757.245 1.477-.056 1.68-.631A7 7 0 0 0 8 3z"/>
                                    </svg>
                                </div>
                                仪表盘
                            </div>
                        </a>
                    </nav>
                </div>

                <div class="sidebar-menu-header">内容管理</div>
                <div class="px-3">
                    <nav class="nav flex-column">
                        <a href="?page=category" class="nav-link py-2 <?php echo $page === 'category' ? 'active' : ''; ?>">
                            <div class="d-flex align-items-center">
                                <div class="nav-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-folder" viewBox="0 0 16 16">
                                        <path d="M.54 3.87.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.826a2 2 0 0 1-1.991-1.819l-.637-7a1.99 1.99 0 0 1 .342-1.31zM2.19 4a1 1 0 0 0-.996 1.09l.637 7a1 1 0 0 0 .995.91h10.348a1 1 0 0 0 .995-.91l.637-7A1 1 0 0 0 13.81 4H2.19zm4.69-1.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981l.006.139C1.72 3.042 1.95 3 2.19 3h5.396l-.707-.707z"/>
                                    </svg>
                                </div>
                                分类管理
                            </div>
                        </a>
                        <a href="?page=content" class="nav-link py-2 <?php echo $page === 'content' ? 'active' : ''; ?>">
                            <div class="d-flex align-items-center">
                                <div class="nav-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-file-text" viewBox="0 0 16 16">
                                        <path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1H5zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zM5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1H5zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1H5z"/>
                                        <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z"/>
                                    </svg>
                                </div>
                                内容管理
                            </div>
                        </a>
                    </nav>
                </div>

                <div class="sidebar-menu-header">系统管理</div>
                <div class="px-3">
                    <nav class="nav flex-column">
                        <a href="?page=user" class="nav-link py-2 <?php echo $page === 'user' ? 'active' : ''; ?>">
                            <div class="d-flex align-items-center">
                                <div class="nav-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
                                        <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
                                    </svg>
                                </div>
                                用户管理
                            </div>
                        </a>
                        <a href="?page=backup" class="nav-link py-2 <?php echo $page === 'backup' ? 'active' : ''; ?>">
                            <div class="d-flex align-items-center">
                                <div class="nav-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-cloud-arrow-up" viewBox="0 0 16 16">
                                        <path fill-rule="evenodd" d="M7.646 5.146a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1-.708.708L8.5 6.707V10.5a.5.5 0 0 1-1 0V6.707L6.354 7.854a.5.5 0 1 1-.708-.708l2-2z"/>
                                        <path d="M4.406 3.342A5.53 5.53 0 0 1 8 2c2.69 0 4.923 2 5.166 4.579C14.758 6.804 16 8.137 16 9.773 16 11.569 14.502 13 12.687 13H3.781C1.708 13 0 11.366 0 9.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383zm.653.757c-.757.653-1.153 1.44-1.153 2.056v.448l-.445.049C2.064 6.805 1 7.952 1 9.318 1 10.785 2.23 12 3.781 12h8.906C13.98 12 15 10.988 15 9.773c0-1.216-1.02-2.228-2.313-2.228h-.5v-.5C12.188 4.825 10.328 3 8 3a4.53 4.53 0 0 0-2.941 1.1z"/>
                                    </svg>
                                </div>
                                备份管理
                            </div>
                        </a>
                        <a href="?page=setting" class="nav-link py-2 <?php echo $page === 'setting' ? 'active' : ''; ?>">
                            <div class="d-flex align-items-center">
                                <div class="nav-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-gear" viewBox="0 0 16 16">
                                        <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
                                        <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 0-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 0-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 0 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 0 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 0 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 0 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 0-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
                                    </svg>
                                </div>
                                系统设置
                            </div>
                        </a>
                    </nav>
                </div>

                <div class="sidebar-footer">
                    <div>© <?php echo date('Y'); ?> 后台管理系统</div>
                    <div>Version 1.0.0</div>
                </div>
            </div>
            
            <!-- 内容区域 -->
            <div class="col-12 col-lg-10 content bg-light">
                <!-- 添加简洁的顶部导航栏，修改高度与侧边栏一致 -->
                <div class="top-header d-flex align-items-center justify-content-between">
                    <!-- 左侧：面包屑导航 -->
                    <div class="d-flex align-items-center">
                        <!-- 移动端菜单按钮 -->
                        <button class="btn btn-sm d-lg-none me-2 text-primary border-0" type="button" id="sidebarToggle">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
                            </svg>
                        </button>
                        
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0 py-2">
                                <li class="breadcrumb-item"><a href="?page=dashboard" class="text-decoration-none">首页</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo ucfirst($page); ?></li>
                            </ol>
                        </nav>
                    </div>
                    
                    <!-- 右侧：功能图标 -->
                    <div class="d-flex align-items-center">
                        <!-- 返回首页 -->
                        <a href="../index.php" class="btn btn-sm btn-outline-primary me-2" title="返回首页">
                            <i class="bi bi-house-door"></i>
                        </a>
                        
                        <!-- 退出登录 -->
                        <a href="?logout=1" class="btn btn-sm btn-outline-danger" title="退出登录">
                            <i class="bi bi-box-arrow-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="p-4">
                    <div class="bg-white p-4 rounded shadow-sm">
                        <?php include_once getPageContent($page); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加Bootstrap JS以支持下拉菜单等功能 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 更新日期和时间
        function updateDateTime() {
            const now = new Date();
            const dateOptions = { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' };
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
            
            const dateElement = document.getElementById('currentDate');
            const timeElement = document.getElementById('currentTime');
            
            if (dateElement && timeElement) {
                dateElement.textContent = now.toLocaleDateString('zh-CN', dateOptions);
                timeElement.textContent = now.toLocaleTimeString('zh-CN', timeOptions);
                setTimeout(updateDateTime, 1000);
            }
        }
        
        // 页面加载后初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 移动端侧边栏切换
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarClose = document.getElementById('sidebarClose');
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.add('show');
                    sidebarBackdrop.classList.add('show');
                    document.body.style.overflow = 'hidden'; // 防止背景滚动
                });
            }
            
            if (sidebarClose) {
                sidebarClose.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    sidebarBackdrop.classList.remove('show');
                    document.body.style.overflow = ''; // 恢复滚动
                });
            }
            
            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    sidebarBackdrop.classList.remove('show');
                    document.body.style.overflow = ''; // 恢复滚动
                });
            }
            
            // 尝试初始化日期时间
            updateDateTime();
        });
    </script>
    <?php endif; ?>

    <!-- 保留移动端触发按钮 -->
    <button class="btn btn-sm btn-primary d-lg-none" id="sidebarToggle" style="position: fixed; bottom: 20px; right: 20px; width: 40px; height: 40px; border-radius: 50%; z-index: 1030; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,.2);">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
        </svg>
    </button>
</body>
</html>

<?php
/**
 * 获取页面内容
 */
function getPageContent($page) {
    $validPages = [
        'dashboard', 'content', 'category', 'user', 'backup', 'setting'
    ];
    
    if (!in_array($page, $validPages)) {
        $page = 'dashboard';
    }
    
    $file = __DIR__ . '/pages/' . $page . '.php';
    
    if (file_exists($file)) {
        return $file;
    }
    
    // 如果页面文件不存在，则显示默认的仪表盘
    return __DIR__ . '/pages/dashboard.php';
}
?> 