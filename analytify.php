
/**
 * AnalytifyAi Stats - Revenue Attribution Dashboard with AI Insights
 * Enhanced with OpenAI Integration and Security Improvements
 */

// Add admin menu
add_action('admin_menu', 'analytify_stats_add_admin_menu');

function analytify_stats_add_admin_menu() {
    add_menu_page(
        '⚡ Site Insights',
        'Site Insights',
        'manage_options',
        'analytify-stats-dashboard',
        'analytify_stats_render_admin_page',
        'dashicons-chart-area',
        30
    );
    
    add_submenu_page(
        'analytify-stats-dashboard',
        'Settings',
        'Settings',
        'manage_options',
        'analytify-stats-settings',
        'analytify_stats_render_settings_page'
    );
}

// Encryption functions for sensitive data
function analytify_stats_encrypt($data) {
    $key = wp_salt('auth');
    $cipher = "AES-256-CBC";
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
    $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function analytify_stats_decrypt($data) {
    $key = wp_salt('auth');
    $cipher = "AES-256-CBC";
    list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
    return openssl_decrypt($encrypted_data, $cipher, $key, 0, $iv);
}

// Settings page
function analytify_stats_render_settings_page() {
    if (isset($_POST['submit'])) {
        check_admin_referer('analytify_stats_settings');
        
        update_option('analytify_stats_property_id', sanitize_text_field($_POST['property_id']));
        update_option('analytify_stats_api_email', sanitize_email($_POST['api_email']));
        
        // Encrypt and store private key
        $private_key = wp_unslash($_POST['api_private_key']);
        if (!empty($private_key)) {
            update_option('analytify_stats_api_private_key', analytify_stats_encrypt($private_key));
        }
        
        // Encrypt and store OpenAI API key
        $openai_key = sanitize_text_field($_POST['openai_api_key']);
        if (!empty($openai_key)) {
            update_option('analytify_stats_openai_key', analytify_stats_encrypt($openai_key));
        }
        
        // Store AI settings
        update_option('analytify_stats_ai_enabled', isset($_POST['ai_enabled']) ? 1 : 0);
        update_option('analytify_stats_ai_model', sanitize_text_field($_POST['ai_model'] ?? 'gpt-4-turbo-preview'));
        
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    
    $property_id = get_option('analytify_stats_property_id', '');
    $api_email = get_option('analytify_stats_api_email', '');
    $api_private_key = get_option('analytify_stats_api_private_key', '');
    $openai_key = get_option('analytify_stats_openai_key', '');
    $ai_enabled = get_option('analytify_stats_ai_enabled', 0);
    $ai_model = get_option('analytify_stats_ai_model', 'gpt-4-turbo-preview');
    
    // Decrypt for display (masked)
    $decrypted_private_key = $api_private_key ? analytify_stats_decrypt($api_private_key) : '';
    $decrypted_openai_key = $openai_key ? analytify_stats_decrypt($openai_key) : '';
    
    ?>
    <div class="wrap">
        <h1>analytify Stats Settings</h1>
        
        <div class="nav-tab-wrapper">
            <a href="#ga4-settings" class="nav-tab nav-tab-active" data-tab="ga4">GA4 Settings</a>
            <a href="#ai-settings" class="nav-tab" data-tab="ai">AI Insights</a>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('analytify_stats_settings'); ?>
            
            <!-- GA4 Settings Tab -->
            <div id="ga4-settings" class="tab-content">
                <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">
                    <h3>GA4 Setup Instructions:</h3>
                    <ol>
                        <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                        <li>Create a new project or select existing one</li>
                        <li>Enable "Google Analytics Data API"</li>
                        <li>Create a Service Account under "IAM & Admin" → "Service Accounts"</li>
                        <li>Download the JSON key file</li>
                        <li>In GA4, add the service account email as a user with "Viewer" access</li>
                        <li>Copy values from JSON file to fields below</li>
                    </ol>
                    
                    <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7;">
                        <p><strong>Alternative Method:</strong> Having trouble with the private key? You can also:</p>
                        <button type="button" id="convert-key-format" class="button">Convert Key Format</button>
                        <div id="key-converter" style="display: none; margin-top: 10px;">
                            <textarea id="json-input" placeholder="Paste your entire JSON file content here..." style="width: 100%; height: 150px;"></textarea>
                            <button type="button" id="extract-values" class="button" style="margin-top: 10px;">Extract Values</button>
                        </div>
                    </div>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="property_id">GA4 Property ID</label></th>
                        <td>
                            <input type="text" id="property_id" name="property_id" value="<?php echo esc_attr($property_id); ?>" class="regular-text" />
                            <p class="description">Format: 123456789 (numbers only)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="api_email">Service Account Email</label></th>
                        <td>
                            <input type="email" id="api_email" name="api_email" value="<?php echo esc_attr($api_email); ?>" class="large-text" />
                            <p class="description">From JSON: "client_email"</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="api_private_key">Private Key</label></th>
                        <td>
                            <textarea id="api_private_key" name="api_private_key" rows="10" class="large-text code" placeholder="Paste your private key here..."><?php echo $decrypted_private_key ? str_repeat('*', 50) . '...[ENCRYPTED]' : ''; ?></textarea>
                            <p class="description">From JSON: "private_key" - paste exactly as shown including \n characters</p>
                            <p class="description" style="color: #666;">Copy everything between the quotes, including all \n characters. The key should start with: -----BEGIN PRIVATE KEY-----\n</p>
                            <p class="description" style="color: #d63638;"><strong>Tip:</strong> Use the "Convert Key Format" helper above if you're having trouble.</p>
                            <p class="description" style="color: #4caf50;"><strong>Security:</strong> Your private key is encrypted before storage.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- AI Settings Tab -->
            <div id="ai-settings" class="tab-content" style="display: none;">
                <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #10b981;">
                    <h3>🤖 AI-Powered Insights</h3>
                    <p>Enable AI analysis to get intelligent insights and recommendations based on your revenue data.</p>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ai_enabled">Enable AI Insights</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="ai_enabled" name="ai_enabled" value="1" <?php checked($ai_enabled, 1); ?> />
                                Enable AI-powered analysis and recommendations
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="openai_api_key">OpenAI API Key</label></th>
                        <td>
                            <input type="password" id="openai_api_key" name="openai_api_key" value="<?php echo $decrypted_openai_key ? str_repeat('*', 20) : ''; ?>" class="large-text" placeholder="sk-..." />
                            <p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_model">AI Model</label></th>
                        <td>
                            <select id="ai_model" name="ai_model">
                                <option value="gpt-4-turbo-preview" <?php selected($ai_model, 'gpt-4-turbo-preview'); ?>>GPT-4 Turbo (Recommended)</option>
                                <option value="gpt-4" <?php selected($ai_model, 'gpt-4'); ?>>GPT-4</option>
                                <option value="gpt-3.5-turbo" <?php selected($ai_model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Faster, Lower Cost)</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php submit_button('Save Settings'); ?>
        </form>
        
        <?php if ($property_id && $api_email && $api_private_key): ?>
        <div style="margin-top: 20px;">
            <h3>Test Connection</h3>
            <button id="test-connection" class="button">Test GA4 Connection</button>
            <div id="test-result"></div>
        </div>
        <?php endif; ?>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab navigation
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $($(this).attr('href')).show();
            });
            
            // Test connection
            $('#test-connection').on('click', function() {
                $('#test-result').html('<p>Testing connection...</p>');
                $.post(ajaxurl, {
                    action: 'analytify_stats_test_connection',
                    nonce: '<?php echo wp_create_nonce('analytify_stats_test'); ?>'
                }, function(response) {
                    $('#test-result').html(response);
                });
            });
            
            // Key format converter
            $('#convert-key-format').on('click', function() {
                $('#key-converter').toggle();
            });
            
            $('#extract-values').on('click', function() {
                try {
                    const jsonStr = $('#json-input').val();
                    const json = JSON.parse(jsonStr);
                    
                    if (json.client_email) {
                        $('#api_email').val(json.client_email);
                    }
                    
                    if (json.private_key) {
                        $('#api_private_key').val(json.private_key);
                    }
                    
                    alert('Values extracted successfully! Don\'t forget to add your GA4 Property ID.');
                    $('#key-converter').hide();
                    $('#json-input').val('');
                } catch (e) {
                    alert('Invalid JSON format. Please paste the entire content of your JSON key file.');
                }
            });
        });
        </script>
        
        <style>
        .nav-tab-wrapper {
            margin-bottom: 20px;
        }
        .tab-content {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        </style>
    </div>
    <?php
}

