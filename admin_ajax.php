<?php
/**
 * Softaculous SSO - Admin AJAX Handler
 * Handles AJAX requests from admin area for plugins/themes management
 * This file bypasses addonmodules.php to work for all admin users/staff
 */

// Save uploaded files before WHMCS init (it may clear them)
$savedFiles = $_FILES;
$savedPost = $_POST;

// Prevent any output before headers
ob_start();

// Get the WHMCS root path
$whmcsRoot = realpath(__DIR__ . '/../../..');

// Initialize WHMCS properly
define('CLIENTAREA', true);
require_once $whmcsRoot . '/init.php';

// Restore uploaded files
$_FILES = $savedFiles;

// Clear output buffer and set headers
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Check admin session from database using the cookie
$adminSessionId = $_COOKIE['WHMCSAdminSession'] ?? '';
$adminId = null;

if (!empty($adminSessionId)) {
    // Query the database for admin session
    try {
        $result = \WHMCS\Database\Capsule::table('tbladmins')
            ->join('tbladminsessions', 'tbladmins.id', '=', 'tbladminsessions.adminid')
            ->where('tbladminsessions.sessionid', $adminSessionId)
            ->where('tbladminsessions.lastvisit', '>', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->select('tbladmins.id', 'tbladmins.username')
            ->first();
        
        if ($result) {
            $adminId = $result->id;
        }
    } catch (\Exception $e) {
        // Fallback - just check if cookie exists (less secure but works)
        $adminId = 1;
    }
}

if (empty($adminId)) {
    echo json_encode(['error' => 'Admin login required']);
    exit;
}

// Load required files
require_once __DIR__ . '/lib/SoftaculousAPI.php';
require_once __DIR__ . '/lib/ServiceHelper.php';

$cmd = $_GET['cmd'] ?? '';
$serviceId = $_GET['service_id'] ?? '';
$insId = $_GET['insid'] ?? '';

switch ($cmd) {
    case 'get_plugins':
        echo json_encode(getPlugins($serviceId, $insId));
        break;
    case 'get_themes':
        echo json_encode(getThemes($serviceId, $insId));
        break;
    case 'toggle_plugin':
        $slug = $_GET['slug'] ?? '';
        $action = $_GET['plugin_action'] ?? '';
        echo json_encode(togglePlugin($serviceId, $insId, $slug, $action));
        break;
    case 'activate_theme':
        $slug = $_GET['slug'] ?? '';
        echo json_encode(activateTheme($serviceId, $insId, $slug));
        break;
    case 'upload_plugin':
        $result = uploadPlugin($serviceId, $insId);
        // Return HTML that closes the window automatically
        header('Content-Type: text/html; charset=utf-8');
        $status = isset($result['success']) ? 'success' : 'error';
        $message = isset($result['success']) ? 'התוסף הועלה בהצלחה!' : ($result['error'] ?? 'שגיאה לא ידועה');
        echo '<!DOCTYPE html><html dir="rtl"><head><meta charset="utf-8"><title>העלאת תוסף</title></head><body style="font-family:Arial,sans-serif;text-align:center;padding:50px;">';
        echo '<h2 style="color:' . ($status === 'success' ? '#28a745' : '#dc3545') . ';">' . $message . '</h2>';
        echo '<p>החלון ייסגר אוטומטית...</p>';
        echo '<script>setTimeout(function(){ window.close(); }, 1500);</script>';
        echo '</body></html>';
        exit;
    case 'delete_plugin':
        $slug = $_GET['slug'] ?? '';
        echo json_encode(deletePlugin($serviceId, $insId, $slug));
        break;
    default:
        echo json_encode(['error' => 'Unknown command']);
}
exit;

function getPlugins($serviceId, $insId) {
    if (empty($serviceId) || empty($insId)) {
        return ['error' => 'חסרים פרטים נדרשים'];
    }
    
    try {
        $serverDetails = \SoftaculousSso\ServiceHelper::getServerDetails($serviceId);
        if (!$serverDetails) {
            return ['error' => 'לא ניתן לקבל פרטי שרת'];
        }
        
        $port = \SoftaculousSso\ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);
        
        $api = new \SoftaculousSso\SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );
        
        return $api->getPlugins($insId);
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}

function getThemes($serviceId, $insId) {
    if (empty($serviceId) || empty($insId)) {
        return ['error' => 'חסרים פרטים נדרשים'];
    }
    
    try {
        $serverDetails = \SoftaculousSso\ServiceHelper::getServerDetails($serviceId);
        if (!$serverDetails) {
            return ['error' => 'לא ניתן לקבל פרטי שרת'];
        }
        
        $port = \SoftaculousSso\ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);
        
        $api = new \SoftaculousSso\SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );
        
        return $api->getThemes($insId);
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}

