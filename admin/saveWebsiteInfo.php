<?php
/**
 * 网站信息获取工具
 * 用于自动获取网站信息并保存相关图片
 */

// 设置错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 设置日志文件
$logFile = 'website_fetch_log.txt';
function logMessage($message) {
    global $logFile;
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents(__DIR__ . '/' . $logFile, $timestamp . ' ' . $message . "\n", FILE_APPEND);
}

/**
 * 将图片转换为WebP格式以减少存储空间
 * @param string $sourcePath 源图片路径
 * @param string $destPath 目标WebP图片路径(不含扩展名)
 * @param int $quality WebP质量(0-100)
 * @return string|bool 成功返回生成的WebP文件路径，失败返回false
 */
function convertToWebP($sourcePath, $destPath, $quality = 80) {
    // 检查GD库和WebP支持
    if (!function_exists('imagewebp')) {
        logMessage('错误: 系统不支持WebP转换，缺少imagewebp函数');
        return false;
    }
    
    // 检查源文件
    if (!file_exists($sourcePath)) {
        logMessage('错误: 源文件不存在: ' . $sourcePath);
        return false;
    }
    
    $destPath = $destPath . '.webp';
    
    // 获取文件扩展名
    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    
    try {
        // 根据不同图片格式创建图像资源
        $srcImage = null;
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $srcImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'png':
                $srcImage = imagecreatefrompng($sourcePath);
                // 保留PNG透明度
                imagepalettetotruecolor($srcImage);
                imagealphablending($srcImage, true);
                imagesavealpha($srcImage, true);
                break;
            case 'gif':
                $srcImage = imagecreatefromgif($sourcePath);
                break;
            case 'bmp':
                $srcImage = imagecreatefrombmp($sourcePath);
                break;
            case 'webp':
                // 已经是webp格式，直接复制
                if (copy($sourcePath, $destPath)) {
                    logMessage('文件已经是WebP格式，直接复制: ' . $destPath);
                    return $destPath;
                }
                return false;
            default:
                logMessage('不支持的图片格式: ' . $extension);
                return false;
        }
        
        if (!$srcImage) {
            logMessage('无法创建图像资源: ' . $sourcePath);
            return false;
        }
        
        // 转换为WebP
        $result = imagewebp($srcImage, $destPath, $quality);
        imagedestroy($srcImage);
        
        if ($result) {
            // 比较文件大小，如果WebP不比原图小，则保留原图
            $originalSize = filesize($sourcePath);
            $webpSize = filesize($destPath);
            
            if ($webpSize > $originalSize) {
                logMessage('WebP转换后体积更大，保留原始文件: ' . $sourcePath);
                unlink($destPath); // 删除WebP文件
                return false;
            }
            
            logMessage('成功转换为WebP，压缩率: ' . round(($originalSize - $webpSize) / $originalSize * 100, 2) . '%');
            
            // 删除原始文件节省空间
            unlink($sourcePath);
            
            return $destPath;
        } else {
            logMessage('WebP转换失败: ' . $sourcePath);
            return false;
        }
    } catch (Exception $e) {
        logMessage('WebP转换异常: ' . $e->getMessage());
        return false;
    }
}

// 确保上传目录存在
function ensureUploadDirExists($dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0777, true)) {
            logMessage('错误: 无法创建目录: ' . $dir);
            return false;
        }
        logMessage('已创建目录: ' . $dir);
    }
    
    // 确保目录可写
    if (!is_writable($dir)) {
        if (!chmod($dir, 0777)) {
            logMessage('错误: 无法修改目录权限: ' . $dir);
            return false;
        }
        logMessage('已修改目录权限: ' . $dir);
    }
    
    return true;
}

// 确保uploads目录存在
$uploadsBaseDir = __DIR__ . '/../uploads/';
$uploadsWebsitesDir = $uploadsBaseDir . 'websites/';

if (!ensureUploadDirExists($uploadsBaseDir) || !ensureUploadDirExists($uploadsWebsitesDir)) {
    logMessage('无法确保上传基础目录存在且可写');
    outputJson(['success' => false, 'message' => '系统错误: 无法准备上传目录，请联系管理员']);
    exit;
}

logMessage('开始处理请求');

// 确保请求为POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logMessage('错误: 非POST请求');
    outputJson(['success' => false, 'message' => '请使用POST请求']);
    exit;
}

// 获取URL参数
$url = isset($_POST['url']) ? trim($_POST['url']) : '';
if (empty($url)) {
    logMessage('错误: URL为空');
    outputJson(['success' => false, 'message' => 'URL不能为空']);
    exit;
}

// 验证URL格式
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    logMessage('错误: URL格式不正确: ' . $url);
    outputJson(['success' => false, 'message' => 'URL格式不正确']);
    exit;
}