// Test connection handler
add_action('wp_ajax_analytify_stats_test_connection', 'analytify_stats_test_connection');
function analytify_stats_test_connection() {
    check_ajax_referer('analytify_stats_test', 'nonce');
    
    delete_transient('analytify_stats_access_token');
    
    $encrypted_key = get_option('analytify_stats_api_private_key');
    $private_key = $encrypted_key ? analytify_stats_decrypt($encrypted_key) : '';
    
    $debug_info = array(
        'key_length' => strlen($private_key),
        'has_begin' => strpos($private_key, '-----BEGIN PRIVATE KEY-----') !== false,
        'has_end' => strpos($private_key, '-----END PRIVATE KEY-----') !== false,
        'has_escaped_n' => strpos($private_key, '\n') !== false,
        'has_real_newlines' => strpos($private_key, "\n") !== false,
    );
    
    $token = analytify_stats_get_access_token();
    if (is_wp_error($token)) {
        echo '<div class="notice notice-error"><p>Connection failed: ' . esc_html($token->get_error_message()) . '</p>';
        echo '<p style="font-size: 12px;">Debug info: ' . esc_html(json_encode($debug_info)) . '</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>Connection successful! Token obtained.</p></div>';
    }
    
    wp_die();
}

// Main dashboard page with AI integration
function analytify_stats_render_admin_page() {
    $property_id = get_option('analytify_stats_property_id');
    $ai_enabled = get_option('analytify_stats_ai_enabled', 0);
    
    if (!$property_id) {
        ?>
        <div class="wrap">
            <h1>Revenue Attribution Dashboard</h1>
            <div class="notice notice-warning">
                <p>Please configure your GA4 connection in <a href="<?php echo admin_url('admin.php?page=analytify-stats-settings'); ?>">Settings</a> first.</p>
            </div>
        </div>
        <?php
        return;
    }
    ?>
    <div class="wrap">
        <h1>Revenue Attribution Dashboard <?php if ($ai_enabled): ?><span style="color: #10b981; font-size: 0.7em;">✨ AI-Powered</span><?php endif; ?></h1>
        
        <div class="analytify-stats-dashboard">
            <div class="analytify-stats-filters">
                <select id="analytify-stats-date-range" class="analytify-stats-select">
                    <option value="28">Last 28 Days</option>
                    <option value="7">Last 7 Days</option>
                    <option value="30">Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                </select>
                <button id="analytify-stats-refresh" class="button button-primary">Load Data</button>
                <?php if ($ai_enabled): ?>
                <button id="analytify-stats-ai-analyze" class="button button-secondary" style="display: none;">
                    <span class="dashicons dashicons-lightbulb" style="vertical-align: middle;"></span> Get AI Insights
                </button>
                <?php endif; ?>
                <a href="<?php echo admin_url('admin.php?page=analytify-stats-settings'); ?>" class="button" style="margin-left: 10px;">Settings</a>
            </div>
            
            <!-- AI Insights Container -->
            <div id="analytify-ai-insights" style="display: none;">
                <!-- AI content will be inserted here -->
            </div>
            
            <!-- Chart Container -->
            <div id="analytify-stats-chart-container" style="display: none;">
                <div class="analytify-stats-chart-wrapper">
                    <h3>Revenue Trends</h3>
                    <div class="analytify-chart-container">
                        <canvas id="analytify-stats-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <div id="analytify-stats-content">
                <div class="analytify-stats-info">
                    <p>Click "Load Data" to fetch your revenue attribution report from GA4.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
    /* Original Dashboard Styles */
    .analytify-stats-dashboard {
        background: #fff;
        padding: 20px;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        margin-top: 20px;
    }
    .analytify-stats-filters {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        align-items: center;
    }
    .analytify-stats-select {
        min-width: 150px;
    }
    .analytify-stats-info {
        padding: 20px;
        background: #f0f0f1;
        border-left: 4px solid #0073aa;
    }
    
    /* Chart styles */
    .analytify-stats-chart-wrapper {
        background: #fff;
        padding: 20px;
        margin-bottom: 30px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .analytify-stats-chart-wrapper h3 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #23282d;
    }
    .analytify-chart-container {
        position: relative;
        height: 40vh;
        max-height: 400px;
        min-height: 250px;
    }
    
    /* Table Styles */
    .analytify-stats-table {
        width: 100%;
        border-collapse: collapse;
    }
    .analytify-stats-table th,
    .analytify-stats-table td {
        text-align: left;
        padding: 12px;
        border-bottom: 1px solid #e1e1e1;
    }
    .analytify-stats-table th {
        background: #f1f1f1;
        font-weight: 600;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .analytify-stats-table tr:hover {
        background: #f9f9f9;
    }
    .analytify-stats-table td:nth-child(n+3),
    .analytify-stats-table th:nth-child(n+3) {
        text-align: right;
    }
    .analytify-stats-loading {
        text-align: center;
        padding: 40px;
        color: #666;
    }
    .analytify-stats-error {
        color: #d63638;
        padding: 20px;
        background: #fcf0f1;
        border-left: 4px solid #d63638;
        margin: 20px 0;
    }
    .analytify-stats-total-row {
        font-weight: bold;
        background: #f0f0f0;
    }
    
    /* Icon styles */
    .source-icon {
        display: inline-block;
        width: 20px;
        margin-right: 8px;
        font-size: 16px;
        vertical-align: middle;
    }
    
    /* Performance indicator styles */
    .perf-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-left: 8px;
        vertical-align: middle;
    }
    .perf-high { background-color: #4caf50; }
    .perf-medium { background-color: #ff9800; }
    .perf-low { background-color: #f44336; }
    
    /* Revenue bar */
    .revenue-bar-container {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .revenue-bar {
        flex: 1;
        height: 20px;
        background: #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
        max-width: 200px;
    }
    .revenue-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #4caf50 0%, #66bb6a 100%);
        transition: width 0.3s ease;
    }
    .revenue-percentage {
        font-weight: 600;
        color: #666;
        min-width: 50px;
    }
    
    /* AI Insights Styles */
    #analytify-ai-insights {
        margin: 20px 0;
        animation: fadeIn 0.5s ease-in;
    }
    
    .ai-insights-container {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 30px;
        color: white;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    
    .ai-insights-header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .ai-insights-header h2 {
        color: white;
        margin: 0;
        flex: 1;
    }
    
    .ai-score {
        background: rgba(255,255,255,0.2);
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: bold;
        font-size: 18px;
    }
    
    .ai-summary {
        background: rgba(255,255,255,0.1);
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        line-height: 1.6;
    }
    
    .ai-recommendations {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .ai-recommendation-card {
        background: white;
        color: #333;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }
    
    .ai-recommendation-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    
    .recommendation-priority {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        margin-bottom: 10px;
    }
    
    .priority-high {
        background: #fee2e2;
        color: #dc2626;
    }
    
    .priority-medium {
        background: #fef3c7;
        color: #d97706;
    }
    
    .priority-low {
        background: #dbeafe;
        color: #2563eb;
    }
    
    .ai-metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }
    
    .ai-metric-card {
        background: rgba(255,255,255,0.9);
        color: #333;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
    }
    
    .ai-metric-value {
        font-size: 24px;
        font-weight: bold;
        margin: 5px 0;
    }
    
    .ai-metric-label {
        font-size: 14px;
        color: #666;
    }
    
    .ai-loading {
        text-align: center;
        padding: 40px;
    }
    
    .ai-loading-spinner {
        display: inline-block;
        width: 40px;
        height: 40px;
        border: 4px solid rgba(255,255,255,0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    .analytify-stats-table td:nth-child(1) {
        text-align: left;
    }
    
    /* Spinning animation for loading */
    .dashicons.spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        let chartInstance = null;
        let currentData = null;
        
        $('#analytify-stats-refresh').on('click', function() {
            const button = $(this);
            const originalText = button.text();
            button.prop('disabled', true).text('Loading...');
            
            $('#analytify-stats-content').html('<div class="analytify-stats-loading">Fetching data from Google Analytics...</div>');
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'analytify_stats_get_data',
                    days: $('#analytify-stats-date-range').val(),
                    nonce: '<?php echo wp_create_nonce('analytify_stats_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        currentData = response.data;
                        $('#analytify-stats-content').html(response.data.html);
                        
                        if (response.data.chartData) {
                            $('#analytify-stats-chart-container').show();
                            updateChart(response.data.chartData);
                        }
                        
                        <?php if ($ai_enabled): ?>
                        $('#analytify-stats-ai-analyze').show();
                        <?php endif; ?>
                    } else {
                        $('#analytify-stats-content').html('<div class="analytify-stats-error">' + response.data + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#analytify-stats-content').html('<div class="analytify-stats-error">Request failed: ' + error + '</div>');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });
        
        <?php if ($ai_enabled): ?>
        $('#analytify-stats-ai-analyze').on('click', function() {
            if (!currentData) return;
            
            const button = $(this);
            const originalHtml = button.html();
            button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Analyzing...');
            
            $('#analytify-ai-insights').html('<div class="ai-loading"><div class="ai-loading-spinner"></div><p>AI is analyzing your data...</p></div>').show();
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'analytify_stats_ai_analyze',
                    data: JSON.stringify(currentData),
                    days: $('#analytify-stats-date-range').val(),
                    nonce: '<?php echo wp_create_nonce('analytify_stats_ai_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#analytify-ai-insights').html(response.data.html);
                    } else {
                        $('#analytify-ai-insights').html('<div class="analytify-stats-error">AI Analysis failed: ' + response.data + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#analytify-ai-insights').html('<div class="analytify-stats-error">AI request failed: ' + error + '</div>');
                },
                complete: function() {
                    button.prop('disabled', false).html(originalHtml);
                }
            });
        });
        <?php endif; ?>
        
        function updateChart(data) {
            const ctx = document.getElementById('analytify-stats-chart').getContext('2d');
            
            if (chartInstance) {
                chartInstance.destroy();
            }
            
            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Revenue',
                        data: data.revenue,
                        borderColor: '#4caf50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        yAxisID: 'y'
                    }, {
                        label: 'Visitors',
                        data: data.visitors,
                        borderColor: '#2196F3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Revenue ($)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(0);
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Visitors'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
        }
    });
    </script>
    <?php
}

// AI Analysis AJAX Handler
add_action('wp_ajax_analytify_stats_ai_analyze', 'analytify_stats_ai_analyze');

function analytify_stats_ai_analyze() {
    check_ajax_referer('analytify_stats_ai_nonce', 'nonce');
    
    $openai_key = get_option('analytify_stats_openai_key');
    if (!$openai_key) {
        wp_send_json_error('OpenAI API key not configured');
    }
    
    $decrypted_key = analytify_stats_decrypt($openai_key);
    $model = get_option('analytify_stats_ai_model', 'gpt-4-turbo-preview');
    
    $data = json_decode(stripslashes($_POST['data']), true);
    $days = intval($_POST['days']);
    
    // Prepare data for AI analysis
    $prompt = analytify_stats_create_ai_prompt($data, $days);
    
    // Call OpenAI API
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $decrypted_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are an expert digital marketing analyst specializing in revenue attribution and conversion optimization. Provide actionable insights based on Google Analytics data. Always respond in valid JSON format.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'response_format' => array('type' => 'json_object')
        )),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error('OpenAI API request failed: ' . $response->get_error_message());
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['error'])) {
        wp_send_json_error('OpenAI API error: ' . $body['error']['message']);
    }
    
    $ai_content = json_decode($body['choices'][0]['message']['content'], true);
    
    if (!$ai_content) {
        wp_send_json_error('Failed to parse AI response');
    }
    
    // Generate HTML from AI response
    $html = analytify_stats_format_ai_insights($ai_content);
    
    wp_send_json_success(array('html' => $html));
}

// Create AI prompt
function analytify_stats_create_ai_prompt($data, $days) {
    $prompt = "Analyze the following Google Analytics revenue attribution data for the last {$days} days and provide strategic insights:\n\n";
    
    // Add structured data
    $prompt .= "REVENUE DATA:\n";
    $prompt .= json_encode($data['structured_data']) . "\n\n";
    
    $prompt .= "Please provide a comprehensive analysis in the following JSON structure:
{
    \"overall_score\": \"A score from 0-100 indicating overall marketing performance\",
    \"summary\": \"A 2-3 sentence executive summary of key findings\",
    \"key_metrics\": {
        \"best_performing_channel\": \"Channel name\",
        \"worst_performing_channel\": \"Channel name\",
        \"total_revenue\": \"Total revenue amount\",
        \"average_order_value\": \"Average order value\",
        \"conversion_rate\": \"Overall conversion rate percentage\"
    },
    \"insights\": [
        {
            \"title\": \"Key insight title\",
            \"description\": \"Detailed explanation\",
            \"impact\": \"high/medium/low\",
            \"metric\": \"Supporting metric or data point\"
        }
    ],
    \"recommendations\": [
        {
            \"title\": \"Actionable recommendation\",
            \"description\": \"Detailed steps to implement\",
            \"priority\": \"high/medium/low\",
            \"expected_impact\": \"Expected outcome\",
            \"effort\": \"high/medium/low\"
        }
    ],
    \"opportunities\": [
        {
            \"channel\": \"Channel/source name\",
            \"current_performance\": \"Current metrics\",
            \"potential\": \"Growth potential explanation\",
            \"action\": \"Specific action to take\"
        }
    ],
    \"warnings\": [
        {
            \"issue\": \"Problem identified\",
            \"severity\": \"high/medium/low\",
            \"affected_channel\": \"Channel name\",
            \"recommendation\": \"How to fix\"
        }
    ]
}

