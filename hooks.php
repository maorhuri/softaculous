<?php
/**
 * Softaculous SSO Hooks
 * Adds WordPress installations table to product details page
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once __DIR__ . '/lib/SoftaculousAPI.php';
require_once __DIR__ . '/lib/ServiceHelper.php';
require_once __DIR__ . '/controllers/client.php';

/**
 * Add WordPress SSO widget to client area product details
 */
add_hook('ClientAreaPageProductDetails', 1, function($vars) {
    $serviceId = $vars['serviceid'] ?? null;
    
    if (!$serviceId) {
        return [];
    }

    // Check if service is active
    $service = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->first();

    if (!$service || $service->domainstatus !== 'Active') {
        return [];
    }

    return [
        'softaculous_sso_enabled' => true,
        'softaculous_sso_service_id' => $serviceId,
    ];
});

/**
 * Add custom CSS and JS for the widget
 */
add_hook('ClientAreaHeadOutput', 1, function($vars) {
    if ($vars['filename'] !== 'clientarea' || 
        ($vars['action'] ?? '') !== 'productdetails') {
        return '';
    }

    return <<<HTML
<style>
.softaculous-sso-widget {
    margin: 20px auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
    width: 100%;
    max-width: 100%;
}
.softaculous-sso-widget .widget-header {
    background: #bd417b;
    color: #fff;
    padding: 15px 20px;
    font-size: 16px;
    font-weight: 600;
    text-align: center;
}
.softaculous-sso-widget .widget-header i {
    margin-left: 8px;
}
.softaculous-sso-widget .widget-body {
    padding: 0;
}
.softaculous-sso-table {
    width: 100%;
    border-collapse: collapse;
}
.softaculous-sso-table th,
.softaculous-sso-table td {
    padding: 15px 20px;
    text-align: right;
    border-bottom: 1px solid #eee;
}
.softaculous-sso-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
}
.softaculous-sso-table tr:last-child td {
    border-bottom: none;
}
.softaculous-sso-table tr:hover {
    background: #f8f9fa;
}
.btn-sso {
    background: #bd417b;
    color: #fff !important;
    padding: 10px 20px;
    border-radius: 4px;
    text-decoration: none !important;
    display: inline-block;
    font-size: 14px;
    transition: all 0.2s;
    margin: 3px;
}
.btn-sso:hover {
    background: #a03668;
    transform: translateY(-1px);
}
.btn-sso i {
    margin-left: 5px;
}
.btn-sso-green {
    background: #bd417b;
}
.btn-sso-green:hover {
    background: #a03668;
}
.softaculous-sso-loading {
    text-align: center;
    padding: 40px;
    color: #666;
}
.softaculous-sso-loading i {
    font-size: 24px;
    margin-bottom: 10px;
    display: block;
}
.softaculous-sso-empty {
    text-align: center;
    padding: 40px;
    color: #999;
}
.softaculous-sso-error {
    text-align: center;
    padding: 20px;
    color: #dc3545;
    background: #fff5f5;
}
.wp-version-badge {
    background: #28a745;
    color: #fff;
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
}
.softaculous-sso-actions {
    white-space: nowrap;
}
.softaculous-sso-success {
    text-align: center;
    padding: 20px;
    color: #28a745;
}
.softaculous-sso-success i {
    font-size: 48px;
    margin-bottom: 15px;
    display: block;
}
.wp-version-outdated {
    background: #dc3545 !important;
}
.wp-version-outdated i {
    margin-right: 5px;
}

/* Modal Styles */
.softaculous-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.softaculous-modal {
    background: #fff;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}
.softaculous-modal-header {
    background: #bd417b;
    color: #fff;
    padding: 15px 20px;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.softaculous-modal-header h3 {
    margin: 0;
    font-size: 18px;
}
.softaculous-modal-header h3 i {
    margin-left: 8px;
}
.softaculous-modal-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}
.softaculous-modal-body {
    padding: 20px;
}
.softaculous-modal-body .form-group {
    margin-bottom: 15px;
}
.softaculous-modal-body label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}
.softaculous-modal-body .form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}
.softaculous-modal-body .form-control:focus {
    border-color: #bd417b;
    outline: none;
}
.softaculous-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #eee;
    text-align: left;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.auto-upgrade-settings .form-check {
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.auto-upgrade-settings .form-check input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}
.auto-upgrade-settings .form-check label {
    margin: 0;
    cursor: pointer;
    font-weight: normal;
}

/* Progress Bar */
.clone-progress {
    text-align: center;
    padding: 20px;
}
.clone-progress p {
    margin-bottom: 15px;
    font-size: 16px;
    color: #333;
}
.clone-progress i {
    margin-left: 8px;
    color: #bd417b;
}
.progress-bar-container {
    width: 100%;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 15px;
}
.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #bd417b, #e91e8c);
    width: 0%;
    transition: width 0.3s ease;
    border-radius: 10px;
}
.progress-text {
    font-size: 14px;
    color: #666;
}

/* Status Badges */
.badge-active {
    background: #28a745;
    color: #fff;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
}
.badge-inactive {
    background: #6c757d;
    color: #fff;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
}
.btn-sso.btn-sm {
    padding: 5px 12px;
    font-size: 12px;
}
.btn-sso.btn-danger {
    background: #dc3545;
    border-color: #dc3545;
}
.btn-sso.btn-danger:hover {
    background: #c82333;
    border-color: #bd2130;
}

/* Modal Header Centered White Text */
.softaculous-modal-header h3 {
    color: #fff;
    text-align: center;
    flex: 1;
    margin: 0;
}
.softaculous-modal-header {
    justify-content: space-between;
}

/* Search Box */
.plugins-search-box {
    margin-bottom: 15px;
}
.plugins-search-box input {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
}
.plugins-search-box input:focus {
    outline: none;
    border-color: #bd417b;
}

