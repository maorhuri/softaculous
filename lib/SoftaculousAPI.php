<?php
/**
 * Softaculous API Handler
 * Supports cPanel and DirectAdmin
 */

namespace SoftaculousSso;

class SoftaculousAPI
{
    private $serverType;
    private $hostname;
    private $port;
    private $username;
    private $password;
    private $useSSL = true;

    const TYPE_CPANEL = 'cpanel';
    const TYPE_DIRECTADMIN = 'directadmin';

    public function __construct($serverType, $hostname, $port, $username, $password, $useSSL = true)
    {
        $this->serverType = $serverType;
        $this->hostname = $hostname;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->useSSL = $useSSL;
    }

    /**
     * Get base URL for Softaculous based on server type
     */
    private function getBaseUrl()
    {
        $protocol = $this->useSSL ? 'https' : 'http';
        $auth = urlencode($this->username) . ':' . urlencode($this->password);

        if ($this->serverType === self::TYPE_CPANEL) {
            return "{$protocol}://{$auth}@{$this->hostname}:{$this->port}/frontend/jupiter/softaculous/index.live.php";
        } elseif ($this->serverType === self::TYPE_DIRECTADMIN) {
            // DirectAdmin uses CMD_PLUGINS path
            return "{$protocol}://{$auth}@{$this->hostname}:{$this->port}/CMD_PLUGINS/softaculous/index.raw";
        }

        return null;
    }

    /**
     * Get base URL without auth for logging
     */
    private function getBaseUrlForLog()
    {
        $protocol = $this->useSSL ? 'https' : 'http';

        if ($this->serverType === self::TYPE_CPANEL) {
            return "{$protocol}://{$this->hostname}:{$this->port}/frontend/jupiter/softaculous/index.live.php";
        } elseif ($this->serverType === self::TYPE_DIRECTADMIN) {
            return "{$protocol}://{$this->hostname}:{$this->port}/CMD_PLUGINS/softaculous/index.raw";
        }

        return null;
    }

    /**
     * Make API request to Softaculous
     */
    private function request($action, $params = [])
    {
        if ($this->serverType === self::TYPE_DIRECTADMIN) {
            return $this->requestDirectAdmin($action, $params);
        }
        
        return $this->requestCPanel($action, $params);
    }