Focus on:
1. Revenue optimization opportunities
2. Underperforming channels that need attention
3. High-performing channels to scale
4. Conversion rate optimization tactics
5. Budget allocation recommendations
6. Seasonal or trend-based insights";

    return $prompt;
}

// Format AI insights into HTML
function analytify_stats_format_ai_insights($insights) {
    ob_start();
    ?>
    <div class="ai-insights-container">
        <div class="ai-insights-header">
            <h2>🤖 AI-Powered Marketing Insights</h2>
            <div class="ai-score">Performance Score: <?php echo intval($insights['overall_score']); ?>/100</div>
        </div>
        
        <div class="ai-summary">
            <p><?php echo esc_html($insights['summary']); ?></p>
        </div>
        
        <?php if (!empty($insights['key_metrics'])): ?>
        <div class="ai-metrics-grid">
            <div class="ai-metric-card">
                <div class="ai-metric-label">Best Channel</div>
                <div class="ai-metric-value"><?php echo esc_html($insights['key_metrics']['best_performing_channel']); ?></div>
            </div>
            <div class="ai-metric-card">
                <div class="ai-metric-label">Total Revenue</div>
                <div class="ai-metric-value"><?php echo esc_html($insights['key_metrics']['total_revenue']); ?></div>
            </div>
            <div class="ai-metric-card">
                <div class="ai-metric-label">Avg Order Value</div>
                <div class="ai-metric-value"><?php echo esc_html($insights['key_metrics']['average_order_value']); ?></div>
            </div>
            <div class="ai-metric-card">
                <div class="ai-metric-label">Conversion Rate</div>
                <div class="ai-metric-value"><?php echo esc_html($insights['key_metrics']['conversion_rate']); ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($insights['recommendations'])): ?>
        <h3 style="color: white; margin-top: 30px;">📋 Recommendations</h3>
        <div class="ai-recommendations">
            <?php foreach ($insights['recommendations'] as $rec): ?>
            <div class="ai-recommendation-card">
                <span class="recommendation-priority priority-<?php echo esc_attr($rec['priority']); ?>">
                    <?php echo ucfirst(esc_html($rec['priority'])); ?> Priority
                </span>
                <h4><?php echo esc_html($rec['title']); ?></h4>
                <p><?php echo esc_html($rec['description']); ?></p>
                <p style="margin-top: 10px; font-size: 14px; color: #666;">
                    <strong>Expected Impact:</strong> <?php echo esc_html($rec['expected_impact']); ?><br>
                    <strong>Effort:</strong> <?php echo ucfirst(esc_html($rec['effort'])); ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($insights['warnings'])): ?>
        <h3 style="color: white; margin-top: 30px;">⚠️ Issues to Address</h3>
        <div class="ai-recommendations">
            <?php foreach ($insights['warnings'] as $warning): ?>
            <div class="ai-recommendation-card" style="border-left: 4px solid #dc2626;">
                <h4><?php echo esc_html($warning['issue']); ?></h4>
                <p>Affected Channel: <strong><?php echo esc_html($warning['affected_channel']); ?></strong></p>
                <p><?php echo esc_html($warning['recommendation']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Get access token using JWT (with proper error handling)
function analytify_stats_get_access_token() {
    $cache_key = 'analytify_stats_access_token';
    $token = get_transient($cache_key);
    
    if ($token) {
        return $token;
    }
    
    $client_email = get_option('analytify_stats_api_email');
    $encrypted_key = get_option('analytify_stats_api_private_key');
    
    if (!$client_email || !$encrypted_key) {
        return new WP_Error('missing_credentials', 'API credentials not configured');
    }
    
    // Decrypt the private key
    $private_key = analytify_stats_decrypt($encrypted_key);
    
    // Handle newline characters
    if (strpos($private_key, '\n') !== false && strpos($private_key, "\n") === false) {
        $private_key = str_replace('\n', "\n", $private_key);
    }
    
    $private_key = trim($private_key, '"\'');
    
    // Validate key format
    if (strpos($private_key, '-----BEGIN PRIVATE KEY-----') === false) {
        return new WP_Error('invalid_key_format', 'Private key must start with -----BEGIN PRIVATE KEY-----');
    }
    
    $now = time();
    $header = json_encode(array('alg' => 'RS256', 'typ' => 'JWT'));
    $claims = json_encode(array(
        'iss' => $client_email,
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ));
    
    $base64_header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64_claims = rtrim(strtr(base64_encode($claims), '+/', '-_'), '=');
    
    $signature_input = $base64_header . '.' . $base64_claims;
    
    $signature = '';
    $private_key_resource = openssl_pkey_get_private($private_key);
    if (!$private_key_resource) {
        return new WP_Error('invalid_key', 'Invalid private key format');
    }
    
    openssl_sign($signature_input, $signature, $private_key_resource, OPENSSL_ALGO_SHA256);
    $base64_signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    
    $jwt = $base64_header . '.' . $base64_claims . '.' . $base64_signature;
    
    $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
        'body' => array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ),
        'timeout' => 15
    ));
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['access_token'])) {
        set_transient($cache_key, $body['access_token'], 3500);
        return $body['access_token'];
    }
    
    return new WP_Error('token_error', isset($body['error_description']) ? $body['error_description'] : 'Failed to get access token');
}