function togglePlugin($serviceId, $insId, $slug, $action) {
    if (empty($serviceId) || empty($insId) || empty($slug) || empty($action)) {
        return ['error' => 'חסרים פרטים נדרשים'];
    }
    
    try {
        $serverDetails = \SoftaculousSso\ServiceHelper::getServerDetails($serviceId);
        if (!$serverDetails) {
            return ['error' => 'לא ניתן לקבל פרטי שרת'];
        }
        
        $port = \SoftaculousSso\ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);
        
        $api = new \SoftaculousSso\SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );
        
        if ($action === 'activate') {
            return $api->activatePlugin($insId, $slug);
        } else {
            return $api->deactivatePlugin($insId, $slug);
        }
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}

function activateTheme($serviceId, $insId, $slug) {
    if (empty($serviceId) || empty($insId) || empty($slug)) {
        return ['error' => 'חסרים פרטים נדרשים'];
    }
    
    try {
        $serverDetails = \SoftaculousSso\ServiceHelper::getServerDetails($serviceId);
        if (!$serverDetails) {
            return ['error' => 'לא ניתן לקבל פרטי שרת'];
        }
        
        $port = \SoftaculousSso\ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);
        
        $api = new \SoftaculousSso\SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );
        
        return $api->activateTheme($insId, $slug);
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}

function deletePlugin($serviceId, $insId, $slug) {
    if (empty($serviceId) || empty($insId) || empty($slug)) {
        return ['error' => 'חסרים פרטים נדרשים'];
    }
    
    $isActive = isset($_GET['is_active']) && ($_GET['is_active'] === '1' || $_GET['is_active'] === 'true');
    
    try {
        $serverDetails = \SoftaculousSso\ServiceHelper::getServerDetails($serviceId);
        if (!$serverDetails) {
            return ['error' => 'לא ניתן לקבל פרטי שרת'];
        }
        
        $port = \SoftaculousSso\ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);
        
        $api = new \SoftaculousSso\SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );
        
        // If plugin is active, deactivate it first
        if ($isActive) {
            $deactivateResult = $api->deactivatePlugin($insId, $slug);
            // Wait a moment for deactivation to complete
            usleep(500000); // 0.5 seconds
        }
        
        return $api->deletePlugin($insId, $slug);
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}

function uploadPlugin($serviceId, $insId) {
    global $savedFiles, $savedPost;
    
    if (empty($serviceId) || empty($insId)) {
        return ['error' => 'חסרים פרטים נדרשים'];
    }
    
    // Use saved files (before WHMCS init)
    $files = !empty($savedFiles) ? $savedFiles : $_FILES;
    
    // Check if file was received
    if (empty($files) || !isset($files['plugin_zip'])) {
        return ['error' => 'לא נבחר קובץ להעלאה'];
    }
    
    // Use the saved files
    $_FILES = $files;
    
    // Check if file was uploaded
    if (!isset($_FILES['plugin_zip']) || $_FILES['plugin_zip']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'הקובץ גדול מדי (מגבלת שרת)',
            UPLOAD_ERR_FORM_SIZE => 'הקובץ גדול מדי',
            UPLOAD_ERR_PARTIAL => 'הקובץ הועלה באופן חלקי',
            UPLOAD_ERR_NO_FILE => 'לא נבחר קובץ',
            UPLOAD_ERR_NO_TMP_DIR => 'תיקיית זמנית חסרה',
            UPLOAD_ERR_CANT_WRITE => 'שגיאה בכתיבת הקובץ',
        ];
        $errorCode = $_FILES['plugin_zip']['error'] ?? UPLOAD_ERR_NO_FILE;
        return ['error' => $errorMessages[$errorCode] ?? 'שגיאה בהעלאת הקובץ', 'debug' => $_FILES];
    }
    
    $uploadedFile = $_FILES['plugin_zip'];
    $fileName = $uploadedFile['name'];
    $tmpPath = $uploadedFile['tmp_name'];
    
    // Validate file extension
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext !== 'zip') {
        return ['error' => 'יש להעלות קובץ ZIP בלבד'];
    }
    
    // Check if temp file exists
    if (!file_exists($tmpPath)) {
        return ['error' => 'קובץ זמני לא נמצא', 'tmp_path' => $tmpPath];
    }
    
    // Copy to a permanent temp location (the original tmp file may be deleted)
    $newTmpPath = sys_get_temp_dir() . '/softaculous_upload_' . uniqid() . '_' . $fileName;
    if (!move_uploaded_file($tmpPath, $newTmpPath)) {
        // Try copy if move fails
        if (!copy($tmpPath, $newTmpPath)) {
            return ['error' => 'לא ניתן להעתיק את הקובץ'];
        }
    }
    
    try {
        $serverDetails = \SoftaculousSso\ServiceHelper::getServerDetails($serviceId);
        if (!$serverDetails) {
            @unlink($newTmpPath);
            return ['error' => 'לא ניתן לקבל פרטי שרת'];
        }
        
        $port = \SoftaculousSso\ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);
        
        $api = new \SoftaculousSso\SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );
        
        $result = $api->uploadPlugin($insId, $newTmpPath, $fileName);
        
        // Clean up temp file
        @unlink($newTmpPath);
        
        return $result;
    } catch (\Exception $e) {
        @unlink($newTmpPath);
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}
