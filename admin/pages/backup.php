<?php
/**
 * 备份管理页面
 */

// 定义备份目录
$backupDir = dirname(dirname(__DIR__)) . '/backup';
$backupUrl = '../backup'; // 相对于admin目录的备份URL路径
$message = '';
$messageType = '';

// 如果备份目录不存在，则创建
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0777, true)) {
        $message = '创建备份目录失败';
        $messageType = 'error';
    }
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 创建备份
    if (isset($_POST['create_backup'])) {
        $dbFile = dirname(dirname(__DIR__)) . '/data/data.db';
        if (!file_exists($dbFile)) {
            $message = '数据库文件不存在';
            $messageType = 'error';
        } else {
            $backupFile = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.db';
            if (copy($dbFile, $backupFile)) {
                $message = '备份创建成功';
                $messageType = 'success';
            } else {
                $message = '备份创建失败';
                $messageType = 'error';
            }
        }
    }
    
    // 恢复备份
    if (isset($_POST['restore_backup']) && isset($_POST['backup_file'])) {
        $backupFile = $backupDir . '/' . $_POST['backup_file'];
        if (!file_exists($backupFile)) {
            $message = '备份文件不存在';
            $messageType = 'error';
        } else {
            $dbFile = dirname(dirname(__DIR__)) . '/data/data.db';
            if (copy($backupFile, $dbFile)) {
                $message = '备份恢复成功';
                $messageType = 'success';
            } else {
                $message = '备份恢复失败';
                $messageType = 'error';
            }
        }
    }
    
    // 删除备份
    if (isset($_POST['delete_backup']) && isset($_POST['backup_file'])) {
        $backupFile = $backupDir . '/' . $_POST['backup_file'];
        if (!file_exists($backupFile)) {
            $message = '备份文件不存在';
            $messageType = 'error';
        } else {
            if (unlink($backupFile)) {
                $message = '备份删除成功';
                $messageType = 'success';
            } else {
                $message = '备份删除失败';
                $messageType = 'error';
            }
        }
    }
    
    // 导入备份文件
    if (isset($_FILES['import_backup']) && $_FILES['import_backup']['error'] == 0) {
        $fileInfo = pathinfo($_FILES['import_backup']['name']);
        $extension = strtolower($fileInfo['extension']);
        
        if ($extension != 'db' && $extension != 'sqlite') {
            $message = '只支持导入.db或.sqlite文件';
            $messageType = 'error';
        } else {
            $filename = 'backup_imported_' . date('Y-m-d_H-i-s') . '.' . $extension;
            $destination = $backupDir . '/' . $filename;
            
            if (move_uploaded_file($_FILES['import_backup']['tmp_name'], $destination)) {
                $message = '备份文件导入成功';
                $messageType = 'success';
            } else {
                $message = '备份文件导入失败';
                $messageType = 'error';
            }
        }
    }
}

// 获取备份列表
$backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && (strpos($file, 'backup_') === 0) && (pathinfo($file, PATHINFO_EXTENSION) === 'db' || pathinfo($file, PATHINFO_EXTENSION) === 'sqlite')) {
            $backupPath = $backupDir . '/' . $file;
            $backups[] = [
                'name' => $file,
                'size' => filesize($backupPath),
                'time' => filemtime($backupPath)
            ];
        }
    }
    
    // 按时间降序排序
    usort($backups, function($a, $b) {
        return $b['time'] - $a['time'];
    });
}

// 格式化文件大小
function formatFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}
?>