// AJAX handler for data (with structured data for AI)
add_action('wp_ajax_analytify_stats_get_data', 'analytify_stats_ajax_get_data');

function analytify_stats_ajax_get_data() {
    check_ajax_referer('analytify_stats_nonce', 'nonce');
    
    $days = intval($_POST['days']);
    $property_id = get_option('analytify_stats_property_id');
    
    if (!$property_id) {
        wp_send_json_error('Property ID not configured');
    }
    
    $token = analytify_stats_get_access_token();
    if (is_wp_error($token)) {
        wp_send_json_error('Auth failed: ' . $token->get_error_message());
    }
    
    $end_date = date('Y-m-d');
    $start_date = date('Y-m-d', strtotime("-{$days} days"));
    
    // Get main attribution data
    $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$property_id}:runReport";
    
    $body = array(
        'dateRanges' => array(
            array('startDate' => $start_date, 'endDate' => $end_date)
        ),
        'dimensions' => array(
            array('name' => 'sessionSource'),
            array('name' => 'sessionMedium')
        ),
        'metrics' => array(
            array('name' => 'sessions'),
            array('name' => 'totalRevenue'),
            array('name' => 'transactions'),
            array('name' => 'purchasers'),
            array('name' => 'averagePurchaseRevenue')
        ),
        'orderBys' => array(
            array('metric' => array('metricName' => 'totalRevenue'), 'desc' => true)
        )
    );
    
    $response = wp_remote_post($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($body),
        'timeout' => 15
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error('API request failed: ' . $response->get_error_message());
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($data['error'])) {
        // Handle metrics not available
        if (strpos($data['error']['message'], 'purchasers') !== false || 
            strpos($data['error']['message'], 'averagePurchaseRevenue') !== false) {
            
            $body['metrics'] = array(
                array('name' => 'sessions'),
                array('name' => 'totalRevenue'),
                array('name' => 'transactions')
            );
            
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($body),
                'timeout' => 15
            ));
            
            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                $data['basic_metrics'] = true;
            }
        }
        
        if (isset($data['error'])) {
            wp_send_json_error('GA4 API Error: ' . esc_html($data['error']['message']));
        }
    }
    
    // Get time series data for chart
    $chart_data = analytify_stats_get_time_series_data($property_id, $token, $start_date, $end_date);
    
    // Prepare structured data for AI analysis
    $structured_data = analytify_stats_prepare_structured_data($data);
    
    // Format the response
    $html = analytify_stats_format_enhanced_table($data);
    
    wp_send_json_success(array(
        'html' => $html,
        'chartData' => $chart_data,
        'structured_data' => $structured_data
    ));
}

