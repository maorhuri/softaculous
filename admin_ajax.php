<?php
/**
 * Softaculous SSO - Admin AJAX Handler
 * Handles AJAX requests from admin area for plugins/themes management
 */

// Initialize WHMCS
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/adminfunctions.php';

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Check if admin is logged in
if (empty($_SESSION['adminid'])) {
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
        
        return $api->togglePlugin($insId, $slug, $action);
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