<!-- 备份管理页面 -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0 d-flex align-items-center">
            <i class="bi bi-archive-fill text-primary me-2"></i>
            备份管理
        </h2>
        <div class="small text-muted">数据备份与恢复</div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center">
            <?php if ($messageType === 'error'): ?>
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php else: ?>
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php endif; ?>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <!-- 创建备份按钮和导入备份按钮 -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">备份操作</h5>
            <p class="card-text text-muted mb-3">创建或导入数据库的完整备份。备份将保存您的所有数据，包括文章、分类和用户信息。</p>
            <div class="d-flex justify-content-between">
                <form method="post">
                    <button type="submit" name="create_backup" class="btn btn-primary d-flex align-items-center">
                        <i class="bi bi-plus-circle me-2"></i>
                        创建新备份
                    </button>
                </form>
                
                <button type="button" class="btn btn-success d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#importBackupModal">
                    <i class="bi bi-upload me-2"></i>
                    导入备份
                </button>
            </div>
        </div>
    </div>
    
    <!-- 备份列表 -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">备份列表</h5>
            <p class="card-subtitle mt-1 text-muted small">管理您之前创建的所有备份</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>备份文件</th>
                        <th>大小</th>
                        <th>创建时间</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backups)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-5">
                            <i class="bi bi-archive text-muted fs-1 mb-3 d-block"></i>
                            <p class="mb-1 fw-medium">暂无备份数据</p>
                            <p class="text-muted small">点击"创建新备份"按钮来创建第一个备份</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($backups as $backup): ?>
                    <tr>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-file-earmark-zip me-2 text-muted"></i>
                                <span class="fw-medium"><?php echo htmlspecialchars($backup['name']); ?></span>
                            </div>
                        </td>
                        <td class="align-middle text-muted">
                            <?php echo formatFileSize($backup['size']); ?>
                        </td>
                        <td class="align-middle text-muted">
                            <?php echo date('Y-m-d H:i:s', $backup['time']); ?>
                        </td>
                        <td class="align-middle text-end">
                            <div class="btn-group">
                                <a href="<?php echo $backupUrl . '/' . urlencode($backup['name']); ?>" class="btn btn-sm btn-outline-success me-2" download>
                                    <i class="bi bi-download me-1"></i>
                                    下载
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-primary me-2 restore-backup" 
                                    data-bs-toggle="modal" data-bs-target="#restoreModal"
                                    data-file="<?php echo htmlspecialchars($backup['name']); ?>">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                                    恢复
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-backup" 
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    data-file="<?php echo htmlspecialchars($backup['name']); ?>">
                                    <i class="bi bi-trash me-1"></i>
                                    删除
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

<!-- 导入备份模态框 -->
<div class="modal fade" id="importBackupModal" tabindex="-1" aria-labelledby="importBackupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="importBackupModalLabel">
                    <i class="bi bi-upload me-2"></i>导入备份
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" enctype="multipart/form-data" id="importForm">
                    <div class="mb-3">
                        <label for="importFile" class="form-label fw-medium">选择备份文件</label>
                        <input type="file" class="form-control" id="importFile" name="import_backup" accept=".db,.sqlite" required>
                        <div class="form-text text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            请选择有效的SQLite数据库文件（.db或.sqlite格式）
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>取消
                </button>
                <button type="button" class="btn btn-success" id="submitImport">
                    <i class="bi bi-upload me-1"></i>导入
                </button>
            </div>
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
                        <h5 class="fw-bold mb-1">删除备份</h5>
                        <p class="mb-0">确定要删除备份文件吗？</p>
                        <p class="text-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i>此操作不可撤销</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>取消
                </button>
                <form method="post" id="deleteForm">
                    <input type="hidden" name="backup_file" id="deleteBackupFile">
                    <button type="submit" name="delete_backup" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>确认删除
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 恢复确认模态框 -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="restoreModalLabel">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>确认恢复
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary me-3">
                        <i class="bi bi-arrow-counterclockwise fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">恢复备份</h5>
                        <p class="mb-0">确定要恢复此备份文件吗？</p>
                        <p class="text-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i>当前数据将被覆盖！</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>取消
                </button>
                <form method="post" id="restoreForm">
                    <input type="hidden" name="backup_file" id="restoreBackupFile">
                    <button type="submit" name="restore_backup" class="btn btn-primary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>确认恢复
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 提交导入表单
        document.getElementById('submitImport').addEventListener('click', function() {
            document.getElementById('importForm').submit();
        });
        
        // 设置删除备份文件名
        const deleteButtons = document.querySelectorAll('.delete-backup');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const fileName = this.getAttribute('data-file');
                document.getElementById('deleteBackupFile').value = fileName;
            });
        });
        
        // 设置恢复备份文件名
        const restoreButtons = document.querySelectorAll('.restore-backup');
        restoreButtons.forEach(button => {
            button.addEventListener('click', function() {
                const fileName = this.getAttribute('data-file');
                document.getElementById('restoreBackupFile').value = fileName;
            });
        });
    });
</script> 