// Prepare structured data for AI
function analytify_stats_prepare_structured_data($data) {
    $structured = array(
        'channels' => array(),
        'totals' => array(
            'visitors' => 0,
            'revenue' => 0,
            'transactions' => 0,
            'purchasers' => 0
        )
    );
    
    if (isset($data['rows']) && !empty($data['rows'])) {
        foreach ($data['rows'] as $row) {
            $source = $row['dimensionValues'][0]['value'];
            $medium = $row['dimensionValues'][1]['value'];
            $visitors = intval($row['metricValues'][0]['value']);
            $revenue = floatval($row['metricValues'][1]['value']);
            $transactions = intval($row['metricValues'][2]['value']);
            
            $channel = array(
                'source' => $source,
                'medium' => $medium,
                'visitors' => $visitors,
                'revenue' => $revenue,
                'transactions' => $transactions,
                'conversion_rate' => $visitors > 0 ? ($transactions / $visitors) * 100 : 0,
                'revenue_per_visitor' => $visitors > 0 ? $revenue / $visitors : 0
            );
            
            if (!isset($data['basic_metrics'])) {
                $channel['purchasers'] = intval($row['metricValues'][3]['value']);
                $channel['avg_order_value'] = floatval($row['metricValues'][4]['value']);
            } else {
                $channel['purchasers'] = $transactions;
                $channel['avg_order_value'] = $transactions > 0 ? $revenue / $transactions : 0;
            }
            
            $structured['channels'][] = $channel;
            
            // Update totals
            $structured['totals']['visitors'] += $visitors;
            $structured['totals']['revenue'] += $revenue;
            $structured['totals']['transactions'] += $transactions;
            $structured['totals']['purchasers'] += $channel['purchasers'];
        }
    }
    
    return $structured;
}

