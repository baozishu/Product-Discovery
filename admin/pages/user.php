<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - 管理面板</title>
    <style>
        /* 头像效果样式 */
        .avatar-initial {
            transition: all 0.3s ease;
        }
        
        .avatar-initial:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 1;
        }
        
        .avatar-glow {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(13, 202, 240, 0.7);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(13, 202, 240, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(13, 202, 240, 0);
            }
        }
        
        .avatar-animated {
            transition: all 0.3s ease;
        }
        .avatar-animated:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2) !important;
        }
        .avatar-text {
            transition: all 0.3s ease;
        }
        .avatar-animated:hover .avatar-text {
            transform: translate(-50%, -50%) scale(1.2);
        }
        .avatar-glow {
            transition: all 0.3s ease;
            box-shadow: 0 0 0 rgba(13, 202, 240, 0);
        }
        .avatar-animated:hover .avatar-glow {
            box-shadow: 0 0 10px rgba(13, 202, 240, 0.7);
        }
    </style>
</head>

<body>
<?php
/**
 * 用户管理页面
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
                // 检查用户名是否已存在
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $checkStmt->execute([$_POST['username']]);
                if ($checkStmt->fetchColumn() > 0) {
                    $message = '用户名已存在';
                    $messageType = 'error';
                } else {
                    // 添加用户
                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, create_time) VALUES (?, ?, ?, ?, ?)");
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt->execute([
                        $_POST['username'],
                        $password,
                        $_POST['email'],
                        $_POST['role'],
                        time()
                    ]);
                    $message = '用户添加成功！';
                    $messageType = 'success';
                    // 重定向到列表页
                    header('Location: ?page=user&message=' . urlencode($message) . '&type=' . $messageType);
                    exit;
                }
            } elseif ($_POST['action'] === 'edit' && $id > 0) {
                // 检查是否修改了用户名，并且新用户名已存在
                if (isset($_POST['username'])) {
                    $checkStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                    $checkStmt->execute([$id]);
                    $currentUsername = $checkStmt->fetchColumn();
                    
                    if ($_POST['username'] !== $currentUsername) {
                        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                        $checkStmt->execute([$_POST['username'], $id]);
                        if ($checkStmt->fetchColumn() > 0) {
                            $message = '用户名已存在';
                            $messageType = 'error';
                            goto renderPage;
                        }
                    }
                }
                
                // 更新用户信息
                if (!empty($_POST['password'])) {
                    // 更新包括密码
                    $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, email = ?, role = ?, update_time = ? WHERE id = ?");
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt->execute([
                        $_POST['username'],
                        $password,
                        $_POST['email'],
                        $_POST['role'],
                        time(),
                        $id
                    ]);
                } else {
                    // 不更新密码
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, update_time = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['username'],
                        $_POST['email'],
                        $_POST['role'],
                        time(),
                        $id
                    ]);
                }
                
                $message = '用户更新成功！';
                $messageType = 'success';
                
                // 重定向到列表页
                header('Location: ?page=user&message=' . urlencode($message) . '&type=' . $messageType);
                exit;
            } elseif ($_POST['action'] === 'delete' && $id > 0) {
                // 禁止删除当前登录用户
                if ($id == $_SESSION['admin_id']) {
                    $message = '不能删除当前登录用户';
                    $messageType = 'error';
                } else {
                    // 检查该用户是否有内容
                    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
                    $checkStmt->execute([$id]);
                    $count = $checkStmt->fetchColumn();
                    
                    if ($count > 0) {
                        $message = '无法删除，该用户有' . $count . '条内容';
                        $messageType = 'error';
                    } else {
                        // 删除用户
                        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$id]);
                        $message = '用户删除成功！';
                        $messageType = 'success';
                    }
                }
                
                // 重定向到列表页
                header('Location: ?page=user&message=' . urlencode($message) . '&type=' . $messageType);
                exit;
            }
        } catch (PDOException $e) {
            $message = '操作失败: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// 从URL获取消息
renderPage:
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $messageType = isset($_GET['type']) ? $_GET['type'] : 'info';
}

// 获取用户列表或单个用户
try {
    $conn = new PDO("sqlite:" . $db['file']);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($action === 'edit' && $id > 0) {
        // 获取单个用户
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $message = '用户不存在！';
            $messageType = 'error';
            $action = 'list'; // 回到列表页
        }
    } elseif ($action === 'list') {
        // 获取用户列表
        $users = [];
        $stmt = $conn->query("SELECT * FROM users ORDER BY create_time DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取每个用户的内容数量
        $contentCountsStmt = $conn->query("SELECT user_id, COUNT(*) as count FROM posts GROUP BY user_id");
        $contentCounts = [];
        while ($row = $contentCountsStmt->fetch(PDO::FETCH_ASSOC)) {
            $contentCounts[$row['user_id']] = $row['count'];
        }
    }
} catch (PDOException $e) {
    $message = '数据库错误: ' . $e->getMessage();
    $messageType = 'error';
}
?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 fw-bold mb-0">用户管理</h1>
        <?php if ($action === 'list'): ?>
        <a href="?page=user&action=add" class="btn btn-primary">
            <i class="bi bi-person-plus-fill me-2"></i>添加用户
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
    <!-- 用户列表 -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 d-flex align-items-center">
                <i class="bi bi-people-fill text-primary me-2"></i>
                用户列表
            </h5>
            <span class="badge bg-primary rounded-pill"><?php echo count($users); ?> 个用户</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 py-3 text-uppercase small">用户信息</th>
                            <th class="py-3 text-uppercase small">电子邮件</th>
                            <th class="py-3 text-uppercase small">角色</th>
                            <th class="text-center py-3 text-uppercase small">内容数量</th>
                            <th class="py-3 text-uppercase small">注册时间</th>
                            <th class="text-end pe-3 py-3 text-uppercase small">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="py-5">
                                    <i class="bi bi-people text-muted fs-1 mb-3 d-block"></i>
                                    <p class="mb-1 fw-medium">暂无用户</p>
                                    <p class="text-muted small">点击"添加用户"按钮创建第一个用户</p>
                                    <a href="?page=user&action=add" class="btn btn-primary mt-3">
                                        <i class="bi bi-person-plus-fill me-1"></i>添加用户
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($users as $item): ?>
                        <tr class="align-middle">
                            <td class="ps-3">
                                <div class="d-flex align-items-center py-2">
                                    <div class="avatar-initial rounded-circle bg-<?php echo $item['role'] === 'admin' ? 'danger' : 'secondary'; ?> bg-gradient text-white text-center flex-shrink-0 me-3 shadow position-relative avatar-animated" style="width: 48px; height: 48px; overflow: hidden;">
                                        <span class="avatar-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 20px; font-weight: 600;"><?php 
                                        // 获取用户名首字，支持中文
                                        $firstChar = mb_substr($item['username'], 0, 1, 'UTF-8');
                                        // 如果是英文字符则转大写
                                        echo preg_match('/[a-zA-Z]/', $firstChar) ? strtoupper($firstChar) : $firstChar; 
                                        ?></span>
                                        <div class="avatar-glow" style="position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; border-radius: 50%; background-color: <?php echo $item['id'] == $_SESSION['admin_id'] ? '#0dcaf0' : 'transparent'; ?>; border: 2px solid #fff;"></div>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-medium"><?php echo htmlspecialchars($item['username']); ?></h6>
                                        <?php if ($item['id'] == $_SESSION['admin_id']): ?>
                                        <span class="badge bg-info rounded-pill mt-1"><i class="bi bi-check-circle-fill me-1"></i>当前用户</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="mailto:<?php echo htmlspecialchars($item['email']); ?>" class="text-decoration-none d-flex align-items-center">
                                    <i class="bi bi-envelope-fill text-muted me-1"></i>
                                    <?php echo htmlspecialchars($item['email']); ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge <?php echo $item['role'] === 'admin' ? 'bg-danger' : 'bg-secondary'; ?> rounded">
                                    <i class="bi bi-<?php echo $item['role'] === 'admin' ? 'shield-fill-check' : 'person-fill'; ?> me-1"></i>
                                    <?php echo $item['role'] === 'admin' ? '管理员' : '用户'; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php 
                                $count = isset($contentCounts[$item['id']]) ? $contentCounts[$item['id']] : 0;
                                echo '<span class="badge ' . ($count > 0 ? 'bg-success' : 'bg-light text-dark') . ' rounded-pill">' . $count . '</span>';
                                ?>
                            </td>
                            <td>
                                <div class="text-muted d-flex align-items-center">
                                    <i class="bi bi-calendar-date me-1"></i>
                                    <?php echo $item['create_time']; ?>
                                </div>
                            </td>
                            <td class="text-end pe-3">
                                <a href="?page=user&action=edit&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="编辑用户">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <?php if ($item['id'] != $_SESSION['admin_id']): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="<?php echo $item['id']; ?>" data-name="<?php echo htmlspecialchars($item['username']); ?>" title="删除用户">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
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
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="bi bi-exclamation-diamond-fill me-2"></i>确认删除
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex">
                        <div class="bg-danger bg-opacity-10 p-3 rounded-circle text-danger me-3">
                            <i class="bi bi-trash-fill fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">删除用户</h5>
                            <p class="mb-0">确定要删除用户 "<span id="deleteName" class="fw-bold"></span>" 吗？</p>
                            <p class="text-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i>此操作不可撤销</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button id="cancelDelete" type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>取消
                    </button>
                    <form id="deleteForm" method="post" action="">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>确认删除
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
                            deleteForm.action = `?page=user&action=delete&id=${id}`;
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
    <!-- 添加/编辑用户表单 -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex align-items-center">
            <i class="bi bi-person-<?php echo $action === 'add' ? 'plus' : 'gear'; ?> text-primary fs-4 me-2"></i>
            <h5 class="card-title mb-0 fw-bold">
                <?php echo $action === 'add' ? '添加新用户' : '编辑用户'; ?>
            </h5>
        </div>
        <div class="card-body p-4">
            <form method="post" action="?page=user&action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $id : ''; ?>" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="<?php echo $action; ?>">
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="username" class="form-label fw-medium">
                                <i class="bi bi-person-fill text-primary me-1"></i> 用户名
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" id="username" class="form-control" value="<?php echo isset($user) ? htmlspecialchars($user['username']) : ''; ?>" required>
                                <div class="invalid-feedback">请输入用户名</div>
                            </div>
                            <small class="text-muted mt-1">用户登录时使用的名称，创建后不建议修改</small>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label fw-medium">
                                <i class="bi bi-key-fill text-primary me-1"></i> 密码 
                                <?php echo $action === 'edit' ? '<span class="badge bg-light text-dark ms-1">留空表示不修改</span>' : ''; ?>
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                                <input type="password" name="password" id="password" class="form-control" <?php echo $action === 'add' ? 'required' : ''; ?>>
                                <div class="invalid-feedback">请输入密码</div>
                            </div>
                            <small class="text-muted mt-1">建议使用包含字母、数字和特殊字符的强密码</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="email" class="form-label fw-medium">
                                <i class="bi bi-envelope-fill text-primary me-1"></i> 电子邮件
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" id="email" class="form-control" value="<?php echo isset($user) ? htmlspecialchars($user['email']) : ''; ?>" required>
                                <div class="invalid-feedback">请输入有效的电子邮件地址</div>
                            </div>
                            <small class="text-muted mt-1">用于接收系统通知和找回密码</small>
                        </div>
                        
                        <div class="mb-4">
                            <label for="role" class="form-label fw-medium">
                                <i class="bi bi-shield-fill text-primary me-1"></i> 用户角色
                            </label>
                            <div class="p-3 rounded bg-light border">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" id="roleAdmin" value="admin" checked>
                                    <label class="form-check-label fw-medium" for="roleAdmin">
                                        <span class="badge bg-danger me-1"><i class="bi bi-person-fill-gear"></i></span>
                                        管理员
                                    </label>
                                    <div class="text-muted ms-4 small">拥有系统的所有管理权限</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4 pt-2 border-top">
                    <a href="?page=user" class="btn btn-outline-secondary btn-lg px-4">
                        <i class="bi bi-arrow-left me-1"></i> 返回列表
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-save me-1"></i> <?php echo $action === 'add' ? '添加用户' : '保存修改'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
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
        })()
    </script>
    <?php endif; ?>
</div> 
</body>
</html> 