<?php
/**
 * Service Helper - Get server details from WHMCS service
 */

namespace SoftaculousSso;

use WHMCS\Database\Capsule;

class ServiceHelper
{
    /**
     * Get server details for a hosting service
     */
    public static function getServerDetails($serviceId)
    {
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();

        if (!$service) {
            return null;
        }

        $server = Capsule::table('tblservers')
            ->where('id', $service->server)
            ->first();

        if (!$server) {
            return null;
        }

        // Decrypt password
        $password = self::decryptPassword($service->password);
        $serverPassword = self::decryptPassword($server->password);

        // Determine server type from module
        $serverType = self::detectServerType($server->type);

        return [
            'service_id' => $serviceId,
            'server_id' => $server->id,
            'server_type' => $serverType,
            'server_module' => $server->type,
            'hostname' => $server->hostname ?: $server->ipaddress,
            'ip' => $server->ipaddress,
            'username' => $service->username,
            'password' => $password,
            'server_username' => $server->username,
            'server_password' => $serverPassword,
            'server_accesshash' => $server->accesshash,
            'secure' => $server->secure ? true : false,
            'domain' => $service->domain,
            'server_port' => $server->port ?? null,
        ];
    }

    /**
     * Detect server type from WHMCS module name
     */
    public static function detectServerType($moduleName)
    {
        $moduleName = strtolower($moduleName);

        if (strpos($moduleName, 'cpanel') !== false || 
            strpos($moduleName, 'whm') !== false ||
            $moduleName === 'cpanel') {
            return SoftaculousAPI::TYPE_CPANEL;
        }

        if (strpos($moduleName, 'directadmin') !== false ||
            $moduleName === 'directadmin') {
            return SoftaculousAPI::TYPE_DIRECTADMIN;
        }

        // Default to cPanel if unknown
        return SoftaculousAPI::TYPE_CPANEL;
    }

    /**
     * Decrypt WHMCS password
     */
    private static function decryptPassword($encryptedPassword)
    {
        if (empty($encryptedPassword)) {
            return '';
        }

        try {
            // Try using WHMCS decrypt function directly
            if (function_exists('decrypt')) {
                return decrypt($encryptedPassword);
            }
            
            // Fallback to localAPI
            $result = localAPI('DecryptPassword', ['password2' => $encryptedPassword]);
            if (isset($result['password'])) {
                return $result['password'];
            }
            
            return '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get addon module settings
     */
    public static function getModuleSettings()
    {
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'softaculous_sso')
            ->pluck('value', 'setting')
            ->toArray();

        return [
            'default_port_cpanel' => $settings['default_port_cpanel'] ?? '2083',
            'default_port_directadmin' => $settings['default_port_directadmin'] ?? '2222',
            'custom_port' => $settings['custom_port'] ?? '',
        ];
    }

    /**
     * Get the port to use for connection
     */
    public static function getPort($serverType, $serverDetails = null)
    {
        $settings = self::getModuleSettings();

        // Check if server has a custom port defined in WHMCS
        if ($serverDetails && !empty($serverDetails['server_port'])) {
            return $serverDetails['server_port'];
        }

        // Custom port only applies to DirectAdmin (4564 is DirectAdmin specific)
        if ($serverType === SoftaculousAPI::TYPE_DIRECTADMIN) {
            if (!empty($settings['custom_port'])) {
                return $settings['custom_port'];
            }
            return $settings['default_port_directadmin'];
        }

        // For cPanel, always use the cPanel default port (2083)
        return $settings['default_port_cpanel'];
    }

    /**
     * Get server port from WHMCS server configuration
     */
    public static function getServerPort($serverId)
    {
        $server = Capsule::table('tblservers')
            ->where('id', $serverId)
            ->first();

        if ($server && !empty($server->port)) {
            return $server->port;
        }

        return null;
    }
}