// Rest of the functions remain the same (analytify_stats_get_time_series_data, analytify_stats_format_enhanced_table, etc.)
// ... [Include all the remaining helper functions from the original code]

// Enhanced table formatting function
function analytify_stats_format_enhanced_table($data) {
    $using_basic_metrics = isset($data['basic_metrics']) && $data['basic_metrics'];
    
    // Calculate averages for color coding
    $revenues_per_visitor = array();
    $conversion_rates = array();
    $source_data = array();
    
    if (isset($data['rows']) && !empty($data['rows'])) {
        foreach ($data['rows'] as $row) {
            $visitors = intval($row['metricValues'][0]['value']);
            $revenue = floatval($row['metricValues'][1]['value']);
            $transactions = intval($row['metricValues'][2]['value']);
            
            if ($using_basic_metrics) {
                $purchasers = $transactions;
                $avg_order = $transactions > 0 ? $revenue / $transactions : 0;
            } else {
                $purchasers = intval($row['metricValues'][3]['value'] ?? 0);
                $avg_order = floatval($row['metricValues'][4]['value'] ?? 0);
                
                if ($purchasers == 0 && $transactions > 0) {
                    $purchasers = $transactions;
                }
                
                if ($avg_order == 0 && $purchasers > 0) {
                    $avg_order = $revenue / $purchasers;
                }
            }
            
            if ($revenue > 0 && $visitors > 0) {
                $rpv = $revenue / $visitors;
                $conv = ($purchasers / $visitors) * 100;
                
                $revenues_per_visitor[] = $rpv;
                $conversion_rates[] = $conv;
                
                $source_data[] = array(
                    'source' => $row['dimensionValues'][0]['value'],
                    'medium' => $row['dimensionValues'][1]['value'],
                    'visitors' => $visitors,
                    'revenue' => $revenue,
                    'transactions' => $transactions,
                    'purchasers' => $purchasers,
                    'rpv' => $rpv,
                    'conv' => $conv
                );
            }
        }
    }
    
    $avg_rpv = !empty($revenues_per_visitor) ? array_sum($revenues_per_visitor) / count($revenues_per_visitor) : 0;
    $avg_conv = !empty($conversion_rates) ? array_sum($conversion_rates) / count($conversion_rates) : 0;
    
    // Calculate total revenue for percentage
    $total_revenue = array_sum(array_column($source_data, 'revenue'));
    
    ob_start();
    ?>
    <table class="analytify-stats-table">
        <thead>
            <tr>
                <th>Source / Medium</th>
                <th>Visitors</th>
                <th>Revenue</th>
                <th>% of Revenue</th>
                <th><?php echo $using_basic_metrics ? 'Transactions' : 'Purchasers'; ?></th>
                <th>Rev/Visitor</th>
                <th>Visitor→Paid %</th>
                <?php if (!$using_basic_metrics): ?>
                <th>Avg Order</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php
        $totals = array(
            'visitors' => 0, 
            'revenue' => 0, 
            'transactions' => 0,
            'purchasers' => 0
        );
        
        if (!empty($source_data)) {
            foreach ($source_data as $data) {
                $icon = analytify_stats_get_source_icon($data['source'], $data['medium']);
                $rpv_class = analytify_stats_get_performance_class($data['rpv'], $avg_rpv, 'revenue');
                $conv_class = analytify_stats_get_performance_class($data['conv'], $avg_conv, 'conversion');
                $revenue_percentage = ($data['revenue'] / $total_revenue) * 100;
                
                $totals['visitors'] += $data['visitors'];
                $totals['revenue'] += $data['revenue'];
                $totals['transactions'] += $data['transactions'];
                $totals['purchasers'] += $data['purchasers'];
                
                if (!$using_basic_metrics) {
                    $avg_order = $data['purchasers'] > 0 ? $data['revenue'] / $data['purchasers'] : 0;
                }
                ?>
                <tr>
                    <td>
                        <span class="source-icon"><?php echo $icon; ?></span>
                        <?php echo esc_html($data['source'] . ' / ' . $data['medium']); ?>
                    </td>
                    <td><?php echo number_format($data['visitors']); ?></td>
                    <td>$<?php echo number_format($data['revenue'], 2); ?></td>
                    <td>
                        <div class="revenue-bar-container">
                            <div class="revenue-bar">
                                <div class="revenue-bar-fill" style="width: <?php echo min($revenue_percentage, 100); ?>%;"></div>
                            </div>
                            <span class="revenue-percentage"><?php echo number_format($revenue_percentage, 1); ?>%</span>
                        </div>
                    </td>
                    <td><?php echo $data['purchasers']; ?></td>
                    <td>
                        $<?php echo number_format($data['rpv'], 2); ?>
                        <span class="perf-indicator <?php echo $rpv_class; ?>"></span>
                    </td>
                    <td>
                        <?php echo number_format($data['conv'], 2); ?>%
                        <span class="perf-indicator <?php echo $conv_class; ?>"></span>
                    </td>
                    <?php if (!$using_basic_metrics): ?>
                    <td>$<?php echo number_format($avg_order, 2); ?></td>
                    <?php endif; ?>
                </tr>
                <?php
            }
        } else {
            ?>
            <tr>
                <td colspan="<?php echo $using_basic_metrics ? 7 : 8; ?>" style="text-align: center; padding: 40px;">
                    No revenue data found for the selected period.
                </td>
            </tr>
            <?php
        }
        
        if ($totals['revenue'] > 0) {
            $total_rpv = $totals['visitors'] > 0 ? $totals['revenue'] / $totals['visitors'] : 0;
            $total_conv = $totals['visitors'] > 0 ? ($totals['purchasers'] / $totals['visitors']) * 100 : 0;
            $total_avg_order = $totals['purchasers'] > 0 ? $totals['revenue'] / $totals['purchasers'] : 0;
            ?>
            <tr class="analytify-stats-total-row">
                <td><span class="source-icon">📊</span> Total</td>
                <td><?php echo number_format($totals['visitors']); ?></td>
                <td>$<?php echo number_format($totals['revenue'], 2); ?></td>
                <td>100.0%</td>
                <td><?php echo $totals['purchasers']; ?></td>
                <td>$<?php echo number_format($total_rpv, 2); ?></td>
                <td><?php echo number_format($total_conv, 2); ?>%</td>
                <?php if (!$using_basic_metrics): ?>
                <td>$<?php echo number_format($total_avg_order, 2); ?></td>
                <?php endif; ?>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>
    
    <div style="margin-top: 20px; padding: 15px; background: #f1f1f1; border-left: 4px solid #0073aa;">
        <p><strong>Visual Indicators:</strong></p>
        <ul style="margin: 10px 0 0 20px;">
            <li><strong>Icons:</strong> 🔍 Organic | 💰 Paid | 📱 Social | ✉️ Email | 🔗 Referral | 🎯 Direct</li>
            <li><strong>Performance Dots:</strong> 
                <span class="perf-indicator perf-high"></span> Above Average | 
                <span class="perf-indicator perf-medium"></span> Average | 
                <span class="perf-indicator perf-low"></span> Below Average
            </li>
            <li><strong>Revenue %:</strong> Shows each source's contribution to total revenue</li>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}

// Get time series data function
function analytify_stats_get_time_series_data($property_id, $token, $start_date, $end_date) {
    $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$property_id}:runReport";
    
    $body = array(
        'dateRanges' => array(
            array('startDate' => $start_date, 'endDate' => $end_date)
        ),
        'dimensions' => array(
            array('name' => 'date')
        ),
        'metrics' => array(
            array('name' => 'totalRevenue'),
            array('name' => 'sessions')
        ),
        'orderBys' => array(
            array('dimension' => array('dimensionName' => 'date'))
        )
    );
    
    $response = wp_remote_post($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($body),
        'timeout' => 15
    ));
    
    if (is_wp_error($response)) {
        return null;
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($data['error']) || !isset($data['rows'])) {
        return null;
    }
    
    $labels = array();
    $revenue = array();
    $visitors = array();
    
    foreach ($data['rows'] as $row) {
        $date = $row['dimensionValues'][0]['value'];
        $labels[] = date('M j', strtotime($date));
        $revenue[] = floatval($row['metricValues'][0]['value']);
        $visitors[] = intval($row['metricValues'][1]['value']);
    }
    
    return array(
        'labels' => $labels,
        'revenue' => $revenue,
        'visitors' => $visitors
    );
}