/* Pagination */
.plugins-pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 15px;
}
.plugins-pagination button {
    padding: 8px 14px;
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 5px;
    cursor: pointer;
    font-size: 13px;
}
.plugins-pagination button:hover {
    background: #f5f5f5;
}
.plugins-pagination button.active {
    background: #bd417b;
    color: #fff;
    border-color: #bd417b;
}
.plugins-pagination button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Actions Menu */
.actions-menu {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.action-item {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-radius: 8px;
    color: #333;
    text-decoration: none;
    transition: all 0.2s;
    border: 1px solid #eee;
}
.action-item:hover {
    background: #bd417b;
    color: #fff;
    border-color: #bd417b;
}
.action-item:hover i {
    color: #fff;
}
.action-item i {
    font-size: 18px;
    margin-left: 15px;
    color: #bd417b;
    width: 24px;
}
.action-item span {
    font-size: 15px;
    font-weight: 500;
}
</style>
HTML;
});

/**
 * Add WordPress SSO widget HTML to client area
 */
add_hook('ClientAreaFooterOutput', 1, function($vars) {
    if ($vars['filename'] !== 'clientarea' || 
        ($vars['action'] ?? '') !== 'productdetails') {
        return '';
    }

    $serviceId = $vars['serviceid'] ?? null;
    if (!$serviceId) {
        return '';
    }

    // Check if service is active
    $service = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->first();

    if (!$service || $service->domainstatus !== 'Active') {
        return '';
    }

    $token = generate_token('plain');

    return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Find the right place to insert the widget
    var targetElement = document.querySelector('.panel-sidebar') || 
                        document.querySelector('.client-home-panels') ||
                        document.querySelector('#Primary_Sidebar-Product_Details_Actions_702');
    
    // Try to find after active addons or product details
    var mainContent = document.querySelector('.main-content') || 
                      document.querySelector('#main-body') ||
                      document.querySelector('.content-padded');
    
    if (!mainContent) {
        mainContent = document.querySelector('.container-fluid') || document.body;
    }

    // Create widget container
    var widgetHtml = `
        <div class="softaculous-sso-widget" id="softaculous-sso-widget">
            <div class="widget-header">
                <i class="fab fa-wordpress"></i>
                אתרי וורדפרס
            </div>
            <div class="widget-body">
                <div class="softaculous-sso-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    טוען אתרי וורדפרס...
                </div>
            </div>
        </div>
    `;

    // Find the product-details div and insert AFTER it
    var productDetails = document.querySelector('.product-details');
    
    if (productDetails) {
        // Insert AFTER the product-details div (after the pink card and stats)
        productDetails.insertAdjacentHTML('afterend', widgetHtml);
    } else {
        // Fallback for DirectAdmin template
        var productDetailsPanel = document.querySelector('.panel-product-details') || 
                                  document.querySelector('.panel.panel-default');
        if (productDetailsPanel) {
            productDetailsPanel.insertAdjacentHTML('beforebegin', widgetHtml);
        } else if (mainContent) {
            mainContent.insertAdjacentHTML('beforeend', widgetHtml);
        }
    }

    // Load WordPress installations
    loadWordPressInstallations();
});

