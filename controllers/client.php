<?php
/**
 * Client Area Controller for Softaculous SSO
 * Handles AJAX requests and SSO redirects
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once dirname(__DIR__) . '/lib/SoftaculousAPI.php';
require_once dirname(__DIR__) . '/lib/ServiceHelper.php';

use SoftaculousSso\SoftaculousAPI;
use SoftaculousSso\ServiceHelper;

/**
 * Handle custom module operations
 */
add_hook('ClientAreaProductDetailsOutput', 1, function($vars) {
    $modop = $_GET['modop'] ?? '';
    $action = $_GET['a'] ?? '';
    
    if ($modop !== 'custom') {
        return;
    }

    $serviceId = $vars['serviceid'] ?? $_GET['id'] ?? null;
    
    if (!$serviceId) {
        return;
    }

    // Verify ownership
    $clientId = $_SESSION['uid'] ?? null;
    if (!$clientId) {
        if ($action === 'softaculous_sso_get_installations') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'לא מחובר']);
            exit;
        }
        return;
    }

    $service = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->where('userid', $clientId)
        ->first();

    if (!$service) {
        if ($action === 'softaculous_sso_get_installations') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'שירות לא נמצא']);
            exit;
        }
        return;
    }

    switch ($action) {
        case 'softaculous_sso_get_installations':
            handleGetInstallations($serviceId);
            break;
        case 'softaculous_sso_login':
            handleSsoLogin($serviceId);
            break;
        case 'softaculous_sso_clone':
            handleCloneInstallation($serviceId);
            break;
        case 'softaculous_sso_set_auto_upgrade':
            handleSetAutoUpgrade($serviceId);
            break;
        case 'softaculous_sso_get_auto_upgrade':
            handleGetAutoUpgrade($serviceId);
            break;
        case 'softaculous_sso_get_plugins':
            handleGetPlugins($serviceId);
            break;
        case 'softaculous_sso_get_themes':
            handleGetThemes($serviceId);
            break;
        case 'softaculous_sso_toggle_plugin':
            handleTogglePlugin($serviceId);
            break;
        case 'softaculous_sso_activate_theme':
            handleActivateTheme($serviceId);
            break;
        case 'softaculous_sso_install_wordpress':
            handleInstallWordPress($serviceId);
            break;
        case 'softaculous_sso_scan_installations':
            handleScanInstallations($serviceId);
            break;
    }
});

/**
 * Get WordPress installations for a service
 */
