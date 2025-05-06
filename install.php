<?php
/**
 * 安装脚本
 */

// 定义根目录
define('__ROOT_DIR__', dirname(__FILE__));

// 配置文件路径
$config_file = __ROOT_DIR__ . '/config.inc.php';

// 数据库文件路径
$db_dir = __ROOT_DIR__ . '/data';
$db_file = $db_dir . '/data.db';

// 启用会话
session_start();

// 安装步骤
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$step = $step < 1 ? 1 : ($step > 3 ? 3 : $step);

// 错误信息
$error = '';

// 检查环境
function checkEnvironment() {
    $requirements = [
        'PHP版本 >= 7.0' => version_compare(PHP_VERSION, '7.0.0', '>='),
        'PDO扩展' => extension_loaded('pdo'),
        'PDO SQLite扩展' => extension_loaded('pdo_sqlite'),
        '/data 目录可写' => is_writable(__ROOT_DIR__ . '/data') || is_dir(__ROOT_DIR__ . '/data') || @mkdir(__ROOT_DIR__ . '/data', 0755, true),
        '根目录可写' => is_writable(__ROOT_DIR__) || (file_exists(__ROOT_DIR__ . '/config.inc.php') && is_writable(__ROOT_DIR__ . '/config.inc.php'))
    ];
    
    return $requirements;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['next_step']) && $step == 1) {
        // 环境检测通过，进入下一步
        $requirements = checkEnvironment();
        $all_pass = true;
        foreach ($requirements as $status) {
            if (!$status) {
                $all_pass = false;
                break;
            }
        }
        
        if ($all_pass) {
            header('Location: install.php?step=2');
            exit;
        } else {
            $error = '环境检测未通过，请解决问题后重试';
        }
    } elseif (isset($_POST['install']) && $step == 2) {
        // 安装系统 - 处理用户设置
        $site_name = isset($_POST['site_name']) ? trim($_POST['site_name']) : '我的网站';
        $site_description = isset($_POST['site_description']) ? trim($_POST['site_description']) : '一个简单的内容管理系统';
        $site_keywords = isset($_POST['site_keywords']) ? trim($_POST['site_keywords']) : 'CMS,PHP,SQLite';
        $admin_username = isset($_POST['admin_username']) ? trim($_POST['admin_username']) : '';
        $admin_password = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
        $admin_email = isset($_POST['admin_email']) ? trim($_POST['admin_email']) : '';
        
        // 保存到会话，用于最后显示
        $_SESSION['admin_username'] = $admin_username;
        $_SESSION['site_name'] = $site_name;
        
        // 验证表单
        if (empty($admin_username)) {
            $error = '管理员用户名不能为空';
        } elseif (empty($admin_password)) {
            $error = '管理员密码不能为空';
        } elseif (strlen($admin_password) < 6) {
            $error = '管理员密码长度不能少于6个字符';
        } elseif (empty($admin_email) || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $error = '请输入有效的电子邮件地址';
        } else {
            // 创建数据目录
            if (!is_dir($db_dir)) {
                if (!mkdir($db_dir, 0755, true)) {
                    $error = '创建数据目录失败，请检查权限';
                }
            }
            
            // 尝试创建数据库
            if (empty($error)) {
                try {
                    // 连接到SQLite数据库
                    $pdo = new PDO('sqlite:' . $db_file);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // 创建用户表
                    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        username TEXT NOT NULL UNIQUE,
                        password TEXT NOT NULL,
                        email TEXT NOT NULL,
                        role TEXT NOT NULL DEFAULT "user",
                        create_time TEXT NOT NULL,
                        update_time TEXT
                    )');
                    
                    // 创建分类表
                    $pdo->exec('CREATE TABLE IF NOT EXISTS categories (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        slug TEXT NOT NULL,
                        description TEXT,
                        parent_id INTEGER,
                        create_time TEXT NOT NULL,
                        update_time TEXT
                    )');
                    
                    // 创建文章表
                    $pdo->exec('CREATE TABLE IF NOT EXISTS posts (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT NOT NULL,
                        content TEXT NOT NULL,
                        summary TEXT,
                        link TEXT,
                        icon TEXT,
                        gallery TEXT,
                        category_id INTEGER,
                        user_id INTEGER NOT NULL,
                        status TEXT NOT NULL DEFAULT "draft" CHECK (status IN ("published", "draft")),
                        is_top INTEGER NOT NULL DEFAULT 0,
                        create_time TEXT NOT NULL,
                        update_time TEXT,
                        FOREIGN KEY (category_id) REFERENCES categories(id),
                        FOREIGN KEY (user_id) REFERENCES users(id)
                    )');
                    
                    // 创建管理员账户
                    $currentTime = time();
                    $currentTimeStr = date('Y-m-d H:i:s', $currentTime); // 格式化为日期字符串
                    $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare('INSERT INTO users (username, password, email, role, create_time) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$admin_username, $password_hash, $admin_email, 'admin', $currentTimeStr]);
                    
                    // 创建默认分类
                    $stmt = $pdo->prepare('INSERT INTO categories (name, slug, description, create_time) VALUES (?, ?, ?, ?)');
                    $stmt->execute(['默认分类', 'default', '默认分类描述', $currentTimeStr]);
                    
                    // 创建示例文章
                    $stmt = $pdo->prepare('INSERT INTO posts (title, content, summary, user_id, category_id, status, create_time) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        '欢迎使用内容管理系统', 
                        '<p>这是您的第一篇文章，您可以在后台删除或编辑它。</p><p>系统支持HTML格式的内容编辑，可以方便地添加和管理各类产品信息。</p>', 
                        '这是一个简单的示例内容，展示系统的基本功能',
                        1, // 管理员ID
                        1, // 默认分类ID
                        'published', // 已发布状态
                        $currentTimeStr
                    ]);
                    
                    // 创建配置文件
                    $db_config = [
                        'file' => $db_file,
                        'name' => $site_name,
                        'description' => $site_description,
                        'keywords' => $site_keywords,
                        'theme' => 'default',
                        'per_page' => 10
                    ];
                    
                    $config_content = "<?php\n";
                    $config_content .= "// 数据库配置\n";
                    $config_content .= "\$db = " . var_export($db_config, true) . ";\n";
                    $config_content .= "?>";
                    
                    if (file_put_contents($config_file, $config_content)) {
                        // 安装成功，进入完成页面
                        header('Location: install.php?step=3');
                        exit;
                    } else {
                        $error = '创建配置文件失败，请检查权限';
                    }
                    
                } catch (PDOException $e) {
                    $error = '数据库错误: ' . $e->getMessage();
                }
            }
        }
    }
}