// Helper functions
function analytify_stats_get_source_icon($source, $medium) {
    $source_lower = strtolower($source);
    $medium_lower = strtolower($medium);
    
    if (strpos($medium_lower, 'cpc') !== false || strpos($medium_lower, 'paid') !== false) {
        return '💰';
    } elseif (strpos($medium_lower, 'email') !== false) {
        return '✉️';
    } elseif (strpos($medium_lower, 'social') !== false) {
        return '📱';
    } elseif ($medium_lower === 'organic') {
        return '🔍';
    } elseif ($medium_lower === 'referral') {
        return '🔗';
    } elseif ($source_lower === '(direct)' && $medium_lower === '(none)') {
        return '🎯';
    }
    
    if (in_array($source_lower, array('facebook', 'twitter', 'instagram', 'linkedin', 'pinterest'))) {
        return '📱';
    } elseif (in_array($source_lower, array('google', 'bing', 'yahoo', 'duckduckgo'))) {
        return '🔍';
    }
    
    return '🌐';
}

function analytify_stats_get_performance_class($value, $average, $type = 'revenue') {
    if ($type === 'conversion') {
        if ($value > $average * 1.5) return 'perf-high';
        if ($value < $average * 0.5) return 'perf-low';
        return 'perf-medium';
    } else {
        if ($value > $average * 1.2) return 'perf-high';
        if ($value < $average * 0.8) return 'perf-low';
        return 'perf-medium';
    }
}
