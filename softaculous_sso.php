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
    echo '<h2>Softaculous WordPress SSO</h2>';
    echo '<p>המודול פעיל. הלקוחות יכולים לראות את אתרי הוורדפרס שלהם בעמוד פרטי המוצר.</p>';
}
