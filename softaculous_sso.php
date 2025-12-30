<?php
/**
 * Softaculous WordPress SSO Addon Module for WHMCS
 * Supports cPanel and DirectAdmin
 * 
 * @author MVN
 * @version 1.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function softaculous_sso_config()
{
    return [
        'name' => 'Softaculous WordPress SSO',
        'description' => 'התחברות בקליק לאתרי וורדפרס דרך Softaculous',
        'version' => '1.0.0',
        'author' => 'MVN',
        'fields' => [
            'default_port_cpanel' => [
                'FriendlyName' => 'פורט cPanel',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '2083',
                'Description' => 'פורט ברירת מחדל ל-cPanel (לרוב 2083)',
            ],
            'default_port_directadmin' => [
                'FriendlyName' => 'פורט DirectAdmin',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '2222',
                'Description' => 'פורט ברירת מחדל ל-DirectAdmin (לרוב 2222)',
            ],
            'custom_port' => [
                'FriendlyName' => 'פורט מותאם אישית',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '4564',
                'Description' => 'אם מוגדר, ישתמש בפורט זה במקום ברירת המחדל (למשל 4564)',
            ],
        ],
    ];
}

function softaculous_sso_activate()
{
    return [
        'status' => 'success',
        'description' => 'Softaculous WordPress SSO הופעל בהצלחה',
    ];
}

function softaculous_sso_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'Softaculous WordPress SSO הושבת בהצלחה',
    ];
}

function softaculous_sso_output($vars)
{
    // Handle admin actions
    $action = $_GET['action'] ?? '';
    
    if ($action === 'admin_get_installations') {
        header('Content-Type: application/json');
        echo json_encode(softaculous_sso_admin_get_installations());
        exit;
    }
    
    if ($action === 'admin_get_all_installations') {
        header('Content-Type: application/json');
        echo json_encode(softaculous_sso_admin_get_all_installations());
        exit;
    }
    
    if ($action === 'admin_sso') {
        softaculous_sso_admin_sso();
        exit;
    }
    
    if ($action === 'admin_install_wordpress') {
        header('Content-Type: application/json');
        echo json_encode(softaculous_sso_admin_install_wordpress());
        exit;
    }
    
    if ($action === 'admin_delete_installation') {
        header('Content-Type: application/json');
        echo json_encode(softaculous_sso_admin_delete_installation());
        exit;
    }
    
    if ($action === 'admin_get_plugins') {
        header('Content-Type: application/json');
        echo json_encode(softaculous_sso_admin_get_plugins());
        exit;
    }
    
    if ($action === 'admin_get_themes') {
        header('Content-Type: application/json');
        echo json_encode(softaculous_sso_admin_get_themes());
        exit;
    }
    
    if ($action === 'admin_toggle_plugin') {
        header('Content-Type: application/json');
        echo json_encode(softaculous_sso_admin_toggle_plugin());
        exit;
    }
    
    if ($action === 'admin_activate_theme') {
        header('Content-Type: application/json');
        echo json_encode(softaculous_sso_admin_activate_theme());
        exit;
    }
    
    echo '<h2>Softaculous WordPress SSO</h2>';
    echo '<p>המודול פעיל. הלקוחות יכולים לראות את אתרי הוורדפרס שלהם בעמוד פרטי המוצר.</p>';
}

/**
 * Get WordPress installations for admin
 */
