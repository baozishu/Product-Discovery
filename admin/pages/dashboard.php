<?php
/**
 * 仪表盘页面
 */

// 获取统计数据
try {
    $conn = new PDO("sqlite:" . $db['file']);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取内容数量
    $contentStmt = $conn->query("SELECT COUNT(*) FROM posts");
    $contentCount = $contentStmt->fetchColumn();
    
    // 获取分类数量
    $categoryStmt = $conn->query("SELECT COUNT(*) FROM categories");
    $categoryCount = $categoryStmt->fetchColumn();
    
    // 获取用户数量
    $userStmt = $conn->query("SELECT COUNT(*) FROM users");
    $userCount = $userStmt->fetchColumn();
    
    // 获取最近的内容
    $recentStmt = $conn->query("SELECT p.*, c.name as category_name, u.username 
                              FROM posts p 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              LEFT JOIN users u ON p.user_id = u.id 
                              ORDER BY p.is_top DESC, p.create_time DESC LIMIT 5");
    $recentContents = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = '数据库错误: ' . $e->getMessage();
}
?>

<div class="mb-4">
    <!-- 页面标题 -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <h1 class="h3 fw-bold text-dark me-2">仪表盘</h1>
            <span class="small text-muted">系统概览</span>
        </div>
        <div class="badge bg-primary rounded-pill px-3 py-2">
            今日：<?php echo date('Y-m-d'); ?>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
    <!-- 错误提示 -->
    <div class="alert alert-danger d-flex align-items-start border-start border-danger border-4">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-exclamation-circle me-2 mt-1" viewBox="0 0 16 16">
            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
            <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
        </svg>
        <div>
            <h5 class="alert-heading fs-6 fw-bold">出现错误</h5>
            <div class="small"><?php echo htmlspecialchars($error); ?></div>
        </div>
    </div>
    <?php else: ?>
    
    <!-- 统计卡片 -->
    <div class="row g-4 mb-4">
        <!-- 内容数量卡片 -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm transition-hover">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-uppercase small fw-bold text-muted mb-1">内容数量</p>
                            <h2 class="fs-1 fw-bold mb-2"><?php echo $contentCount; ?></h2>
                            <a href="?page=content" class="text-primary fw-medium small d-inline-flex align-items-center">
                                查看全部
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right ms-1" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                                </svg>
                            </a>
                        </div>
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-file-text" viewBox="0 0 16 16">
                                <path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1H5zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zM5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1H5zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1H5z"/>
                                <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 分类数量卡片 -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm transition-hover">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-uppercase small fw-bold text-muted mb-1">分类数量</p>
                            <h2 class="fs-1 fw-bold mb-2"><?php echo $categoryCount; ?></h2>
                            <a href="?page=category" class="text-success fw-medium small d-inline-flex align-items-center">
                                查看全部
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right ms-1" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                                </svg>
                            </a>
                        </div>
                        <div class="bg-success bg-opacity-10 text-success p-3 rounded">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-folder" viewBox="0 0 16 16">
                                <path d="M.54 3.87.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.826a2 2 0 0 1-1.991-1.819l-.637-7a1.99 1.99 0 0 1 .342-1.31zM2.19 4a1 1 0 0 0-.996 1.09l.637 7a1 1 0 0 0 .995.91h10.348a1 1 0 0 0 .995-.91l.637-7A1 1 0 0 0 13.81 4H2.19zm4.69-1.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981l.006.139C1.72 3.042 1.95 3 2.19 3h5.396l-.707-.707z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 用户数量卡片 -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm transition-hover">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-uppercase small fw-bold text-muted mb-1">用户数量</p>
                            <h2 class="fs-1 fw-bold mb-2"><?php echo $userCount; ?></h2>
                            <a href="?page=user" class="text-info fw-medium small d-inline-flex align-items-center">
                                查看全部
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right ms-1" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                                </svg>
                            </a>
                        </div>
                        <div class="bg-info bg-opacity-10 text-info p-3 rounded">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
                                <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 最近内容 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <div>
                <h5 class="card-title mb-0">最近发布的内容</h5>
                <p class="text-muted small mb-0 mt-1">查看最新添加的5篇文章</p>
            </div>
            <a href="?page=content&action=add" class="btn btn-primary btn-sm d-flex align-items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus me-1" viewBox="0 0 16 16">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                </svg>
                添加内容
            </a>
        </div>
        
        <?php if (empty($recentContents)): ?>
        <!-- 无内容提示 -->
        <div class="card-body text-center py-5">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-file-earmark-text text-muted mb-3" viewBox="0 0 16 16">
                <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5z"/>
                <path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5L9.5 0zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/>
            </svg>
            <h4 class="text-dark mb-2">暂无内容</h4>
            <p class="text-muted mb-4">还没有发布任何文章，点击下方按钮添加第一篇文章。</p>
            <a href="?page=content&action=add" class="btn btn-primary d-inline-flex align-items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus me-2" viewBox="0 0 16 16">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                </svg>
                立即添加内容
            </a>
        </div>
        <?php else: ?>
        <!-- 内容列表 -->
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="fw-medium">标题</th>
                        <th scope="col" class="fw-medium">分类</th>
                        <th scope="col" class="fw-medium">作者</th>
                        <th scope="col" class="fw-medium">状态</th>
                        <th scope="col" class="fw-medium">发布时间</th>
                        <th scope="col" class="text-end fw-medium">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentContents as $content): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if (isset($content['is_top']) && $content['is_top']): ?>
                                <span class="badge bg-danger me-2">置顶</span>
                                <?php endif; ?>
                                <div class="fw-medium text-truncate" style="max-width: 250px;">
                                    <?php echo htmlspecialchars($content['title']); ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">
                                <?php echo htmlspecialchars($content['category_name'] ?? '未分类'); ?>
                            </span>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($content['username'] ?? '未知'); ?></small>
                        </td>
                        <td>
                            <?php if ($content['status'] === 'published'): ?>
                            <span class="badge bg-success">已发布</span>
                            <?php else: ?>
                            <span class="badge bg-warning text-dark">草稿</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo date('Y-m-d H:i', strtotime($content['create_time'])); ?>
                            </small>
                        </td>
                        <td>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="?page=content&action=edit&id=<?php echo $content['id']; ?>" class="btn btn-sm btn-outline-primary" title="编辑">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </a>
                                <a href="../article.php?id=<?php echo $content['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="查看">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                        <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- 查看更多 -->
        <div class="card-footer bg-white text-end py-3">
            <a href="?page=content" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center">
                查看全部内容
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right ms-2" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                </svg>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.transition-hover {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}
.transition-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style> 