function loadWordPressInstallations() {
    var serviceId = {$serviceId};
    
    fetch('clientarea.php?action=productdetails&id=' + serviceId + '&modop=custom&a=softaculous_sso_get_installations&token={$token}', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        var widgetBody = document.querySelector('#softaculous-sso-widget .widget-body');
        
        if (data.error) {
            widgetBody.innerHTML = '<div class="softaculous-sso-error"><i class="fas fa-exclamation-circle"></i> ' + data.error + '</div>';
            return;
        }

        if (!data.installations || data.installations.length === 0) {
            var debugMsg = '';
            if (data._debug) {
                debugMsg = '<br><small style="color:#999;">Debug: ' + JSON.stringify(data._debug) + '</small>';
            }
            widgetBody.innerHTML = '<div class="softaculous-sso-empty"><i class="fab fa-wordpress" style="font-size:48px;margin-bottom:15px;display:block;opacity:0.3;"></i>לא נמצאו אתרי וורדפרס' + debugMsg + '</div>';
            return;
        }

        var tableHtml = `
            <table class="softaculous-sso-table">
                <thead>
                    <tr>
                        <th>כתובת האתר</th>
                        <th>גירסת וורדפרס</th>
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.installations.forEach(function(install) {
            var versionClass = 'wp-version-badge';
            var updateWarning = '';
            
            tableHtml += '<tr>';
            tableHtml += '<td><a href="' + install.softurl + '" target="_blank">' + install.softurl + '</a></td>';
            tableHtml += '<td><span class="' + versionClass + '">' + install.softversion + '</span>' + updateWarning + '</td>';
            tableHtml += '<td class="softaculous-sso-actions">';
            tableHtml += '<a href="' + install.softurl + '" target="_blank" class="btn-sso"><i class="fas fa-external-link-alt"></i> צפייה</a>';
            tableHtml += '<a href="clientarea.php?action=productdetails&id=' + serviceId + '&modop=custom&a=softaculous_sso_login&insid=' + encodeURIComponent(install.insid) + '&token={$token}" class="btn-sso" target="_blank"><i class="fas fa-sign-in-alt"></i> התחברות לאתר</a>';
            tableHtml += '<button class="btn-sso" onclick="openActionsModal(\'' + install.insid + '\', \'' + install.softurl + '\')"><i class="fas fa-cog"></i> פעולות ניהול</button>';
            tableHtml += '</td>';
            tableHtml += '</tr>';
        });

        tableHtml += '</tbody></table>';
        widgetBody.innerHTML = tableHtml;
        
        // Check for updates for each installation
        checkForUpdates(data.installations);
    })
    .catch(error => {
        var widgetBody = document.querySelector('#softaculous-sso-widget .widget-body');
        widgetBody.innerHTML = '<div class="softaculous-sso-error"><i class="fas fa-exclamation-circle"></i> שגיאה בטעינת הנתונים</div>';
        console.error('Softaculous SSO Error:', error);
    });
}

// Check for updates
function checkForUpdates(installations) {
    // Latest WordPress version (will be updated dynamically)
    var latestVersion = '6.8.3';
    
    installations.forEach(function(install) {
        if (install.softversion && install.softversion < latestVersion) {
            var badge = document.querySelector('span.wp-version-badge');
            if (badge && badge.textContent === install.softversion) {
                badge.classList.add('wp-version-outdated');
                badge.innerHTML += ' <i class="fas fa-exclamation-triangle" title="יש עדכון זמין"></i>';
            }
        }
    });
}

// Clone Modal
function openCloneModal(insId, sourceUrl) {
    var modalHtml = `
        <div class="softaculous-modal-overlay" id="clone-modal">
            <div class="softaculous-modal">
                <div class="softaculous-modal-header">
                    <h3><i class="fas fa-clone"></i> שכפול אתר</h3>
                    <button class="softaculous-modal-close" onclick="closeModal('clone-modal')">&times;</button>
                </div>
                <div class="softaculous-modal-body" id="clone-modal-body">
                    <p>שכפול האתר: <strong>` + sourceUrl + `</strong></p>
                    <div class="form-group">
                        <label>דומיין יעד:</label>
                        <input type="text" id="clone-domain" class="form-control" placeholder="example.com" value="">
                    </div>
                    <div class="form-group">
                        <label>תיקייה (השאר ריק לשורש):</label>
                        <input type="text" id="clone-directory" class="form-control" placeholder="staging">
                    </div>
                </div>
                <div class="softaculous-modal-footer" id="clone-modal-footer">
                    <button class="btn-sso" onclick="closeModal('clone-modal')">ביטול</button>
                    <button class="btn-sso" onclick="executeClone('` + insId + `')"><i class="fas fa-clone"></i> שכפל עכשיו</button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function executeClone(insId) {
    var domain = document.getElementById('clone-domain').value;
    var directory = document.getElementById('clone-directory').value;
    
    if (!domain) {
        alert('יש להזין דומיין יעד');
        return;
    }
    
    var formData = new FormData();
    formData.append('insid', insId);
    formData.append('domain', domain);
    formData.append('directory', directory);
    
    // Show progress bar
    document.getElementById('clone-modal-body').innerHTML = `
        <div class="clone-progress">
            <p><i class="fas fa-clone"></i> משכפל את האתר...</p>
            <div class="progress-bar-container">
                <div class="progress-bar" id="clone-progress-bar"></div>
            </div>
            <p class="progress-text" id="clone-progress-text">מתחיל שכפול...</p>
        </div>
    `;
    document.getElementById('clone-modal-footer').style.display = 'none';
    
    // Animate progress bar
    var progress = 0;
    var progressBar = document.getElementById('clone-progress-bar');
    var progressText = document.getElementById('clone-progress-text');
    var progressMessages = [
        'מעתיק קבצים...',
        'מעתיק מסד נתונים...',
        'מעדכן הגדרות...',
        'מסיים...'
    ];
    var messageIndex = 0;
    
    var progressInterval = setInterval(function() {
        if (progress < 90) {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            progressBar.style.width = progress + '%';
            
            if (progress > (messageIndex + 1) * 22 && messageIndex < progressMessages.length - 1) {
                messageIndex++;
                progressText.textContent = progressMessages[messageIndex];
            }
        }
    }, 500);
    
    fetch('clientarea.php?action=productdetails&id={$serviceId}&modop=custom&a=softaculous_sso_clone&token={$token}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        clearInterval(progressInterval);
        progressBar.style.width = '100%';
        
        setTimeout(function() {
            if (data.success) {
                document.getElementById('clone-modal-body').innerHTML = '<div class="softaculous-sso-success"><i class="fas fa-check-circle"></i><br>' + data.message + '<br><a href="' + data.url + '" target="_blank">' + data.url + '</a></div>';
                document.getElementById('clone-modal-footer').innerHTML = '<button class="btn-sso" onclick="location.reload()">סגור</button>';
                document.getElementById('clone-modal-footer').style.display = 'flex';
            } else {
                document.getElementById('clone-modal-body').innerHTML = '<div class="softaculous-sso-error"><i class="fas fa-times-circle"></i><br>שגיאה: ' + (data.error || 'שגיאה לא ידועה') + '</div>';
                document.getElementById('clone-modal-footer').innerHTML = '<button class="btn-sso" onclick="closeModal(\'clone-modal\')">סגור</button>';
                document.getElementById('clone-modal-footer').style.display = 'flex';
            }
        }, 500);
    })
    .catch(error => {
        clearInterval(progressInterval);
        document.getElementById('clone-modal-body').innerHTML = '<div class="softaculous-sso-error"><i class="fas fa-times-circle"></i><br>שגיאה בשכפול</div>';
        document.getElementById('clone-modal-footer').innerHTML = '<button class="btn-sso" onclick="closeModal(\'clone-modal\')">סגור</button>';
        document.getElementById('clone-modal-footer').style.display = 'flex';
    });
}

// Auto Upgrade Modal
function openAutoUpgradeModal(insId) {
    var modalHtml = `
        <div class="softaculous-modal-overlay" id="autoupgrade-modal">
            <div class="softaculous-modal">
                <div class="softaculous-modal-header">
                    <h3><i class="fas fa-sync-alt"></i> הגדרות עדכון אוטומטי</h3>
                    <button class="softaculous-modal-close" onclick="closeModal('autoupgrade-modal')">&times;</button>
                </div>
                <div class="softaculous-modal-body">
                    <div class="softaculous-sso-loading"><i class="fas fa-spinner fa-spin"></i><br>טוען הגדרות...</div>
                </div>
                <div class="softaculous-modal-footer">
                    <button class="btn-sso" onclick="closeModal('autoupgrade-modal')">סגור</button>
                    <button class="btn-sso" id="save-autoupgrade-btn" onclick="saveAutoUpgrade('` + insId + `')" style="display:none;"><i class="fas fa-save"></i> שמור הגדרות</button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Load current settings
    fetch('clientarea.php?action=productdetails&id={$serviceId}&modop=custom&a=softaculous_sso_get_auto_upgrade&insid=' + insId + '&token={$token}')
    .then(response => response.json())
    .then(data => {
        var settingsHtml = `
            <div class="auto-upgrade-settings">
                <div class="form-check">
                    <input type="checkbox" id="auto-core" ` + (data.auto_upgrade_core ? 'checked' : '') + `>
                    <label for="auto-core">עדכון אוטומטי של ליבת וורדפרס</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" id="auto-plugins" ` + (data.auto_upgrade_plugins ? 'checked' : '') + `>
                    <label for="auto-plugins">עדכון אוטומטי של תוספים</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" id="auto-themes" ` + (data.auto_upgrade_themes ? 'checked' : '') + `>
                    <label for="auto-themes">עדכון אוטומטי של תבניות</label>
                </div>
            </div>
        `;
        document.querySelector('#autoupgrade-modal .softaculous-modal-body').innerHTML = settingsHtml;
        document.getElementById('save-autoupgrade-btn').style.display = 'inline-block';
    })
    .catch(error => {
        document.querySelector('#autoupgrade-modal .softaculous-modal-body').innerHTML = '<div class="softaculous-sso-error">שגיאה בטעינת ההגדרות</div>';
    });
}

function saveAutoUpgrade(insId) {
    var autoCore = document.getElementById('auto-core').checked ? '1' : '0';
    var autoPlugins = document.getElementById('auto-plugins').checked ? '1' : '0';
    var autoThemes = document.getElementById('auto-themes').checked ? '1' : '0';
    
    var formData = new FormData();
    formData.append('insid', insId);
    formData.append('auto_core', autoCore);
    formData.append('auto_plugins', autoPlugins);
    formData.append('auto_themes', autoThemes);
    
    document.querySelector('#autoupgrade-modal .softaculous-modal-body').innerHTML = '<div class="softaculous-sso-loading"><i class="fas fa-spinner fa-spin"></i><br>שומר הגדרות...</div>';
    
    fetch('clientarea.php?action=productdetails&id={$serviceId}&modop=custom&a=softaculous_sso_set_auto_upgrade&token={$token}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelector('#autoupgrade-modal .softaculous-modal-body').innerHTML = '<div class="softaculous-sso-success"><i class="fas fa-check-circle"></i><br>' + data.message + '</div>';
            setTimeout(function() { closeModal('autoupgrade-modal'); }, 2000);
        } else {
            document.querySelector('#autoupgrade-modal .softaculous-modal-body').innerHTML = '<div class="softaculous-sso-error"><i class="fas fa-times-circle"></i><br>שגיאה: ' + (data.error || 'שגיאה לא ידועה') + '</div>';
        }
    })
    .catch(error => {
        document.querySelector('#autoupgrade-modal .softaculous-modal-body').innerHTML = '<div class="softaculous-sso-error">שגיאה בשמירת ההגדרות</div>';
    });
}

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.remove();
    }
}

// Actions Modal
function openActionsModal(insId, siteUrl) {
    var modalHtml = `
        <div class="softaculous-modal-overlay" id="actions-modal">
            <div class="softaculous-modal" style="max-width:400px;">
                <div class="softaculous-modal-header">
                    <h3><i class="fas fa-cog"></i> פעולות ניהול</h3>
                    <button class="softaculous-modal-close" onclick="closeModal('actions-modal')">&times;</button>
                </div>
                <div class="softaculous-modal-body">
                    <div class="actions-menu">
                        <a href="#" class="action-item disabled" onclick="return false;" style="opacity:0.5;cursor:not-allowed;">
                            <i class="fas fa-sync-alt"></i>
                            <span>ניהול עדכונים - בקרוב!</span>
                        </a>
                        <a href="#" class="action-item disabled" onclick="return false;" style="opacity:0.5;cursor:not-allowed;">
                            <i class="fas fa-clone"></i>
                            <span>שכפול סביבה - בקרוב!</span>
                        </a>
                        <a href="#" class="action-item" onclick="closeModal('actions-modal'); openPluginsModal('` + insId + `'); return false;">
                            <i class="fas fa-plug"></i>
                            <span>ניהול תוספים</span>
                        </a>
                        <a href="#" class="action-item" onclick="closeModal('actions-modal'); openThemesModal('` + insId + `'); return false;">
                            <i class="fas fa-palette"></i>
                            <span>ניהול תבניות</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

// Plugins Modal
function openPluginsModal(insId) {
    var modalHtml = `
        <div class="softaculous-modal-overlay" id="plugins-modal">
            <div class="softaculous-modal" style="max-width:700px;">
                <div class="softaculous-modal-header">
                    <h3><i class="fas fa-plug"></i> ניהול תוספים</h3>
                    <button class="softaculous-modal-close" onclick="closeModal('plugins-modal')">&times;</button>
                </div>
                <div class="softaculous-modal-body" id="plugins-modal-body">
                    <div class="softaculous-sso-loading"><i class="fas fa-spinner fa-spin"></i><br>טוען תוספים...</div>
                </div>
                <div class="softaculous-modal-footer">
                    <button class="btn-sso" onclick="closeModal('plugins-modal')">סגור</button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Load plugins
    fetch('clientarea.php?action=productdetails&id={$serviceId}&modop=custom&a=softaculous_sso_get_plugins&insid=' + insId + '&token={$token}')
    .then(response => response.text())
    .then(text => {
        var data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            document.getElementById('plugins-modal-body').innerHTML = '<div class="softaculous-sso-error">שגיאת JSON</div>';
            return;
        }
        
        if (data.error) {
            document.getElementById('plugins-modal-body').innerHTML = '<div class="softaculous-sso-error">' + data.error + '</div>';
            return;
        }
        
        // Get plugins from plugins_list
        var rootData = data.plugins || data;
        var pluginsData = rootData.plugins_list || data.plugins_list || {};
        var plugins = [];
        
        if (typeof pluginsData === 'object' && !Array.isArray(pluginsData)) {
            Object.keys(pluginsData).forEach(function(key) {
                var plugin = pluginsData[key];
                if (plugin) {
                    plugin._file = key;
                    plugins.push(plugin);
                }
            });
        } else {
            plugins = pluginsData;
        }
        
        plugins = plugins.filter(function(p) { return p !== null && p !== undefined; });
        
        if (plugins.length === 0) {
            document.getElementById('plugins-modal-body').innerHTML = '<div class="softaculous-sso-empty">לא נמצאו תוספים</div>';
            return;
        }
        
        // Sort: active first
        plugins.sort(function(a, b) {
            var aActive = a.activated === 1 || a.activated === '1' || a.activated === true;
            var bActive = b.activated === 1 || b.activated === '1' || b.activated === true;
            if (aActive && !bActive) return -1;
            if (!aActive && bActive) return 1;
            return 0;
        });
        
        // Store plugins globally for pagination/search
        window._pluginsData = plugins;
        window._pluginsInsId = insId;
        window._pluginsPage = 1;
        window._pluginsPerPage = 5;
        window._pluginsSearch = '';
        
        renderPluginsTable();
    })
    .catch(error => {
        document.getElementById('plugins-modal-body').innerHTML = '<div class="softaculous-sso-error">שגיאה בטעינת התוספים</div>';
    });
}

function renderPluginsTable() {
    var plugins = window._pluginsData || [];
    var insId = window._pluginsInsId;
    var page = window._pluginsPage || 1;
    var perPage = window._pluginsPerPage || 5;
    var search = (window._pluginsSearch || '').toLowerCase();
    
    // Filter by search
    var filtered = plugins.filter(function(p) {
        if (!search) return true;
        var name = (p.Name || p['Plugin Name'] || p.name || '').toLowerCase();
        return name.indexOf(search) !== -1;
    });
    
    // Pagination
    var totalPages = Math.ceil(filtered.length / perPage);
    var start = (page - 1) * perPage;
    var pagePlugins = filtered.slice(start, start + perPage);
    
    // Build HTML
    var html = '<div class="plugins-search-box"><input type="text" placeholder="חיפוש תוסף..." value="' + (window._pluginsSearch || '') + '" oninput="searchPlugins(this.value)"></div>';
    html += '<table class="softaculous-sso-table"><thead><tr><th>שם התוסף</th><th>גירסה</th><th>סטטוס</th><th>פעולות</th></tr></thead><tbody>';
    
    pagePlugins.forEach(function(plugin) {
        if (!plugin) return;
        var pluginName = plugin.Name || plugin['Plugin Name'] || plugin.name || plugin.title || '';
        var pluginVersion = plugin.Version || plugin.version || '-';
        var pluginSlug = plugin._file || plugin.slug || plugin.Slug || pluginName;
        var isActive = plugin.activated === 1 || plugin.activated === '1' || plugin.activated === true;
        
        var statusText = isActive ? '<span class="badge-active">פעיל</span>' : '<span class="badge-inactive">לא פעיל</span>';
        var btnText = isActive ? 'כבה' : 'הפעל';
        var btnAction = isActive ? 'deactivate' : 'activate';
        var escapedSlug = pluginSlug.replace(/'/g, "\\'");
        
        html += '<tr>';
        html += '<td><strong>' + pluginName + '</strong></td>';
        html += '<td>' + pluginVersion + '</td>';
        html += '<td>' + statusText + '</td>';
        html += '<td>';
        html += '<button class="btn-sso btn-sm" onclick="togglePlugin(\'' + insId + '\', \'' + escapedSlug + '\', \'' + btnAction + '\')">' + btnText + '</button> ';
        html += '<button class="btn-sso btn-sm btn-danger" onclick="deletePlugin(\'' + insId + '\', \'' + escapedSlug + '\')">מחק</button>';
        html += '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    
    // Pagination controls
    if (totalPages > 1) {
        html += '<div class="plugins-pagination">';
        html += '<button ' + (page <= 1 ? 'disabled' : '') + ' onclick="goToPluginsPage(' + (page - 1) + ')">הקודם</button>';
        for (var i = 1; i <= totalPages; i++) {
            html += '<button class="' + (i === page ? 'active' : '') + '" onclick="goToPluginsPage(' + i + ')">' + i + '</button>';
        }
        html += '<button ' + (page >= totalPages ? 'disabled' : '') + ' onclick="goToPluginsPage(' + (page + 1) + ')">הבא</button>';
        html += '</div>';
    }
    
    document.getElementById('plugins-modal-body').innerHTML = html;
}

function searchPlugins(val) {
    window._pluginsSearch = val;
    window._pluginsPage = 1;
    
    // Save cursor position
    var input = document.querySelector('#plugins-modal-body .plugins-search-box input');
    var cursorPos = input ? input.selectionStart : val.length;
    
    renderPluginsTable();
    
    // Restore focus and cursor position
    setTimeout(function() {
        var newInput = document.querySelector('#plugins-modal-body .plugins-search-box input');
        if (newInput) {
            newInput.focus();
            newInput.setSelectionRange(cursorPos, cursorPos);
        }
    }, 0);
}

function goToPluginsPage(p) {
    window._pluginsPage = p;
    renderPluginsTable();
}

function deletePlugin(insId, slug) {
    if (!confirm('האם אתה בטוח שברצונך למחוק את התוסף?')) return;
    
    document.getElementById('plugins-modal-body').innerHTML = '<div class="softaculous-sso-loading"><i class="fas fa-spinner fa-spin"></i><br>מוחק תוסף...</div>';
    
    var formData = new FormData();
    formData.append('insid', insId);
    formData.append('slug', slug);
    formData.append('plugin_action', 'delete');
    
    fetch('clientarea.php?action=productdetails&id={$serviceId}&modop=custom&a=softaculous_sso_toggle_plugin&token={$token}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('plugins-modal-body').innerHTML = '<div class="softaculous-sso-success"><i class="fas fa-check-circle"></i><br>' + data.message + '</div>';
            setTimeout(function() { 
                closeModal('plugins-modal');
                openPluginsModal(insId);
            }, 1500);
        } else {
            document.getElementById('plugins-modal-body').innerHTML = '<div class="softaculous-sso-error">' + (data.error || 'שגיאה') + '</div>';
        }
    })
    .catch(error => {
        document.getElementById('plugins-modal-body').innerHTML = '<div class="softaculous-sso-error">שגיאה במחיקת התוסף</div>';
    });
}

function togglePlugin(insId, slug, action) {
    document.getElementById('plugins-modal-body').innerHTML = '<div class="softaculous-sso-loading"><i class="fas fa-spinner fa-spin"></i><br>' + (action === 'activate' ? 'מפעיל' : 'מכבה') + ' תוסף...</div>';
    
    var formData = new FormData();
    formData.append('insid', insId);
    formData.append('slug', slug);
    formData.append('plugin_action', action);
    
    fetch('clientarea.php?action=productdetails&id={$serviceId}&modop=custom&a=softaculous_sso_toggle_plugin&token={$token}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('plugins-modal-body').innerHTML = '<div class="softaculous-sso-success"><i class="fas fa-check-circle"></i><br>' + data.message + '</div>';
            setTimeout(function() { 
                closeModal('plugins-modal');
                openPluginsModal(insId);
            }, 1500);
        } else {
            document.getElementById('plugins-modal-body').innerHTML = '<div class="softaculous-sso-error">' + (data.error || 'שגיאה') + '</div>';
        }
    })
    .catch(error => {
        document.getElementById('plugins-modal-body').innerHTML = '<div class="softaculous-sso-error">שגיאה בביצוע הפעולה</div>';
    });
}

// Themes Modal
function openThemesModal(insId) {
    var modalHtml = `
        <div class="softaculous-modal-overlay" id="themes-modal">
            <div class="softaculous-modal" style="max-width:700px;">
                <div class="softaculous-modal-header">
                    <h3><i class="fas fa-palette"></i> ניהול תבניות</h3>
                    <button class="softaculous-modal-close" onclick="closeModal('themes-modal')">&times;</button>
                </div>
                <div class="softaculous-modal-body" id="themes-modal-body">
                    <div class="softaculous-sso-loading"><i class="fas fa-spinner fa-spin"></i><br>טוען תבניות...</div>
                </div>
                <div class="softaculous-modal-footer">
                    <button class="btn-sso" onclick="closeModal('themes-modal')">סגור</button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Load themes
    fetch('clientarea.php?action=productdetails&id={$serviceId}&modop=custom&a=softaculous_sso_get_themes&insid=' + insId + '&token={$token}')
    .then(response => response.text())
    .then(text => {
        var data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            document.getElementById('themes-modal-body').innerHTML = '<div class="softaculous-sso-error">שגיאת JSON</div>';
            return;
        }
        
        if (data.error) {
            document.getElementById('themes-modal-body').innerHTML = '<div class="softaculous-sso-error">' + data.error + '</div>';
            return;
        }
        
        // Get themes from themes_list
        var rootData = data.themes || data;
        var themesData = rootData.themes_list || data.themes_list || {};
        var themes = [];
        
        // Convert object to array with keys as file paths
        if (typeof themesData === 'object' && !Array.isArray(themesData)) {
            Object.keys(themesData).forEach(function(key) {
                var theme = themesData[key];
                if (theme) {
                    theme._slug = key; // Store the slug/key
                    themes.push(theme);
                }
            });
        } else {
            themes = themesData;
        }
        
        // Filter out null/undefined values
        themes = themes.filter(function(t) { return t !== null && t !== undefined; });
        
        if (themes.length === 0) {
            document.getElementById('themes-modal-body').innerHTML = '<div class="softaculous-sso-empty">לא נמצאו תבניות</div>';
            return;
        }
        
        // Sort: active first
        themes.sort(function(a, b) {
            var aActive = a.activated === 1 || a.activated === '1' || a.activated === true;
            var bActive = b.activated === 1 || b.activated === '1' || b.activated === true;
            if (aActive && !bActive) return -1;
            if (!aActive && bActive) return 1;
            return 0;
        });
        
        // Store themes globally for pagination/search
        window._themesData = themes;
        window._themesInsId = insId;
        window._themesPage = 1;
        window._themesPerPage = 5;
        window._themesSearch = '';
        
        renderThemesTable();
    })
    .catch(error => {
        document.getElementById('themes-modal-body').innerHTML = '<div class="softaculous-sso-error">שגיאה בטעינת התבניות</div>';
    });
}

function renderThemesTable() {
    var themes = window._themesData || [];
    var insId = window._themesInsId;
    var page = window._themesPage || 1;
    var perPage = window._themesPerPage || 5;
    var search = (window._themesSearch || '').toLowerCase();
    
    // Filter by search
    var filtered = themes.filter(function(t) {
        if (!search) return true;
        var name = (t.Name || t['Theme Name'] || t.name || t._slug || '').toLowerCase();
        return name.indexOf(search) !== -1;
    });
    
    // Pagination
    var totalPages = Math.ceil(filtered.length / perPage);
    var start = (page - 1) * perPage;
    var pageThemes = filtered.slice(start, start + perPage);
    
    // Build HTML
    var html = '<div class="plugins-search-box"><input type="text" placeholder="חיפוש תבנית..." value="' + (window._themesSearch || '') + '" oninput="searchThemes(this.value)"></div>';
    html += '<table class="softaculous-sso-table"><thead><tr><th>שם התבנית</th><th>גירסה</th><th>סטטוס</th><th>פעולות</th></tr></thead><tbody>';
    
    pageThemes.forEach(function(theme) {
        if (!theme) return;
        var themeName = theme.Name || theme['Theme Name'] || theme.name || theme.title || theme._slug || '';
        var themeVersion = theme.Version || theme.version || '-';
        var themeSlug = theme._slug || theme.slug || theme.Slug || theme.stylesheet || themeName;
        var isActive = theme.activated === 1 || theme.activated === '1' || theme.activated === true;
        
        var statusText = isActive ? '<span class="badge-active">פעילה</span>' : '<span class="badge-inactive">לא פעילה</span>';
        var escapedSlug = themeSlug.replace(/'/g, "\\'");
        
        html += '<tr>';
        html += '<td><strong>' + themeName + '</strong></td>';
        html += '<td>' + themeVersion + '</td>';
        html += '<td>' + statusText + '</td>';
        html += '<td>';
        if (!isActive) {
            html += '<button class="btn-sso btn-sm" onclick="activateTheme(\'' + insId + '\', \'' + escapedSlug + '\')">הפעל</button> ';
            html += '<button class="btn-sso btn-sm btn-danger" onclick="deleteTheme(\'' + insId + '\', \'' + escapedSlug + '\')">מחק</button>';
        } else {
            html += '<span style="color:#28a745;font-weight:bold;">תבנית פעילה</span>';
        }
        html += '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    
    // Pagination controls
    if (totalPages > 1) {
        html += '<div class="plugins-pagination">';
        html += '<button ' + (page <= 1 ? 'disabled' : '') + ' onclick="goToThemesPage(' + (page - 1) + ')">הקודם</button>';
        for (var i = 1; i <= totalPages; i++) {
            html += '<button class="' + (i === page ? 'active' : '') + '" onclick="goToThemesPage(' + i + ')">' + i + '</button>';
        }
        html += '<button ' + (page >= totalPages ? 'disabled' : '') + ' onclick="goToThemesPage(' + (page + 1) + ')">הבא</button>';
        html += '</div>';
    }
    
    document.getElementById('themes-modal-body').innerHTML = html;
}

function searchThemes(val) {
    window._themesSearch = val;
    window._themesPage = 1;
    
    // Save cursor position
    var input = document.querySelector('#themes-modal-body .plugins-search-box input');
    var cursorPos = input ? input.selectionStart : val.length;
    
    renderThemesTable();
    
    // Restore focus and cursor position
    setTimeout(function() {
        var newInput = document.querySelector('#themes-modal-body .plugins-search-box input');
        if (newInput) {
            newInput.focus();
            newInput.setSelectionRange(cursorPos, cursorPos);
        }
    }, 0);
}

function goToThemesPage(p) {
    window._themesPage = p;
    renderThemesTable();
}

function deleteTheme(insId, slug) {
    if (!confirm('האם אתה בטוח שברצונך למחוק את התבנית?')) return;
    
    document.getElementById('themes-modal-body').innerHTML = '<div class="softaculous-sso-loading"><i class="fas fa-spinner fa-spin"></i><br>מוחק תבנית...</div>';
    
    var formData = new FormData();
    formData.append('insid', insId);
    formData.append('slug', slug);
    formData.append('theme_action', 'delete');
    
    fetch('clientarea.php?action=productdetails&id={$serviceId}&modop=custom&a=softaculous_sso_activate_theme&token={$token}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('themes-modal-body').innerHTML = '<div class="softaculous-sso-success"><i class="fas fa-check-circle"></i><br>' + data.message + '</div>';
            setTimeout(function() { 
                closeModal('themes-modal');
                openThemesModal(insId);
            }, 1500);
        } else {
            document.getElementById('themes-modal-body').innerHTML = '<div class="softaculous-sso-error">' + (data.error || 'שגיאה') + '</div>';
        }
    })
    .catch(error => {
        document.getElementById('themes-modal-body').innerHTML = '<div class="softaculous-sso-error">שגיאה במחיקת התבנית</div>';
    });
}

function activateTheme(insId, slug) {
    document.getElementById('themes-modal-body').innerHTML = '<div class="softaculous-sso-loading"><i class="fas fa-spinner fa-spin"></i><br>מפעיל תבנית...</div>';
    
    var formData = new FormData();
    formData.append('insid', insId);
    formData.append('slug', slug);
    
    fetch('clientarea.php?action=productdetails&id={$serviceId}&modop=custom&a=softaculous_sso_activate_theme&token={$token}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('themes-modal-body').innerHTML = '<div class="softaculous-sso-success"><i class="fas fa-check-circle"></i><br>' + data.message + '</div>';
            setTimeout(function() { 
                closeModal('themes-modal');
                openThemesModal(insId);
            }, 1500);
        } else {
            document.getElementById('themes-modal-body').innerHTML = '<div class="softaculous-sso-error">' + (data.error || 'שגיאה') + '</div>';
        }
    })
    .catch(error => {
        document.getElementById('themes-modal-body').innerHTML = '<div class="softaculous-sso-error">שגיאה בביצוע הפעולה</div>';
    });
}
</script>
HTML;
});