function softaculous_sso_admin_get_installations()
{
    require_once __DIR__ . '/lib/SoftaculousAPI.php';
    require_once __DIR__ . '/lib/ServiceHelper.php';
    
    $serviceId = $_GET['service_id'] ?? '';
    
    if (empty($serviceId)) {
        return ['error' => 'חסר מזהה שירות'];
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
        
        $installations = $api->getWordPressInstallations();
        
        return ['installations' => $installations];
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}

/**
 * Perform SSO login for admin
 */
function softaculous_sso_admin_sso()
{
    require_once __DIR__ . '/lib/SoftaculousAPI.php';
    require_once __DIR__ . '/lib/ServiceHelper.php';
    
    $serviceId = $_GET['service_id'] ?? '';
    $insId = $_GET['insid'] ?? '';
    
    if (empty($serviceId) || empty($insId)) {
        die('חסרים פרטים נדרשים');
    }
    
    try {
        $serverDetails = \SoftaculousSso\ServiceHelper::getServerDetails($serviceId);
        
        if (!$serverDetails) {
            die('לא ניתן לקבל פרטי שרת');
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
        
        $ssoUrl = $api->getSignOnUrl($insId);
        
        if (isset($ssoUrl['error'])) {
            die('שגיאה: ' . $ssoUrl['error']);
        }
        
        if (!empty($ssoUrl['sign_on_url'])) {
            header('Location: ' . $ssoUrl['sign_on_url']);
            exit;
        }
        
        die('לא ניתן ליצור קישור התחברות');
    } catch (\Exception $e) {
        die('שגיאה: ' . $e->getMessage());
    }
}

/**
 * Get all WordPress installations for all services of a user
 */
function softaculous_sso_admin_get_all_installations()
{
    require_once __DIR__ . '/lib/SoftaculousAPI.php';
    require_once __DIR__ . '/lib/ServiceHelper.php';
    
    $userId = $_GET['user_id'] ?? '';
    
    if (empty($userId)) {
        return ['error' => 'חסר מזהה לקוח'];
    }
    
    try {
        // Get all active services for this user
        $services = Capsule::table('tblhosting')
            ->where('userid', $userId)
            ->where('domainstatus', 'Active')
            ->get();
        
        if (empty($services) || count($services) === 0) {
            return ['error' => 'לא נמצאו שירותים פעילים'];
        }
        
        $allInstallations = [];
        
        foreach ($services as $service) {
            // Get server details
            $server = Capsule::table('tblservers')
                ->where('id', $service->server)
                ->first();
            
            if (!$server) {
                continue;
            }
            
            $serverType = strtolower($server->type);
            if (!in_array($serverType, ['cpanel', 'directadmin'])) {
                continue;
            }
            
            // Get server details for API
            $serverDetails = \SoftaculousSso\ServiceHelper::getServerDetails($service->id);
            
            if (!$serverDetails) {
                continue;
            }
            
            $port = \SoftaculousSso\ServiceHelper::getPort($serverDetails['server_type'], $serverDetails);
            
            try {
                $api = new \SoftaculousSso\SoftaculousAPI(
                    $serverDetails['server_type'],
                    $serverDetails['hostname'],
                    $port,
                    $serverDetails['username'],
                    $serverDetails['password'],
                    $serverDetails['secure']
                );
                
                $result = $api->getWordPressInstallations();
                
                // getWordPressInstallations returns ['installations' => [...]]
                $installations = $result['installations'] ?? $result;
                
                if (!empty($installations) && is_array($installations)) {
                    foreach ($installations as $install) {
                        if (is_array($install)) {
                            $install['_service_id'] = $service->id;
                            $allInstallations[] = $install;
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Build services list for install form
        $servicesList = [];
        foreach ($services as $service) {
            $server = Capsule::table('tblservers')
                ->where('id', $service->server)
                ->first();
            
            if ($server) {
                $serverType = strtolower($server->type);
                if (in_array($serverType, ['cpanel', 'directadmin'])) {
                    $servicesList[] = [
                        'id' => $service->id,
                        'domain' => $service->domain
                    ];
                }
            }
        }
        
        return ['installations' => $allInstallations, 'services' => $servicesList];
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}

/**
 * Install WordPress on a service
 */
function softaculous_sso_admin_install_wordpress()
{
    require_once __DIR__ . '/lib/SoftaculousAPI.php';
    require_once __DIR__ . '/lib/ServiceHelper.php';
    
    $serviceId = $_GET['service_id'] ?? '';
    
    if (empty($serviceId)) {
        return ['error' => 'חסר מזהה שירות'];
    }
    
    try {
        // Get server details
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
        
        // Install WordPress on the main domain
        $result = $api->installWordPress($serverDetails['domain']);
        
        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }
        
        return [
            'success' => true,
            'admin_url' => $result['admin_url'] ?? 'https://' . $serverDetails['domain'] . '/wp-admin',
            'admin_username' => $result['admin_username'] ?? 'admin',
            'admin_password' => $result['admin_password'] ?? ''
        ];
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}

/**
 * Delete WordPress installation (admin)
 */
function softaculous_sso_admin_delete_installation()
{
    require_once __DIR__ . '/lib/SoftaculousAPI.php';
    require_once __DIR__ . '/lib/ServiceHelper.php';
    
    $serviceId = $_GET['service_id'] ?? '';
    $insId = $_GET['insid'] ?? '';
    
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
        
        $result = $api->deleteInstallation($insId);
        
        return $result;
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}

/**
 * Get plugins for admin
 */
function softaculous_sso_admin_get_plugins()
{
    require_once __DIR__ . '/lib/SoftaculousAPI.php';
    require_once __DIR__ . '/lib/ServiceHelper.php';
    
    $serviceId = $_GET['service_id'] ?? '';
    $insId = $_GET['insid'] ?? '';
    
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
        
        $result = $api->getPlugins($insId);
        
        return $result;
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}

/**
 * Get themes for admin
 */
function softaculous_sso_admin_get_themes()
{
    require_once __DIR__ . '/lib/SoftaculousAPI.php';
    require_once __DIR__ . '/lib/ServiceHelper.php';
    
    $serviceId = $_GET['service_id'] ?? '';
    $insId = $_GET['insid'] ?? '';
    
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
        
        $result = $api->getThemes($insId);
        
        return $result;
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}

/**
 * Toggle plugin for admin
 */
function softaculous_sso_admin_toggle_plugin()
{
    require_once __DIR__ . '/lib/SoftaculousAPI.php';
    require_once __DIR__ . '/lib/ServiceHelper.php';
    
    $serviceId = $_GET['service_id'] ?? '';
    $insId = $_GET['insid'] ?? '';
    $slug = $_GET['slug'] ?? '';
    $pluginAction = $_GET['plugin_action'] ?? '';
    
    if (empty($serviceId) || empty($insId) || empty($slug) || empty($pluginAction)) {
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
        
        $result = $api->togglePlugin($insId, $slug, $pluginAction);
        
        return $result;
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}

/**
 * Activate theme for admin
 */
function softaculous_sso_admin_activate_theme()
{
    require_once __DIR__ . '/lib/SoftaculousAPI.php';
    require_once __DIR__ . '/lib/ServiceHelper.php';
    
    $serviceId = $_GET['service_id'] ?? '';
    $insId = $_GET['insid'] ?? '';
    $slug = $_GET['slug'] ?? '';
    
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
        
        $result = $api->activateTheme($insId, $slug);
        
        return $result;
    } catch (\Exception $e) {
        return ['error' => 'שגיאה: ' . $e->getMessage()];
    }
}