logMessage('处理URL: ' . $url);

// 创建存储目录
$date = date('Y-m-d');
$parsedUrl = parse_url($url);
$domain = preg_replace('/[^a-zA-Z0-9-_\.]/', '-', $parsedUrl['host']); // 域名作为文件夹名，替换非法字符

$uploadDir = __DIR__ . '/../uploads/websites/' . $date . '/' . $domain . '/';
$relativeDir = '/uploads/websites/' . $date . '/' . $domain . '/';

if (!file_exists($uploadDir)) {
    logMessage('创建目录: ' . $uploadDir);
    if (!mkdir($uploadDir, 0777, true)) {
        logMessage('错误: 无法创建目录: ' . $uploadDir);
        outputJson(['success' => false, 'message' => '无法创建上传目录，请检查权限']);
        exit;
    }
}

try {
    logMessage('获取网站内容');
    // 获取网站内容
    $response = getWebsiteContent($url);
    if (!$response) {
        logMessage('错误: 无法获取网站内容');
        outputJson(['success' => false, 'message' => '无法访问该网站，请确认URL正确并且网站可访问']);
        exit;
    }
    
    logMessage('成功获取网站内容，提取信息');
    
    // 提取标题
    $title = extractTitle($response);
    logMessage('提取标题结果: ' . ($title ? $title : '未找到标题'));
    
    // 提取并保存图标
    $favicon = extractAndSaveFavicon($url, $response, $uploadDir, $relativeDir);
    logMessage('提取图标结果: ' . ($favicon ? '成功' : '失败'));
    
    // 创建截图
    $screenshots = takeScreenshots($url, $uploadDir, $relativeDir);
    logMessage('创建截图结果: ' . count($screenshots) . '张');
    
    // 处理提取到的图片，转换为WebP格式
    $processedScreenshots = [];
    foreach ($screenshots as $screenshot) {
        // 如果已经是WebP格式，则直接使用
        if (pathinfo($screenshot, PATHINFO_EXTENSION) === 'webp') {
            $processedScreenshots[] = $screenshot;
        } else {
            // 获取完整路径
            $fullPath = __DIR__ . '/..' . $screenshot;
            if (file_exists($fullPath)) {
                // 转换为WebP
                $webpFile = convertToWebP($fullPath, dirname($fullPath) . '/' . pathinfo($fullPath, PATHINFO_FILENAME), 85);
                if ($webpFile) {
                    // 获取相对路径
                    $webpRelativePath = dirname($screenshot) . '/' . basename($webpFile);
                    $processedScreenshots[] = $webpRelativePath;
                } else {
                    $processedScreenshots[] = $screenshot;
                }
            } else {
                $processedScreenshots[] = $screenshot;
            }
        }
    }
    
    // 处理网站信息
    $result = [
        'success' => true,
        'url' => $url,
        'title' => $title,
        'description' => extractDescription($response),
        'favicon' => $favicon,
        'screenshots' => $processedScreenshots
    ];
    
    logMessage('处理成功，返回结果');
    outputJson($result);
} catch (Exception $e) {
    logMessage('发生异常: ' . $e->getMessage());
    outputJson(['success' => false, 'message' => '处理过程中出错: ' . $e->getMessage()]);
}

/**
 * 获取网站内容 - 使用cURL代替file_get_contents
 */
function getWebsiteContent($url) {
    if (!function_exists('curl_init')) {
        logMessage('cURL不可用，尝试使用file_get_contents');
        return useFileGetContents($url);
    }
    
    logMessage('使用cURL获取内容');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    if ($error) {
        logMessage('cURL错误: ' . $error);
        return false;
    }
    
    logMessage('cURL请求状态码: ' . $info['http_code']);
    
    if ($info['http_code'] >= 400) {
        logMessage('HTTP错误: ' . $info['http_code']);
        return false;
    }
    
    return $response;
}

/**
 * 使用file_get_contents作为备选方案
 */
function useFileGetContents($url) {
    logMessage('使用file_get_contents获取内容');
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
            'timeout' => 30,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];
    $context = stream_context_create($options);
    
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        logMessage('file_get_contents获取失败');
        return false;
    }
    
    return $response;
}

/**
 * 提取网站标题
 */