/**
 * =====================================================
 * ADMIN AREA HOOKS
 * =====================================================
 */

/**
 * Add WordPress SSO button to Admin Area product page
 */
add_hook('AdminAreaHeaderOutput', 1, function($vars) {
    // Check if we're on any client-related page
    $userId = $_GET['userid'] ?? $_GET['id'] ?? null;
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Only show on client pages
    $isClientPage = (strpos($scriptName, 'clientssummary') !== false || 
                     strpos($scriptName, 'clientsservices') !== false ||
                     strpos($scriptName, 'clientsproducts') !== false ||
                     strpos($scriptName, 'clientsdomains') !== false ||
                     strpos($scriptName, 'clientsinvoices') !== false ||
                     strpos($scriptName, 'clientscontacts') !== false);
    
    if (!$isClientPage || !$userId) {
        return '';
    }
    
    // Check if this client has any cPanel/DirectAdmin services with WordPress
    $services = Capsule::table('tblhosting')
        ->where('userid', $userId)
        ->where('domainstatus', 'Active')
        ->get();
    
    $hasValidService = false;
    foreach ($services as $service) {
        $server = Capsule::table('tblservers')
            ->where('id', $service->server)
            ->first();
        
        if ($server) {
            $serverType = strtolower($server->type);
            if (in_array($serverType, ['cpanel', 'directadmin'])) {
                $hasValidService = true;
                break;
            }
        }
    }
    
    if (!$hasValidService) {
        return '';
    }

    return <<<HTML
<style>
.sso-admin-btn {
    background: linear-gradient(135deg, #bd417b 0%, #e91e8c 100%);
    color: #fff !important;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 13px;
    margin-left: 10px;
    text-decoration: none !important;
    display: inline-block;
}
.sso-admin-btn:hover {
    background: linear-gradient(135deg, #a03568 0%, #d01a7d 100%);
    color: #fff !important;
}
.sso-admin-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sso-admin-modal {
    background: #fff;
    border-radius: 10px;
    max-width: 700px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}
.sso-admin-modal-header {
    background: linear-gradient(135deg, #bd417b 0%, #e91e8c 100%);
    color: #fff;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.sso-admin-modal-header h3 {
    margin: 0;
    font-size: 18px;
    flex: 1;
    text-align: center;
    color: #fff;
}
.sso-admin-modal-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}
.sso-admin-modal-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}
.sso-admin-table {
    width: 100%;
    border-collapse: collapse;
}
.sso-admin-table th,
.sso-admin-table td {
    padding: 12px;
    text-align: right;
    border-bottom: 1px solid #eee;
}
.sso-admin-table th {
    background: #f8f9fa;
    font-weight: 600;
}
.sso-admin-table tr:hover {
    background: #f5f5f5;
}
.sso-admin-loading {
    text-align: center;
    padding: 40px;
    color: #666;
}
.sso-admin-error {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 5px;
    text-align: center;
}
.sso-admin-empty {
    text-align: center;
    padding: 40px;
    color: #666;
}
</style>
HTML;
});

/**
 * Add WordPress SSO button via JavaScript injection
 */
add_hook('AdminAreaFooterOutput', 1, function($vars) {
    // Check if we're on any client-related page
    $userId = $_GET['userid'] ?? $_GET['id'] ?? null;
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Only show on client pages
    $isClientPage = (strpos($scriptName, 'clientssummary') !== false || 
                     strpos($scriptName, 'clientsservices') !== false ||
                     strpos($scriptName, 'clientsproducts') !== false ||
                     strpos($scriptName, 'clientsdomains') !== false ||
                     strpos($scriptName, 'clientsinvoices') !== false ||
                     strpos($scriptName, 'clientscontacts') !== false);
    
    if (!$isClientPage || !$userId) {
        return '';
    }
    
    // Check if this client has any cPanel/DirectAdmin services
    $services = Capsule::table('tblhosting')
        ->where('userid', $userId)
        ->where('domainstatus', 'Active')
        ->get();
    
    $hasValidService = false;
    foreach ($services as $service) {
        $server = Capsule::table('tblservers')
            ->where('id', $service->server)
            ->first();
        
        if ($server) {
            $serverType = strtolower($server->type);
            if (in_array($serverType, ['cpanel', 'directadmin'])) {
                $hasValidService = true;
                break;
            }
        }
    }
    
    if (!$hasValidService) {
        return '';
    }

    return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if button already exists
    if (document.getElementById('sso-admin-wp-btn')) return;
    
    // Find the client profile header area - look for the client name/title
    var headerArea = document.querySelector('.client-header, .header-lined, .title-header, h1, h2');
    var tabsArea = document.querySelector('.nav-tabs, .client-tabs, ul.nav');
    
    // Create the SSO button
    var ssoBtn = document.createElement('a');
    ssoBtn.href = '#';
    ssoBtn.id = 'sso-admin-wp-btn';
    ssoBtn.className = 'sso-admin-btn';
    ssoBtn.innerHTML = '<i class="fab fa-wordpress"></i> התחברות לאתרים';
    ssoBtn.onclick = function(e) {
        e.preventDefault();
        openAdminSSOModal({$userId});
    };
    
    // Try to insert near the tabs or header
    if (tabsArea && tabsArea.parentNode) {
        tabsArea.parentNode.insertBefore(ssoBtn, tabsArea);
    } else if (headerArea) {
        headerArea.appendChild(ssoBtn);
    } else {
        // Fallback: add to top of content
        var content = document.querySelector('#content, .main-content, .contentarea');
        if (content) {
            content.insertBefore(ssoBtn, content.firstChild);
        }
    }
});

function openAdminSSOModal(userId) {
    // Remove existing modal
    var existing = document.getElementById('admin-sso-modal');
    if (existing) existing.remove();
    
    var modalHtml = '<div class="sso-admin-modal-overlay" id="admin-sso-modal">' +
        '<div class="sso-admin-modal">' +
            '<div class="sso-admin-modal-header">' +
                '<h3><i class="fab fa-wordpress"></i> התחברות לאתרי וורדפרס</h3>' +
                '<button class="sso-admin-modal-close" onclick="closeAdminSSOModal()">&times;</button>' +
            '</div>' +
            '<div class="sso-admin-modal-body" id="admin-sso-modal-body">' +
                '<div class="sso-admin-loading"><i class="fas fa-spinner fa-spin"></i> טוען אתרים...</div>' +
            '</div>' +
        '</div>' +
    '</div>';
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Load installations for all services of this user
    fetch('addonmodules.php?module=softaculous_sso&action=admin_get_all_installations&user_id=' + userId)
    .then(response => response.text())
    .then(text => {
        var data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            document.getElementById('admin-sso-modal-body').innerHTML = '<div class="sso-admin-error">שגיאה בטעינת הנתונים</div>';
            return;
        }
        
        if (data.error) {
            document.getElementById('admin-sso-modal-body').innerHTML = '<div class="sso-admin-error">' + data.error + '</div>';
            return;
        }
        
        var installations = data.installations || [];
        if (installations.length === 0) {
            document.getElementById('admin-sso-modal-body').innerHTML = '<div class="sso-admin-empty">לא נמצאו התקנות וורדפרס</div>';
            return;
        }
        
        var html = '<table class="sso-admin-table">';
        html += '<thead><tr><th>כתובת האתר</th><th>פעולה</th></tr></thead>';
        html += '<tbody>';
        
        installations.forEach(function(install) {
            var serviceId = install._service_id || '';
            var siteUrl = install.softurl || install.siteurl || install.url || 'לא ידוע';
            var insId = install.insid || install.id || '';
            html += '<tr>';
            html += '<td><a href="' + siteUrl + '" target="_blank">' + siteUrl + '</a></td>';
            html += '<td><button class="sso-admin-btn" onclick="adminSSO(' + serviceId + ', \'' + insId + '\')"><i class="fas fa-sign-in-alt"></i> התחבר</button></td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        document.getElementById('admin-sso-modal-body').innerHTML = html;
    })
    .catch(error => {
        document.getElementById('admin-sso-modal-body').innerHTML = '<div class="sso-admin-error">שגיאה בטעינת האתרים</div>';
    });
}

function closeAdminSSOModal() {
    var modal = document.getElementById('admin-sso-modal');
    if (modal) modal.remove();
}

function adminSSO(serviceId, insId) {
    // Open SSO in new tab
    window.open('addonmodules.php?module=softaculous_sso&action=admin_sso&service_id=' + serviceId + '&insid=' + insId, '_blank');
}
</script>
HTML;
});