function handleGetInstallations($serviceId)
{
    header('Content-Type: application/json');

    try {
        $serverDetails = ServiceHelper::getServerDetails($serviceId);
        
        if (!$serverDetails) {
            echo json_encode(['error' => 'לא ניתן לקבל פרטי שרת']);
            exit;
        }

        $port = ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);

        // Debug info for troubleshooting
        $debugInfo = [
            'server_type' => $serverDetails['server_type'],
            'server_module' => $serverDetails['server_module'],
            'hostname' => $serverDetails['hostname'],
            'port' => $port,
            'username' => $serverDetails['username'],
            'secure' => $serverDetails['secure'],
            'password_length' => strlen($serverDetails['password']),
            'password_empty' => empty($serverDetails['password']),
        ];

        $api = new SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );

        $result = $api->getWordPressInstallations();

        if (isset($result['error'])) {
            $errorMsg = 'שגיאה בחיבור לשרת: ' . $result['error'];
            $errorMsg .= ' | Connection: ' . json_encode($debugInfo, JSON_UNESCAPED_UNICODE);
            if (isset($result['debug'])) {
                $errorMsg .= ' | Response: ' . json_encode($result['debug'], JSON_UNESCAPED_UNICODE);
            }
            echo json_encode(['error' => $errorMsg]);
            exit;
        }

        // Debug: log raw result
        if (isset($result['_debug_raw'])) {
            $result['debug_info'] = $result['_debug_raw'];
            unset($result['_debug_raw']);
        }

        echo json_encode($result);
    } catch (\Exception $e) {
        echo json_encode(['error' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Handle SSO login redirect
 */
function handleSsoLogin($serviceId)
{
    $insId = $_GET['insid'] ?? '';
    
    if (empty($insId)) {
        die('מזהה התקנה חסר');
    }

    try {
        $serverDetails = ServiceHelper::getServerDetails($serviceId);
        
        if (!$serverDetails) {
            die('לא ניתן לקבל פרטי שרת');
        }

        $port = ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);

        $api = new SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );

        $result = $api->getSignOnUrl($insId);

        if (isset($result['error'])) {
            die('שגיאה בהתחברות: ' . $result['error']);
        }

        if (isset($result['sign_on_url'])) {
            header('Location: ' . $result['sign_on_url']);
            exit;
        }

        die('לא ניתן לקבל קישור התחברות');
    } catch (\Exception $e) {
        die('שגיאה: ' . $e->getMessage());
    }
}

/**
 * Handle clone installation
 */
function handleCloneInstallation($serviceId)
{
    header('Content-Type: application/json');

    $insId = $_POST['insid'] ?? $_GET['insid'] ?? '';
    $domain = $_POST['domain'] ?? '';
    $directory = $_POST['directory'] ?? '';
    $dbName = $_POST['dbname'] ?? '';

    if (empty($insId) || empty($domain)) {
        echo json_encode(['error' => 'חסרים פרטים נדרשים']);
        exit;
    }

    try {
        $serverDetails = ServiceHelper::getServerDetails($serviceId);
        
        if (!$serverDetails) {
            echo json_encode(['error' => 'לא ניתן לקבל פרטי שרת']);
            exit;
        }

        $port = ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);

        $api = new SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );

        $result = $api->cloneInstallation($insId, $domain, $directory, $dbName, false);

        echo json_encode($result);
    } catch (\Exception $e) {
        echo json_encode(['error' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Handle set auto-upgrade settings
 */
function handleSetAutoUpgrade($serviceId)
{
    header('Content-Type: application/json');

    $insId = $_POST['insid'] ?? $_GET['insid'] ?? '';
    $autoCore = isset($_POST['auto_core']) ? $_POST['auto_core'] === '1' : true;
    $autoPlugins = isset($_POST['auto_plugins']) ? $_POST['auto_plugins'] === '1' : true;
    $autoThemes = isset($_POST['auto_themes']) ? $_POST['auto_themes'] === '1' : true;

    if (empty($insId)) {
        echo json_encode(['error' => 'מזהה התקנה חסר']);
        exit;
    }

    try {
        $serverDetails = ServiceHelper::getServerDetails($serviceId);
        
        if (!$serverDetails) {
            echo json_encode(['error' => 'לא ניתן לקבל פרטי שרת']);
            exit;
        }

        $port = ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);

        $api = new SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );

        $result = $api->setAutoUpgrade($insId, $autoCore, $autoPlugins, $autoThemes);

        echo json_encode($result);
    } catch (\Exception $e) {
        echo json_encode(['error' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Handle get auto-upgrade settings
 */
function handleGetAutoUpgrade($serviceId)
{
    header('Content-Type: application/json');

    $insId = $_GET['insid'] ?? '';

    if (empty($insId)) {
        echo json_encode(['error' => 'מזהה התקנה חסר']);
        exit;
    }

    try {
        $serverDetails = ServiceHelper::getServerDetails($serviceId);
        
        if (!$serverDetails) {
            echo json_encode(['error' => 'לא ניתן לקבל פרטי שרת']);
            exit;
        }

        $port = ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);

        $api = new SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );

        $result = $api->getAutoUpgradeSettings($insId);

        echo json_encode($result);
    } catch (\Exception $e) {
        echo json_encode(['error' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Get plugins list for an installation
 */
function handleGetPlugins($serviceId)
{
    header('Content-Type: application/json');

    $insId = $_GET['insid'] ?? '';

    if (empty($insId)) {
        echo json_encode(['error' => 'מזהה התקנה חסר', 'step' => 1]);
        exit;
    }

    try {
        $serverDetails = ServiceHelper::getServerDetails($serviceId);
        
        if (!$serverDetails) {
            echo json_encode(['error' => 'לא ניתן לקבל פרטי שרת', 'step' => 2]);
            exit;
        }

        $port = ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);

        $api = new SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );

        $result = $api->getPlugins($insId);

        // Always add debug info
        $result['_debug'] = [
            'server_type' => $serverDetails['server_type'],
            'hostname' => $serverDetails['hostname'],
            'port' => $port,
            'insid' => $insId,
            'step' => 'success'
        ];

        echo json_encode($result);
    } catch (\Exception $e) {
        echo json_encode(['error' => 'שגיאה: ' . $e->getMessage(), 'step' => 'exception']);
    }
    exit;
}

/**
 * Get themes list for an installation
 */
function handleGetThemes($serviceId)
{
    header('Content-Type: application/json');

    $insId = $_GET['insid'] ?? '';

    if (empty($insId)) {
        echo json_encode(['error' => 'מזהה התקנה חסר']);
        exit;
    }

    try {
        $serverDetails = ServiceHelper::getServerDetails($serviceId);
        
        if (!$serverDetails) {
            echo json_encode(['error' => 'לא ניתן לקבל פרטי שרת']);
            exit;
        }

        $port = ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);

        $api = new SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );

        $result = $api->getThemes($insId);

        echo json_encode($result);
    } catch (\Exception $e) {
        echo json_encode(['error' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Toggle plugin activation
 */
function handleTogglePlugin($serviceId)
{
    header('Content-Type: application/json');

    $insId = $_POST['insid'] ?? $_GET['insid'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $pluginAction = $_POST['plugin_action'] ?? '';

    if (empty($insId) || empty($slug) || empty($pluginAction)) {
        echo json_encode(['error' => 'חסרים פרטים נדרשים']);
        exit;
    }

    try {
        $serverDetails = ServiceHelper::getServerDetails($serviceId);
        
        if (!$serverDetails) {
            echo json_encode(['error' => 'לא ניתן לקבל פרטי שרת']);
            exit;
        }

        $port = ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);

        $api = new SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );

        if ($pluginAction === 'activate') {
            $result = $api->activatePlugin($insId, $slug);
        } elseif ($pluginAction === 'delete') {
            $result = $api->deletePlugin($insId, $slug);
        } else {
            $result = $api->deactivatePlugin($insId, $slug);
        }

        echo json_encode($result);
    } catch (\Exception $e) {
        echo json_encode(['error' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Activate a theme
 */
function handleActivateTheme($serviceId)
{
    header('Content-Type: application/json');

    $insId = $_POST['insid'] ?? $_GET['insid'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $themeAction = $_POST['theme_action'] ?? 'activate';

    if (empty($insId) || empty($slug)) {
        echo json_encode(['error' => 'חסרים פרטים נדרשים']);
        exit;
    }

    try {
        $serverDetails = ServiceHelper::getServerDetails($serviceId);
        
        if (!$serverDetails) {
            echo json_encode(['error' => 'לא ניתן לקבל פרטי שרת']);
            exit;
        }

        $port = ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);

        $api = new SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );

        if ($themeAction === 'delete') {
            $result = $api->deleteTheme($insId, $slug);
        } else {
            $result = $api->activateTheme($insId, $slug);
        }

        echo json_encode($result);
    } catch (\Exception $e) {
        echo json_encode(['error' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Install WordPress on the service domain
 */
function handleInstallWordPress($serviceId)
{
    header('Content-Type: application/json');

    try {
        $serverDetails = ServiceHelper::getServerDetails($serviceId);
        
        if (!$serverDetails) {
            echo json_encode(['error' => 'לא ניתן לקבל פרטי שרת']);
            exit;
        }

        $port = ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);

        $api = new SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );

        $result = $api->installWordPress($serverDetails['domain']);

        if (isset($result['error'])) {
            // Ensure error is a string
            $errorMsg = $result['error'];
            if (!is_string($errorMsg)) {
                $errorMsg = json_encode($errorMsg);
            }
            echo json_encode(['error' => $errorMsg]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'admin_url' => $result['admin_url'] ?? 'https://' . $serverDetails['domain'] . '/wp-admin',
            'admin_username' => $result['admin_username'] ?? '',
            'admin_password' => $result['admin_password'] ?? ''
        ]);
    } catch (\Exception $e) {
        echo json_encode(['error' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Scan for existing WordPress installations
 */
function handleScanInstallations($serviceId)
{
    header('Content-Type: application/json');

    try {
        $serverDetails = ServiceHelper::getServerDetails($serviceId);
        
        if (!$serverDetails) {
            echo json_encode(['error' => 'לא ניתן לקבל פרטי שרת']);
            exit;
        }

        $port = ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);

        $api = new SoftaculousAPI(
            $serverDetails['server_type'],
            $serverDetails['hostname'],
            $port,
            $serverDetails['username'],
            $serverDetails['password'],
            $serverDetails['secure']
        );

        $result = $api->scanInstallations();

        echo json_encode($result);
    } catch (\Exception $e) {
        echo json_encode(['error' => 'שגיאה: ' . $e->getMessage()]);
    }
    exit;
}