function extractTitle($html) {
    if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

/**
 * 提取并保存网站图标
 */
function extractAndSaveFavicon($url, $html, $uploadDir, $relativeDir) {
    logMessage('开始提取favicon');
    $parsedUrl = parse_url($url);
    $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    
    // 尝试从HTML中提取favicon链接
    $faviconUrl = '';
    if (preg_match('/<link[^>]+rel=("|\')(?:shortcut )?icon("|\')[^>]+href=("|\')(.*?)("|\')[^>]*>/i', $html, $matches)) {
        $faviconUrl = $matches[4];
        logMessage('从HTML提取到favicon链接: ' . $faviconUrl);
    }
    
    // 如果未找到，使用默认路径
    if (empty($faviconUrl)) {
        $faviconUrl = $baseUrl . '/favicon.ico';
        logMessage('使用默认favicon路径: ' . $faviconUrl);
    } elseif (strpos($faviconUrl, 'http') !== 0) {
        // 相对路径转为绝对路径
        if (strpos($faviconUrl, '/') === 0) {
            $faviconUrl = $baseUrl . $faviconUrl;
        } else {
            $faviconUrl = $baseUrl . '/' . $faviconUrl;
        }
        logMessage('转换favicon为绝对路径: ' . $faviconUrl);
    }
    
    // 获取文件扩展名
    $extension = pathinfo(parse_url($faviconUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
    $extension = $extension ? $extension : 'ico';
    
    // 生成文件名
    $filename = 'favicon.' . $extension;
    $filePath = $uploadDir . $filename;
    $relativePath = $relativeDir . $filename;
    
    logMessage('尝试下载favicon到: ' . $filePath);
    
    // 下载图标 - 使用cURL
    if (downloadFile($faviconUrl, $filePath)) {
        logMessage('favicon下载成功');
        
        // 尝试转换为WebP格式 (对于非ico格式的图标)
        if ($extension != 'ico') {
            $webpFile = convertToWebP($filePath, $uploadDir . 'favicon', 90);
            if ($webpFile) {
                // 更新相对路径为WebP文件
                $relativePath = $relativeDir . 'favicon.webp';
                logMessage('favicon已转换为WebP格式: ' . $relativePath);
            }
        }
        
        return $relativePath;
    }
    
    logMessage('favicon下载失败');
    return '';
}

/**
 * 使用cURL下载文件
 */
function downloadFile($url, $savePath) {
    logMessage('尝试下载文件: ' . $url);
    
    // 尝试使用cURL下载
    if (function_exists('curl_init')) {
        logMessage('使用cURL下载文件');
        $ch = curl_init($url);
        $fp = fopen($savePath, 'wb');
        
        if (!$fp) {
            logMessage('无法打开文件进行写入: ' . $savePath);
            return false;
        }
        
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FAILONERROR, false); // 获取所有HTTP响应码
        
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        fclose($fp);
        
        if ($error) {
            logMessage('cURL下载文件错误: ' . $error);
            return tryFallbackDownload($url, $savePath);
        }
        
        if ($httpCode >= 400) {
            logMessage('HTTP错误 ' . $httpCode . ' 下载文件: ' . $url);
            return tryFallbackDownload($url, $savePath);
        }
        
        // 验证文件是否成功下载且有内容
        if (file_exists($savePath) && filesize($savePath) > 0) {
            logMessage('文件下载成功: ' . $url . ' 到 ' . $savePath);
            return true;
        } else {
            logMessage('文件下载失败或为空: ' . $url);
            return tryFallbackDownload($url, $savePath);
        }
    } else {
        // 如果cURL不可用，直接使用备选方法
        return tryFallbackDownload($url, $savePath);
    }
}

/**
 * 尝试使用备选方法下载文件
 */
function tryFallbackDownload($url, $savePath) {
    logMessage('尝试使用file_get_contents下载: ' . $url);
    
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
            'timeout' => 30,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];
    $context = stream_context_create($options);
    
    $content = @file_get_contents($url, false, $context);
    if ($content === false) {
        logMessage('file_get_contents下载失败: ' . $url);
        return false;
    }
    
    $result = @file_put_contents($savePath, $content);
    if ($result === false) {
        logMessage('无法写入文件: ' . $savePath);
        return false;
    }
    
    logMessage('使用file_get_contents下载成功: ' . $url);
    return filesize($savePath) > 0;
}

/**
 * 提取网站描述（meta description）
 */
function extractDescription($html) {
    logMessage('开始提取网站描述');
    // 尝试从meta description标签提取
    if (preg_match('/<meta[^>]+name=("|\')description("|\')[^>]+content=("|\')(.*?)("|\')[^>]*>/i', $html, $matches)) {
        $description = trim($matches[4]);
        logMessage('从meta标签提取到描述: ' . $description);
        return $description;
    }
    
    // 尝试从开放图谱(Open Graph)meta标签提取
    if (preg_match('/<meta[^>]+property=("|\')og:description("|\')[^>]+content=("|\')(.*?)("|\')[^>]*>/i', $html, $matches)) {
        $description = trim($matches[4]);
        logMessage('从og:description提取到描述: ' . $description);
        return $description;
    }
    
    logMessage('未找到网站描述');
    return '';
}

/**
 * 创建网站截图
 */
function takeScreenshots($url, $uploadDir, $relativeDir) {
    logMessage('开始创建截图');
    $screenshots = [];
    
    try {
        // 使用WordPress的mshots服务获取截图
        $encodedUrl = urlencode($url);
        // 获取全尺寸截图
        $screenshotUrl = "https://s0.wp.com/mshots/v1/{$encodedUrl}?w=600&h=400";
        
        logMessage('使用mshots服务获取截图: ' . $screenshotUrl);
        
        // 保存截图
        $filename = 'screenshot.jpg';
        $filePath = $uploadDir . $filename;
        $relativePath = $relativeDir . $filename;
        
        logMessage('尝试下载截图到: ' . $filePath);
        
        // 下载完整尺寸截图
        if (downloadFile($screenshotUrl, $filePath)) {
            logMessage('截图下载成功');
            
            // 转换为WebP格式
            $webpFile = convertToWebP($filePath, $uploadDir . 'screenshot', 85);
            if ($webpFile) {
                $relativePath = $relativeDir . 'screenshot.webp';
                logMessage('截图已转换为WebP格式: ' . $relativePath);
            }
            
            $screenshots[] = $relativePath;
        } else {
            logMessage('截图下载失败，尝试使用备选方法');
            
            // 如果下载失败，使用之前的模拟截图方式作为备选
            $width = 1280;
            $height = 800;
            $image = imagecreatetruecolor($width, $height);
            $bgColor = imagecolorallocate($image, 245, 245, 245);
            imagefill($image, 0, 0, $bgColor);
            
            // 添加文本
            $textColor = imagecolorallocate($image, 50, 50, 50);
            $text = "无法获取 $url 的截图\n生成时间: " . date('Y-m-d H:i:s');
            imagestring($image, 5, 20, 20, $text, $textColor);
            
            // 直接保存为WebP
            $webpPath = $uploadDir . 'screenshot.webp';
            $webpRelativePath = $relativeDir . 'screenshot.webp';
            
            if (imagewebp($image, $webpPath, 80)) {
                logMessage('备选截图保存为WebP格式: ' . $webpPath);
                $screenshots[] = $webpRelativePath;
            } else if (imagejpeg($image, $filePath, 90)) {
                logMessage('备选截图保存为JPEG格式: ' . $filePath);
                $screenshots[] = $relativePath;
            }
            
            imagedestroy($image);
        }
        
        return $screenshots;
    } catch (Exception $e) {
        logMessage('创建截图出错: ' . $e->getMessage());
        return [];
    }
}

/**
 * 输出JSON响应
 */
function outputJson($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * 处理图片集，将图片集中的图片转换为WebP格式
 * @param string $galleryString 图片集字符串，多个图片URL用逗号分隔
 * @param string $uploadDir 上传目录路径
 * @param string $relativeDir 相对目录路径（用于返回）
 * @return string 处理后的图片集字符串
 */
function processGalleryImages($galleryString, $uploadDir, $relativeDir) {
    logMessage('开始处理图片集: ' . $galleryString);
    
    if (empty($galleryString)) {
        return '';
    }
    
    // 分割图片URL
    $images = explode(',', $galleryString);
    $processedImages = [];
    
    foreach ($images as $index => $imageUrl) {
        $imageUrl = trim($imageUrl);
        if (empty($imageUrl)) {
            continue;
        }
        
        logMessage('处理图片 #' . ($index + 1) . ': ' . $imageUrl);
        
        // 生成唯一的文件名
        $filename = 'gallery_' . ($index + 1) . '_' . uniqid() . '.jpg';
        $filePath = $uploadDir . $filename;
        $relativePath = $relativeDir . $filename;
        
        // 下载图片
        if (downloadFile($imageUrl, $filePath)) {
            logMessage('图片下载成功: ' . $filePath);
            
            // 转换为WebP格式
            $webpFile = convertToWebP($filePath, $uploadDir . 'gallery_' . ($index + 1) . '_' . uniqid(), 85);
            if ($webpFile) {
                // 获取转换后文件的相对路径
                $webpRelativePath = $relativeDir . basename($webpFile);
                logMessage('图片已转换为WebP格式: ' . $webpRelativePath);
                $processedImages[] = $webpRelativePath;
            } else {
                // 如果转换失败，使用原始下载的图片
                $processedImages[] = $relativePath;
            }
        } else {
            // 如果下载失败，保留原始URL
            logMessage('图片下载失败，保留原始URL: ' . $imageUrl);
            $processedImages[] = $imageUrl;
        }
    }
    
    // 组合处理后的图片集
    $processedGallery = implode(',', $processedImages);
    logMessage('图片集处理完成: ' . $processedGallery);
    return $processedGallery;
} 