// 检查是否已安装
if (file_exists($config_file) && file_exists($db_file) && $step != 3) {
    // 如果是强制安装则不跳转
    if (!isset($_GET['force']) || $_GET['force'] != 1) {
        header('Location: index.php');
        exit;
    }
}

// 获取步骤标题
function getStepTitle($step) {
    $titles = [
        1 => '环境检测',
        2 => '管理员设置',
        3 => '完成安装'
    ];
    return $titles[$step] ?? '';
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装向导 - <?php echo getStepTitle($step); ?></title>
    <link rel="stylesheet" href="admin/css/tailwind.min.css">
    <link rel="stylesheet" href="admin/css/bootstrap.min.css">
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        .step-indicator {
            position: relative;
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 0 10%;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e5e7eb;
            z-index: 1;
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            width: 33.33%;
        }
        .step-bubble {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
            z-index: 2;
        }
        .step-bubble.active {
            background: #3b82f6;
            color: white;
        }
        .step-bubble.completed {
            background: #10b981;
            color: white;
        }
        .step-label {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 0.5rem;
            text-align: center;
            width: 100%;
            font-size: 0.875rem;
            color: #6b7280;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-lg shadow-md">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    系统安装向导
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    欢迎使用内容管理系统，请按照提示完成安装
                </p>
            </div>
            
            <!-- 步骤指示器 -->
            <div class="step-indicator mt-8">
                <div class="step-item">
                    <div class="step-bubble <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                        <?php if ($step > 1): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        <?php else: ?>
                            1
                        <?php endif; ?>
                    </div>
                    <div class="step-label <?php echo $step == 1 ? 'text-blue-600 font-medium' : ''; ?>">环境检测</div>
                </div>
                <div class="step-item">
                    <div class="step-bubble <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                        <?php if ($step > 2): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        <?php else: ?>
                            2
                        <?php endif; ?>
                    </div>
                    <div class="step-label <?php echo $step == 2 ? 'text-blue-600 font-medium' : ''; ?>">管理员设置</div>
                </div>
                <div class="step-item">
                    <div class="step-bubble <?php echo $step >= 3 ? 'active' : ''; ?>">
                        3
                    </div>
                    <div class="step-label <?php echo $step == 3 ? 'text-blue-600 font-medium' : ''; ?>">完成安装</div>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mt-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">
                            <?php echo htmlspecialchars($error); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="fade-in">
                <?php if ($step == 1): ?>
                <!-- 步骤1：环境检测 -->
                <div class="mt-6">
                    <div class="py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">环境检测</h3>
                        <p class="mt-1 text-sm text-gray-600">请确保以下环境需求都满足要求</p>
                    </div>
                    
                    <div class="mt-6">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">需求</th>
                                    <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $all_pass = true;
                                foreach (checkEnvironment() as $requirement => $status): 
                                    $all_pass = $all_pass && $status;
                                ?>
                                <tr>
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($requirement); ?></td>
                                    <td class="px-3 py-3 whitespace-nowrap text-right text-sm">
                                        <?php if ($status): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            通过
                                        </span>
                                        <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            未通过
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <form method="post" action="install.php?step=1" class="mt-6">
                        <div class="flex justify-end">
                            <button type="submit" name="next_step" value="1" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" <?php echo $all_pass ? '' : 'disabled'; ?>>
                                下一步
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php elseif ($step == 2): ?>
                <!-- 步骤2：管理员设置 -->
                <form class="mt-6 space-y-6" method="post" action="install.php?step=2">
                    <div class="rounded-md shadow-sm -space-y-px">
                        <div class="py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800">网站基本信息</h3>
                        </div>
                        
                        <div class="mt-4 grid grid-cols-1 gap-4">
                            <div>
                                <label for="site_name" class="block text-sm font-medium text-gray-700 mb-1">网站名称</label>
                                <input type="text" id="site_name" name="site_name" value="我的网站" class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm">
                            </div>
                            
                            <div>
                                <label for="site_description" class="block text-sm font-medium text-gray-700 mb-1">网站描述</label>
                                <textarea id="site_description" name="site_description" rows="2" class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm">一个简单的内容管理系统</textarea>
                            </div>
                            
                            <div>
                                <label for="site_keywords" class="block text-sm font-medium text-gray-700 mb-1">网站关键词</label>
                                <input type="text" id="site_keywords" name="site_keywords" value="CMS,PHP,SQLite" class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="rounded-md shadow-sm -space-y-px">
                        <div class="py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800">管理员账户</h3>
                        </div>
                        
                        <div class="mt-4 grid grid-cols-1 gap-4">
                            <div>
                                <label for="admin_username" class="block text-sm font-medium text-gray-700 mb-1">用户名 <span class="text-red-500">*</span></label>
                                <input type="text" id="admin_username" name="admin_username" required class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm">
                            </div>
                            
                            <div>
                                <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-1">密码 <span class="text-red-500">*</span></label>
                                <input type="password" id="admin_password" name="admin_password" required class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm">
                                <p class="mt-1 text-xs text-gray-500">密码长度不能少于6个字符</p>
                            </div>
                            
                            <div>
                                <label for="admin_email" class="block text-sm font-medium text-gray-700 mb-1">电子邮件 <span class="text-red-500">*</span></label>
                                <input type="email" id="admin_email" name="admin_email" required class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700">
                                        安装将会创建数据库和配置文件，请确保您的服务器有足够的权限。
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <a href="install.php?step=1" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                            </svg>
                            上一步
                        </a>
                        <button type="submit" name="install" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            安装
                            <svg class="h-5 w-5 ml-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </form>
                
                <?php elseif ($step == 3): ?>
                <!-- 步骤3：安装完成 -->
                <div class="mt-6 text-center">
                    <div class="rounded-full mx-auto bg-green-100 p-3 w-16 h-16 flex items-center justify-center">
                        <svg class="h-10 w-10 text-green-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    
                    <h3 class="mt-4 text-xl font-medium text-gray-900">安装完成！</h3>
                    <p class="mt-2 text-gray-600">恭喜您，系统已成功安装。您现在可以开始使用系统了。</p>
                    
                    <div class="mt-6 bg-blue-50 rounded-md p-4 text-left">
                        <h4 class="text-md font-medium text-blue-800 mb-2">管理员信息</h4>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-sm text-gray-600">网站名称:</div>
                            <div class="col-span-2 text-sm font-medium"><?php echo isset($_SESSION['site_name']) ? htmlspecialchars($_SESSION['site_name']) : '我的网站'; ?></div>
                            
                            <div class="text-sm text-gray-600">管理员账户:</div>
                            <div class="col-span-2 text-sm font-medium"><?php echo isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'admin'; ?></div>
                        </div>
                        <div class="mt-2 text-xs text-blue-700">请妥善保管您的账户信息！</div>
                    </div>
                    
                    <div class="mt-4 text-sm text-gray-500">
                        为安全起见，建议删除安装文件 (install.php)
                    </div>
                    
                    <div class="mt-6 flex justify-center space-x-4">
                        <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                            </svg>
                            访问首页
                        </a>
                        <a href="admin/index.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd" />
                            </svg>
                            进入后台
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
