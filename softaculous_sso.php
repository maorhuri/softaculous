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
    
    if ($action === 'admin_sso') {
        softaculous_sso_admin_sso();
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
        
        $ssoUrl = $api->getWordPressSSOUrl($insId);
        
        if (isset($ssoUrl['error'])) {
            die('שגיאה: ' . $ssoUrl['error']);
        }
        
        if (!empty($ssoUrl['url'])) {
            header('Location: ' . $ssoUrl['url']);
            exit;
        }
        
        die('לא ניתן ליצור קישור התחברות');
    } catch (\Exception $e) {
        die('שגיאה: ' . $e->getMessage());
    }
}