    /**
     * Make API request for cPanel
     * Try multiple theme paths as cPanel themes can vary
     */
    private function requestCPanel($action, $params = [])
    {
        $protocol = $this->useSSL ? 'https' : 'http';
        
        // Try different cPanel theme paths
        $themePaths = [
            '/frontend/jupiter/softaculous/index.live.php',
            '/frontend/paper_lantern/softaculous/index.live.php',
            '/cpsess0/frontend/jupiter/softaculous/index.live.php',
            '/cpsess0/frontend/paper_lantern/softaculous/index.live.php',
        ];
        
        foreach ($themePaths as $themePath) {
            $url = "{$protocol}://{$this->hostname}:{$this->port}{$themePath}";
            $url .= '?api=json&act=' . $action;
            
            foreach ($params as $key => $value) {
                $url .= '&' . urlencode($key) . '=' . urlencode($value);
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

            $result = $this->executeAndParse($ch, $action);
            
            // If we got a valid response (not 404), return it
            if (!isset($result['error']) || strpos($result['error'], '404') === false) {
                return $result;
            }
        }
        
        // If all paths failed, return the last error
        return ['error' => 'Could not connect to Softaculous on cPanel - tried multiple theme paths'];
    }

    /**
     * Make API request for DirectAdmin
     * DirectAdmin requires session-based login first
     */
    private function requestDirectAdmin($action, $params = [])
    {
        $protocol = $this->useSSL ? 'https' : 'http';
        $baseUrl = "{$protocol}://{$this->hostname}:{$this->port}";
        
        // Create unique cookie file for this session
        $cookieFile = sys_get_temp_dir() . '/softaculous_da_' . md5($this->username . $this->hostname . time()) . '.txt';
        
        // Step 1: Login to DirectAdmin
        $loginUrl = $baseUrl . '/CMD_LOGIN';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'username' => $this->username,
            'password' => $this->password,
            'referer' => '/CMD_PLUGINS/softaculous/index.raw'
        ]));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirect yet
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $loginResponse = curl_exec($ch);
        $loginError = curl_error($ch);
        $loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($loginError) {
            @unlink($cookieFile);
            return ['error' => 'Login cURL Error: ' . $loginError];
        }
        
        // Check if login was successful (should redirect or return 200/302)
        if ($loginHttpCode !== 200 && $loginHttpCode !== 302 && $loginHttpCode !== 301) {
            @unlink($cookieFile);
            return [
                'error' => 'DirectAdmin login failed',
                'debug' => [
                    'login_http_code' => $loginHttpCode,
                    'login_response' => substr($loginResponse, 0, 500)
                ]
            ];
        }
        
        // Step 2: Access Softaculous with session cookie
        $softUrl = $baseUrl . '/CMD_PLUGINS/softaculous/index.raw?api=json&act=' . $action;
        
        foreach ($params as $key => $value) {
            $softUrl .= '&' . urlencode($key) . '=' . urlencode($value);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $softUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        
        $result = $this->executeAndParse($ch, $action);
        
        // Cleanup cookie file
        @unlink($cookieFile);
        
        return $result;
    }

    /**
     * Execute curl and parse response
     */
    private function executeAndParse($ch, $action)
    {
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return ['error' => 'cURL Error: ' . $error];
        }

        if ($httpCode === 401) {
            return ['error' => 'Authentication failed (401) - check username/password'];
        }

        if ($httpCode === 404) {
            return ['error' => 'Softaculous not found (404) - URL: ' . $this->getBaseUrlForLog()];
        }

        if ($httpCode !== 200) {
            return ['error' => 'HTTP Error: ' . $httpCode];
        }

        // Check for DirectAdmin login error
        if (strpos($response, 'Not logged in') !== false || strpos($response, 'Error Code: 1') !== false) {
            return ['error' => 'DirectAdmin login failed - check username/password'];
        }

        // Try to decode as JSON
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Maybe it's serialized PHP?
            $data = @unserialize($response);
            if ($data !== false) {
                return $data;
            }
            
            // Return debug info
            $preview = substr($response, 0, 500);
            return [
                'error' => 'Invalid response format',
                'debug' => [
                    'http_code' => $httpCode,
                    'response_preview' => $preview,
                    'server_type' => $this->serverType,
                    'url' => $this->getBaseUrlForLog() . '?api=json&act=' . $action
                ]
            ];
        }

        return $data;
    }

    /**
     * Get list of WordPress installations
     */
    public function getWordPressInstallations()
    {
        $result = $this->request('installations');

        if (isset($result['error'])) {
            return $result;
        }

        $wpInstallations = [];
        
        // Get installations array
        $installations = $result['installations'] ?? $result;
        
        if (is_array($installations)) {
            // Check for nested structure: installations[scriptId][insId]
            // WordPress script ID is 26
            if (isset($installations['26']) && is_array($installations['26'])) {
                // Nested structure: installations[26][26_xxxxx]
                foreach ($installations['26'] as $insId => $installation) {
                    if (is_array($installation)) {
                        $wpInstallations[] = [
                            'insid' => $insId,
                            'softurl' => $installation['softurl'] ?? '',
                            'softpath' => $installation['softpath'] ?? '',
                            'softversion' => $installation['ver'] ?? '',
                            'admin_username' => $installation['admin'] ?? '',
                            'site_name' => $installation['site_name'] ?? '',
                        ];
                    }
                }
            } else {
                // Flat structure: installations[26_xxxxx]
                foreach ($installations as $insId => $installation) {
                    if (is_string($insId) && strpos($insId, '26_') === 0 && is_array($installation)) {
                        $wpInstallations[] = [
                            'insid' => $insId,
                            'softurl' => $installation['softurl'] ?? '',
                            'softpath' => $installation['softpath'] ?? '',
                            'softversion' => $installation['ver'] ?? '',
                            'admin_username' => $installation['admin'] ?? '',
                            'site_name' => $installation['site_name'] ?? '',
                        ];
                    }
                }
            }
        }

        return ['installations' => $wpInstallations];
    }

    /**
     * Scan for existing installations (import manually installed scripts)
     * This is equivalent to the "Scan" button in Softaculous
     */
    public function scanInstallations()
    {
        // The scan/import action in Softaculous
        $result = $this->request('import');
        
        if (isset($result['error'])) {
            return $result;
        }
        
        return ['success' => true, 'message' => 'הסריקה הושלמה בהצלחה'];
    }

    /**
     * Install WordPress on a domain
     */
    public function installWordPress($domain, $path = '', $siteName = 'אתר וורדפרס חדש')
    {
        // Generate random password and username
        $adminPassword = $this->generatePassword(12);
        $adminUsername = $this->generateUsername();
        $adminEmail = $adminUsername . '@' . $domain;
        $dbSuffix = substr(md5(time()), 0, 5);
        
        // Prepare installation data for Softaculous
        // Note: Database name max 7 chars, only alphanumeric
        $installData = [
            'softsubmit' => '1',
            'soft' => '26', // WordPress software ID
            'softdomain' => $domain,
            'softdirectory' => $path,
            'softdb' => 'wp' . $dbSuffix,
            'dbusername' => 'wp' . $dbSuffix,
            'dbuserpass' => $this->generatePassword(16),
            'hostname' => 'localhost',
            'admin_username' => $adminUsername,
            'admin_pass' => $adminPassword,
            'admin_email' => $adminEmail,
            'site_name' => $siteName,
            'site_desc' => 'אתר וורדפרס',
            'language' => 'he_IL',
            'softproto' => 'https://',
            'eu_auto_upgrade' => '1',
        ];
        
        // Use the install action with soft=26 for WordPress
        $result = $this->requestInstall($installData);
        
        // DEBUG: Log the raw result to understand what Softaculous returns
        error_log('Softaculous installWordPress result: ' . print_r($result, true));
        
        if (isset($result['error'])) {
            return $result;
        }
        
        $siteUrl = 'https://' . $domain . ($path ? '/' . $path : '');
        
        // Check for success indicators
        if (isset($result['done']) || isset($result['__settings']) || isset($result['setup_complete']) || isset($result['setupcontinue'])) {
            return [
                'success' => true,
                'admin_url' => $siteUrl . '/wp-admin',
                'admin_username' => $adminUsername,
                'admin_password' => $adminPassword,
                'site_url' => $siteUrl
            ];
        }
        
        // If we got here but no error, might still be success
        if (!isset($result['error']) && !isset($result['iscripts'])) {
            return [
                'success' => true,
                'admin_url' => $siteUrl . '/wp-admin',
                'admin_username' => $adminUsername,
                'admin_password' => $adminPassword,
                'site_url' => $siteUrl,
                'note' => 'ההתקנה בוצעה'
            ];
        }
        
        // Extract error message from Softaculous response
        $errorMsg = 'התקנה נכשלה';
        if (isset($result['error']) && is_string($result['error'])) {
            $errorMsg = $result['error'];
        } elseif (isset($result['e']) && is_array($result['e'])) {
            // Softaculous returns errors in 'e' array - flatten if needed
            $errors = [];
            foreach ($result['e'] as $err) {
                if (is_string($err)) {
                    $errors[] = $err;
                } elseif (is_array($err)) {
                    $errors[] = implode(', ', array_map(function($v) {
                        return is_string($v) ? $v : json_encode($v);
                    }, $err));
                } else {
                    $errors[] = json_encode($err);
                }
            }
            $errorMsg = implode(', ', $errors);
        } elseif (isset($result['error_msg'])) {
            $errorMsg = is_string($result['error_msg']) ? $result['error_msg'] : json_encode($result['error_msg']);
        } elseif (isset($result['emsg'])) {
            $errorMsg = is_string($result['emsg']) ? $result['emsg'] : json_encode($result['emsg']);
        } elseif (isset($result['iscripts'])) {
            // Got software list instead of install result - installation failed
            $errorMsg = 'התקנה נכשלה - לא התקבלה תגובה מהשרת';
        }
        
        return ['error' => $errorMsg];
    }
    
    /**
     * Make install request to Softaculous
     */
    private function requestInstall($data)
    {
        if ($this->serverType === self::TYPE_DIRECTADMIN) {
            return $this->requestInstallDirectAdmin($data);
        }
        
        return $this->requestInstallCPanel($data);
    }
    
    /**
     * Install request for cPanel
     */
    private function requestInstallCPanel($data)
    {
        $protocol = $this->useSSL ? 'https' : 'http';
        
        $themePaths = [
            '/frontend/jupiter/softaculous/index.live.php',
            '/frontend/paper_lantern/softaculous/index.live.php',
        ];
        
        foreach ($themePaths as $themePath) {
            $url = "{$protocol}://{$this->hostname}:{$this->port}{$themePath}?api=json&act=software&soft=26";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($error) {
                continue;
            }
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        
        return ['error' => 'Failed to connect to Softaculous'];
    }
    
    /**
     * Install request for DirectAdmin
     */
    private function requestInstallDirectAdmin($data)
    {
        $protocol = $this->useSSL ? 'https' : 'http';
        $baseUrl = "{$protocol}://{$this->hostname}:{$this->port}";
        
        // Create temp cookie file
        $cookieFile = sys_get_temp_dir() . '/softaculous_install_' . md5($this->username . time()) . '.txt';
        
        // Step 1: Login to DirectAdmin
        $loginUrl = $baseUrl . '/CMD_LOGIN';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'username' => $this->username,
            'password' => $this->password,
            'referer' => '/CMD_PLUGINS/softaculous/index.raw'
        ]));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        curl_exec($ch);
        curl_close($ch);
        
        // Step 2: Make install request
        $softUrl = $baseUrl . '/CMD_PLUGINS/softaculous/index.raw?api=json&act=software&soft=26';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $softUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        @unlink($cookieFile);
        
        if ($error) {
            return ['error' => 'cURL Error: ' . $error];
        }
        
        $result = json_decode($response, true);
        if ($result === null) {
            return ['error' => 'Invalid JSON response: ' . substr($response, 0, 500)];
        }
        
        return $result;
    }
    
    /**
     * Generate random password
     */
    private function generatePassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    /**
     * Generate random username (never admin)
     */
    private function generateUsername()
    {
        $prefixes = ['wp', 'user', 'site', 'web', 'mgr'];
        $prefix = $prefixes[array_rand($prefixes)];
        $suffix = substr(md5(time() . random_int(1000, 9999)), 0, 6);
        return $prefix . '_' . $suffix;
    }

    /**
     * Delete WordPress installation
     */
    public function deleteInstallation($insId)
    {
        // According to Softaculous API docs: https://www.softaculous.com/docs/api/api/#remove-an-installed-script
        $deleteData = [
            'removeins' => '1',
            'remove_dir' => '1',
            'remove_datadir' => '1', 
            'remove_db' => '1',
            'remove_dbuser' => '1'
        ];
        
        if ($this->serverType === self::TYPE_DIRECTADMIN) {
            $result = $this->requestRemoveDirectAdmin($insId, $deleteData);
        } else {
            $result = $this->requestRemoveCPanel($insId, $deleteData);
        }
        
        if (isset($result['error'])) {
            return $result;
        }
        
        // Check for actual success - done key means deletion completed
        if (isset($result['done'])) {
            return ['success' => true, 'message' => 'ההתקנה נמחקה בהצלחה'];
        }
        
        // Extract error message from Softaculous response
        $errorMsg = 'מחיקה נכשלה';
        if (isset($result['e']) && is_array($result['e'])) {
            $errorMsg = implode(', ', $result['e']);
        } elseif (isset($result['error_msg'])) {
            $errorMsg = $result['error_msg'];
        } elseif (isset($result['emsg'])) {
            $errorMsg = $result['emsg'];
        }
        
        return ['error' => $errorMsg];
    }
    
    /**
     * Remove request for cPanel
     */
    private function requestRemoveCPanel($insId, $data)
    {
        $protocol = $this->useSSL ? 'https' : 'http';
        
        $themePaths = [
            '/frontend/jupiter/softaculous/index.live.php',
            '/frontend/paper_lantern/softaculous/index.live.php',
        ];
        
        foreach ($themePaths as $themePath) {
            // Use act=remove in URL like install uses act=software
            $url = "{$protocol}://{$this->hostname}:{$this->port}{$themePath}?api=json&act=remove&insid=" . urlencode($insId);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($error) {
                continue;
            }
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        
        return ['error' => 'Failed to connect to Softaculous'];
    }
    
    /**
     * Remove request for DirectAdmin
     */
    private function requestRemoveDirectAdmin($insId, $data)
    {
        $protocol = $this->useSSL ? 'https' : 'http';
        $baseUrl = "{$protocol}://{$this->hostname}:{$this->port}";
        
        $cookieFile = sys_get_temp_dir() . '/softaculous_remove_' . md5($this->username . time()) . '.txt';
        
        // Step 1: Login to DirectAdmin
        $loginUrl = $baseUrl . '/CMD_LOGIN';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'username' => $this->username,
            'password' => $this->password,
            'referer' => '/CMD_PLUGINS/softaculous/index.raw'
        ]));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        curl_exec($ch);
        curl_close($ch);
        
        // Step 2: Make remove request
        $softUrl = $baseUrl . '/CMD_PLUGINS/softaculous/index.raw?api=json&act=remove&insid=' . urlencode($insId);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $softUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        @unlink($cookieFile);
        
        if ($error) {
            return ['error' => 'cURL Error: ' . $error];
        }
        
        $result = json_decode($response, true);
        if ($result === null) {
            return ['error' => 'Invalid JSON response: ' . substr($response, 0, 500)];
        }
        
        return $result;
    }

    /**
     * Get SSO URL for WordPress installation
     */
    public function getSignOnUrl($insId)
    {
        // Use the same request method as getWordPressInstallations (with session login for DirectAdmin)
        $result = $this->request('sign_on', ['insid' => $insId]);

        if (isset($result['error'])) {
            return $result;
        }

        if (isset($result['sign_on_url'])) {
            return ['sign_on_url' => $result['sign_on_url']];
        }

        return ['error' => 'Could not get sign on URL', 'response' => $result];
    }

    /**
     * Get latest WordPress version to check if update is needed
     */
    public function getLatestWordPressVersion()
    {
        $result = $this->request('software', ['soft' => '26']);
        
        if (isset($result['error'])) {
            return null;
        }

        return $result['ver'] ?? $result['version'] ?? null;
    }

    /**
     * Check if installation needs update
     */
    public function checkForUpdates($insId)
    {
        $result = $this->request('installations', ['showupdates' => 'true']);
        
        if (isset($result['error'])) {
            return $result;
        }

        // Check if this installation is in the outdated list
        $installations = $result['installations'] ?? $result;
        
        if (isset($installations['26'][$insId])) {
            $inst = $installations['26'][$insId];
            return [
                'needs_update' => true,
                'current_version' => $inst['ver'] ?? '',
                'latest_version' => $inst['latest_ver'] ?? $inst['uver'] ?? ''
            ];
        }

        return ['needs_update' => false];
    }

    /**
     * Clone/Staging an installation
     * @param string $insId Installation ID
     * @param string $domain Target domain
     * @param string $directory Target directory (empty for root)
     * @param string $dbName Database name for clone
     * @param bool $staging If true, creates staging; if false, creates clone
     */
    public function cloneInstallation($insId, $domain, $directory = '', $dbName = '', $staging = false)
    {
        $action = $staging ? 'staging' : 'sclone';
        
        $params = [
            'insid' => $insId,
            'softsubmit' => '1',
            'softdomain' => $domain,
            'softdirectory' => $directory,
        ];

        if (!empty($dbName)) {
            $params['softdb'] = $dbName;
        }

        $result = $this->request($action, $params);

        if (isset($result['error'])) {
            return $result;
        }

        if (isset($result['done']) || isset($result['__settings']['softurl'])) {
            return [
                'success' => true,
                'url' => $result['__settings']['softurl'] ?? '',
                'message' => $staging ? 'Staging נוצר בהצלחה' : 'האתר שוכפל בהצלחה'
            ];
        }

        return ['error' => 'Clone failed', 'response' => $result];
    }

    /**
     * Set auto-upgrade settings for an installation
     * @param string $insId Installation ID
     * @param bool $autoUpgradeCore Auto upgrade WordPress core
     * @param bool $autoUpgradePlugins Auto upgrade plugins
     * @param bool $autoUpgradeThemes Auto upgrade themes
     */
    public function setAutoUpgrade($insId, $autoUpgradeCore = true, $autoUpgradePlugins = true, $autoUpgradeThemes = true)
    {
        $params = [
            'insid' => $insId,
            'save' => '1',
            'auto_upgrade_core' => $autoUpgradeCore ? '1' : '0',
            'auto_upgrade_plugins' => $autoUpgradePlugins ? '1' : '0',
            'auto_upgrade_themes' => $autoUpgradeThemes ? '1' : '0',
        ];

        $result = $this->request('wordpress', $params);

        if (isset($result['error'])) {
            return $result;
        }

        if (isset($result['done'])) {
            return [
                'success' => true,
                'message' => 'הגדרות עדכון אוטומטי נשמרו בהצלחה'
            ];
        }

        return ['error' => 'Failed to save settings', 'response' => $result];
    }

    /**
     * Get auto-upgrade settings for an installation
     */
    public function getAutoUpgradeSettings($insId)
    {
        $result = $this->request('wordpress');

        if (isset($result['error'])) {
            return $result;
        }

        // Find the installation in the response
        $installations = $result['installations'] ?? $result;
        
        if (isset($installations['26'][$insId])) {
            $inst = $installations['26'][$insId];
            return [
                'auto_upgrade_core' => !empty($inst['auto_upgrade_core']),
                'auto_upgrade_plugins' => !empty($inst['auto_upgrade_plugins']),
                'auto_upgrade_themes' => !empty($inst['auto_upgrade_themes']),
            ];
        }

        // Try flat structure
        if (isset($installations[$insId])) {
            $inst = $installations[$insId];
            return [
                'auto_upgrade_core' => !empty($inst['auto_upgrade_core']),
                'auto_upgrade_plugins' => !empty($inst['auto_upgrade_plugins']),
                'auto_upgrade_themes' => !empty($inst['auto_upgrade_themes']),
            ];
        }

        return [
            'auto_upgrade_core' => false,
            'auto_upgrade_plugins' => false,
            'auto_upgrade_themes' => false,
        ];
    }

    /**
     * Upgrade WordPress installation to latest version
     */
    public function upgradeInstallation($insId)
    {
        $result = $this->request('upgrade', [
            'insid' => $insId,
            'softsubmit' => '1'
        ]);

        if (isset($result['error'])) {
            return $result;
        }

        if (isset($result['done']) || isset($result['__settings'])) {
            return [
                'success' => true,
                'message' => 'וורדפרס עודכן בהצלחה',
                'url' => $result['__settings']['softurl'] ?? ''
            ];
        }

        if (isset($result['setupcontinue'])) {
            return [
                'success' => true,
                'message' => 'יש להשלים את העדכון',
                'continue_url' => $result['setupcontinue']
            ];
        }

        return ['error' => 'Upgrade failed', 'response' => $result];
    }

    /**
     * Get list of installed plugins
     */
    public function getPlugins($insId)
    {
        $result = $this->requestPost('wordpress', [
            'insid' => $insId,
            'type' => 'plugins',
            'list' => '1'
        ]);

        if (isset($result['error'])) {
            return $result;
        }

        // Try to find plugins in different response structures
        $plugins = [];
        if (isset($result['plugins'])) {
            $plugins = $result['plugins'];
        } elseif (isset($result['data']['plugins'])) {
            $plugins = $result['data']['plugins'];
        } elseif (isset($result['installed_plugins'])) {
            $plugins = $result['installed_plugins'];
        } else {
            // Maybe the result itself is the plugins array
            $plugins = $result;
        }

        return ['plugins' => $plugins, 'raw_keys' => array_keys($result)];
    }

    /**
     * Get list of installed themes
     */
    public function getThemes($insId)
    {
        $result = $this->requestPost('wordpress', [
            'insid' => $insId,
            'type' => 'themes',
            'list' => '1'
        ]);

        if (isset($result['error'])) {
            return $result;
        }

        // Return the full result - let JavaScript handle parsing
        // Add raw_keys for debugging
        $result['raw_keys'] = array_keys($result);
        return $result;
    }

    /**
     * Activate a plugin
     */
    public function activatePlugin($insId, $slug)
    {
        $result = $this->requestPost('wordpress', [
            'insid' => $insId,
            'type' => 'plugins',
            'slug' => $slug,
            'activate' => '1'
        ]);

        if (isset($result['error'])) {
            return $result;
        }

        if (isset($result['done'])) {
            return ['success' => true, 'message' => 'התוסף הופעל בהצלחה'];
        }

        return ['error' => 'Failed to activate plugin', 'response' => $result];
    }

    /**
     * Deactivate a plugin
     */
    public function deactivatePlugin($insId, $slug)
    {
        $result = $this->requestPost('wordpress', [
            'insid' => $insId,
            'type' => 'plugins',
            'slug' => $slug,
            'deactivate' => '1'
        ]);

        if (isset($result['error'])) {
            return $result;
        }

        if (isset($result['done'])) {
            return ['success' => true, 'message' => 'התוסף כובה בהצלחה'];
        }

        return ['error' => 'Failed to deactivate plugin', 'response' => $result];
    }

    /**
     * Delete a plugin
     */
    public function deletePlugin($insId, $slug)
    {
        $result = $this->requestPost('wordpress', [
            'insid' => $insId,
            'type' => 'plugins',
            'slug' => $slug,
            'delete' => '1'
        ]);

        if (isset($result['error'])) {
            return $result;
        }

        if (isset($result['done'])) {
            return ['success' => true, 'message' => 'התוסף נמחק בהצלחה'];
        }

        return ['error' => 'Failed to delete plugin', 'response' => $result];
    }

    /**
     * Upload a plugin from ZIP file
     */
    public function uploadPlugin($insId, $zipFilePath, $zipFileName)
    {
        if ($this->serverType === self::TYPE_DIRECTADMIN) {
            return $this->uploadPluginDirectAdmin($insId, $zipFilePath, $zipFileName);
        }
        
        return $this->uploadPluginCPanel($insId, $zipFilePath, $zipFileName);
    }

    /**
     * Upload plugin for cPanel
     */
    private function uploadPluginCPanel($insId, $zipFilePath, $zipFileName)
    {
        $protocol = $this->useSSL ? 'https' : 'http';
        
        $themePaths = [
            '/frontend/jupiter/softaculous/index.live.php',
            '/frontend/paper_lantern/softaculous/index.live.php',
        ];
        
        foreach ($themePaths as $themePath) {
            $url = "{$protocol}://{$this->hostname}:{$this->port}{$themePath}?api=json&act=wordpress&upload=1";
            
            $postFields = [
                'insid' => $insId,
                'type' => 'plugins',
                'activate' => '1',
                'custom_file' => new \CURLFile($zipFilePath, 'application/zip', $zipFileName)
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($error) {
                continue;
            }
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if ($result !== null) {
                    if (isset($result['done'])) {
                        return ['success' => true, 'message' => 'התוסף הועלה בהצלחה'];
                    }
                    return $result;
                }
            }
        }
        
        return ['error' => 'Failed to upload plugin'];
    }

    /**
     * Upload plugin for DirectAdmin
     */
    private function uploadPluginDirectAdmin($insId, $zipFilePath, $zipFileName)
    {
        $protocol = $this->useSSL ? 'https' : 'http';
        $baseUrl = "{$protocol}://{$this->hostname}:{$this->port}";
        
        $cookieFile = sys_get_temp_dir() . '/softaculous_upload_' . md5($this->username . time()) . '.txt';
        
        // Step 1: Login to DirectAdmin
        $loginUrl = $baseUrl . '/CMD_LOGIN';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'username' => $this->username,
            'password' => $this->password,
            'referer' => '/CMD_PLUGINS/softaculous/index.raw'
        ]));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        curl_exec($ch);
        curl_close($ch);
        
        // Step 2: Upload plugin
        $softUrl = $baseUrl . '/CMD_PLUGINS/softaculous/index.raw?api=json&act=wordpress&upload=1';
        
        $postFields = [
            'insid' => $insId,
            'type' => 'plugins',
            'activate' => '1',
            'custom_file' => new \CURLFile($zipFilePath, 'application/zip', $zipFileName)
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $softUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        @unlink($cookieFile);
        
        if ($error) {
            return ['error' => 'cURL Error: ' . $error];
        }
        
        $result = json_decode($response, true);
        if ($result === null) {
            return ['error' => 'Invalid JSON response'];
        }
        
        if (isset($result['done'])) {
            return ['success' => true, 'message' => 'התוסף הועלה בהצלחה'];
        }
        
        return $result;
    }

    /**
     * Activate a theme
     */
    public function activateTheme($insId, $slug)
    {
        $result = $this->requestPost('wordpress', [
            'insid' => $insId,
            'type' => 'themes',
            'slug' => $slug,
            'activate' => '1'
        ]);

        if (isset($result['error'])) {
            return $result;
        }

        if (isset($result['done'])) {
            return ['success' => true, 'message' => 'התבנית הופעלה בהצלחה'];
        }

        return ['error' => 'Failed to activate theme', 'response' => $result];
    }

    /**
     * Delete a theme
     */
    public function deleteTheme($insId, $slug)
    {
        $result = $this->requestPost('wordpress', [
            'insid' => $insId,
            'type' => 'themes',
            'slug' => $slug,
            'delete' => '1'
        ]);

        if (isset($result['error'])) {
            return $result;
        }

        if (isset($result['done'])) {
            return ['success' => true, 'message' => 'התבנית נמחקה בהצלחה'];
        }

        return ['error' => 'Failed to delete theme', 'response' => $result];
    }

    /**
     * Make POST request (for plugins/themes management)
     */
    private function requestPost($action, $params = [])
    {
        if ($this->serverType === self::TYPE_DIRECTADMIN) {
            return $this->requestDirectAdminPost($action, $params);
        }
        
        return $this->requestCPanelPost($action, $params);
    }

    /**
     * POST request for cPanel
     */
    private function requestCPanelPost($action, $params = [])
    {
        $protocol = $this->useSSL ? 'https' : 'http';
        
        $themePaths = [
            '/frontend/jupiter/softaculous/index.live.php',
            '/frontend/paper_lantern/softaculous/index.live.php',
        ];
        
        foreach ($themePaths as $themePath) {
            $url = "{$protocol}://{$this->hostname}:{$this->port}{$themePath}?api=json&act={$action}";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

            $result = $this->executeAndParse($ch, $action);
            
            if (!isset($result['error']) || strpos($result['error'], '404') === false) {
                return $result;
            }
        }
        
        return ['error' => 'Could not connect to Softaculous'];
    }

    /**
     * POST request for DirectAdmin
     */
    private function requestDirectAdminPost($action, $params = [])
    {
        $protocol = $this->useSSL ? 'https' : 'http';
        $baseUrl = "{$protocol}://{$this->hostname}:{$this->port}";
        
        $cookieFile = sys_get_temp_dir() . '/softaculous_da_' . md5($this->username . $this->hostname . time()) . '.txt';
        
        // Login first
        $loginUrl = $baseUrl . '/CMD_LOGIN';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'username' => $this->username,
            'password' => $this->password,
            'referer' => '/CMD_PLUGINS/softaculous/index.raw'
        ]));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $loginResponse = curl_exec($ch);
        $loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($loginHttpCode !== 200 && $loginHttpCode !== 302 && $loginHttpCode !== 301) {
            @unlink($cookieFile);
            return ['error' => 'DirectAdmin login failed'];
        }
        
        // Make POST request
        $softUrl = $baseUrl . '/CMD_PLUGINS/softaculous/index.raw?api=json&act=' . $action;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $softUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        
        $result = $this->executeAndParse($ch, $action);
        
        @unlink($cookieFile);
        
        return $result;
    }
}
