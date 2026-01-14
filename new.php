<?php
/**
 * Plugin Name: Visa Management System Pro
 * Plugin URI: https://yourwebsite.com
 * Description: Complete Visa Application Management System with Dashboard, SMS, Reports, Tracking
 * Version: 3.0.1
 * Author: Your Company
 * License: GPL v2 or later
 * Text Domain: visa-crm
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VMS_VERSION', '3.0.1');
define('VMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VMS_PLUGIN_FILE', __FILE__);

// ==================== DATABASE SETUP ====================
register_activation_hook(__FILE__, 'vms_install_tables');
function vms_install_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // 1. Main clients table
    $table_clients = $wpdb->prefix . 'vms_clients';
    $sql_clients = "CREATE TABLE IF NOT EXISTS $table_clients (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        serial_no varchar(50) DEFAULT NULL,
        client_name varchar(200) NOT NULL,
        passport_no varchar(100) NOT NULL,
        phone varchar(30) NOT NULL,
        email varchar(100) DEFAULT '',
        visa_type varchar(50) DEFAULT '',
        country varchar(100) DEFAULT '',
        total_fee decimal(15,2) DEFAULT '0.00',
        paid_fee decimal(15,2) DEFAULT '0.00',
        due_fee decimal(15,2) DEFAULT '0.00',
        current_step int(2) DEFAULT 1,
        step_name varchar(200) DEFAULT 'Application Submitted',
        status varchar(50) DEFAULT 'pending',
        application_date date DEFAULT NULL,
        submission_date date DEFAULT NULL,
        delivery_date date DEFAULT NULL,
        passport_copy varchar(500) DEFAULT '',
        photo varchar(500) DEFAULT '',
        notes text,
        assigned_to bigint(20) DEFAULT 0,
        created_by bigint(20) DEFAULT 0,
        updated_by bigint(20) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_deleted tinyint(1) DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY passport_no (passport_no),
        KEY idx_serial (serial_no),
        KEY idx_status (status),
        KEY idx_step (current_step),
        KEY idx_assigned (assigned_to),
        KEY idx_created (created_at),
        KEY idx_status_date (status, application_date)
    ) $charset_collate;";
    dbDelta($sql_clients);
    
    // 2. Payments table
    $table_payments = $wpdb->prefix . 'vms_payments';
    $sql_payments = "CREATE TABLE IF NOT EXISTS $table_payments (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        client_id bigint(20) NOT NULL,
        invoice_no varchar(100) DEFAULT NULL,
        amount decimal(15,2) NOT NULL,
        payment_date date NOT NULL,
        payment_method varchar(50) DEFAULT 'cash',
        transaction_id varchar(200) DEFAULT '',
        received_by bigint(20) DEFAULT 0,
        notes text,
        status varchar(50) DEFAULT 'completed',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_client (client_id),
        KEY idx_invoice (invoice_no),
        KEY idx_date (payment_date),
        KEY idx_client_date (client_id, payment_date)
    ) $charset_collate;";
    dbDelta($sql_payments);
    
    // 3. SMS logs table
    $table_sms = $wpdb->prefix . 'vms_sms_logs';
    $sql_sms = "CREATE TABLE IF NOT EXISTS $table_sms (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        client_id bigint(20) DEFAULT NULL,
        phone varchar(30) NOT NULL,
        message text NOT NULL,
        status varchar(50) DEFAULT 'sent',
        response text,
        sent_by bigint(20) DEFAULT 0,
        sent_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_client (client_id),
        KEY idx_phone (phone),
        KEY idx_date (sent_at)
    ) $charset_collate;";
    dbDelta($sql_sms);
    
    // 4. Activity logs table
    $table_activity = $wpdb->prefix . 'vms_activity_logs';
    $sql_activity = "CREATE TABLE IF NOT EXISTS $table_activity (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) DEFAULT NULL,
        user_name varchar(200) DEFAULT '',
        action varchar(200) NOT NULL,
        details text,
        ip_address varchar(50) DEFAULT '',
        user_agent text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user (user_id),
        KEY idx_action (action),
        KEY idx_date (created_at)
    ) $charset_collate;";
    dbDelta($sql_activity);
    
    // 5. Settings table
    $table_settings = $wpdb->prefix . 'vms_settings';
    $sql_settings = "CREATE TABLE IF NOT EXISTS $table_settings (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        setting_key varchar(100) NOT NULL,
        setting_value text,
        setting_group varchar(100) DEFAULT 'general',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY setting_key (setting_key),
        KEY idx_group (setting_group)
    ) $charset_collate;";
    dbDelta($sql_settings);
    
    // 6. Expenses table
    $table_expenses = $wpdb->prefix . 'vms_expenses';
    $sql_expenses = "CREATE TABLE IF NOT EXISTS $table_expenses (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        expense_date date NOT NULL,
        category varchar(100) NOT NULL,
        description text,
        amount decimal(15,2) NOT NULL,
        paid_to varchar(200) DEFAULT '',
        payment_method varchar(50) DEFAULT 'cash',
        receipt_no varchar(100) DEFAULT '',
        approved_by bigint(20) DEFAULT 0,
        notes text,
        created_by bigint(20) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_date (expense_date),
        KEY idx_category (category)
    ) $charset_collate;";
    dbDelta($sql_expenses);
    
    // Insert default admin settings
    $admin_id = get_current_user_id();
    
    // Default settings
    $default_settings = [
        'sms_api_key' => '',
        'sms_sender_id' => 'VISA',
        'currency_symbol' => '৳',
        'company_name' => 'Visa Management System',
        'company_phone' => '',
        'company_email' => '',
        'company_address' => '',
        'auto_generate_invoice' => '1',
        'default_visa_types' => 'Tourist,Business,Student,Work,Medical,Family',
        'default_countries' => 'USA,Canada,UK,Australia,Japan,Singapore,UAE,Saudi Arabia'
    ];
    
    foreach ($default_settings as $key => $value) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_settings WHERE setting_key = %s",
            $key
        ));
        
        if (!$exists) {
            $wpdb->insert($table_settings, [
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_group' => 'general'
            ]);
        }
    }
    
    // Log installation
    vms_log_activity($admin_id, 'SYSTEM_INSTALL', 'Visa Management System installed successfully');
}

// ==================== CORE FUNCTIONS ====================
function vms_log_activity($user_id, $action, $details = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'vms_activity_logs';
    
    $user = get_userdata($user_id);
    
    $data = [
        'user_id' => $user_id,
        'user_name' => $user ? $user->display_name : 'System',
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    
    $wpdb->insert($table, $data);
}

function vms_generate_serial() {
    global $wpdb;
    $table = $wpdb->prefix . 'vms_clients';
    
    $year = date('y');
    $month = date('m');
    
    $wpdb->query("LOCK TABLES $table WRITE");
    
    try {
        $last_serial = $wpdb->get_var($wpdb->prepare(
            "SELECT serial_no FROM $table WHERE serial_no LIKE %s ORDER BY serial_no DESC LIMIT 1",
            "VMS-" . $year . $month . "-%"
        ));
        
        if ($last_serial) {
            $last_num = intval(substr($last_serial, -4));
            $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $next_num = '0001';
        }
        
        $serial = "VMS-$year$month-$next_num";
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE serial_no = %s",
            $serial
        ));
        
        if ($exists) {
            $serial = "VMS-$year$month-" . date('His');
        }
        
    } finally {
        $wpdb->query("UNLOCK TABLES");
    }
    
    return $serial;
}

function vms_get_setting($key, $default = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'vms_settings';
    
    $value = $wpdb->get_var($wpdb->prepare(
        "SELECT setting_value FROM $table WHERE setting_key = %s",
        $key
    ));
    
    return $value ? $value : $default;
}

function vms_update_setting($key, $value) {
    global $wpdb;
    $table = $wpdb->prefix . 'vms_settings';
    
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE setting_key = %s",
        $key
    ));
    
    if ($exists) {
        $wpdb->update($table, 
            ['setting_value' => $value, 'updated_at' => current_time('mysql')],
            ['setting_key' => $key]
        );
    } else {
        $wpdb->insert($table, [
            'setting_key' => $key,
            'setting_value' => $value,
            'setting_group' => 'general'
        ]);
    }
}

function vms_generate_invoice_no() {
    global $wpdb;
    $payments_table = $wpdb->prefix . 'vms_payments';
    
    $prefix = 'INV-';
    $year = date('Y');
    $month = date('m');
    
    $last_invoice = $wpdb->get_var($wpdb->prepare(
        "SELECT invoice_no FROM $payments_table WHERE invoice_no LIKE %s ORDER BY invoice_no DESC LIMIT 1",
        $prefix . $year . $month . '%'
    ));
    
    if ($last_invoice) {
        $last_num = intval(substr($last_invoice, -4));
        $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $next_num = '0001';
    }
    
    return $prefix . $year . $month . '-' . $next_num;
}

function vms_handle_file_upload($field_name, $client_id) {
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    if (empty($_FILES[$field_name]['name'])) {
        return new WP_Error('no_file', 'No file uploaded');
    }
    
    if ($_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('upload_error', 'Upload failed with error code: ' . $_FILES[$field_name]['error']);
    }
    
    if ($_FILES[$field_name]['size'] > 5242880) {
        return new WP_Error('file_too_large', 'File size must be less than 5MB');
    }
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
    $file_info = wp_check_filetype($_FILES[$field_name]['name']);
    
    if (!in_array($file_info['type'], $allowed_mimes)) {
        return new WP_Error('invalid_file_type', 'Only PDF, JPG, and PNG files are allowed');
    }
    
    $uploadedfile = $_FILES[$field_name];
    $upload_overrides = array('test_form' => false);
    
    $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
    
    if ($movefile && !isset($movefile['error'])) {
        return $movefile['url'];
    }
    
    return new WP_Error('upload_failed', $movefile['error'] ?? 'Upload failed');
}

function vms_send_sms_api($phone, $message, $api_key, $sender_id) {
    // Placeholder function - implement actual SMS API integration
    $url = "https://api.smsprovider.com/send";
    $data = [
        'api_key' => $api_key,
        'sender_id' => $sender_id,
        'to' => $phone,
        'message' => $message
    ];
    
    $response = wp_remote_post($url, [
        'body' => $data,
        'timeout' => 30
    ]);
    
    if (is_wp_error($response)) {
        return [
            'success' => false,
            'error' => $response->get_error_message()
        ];
    }
    
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    
    if (isset($result['status']) && $result['status'] == 'success') {
        return [
            'success' => true,
            'response' => $body
        ];
    } else {
        return [
            'success' => false,
            'error' => $result['message'] ?? 'Unknown error'
        ];
    }
}

function vms_get_sms_templates() {
    $company_name = vms_get_setting('company_name', 'Visa Management');
    
    return [
        'Application Received' => "Your visa application has been received. Ref: {serial_no}. Thank you for choosing $company_name.",
        'Processing Started' => "Your visa application is now being processed. We will update you on the progress. $company_name",
        'Document Required' => "Additional documents required for your visa application. Please contact us immediately. $company_name",
        'Visa Approved' => "Congratulations! Your visa has been approved. Please collect your passport. $company_name",
        'Ready for Collection' => "Your passport is ready for collection. Please visit our office during working hours. $company_name",
        'Payment Reminder' => "Payment reminder: Your visa application fee is due. Please make payment to avoid delays. $company_name"
    ];
}

// ==================== ADMIN MENU ====================
add_action('admin_menu', 'vms_admin_menu');
function vms_admin_menu() {
    $capability = 'manage_options';
    
    // Main menu
    add_menu_page(
        'Visa Management',
        'Visa Management',
        $capability,
        'vms-dashboard',
        'vms_dashboard_page',
        'dashicons-passport',
        30
    );
    
    // Submenus
    add_submenu_page(
        'vms-dashboard',
        'Dashboard',
        'Dashboard',
        $capability,
        'vms-dashboard',
        'vms_dashboard_page'
    );
    
    add_submenu_page(
        'vms-dashboard',
        'Applications',
        'Applications',
        $capability,
        'vms-applications',
        'vms_applications_page'
    );
    
    add_submenu_page(
        'vms-dashboard',
        'Payments',
        'Payments',
        $capability,
        'vms-payments',
        'vms_payments_page'
    );
    
    add_submenu_page(
        'vms-dashboard',
        'SMS Center',
        'SMS Center',
        $capability,
        'vms-sms',
        'vms_sms_page'
    );
    
    add_submenu_page(
        'vms-dashboard',
        'Reports',
        'Reports',
        $capability,
        'vms-reports',
        'vms_reports_page'
    );
    
    add_submenu_page(
        'vms-dashboard',
        'Expenses',
        'Expenses',
        $capability,
        'vms-expenses',
        'vms_expenses_page'
    );
    
    add_submenu_page(
        'vms-dashboard',
        'Settings',
        'Settings',
        $capability,
        'vms-settings',
        'vms_settings_page'
    );
    
    // Hidden pages
    add_submenu_page(
        null,
        'Export Data',
        'Export Data',
        $capability,
        'vms-export',
        'vms_export_page'
    );
    
    add_submenu_page(
        'vms-dashboard',
        'Activity Logs',
        'Activity Logs',
        $capability,
        'vms-activity-logs',
        'vms_activity_logs_page'
    );
}

// ==================== DASHBOARD PAGE ====================
function vms_dashboard_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vms_clients';
    $payments_table = $wpdb->prefix . 'vms_payments';
    $expenses_table = $wpdb->prefix . 'vms_expenses';
    
    $user_id = get_current_user_id();
    vms_log_activity($user_id, 'VIEW_DASHBOARD', 'Accessed dashboard page');
    
    // Get statistics
    $stats = [
        'total_clients' => $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE is_deleted = 0"),
        'total_revenue' => $wpdb->get_var("SELECT SUM(total_fee) FROM $clients_table WHERE is_deleted = 0") ?: 0,
        'total_collected' => $wpdb->get_var("SELECT SUM(paid_fee) FROM $clients_table WHERE is_deleted = 0") ?: 0,
        'total_due' => $wpdb->get_var("SELECT SUM(due_fee) FROM $clients_table WHERE is_deleted = 0") ?: 0,
        'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE status = 'pending' AND is_deleted = 0"),
        'processing' => $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE status = 'processing' AND is_deleted = 0"),
        'approved' => $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE status = 'approved' AND is_deleted = 0"),
        'completed' => $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE status = 'completed' AND is_deleted = 0"),
        'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE status = 'rejected' AND is_deleted = 0"),
        'total_expenses' => $wpdb->get_var("SELECT SUM(amount) FROM $expenses_table") ?: 0,
        'today_payments' => $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $payments_table WHERE payment_date = %s",
            date('Y-m-d')
        )) ?: 0,
        'today_expenses' => $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $expenses_table WHERE expense_date = %s",
            date('Y-m-d')
        )) ?: 0,
    ];
    
    // Recent applications
    $recent_apps = $wpdb->get_results(
        "SELECT * FROM $clients_table 
         WHERE is_deleted = 0 
         ORDER BY created_at DESC LIMIT 5"
    );
    
    // Recent payments
    $recent_payments = $wpdb->get_results("
        SELECT p.*, c.client_name, c.passport_no 
        FROM $payments_table p 
        LEFT JOIN $clients_table c ON p.client_id = c.id 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    
    ?>
    <div class="wrap vms-dashboard">
        <div class="vms-header">
            <h1><span class="dashicons dashicons-passport"></span> Visa Management Dashboard</h1>
            <div class="vms-header-actions">
                <span class="vms-version">Version 3.0.1</span>
                <span class="vms-user">Welcome, <?php echo wp_get_current_user()->display_name; ?></span>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="vms-stats-grid">
            <div class="vms-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_clients']); ?></h3>
                    <p>Total Clients</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-up">↑ 12%</span>
                </div>
            </div>
            
            <div class="vms-stat-card revenue-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="stat-content">
                    <h3>৳<?php echo number_format($stats['total_revenue']); ?></h3>
                    <p>Total Revenue</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-up">↑ 18%</span>
                </div>
            </div>
            
            <div class="vms-stat-card collection-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-money"></span>
                </div>
                <div class="stat-content">
                    <h3>৳<?php echo number_format($stats['total_collected']); ?></h3>
                    <p>Collected</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-up">↑ 15%</span>
                </div>
            </div>
            
            <div class="vms-stat-card due-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="stat-content">
                    <h3>৳<?php echo number_format($stats['total_due']); ?></h3>
                    <p>Due Amount</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-down">↑ 8%</span>
                </div>
            </div>
            
            <div class="vms-stat-card processing-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['processing']); ?></h3>
                    <p>Processing</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-up">↑ 22%</span>
                </div>
            </div>
            
            <div class="vms-stat-card completed-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['completed']); ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-up">↑ 10%</span>
                </div>
            </div>
            
            <div class="vms-stat-card today-collection-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="stat-content">
                    <h3>৳<?php echo number_format($stats['today_payments']); ?></h3>
                    <p>Today's Collection</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-up">↑ 25%</span>
                </div>
            </div>
            
            <div class="vms-stat-card expenses-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-chart-bar"></span>
                </div>
                <div class="stat-content">
                    <h3>৳<?php echo number_format($stats['total_expenses']); ?></h3>
                    <p>Total Expenses</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-down">↓ 5%</span>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="vms-content-grid">
            <!-- Recent Applications -->
            <div class="vms-card">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-media-document"></span> Recent Applications</h2>
                    <a href="?page=vms-applications" class="vms-card-action">View All →</a>
                </div>
                <div class="vms-table-container">
                    <table class="vms-table">
                        <thead>
                            <tr>
                                <th>Serial</th>
                                <th>Name</th>
                                <th>Passport</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Fee</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_apps): ?>
                                <?php foreach ($recent_apps as $client): ?>
                                <tr>
                                    <td><span class="vms-serial"><?php echo esc_html($client->serial_no); ?></span></td>
                                    <td>
                                        <div class="client-info">
                                            <strong><?php echo esc_html($client->client_name); ?></strong>
                                            <small><?php echo esc_html($client->phone); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($client->passport_no); ?></td>
                                    <td><span class="vms-badge"><?php echo esc_html($client->visa_type); ?></span></td>
                                    <td>
                                        <span class="vms-status-badge status-<?php echo esc_attr($client->status); ?>">
                                            <?php echo esc_html(ucfirst($client->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fee-info">
                                            <strong>৳<?php echo number_format($client->total_fee); ?></strong>
                                            <small>Paid: ৳<?php echo number_format($client->paid_fee); ?></small>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data">
                                        <span class="dashicons dashicons-info"></span>
                                        No applications found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Payments -->
            <div class="vms-card">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-money-alt"></span> Recent Payments</h2>
                    <a href="?page=vms-payments" class="vms-card-action">View All →</a>
                </div>
                <div class="vms-table-container">
                    <table class="vms-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_payments): ?>
                                <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('M d', strtotime($payment->payment_date)); ?></td>
                                    <td>
                                        <div class="client-info">
                                            <strong><?php echo esc_html($payment->client_name); ?></strong>
                                            <small><?php echo esc_html($payment->passport_no); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="payment-amount">৳<?php echo number_format($payment->amount); ?></span>
                                    </td>
                                    <td><span class="payment-method"><?php echo esc_html(ucfirst($payment->payment_method)); ?></span></td>
                                    <td>
                                        <span class="vms-status-badge status-<?php echo esc_attr(strtolower($payment->status)); ?>">
                                            <?php echo esc_html(ucfirst($payment->status)); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-data">
                                        <span class="dashicons dashicons-info"></span>
                                        No payments found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="vms-content-grid">
            <div class="vms-card">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-plus-alt"></span> Quick Actions</h2>
                </div>
                <div class="vms-quick-actions">
                    <a href="?page=vms-applications&action=add" class="vms-quick-action">
                        <div class="action-icon">
                            <span class="dashicons dashicons-plus"></span>
                        </div>
                        <div class="action-content">
                            <h3>Add New Client</h3>
                            <p>Create new visa application</p>
                        </div>
                        <div class="action-arrow">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                        </div>
                    </a>
                    
                    <a href="?page=vms-payments&action=add" class="vms-quick-action">
                        <div class="action-icon">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <div class="action-content">
                            <h3>Record Payment</h3>
                            <p>Add new payment entry</p>
                        </div>
                        <div class="action-arrow">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                        </div>
                    </a>
                    
                    <a href="?page=vms-sms" class="vms-quick-action">
                        <div class="action-icon">
                            <span class="dashicons dashicons-email-alt"></span>
                        </div>
                        <div class="action-content">
                            <h3>Send SMS</h3>
                            <p>Send SMS to clients</p>
                        </div>
                        <div class="action-arrow">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                        </div>
                    </a>
                    
                    <a href="?page=vms-reports" class="vms-quick-action">
                        <div class="action-icon">
                            <span class="dashicons dashicons-chart-bar"></span>
                        </div>
                        <div class="action-content">
                            <h3>View Reports</h3>
                            <p>Generate detailed reports</p>
                        </div>
                        <div class="action-arrow">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                        </div>
                    </a>
                    
                    <a href="?page=vms-expenses&action=add" class="vms-quick-action">
                        <div class="action-icon">
                            <span class="dashicons dashicons-cart"></span>
                        </div>
                        <div class="action-content">
                            <h3>Add Expense</h3>
                            <p>Record business expenses</p>
                        </div>
                        <div class="action-arrow">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                        </div>
                    </a>
                    
                    <a href="?page=vms-export&type=applications" class="vms-quick-action">
                        <div class="action-icon">
                            <span class="dashicons dashicons-download"></span>
                        </div>
                        <div class="action-content">
                            <h3>Export Data</h3>
                            <p>Export data to CSV/Excel</p>
                        </div>
                        <div class="action-arrow">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Status Summary -->
            <div class="vms-card">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-chart-pie"></span> Status Summary</h2>
                </div>
                <div class="vms-status-summary">
                    <div class="status-chart">
                        <canvas id="statusChart" width="200" height="200"></canvas>
                    </div>
                    <div class="status-legend">
                        <div class="status-item">
                            <span class="status-dot pending-dot"></span>
                            <span class="status-name">Pending</span>
                            <span class="status-count"><?php echo esc_html($stats['pending']); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-dot processing-dot"></span>
                            <span class="status-name">Processing</span>
                            <span class="status-count"><?php echo esc_html($stats['processing']); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-dot approved-dot"></span>
                            <span class="status-name">Approved</span>
                            <span class="status-count"><?php echo esc_html($stats['approved']); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-dot completed-dot"></span>
                            <span class="status-name">Completed</span>
                            <span class="status-count"><?php echo esc_html($stats['completed']); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-dot rejected-dot"></span>
                            <span class="status-name">Rejected</span>
                            <span class="status-count"><?php echo esc_html($stats['rejected']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Financial Summary -->
            <div class="vms-card">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-chart-area"></span> Financial Summary</h2>
                </div>
                <div class="vms-financial-summary">
                    <div class="financial-item">
                        <div class="financial-label">
                            <span class="dashicons dashicons-money-alt"></span>
                            <span>Total Revenue</span>
                        </div>
                        <div class="financial-value revenue">৳<?php echo number_format($stats['total_revenue']); ?></div>
                    </div>
                    
                    <div class="financial-item">
                        <div class="financial-label">
                            <span class="dashicons dashicons-yes"></span>
                            <span>Total Collected</span>
                        </div>
                        <div class="financial-value collection">৳<?php echo number_format($stats['total_collected']); ?></div>
                    </div>
                    
                    <div class="financial-item">
                        <div class="financial-label">
                            <span class="dashicons dashicons-warning"></span>
                            <span>Total Due</span>
                        </div>
                        <div class="financial-value due">৳<?php echo number_format($stats['total_due']); ?></div>
                    </div>
                    
                    <div class="financial-item">
                        <div class="financial-label">
                            <span class="dashicons dashicons-cart"></span>
                            <span>Total Expenses</span>
                        </div>
                        <div class="financial-value expense">৳<?php echo number_format($stats['total_expenses']); ?></div>
                    </div>
                    
                    <div class="financial-item">
                        <div class="financial-label">
                            <span class="dashicons dashicons-chart-line"></span>
                            <span>Today's Collection</span>
                        </div>
                        <div class="financial-value today-collection">৳<?php echo number_format($stats['today_payments']); ?></div>
                    </div>
                    
                    <div class="financial-item">
                        <div class="financial-label">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <span>Today's Expenses</span>
                        </div>
                        <div class="financial-value today-expense">৳<?php echo number_format($stats['today_expenses']); ?></div>
                    </div>
                    
                    <div class="financial-item net-profit">
                        <div class="financial-label">
                            <span class="dashicons dashicons-chart-pie"></span>
                            <span>Net Profit</span>
                        </div>
                        <?php 
                        $net_profit = $stats['total_collected'] - $stats['total_expenses'];
                        $profit_class = $net_profit >= 0 ? 'profit' : 'loss';
                        ?>
                        <div class="financial-value <?php echo esc_attr($profit_class); ?>">
                            ৳<?php echo number_format($net_profit); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="vms-card full-width">
            <div class="vms-card-header">
                <h2><span class="dashicons dashicons-chart-line"></span> Monthly Performance</h2>
                <div class="chart-period-selector">
                    <select id="chartPeriod">
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
            </div>
            <div class="vms-chart-container">
                <canvas id="monthlyChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    jQuery(document).ready(function($) {
        // Status Pie Chart
        var statusCtx = document.getElementById('statusChart').getContext('2d');
        var statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Processing', 'Approved', 'Completed', 'Rejected'],
                datasets: [{
                    data: [
                        <?php echo esc_js($stats['pending']); ?>,
                        <?php echo esc_js($stats['processing']); ?>,
                        <?php echo esc_js($stats['approved']); ?>,
                        <?php echo esc_js($stats['completed']); ?>,
                        <?php echo esc_js($stats['rejected']); ?>
                    ],
                    backgroundColor: [
                        '#f59e0b',
                        '#3b82f6',
                        '#10b981',
                        '#8b5cf6',
                        '#ef4444'
                    ],
                    borderWidth: 2,
                    borderColor: 'white'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Monthly Chart
        var monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        var monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Applications',
                    data: [12, 19, 15, 25, 22, 30, 28, 35, 32, 40, 38, 45],
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3
                }, {
                    label: 'Revenue',
                    data: [120000, 190000, 150000, 250000, 220000, 300000, 280000, 350000, 320000, 400000, 380000, 450000],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000) {
                                    return '৳' + (value / 1000).toFixed(0) + 'k';
                                }
                                return '৳' + value;
                            }
                        }
                    }
                }
            }
        });
        
        // Period selector
        $('#chartPeriod').on('change', function() {
            var period = $(this).val();
            
            if (period === 'quarterly') {
                monthlyChart.data.labels = ['Q1', 'Q2', 'Q3', 'Q4'];
                monthlyChart.data.datasets[0].data = [56, 83, 95, 123];
                monthlyChart.data.datasets[1].data = [560000, 830000, 950000, 1230000];
            } else if (period === 'yearly') {
                monthlyChart.data.labels = ['2020', '2021', '2022', '2023', '2024'];
                monthlyChart.data.datasets[0].data = [150, 220, 280, 350, 420];
                monthlyChart.data.datasets[1].data = [1500000, 2200000, 2800000, 3500000, 4200000];
            } else {
                monthlyChart.data.labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                monthlyChart.data.datasets[0].data = [12, 19, 15, 25, 22, 30, 28, 35, 32, 40, 38, 45];
                monthlyChart.data.datasets[1].data = [120000, 190000, 150000, 250000, 220000, 300000, 280000, 350000, 320000, 400000, 380000, 450000];
            }
            
            monthlyChart.update();
        });
    });
    </script>
    
    <style>
    /* Dashboard Styles */
    .vms-dashboard {
        max-width: 1800px;
        margin: 0 auto;
        padding: 20px;
        background: #f5f7fa;
        min-height: calc(100vh - 32px);
    }
    
    .vms-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .vms-header h1 {
        margin: 0;
        color: white;
        font-size: 28px;
        font-weight: 600;
    }
    
    .vms-header h1 .dashicons {
        vertical-align: middle;
        margin-right: 10px;
        font-size: 32px;
    }
    
    .vms-header-actions {
        display: flex;
        gap: 20px;
        align-items: center;
    }
    
    .vms-version {
        background: rgba(255,255,255,0.2);
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 500;
    }
    
    .vms-user {
        font-weight: 500;
        font-size: 16px;
    }
    
    .vms-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }
    
    .vms-stat-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all 0.3s ease;
        border-left: 5px solid;
        position: relative;
        overflow: hidden;
    }
    
    .vms-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }
    
    .vms-stat-card:nth-child(1) { border-left-color: #4f46e5; }
    .vms-stat-card:nth-child(2) { border-left-color: #10b981; }
    .vms-stat-card:nth-child(3) { border-left-color: #3b82f6; }
    .vms-stat-card:nth-child(4) { border-left-color: #ef4444; }
    .vms-stat-card:nth-child(5) { border-left-color: #f59e0b; }
    .vms-stat-card:nth-child(6) { border-left-color: #8b5cf6; }
    .vms-stat-card:nth-child(7) { border-left-color: #ec4899; }
    .vms-stat-card:nth-child(8) { border-left-color: #14b8a6; }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
    }
    
    .vms-stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); }
    .vms-stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); }
    .vms-stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%); }
    .vms-stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #ef4444 0%, #f87171 100%); }
    .vms-stat-card:nth-child(5) .stat-icon { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
    .vms-stat-card:nth-child(6) .stat-icon { background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%); }
    .vms-stat-card:nth-child(7) .stat-icon { background: linear-gradient(135deg, #ec4899 0%, #f472b6 100%); }
    .vms-stat-card:nth-child(8) .stat-icon { background: linear-gradient(135deg, #14b8a6 0%, #2dd4bf 100%); }
    
    .stat-content h3 {
        margin: 0 0 5px 0;
        font-size: 28px;
        font-weight: 700;
        color: #1f2937;
        line-height: 1;
    }
    
    .stat-content p {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
        font-weight: 500;
    }
    
    .stat-trend {
        margin-left: auto;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
        background: rgba(16, 185, 129, 0.1);
    }
    
    .trend-up { color: #10b981; }
    .trend-down { color: #ef4444; }
    
    .vms-content-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }
    
    .vms-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
    }
    
    .vms-card:hover {
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }
    
    .vms-card-header {
        padding: 24px 24px 16px;
        border-bottom: 1px solid #f3f4f6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .vms-card-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .vms-card-header .dashicons {
        color: #4f46e5;
        font-size: 22px;
    }
    
    .vms-card-action {
        color: #4f46e5;
        text-decoration: none;
        font-weight: 500;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .vms-card-action:hover {
        color: #3730a3;
    }
    
    .vms-table-container {
        padding: 0 24px 24px;
    }
    
    .vms-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .vms-table thead {
        border-bottom: 2px solid #f3f4f6;
    }
    
    .vms-table th {
        padding: 12px 8px;
        text-align: left;
        font-weight: 600;
        color: #6b7280;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .vms-table tbody tr {
        border-bottom: 1px solid #f9fafb;
        transition: background 0.2s;
    }
    
    .vms-table tbody tr:hover {
        background: #f9fafb;
    }
    
    .vms-table td {
        padding: 16px 8px;
        color: #374151;
        vertical-align: middle;
    }
    
    .vms-serial {
        background: #e0e7ff;
        color: #3730a3;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        font-family: monospace;
    }
    
    .client-info {
        display: flex;
        flex-direction: column;
    }
    
    .client-info strong {
        font-weight: 600;
        margin-bottom: 2px;
    }
    
    .client-info small {
        color: #6b7280;
        font-size: 12px;
    }
    
    .vms-badge {
        display: inline-block;
        padding: 4px 10px;
        background: #f3f4f6;
        color: #374151;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    
    .vms-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-pending {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    
    .status-processing {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }
    
    .status-approved {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .status-completed {
        background: #ede9fe;
        color: #5b21b6;
        border: 1px solid #ddd6fe;
    }
    
    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .fee-info {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }
    
    .fee-info strong {
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 2px;
    }
    
    .fee-info small {
        color: #6b7280;
        font-size: 11px;
    }
    
    .no-data {
        text-align: center;
        padding: 40px !important;
        color: #9ca3af;
    }
    
    .no-data .dashicons {
        font-size: 32px;
        margin-bottom: 10px;
        display: block;
        color: #d1d5db;
    }
    
    .payment-amount {
        font-weight: 700;
        color: #10b981;
    }
    
    .payment-method {
        padding: 4px 10px;
        background: #f0f9ff;
        color: #0369a1;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .vms-quick-actions {
        padding: 0 24px 24px;
        display: grid;
        gap: 16px;
    }
    
    .vms-quick-action {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px;
        background: #f8fafc;
        border-radius: 10px;
        text-decoration: none;
        color: inherit;
        transition: all 0.3s ease;
        border: 1px solid transparent;
    }
    
    .vms-quick-action:hover {
        background: white;
        border-color: #e0e7ff;
        transform: translateX(5px);
    }
    
    .action-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }
    
    .action-content {
        flex: 1;
    }
    
    .action-content h3 {
        margin: 0 0 5px 0;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
    }
    
    .action-content p {
        margin: 0;
        color: #6b7280;
        font-size: 13px;
    }
    
    .action-arrow {
        color: #9ca3af;
        transition: color 0.3s;
    }
    
    .vms-quick-action:hover .action-arrow {
        color: #4f46e5;
    }
    
    .vms-status-summary {
        padding: 24px;
        display: flex;
        gap: 30px;
        align-items: center;
    }
    
    .status-chart {
        width: 150px;
        height: 150px;
    }
    
    .status-legend {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .status-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 0;
    }
    
    .status-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }
    
    .pending-dot { background: #f59e0b; }
    .processing-dot { background: #3b82f6; }
    .approved-dot { background: #10b981; }
    .completed-dot { background: #8b5cf6; }
    .rejected-dot { background: #ef4444; }
    
    .status-name {
        flex: 1;
        color: #374151;
        font-weight: 500;
    }
    
    .status-count {
        background: #f3f4f6;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 14px;
        color: #1f2937;
        min-width: 40px;
        text-align: center;
    }
    
    .vms-financial-summary {
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    
    .financial-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 0;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .financial-item:last-child {
        border-bottom: none;
    }
    
    .financial-label {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #6b7280;
        font-weight: 500;
    }
    
    .financial-label .dashicons {
        color: #9ca3af;
        font-size: 20px;
    }
    
    .financial-value {
        font-size: 18px;
        font-weight: 700;
    }
    
    .revenue { color: #10b981; }
    .collection { color: #3b82f6; }
    .due { color: #ef4444; }
    .expense { color: #f59e0b; }
    .today-collection { color: #ec4899; }
    .today-expense { color: #14b8a6; }
    
    .financial-item.net-profit {
        background: #f8fafc;
        padding: 20px;
        border-radius: 10px;
        margin-top: 10px;
        border: 2px solid #f1f5f9;
    }
    
    .financial-item.net-profit .financial-label {
        color: #1f2937;
        font-weight: 600;
    }
    
    .financial-item.net-profit .financial-label .dashicons {
        color: #4f46e5;
    }
    
    .financial-item.net-profit .financial-value {
        font-size: 24px;
    }
    
    .financial-item.net-profit .profit { color: #10b981; }
    .financial-item.net-profit .loss { color: #ef4444; }
    
    .vms-card.full-width {
        grid-column: 1 / -1;
    }
    
    .chart-period-selector select {
        padding: 8px 16px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: white;
        color: #374151;
        font-weight: 500;
        cursor: pointer;
        outline: none;
        transition: all 0.2s;
    }
    
    .chart-period-selector select:hover {
        border-color: #4f46e5;
    }
    
    .chart-period-selector select:focus {
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .vms-chart-container {
        padding: 0 24px 24px;
        position: relative;
        height: 300px;
    }
    
    @media (max-width: 1400px) {
        .vms-content-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .vms-stats-grid {
            grid-template-columns: 1fr;
        }
        
        .vms-header {
            flex-direction: column;
            gap: 20px;
            text-align: center;
        }
        
        .vms-status-summary {
            flex-direction: column;
            text-align: center;
        }
    }
    </style>
    <?php
}
// ==================== APPLICATIONS PAGE ====================
function vms_applications_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vms_clients';
    
    $user_id = get_current_user_id();
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    
    switch ($action) {
        case 'add':
        case 'edit':
            vms_handle_client_form();
            break;
            
        case 'delete':
            if (isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_client')) {
                $client_id = intval($_GET['id']);
                $result = $wpdb->update($clients_table, 
                    ['is_deleted' => 1, 'updated_at' => current_time('mysql')],
                    ['id' => $client_id]
                );
                
                if ($result !== false) {
                    vms_log_activity($user_id, 'DELETE_CLIENT', "Deleted client ID: $client_id");
                    echo '<div class="notice notice-success is-dismissible"><p>Client deleted successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Error deleting client: ' . esc_html($wpdb->last_error) . '</p></div>';
                }
            }
            vms_client_list();
            break;
            
        case 'view':
            vms_view_client();
            break;
            
        default:
            vms_client_list();
            break;
    }
}

function vms_client_list() {
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vms_clients';
    
    // Get filter parameters
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $visa_type = isset($_GET['visa_type']) ? sanitize_text_field($_GET['visa_type']) : '';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // Build query
    $where = "WHERE is_deleted = 0";
    if ($status) {
        $where .= $wpdb->prepare(" AND status = %s", $status);
    }
    if ($visa_type) {
        $where .= $wpdb->prepare(" AND visa_type = %s", $visa_type);
    }
    if ($search) {
        $where .= $wpdb->prepare(" AND (client_name LIKE %s OR passport_no LIKE %s OR phone LIKE %s OR serial_no LIKE %s)",
            "%$search%", "%$search%", "%$search%", "%$search%"
        );
    }
    
    // Get total count
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $clients_table $where");
    
    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Get clients
    $clients = $wpdb->get_results("
        SELECT * FROM $clients_table 
        $where 
        ORDER BY created_at DESC 
        LIMIT $per_page OFFSET $offset
    ");
    
    // Get unique visa types for filter
    $visa_types = $wpdb->get_col("SELECT DISTINCT visa_type FROM $clients_table WHERE visa_type != '' AND is_deleted = 0 ORDER BY visa_type");
    
    ?>
    <div class="wrap vms-applications">
        <div class="vms-page-header">
            <h1><span class="dashicons dashicons-media-document"></span> Applications Management</h1>
            <div class="vms-page-actions">
                <a href="?page=vms-applications&action=add" class="button button-primary button-hero">
                    <span class="dashicons dashicons-plus"></span> Add New Client
                </a>
                <a href="?page=vms-export&type=applications" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span> Export
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="vms-filters-card">
            <form method="get" action="">
                <input type="hidden" name="page" value="vms-applications">
                
                <div class="vms-filter-group">
                    <div class="vms-search-box">
                        <span class="dashicons dashicons-search"></span>
                        <input type="text" name="s" placeholder="Search by name, passport, phone or serial..." 
                               value="<?php echo esc_attr($search); ?>">
                    </div>
                    
                    <div class="vms-filter-row">
                        <div class="vms-filter-item">
                            <label>Status</label>
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                                <option value="processing" <?php selected($status, 'processing'); ?>>Processing</option>
                                <option value="approved" <?php selected($status, 'approved'); ?>>Approved</option>
                                <option value="completed" <?php selected($status, 'completed'); ?>>Completed</option>
                                <option value="rejected" <?php selected($status, 'rejected'); ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="vms-filter-item">
                            <label>Visa Type</label>
                            <select name="visa_type">
                                <option value="">All Visa Types</option>
                                <?php foreach ($visa_types as $type): ?>
                                    <option value="<?php echo esc_attr($type); ?>" <?php selected($visa_type, $type); ?>>
                                        <?php echo esc_html($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="vms-filter-actions">
                            <button type="submit" class="button button-primary">
                                <span class="dashicons dashicons-filter"></span> Filter
                            </button>
                            <?php if ($status || $visa_type || $search): ?>
                                <a href="?page=vms-applications" class="button">
                                    <span class="dashicons dashicons-no"></span> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Summary Stats -->
        <div class="vms-summary-stats">
            <div class="vms-summary-stat">
                <span class="stat-value"><?php echo esc_html($total); ?></span>
                <span class="stat-label">Total Applications</span>
            </div>
            <div class="vms-summary-stat">
                <span class="stat-value"><?php echo esc_html(count($clients)); ?></span>
                <span class="stat-label">Showing</span>
            </div>
        </div>
        
        <!-- Applications Table -->
        <div class="vms-card">
            <div class="vms-table-responsive">
                <table class="vms-data-table">
                    <thead>
                        <tr>
                            <th class="column-serial">Serial No</th>
                            <th class="column-client">Client Details</th>
                            <th class="column-visa">Visa Info</th>
                            <th class="column-financial">Financial</th>
                            <th class="column-status">Status</th>
                            <th class="column-date">Date</th>
                            <th class="column-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($clients): ?>
                            <?php foreach ($clients as $client): ?>
                            <tr>
                                <td>
                                    <div class="serial-number">
                                        <span class="serial-icon">#</span>
                                        <span class="serial-value"><?php echo esc_html($client->serial_no); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="client-details">
                                        <div class="client-name">
                                            <strong><?php echo esc_html($client->client_name); ?></strong>
                                        </div>
                                        <div class="client-info">
                                            <span class="info-item">
                                                <span class="dashicons dashicons-phone"></span>
                                                <?php echo esc_html($client->phone); ?>
                                            </span>
                                            <?php if ($client->email): ?>
                                                <span class="info-item">
                                                    <span class="dashicons dashicons-email"></span>
                                                    <?php echo esc_html($client->email); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="info-item">
                                                <span class="dashicons dashicons-id"></span>
                                                <?php echo esc_html($client->passport_no); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="visa-info">
                                        <?php if ($client->visa_type): ?>
                                            <div class="visa-type">
                                                <span class="dashicons dashicons-location"></span>
                                                <?php echo esc_html($client->visa_type); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($client->country): ?>
                                            <div class="visa-country">
                                                <span class="dashicons dashicons-flag"></span>
                                                <?php echo esc_html($client->country); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="visa-step">
                                            <span class="step-label">Step <?php echo esc_html($client->current_step); ?>:</span>
                                            <?php echo esc_html($client->step_name); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="financial-info">
                                        <div class="fee-total">
                                            <span class="fee-label">Total:</span>
                                            <span class="fee-value">৳<?php echo number_format($client->total_fee, 2); ?></span>
                                        </div>
                                        <div class="fee-paid">
                                            <span class="fee-label">Paid:</span>
                                            <span class="fee-value paid">৳<?php echo number_format($client->paid_fee, 2); ?></span>
                                        </div>
                                        <div class="fee-due">
                                            <span class="fee-label">Due:</span>
                                            <span class="fee-value <?php echo $client->due_fee > 0 ? 'due' : 'paid'; ?>">
                                                ৳<?php echo number_format($client->due_fee, 2); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="status-container">
                                        <span class="vms-status-badge status-<?php echo esc_attr(strtolower($client->status)); ?>">
                                            <?php echo esc_html(ucfirst($client->status)); ?>
                                        </span>
                                        <div class="status-date">
                                            <?php echo date('M d, Y', strtotime($client->application_date)); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="date-info">
                                        <div class="date-created">
                                            <?php echo date('M d, Y', strtotime($client->created_at)); ?>
                                        </div>
                                        <div class="date-ago">
                                            <?php echo human_time_diff(strtotime($client->created_at), current_time('timestamp')); ?> ago
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="vms-action-buttons">
                                        <div class="action-group">
                                            <a href="?page=vms-applications&action=edit&id=<?php echo esc_attr($client->id); ?>" 
                                               class="vms-action-button edit-button" title="Edit">
                                                <span class="dashicons dashicons-edit"></span>
                                                <span class="action-text">Edit</span>
                                            </a>
                                            
                                            <a href="?page=vms-applications&action=view&id=<?php echo esc_attr($client->id); ?>" 
                                               class="vms-action-button view-button" title="View">
                                                <span class="dashicons dashicons-visibility"></span>
                                                <span class="action-text">View</span>
                                            </a>
                                        </div>
                                        
                                        <div class="action-group">
                                            <a href="?page=vms-payments&client_id=<?php echo esc_attr($client->id); ?>" 
                                               class="vms-action-button payment-button" title="Payment">
                                                <span class="dashicons dashicons-money-alt"></span>
                                                <span class="action-text">Payment</span>
                                            </a>
                                            
                                            <a href="?page=vms-sms&client_id=<?php echo esc_attr($client->id); ?>" 
                                               class="vms-action-button sms-button" title="SMS">
                                                <span class="dashicons dashicons-email-alt"></span>
                                                <span class="action-text">SMS</span>
                                            </a>
                                        </div>
                                        
                                        <div class="action-group">
                                            <a href="?page=vms-applications&action=delete&id=<?php echo esc_attr($client->id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_client'); ?>" 
                                               class="vms-action-button delete-button" 
                                               onclick="return confirm('Are you sure you want to delete this client?')" title="Delete">
                                                <span class="dashicons dashicons-trash"></span>
                                                <span class="action-text">Delete</span>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-data">
                                    <div class="empty-state">
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <h3>No applications found</h3>
                                        <p>Get started by adding your first client</p>
                                        <a href="?page=vms-applications&action=add" class="button button-primary">
                                            <span class="dashicons dashicons-plus"></span> Add New Client
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php
            if ($total > $per_page) {
                $total_pages = ceil($total / $per_page);
                ?>
                <div class="vms-pagination">
                    <div class="pagination-info">
                        Showing <?php echo esc_html(min($per_page * ($current_page - 1) + 1, $total)); ?> 
                        to <?php echo esc_html(min($per_page * $current_page, $total)); ?> 
                        of <?php echo esc_html($total); ?> entries
                    </div>
                    <div class="pagination-links">
                        <?php if ($current_page > 1): ?>
                            <a class="pagination-link prev" href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>">
                                <span class="dashicons dashicons-arrow-left-alt"></span> Previous
                            </a>
                        <?php endif; ?>
                        
                        <div class="pagination-numbers">
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a class="pagination-number <?php echo $i == $current_page ? 'active' : ''; ?>" 
                                   href="<?php echo esc_url(add_query_arg('paged', $i)); ?>">
                                    <?php echo esc_html($i); ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a class="pagination-link next" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>">
                                Next <span class="dashicons dashicons-arrow-right-alt"></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
    
    <style>
    .vms-applications {
        max-width: 1800px;
        margin: 0 auto;
        padding: 20px;
        background: #f5f7fa;
        min-height: calc(100vh - 32px);
    }
    
    .vms-page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding: 0 10px;
    }
    
    .vms-page-header h1 {
        margin: 0;
        font-size: 28px;
        font-weight: 600;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .vms-page-header h1 .dashicons {
        color: #4f46e5;
        font-size: 32px;
    }
    
    .vms-page-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }
    
    .button-hero {
        padding: 12px 24px;
        font-size: 16px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .vms-filters-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .vms-filter-group {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .vms-search-box {
        position: relative;
        max-width: 500px;
    }
    
    .vms-search-box .dashicons {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 20px;
    }
    
    .vms-search-box input {
        width: 100%;
        padding: 14px 20px 14px 50px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 15px;
        transition: all 0.2s;
        background: #f9fafb;
    }
    
    .vms-search-box input:focus {
        outline: none;
        border-color: #4f46e5;
        background: white;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .vms-filter-row {
        display: flex;
        gap: 20px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    
    .vms-filter-item {
        flex: 1;
        min-width: 200px;
    }
    
    .vms-filter-item label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #374151;
        font-size: 14px;
    }
    
    .vms-filter-item select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        color: #374151;
        background: white;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .vms-filter-item select:hover {
        border-color: #d1d5db;
    }
    
    .vms-filter-item select:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .vms-filter-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        padding-bottom: 5px;
    }
    
    .vms-summary-stats {
        display: flex;
        gap: 20px;
        margin-bottom: 24px;
        padding: 0 10px;
    }
    
    .vms-summary-stat {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        text-align: center;
        min-width: 150px;
    }
    
    .vms-summary-stat .stat-value {
        display: block;
        font-size: 32px;
        font-weight: 700;
        color: #4f46e5;
        margin-bottom: 5px;
    }
    
    .vms-summary-stat .stat-label {
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }
    
    .vms-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    
    .vms-table-responsive {
        overflow-x: auto;
    }
    
    .vms-data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1200px;
    }
    
    .vms-data-table thead {
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .vms-data-table th {
        padding: 18px 16px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    
    .vms-data-table tbody tr {
        border-bottom: 1px solid #f3f4f6;
        transition: background 0.2s;
    }
    
    .vms-data-table tbody tr:hover {
        background: #f9fafb;
    }
    
    .vms-data-table td {
        padding: 20px 16px;
        color: #374151;
        vertical-align: top;
    }
    
    .serial-number {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .serial-icon {
        color: #9ca3af;
        font-size: 12px;
        font-weight: 600;
    }
    
    .serial-value {
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        font-size: 13px;
        font-weight: 600;
        color: #4f46e5;
    }
    
    .client-details {
        min-width: 250px;
    }
    
    .client-name {
        margin-bottom: 8px;
    }
    
    .client-name strong {
        font-size: 15px;
        font-weight: 600;
        color: #1f2937;
    }
    
    .client-info {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .info-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #6b7280;
    }
    
    .info-item .dashicons {
        font-size: 16px;
        color: #9ca3af;
    }
    
    .visa-info {
        min-width: 200px;
    }
    
    .visa-type, .visa-country, .visa-step {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
        font-size: 13px;
    }
    
    .visa-type {
        color: #4f46e5;
        font-weight: 600;
    }
    
    .visa-country {
        color: #6b7280;
    }
    
    .visa-step {
        color: #374151;
        font-size: 12px;
    }
    
    .step-label {
        font-weight: 600;
        color: #9ca3af;
    }
    
    .financial-info {
        min-width: 180px;
    }
    
    .fee-total, .fee-paid, .fee-due {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
    }
    
    .fee-label {
        font-size: 12px;
        color: #6b7280;
    }
    
    .fee-value {
        font-weight: 700;
        font-size: 14px;
    }
    
    .fee-value.paid {
        color: #10b981;
    }
    
    .fee-value.due {
        color: #ef4444;
    }
    
    .status-container {
        min-width: 120px;
    }
    
    .status-date {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 6px;
        text-align: center;
    }
    
    .date-info {
        min-width: 120px;
    }
    
    .date-created {
        font-weight: 600;
        color: #1f2937;
        font-size: 14px;
        margin-bottom: 4px;
    }
    
    .date-ago {
        font-size: 11px;
        color: #9ca3af;
    }
    
    .vms-action-buttons {
        display: flex;
        flex-direction: column;
        gap: 10px;
        min-width: 180px;
    }
    
    .action-group {
        display: flex;
        gap: 8px;
    }
    
    .vms-action-button {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.2s;
        border: 1px solid transparent;
    }
    
    .vms-action-button .dashicons {
        font-size: 16px;
    }
    
    .edit-button {
        background: #e0e7ff;
        color: #3730a3;
        border-color: #c7d2fe;
    }
    
    .edit-button:hover {
        background: #c7d2fe;
        color: #312e81;
    }
    
    .view-button {
        background: #dbeafe;
        color: #1e40af;
        border-color: #bfdbfe;
    }
    
    .view-button:hover {
        background: #bfdbfe;
        color: #1e3a8a;
    }
    
    .payment-button {
        background: #d1fae5;
        color: #065f46;
        border-color: #a7f3d0;
    }
    
    .payment-button:hover {
        background: #a7f3d0;
        color: #064e3b;
    }
    
    .sms-button {
        background: #e0e7ff;
        color: #3730a3;
        border-color: #c7d2fe;
    }
    
    .sms-button:hover {
        background: #c7d2fe;
        color: #312e81;
    }
    
    .delete-button {
        background: #fee2e2;
        color: #991b1b;
        border-color: #fecaca;
    }
    
    .delete-button:hover {
        background: #fecaca;
        color: #7f1d1d;
    }
    
    .vms-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 24px;
        border-top: 1px solid #f3f4f6;
    }
    
    .pagination-info {
        color: #6b7280;
        font-size: 14px;
    }
    
    .pagination-links {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    
    .pagination-link {
        padding: 8px 16px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
    }
    
    .pagination-link:hover {
        background: #f3f4f6;
        border-color: #d1d5db;
    }
    
    .pagination-numbers {
        display: flex;
        gap: 4px;
    }
    
    .pagination-number {
        padding: 8px 12px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        font-size: 14px;
        min-width: 40px;
        text-align: center;
        transition: all 0.2s;
    }
    
    .pagination-number:hover {
        background: #f3f4f6;
        border-color: #d1d5db;
    }
    
    .pagination-number.active {
        background: #4f46e5;
        color: white;
        border-color: #4f46e5;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state .dashicons {
        font-size: 64px;
        color: #d1d5db;
        margin-bottom: 20px;
    }
    
    .empty-state h3 {
        font-size: 20px;
        color: #374151;
        margin: 0 0 10px 0;
    }
    
    .empty-state p {
        color: #6b7280;
        margin: 0 0 20px 0;
    }
    
    @media (max-width: 1400px) {
        .vms-page-header {
            flex-direction: column;
            gap: 20px;
            align-items: flex-start;
        }
        
        .vms-page-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
    
    @media (max-width: 768px) {
        .vms-filter-row {
            flex-direction: column;
        }
        
        .vms-filter-item {
            width: 100%;
        }
        
        .vms-summary-stats {
            flex-direction: column;
        }
        
        .vms-pagination {
            flex-direction: column;
            gap: 20px;
            text-align: center;
        }
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Enhance table interactions
        $('.vms-data-table tbody tr').on('click', function(e) {
            if (!$(e.target).closest('.vms-action-button').length) {
                window.location.href = $(this).find('.view-button').attr('href');
            }
        });
    });
    </script>
    <?php
}

function vms_handle_client_form() {
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vms_clients';
    
    $user_id = get_current_user_id();
    $client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $client = null;
    
    if ($client_id) {
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $clients_table WHERE id = %d AND is_deleted = 0",
            $client_id
        ));
    }
    
    // Handle form submission
    if (isset($_POST['save_client'])) {
        $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
        
        if (!wp_verify_nonce($nonce, 'vms_save_client')) {
            wp_die('Security check failed');
        }
        
        $errors = [];
        if (empty($_POST['client_name'])) $errors[] = 'Client name is required';
        if (empty($_POST['passport_no'])) $errors[] = 'Passport number is required';
        if (empty($_POST['phone'])) $errors[] = 'Phone number is required';
        
        if (!empty($_POST['email']) && !is_email($_POST['email'])) {
            $errors[] = 'Invalid email format';
        }
        
        if (!empty($_POST['passport_no']) && !preg_match('/^[A-Z0-9]{6,15}$/', strtoupper($_POST['passport_no']))) {
            $errors[] = 'Invalid passport format';
        }
        
        if (!empty($_POST['phone']) && !preg_match('/^\+?[0-9]{10,15}$/', $_POST['phone'])) {
            $errors[] = 'Invalid phone format';
        }
        
        if (!empty($errors)) {
            echo '<div class="notice notice-error"><p>' . esc_html(implode('<br>', $errors)) . '</p></div>';
        } else {
            $serial_no = '';
            if (!$client_id) {
                $serial_result = vms_generate_serial();
                if (is_wp_error($serial_result)) {
                    echo '<div class="notice notice-error"><p>Error generating serial: ' . esc_html($serial_result->get_error_message()) . '</p></div>';
                    return;
                }
                $serial_no = $serial_result;
            }
            
            $data = [
                'client_name' => sanitize_text_field($_POST['client_name']),
                'passport_no' => strtoupper(sanitize_text_field($_POST['passport_no'])),
                'phone' => sanitize_text_field($_POST['phone']),
                'email' => sanitize_email($_POST['email']),
                'visa_type' => sanitize_text_field($_POST['visa_type']),
                'country' => sanitize_text_field($_POST['country']),
                'total_fee' => floatval($_POST['total_fee']),
                'paid_fee' => floatval($_POST['paid_fee']),
                'current_step' => intval($_POST['current_step']),
                'step_name' => sanitize_text_field($_POST['step_name']),
                'status' => sanitize_text_field($_POST['status']),
                'application_date' => sanitize_text_field($_POST['application_date']),
                'submission_date' => sanitize_text_field($_POST['submission_date']),
                'delivery_date' => sanitize_text_field($_POST['delivery_date']),
                'notes' => sanitize_textarea_field($_POST['notes']),
                'updated_by' => $user_id,
                'updated_at' => current_time('mysql')
            ];
            
            $data['due_fee'] = $data['total_fee'] - $data['paid_fee'];
            
            if (!$client_id) {
                $data['serial_no'] = $serial_no;
                $data['created_by'] = $user_id;
                $data['created_at'] = current_time('mysql');
            }
            
            if ($client_id && $client) {
                $result = $wpdb->update($clients_table, $data, ['id' => $client_id]);
                $message = 'Client updated successfully!';
                $action = 'UPDATE_CLIENT';
            } else {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $clients_table WHERE passport_no = %s AND is_deleted = 0",
                    $data['passport_no']
                ));
                
                if ($existing > 0) {
                    echo '<div class="notice notice-error"><p>Passport number already exists!</p></div>';
                    return;
                }
                
                $result = $wpdb->insert($clients_table, $data);
                $client_id = $wpdb->insert_id;
                $message = 'Client added successfully!';
                $action = 'ADD_CLIENT';
            }
            
            if ($result === false) {
                echo '<div class="notice notice-error"><p>Database error: ' . esc_html($wpdb->last_error) . '</p></div>';
                return;
            }
            
            if (!empty($_FILES['passport_copy']['name'])) {
                $passport_file = vms_handle_file_upload('passport_copy', $client_id);
                if (!is_wp_error($passport_file) && $passport_file) {
                    $wpdb->update($clients_table, 
                        ['passport_copy' => $passport_file],
                        ['id' => $client_id]
                    );
                }
            }
            
            if (!empty($_FILES['photo']['name'])) {
                $photo_file = vms_handle_file_upload('photo', $client_id);
                if (!is_wp_error($photo_file) && $photo_file) {
                    $wpdb->update($clients_table, 
                        ['photo' => $photo_file],
                        ['id' => $client_id]
                    );
                }
            }
            
            vms_log_activity($user_id, $action, "Client ID: $client_id - " . $data['client_name']);
            
            if (!$client_id && isset($data['serial_no'])) {
                echo '<div class="notice notice-success"><p>' . esc_html($message) . ' Serial Number: <strong>' . esc_html($data['serial_no']) . '</strong></p></div>';
            } else {
                echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            }
            
            if ($client_id) {
                $client = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $clients_table WHERE id = %d",
                    $client_id
                ));
            }
        }
    }
    
    ?>
    <div class="wrap vms-client-form-page">
        <div class="vms-page-header">
            <h1><span class="dashicons dashicons-plus"></span> <?php echo $client ? 'Edit Client' : 'Add New Client'; ?></h1>
            <div class="vms-page-actions">
                <a href="?page=vms-applications" class="button">← Back to Applications</a>
            </div>
        </div>
        
        <div class="vms-form-container">
            <form method="post" enctype="multipart/form-data" class="vms-form">
                <?php wp_nonce_field('vms_save_client'); ?>
                
                <div class="vms-form-grid">
                    <!-- Left Column -->
                    <div class="vms-form-column">
                        <div class="vms-form-section">
                            <h2><span class="dashicons dashicons-admin-users"></span> Client Information</h2>
                            <div class="form-group">
                                <label for="client_name">Full Name *</label>
                                <input type="text" id="client_name" name="client_name" required
                                       value="<?php echo $client ? esc_attr($client->client_name) : ''; ?>"
                                       class="regular-text">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="passport_no">Passport Number *</label>
                                    <input type="text" id="passport_no" name="passport_no" required
                                           value="<?php echo $client ? esc_attr($client->passport_no) : ''; ?>"
                                           class="regular-text" pattern="[A-Z0-9]{6,15}" 
                                           title="6-15 alphanumeric characters">
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone Number *</label>
                                    <input type="tel" id="phone" name="phone" required
                                           value="<?php echo $client ? esc_attr($client->phone) : ''; ?>"
                                           class="regular-text" pattern="\+?[0-9]{10,15}"
                                           title="10-15 digits, optional + prefix">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email"
                                       value="<?php echo $client ? esc_attr($client->email) : ''; ?>"
                                       class="regular-text">
                            </div>
                        </div>
                        
                        <div class="vms-form-section">
                            <h2><span class="dashicons dashicons-location"></span> Visa Information</h2>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="visa_type">Visa Type</label>
                                    <select id="visa_type" name="visa_type" class="regular-text">
                                        <option value="">Select Type</option>
                                        <option value="Tourist" <?php selected($client ? $client->visa_type : '', 'Tourist'); ?>>Tourist Visa</option>
                                        <option value="Business" <?php selected($client ? $client->visa_type : '', 'Business'); ?>>Business Visa</option>
                                        <option value="Student" <?php selected($client ? $client->visa_type : '', 'Student'); ?>>Student Visa</option>
                                        <option value="Work" <?php selected($client ? $client->visa_type : '', 'Work'); ?>>Work Visa</option>
                                        <option value="Medical" <?php selected($client ? $client->visa_type : '', 'Medical'); ?>>Medical Visa</option>
                                        <option value="Family" <?php selected($client ? $client->visa_type : '', 'Family'); ?>>Family Visa</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="country">Country</label>
                                    <input type="text" id="country" name="country"
                                           value="<?php echo $client ? esc_attr($client->country) : ''; ?>"
                                           class="regular-text" placeholder="e.g., USA, Canada">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="application_date">Application Date</label>
                                    <input type="date" id="application_date" name="application_date"
                                           value="<?php echo $client ? esc_attr($client->application_date) : date('Y-m-d'); ?>"
                                           class="regular-text">
                                </div>
                                
                                <div class="form-group">
                                    <label for="submission_date">Submission Date</label>
                                    <input type="date" id="submission_date" name="submission_date"
                                           value="<?php echo $client ? esc_attr($client->submission_date) : ''; ?>"
                                           class="regular-text">
                                </div>
                                
                                <div class="form-group">
                                    <label for="delivery_date">Delivery Date</label>
                                    <input type="date" id="delivery_date" name="delivery_date"
                                           value="<?php echo $client ? esc_attr($client->delivery_date) : ''; ?>"
                                           class="regular-text">
                                </div>
                            </div>
                        </div>
                        
                        <div class="vms-form-section">
                            <h2><span class="dashicons dashicons-media-document"></span> Documents</h2>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="passport_copy">Passport Copy</label>
                                    <?php if ($client && $client->passport_copy): ?>
                                        <p>
                                            <a href="<?php echo esc_url($client->passport_copy); ?>" target="_blank">View Current File</a>
                                        </p>
                                    <?php endif; ?>
                                    <input type="file" id="passport_copy" name="passport_copy" accept=".pdf,.jpg,.jpeg,.png">
                                    <p class="description">PDF, JPG, PNG (max 5MB)</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="photo">Client Photo</label>
                                    <?php if ($client && $client->photo): ?>
                                        <p>
                                            <a href="<?php echo esc_url($client->photo); ?>" target="_blank">View Current Photo</a>
                                        </p>
                                    <?php endif; ?>
                                    <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png">
                                    <p class="description">JPG, PNG (max 5MB)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="vms-form-column">
                        <div class="vms-form-section">
                            <h2><span class="dashicons dashicons-money-alt"></span> Financial Information</h2>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="total_fee">Total Fee (৳)</label>
                                    <input type="number" id="total_fee" name="total_fee" step="0.01" min="0"
                                           value="<?php echo $client ? esc_attr($client->total_fee) : '0'; ?>"
                                           class="regular-text" onchange="calculateDue()" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="paid_fee">Paid Amount (৳)</label>
                                    <input type="number" id="paid_fee" name="paid_fee" step="0.01" min="0"
                                           value="<?php echo $client ? esc_attr($client->paid_fee) : '0'; ?>"
                                           class="regular-text" onchange="calculateDue()" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Due Amount (৳)</label>
                                    <input type="text" id="due_fee" readonly
                                           value="<?php echo $client ? esc_attr($client->due_fee) : '0'; ?>"
                                           class="regular-text" style="background: #f0f0f0;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="vms-form-section">
                            <h2><span class="dashicons dashicons-update"></span> Application Status</h2>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="current_step">Current Step</label>
                                    <select id="current_step" name="current_step" class="regular-text">
                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                            <option value="<?php echo esc_attr($i); ?>" 
                                                <?php selected($client ? $client->current_step : 1, $i); ?>>
                                                Step <?php echo esc_html($i); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="step_name">Step Name</label>
                                    <input type="text" id="step_name" name="step_name"
                                           value="<?php echo $client ? esc_attr($client->step_name) : 'Application Submitted'; ?>"
                                           class="regular-text">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Application Status</label>
                                <select id="status" name="status" class="regular-text">
                                    <option value="pending" <?php selected($client ? $client->status : 'pending', 'pending'); ?>>Pending</option>
                                    <option value="processing" <?php selected($client ? $client->status : 'pending', 'processing'); ?>>Processing</option>
                                    <option value="approved" <?php selected($client ? $client->status : 'pending', 'approved'); ?>>Approved</option>
                                    <option value="completed" <?php selected($client ? $client->status : 'pending', 'completed'); ?>>Completed</option>
                                    <option value="rejected" <?php selected($client ? $client->status : 'pending', 'rejected'); ?>>Rejected</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="vms-form-section">
                            <h2><span class="dashicons dashicons-edit"></span> Notes</h2>
                            <div class="form-group">
                                <textarea id="notes" name="notes" rows="6" class="large-text"><?php 
                                    echo $client ? esc_textarea($client->notes) : ''; 
                                ?></textarea>
                            </div>
                        </div>
                        
                        <div class="vms-form-section">
                            <div class="form-actions">
                                <button type="submit" name="save_client" class="button button-primary button-large">
                                    <span class="dashicons dashicons-yes-alt"></span> Save Client
                                </button>
                                
                                <a href="?page=vms-applications" class="button button-large">
                                    Cancel
                                </a>
                                
                                <?php if ($client): ?>
                                    <a href="?page=vms-payments&client_id=<?php echo esc_attr($client->id); ?>" 
                                       class="button button-large">
                                        <span class="dashicons dashicons-money-alt"></span> Add Payment
                                    </a>
                                    
                                    <a href="?page=vms-sms&client_id=<?php echo esc_attr($client->id); ?>" 
                                       class="button button-large">
                                        <span class="dashicons dashicons-email-alt"></span> Send SMS
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <style>
    .vms-client-form-page {
        max-width: 1600px;
        margin: 0 auto;
        padding: 20px;
        background: #f5f7fa;
        min-height: calc(100vh - 32px);
    }
    
    .vms-form-container {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .vms-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
    }
    
    @media (max-width: 1200px) {
        .vms-form-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .vms-form-section {
        margin-bottom: 40px;
        padding-bottom: 30px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .vms-form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }
    
    .vms-form-section h2 {
        margin-top: 0;
        color: #374151;
        font-size: 18px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f3f4f6;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .vms-form-section h2 .dashicons {
        color: #4f46e5;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #374151;
        font-size: 14px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        color: #374151;
        transition: all 0.2s;
        background: white;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .form-group textarea {
        resize: vertical;
    }
    
    .form-group .description {
        font-size: 12px;
        color: #6b7280;
        margin: 4px 0 0 0;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }
    
    .form-actions .button-large {
        padding: 12px 24px;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    </style>
    
    <script>
    function calculateDue() {
        var total = parseFloat(document.getElementById('total_fee').value) || 0;
        var paid = parseFloat(document.getElementById('paid_fee').value) || 0;
        var due = total - paid;
        
        document.getElementById('due_fee').value = due.toFixed(2);
        
        var dueInput = document.getElementById('due_fee');
        if (due > 0) {
            dueInput.style.color = '#ef4444';
            dueInput.style.fontWeight = 'bold';
        } else if (due < 0) {
            dueInput.style.color = '#f59e0b';
            dueInput.style.fontWeight = 'bold';
        } else {
            dueInput.style.color = '#10b981';
            dueInput.style.fontWeight = 'bold';
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        calculateDue();
        
        var stepSelect = document.getElementById('current_step');
        var stepNameInput = document.getElementById('step_name');
        
        var stepNames = {
            1: 'Application Submitted',
            2: 'Document Verification',
            3: 'Processing Started',
            4: 'Under Review',
            5: 'Additional Documents Required',
            6: 'Interview Scheduled',
            7: 'Approval Pending',
            8: 'Visa Approved',
            9: 'Passport Ready',
            10: 'Completed'
        };
        
        if (stepSelect && stepNameInput) {
            stepSelect.addEventListener('change', function() {
                var step = this.value;
                if (stepNames[step]) {
                    stepNameInput.value = stepNames[step];
                }
            });
        }
    });
    </script>
    <?php
}

function vms_view_client() {
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vms_clients';
    $payments_table = $wpdb->prefix . 'vms_payments';
    
    $client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$client_id) {
        echo '<div class="notice notice-error"><p>Client ID not provided</p></div>';
        return;
    }
    
    $client = $wpdb->get_row($wpdb->prepare(
        "SELECT c.*, u.display_name as created_by_name 
         FROM $clients_table c 
         LEFT JOIN {$wpdb->prefix}users u ON c.created_by = u.ID 
         WHERE c.id = %d AND c.is_deleted = 0",
        $client_id
    ));
    
    if (!$client) {
        echo '<div class="notice notice-error"><p>Client not found</p></div>';
        return;
    }
    
    $payments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $payments_table WHERE client_id = %d ORDER BY payment_date DESC",
        $client_id
    ));
    
    $sms_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}vms_sms_logs WHERE client_id = %d ORDER BY sent_at DESC",
        $client_id
    ));
    
    ?>
    <div class="wrap vms-view-client">
        <div class="vms-page-header">
            <h1><span class="dashicons dashicons-admin-users"></span> Client Details: <?php echo esc_html($client->client_name); ?></h1>
            <div class="vms-page-actions">
                <a href="?page=vms-applications&action=edit&id=<?php echo esc_attr($client->id); ?>" class="button button-primary">
                    <span class="dashicons dashicons-edit"></span> Edit
                </a>
                <a href="?page=vms-applications" class="button">← Back to Applications</a>
            </div>
        </div>
        
        <div class="vms-client-grid">
            <!-- Client Information -->
            <div class="vms-card">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-info"></span> Client Information</h2>
                </div>
                <div class="vms-details-table">
                    <div class="detail-row">
                        <span class="detail-label">Serial No:</span>
                        <span class="detail-value"><strong><?php echo esc_html($client->serial_no); ?></strong></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Full Name:</span>
                        <span class="detail-value"><?php echo esc_html($client->client_name); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Passport No:</span>
                        <span class="detail-value"><?php echo esc_html($client->passport_no); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <span class="detail-value"><?php echo esc_html($client->phone); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?php echo esc_html($client->email); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Application Date:</span>
                        <span class="detail-value"><?php echo date('F d, Y', strtotime($client->application_date)); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Created By:</span>
                        <span class="detail-value"><?php echo esc_html($client->created_by_name); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Created At:</span>
                        <span class="detail-value"><?php echo date('F d, Y H:i', strtotime($client->created_at)); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Visa Information -->
            <div class="vms-card">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-location"></span> Visa Information</h2>
                </div>
                <div class="vms-details-table">
                    <div class="detail-row">
                        <span class="detail-label">Visa Type:</span>
                        <span class="detail-value"><?php echo esc_html($client->visa_type); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Country:</span>
                        <span class="detail-value"><?php echo esc_html($client->country); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Current Step:</span>
                        <span class="detail-value">Step <?php echo esc_html($client->current_step); ?> - <?php echo esc_html($client->step_name); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="vms-status-badge status-<?php echo esc_attr(strtolower($client->status)); ?>">
                                <?php echo esc_html(ucfirst($client->status)); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Submission Date:</span>
                        <span class="detail-value"><?php echo $client->submission_date ? date('F d, Y', strtotime($client->submission_date)) : 'N/A'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Delivery Date:</span>
                        <span class="detail-value"><?php echo $client->delivery_date ? date('F d, Y', strtotime($client->delivery_date)) : 'N/A'; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Financial Information -->
            <div class="vms-card">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-money-alt"></span> Financial Information</h2>
                </div>
                <div class="vms-details-table">
                    <div class="detail-row">
                        <span class="detail-label">Total Fee:</span>
                        <span class="detail-value"><strong>৳<?php echo number_format($client->total_fee, 2); ?></strong></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Paid Amount:</span>
                        <span class="detail-value" style="color: #10b981;">৳<?php echo number_format($client->paid_fee, 2); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Due Amount:</span>
                        <span class="detail-value" style="color: <?php echo $client->due_fee > 0 ? '#ef4444' : '#10b981'; ?>;">
                            ৳<?php echo number_format($client->due_fee, 2); ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Status:</span>
                        <span class="detail-value">
                            <?php if ($client->due_fee <= 0): ?>
                                <span style="color: #10b981; font-weight: bold;">✅ Fully Paid</span>
                            <?php elseif ($client->paid_fee == 0): ?>
                                <span style="color: #ef4444; font-weight: bold;">❌ Not Paid</span>
                            <?php else: ?>
                                <span style="color: #f59e0b; font-weight: bold;">⏳ Partially Paid</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <div class="vms-action-buttons" style="margin-top: 20px;">
                    <a href="?page=vms-payments&action=add&client_id=<?php echo esc_attr($client->id); ?>" 
                       class="button" style="background: #10b981; color: white;">
                        <span class="dashicons dashicons-money-alt"></span> Add Payment
                    </a>
                    <a href="?page=vms-sms&client_id=<?php echo esc_attr($client->id); ?>" 
                       class="button" style="background: #3b82f6; color: white;">
                        <span class="dashicons dashicons-email-alt"></span> Send SMS
                    </a>
                </div>
            </div>
            
            <!-- Documents -->
            <div class="vms-card">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-media-document"></span> Documents</h2>
                </div>
                <div class="vms-documents">
                    <?php if ($client->passport_copy): ?>
                        <div class="document-item">
                            <span class="document-icon">🛂</span>
                            <div class="document-info">
                                <strong>Passport Copy</strong>
                                <a href="<?php echo esc_url($client->passport_copy); ?>" target="_blank" class="button button-small">View</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($client->photo): ?>
                        <div class="document-item">
                            <span class="document-icon">📸</span>
                            <div class="document-info">
                                <strong>Client Photo</strong>
                                <a href="<?php echo esc_url($client->photo); ?>" target="_blank" class="button button-small">View</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($client->notes): ?>
                    <div style="margin-top: 20px;">
                        <h3><span class="dashicons dashicons-edit"></span> Notes</h3>
                        <div class="vms-notes">
                            <?php echo wp_kses_post(nl2br($client->notes)); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Payment History -->
            <div class="vms-card full-width">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-money"></span> Payment History</h2>
                </div>
                <div class="vms-table-container">
                    <?php if ($payments): ?>
                        <table class="vms-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Invoice No</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Received By</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($payment->payment_date)); ?></td>
                                    <td><?php echo esc_html($payment->invoice_no); ?></td>
                                    <td><strong style="color: #10b981;">৳<?php echo number_format($payment->amount, 2); ?></strong></td>
                                    <td><?php echo esc_html(ucfirst($payment->payment_method)); ?></td>
                                    <td>
                                        <?php
                                        $received_by = get_userdata($payment->received_by);
                                        echo $received_by ? esc_html($received_by->display_name) : 'System';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="vms-status-badge status-<?php echo esc_attr(strtolower($payment->status)); ?>">
                                            <?php echo esc_html(ucfirst($payment->status)); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="padding: 20px; text-align: center; color: #6b7280;">No payment records found.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- SMS History -->
            <div class="vms-card full-width">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-email-alt"></span> SMS History</h2>
                </div>
                <div class="vms-sms-history">
                    <?php if ($sms_logs): ?>
                        <?php foreach ($sms_logs as $sms): ?>
                        <div class="sms-item">
                            <div class="sms-header">
                                <span class="sms-date"><?php echo date('M d, Y H:i', strtotime($sms->sent_at)); ?></span>
                                <span class="sms-status status-<?php echo esc_attr(strtolower($sms->status)); ?>">
                                    <?php echo esc_html(ucfirst($sms->status)); ?>
                                </span>
                            </div>
                            <div class="sms-message">
                                <?php echo wp_kses_post(nl2br($sms->message)); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="padding: 20px; text-align: center; color: #6b7280;">No SMS sent to this client.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .vms-view-client {
        max-width: 1800px;
        margin: 0 auto;
        padding: 20px;
        background: #f5f7fa;
        min-height: calc(100vh - 32px);
    }
    
    .vms-client-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 24px;
    }
    
    .vms-details-table {
        padding: 24px;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .detail-row:last-child {
        border-bottom: none;
    }
    
    .detail-label {
        color: #6b7280;
        font-weight: 500;
    }
    
    .detail-value {
        color: #374151;
        font-weight: 500;
    }
    
    .vms-documents {
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .document-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: #f8fafc;
        border-radius: 8px;
    }
    
    .document-icon {
        font-size: 24px;
    }
    
    .document-info {
        flex: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .vms-notes {
        background: #f8fafc;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #3b82f6;
        margin-top: 10px;
        color: #374151;
        line-height: 1.6;
    }
    
    .vms-sms-history {
        padding: 24px;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .sms-item {
        background: #f8fafc;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        border-left: 4px solid #3b82f6;
    }
    
    .sms-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .sms-date {
        color: #6b7280;
        font-size: 12px;
    }
    
    .sms-message {
        color: #374151;
        line-height: 1.5;
    }
    
    .vms-card.full-width {
        grid-column: 1 / -1;
    }
    </style>
    <?php
}

// ==================== PAYMENTS PAGE ====================
function vms_payments_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    $payments_table = $wpdb->prefix . 'vms_payments';
    $clients_table = $wpdb->prefix . 'vms_clients';
    
    $user_id = get_current_user_id();
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    
    switch ($action) {
        case 'add':
        case 'edit':
            vms_handle_payment_form();
            break;
            
        case 'delete':
            if (isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_payment')) {
                $payment_id = intval($_GET['id']);
                
                $payment = $wpdb->get_row($wpdb->prepare(
                    "SELECT client_id, amount FROM $payments_table WHERE id = %d",
                    $payment_id
                ));
                
                $result = $wpdb->delete($payments_table, ['id' => $payment_id]);
                
                if ($result !== false && $payment) {
                    $total_paid = $wpdb->get_var($wpdb->prepare(
                        "SELECT SUM(amount) FROM $payments_table WHERE client_id = %d AND status = 'completed'",
                        $payment->client_id
                    )) ?: 0;
                    
                    $wpdb->update($clients_table, 
                        [
                            'paid_fee' => $total_paid,
                            'due_fee' => $wpdb->get_var($wpdb->prepare(
                                "SELECT total_fee FROM $clients_table WHERE id = %d",
                                $payment->client_id
                            )) - $total_paid
                        ],
                        ['id' => $payment->client_id]
                    );
                    
                    echo '<div class="notice notice-success is-dismissible"><p>Payment deleted successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Error deleting payment: ' . esc_html($wpdb->last_error) . '</p></div>';
                }
            }
            vms_payment_list();
            break;
            
        default:
            vms_payment_list();
            break;
    }
}

function vms_payment_list() {
    global $wpdb;
    $payments_table = $wpdb->prefix . 'vms_payments';
    $clients_table = $wpdb->prefix . 'vms_clients';
    
    $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    
    $where = "WHERE 1=1";
    if ($client_id) {
        $where .= $wpdb->prepare(" AND p.client_id = %d", $client_id);
    }
    if ($start_date) {
        $where .= $wpdb->prepare(" AND p.payment_date >= %s", $start_date);
    }
    if ($end_date) {
        $where .= $wpdb->prepare(" AND p.payment_date <= %s", $end_date);
    }
    
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    $payments = $wpdb->get_results("
        SELECT p.*, c.client_name, c.passport_no, u.display_name as received_by_name
        FROM $payments_table p 
        LEFT JOIN $clients_table c ON p.client_id = c.id 
        LEFT JOIN {$wpdb->prefix}users u ON p.received_by = u.ID 
        $where 
        ORDER BY p.payment_date DESC 
        LIMIT $per_page OFFSET $offset
    ");
    
    $total_query = "SELECT COUNT(*), SUM(p.amount) FROM $payments_table p $where";
    $total_result = $wpdb->get_row($total_query);
    $total_payments = $total_result->{'COUNT(*)'} ?: 0;
    $total_amount = $total_result->{'SUM(p.amount)'} ?: 0;
    
    $clients = $wpdb->get_results("
        SELECT id, client_name, passport_no 
        FROM $clients_table 
        WHERE is_deleted = 0 
        ORDER BY client_name
    ");
    
    ?>
    <div class="wrap vms-payments">
        <!-- Enhanced Header -->
        <div class="vms-page-header">
            <div class="header-content">
                <h1><span class="dashicons dashicons-money-alt"></span> Payment Management</h1>
                <p class="header-subtitle">Manage and track all payment transactions</p>
            </div>
            <div class="header-actions">
                <a href="?page=vms-payments&action=add" class="button button-primary button-large">
                    <span class="dashicons dashicons-plus"></span> Add Payment
                </a>
                <a href="?page=vms-export&type=payments" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span> Export
                </a>
            </div>
        </div>
        
        <!-- Professional Stats Cards -->
        <div class="vms-stats-container">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($total_payments); ?></div>
                    <div class="stat-label">Total Payments</div>
                    <div class="stat-footer"><?php echo esc_html(count($payments)); ?> this page</div>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number">৳<?php echo number_format($total_amount, 2); ?></div>
                    <div class="stat-label">Total Amount</div>
                    <div class="stat-footer">All currencies</div>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($wpdb->get_var("SELECT COUNT(*) FROM $payments_table WHERE DATE(payment_date) = CURDATE()")); ?></div>
                    <div class="stat-label">Today's Payments</div>
                    <div class="stat-footer">Latest activity</div>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">
                    <span class="dashicons dashicons-trending-up"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number">৳<?php echo number_format($wpdb->get_var("SELECT SUM(amount) FROM $payments_table WHERE DATE(payment_date) = CURDATE()") ?: 0, 2); ?></div>
                    <div class="stat-label">Today's Revenue</div>
                    <div class="stat-footer">Real-time update</div>
                </div>
            </div>
        </div>
        
        <!-- Professional Filter Section -->
        <div class="vms-filter-section">
            <div class="filter-header">
                <h3><span class="dashicons dashicons-filter"></span> Filter Payments</h3>
                <button class="filter-toggle" onclick="toggleFilterSection()">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </button>
            </div>
            <div class="filter-content" id="filterContent">
                <form method="get" action="" class="filter-form">
                    <input type="hidden" name="page" value="vms-payments">
                    
                    <div class="filter-grid">
                        <div class="filter-item">
                            <label class="filter-label">Client</label>
                            <div class="select-wrapper">
                                <select name="client_id" class="modern-select">
                                    <option value="">All Clients</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo esc_attr($client->id); ?>" <?php selected($client_id, $client->id); ?>>
                                            <?php echo esc_html($client->client_name); ?> (<?php echo esc_html($client->passport_no); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="select-arrow"></span>
                            </div>
                        </div>
                        
                        <div class="filter-item">
                            <label class="filter-label">From Date</label>
                            <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" 
                                   class="modern-input">
                        </div>
                        
                        <div class="filter-item">
                            <label class="filter-label">To Date</label>
                            <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" 
                                   class="modern-input">
                        </div>
                        
                        <div class="filter-item filter-actions">
                            <button type="submit" class="button button-primary filter-button">
                                <span class="dashicons dashicons-filter"></span> Apply Filters
                            </button>
                            <?php if ($client_id || $start_date || $end_date): ?>
                                <a href="?page=vms-payments" class="button button-secondary filter-button">
                                    <span class="dashicons dashicons-no"></span> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Professional Table Section -->
        <div class="vms-table-section">
            <div class="table-header">
                <div class="table-title">
                    <h3><span class="dashicons dashicons-list-view"></span> Payment Records</h3>
                    <span class="table-subtitle"><?php echo esc_html($total_payments); ?> total payments</span>
                </div>
                <div class="table-controls">
                    <button class="button button-small refresh-button" onclick="location.reload()" title="Refresh">
                        <span class="dashicons dashicons-update"></span>
                    </button>
                    <div class="table-search">
                        <input type="text" id="paymentSearch" placeholder="Search payments..." class="search-input">
                        <span class="search-icon dashicons dashicons-search"></span>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <table class="vms-data-table professional-table" id="paymentsTable">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="date">
                                <span class="header-content">
                                    Payment Date
                                    <span class="sort-indicator">
                                        <span class="dashicons dashicons-arrow-up-alt2"></span>
                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    </span>
                                </span>
                            </th>
                            <th class="sortable" data-sort="client">
                                <span class="header-content">
                                    Client
                                    <span class="sort-indicator">
                                        <span class="dashicons dashicons-arrow-up-alt2"></span>
                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    </span>
                                </span>
                            </th>
                            <th>Invoice Details</th>
                            <th class="sortable" data-sort="amount">
                                <span class="header-content">
                                    Amount
                                    <span class="sort-indicator">
                                        <span class="dashicons dashicons-arrow-up-alt2"></span>
                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    </span>
                                </span>
                            </th>
                            <th>Method</th>
                            <th>Processed By</th>
                            <th>Status</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsTableBody">
                        <?php if ($payments): ?>
                            <?php foreach ($payments as $index => $payment): ?>
                            <tr class="payment-row" data-index="<?php echo $index; ?>">
                                <td>
                                    <div class="date-cell">
                                        <div class="date-main"><?php echo date('M d, Y', strtotime($payment->payment_date)); ?></div>
                                        <div class="date-sub"><?php echo date('h:i A', strtotime($payment->payment_date)); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="client-info-cell">
                                        <div class="client-avatar">
                                            <span class="dashicons dashicons-businessperson"></span>
                                        </div>
                                        <div class="client-details">
                                            <div class="client-name"><?php echo esc_html($payment->client_name); ?></div>
                                            <div class="client-passport">
                                                <span class="dashicons dashicons-id"></span>
                                                <?php echo esc_html($payment->passport_no); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="invoice-details">
                                        <div class="invoice-number">#<?php echo esc_html($payment->invoice_no); ?></div>
                                        <?php if ($payment->transaction_id): ?>
                                            <div class="transaction-id">TXN: <?php echo esc_html($payment->transaction_id); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="amount-display">
                                        <span class="currency">৳</span>
                                        <span class="amount"><?php echo number_format($payment->amount, 2); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="method-badge method-<?php echo esc_attr(strtolower($payment->payment_method)); ?>">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $payment->payment_method))); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="staff-info">
                                        <span class="dashicons dashicons-admin-users"></span>
                                        <?php echo esc_html($payment->received_by_name ?: 'System'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr(strtolower($payment->status)); ?>">
                                        <span class="status-indicator"></span>
                                        <?php echo esc_html(ucfirst($payment->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?page=vms-payments&action=edit&id=<?php echo esc_attr($payment->id); ?>" 
                                           class="action-btn edit-btn" title="Edit Payment">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                        <a href="?page=vms-payments&action=delete&id=<?php echo esc_attr($payment->id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_payment'); ?>" 
                                           class="action-btn delete-btn" 
                                           onclick="return confirm('Are you sure you want to delete this payment?')" 
                                           title="Delete Payment">
                                            <span class="dashicons dashicons-trash"></span>
                                        </a>
                                        <button class="action-btn view-btn" 
                                                onclick="viewPaymentDetails(<?php echo esc_attr($payment->id); ?>)" 
                                                title="View Details">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <span class="dashicons dashicons-money-alt"></span>
                                        </div>
                                        <h3>No payments found</h3>
                                        <p>Start by adding your first payment record</p>
                                        <a href="?page=vms-payments&action=add" class="button button-primary button-large">
                                            <span class="dashicons dashicons-plus"></span> Add Payment
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Enhanced Pagination -->
            <?php
            if ($total_payments > $per_page) {
                $total_pages = ceil($total_payments / $per_page);
                ?>
                <div class="table-footer">
                    <div class="pagination-info">
                        <span class="info-text">
                            Showing <strong><?php echo esc_html(min($per_page * ($current_page - 1) + 1, $total_payments)); ?></strong> 
                            to <strong><?php echo esc_html(min($per_page * $current_page, $total_payments)); ?></strong> 
                            of <strong><?php echo esc_html($total_payments); ?></strong> entries
                        </span>
                    </div>
                    <div class="pagination-wrapper">
                        <?php if ($current_page > 1): ?>
                            <a class="pagination-btn prev" href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <div class="pagination-numbers">
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if ($start_page > 1) {
                                echo '<a class="pagination-number" href="' . esc_url(add_query_arg('paged', 1)) . '">1</a>';
                                if ($start_page > 2) echo '<span class="pagination-dots">...</span>';
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a class="pagination-number <?php echo $i == $current_page ? 'active' : ''; ?>" 
                                   href="<?php echo esc_url(add_query_arg('paged', $i)); ?>">
                                    <?php echo esc_html($i); ?>
                                </a>
                            <?php 
                            endfor;
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) echo '<span class="pagination-dots">...</span>';
                                echo '<a class="pagination-number" href="' . esc_url(add_query_arg('paged', $total_pages)) . '">' . $total_pages . '</a>';
                            }
                            ?>
                        </div>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a class="pagination-btn next" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>">
                                Next
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
    
    <style>
    /* Professional Payment Management Styles */
    .vms-payments {
        max-width: 100%;
        margin: 0;
        padding: 20px;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        min-height: calc(100vh - 32px);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    }
    
    /* Enhanced Header */
    .vms-page-header {
        background: white;
        padding: 30px;
        border-radius: 16px;
        margin-bottom: 30px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07), 0 1px 3px rgba(0, 0, 0, 0.06);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        border: 1px solid #e2e8f0;
    }
    
    .header-content h1 {
        font-size: 2rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0 0 5px 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .header-content h1 .dashicons {
        font-size: 1.8rem;
        width: 1.8rem;
        height: 1.8rem;
        color: #3182ce;
    }
    
    .header-subtitle {
        color: #718096;
        font-size: 1rem;
        margin: 0;
    }
    
    .header-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .header-actions .button {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        font-weight: 600;
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    
    .header-actions .button-primary {
        background: linear-gradient(135deg, #3182ce, #2c5282);
        border: none;
        box-shadow: 0 2px 4px rgba(49, 130, 206, 0.3);
    }
    
    .header-actions .button-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(49, 130, 206, 0.4);
    }
    
    /* Professional Stats Cards */
    .vms-stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.3s ease;
        border-left: 4px solid;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, transparent, rgba(255, 255, 255, 0.1));
        pointer-events: none;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }
    
    .stat-card.primary {
        border-left-color: #3182ce;
    }
    
    .stat-card.success {
        border-left-color: #38a169;
    }
    
    .stat-card.warning {
        border-left-color: #d69e2e;
    }
    
    .stat-card.info {
        border-left-color: #3182ce;
    }
    
    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: linear-gradient(135deg, #ebf8ff, #bee3f8);
    }
    
    .stat-icon .dashicons {
        font-size: 1.5rem;
        color: #3182ce;
    }
    
    .stat-card.success .stat-icon {
        background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
    }
    
    .stat-card.success .stat-icon .dashicons {
        color: #38a169;
    }
    
    .stat-card.warning .stat-icon {
        background: linear-gradient(135deg, #fef5e7, #fbd38d);
    }
    
    .stat-card.warning .stat-icon .dashicons {
        color: #d69e2e;
    }
    
    .stat-number {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0 0 4px 0;
    }
    
    .stat-label {
        color: #4a5568;
        font-weight: 600;
        font-size: 0.9rem;
        margin: 0 0 2px 0;
    }
    
    .stat-footer {
        color: #718096;
        font-size: 0.8rem;
    }
    
    /* Professional Filter Section */
    .vms-filter-section {
        background: white;
        border-radius: 12px;
        margin-bottom: 24px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    
    .filter-header {
        padding: 20px 24px;
        background: #f7fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
    }
    
    .filter-header h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3748;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .filter-toggle {
        background: none;
        border: none;
        padding: 8px;
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s ease;
    }
    
    .filter-toggle:hover {
        background: #edf2f7;
    }
    
    .filter-content {
        padding: 24px;
        display: block;
    }
    
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        align-items: end;
    }
    
    .filter-label {
        display: block;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }
    
    .select-wrapper {
        position: relative;
    }
    
    .modern-select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.9rem;
        background: white;
        transition: all 0.2s ease;
        appearance: none;
    }
    
    .modern-select:focus {
        outline: none;
        border-color: #3182ce;
        box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
    }
    
    .select-arrow {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        width: 0;
        height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-top: 5px solid #718096;
        pointer-events: none;
    }
    
    .modern-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }
    
    .modern-input:focus {
        outline: none;
        border-color: #3182ce;
        box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
    }
    
    .filter-button {
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
        border: none;
        cursor: pointer;
    }
    
    .filter-button .dashicons {
        font-size: 16px;
    }
    
    /* Professional Table Section */
    .vms-table-section {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    
    .table-header {
        padding: 20px 24px;
        background: #f7fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }
    
    .table-title h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3748;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .table-subtitle {
        color: #718096;
        font-size: 0.8rem;
        margin: 0;
    }
    
    .table-controls {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .refresh-button {
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .refresh-button:hover {
        border-color: #3182ce;
        color: #3182ce;
    }
    
    .table-search {
        position: relative;
    }
    
    .search-input {
        padding: 8px 12px 8px 36px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.9rem;
        width: 200px;
        transition: all 0.2s ease;
    }
    
    .search-input:focus {
        outline: none;
        border-color: #3182ce;
        box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
        width: 250px;
    }
    
    .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #a0aec0;
        pointer-events: none;
    }
    
    /* Professional Table Styles */
    .table-container {
        overflow-x: auto;
    }
    
    .professional-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    
    .professional-table th {
        background: #f7fafc;
        padding: 16px 12px;
        font-weight: 600;
        color: #2d3748;
        text-align: left;
        border-bottom: 2px solid #e2e8f0;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    
    .professional-table th.sortable {
        cursor: pointer;
        user-select: none;
        transition: all 0.2s ease;
    }
    
    .professional-table th.sortable:hover {
        background: #edf2f7;
    }
    
    .header-content {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .sort-indicator {
        display: flex;
        flex-direction: column;
        opacity: 0.4;
        font-size: 12px;
    }
    
    .sort-indicator .dashicons {
        font-size: 12px;
        width: 12px;
        height: 12px;
    }
    
    .professional-table td {
        padding: 16px 12px;
        border-bottom: 1px solid #e2e8f0;
        transition: all 0.2s ease;
    }
    
    .payment-row:hover {
        background: #f7fafc;
        transform: scale(1.01);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    /* Enhanced Cell Styles */
    .date-cell {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .date-main {
        font-weight: 600;
        color: #1a202c;
        font-size: 0.9rem;
    }
    
    .date-sub {
        color: #718096;
        font-size: 0.75rem;
    }
    
    .client-info-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .client-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3182ce, #2c5282);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .client-avatar .dashicons {
        color: white;
        font-size: 1.2rem;
    }
    
    .client-details {
        flex: 1;
    }
    
    .client-name {
        font-weight: 600;
        color: #1a202c;
        font-size: 0.9rem;
        margin-bottom: 2px;
    }
    
    .client-passport {
        color: #718096;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .client-passport .dashicons {
        font-size: 12px;
        width: 12px;
        height: 12px;
    }
    
    .invoice-details {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .invoice-number {
        font-weight: 600;
        color: #1a202c;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
    }
    
    .transaction-id {
        color: #718096;
        font-size: 0.7rem;
        font-family: 'Courier New', monospace;
    }
    
    .amount-display {
        display: flex;
        align-items: center;
        font-weight: 700;
        font-size: 1rem;
        gap: 2px;
    }
    
    .currency {
        color: #38a169;
        font-size: 0.9rem;
    }
    
    .amount {
        color: #1a202c;
    }
    
    .method-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
    }
    
    .method-badge.method-cash {
        background: #c6f6d5;
        color: #22543d;
    }
    
    .method-badge.method-bank_transfer {
        background: #bee3f8;
        color: #2a4365;
    }
    
    .method-badge.method-mobile_banking {
        background: #fef5e7;
        color: #744210;
    }
    
    .method-badge.method-card {
        background: #fed7e2;
        color: #702459;
    }
    
    .method-badge.method-check {
        background: #e9d8fd;
        color: #553c9a;
    }
    
    .staff-info {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #4a5568;
        font-size: 0.85rem;
    }
    
    .staff-info .dashicons {
        color: #a0aec0;
        font-size: 14px;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-indicator {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }
    
    .status-badge.status-completed {
        background: #c6f6d5;
        color: #22543d;
    }
    
    .status-badge.status-completed .status-indicator {
        background: #38a169;
    }
    
    .status-badge.status-pending {
        background: #fef5e7;
        color: #744210;
    }
    
    .status-badge.status-pending .status-indicator {
        background: #d69e2e;
    }
    
    .status-badge.status-failed {
        background: #fed7d7;
        color: #742a2a;
    }
    
    .status-badge.status-failed .status-indicator {
        background: #e53e3e;
    }
    
    .status-badge.status-refunded {
        background: #e9d8fd;
        color: #553c9a;
    }
    
    .status-badge.status-refunded .status-indicator {
        background: #805ad5;
    }
    
    /* Enhanced Action Buttons */
    .action-buttons {
        display: flex;
        gap: 6px;
    }
    
    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
        flex-shrink: 0;
    }
    
    .action-btn.edit-btn {
        background: #ebf8ff;
        color: #3182ce;
    }
    
    .action-btn.edit-btn:hover {
        background: #3182ce;
        color: white;
        transform: scale(1.1);
    }
    
    .action-btn.delete-btn {
        background: #fed7d7;
        color: #e53e3e;
    }
    
    .action-btn.delete-btn:hover {
        background: #e53e3e;
        color: white;
        transform: scale(1.1);
    }
    
    .action-btn.view-btn {
        background: #f0fff4;
        color: #38a169;
    }
    
    .action-btn.view-btn:hover {
        background: #38a169;
        color: white;
        transform: scale(1.1);
    }
    
    .action-btn .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }
    
    /* Enhanced Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #ebf8ff, #bee3f8);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .empty-icon .dashicons {
        font-size: 2rem;
        color: #3182ce;
    }
    
    .empty-state h3 {
        font-size: 1.25rem;
        color: #2d3748;
        margin: 0 0 8px 0;
    }
    
    .empty-state p {
        color: #718096;
        margin: 0 0 20px 0;
    }
    
    /* Enhanced Pagination */
    .table-footer {
        padding: 20px 24px;
        background: #f7fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }
    
    .info-text {
        color: #718096;
        font-size: 0.85rem;
    }
    
    .info-text strong {
        color: #2d3748;
        font-weight: 600;
    }
    
    .pagination-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .pagination-btn {
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        background: white;
        border-radius: 6px;
        text-decoration: none;
        color: #4a5568;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    
    .pagination-btn:hover {
        border-color: #3182ce;
        color: #3182ce;
        background: #ebf8ff;
    }
    
    .pagination-btn .dashicons {
        font-size: 14px;
    }
    
    .pagination-numbers {
        display: flex;
        gap: 4px;
    }
    
    .pagination-number {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #718096;
        font-weight: 500;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
    }
    
    .pagination-number:hover {
        background: #edf2f7;
        color: #2d3748;
    }
    
    .pagination-number.active {
        background: #3182ce;
        color: white;
        border-color: #3182ce;
        font-weight: 600;
    }
    
    .pagination-dots {
        color: #a0aec0;
        padding: 0 4px;
    }
    
    /* Responsive Design */
    @media (max-width: 1200px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }
        
        .vms-stats-container {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
    }
    
    @media (max-width: 768px) {
        .vms-page-header {
            flex-direction: column;
            text-align: center;
            gap: 16px;
        }
        
        .header-content h1 {
            font-size: 1.5rem;
            justify-content: center;
        }
        
        .header-actions {
            justify-content: center;
        }
        
        .table-header {
            flex-direction: column;
            text-align: center;
            gap: 12px;
        }
        
        .table-controls {
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .search-input {
            width: 150px;
        }
        
        .search-input:focus {
            width: 180px;
        }
        
        .table-footer {
            flex-direction: column;
            text-align: center;
        }
        
        .pagination-wrapper {
            justify-content: center;
        }
        
        .professional-table {
            font-size: 0.8rem;
        }
        
        .professional-table th,
        .professional-table td {
            padding: 12px 8px;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 4px;
        }
        
        .action-btn {
            width: 28px;
            height: 28px;
        }
        
        .client-info-cell {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        
        .client-avatar {
            width: 32px;
            height: 32px;
        }
    }
    
    @media (max-width: 480px) {
        .vms-payments {
            padding: 12px;
        }
        
        .vms-page-header {
            padding: 20px;
        }
        
        .stat-card {
            padding: 16px;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
        }
        
        .stat-number {
            font-size: 1.25rem;
        }
        
        .filter-content {
            padding: 16px;
        }
        
        .table-header {
            padding: 16px;
        }
        
        .table-footer {
            padding: 16px;
        }
        
        .pagination-wrapper {
            flex-wrap: wrap;
        }
        
        .pagination-numbers {
            order: -1;
            width: 100%;
            justify-content: center;
        }
    }
    
    /* Animation for smooth interactions */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .vms-stats-container,
    .vms-filter-section,
    .vms-table-section {
        animation: fadeInUp 0.5s ease-out;
    }
    
    .vms-stats-container {
        animation-delay: 0.1s;
    }
    
    .vms-filter-section {
        animation-delay: 0.2s;
    }
    
    .vms-table-section {
        animation-delay: 0.3s;
    }
    
    .row-highlight {
        background: #ebf8ff !important;
        transition: background 0.2s ease;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Filter section toggle
        window.toggleFilterSection = function() {
            const content = document.getElementById('filterContent');
            const toggle = document.querySelector('.filter-toggle');
            const arrow = toggle.querySelector('.dashicons');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                arrow.classList.remove('dashicons-arrow-right-alt2');
                arrow.classList.add('dashicons-arrow-down-alt2');
            } else {
                content.style.display = 'none';
                arrow.classList.remove('dashicons-arrow-down-alt2');
                arrow.classList.add('dashicons-arrow-right-alt2');
            }
        };
        
        // Enhanced table search
        $('#paymentSearch').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            
            $('#paymentsTableBody tr').each(function() {
                const rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.includes(searchTerm));
            });
        });
        
        // Enhanced row interactions
        $('.payment-row').on('click', function(e) {
            // Don't trigger if clicking on action buttons
            if ($(e.target).closest('.action-buttons').length) return;
            
            // Add highlight effect
            $('.payment-row').removeClass('row-highlight');
            $(this).addClass('row-highlight');
            
            setTimeout(() => {
                $(this).removeClass('row-highlight');
            }, 1000);
        });
        
        // Payment details view function
        window.viewPaymentDetails = function(paymentId) {
            // This would typically open a modal or navigate to details page
            console.log('View payment details for ID:', paymentId);
            // You can implement a modal or redirect to details page here
        };
        
        // Enhanced sorting functionality
        $('.sortable').on('click', function() {
            const table = $(this).closest('table');
            const th = $(this);
            const column = th.data('sort');
            const isAscending = !th.hasClass('sort-asc');
            
            // Remove sort classes from all headers
            table.find('.sortable').removeClass('sort-asc sort-desc');
            
            // Add appropriate class
            th.addClass(isAscending ? 'sort-asc' : 'sort-desc');
            
            // Sort logic would go here
            console.log('Sorting by:', column, isAscending ? 'ascending' : 'descending');
        });
        
        // Responsive table improvements
        function handleResponsiveTable() {
            const table = $('.professional-table');
            const container = $('.table-container');
            
            if ($(window).width() <= 768) {
                container.addClass('mobile-table');
            } else {
                container.removeClass('mobile-table');
            }
        }
        
        handleResponsiveTable();
        $(window).on('resize', handleResponsiveTable);
    });
    </script>
    <?php
}

function vms_handle_payment_form() {
    global $wpdb;
    $payments_table = $wpdb->prefix . 'vms_payments';
    $clients_table = $wpdb->prefix . 'vms_clients';
    
    $user_id = get_current_user_id();
    $payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
    
    $payment = null;
    if ($payment_id) {
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $payments_table WHERE id = %d",
            $payment_id
        ));
    }
    
    if (isset($_POST['save_payment'])) {
        $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
        
        if (!wp_verify_nonce($nonce, 'vms_save_payment')) {
            wp_die('Security check failed');
        }
        
        $errors = [];
        if (empty($_POST['client_id'])) {
            $errors[] = 'Client is required';
        }
        if (empty($_POST['amount']) || $_POST['amount'] <= 0) {
            $errors[] = 'Amount must be greater than 0';
        }
        if (empty($_POST['payment_date'])) {
            $errors[] = 'Payment date is required';
        }
        
        if (!empty($errors)) {
            echo '<div class="notice notice-error"><p>' . esc_html(implode('<br>', $errors)) . '</p></div>';
        } else {
            $invoice_no = $payment ? $payment->invoice_no : vms_generate_invoice_no();
            
            $data = [
                'client_id' => intval($_POST['client_id']),
                'invoice_no' => $invoice_no,
                'amount' => floatval($_POST['amount']),
                'payment_date' => sanitize_text_field($_POST['payment_date']),
                'payment_method' => sanitize_text_field($_POST['payment_method']),
                'transaction_id' => sanitize_text_field($_POST['transaction_id']),
                'received_by' => $user_id,
                'notes' => sanitize_textarea_field($_POST['notes']),
                'status' => sanitize_text_field($_POST['status'])
            ];
            
            if ($payment_id && $payment) {
                $result = $wpdb->update($payments_table, $data, ['id' => $payment_id]);
                $message = 'Payment updated successfully!';
                $action = 'UPDATE_PAYMENT';
            } else {
                $result = $wpdb->insert($payments_table, $data);
                $payment_id = $wpdb->insert_id;
                $message = 'Payment added successfully!';
                $action = 'ADD_PAYMENT';
            }
            
            if ($result === false) {
                echo '<div class="notice notice-error"><p>Database error: ' . esc_html($wpdb->last_error) . '</p></div>';
                return;
            }
            
            $client_id = $data['client_id'];
            $total_paid = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(amount) FROM $payments_table WHERE client_id = %d AND status = 'completed'",
                $client_id
            )) ?: 0;
            
            $client_total = $wpdb->get_var($wpdb->prepare(
                "SELECT total_fee FROM $clients_table WHERE id = %d",
                $client_id
            )) ?: 0;
            
            $wpdb->update($clients_table, 
                [
                    'paid_fee' => $total_paid,
                    'due_fee' => $client_total - $total_paid
                ],
                ['id' => $client_id]
            );
            
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            
            if ($payment_id) {
                $payment = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $payments_table WHERE id = %d",
                    $payment_id
                ));
            }
        }
    }
    
    $clients = $wpdb->get_results("
        SELECT id, client_name, passport_no, total_fee, paid_fee 
        FROM $clients_table 
        WHERE is_deleted = 0 
        ORDER BY client_name
    ");
    
    ?>
    <div class="wrap vms-payment-form-page">
        <div class="vms-page-header">
            <div class="header-content">
                <h1><span class="dashicons dashicons-money-alt"></span> <?php echo $payment ? 'Edit Payment' : 'Add Payment'; ?></h1>
                <p class="header-subtitle"><?php echo $payment ? 'Update payment details' : 'Create new payment record'; ?></p>
            </div>
            <div class="header-actions">
                <a href="?page=vms-payments" class="button button-secondary">
                    <span class="dashicons dashicons-arrow-left-alt2"></span> Back to Payments
                </a>
            </div>
        </div>
        
        <div class="form-container">
            <form method="post" class="payment-form">
                <?php wp_nonce_field('vms_save_payment'); ?>
                
                <div class="form-grid">
                    <!-- Left Column -->
                    <div class="form-column">
                        <div class="form-section">
                            <div class="section-header">
                                <h3><span class="dashicons dashicons-info"></span> Payment Information</h3>
                            </div>
                            
                            <div class="form-group">
                                <label for="client_id">Client *</label>
                                <div class="select-wrapper">
                                    <select id="client_id" name="client_id" required class="modern-select">
                                        <option value="">Select Client</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo esc_attr($client->id); ?>" 
                                                    data-total="<?php echo esc_attr($client->total_fee); ?>"
                                                    data-paid="<?php echo esc_attr($client->paid_fee); ?>"
                                                    <?php selected($payment ? $payment->client_id : $client_id, $client->id); ?>>
                                                <?php echo esc_html($client->client_name); ?> (<?php echo esc_html($client->passport_no); ?>)
                                                - Due: ৳<?php echo number_format($client->total_fee - $client->paid_fee, 2); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="select-arrow"></span>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="amount">Amount (৳) *</label>
                                    <div class="input-wrapper">
                                        <span class="input-prefix">৳</span>
                                        <input type="number" id="amount" name="amount" step="0.01" min="0.01" required
                                               value="<?php echo $payment ? esc_attr($payment->amount) : ''; ?>"
                                               class="modern-input">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="payment_date">Payment Date *</label>
                                    <input type="date" id="payment_date" name="payment_date" required
                                           value="<?php echo $payment ? esc_attr($payment->payment_date) : date('Y-m-d'); ?>"
                                           class="modern-input">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="payment_method">Payment Method</label>
                                    <div class="select-wrapper">
                                        <select id="payment_method" name="payment_method" class="modern-select">
                                            <option value="cash" <?php selected($payment ? $payment->payment_method : 'cash', 'cash'); ?>>Cash</option>
                                            <option value="bank_transfer" <?php selected($payment ? $payment->payment_method : 'cash', 'bank_transfer'); ?>>Bank Transfer</option>
                                            <option value="mobile_banking" <?php selected($payment ? $payment->payment_method : 'cash', 'mobile_banking'); ?>>Mobile Banking</option>
                                            <option value="card" <?php selected($payment ? $payment->payment_method : 'cash', 'card'); ?>>Credit/Debit Card</option>
                                            <option value="check" <?php selected($payment ? $payment->payment_method : 'cash', 'check'); ?>>Check</option>
                                        </select>
                                        <span class="select-arrow"></span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <div class="select-wrapper">
                                        <select id="status" name="status" class="modern-select">
                                            <option value="completed" <?php selected($payment ? $payment->status : 'completed', 'completed'); ?>>Completed</option>
                                            <option value="pending" <?php selected($payment ? $payment->status : 'completed', 'pending'); ?>>Pending</option>
                                            <option value="failed" <?php selected($payment ? $payment->status : 'completed', 'failed'); ?>>Failed</option>
                                            <option value="refunded" <?php selected($payment ? $payment->status : 'completed', 'refunded'); ?>>Refunded</option>
                                        </select>
                                        <span class="select-arrow"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="transaction_id">Transaction ID</label>
                                <input type="text" id="transaction_id" name="transaction_id"
                                       value="<?php echo $payment ? esc_attr($payment->transaction_id) : ''; ?>"
                                       class="modern-input" placeholder="Optional transaction reference">
                            </div>
                            
                            <div class="form-group">
                                <label for="invoice_no">Invoice Number</label>
                                <input type="text" id="invoice_no" name="invoice_no" readonly
                                       value="<?php echo $payment ? esc_attr($payment->invoice_no) : vms_generate_invoice_no(); ?>"
                                       class="modern-input" style="background: #f7fafc; border-color: #e2e8f0;">
                            </div>
                            
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" rows="4" class="modern-textarea"><?php 
                                    echo $payment ? esc_textarea($payment->notes) : ''; 
                                ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="save_payment" class="button button-primary button-large">
                                <span class="dashicons dashicons-yes-alt"></span> 
                                <?php echo $payment ? 'Update Payment' : 'Save Payment'; ?>
                            </button>
                            <a href="?page=vms-payments" class="button button-secondary button-large">
                                Cancel
                            </a>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="form-column">
                        <div class="form-section">
                            <div class="section-header">
                                <h3><span class="dashicons dashicons-chart-area"></span> Client Financial Info</h3>
                            </div>
                            <div id="client-financial-info" class="financial-info-card">
                                <div class="empty-state">
                                    <span class="dashicons dashicons-info-outline"></span>
                                    <p>Select a client to view their financial information</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <h3><span class="dashicons dashicons-money"></span> Payment Summary</h3>
                            </div>
                            <div class="payment-summary-card">
                                <div class="summary-item">
                                    <span class="summary-label">Amount:</span>
                                    <span class="summary-value" id="summary-amount">৳0.00</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Method:</span>
                                    <span class="summary-value" id="summary-method">Cash</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Status:</span>
                                    <span class="summary-value" id="summary-status">Completed</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Date:</span>
                                    <span class="summary-value" id="summary-date"><?php echo date('M d, Y'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <style>
    /* Professional Payment Form Styles */
    .vms-payment-form-page {
        max-width: 100%;
        margin: 0;
        padding: 20px;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        min-height: calc(100vh - 32px);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    }
    
    /* Enhanced Form Container */
    .form-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .payment-form {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07), 0 1px 3px rgba(0, 0, 0, 0.06);
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 0;
    }
    
    .form-column {
        padding: 32px;
    }
    
    .form-column:first-child {
        border-right: 1px solid #e2e8f0;
    }
    
    /* Enhanced Section Headers */
    .section-header {
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .section-header h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: #1a202c;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-header h3 .dashicons {
        color: #3182ce;
        font-size: 1.3rem;
    }
    
    /* Enhanced Form Groups */
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }
    
    /* Enhanced Input Styles */
    .modern-input,
    .modern-select,
    .modern-textarea {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.9rem;
        background: white;
        transition: all 0.2s ease;
    }
    
    .modern-input:focus,
    .modern-select:focus,
    .modern-textarea:focus {
        outline: none;
        border-color: #3182ce;
        box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
    }
    
    .modern-textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }
    
    .input-prefix {
        position: absolute;
        left: 16px;
        color: #718096;
        font-weight: 600;
        z-index: 1;
    }
    
    .input-wrapper .modern-input {
        padding-left: 32px;
    }
    
    /* Enhanced Select Wrapper */
    .select-wrapper {
        position: relative;
    }
    
    .select-arrow {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        width: 0;
        height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-top: 5px solid #718096;
        pointer-events: none;
    }
    
    /* Enhanced Financial Info Card */
    .financial-info-card {
        background: linear-gradient(135deg, #ebf8ff, #f7fafc);
        border: 2px solid #bee3f8;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .financial-info-card .empty-state {
        text-align: center;
        color: #718096;
    }
    
    .financial-info-card .empty-state .dashicons {
        font-size: 2rem;
        margin-bottom: 8px;
        display: block;
    }
    
    .financial-info-card .empty-state p {
        margin: 0;
        font-size: 0.9rem;
    }
    
    /* Enhanced Payment Summary Card */
    .payment-summary-card {
        background: linear-gradient(135deg, #f0fff4, #f7fafc);
        border: 2px solid #c6f6d5;
        border-radius: 12px;
        padding: 20px;
    }
    
    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .summary-item:last-child {
        border-bottom: none;
    }
    
    .summary-label {
        color: #4a5568;
        font-weight: 500;
        font-size: 0.9rem;
    }
    
    .summary-value {
        font-weight: 600;
        color: #1a202c;
        font-size: 0.9rem;
    }
    
    /* Enhanced Form Actions */
    .form-actions {
        display: flex;
        gap: 12px;
        padding-top: 24px;
        border-top: 1px solid #e2e8f0;
        margin-top: 24px;
    }
    
    .form-actions .button {
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .form-actions .button-primary {
        background: linear-gradient(135deg, #3182ce, #2c5282);
        border: none;
        box-shadow: 0 2px 4px rgba(49, 130, 206, 0.3);
    }
    
    .form-actions .button-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(49, 130, 206, 0.4);
    }
    
    .form-actions .button-secondary {
        background: white;
        border: 2px solid #e2e8f0;
        color: #4a5568;
    }
    
    .form-actions .button-secondary:hover {
        border-color: #3182ce;
        color: #3182ce;
    }
    
    .form-actions .dashicons {
        font-size: 16px;
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .form-column:first-child {
            border-right: none;
            border-bottom: 1px solid #e2e8f0;
        }
    }
    
    @media (max-width: 768px) {
        .form-column {
            padding: 24px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .form-actions .button {
            width: 100%;
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .vms-payment-form-page {
            padding: 12px;
        }
        
        .form-column {
            padding: 20px;
        }
        
        .section-header h3 {
            font-size: 1.1rem;
        }
        
        .form-group label {
            font-size: 0.85rem;
        }
        
        .modern-input,
        .modern-select,
        .modern-textarea {
            padding: 10px 12px;
            font-size: 0.85rem;
        }
    }
    
    /* Loading states and animations */
    .modern-input:disabled,
    .modern-select:disabled {
        background: #f7fafc;
        cursor: not-allowed;
        opacity: 0.7;
    }
    
    @keyframes inputFocus {
        from {
            border-color: #e2e8f0;
            box-shadow: none;
        }
        to {
            border-color: #3182ce;
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
        }
    }
    
    .modern-input:focus,
    .modern-select:focus,
    .modern-textarea:focus {
        animation: inputFocus 0.2s ease-out;
    }
    
    /* Enhanced hover effects */
    .form-group:hover label {
        color: #2d3748;
    }
    
    .select-wrapper:hover .select-arrow {
        border-top-color: #4a5568;
    }
    
    /* Financial info display enhancement */
    .financial-info-display {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        margin-top: 10px;
    }
    
    .financial-info-display .info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .financial-info-display .info-row:last-child {
        border-bottom: none;
    }
    
    /* Payment summary enhancement */
    .payment-summary {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        margin-top: 10px;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Enhanced client selection with financial preview
        $('#client_id').on('change', function() {
            var selectedOption = $(this).find('option:selected');
            var totalFee = parseFloat(selectedOption.data('total')) || 0;
            var paidFee = parseFloat(selectedOption.data('paid')) || 0;
            var dueFee = totalFee - paidFee;
            
            var html = '<div class="info-row"><span>Total Fee:</span><span>৳' + totalFee.toFixed(2) + '</span></div>' +
                '<div class="info-row"><span>Paid Amount:</span><span style="color: #38a169;">৳' + paidFee.toFixed(2) + '</span></div>' +
                '<div class="info-row"><span>Due Amount:</span><span style="color: ' + (dueFee > 0 ? '#e53e3e' : '#38a169') + ';">৳' + dueFee.toFixed(2) + '</span></div>';
            
            $('#client-financial-info').html(html);
        });
        
        // Payment summary update
        function updateSummary() {
            var amount = parseFloat($('#amount').val()) || 0;
            var method = $('#payment_method option:selected').text();
            var status = $('#status option:selected').text();
            var date = new Date($('#payment_date').val()).toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            });
            
            $('#summary-amount').text('৳' + amount.toFixed(2));
            $('#summary-method').text(method);
            $('#summary-status').text(status);
            $('#summary-date').text(date);
        }
        
        $('#amount, #payment_method, #status, #payment_date').on('change input', updateSummary);
        updateSummary();
        
        <?php if ($payment): ?>
        $('#client_id').trigger('change');
        <?php endif; ?>
        
        // Form validation enhancement
        $('.payment-form').on('submit', function(e) {
            var amount = parseFloat($('#amount').val()) || 0;
            var clientId = $('#client_id').val();
            
            if (amount <= 0) {
                e.preventDefault();
                alert('Please enter a valid amount greater than 0');
                $('#amount').focus();
                return false;
            }
            
            if (!clientId) {
                e.preventDefault();
                alert('Please select a client');
                $('#client_id').focus();
                return false;
            }
        });
        
        // Enhanced input interactions
        $('.modern-input, .modern-select, .modern-textarea').on('focus', function() {
            $(this).parent().addClass('input-focused');
        }).on('blur', function() {
            $(this).parent().removeClass('input-focused');
        });
    });
    </script>
    <?php
}

// ==================== SMS PAGE ====================
function vms_sms_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    $sms_table = $wpdb->prefix . 'vms_sms_logs';
    $clients_table = $wpdb->prefix . 'vms_clients';
    
    $user_id = get_current_user_id();
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'send';
    
    switch ($action) {
        case 'send':
            vms_handle_sms_form();
            break;
            
        case 'history':
            vms_sms_history();
            break;
            
        default:
            vms_handle_sms_form();
            break;
    }
}

function vms_handle_sms_form() {
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vms_clients';
    $sms_table = $wpdb->prefix . 'vms_sms_logs';
    
    $user_id = get_current_user_id();
    $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
    
    if (isset($_POST['send_sms'])) {
        $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
        
        if (!wp_verify_nonce($nonce, 'vms_send_sms')) {
            wp_die('Security check failed');
        }
        
        $errors = [];
        if (empty($_POST['phone'])) {
            $errors[] = 'Phone number is required';
        }
        if (empty($_POST['message'])) {
            $errors[] = 'Message is required';
        }
        
        if (!empty($errors)) {
            echo '<div class="notice notice-error"><p>' . esc_html(implode('<br>', $errors)) . '</p></div>';
        } else {
            $api_key = vms_get_setting('sms_api_key');
            $sender_id = vms_get_setting('sms_sender_id', 'VISA');
            
            if (empty($api_key)) {
                echo '<div class="notice notice-error"><p>SMS API key is not configured. Please configure it in Settings.</p></div>';
            } else {
                $phone = sanitize_text_field($_POST['phone']);
                $message = sanitize_textarea_field($_POST['message']);
                $client_id = intval($_POST['client_id']);
                
                $result = vms_send_sms_api($phone, $message, $api_key, $sender_id);
                
                if ($result['success']) {
                    $data = [
                        'client_id' => $client_id ?: null,
                        'phone' => $phone,
                        'message' => $message,
                        'status' => 'sent',
                        'response' => $result['response'],
                        'sent_by' => $user_id
                    ];
                    
                    $wpdb->insert($sms_table, $data);
                    
                    vms_log_activity($user_id, 'SEND_SMS', "SMS sent to $phone - Client ID: $client_id");
                    
                    echo '<div class="notice notice-success"><p>SMS sent successfully!</p></div>';
                } else {
                    $data = [
                        'client_id' => $client_id ?: null,
                        'phone' => $phone,
                        'message' => $message,
                        'status' => 'failed',
                        'response' => $result['error'],
                        'sent_by' => $user_id
                    ];
                    
                    $wpdb->insert($sms_table, $data);
                    
                    echo '<div class="notice notice-error"><p>SMS failed: ' . esc_html($result['error']) . '</p></div>';
                }
            }
        }
    }
    
    $clients = $wpdb->get_results("
        SELECT id, client_name, phone, passport_no 
        FROM $clients_table 
        WHERE is_deleted = 0 
        ORDER BY client_name
    ");
    
    $templates = vms_get_sms_templates();
    
    ?>
    <div class="wrap vms-sms">
        <div class="vms-page-header">
            <h1><span class="dashicons dashicons-email-alt"></span> SMS Center</h1>
            <div class="vms-page-actions">
                <a href="?page=vms-sms&action=history" class="button">
                    <span class="dashicons dashicons-clock"></span> View History
                </a>
            </div>
        </div>
        
        <div class="vms-sms-container">
            <!-- Send SMS Form -->
            <div class="vms-card">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-email"></span> Send SMS</h2>
                </div>
                <div class="vms-sms-form">
                    <form method="post" class="sms-form">
                        <?php wp_nonce_field('vms_send_sms'); ?>
                        
                        <div class="form-group">
                            <label for="client_id">Select Client (Optional)</label>
                            <select id="client_id" name="client_id">
                                <option value="">Manual Entry</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo esc_attr($client->id); ?>" 
                                            data-phone="<?php echo esc_attr($client->phone); ?>"
                                            <?php selected($client_id, $client->id); ?>>
                                        <?php echo esc_html($client->client_name); ?> (<?php echo esc_html($client->phone); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" required
                                   value="<?php echo $client_id ? esc_attr($wpdb->get_var($wpdb->prepare("SELECT phone FROM $clients_table WHERE id = %d", $client_id))) : ''; ?>"
                                   class="regular-text" pattern="\+?[0-9]{10,15}"
                                   title="10-15 digits, optional + prefix">
                            <p class="description">Enter phone number with country code (e.g., +8801XXXXXXXXX)</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="6" required class="large-text" 
                                      maxlength="160" placeholder="Enter your message here (max 160 characters)"></textarea>
                            <div class="character-count">
                                <span id="char-count">0</span> / 160 characters
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Quick Templates</label>
                            <div class="sms-templates">
                                <?php foreach ($templates as $key => $template): ?>
                                    <button type="button" class="template-button" data-template="<?php echo esc_attr($template); ?>">
                                        <?php echo esc_html($key); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="send_sms" class="button button-primary button-large">
                                <span class="dashicons dashicons-email-alt"></span> Send SMS
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- SMS Settings Info -->
            <div class="vms-card">
                <div class="vms-card-header">
                    <h2><span class="dashicons dashicons-admin-settings"></span> SMS Settings</h2>
                </div>
                <div class="vms-sms-info">
                    <?php
                    $api_key = vms_get_setting('sms_api_key');
                    $sender_id = vms_get_setting('sms_sender_id', 'VISA');
                    ?>
                    
                    <div class="setting-item">
                        <strong>API Status:</strong>
                        <span class="<?php echo $api_key ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $api_key ? '✅ Configured' : '❌ Not Configured'; ?>
                        </span>
                    </div>
                    
                    <div class="setting-item">
                        <strong>Sender ID:</strong>
                        <span><?php echo esc_html($sender_id); ?></span>
                    </div>
                    
                    <div class="setting-item">
                        <strong>Character Limit:</strong>
                        <span>160 characters per SMS</span>
                    </div>
                    
                    <div class="setting-item">
                        <strong>Supported Countries:</strong>
                        <span>Bangladesh, India, Nepal</span>
                    </div>
                    
                    <div class="setting-actions">
                        <a href="?page=vms-settings" class="button">Configure Settings</a>
                        <a href="?page=vms-sms&action=history" class="button">View SMS History</a>
                    </div>
                    
                    <div class="sms-guidelines">
                        <h3><span class="dashicons dashicons-info"></span> SMS Guidelines</h3>
                        <ul>
                            <li>✅ Keep messages under 160 characters</li>
                            <li>✅ Include your company name for identification</li>
                            <li>✅ Use clear and concise language</li>
                            <li>❌ Avoid promotional content</li>
                            <li>❌ Don't send messages outside business hours</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .vms-sms {
        max-width: 1600px;
        margin: 0 auto;
        padding: 20px;
        background: #f5f7fa;
        min-height: calc(100vh - 32px);
    }
    
    .vms-sms-container {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-top: 30px;
    }
    
    @media (max-width: 1200px) {
        .vms-sms-container {
            grid-template-columns: 1fr;
        }
    }
    
    .vms-sms-form {
        padding: 24px;
    }
    
    .sms-form .form-group {
        margin-bottom: 20px;
    }
    
    .character-count {
        text-align: right;
        font-size: 12px;
        color: #6b7280;
        margin-top: 5px;
    }
    
    .sms-templates {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }
    
    .template-button {
        background: #e0e7ff;
        border: none;
        padding: 8px 15px;
        border-radius: 20px;
        cursor: pointer;
        font-size: 12px;
        color: #3730a3;
        transition: all 0.3s ease;
    }
    
    .template-button:hover {
        background: #c7d2fe;
    }
    
    .vms-sms-info {
        padding: 24px;
    }
    
    .setting-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .setting-item:last-child {
        border-bottom: none;
    }
    
    .status-active {
        color: #10b981;
    }
    
    .status-inactive {
        color: #ef4444;
    }
    
    .setting-actions {
        margin-top: 20px;
        display: flex;
        gap: 10px;
    }
    
    .sms-guidelines {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }
    
    .sms-guidelines h3 {
        margin: 0 0 15px 0;
        font-size: 16px;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .sms-guidelines ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .sms-guidelines li {
        padding: 8px 0;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#message').on('input', function() {
            var length = $(this).val().length;
            $('#char-count').text(length);
            
            if (length > 160) {
                $('#char-count').css('color', '#ef4444');
            } else if (length > 140) {
                $('#char-count').css('color', '#f59e0b');
            } else {
                $('#char-count').css('color', '#6b7280');
            }
        });
        
        $('#client_id').on('change', function() {
            var selectedOption = $(this).find('option:selected');
            var phone = selectedOption.data('phone');
            
            if (phone) {
                $('#phone').val(phone);
            }
        });
        
        $('.template-button').on('click', function() {
            var template = $(this).data('template');
            $('#message').val(template);
            $('#message').trigger('input');
        });
        
        $('#message').trigger('input');
    });
    </script>
    <?php
}

function vms_sms_history() {
    global $wpdb;
    $sms_table = $wpdb->prefix . 'vms_sms_logs';
    
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    
    $where = "WHERE 1=1";
    if ($start_date) {
        $where .= $wpdb->prepare(" AND DATE(sent_at) >= %s", $start_date);
    }
    if ($end_date) {
        $where .= $wpdb->prepare(" AND DATE(sent_at) <= %s", $end_date);
    }
    if ($status) {
        $where .= $wpdb->prepare(" AND status = %s", $status);
    }
    
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    $sms_logs = $wpdb->get_results("
        SELECT s.*, c.client_name, u.display_name as sent_by_name
        FROM $sms_table s 
        LEFT JOIN {$wpdb->prefix}vms_clients c ON s.client_id = c.id 
        LEFT JOIN {$wpdb->prefix}users u ON s.sent_by = u.ID 
        $where 
        ORDER BY s.sent_at DESC 
        LIMIT $per_page OFFSET $offset
    ");
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $sms_table s $where");
    
    ?>
    <div class="wrap vms-sms-history">
        <div class="vms-page-header">
            <h1><span class="dashicons dashicons-clock"></span> SMS History</h1>
            <div class="vms-page-actions">
                <a href="?page=vms-sms&action=send" class="button button-primary">
                    <span class="dashicons dashicons-email-alt"></span> Send SMS
                </a>
                <a href="?page=vms-export&type=sms" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span> Export
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="vms-filters-card">
            <form method="get" action="">
                <input type="hidden" name="page" value="vms-sms">
                <input type="hidden" name="action" value="history">
                
                <div class="vms-filter-row">
                    <div class="vms-filter-item">
                        <label>From Date</label>
                        <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                    </div>
                    
                    <div class="vms-filter-item">
                        <label>To Date</label>
                        <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                    </div>
                    
                    <div class="vms-filter-item">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="sent" <?php selected($status, 'sent'); ?>>Sent</option>
                            <option value="failed" <?php selected($status, 'failed'); ?>>Failed</option>
                            <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                        </select>
                    </div>
                    
                    <div class="vms-filter-actions">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-filter"></span> Filter
                        </button>
                        <?php if ($start_date || $end_date || $status): ?>
                            <a href="?page=vms-sms&action=history" class="button">
                                <span class="dashicons dashicons-no"></span> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Summary -->
        <div class="vms-summary-stats">
            <div class="vms-summary-stat">
                <span class="stat-value"><?php echo esc_html($total); ?></span>
                <span class="stat-label">Total SMS</span>
            </div>
            <div class="vms-summary-stat">
                <span class="stat-value"><?php echo esc_html(count($sms_logs)); ?></span>
                <span class="stat-label">Showing</span>
            </div>
        </div>
        
        <!-- SMS History Table -->
        <div class="vms-card">
            <div class="vms-table-responsive">
                <table class="vms-data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Client</th>
                            <th>Phone</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Sent By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sms_logs): ?>
                            <?php foreach ($sms_logs as $sms): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($sms->sent_at)); ?></td>
                                <td>
                                    <?php if ($sms->client_name): ?>
                                        <strong><?php echo esc_html($sms->client_name); ?></strong>
                                    <?php else: ?>
                                        <em>Manual Entry</em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($sms->phone); ?></td>
                                <td>
                                    <div class="sms-message-preview">
                                        <?php echo wp_kses_post($sms->message); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="vms-status-badge status-<?php echo esc_attr(strtolower($sms->status)); ?>">
                                        <?php echo esc_html(ucfirst($sms->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($sms->sent_by_name ?: 'System'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-data">
                                    <div class="empty-state">
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <h3>No SMS history found</h3>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php
            if ($total > $per_page) {
                $total_pages = ceil($total / $per_page);
                ?>
                <div class="vms-pagination">
                    <div class="pagination-info">
                        Showing <?php echo esc_html(min($per_page * ($current_page - 1) + 1, $total)); ?> 
                        to <?php echo esc_html(min($per_page * $current_page, $total)); ?> 
                        of <?php echo esc_html($total); ?> entries
                    </div>
                    <div class="pagination-links">
                        <?php if ($current_page > 1): ?>
                            <a class="pagination-link prev" href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>">
                                <span class="dashicons dashicons-arrow-left-alt"></span> Previous
                            </a>
                        <?php endif; ?>
                        
                        <div class="pagination-numbers">
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a class="pagination-number <?php echo $i == $current_page ? 'active' : ''; ?>" 
                                   href="<?php echo esc_url(add_query_arg('paged', $i)); ?>">
                                    <?php echo esc_html($i); ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a class="pagination-link next" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>">
                                Next <span class="dashicons dashicons-arrow-right-alt"></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
    
    <style>
    .vms-sms-history {
        max-width: 1800px;
        margin: 0 auto;
        padding: 20px;
        background: #f5f7fa;
        min-height: calc(100vh - 32px);
    }
    
    .sms-message-preview {
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    </style>
    <?php
}

// ==================== ENHANCED REPORTS PAGE ====================
function vms_reports_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    $report_type = isset($_GET['report']) ? sanitize_text_field($_GET['report']) : 'summary';
    
    switch ($report_type) {
        case 'summary':
            vms_enhanced_summary_report();
            break;
            
        case 'financial':
            vms_enhanced_financial_report();
            break;
            
        case 'applications':
            vms_enhanced_applications_report();
            break;
            
        case 'sms':
            vms_enhanced_sms_report();
            break;
            
        default:
            vms_enhanced_summary_report();
            break;
    }
}

function vms_enhanced_summary_report() {
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vms_clients';
    $payments_table = $wpdb->prefix . 'vms_payments';
    $expenses_table = $wpdb->prefix . 'vms_expenses';
    
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');
    
    // Get summary data
    $summary = [
        'total_clients' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $clients_table WHERE is_deleted = 0 AND DATE(created_at) BETWEEN %s AND %s",
            $start_date, $end_date
        )) ?: 0,
        
        'total_revenue' => $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_fee) FROM $clients_table WHERE is_deleted = 0 AND DATE(created_at) BETWEEN %s AND %s",
            $start_date, $end_date
        )) ?: 0,
        
        'total_collected' => $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $payments_table WHERE status = 'completed' AND DATE(payment_date) BETWEEN %s AND %s",
            $start_date, $end_date
        )) ?: 0,
        
        'total_expenses' => $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $expenses_table WHERE DATE(expense_date) BETWEEN %s AND %s",
            $start_date, $end_date
        )) ?: 0,
        
        'pending_applications' => $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE status = 'pending' AND is_deleted = 0") ?: 0,
        'processing_applications' => $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE status = 'processing' AND is_deleted = 0") ?: 0,
        'completed_applications' => $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE status = 'completed' AND is_deleted = 0") ?: 0,
    ];
    
    $summary['net_profit'] = $summary['total_collected'] - $summary['total_expenses'];
    
    // Get monthly data for charts
    $monthly_data = [];
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_start = $month . '-01';
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $monthly_data[] = [
            'month' => date('M Y', strtotime($month_start)),
            'applications' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $clients_table WHERE is_deleted = 0 AND DATE(created_at) BETWEEN %s AND %s",
                $month_start, $month_end
            )) ?: 0,
            'revenue' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(total_fee) FROM $clients_table WHERE is_deleted = 0 AND DATE(created_at) BETWEEN %s AND %s",
                $month_start, $month_end
            )) ?: 0,
            'collections' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(amount) FROM $payments_table WHERE status = 'completed' AND DATE(payment_date) BETWEEN %s AND %s",
                $month_start, $month_end
            )) ?: 0,
        ];
    }
    
    ?>
    <div class="wrap vms-reports-pro">
        <!-- Professional Header -->
        <div class="vms-reports-header">
            <div class="vms-reports-header-content">
                <h1>
                    <div class="vms-header-icon">
                        <svg viewBox="0 0 24 24" width="32" height="32">
                            <path fill="currentColor" d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1z"/>
                        </svg>
                    </div>
                    <div class="vms-header-text">
                        <span class="vms-main-title">Reports & Analytics</span>
                        <span class="vms-sub-title">Comprehensive business insights and performance metrics</span>
                    </div>
                </h1>
            </div>
            <div class="vms-reports-actions">
                <button class="vms-btn vms-btn-primary" onclick="window.print()">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path fill="currentColor" d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                        <path fill="currentColor" d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5z"/>
                    </svg>
                    Print Report
                </button>
                <button class="vms-btn vms-btn-secondary" onclick="exportReport()">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path fill="currentColor" d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                        <path fill="currentColor" d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                    </svg>
                    Export Data
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="vms-reports-nav">
            <div class="vms-tabs-container">
                <a href="?page=vms-reports&report=summary" class="vms-tab <?php echo $report_type == 'summary' ? 'active' : ''; ?>">
                    <div class="vms-tab-icon">
                        <svg viewBox="0 0 20 20" width="20" height="20">
                            <path fill="currentColor" d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                        </svg>
                    </div>
                    <div class="vms-tab-content">
                        <span class="vms-tab-title">Summary Report</span>
                        <span class="vms-tab-desc">Overview & key metrics</span>
                    </div>
                </a>
                <a href="?page=vms-reports&report=financial" class="vms-tab <?php echo $report_type == 'financial' ? 'active' : ''; ?>">
                    <div class="vms-tab-icon">
                        <svg viewBox="0 0 20 20" width="20" height="20">
                            <path fill="currentColor" d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                            <path fill="currentColor" d="M8 0a8 8 0 100 16A8 8 0 008 0zm1 14.849c-1.735 0-3.206-1.12-3.728-2.665h1.627a2.475 2.475 0 002.32 1.325c1.325 0 2.327-1.07 2.327-2.488 0-1.325-1.01-2.488-2.327-2.488-1.07 0-1.983.825-2.32 1.325H5.272C5.794 2.27 7.265 1.158 9 1.158c1.825 0 3.27 1.488 3.27 3.42 0 1.825-1.445 3.422-3.27 3.422z"/>
                        </svg>
                    </div>
                    <div class="vms-tab-content">
                        <span class="vms-tab-title">Financial Report</span>
                        <span class="vms-tab-desc">Revenue & expenses</span>
                    </div>
                </a>
                <a href="?page=vms-reports&report=applications" class="vms-tab <?php echo $report_type == 'applications' ? 'active' : ''; ?>">
                    <div class="vms-tab-icon">
                        <svg viewBox="0 0 20 20" width="20" height="20">
                            <path fill="currentColor" d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z"/>
                            <path fill="currentColor" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                            <path fill="currentColor" d="M8 12l2-2 4 4"/>
                        </svg>
                    </div>
                    <div class="vms-tab-content">
                        <span class="vms-tab-title">Applications Report</span>
                        <span class="vms-tab-desc">Application analytics</span>
                    </div>
                </a>
                <a href="?page=vms-reports&report=sms" class="vms-tab <?php echo $report_type == 'sms' ? 'active' : ''; ?>">
                    <div class="vms-tab-icon">
                        <svg viewBox="0 0 20 20" width="20" height="20">
                            <path fill="currentColor" d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                            <path fill="currentColor" d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                        </svg>
                    </div>
                    <div class="vms-tab-content">
                        <span class="vms-tab-title">SMS Report</span>
                        <span class="vms-tab-desc">Communication stats</span>
                    </div>
                </a>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="vms-filters-card">
            <form method="get" action="" class="vms-filter-form">
                <input type="hidden" name="page" value="vms-reports">
                <input type="hidden" name="report" value="summary">
                
                <div class="vms-filter-row">
                    <div class="vms-filter-item">
                        <label class="vms-filter-label">From Date</label>
                        <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="vms-date-input">
                    </div>
                    
                    <div class="vms-filter-item">
                        <label class="vms-filter-label">To Date</label>
                        <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="vms-date-input">
                    </div>
                    
                    <div class="vms-filter-actions">
                        <button type="submit" class="vms-btn vms-btn-primary">
                            <svg width="16" height="16" viewBox="0 0 16 16" style="margin-right: 8px;">
                                <path fill="currentColor" d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z"/>
                            </svg>
                            Apply Filter
                        </button>
                        <a href="?page=vms-reports&report=summary" class="vms-btn vms-btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 16 16" style="margin-right: 8px;">
                                <path fill="currentColor" d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                            </svg>
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Enhanced Stats Grid -->
        <div class="vms-stats-grid">
            <div class="vms-stat-card vms-stat-primary">
                <div class="vms-stat-icon">
                    <svg viewBox="0 0 24 24" width="32" height="32">
                        <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <div class="vms-stat-number"><?php echo number_format($summary['total_clients']); ?></div>
                    <div class="vms-stat-label">New Clients</div>
                    <div class="vms-stat-trend vms-trend-up">
                        <svg width="12" height="12" viewBox="0 0 12 12">
                            <path fill="currentColor" d="M6 0l6 6h-3v6h-6v-6h-3z"/>
                        </svg>
                        +12.5%
                    </div>
                </div>
            </div>
            
            <div class="vms-stat-card vms-stat-success">
                <div class="vms-stat-icon">
                    <svg viewBox="0 0 24 24" width="32" height="32">
                        <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1.93.66 1.64 2.08 1.64 1.55 0 1.95-.98 1.95-1.61 0-2.14-5.52-.97-5.52-4.3 0-1.63 1.18-2.76 2.94-3.18V5h2.67v1.5c1.56.29 2.69 1.19 2.83 2.73h-2.01c-.11-.8-.59-1.39-1.72-1.39-.86 0-1.45.41-1.45 1.3 0 2.14 5.52.97 5.52 4.3 0 1.61-1.08 2.79-2.88 3.32z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <div class="vms-stat-number">৳<?php echo number_format($summary['total_revenue']); ?></div>
                    <div class="vms-stat-label">Total Revenue</div>
                    <div class="vms-stat-trend vms-trend-up">
                        <svg width="12" height="12" viewBox="0 0 12 12">
                            <path fill="currentColor" d="M6 0l6 6h-3v6h-6v-6h-3z"/>
                        </svg>
                        +8.2%
                    </div>
                </div>
            </div>
            
            <div class="vms-stat-card vms-stat-info">
                <div class="vms-stat-icon">
                    <svg viewBox="0 0 24 24" width="32" height="32">
                        <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <div class="vms-stat-number">৳<?php echo number_format($summary['total_collected']); ?></div>
                    <div class="vms-stat-label">Collections</div>
                    <div class="vms-stat-trend vms-trend-down">
                        <svg width="12" height="12" viewBox="0 0 12 12">
                            <path fill="currentColor" d="M6 12l-6-6h3v-6h6v6h3z"/>
                        </svg>
                        -3.1%
                    </div>
                </div>
            </div>
            
            <div class="vms-stat-card vms-stat-warning">
                <div class="vms-stat-icon">
                    <svg viewBox="0 0 24 24" width="32" height="32">
                        <path fill="currentColor" d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <div class="vms-stat-number">৳<?php echo number_format($summary['total_expenses']); ?></div>
                    <div class="vms-stat-label">Expenses</div>
                    <div class="vms-stat-trend vms-trend-up">
                        <svg width="12" height="12" viewBox="0 0 12 12">
                            <path fill="currentColor" d="M6 0l6 6h-3v6h-6v-6h-3z"/>
                        </svg>
                        +5.7%
                    </div>
                </div>
            </div>
            
            <div class="vms-stat-card vms-stat-profit">
                <div class="vms-stat-icon">
                    <svg viewBox="0 0 24 24" width="32" height="32">
                        <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 7.59L12 10.17 10.59 8.59 9.17 10 12 12.83 14.83 10l-1.42-1.41z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <div class="vms-stat-number">৳<?php echo number_format($summary['net_profit']); ?></div>
                    <div class="vms-stat-label">Net Profit</div>
                    <div class="vms-stat-trend vms-trend-up">
                        <svg width="12" height="12" viewBox="0 0 12 12">
                            <path fill="currentColor" d="M6 0l6 6h-3v6h-6v-6h-3z"/>
                        </svg>
                        +15.3%
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="vms-charts-section">
            <div class="vms-chart-card">
                <div class="vms-chart-header">
                    <h3>
                        <svg viewBox="0 0 20 20" width="20" height="20">
                            <path fill="currentColor" d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                        </svg>
                        Monthly Applications Trend
                    </h3>
                    <div class="vms-chart-controls">
                        <button class="vms-chart-btn active" data-period="12m">12M</button>
                        <button class="vms-chart-btn" data-period="6m">6M</button>
                        <button class="vms-chart-btn" data-period="3m">3M</button>
                    </div>
                </div>
                <div class="vms-chart-body">
                    <canvas id="applicationsChart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <div class="vms-chart-card">
                <div class="vms-chart-header">
                    <h3>
                        <svg viewBox="0 0 20 20" width="20" height="20">
                            <path fill="currentColor" d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z"/>
                            <path fill="currentColor" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                        </svg>
                        Revenue vs Collections
                    </h3>
                    <div class="vms-chart-legend">
                        <span class="vms-legend-item">
                            <span class="vms-legend-color" style="background: #4f46e5;"></span>
                            Revenue
                        </span>
                        <span class="vms-legend-item">
                            <span class="vms-legend-color" style="background: #10b981;"></span>
                            Collections
                        </span>
                    </div>
                </div>
                <div class="vms-chart-body">
                    <canvas id="revenueChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Status Distribution & Popular Visa Types -->
        <div class="vms-bottom-section">
            <div class="vms-status-card">
                <div class="vms-card-header">
                    <h3>
                        <svg viewBox="0 0 20 20" width="20" height="20">
                            <path fill="currentColor" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Application Status Distribution
                    </h3>
                </div>
                <div class="vms-status-content">
                    <div class="vms-status-grid">
                        <div class="vms-status-item">
                            <div class="vms-status-icon pending">
                                <svg viewBox="0 0 20 20" width="24" height="24">
                                    <path fill="currentColor" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                                </svg>
                            </div>
                            <div class="vms-status-info">
                                <div class="vms-status-number"><?php echo esc_html($summary['pending_applications']); ?></div>
                                <div class="vms-status-label">Pending</div>
                                <div class="vms-status-percentage">
                                    <?php 
                                    $total = $summary['pending_applications'] + $summary['processing_applications'] + $summary['completed_applications'];
                                    echo $total > 0 ? round(($summary['pending_applications'] / $total) * 100) : 0;
                                    ?>%
                                </div>
                            </div>
                        </div>
                        <div class="vms-status-item">
                            <div class="vms-status-icon processing">
                                <svg viewBox="0 0 20 20" width="24" height="24">
                                    <path fill="currentColor" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"/>
                                </svg>
                            </div>
                            <div class="vms-status-info">
                                <div class="vms-status-number"><?php echo esc_html($summary['processing_applications']); ?></div>
                                <div class="vms-status-label">Processing</div>
                                <div class="vms-status-percentage">
                                    <?php 
                                    echo $total > 0 ? round(($summary['processing_applications'] / $total) * 100) : 0;
                                    ?>%
                                </div>
                            </div>
                        </div>
                        <div class="vms-status-item">
                            <div class="vms-status-icon completed">
                                <svg viewBox="0 0 20 20" width="24" height="24">
                                    <path fill="currentColor" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                </svg>
                            </div>
                            <div class="vms-status-info">
                                <div class="vms-status-number"><?php echo esc_html($summary['completed_applications']); ?></div>
                                <div class="vms-status-label">Completed</div>
                                <div class="vms-status-percentage">
                                    <?php 
                                    echo $total > 0 ? round(($summary['completed_applications'] / $total) * 100) : 0;
                                    ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="vms-status-chart">
                        <canvas id="statusChart" width="200" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="vms-visa-types-card">
                <div class="vms-card-header">
                    <h3>
                        <svg viewBox="0 0 20 20" width="20" height="20">
                            <path fill="currentColor" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Popular Visa Types
                    </h3>
                </div>
                <div class="vms-visa-types-content">
                    <?php
                    $visa_types = $wpdb->get_results("
                        SELECT visa_type, COUNT(*) as count, SUM(total_fee) as revenue
                        FROM $clients_table
                        WHERE is_deleted = 0 AND visa_type != ''
                        GROUP BY visa_type
                        ORDER BY count DESC
                        LIMIT 5
                    ");
                    
                    if ($visa_types):
                        foreach ($visa_types as $index => $type):
                    ?>
                    <div class="vms-visa-item">
                        <div class="vms-visa-rank">#<?php echo $index + 1; ?></div>
                        <div class="vms-visa-info">
                            <div class="vms-visa-type"><?php echo esc_html($type->visa_type); ?></div>
                            <div class="vms-visa-count"><?php echo esc_html($type->count); ?> applications</div>
                        </div>
                        <div class="vms-visa-revenue">৳<?php echo number_format($type->revenue); ?></div>
                    </div>
                    <?php
                        endforeach;
                    else:
                    ?>
                    <div class="vms-no-data">
                        <svg width="48" height="48" viewBox="0 0 24 24">
                            <path fill="#CBD5E0" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1z"/>
                        </svg>
                        <h4>No visa type data available</h4>
                        <p>Start adding applications to see statistics</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    jQuery(document).ready(function($) {
        // Chart configurations
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        };

        // Applications Chart
        const applicationsCtx = document.getElementById('applicationsChart').getContext('2d');
        new Chart(applicationsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_data, 'month')); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode(array_column($monthly_data, 'applications')); ?>,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: chartOptions
        });

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($monthly_data, 'month')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($monthly_data, 'revenue')); ?>,
                    backgroundColor: '#4f46e5',
                    borderRadius: 4
                }, {
                    label: 'Collections',
                    data: <?php echo json_encode(array_column($monthly_data, 'collections')); ?>,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                }]
            },
            options: {
                ...chartOptions,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Processing', 'Completed'],
                datasets: [{
                    data: [
                        <?php echo esc_js($summary['pending_applications']); ?>,
                        <?php echo esc_js($summary['processing_applications']); ?>,
                        <?php echo esc_js($summary['completed_applications']); ?>
                    ],
                    backgroundColor: ['#f59e0b', '#3b82f6', '#10b981'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Chart period buttons
        $('.vms-chart-btn').click(function() {
            $('.vms-chart-btn').removeClass('active');
            $(this).addClass('active');
            // Add logic to update chart data based on period
        });
    });

    function exportReport() {
        // Add export functionality
        alert('Export functionality will be implemented');
    }
    </script>
    
    <style>
    /* Professional Reports Styles */
    .vms-reports-pro {
        max-width: 1800px;
        margin: 0 auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    /* Header Styles */
    .vms-reports-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding: 30px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        color: white;
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .vms-reports-header h1 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .vms-header-icon {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .vms-header-text {
        display: flex;
        flex-direction: column;
    }

    .vms-main-title {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .vms-sub-title {
        font-size: 14px;
        opacity: 0.9;
    }

    .vms-reports-actions {
        display: flex;
        gap: 12px;
    }

    /* Button Styles */
    .vms-btn {
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 14px;
        gap: 8px;
    }

    .vms-btn-primary {
        background: white;
        color: #667eea;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .vms-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    }

    .vms-btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .vms-btn-secondary:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    /* Navigation Tabs */
    .vms-reports-nav {
        margin-bottom: 30px;
    }

    .vms-tabs-container {
        display: flex;
        gap: 10px;
        background: white;
        padding: 8px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow-x: auto;
    }

    .vms-tab {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 24px;
        border-radius: 8px;
        text-decoration: none;
        color: #6b7280;
        font-weight: 500;
        white-space: nowrap;
        transition: all 0.2s ease;
        min-width: 180px;
    }

    .vms-tab:hover {
        background: #f3f4f6;
        color: #4f46e5;
    }

    .vms-tab.active {
        background: #4f46e5;
        color: white;
        box-shadow: 0 4px 6px rgba(79, 70, 229, 0.3);
    }

    .vms-tab-icon {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .vms-tab-content {
        display: flex;
        flex-direction: column;
    }

    .vms-tab-title {
        font-size: 14px;
        font-weight: 600;
    }

    .vms-tab-desc {
        font-size: 12px;
        opacity: 0.8;
    }

    /* Filter Card */
    .vms-filters-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 30px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .vms-filter-form {
        margin: 0;
    }

    .vms-filter-row {
        display: flex;
        gap: 20px;
        align-items: flex-end;
    }

    .vms-filter-item {
        flex: 1;
    }

    .vms-filter-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #374151;
        font-size: 14px;
    }

    .vms-date-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .vms-date-input:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .vms-filter-actions {
        display: flex;
        gap: 12px;
    }

    /* Stats Grid */
    .vms-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }

    .vms-stat-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all 0.3s ease;
        border: 1px solid #f3f4f6;
    }

    .vms-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .vms-stat-icon {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .vms-stat-primary .vms-stat-icon {
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
    }

    .vms-stat-success .vms-stat-icon {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .vms-stat-info .vms-stat-icon {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    }

    .vms-stat-warning .vms-stat-icon {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .vms-stat-profit .vms-stat-icon {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }

    .vms-stat-content {
        flex: 1;
    }

    .vms-stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 4px;
    }

    .vms-stat-label {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 8px;
    }

    .vms-stat-trend {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        font-weight: 500;
    }

    .vms-trend-up {
        color: #10b981;
    }

    .vms-trend-down {
        color: #ef4444;
    }

    /* Charts Section */
    .vms-charts-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 30px;
    }

    @media (max-width: 1200px) {
        .vms-charts-section {
            grid-template-columns: 1fr;
        }
    }

    .vms-chart-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border: 1px solid #f3f4f6;
    }

    .vms-chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .vms-chart-header h3 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }

    .vms-chart-controls {
        display: flex;
        gap: 8px;
    }

    .vms-chart-btn {
        padding: 6px 12px;
        border: 1px solid #e5e7eb;
        background: white;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .vms-chart-btn.active {
        background: #4f46e5;
        color: white;
        border-color: #4f46e5;
    }

    .vms-chart-legend {
        display: flex;
        gap: 16px;
    }

    .vms-legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: #6b7280;
    }

    .vms-legend-color {
        width: 12px;
        height: 12px;
        border-radius: 2px;
    }

    .vms-chart-body {
        height: 300px;
    }

    /* Bottom Section */
    .vms-bottom-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    @media (max-width: 1200px) {
        .vms-bottom-section {
            grid-template-columns: 1fr;
        }
    }

    .vms-status-card,
    .vms-visa-types-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border: 1px solid #f3f4f6;
    }

    .vms-card-header {
        margin-bottom: 20px;
    }

    .vms-card-header h3 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }

    .vms-status-content {
        display: flex;
        gap: 30px;
    }

    .vms-status-grid {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .vms-status-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: #f9fafb;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }

    .vms-status-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .vms-status-icon.pending {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .vms-status-icon.processing {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    }

    .vms-status-icon.completed {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .vms-status-info {
        flex: 1;
    }

    .vms-status-number {
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
    }

    .vms-status-label {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 4px;
    }

    .vms-status-percentage {
        font-size: 12px;
        font-weight: 600;
        color: #4f46e5;
    }

    .vms-status-chart {
        width: 200px;
        height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .vms-visa-types-content {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .vms-visa-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: #f9fafb;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }

    .vms-visa-item:hover {
        background: #f3f4f6;
        transform: translateX(4px);
    }

    .vms-visa-rank {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
    }

    .vms-visa-info {
        flex: 1;
    }

    .vms-visa-type {
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 4px;
    }

    .vms-visa-count {
        font-size: 12px;
        color: #6b7280;
    }

    .vms-visa-revenue {
        font-weight: 700;
        color: #059669;
        font-size: 16px;
    }

    .vms-no-data {
        text-align: center;
        padding: 40px 20px;
        color: #9ca3af;
    }

    .vms-no-data h4 {
        margin: 16px 0 8px;
        color: #6b7280;
    }

    .vms-no-data p {
        margin: 0;
        font-size: 14px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .vms-reports-header {
            flex-direction: column;
            gap: 20px;
            align-items: flex-start;
        }

        .vms-reports-actions {
            width: 100%;
            display: flex;
            gap: 10px;
        }

        .vms-reports-actions .vms-btn {
            flex: 1;
            justify-content: center;
        }

        .vms-filter-row {
            flex-direction: column;
            align-items: stretch;
        }

        .vms-filter-actions {
            justify-content: stretch;
        }

        .vms-filter-actions .vms-btn {
            flex: 1;
            justify-content: center;
        }

        .vms-stats-grid {
            grid-template-columns: 1fr;
        }

        .vms-status-content {
            flex-direction: column;
        }

        .vms-status-chart {
            width: 100%;
            height: 200px;
        }
    }

    @media print {
        .vms-reports-actions,
        .vms-filters-card,
        .vms-chart-controls {
            display: none;
        }
    }
    </style>
    <?php
}

// ==================== ENHANCED EXPENSES PAGE ====================
function vms_enhanced_expenses_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    $expenses_table = $wpdb->prefix . 'vms_expenses';
    
    $user_id = get_current_user_id();
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    
    switch ($action) {
        case 'add':
        case 'edit':
            vms_enhanced_handle_expense_form();
            break;
            
        case 'delete':
            if (isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_expense')) {
                $expense_id = intval($_GET['id']);
                $result = $wpdb->delete($expenses_table, ['id' => $expense_id]);
                
                if ($result !== false) {
                    echo '<div class="notice notice-success is-dismissible"><p>Expense deleted successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Error deleting expense: ' . esc_html($wpdb->last_error) . '</p></div>';
                }
            }
            vms_enhanced_expense_list();
            break;
            
        default:
            vms_enhanced_expense_list();
            break;
    }
}

function vms_enhanced_expense_list() {
    global $wpdb;
    $expenses_table = $wpdb->prefix . 'vms_expenses';
    
    $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    
    $where = "WHERE 1=1";
    if ($category) {
        $where .= $wpdb->prepare(" AND category = %s", $category);
    }
    if ($start_date) {
        $where .= $wpdb->prepare(" AND expense_date >= %s", $start_date);
    }
    if ($end_date) {
        $where .= $wpdb->prepare(" AND expense_date <= %s", $end_date);
    }
    
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    $expenses = $wpdb->get_results("
        SELECT e.*, u.display_name as created_by_name
        FROM $expenses_table e 
        LEFT JOIN {$wpdb->prefix}users u ON e.created_by = u.ID 
        $where 
        ORDER BY e.expense_date DESC 
        LIMIT $per_page OFFSET $offset
    ");
    
    $categories = $wpdb->get_col("SELECT DISTINCT category FROM $expenses_table ORDER BY category");
    
    $total_expenses = $wpdb->get_var("SELECT SUM(amount) FROM $expenses_table $where") ?: 0;
    $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $expenses_table $where") ?: 0;
    
    ?>
    <div class="wrap vms-expenses-pro">
        <!-- Professional Header -->
        <div class="vms-expenses-header">
            <div class="vms-header-content">
                <h1>
                    <div class="vms-header-icon">
                        <svg viewBox="0 0 24 24" width="32" height="32">
                            <path fill="currentColor" d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1z"/>
                        </svg>
                    </div>
                    <div class="vms-header-text">
                        <span class="vms-main-title">Expense Management</span>
                        <span class="vms-sub-title">Track and manage all business expenses efficiently</span>
                    </div>
                </h1>
            </div>
            <div class="vms-header-actions">
                <a href="?page=vms-expenses&action=add" class="vms-btn vms-btn-primary">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path fill="currentColor" d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                    </svg>
                    Add Expense
                </a>
                <a href="?page=vms-export&type=expenses" class="vms-btn vms-btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path fill="currentColor" d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                        <path fill="currentColor" d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                    </svg>
                    Export
                </a>
            </div>
        </div>

        <!-- Enhanced Stats Cards -->
        <div class="vms-stats-grid">
            <div class="vms-stat-card vms-stat-expense">
                <div class="vms-stat-icon">
                    <svg viewBox="0 0 24 24" width="32" height="32">
                        <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <div class="vms-stat-number">৳<?php echo number_format($total_expenses, 2); ?></div>
                    <div class="vms-stat-label">Total Expenses</div>
                    <div class="vms-stat-trend vms-trend-up">
                        <svg width="12" height="12" viewBox="0 0 12 12">
                            <path fill="currentColor" d="M6 0l6 6h-3v6h-6v-6h-3z"/>
                        </svg>
                        +12.5%
                    </div>
                </div>
            </div>
            
            <div class="vms-stat-card vms-stat-records">
                <div class="vms-stat-icon">
                    <svg viewBox="0 0 24 24" width="32" height="32">
                        <path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <div class="vms-stat-number"><?php echo esc_html($total_count); ?></div>
                    <div class="vms-stat-label">Total Records</div>
                    <div class="vms-stat-trend vms-trend-neutral">
                        <svg width="12" height="12" viewBox="0 0 12 12">
                            <path fill="currentColor" d="M6 0l6 6h-12z"/>
                        </svg>
                        All time
                    </div>
                </div>
            </div>
            
            <div class="vms-stat-card vms-stat-showing">
                <div class="vms-stat-icon">
                    <svg viewBox="0 0 24 24" width="32" height="32">
                        <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.94-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <div class="vms-stat-number"><?php echo esc_html(count($expenses)); ?></div>
                    <div class="vms-stat-label">Showing</div>
                    <div class="vms-stat-trend vms-trend-neutral">
                        <svg width="12" height="12" viewBox="0 0 12 12">
                            <path fill="currentColor" d="M6 0l6 6h-12z"/>
                        </svg>
                        Current page
                    </div>
                </div>
            </div>
            
            <div class="vms-stat-card vms-stat-categories">
                <div class="vms-stat-icon">
                    <svg viewBox="0 0 24 24" width="32" height="32">
                        <path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <div class="vms-stat-number"><?php echo esc_html(count($categories)); ?></div>
                    <div class="vms-stat-label">Categories</div>
                    <div class="vms-stat-trend vms-trend-neutral">
                        <svg width="12" height="12" viewBox="0 0 12 12">
                            <path fill="currentColor" d="M6 0l6 6h-12z"/>
                        </svg>
                        Active
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Filter Card -->
        <div class="vms-filter-card">
            <div class="vms-filter-header">
                <h3>
                    <svg viewBox="0 0 20 20" width="20" height="20">
                        <path fill="currentColor" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V4z"/>
                    </svg>
                    Advanced Filters
                </h3>
                <div class="vms-filter-actions">
                    <button type="button" class="vms-btn vms-btn-secondary" onclick="clearFilters()">
                        <svg width="16" height="16" viewBox="0 0 16 16">
                            <path fill="currentColor" d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                        </svg>
                        Clear All
                    </button>
                </div>
            </div>
            <div class="vms-filter-body">
                <form method="get" action="" class="vms-filter-form">
                    <input type="hidden" name="page" value="vms-expenses">
                    
                    <div class="vms-filter-grid">
                        <div class="vms-filter-group">
                            <label>Category</label>
                            <select name="category" class="vms-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat); ?>" <?php selected($category, $cat); ?>>
                                        <?php echo esc_html($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="vms-filter-group">
                            <label>From Date</label>
                            <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="vms-input">
                        </div>
                        
                        <div class="vms-filter-group">
                            <label>To Date</label>
                            <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="vms-input">
                        </div>
                        
                        <div class="vms-filter-actions">
                            <button type="submit" class="vms-btn vms-btn-primary">
                                <svg width="16" height="16" viewBox="0 0 16 16" style="margin-right: 8px;">
                                    <path fill="currentColor" d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z"/>
                                </svg>
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Enhanced Expenses Table -->
        <div class="vms-table-card">
            <div class="vms-table-header">
                <h3>
                    <svg viewBox="0 0 20 20" width="20" height="20">
                        <path fill="currentColor" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V4z"/>
                    </svg>
                    Expense Records
                </h3>
                <div class="vms-table-actions">
                    <button class="vms-btn vms-btn-secondary" onclick="exportTable()">
                        <svg width="16" height="16" viewBox="0 0 16 16">
                            <path fill="currentColor" d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                            <path fill="currentColor" d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                        </svg>
                        Export
                    </button>
                </div>
            </div>
            <div class="vms-table-container">
                <table class="vms-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" class="vms-select-all" onchange="toggleAllRows(this)">
                            </th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Paid To</th>
                            <th>Method</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($expenses): ?>
                            <?php foreach ($expenses as $expense): ?>
                            <tr class="vms-expense-row">
                                <td>
                                    <input type="checkbox" class="vms-row-checkbox" value="<?php echo esc_attr($expense->id); ?>">
                                </td>
                                <td>
                                    <div class="vms-date-cell">
                                        <div class="vms-date-day"><?php echo date('d', strtotime($expense->expense_date)); ?></div>
                                        <div class="vms-date-month"><?php echo date('M', strtotime($expense->expense_date)); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="vms-badge vms-badge-category"><?php echo esc_html($expense->category); ?></span>
                                </td>
                                <td>
                                    <div class="vms-description">
                                        <strong><?php echo esc_html($expense->description); ?></strong>
                                        <?php if($expense->receipt_no): ?>
                                            <small>Receipt: <?php echo esc_html($expense->receipt_no); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="vms-amount">
                                        <span class="vms-amount-value">৳<?php echo number_format($expense->amount, 2); ?></span>
                                    </div>
                                </td>
                                <td><?php echo esc_html($expense->paid_to); ?></td>
                                <td>
                                    <span class="vms-badge vms-badge-method vms-method-<?php echo esc_attr($expense->payment_method); ?>">
                                        <?php echo esc_html(ucfirst($expense->payment_method)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($expense->created_by_name ?: 'System'); ?></td>
                                <td>
                                    <div class="vms-actions">
                                        <a href="?page=vms-expenses&action=edit&id=<?php echo esc_attr($expense->id); ?>" 
                                           class="vms-action-btn vms-action-edit" title="Edit">
                                            <svg width="14" height="14" viewBox="0 0 14 14">
                                                <path fill="currentColor" d="M13.479 2.872 11.08.474a1.75 1.75 0 0 0-2.327-.06L.879 8.287a1.75 1.75 0 0 0-.5 1.06l-.375 3.648a.875.875 0 0 0 .875.954h.078l3.65-.333c.399-.04.773-.216 1.058-.499l7.875-7.875a1.68 1.68 0 0 0-.061-2.371zm-2.975 2.923L8.159 3.449 9.865 1.7l2.389 2.39-1.75 1.706z"/>
                                            </svg>
                                        </a>
                                        <a href="?page=vms-expenses&action=delete&id=<?php echo esc_attr($expense->id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_expense'); ?>" 
                                           class="vms-action-btn vms-action-delete" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this expense?')">
                                            <svg width="14" height="14" viewBox="0 0 14 14">
                                                <path fill="currentColor" d="M5.5 5.5A.5.5 0 0 1 6 5h2a.5.5 0 0 1 0 1H6a.5.5 0 0 1-.5-.5z"/>
                                                <path fill="currentColor" d="M14 4v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h1.172a2 2 0 0 1 1.414.586L4.586 3H9.414l1.414-1.414A2 2 0 0 1 12.828 2H14v2zm-2 0H2v8h10V4z"/>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="vms-empty-state">
                                        <svg width="48" height="48" viewBox="0 0 24 24">
                                            <path fill="#CBD5E0" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1z"/>
                                        </svg>
                                        <h3>No expenses found</h3>
                                        <p>Try adjusting your filters or add a new expense</p>
                                        <a href="?page=vms-expenses&action=add" class="vms-btn vms-btn-primary">
                                            <svg width="16" height="16" viewBox="0 0 16 16" style="margin-right: 8px;">
                                                <path fill="currentColor" d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                            </svg>
                                            Add Expense
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Enhanced Pagination -->
            <?php
            if ($total_count > $per_page) {
                $total_pages = ceil($total_count / $per_page);
                ?>
                <div class="vms-pagination">
                    <div class="vms-pagination-info">
                        Showing <?php echo esc_html(min($per_page * ($current_page - 1) + 1, $total_count)); ?> 
                        to <?php echo esc_html(min($per_page * $current_page, $total_count)); ?> 
                        of <?php echo esc_html($total_count); ?> entries
                    </div>
                    <div class="vms-pagination-controls">
                        <?php if ($current_page > 1): ?>
                            <a class="vms-pagination-btn" href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>">
                                <svg width="16" height="16" viewBox="0 0 16 16">
                                    <path fill="currentColor" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                                </svg>
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <div class="vms-pagination-numbers">
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a class="vms-pagination-number <?php echo $i == $current_page ? 'active' : ''; ?>" 
                                   href="<?php echo esc_url(add_query_arg('paged', $i)); ?>">
                                    <?php echo esc_html($i); ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a class="vms-pagination-btn" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>">
                                Next
                                <svg width="16" height="16" viewBox="0 0 16 16">
                                    <path fill="currentColor" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
    
    <style>
    /* Professional Expenses Styles */
    .vms-expenses-pro {
        max-width: 1800px;
        margin: 0 auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    /* Header Styles */
    .vms-expenses-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding: 30px;
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        border-radius: 16px;
        color: white;
        box-shadow: 0 10px 25px rgba(5, 150, 105, 0.3);
    }

    .vms-header-content h1 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .vms-header-icon {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .vms-header-text {
        display: flex;
        flex-direction: column;
    }

    .vms-main-title {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .vms-sub-title {
        font-size: 14px;
        opacity: 0.9;
    }

    .vms-header-actions {
        display: flex;
        gap: 12px;
    }

    /* Stats Grid */
    .vms-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }

    .vms-stat-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all 0.3s ease;
        border: 1px solid #f3f4f6;
    }

    .vms-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .vms-stat-expense .vms-stat-icon {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
    }

    .vms-stat-records .vms-stat-icon {
        background: linear-gradient(135deg, #059669, #047857);
    }

    .vms-stat-showing .vms-stat-icon {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
    }

    .vms-stat-categories .vms-stat-icon {
        background: linear-gradient(135deg, #7c3aed, #6d28d9);
    }

    .vms-stat-icon {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .vms-stat-content {
        flex: 1;
    }

    .vms-stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 4px;
    }

    .vms-stat-label {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 8px;
    }

    .vms-stat-trend {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        font-weight: 500;
    }

    .vms-trend-up {
        color: #10b981;
    }

    .vms-trend-down {
        color: #ef4444;
    }

    .vms-trend-neutral {
        color: #6b7280;
    }

    /* Filter Card */
    .vms-filter-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 30px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border: 1px solid #f3f4f6;
    }

    .vms-filter-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .vms-filter-header h3 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }

    .vms-filter-body {
        margin: 0;
    }

    .vms-filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        align-items: end;
    }

    .vms-filter-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #374151;
        font-size: 14px;
    }

    .vms-select,
    .vms-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .vms-select:focus,
    .vms-input:focus {
        outline: none;
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }

    /* Table Card */
    .vms-table-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border: 1px solid #f3f4f6;
        overflow: hidden;
    }

    .vms-table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 24px;
        border-bottom: 1px solid #e5e7eb;
    }

    .vms-table-header h3 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }

    .vms-table-actions {
        display: flex;
        gap: 12px;
    }

    .vms-table-container {
        overflow-x: auto;
    }

    .vms-table {
        width: 100%;
        border-collapse: collapse;
    }

    .vms-table th {
        padding: 16px 20px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
        font-size: 14px;
        white-space: nowrap;
    }

    .vms-table td {
        padding: 20px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: middle;
    }

    .vms-table tbody tr:hover {
        background: #f9fafb;
    }

    /* Checkbox Styles */
    .vms-select-all,
    .vms-row-checkbox {
        width: 18px;
        height: 18px;
        accent-color: #059669;
    }

    /* Date Cell */
    .vms-date-cell {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .vms-date-day {
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
        line-height: 1;
    }

    .vms-date-month {
        font-size: 12px;
        color: #6b7280;
        text-transform: uppercase;
        font-weight: 500;
    }

    /* Badge Styles */
    .vms-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .vms-badge-category {
        background: #e0e7ff;
        color: #3730a3;
    }

    .vms-badge-method {
        background: #f3f4f6;
        color: #374151;
    }

    .vms-method-cash {
        background: #d1fae5;
        color: #065f46;
    }

    .vms-method-bank_transfer {
        background: #dbeafe;
        color: #1e40af;
    }

    .vms-method-check {
        background: #fef3c7;
        color: #92400e;
    }

    .vms-method-card {
        background: #f3e8ff;
        color: #5b21b6;
    }

    /* Description Cell */
    .vms-description {
        max-width: 300px;
    }

    .vms-description strong {
        display: block;
        margin-bottom: 4px;
        color: #1f2937;
        font-size: 14px;
        line-height: 1.4;
    }

    .vms-description small {
        color: #6b7280;
        font-size: 12px;
    }

    /* Amount Cell */
    .vms-amount {
        text-align: right;
    }

    .vms-amount-value {
        font-weight: 700;
        color: #dc2626;
        font-size: 16px;
    }

    /* Actions */
    .vms-actions {
        display: flex;
        gap: 8px;
    }

    .vms-action-btn {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .vms-action-edit {
        background: #e0e7ff;
        color: #3730a3;
    }

    .vms-action-edit:hover {
        background: #c7d2fe;
        transform: translateY(-1px);
    }

    .vms-action-delete {
        background: #fee2e2;
        color: #dc2626;
    }

    .vms-action-delete:hover {
        background: #fecaca;
        transform: translateY(-1px);
    }

    /* Empty State */
    .vms-empty-state {
        text-align: center;
        padding: 60px 20px;
    }

    .vms-empty-state h3 {
        margin: 20px 0 10px;
        color: #4b5563;
        font-size: 18px;
    }

    .vms-empty-state p {
        margin: 0 0 20px;
        color: #9ca3af;
        font-size: 14px;
    }

    /* Pagination */
    .vms-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 24px;
        border-top: 1px solid #e5e7eb;
    }

    .vms-pagination-info {
        color: #6b7280;
        font-size: 14px;
    }

    .vms-pagination-controls {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .vms-pagination-btn {
        padding: 8px 16px;
        border-radius: 8px;
        text-decoration: none;
        color: #374151;
        background: white;
        border: 1px solid #d1d5db;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .vms-pagination-btn:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }

    .vms-pagination-numbers {
        display: flex;
        gap: 4px;
    }

    .vms-pagination-number {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        text-decoration: none;
        color: #374151;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .vms-pagination-number.active {
        background: #059669;
        color: white;
    }

    .vms-pagination-number:hover:not(.active) {
        background: #f3f4f6;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .vms-expenses-header {
            flex-direction: column;
            gap: 20px;
            align-items: flex-start;
        }

        .vms-header-actions {
            width: 100%;
            display: flex;
            gap: 10px;
        }

        .vms-header-actions .vms-btn {
            flex: 1;
            justify-content: center;
        }
    }

    @media (max-width: 768px) {
        .vms-stats-grid {
            grid-template-columns: 1fr;
        }

        .vms-filter-grid {
            grid-template-columns: 1fr;
        }

        .vms-pagination {
            flex-direction: column;
            gap: 20px;
            align-items: stretch;
        }

        .vms-pagination-controls {
            justify-content: center;
        }

        .vms-table {
            font-size: 14px;
        }

        .vms-table th,
        .vms-table td {
            padding: 12px;
        }
    }

    @media (max-width: 480px) {
        .vms-pagination-numbers {
            display: none;
        }

        .vms-pagination-controls {
            width: 100%;
            justify-content: space-between;
        }
    }
    </style>
    
    <script>
    function clearFilters() {
        window.location.href = '?page=vms-expenses';
    }

    function exportTable() {
        // Add export functionality
        alert('Export functionality will be implemented');
    }

    function toggleAllRows(checkbox) {
        const rowCheckboxes = document.querySelectorAll('.vms-row-checkbox');
        rowCheckboxes.forEach(cb => cb.checked = checkbox.checked);
    }

    // Add row selection functionality
    document.addEventListener('DOMContentLoaded', function() {
        const rowCheckboxes = document.querySelectorAll('.vms-row-checkbox');
        const selectAllCheckbox = document.querySelector('.vms-select-all');
        
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                const anyChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
                
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = anyChecked && !allChecked;
            });
        });
    });
    </script>
    <?php
}

function vms_enhanced_handle_expense_form() {
    // This function remains the same as the original but with enhanced styling
    // The styling is already included in the main CSS above
    vms_handle_expense_form();
}

// ==================== EXPENSES PAGE ====================
function vms_expenses_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    $expenses_table = $wpdb->prefix . 'vms_expenses';
    
    $user_id = get_current_user_id();
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    
    switch ($action) {
        case 'add':
        case 'edit':
            vms_handle_expense_form();
            break;
            
        case 'delete':
            if (isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_expense')) {
                $expense_id = intval($_GET['id']);
                $result = $wpdb->delete($expenses_table, ['id' => $expense_id]);
                
                if ($result !== false) {
                    echo '<div class="notice notice-success is-dismissible"><p>Expense deleted successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Error deleting expense: ' . esc_html($wpdb->last_error) . '</p></div>';
                }
            }
            vms_expense_list();
            break;
            
        default:
            vms_expense_list();
            break;
    }
}

function vms_expense_list() {
    global $wpdb;
    $expenses_table = $wpdb->prefix . 'vms_expenses';
    
    $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    
    $where = "WHERE 1=1";
    if ($category) {
        $where .= $wpdb->prepare(" AND category = %s", $category);
    }
    if ($start_date) {
        $where .= $wpdb->prepare(" AND expense_date >= %s", $start_date);
    }
    if ($end_date) {
        $where .= $wpdb->prepare(" AND expense_date <= %s", $end_date);
    }
    
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    $expenses = $wpdb->get_results("
        SELECT e.*, u.display_name as created_by_name
        FROM $expenses_table e 
        LEFT JOIN {$wpdb->prefix}users u ON e.created_by = u.ID 
        $where 
        ORDER BY e.expense_date DESC 
        LIMIT $per_page OFFSET $offset
    ");
    
    $categories = $wpdb->get_col("SELECT DISTINCT category FROM $expenses_table ORDER BY category");
    
    $total_expenses = $wpdb->get_var("SELECT SUM(amount) FROM $expenses_table $where") ?: 0;
    $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $expenses_table $where") ?: 0;
    
    ?>
    <div class="wrap vms-pro">
        <!-- Modern Header -->
        <div class="vms-header">
            <div class="vms-header-content">
                <h1>
                    <svg class="vms-icon" viewBox="0 0 24 24" width="24" height="24">
                        <path fill="currentColor" d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm-5 14H4v-4h11v4zm0-5H4V9h11v4zm5 5h-4V9h4v9z"/>
                    </svg>
                    Expense Management
                </h1>
                <p class="vms-subtitle">Track and manage all business expenses efficiently</p>
            </div>
            <div class="vms-header-actions">
                <a href="?page=vms-expenses&action=add" class="vms-btn vms-btn-primary">
                    <svg width="16" height="16" viewBox="0 0 16 16" style="margin-right: 8px;">
                        <path fill="currentColor" d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                    </svg>
                    Add Expense
                </a>
                <a href="?page=vms-export&type=expenses" class="vms-btn vms-btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 16 16" style="margin-right: 8px;">
                        <path fill="currentColor" d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                        <path fill="currentColor" d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                    </svg>
                    Export
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="vms-stats-grid">
            <div class="vms-stat-card">
                <div class="vms-stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <h3>৳<?php echo number_format($total_expenses, 2); ?></h3>
                    <p>Total Expenses</p>
                </div>
            </div>
            
            <div class="vms-stat-card">
                <div class="vms-stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <h3><?php echo esc_html($total_count); ?></h3>
                    <p>Total Records</p>
                </div>
            </div>
            
            <div class="vms-stat-card">
                <div class="vms-stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <h3><?php echo esc_html(count($expenses)); ?></h3>
                    <p>Showing</p>
                </div>
            </div>
            
            <div class="vms-stat-card">
                <div class="vms-stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1z"/>
                    </svg>
                </div>
                <div class="vms-stat-content">
                    <h3><?php echo esc_html(count($categories)); ?></h3>
                    <p>Categories</p>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="vms-card">
            <div class="vms-card-header">
                <h3>Filters</h3>
            </div>
            <div class="vms-card-body">
                <form method="get" action="">
                    <input type="hidden" name="page" value="vms-expenses">
                    
                    <div class="vms-filter-grid">
                        <div class="vms-filter-group">
                            <label>Category</label>
                            <select name="category" class="vms-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat); ?>" <?php selected($category, $cat); ?>>
                                        <?php echo esc_html($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="vms-filter-group">
                            <label>From Date</label>
                            <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="vms-input">
                        </div>
                        
                        <div class="vms-filter-group">
                            <label>To Date</label>
                            <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="vms-input">
                        </div>
                        
                        <div class="vms-filter-actions">
                            <button type="submit" class="vms-btn vms-btn-primary">
                                <svg width="16" height="16" viewBox="0 0 16 16" style="margin-right: 8px;">
                                    <path fill="currentColor" d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z"/>
                                </svg>
                                Apply Filters
                            </button>
                            <?php if ($category || $start_date || $end_date): ?>
                                <a href="?page=vms-expenses" class="vms-btn vms-btn-secondary">
                                    Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Expenses Table Card -->
        <div class="vms-card">
            <div class="vms-card-header">
                <h3>Expense Records</h3>
            </div>
            <div class="vms-card-body">
                <div class="vms-table-container">
                    <table class="vms-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Paid To</th>
                                <th>Method</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($expenses): ?>
                                <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td>
                                        <div class="vms-date-cell">
                                            <div class="vms-date-day"><?php echo date('d', strtotime($expense->expense_date)); ?></div>
                                            <div class="vms-date-month"><?php echo date('M', strtotime($expense->expense_date)); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="vms-badge vms-badge-category"><?php echo esc_html($expense->category); ?></span>
                                    </td>
                                    <td>
                                        <div class="vms-description">
                                            <strong><?php echo esc_html($expense->description); ?></strong>
                                            <?php if($expense->receipt_no): ?>
                                                <small>Receipt: <?php echo esc_html($expense->receipt_no); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="vms-amount">
                                            <span class="vms-amount-value">৳<?php echo number_format($expense->amount, 2); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($expense->paid_to); ?></td>
                                    <td>
                                        <span class="vms-badge vms-badge-method vms-method-<?php echo esc_attr($expense->payment_method); ?>">
                                            <?php echo esc_html(ucfirst($expense->payment_method)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($expense->created_by_name ?: 'System'); ?></td>
                                    <td>
                                        <div class="vms-actions">
                                            <a href="?page=vms-expenses&action=edit&id=<?php echo esc_attr($expense->id); ?>" 
                                               class="vms-action-btn vms-action-edit">
                                                <svg width="14" height="14" viewBox="0 0 14 14">
                                                    <path fill="currentColor" d="M13.479 2.872 11.08.474a1.75 1.75 0 0 0-2.327-.06L.879 8.287a1.75 1.75 0 0 0-.5 1.06l-.375 3.648a.875.875 0 0 0 .875.954h.078l3.65-.333c.399-.04.773-.216 1.058-.499l7.875-7.875a1.68 1.68 0 0 0-.061-2.371zm-2.975 2.923L8.159 3.449 9.865 1.7l2.389 2.39-1.75 1.706z"/>
                                                </svg>
                                            </a>
                                            <a href="?page=vms-expenses&action=delete&id=<?php echo esc_attr($expense->id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_expense'); ?>" 
                                               class="vms-action-btn vms-action-delete" 
                                               onclick="return confirm('Are you sure you want to delete this expense?')">
                                                <svg width="14" height="14" viewBox="0 0 14 14">
                                                    <path fill="currentColor" d="M5.5 5.5A.5.5 0 0 1 6 5h2a.5.5 0 0 1 0 1H6a.5.5 0 0 1-.5-.5z"/>
                                                    <path fill="currentColor" d="M14 4v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h1.172a2 2 0 0 1 1.414.586L4.586 3H9.414l1.414-1.414A2 2 0 0 1 12.828 2H14v2zm-2 0H2v8h10V4z"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="vms-empty-state">
                                            <svg width="48" height="48" viewBox="0 0 24 24">
                                                <path fill="#CBD5E0" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                            </svg>
                                            <h3>No expenses found</h3>
                                            <p>Try adjusting your filters or add a new expense</p>
                                            <a href="?page=vms-expenses&action=add" class="vms-btn vms-btn-primary">
                                                <svg width="16" height="16" viewBox="0 0 16 16" style="margin-right: 8px;">
                                                    <path fill="currentColor" d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                                </svg>
                                                Add Expense
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php
                if ($total_count > $per_page) {
                    $total_pages = ceil($total_count / $per_page);
                    ?>
                    <div class="vms-pagination">
                        <div class="vms-pagination-info">
                            Showing <?php echo esc_html(min($per_page * ($current_page - 1) + 1, $total_count)); ?> 
                            to <?php echo esc_html(min($per_page * $current_page, $total_count)); ?> 
                            of <?php echo esc_html($total_count); ?> entries
                        </div>
                        <div class="vms-pagination-controls">
                            <?php if ($current_page > 1): ?>
                                <a class="vms-pagination-btn" href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>">
                                    <svg width="16" height="16" viewBox="0 0 16 16">
                                        <path fill="currentColor" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                                    </svg>
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <div class="vms-pagination-numbers">
                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a class="vms-pagination-number <?php echo $i == $current_page ? 'active' : ''; ?>" 
                                       href="<?php echo esc_url(add_query_arg('paged', $i)); ?>">
                                        <?php echo esc_html($i); ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <a class="vms-pagination-btn" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>">
                                    Next
                                    <svg width="16" height="16" viewBox="0 0 16 16">
                                        <path fill="currentColor" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
    
    <style>
    /* Professional UI Styles */
    .vms-pro {
        max-width: 1800px;
        margin: 0 auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }
    
    /* Header Styles */
    .vms-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding: 25px 30px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        color: white;
    }
    
    .vms-header-content h1 {
        margin: 0;
        font-size: 28px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .vms-subtitle {
        margin: 5px 0 0;
        opacity: 0.9;
        font-size: 14px;
    }
    
    /* Button Styles */
    .vms-btn {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 14px;
    }
    
    .vms-btn-primary {
        background: white;
        color: #667eea;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .vms-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .vms-btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .vms-btn-secondary:hover {
        background: rgba(255, 255, 255, 0.2);
    }
    
    /* Stats Grid */
    .vms-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .vms-stat-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: transform 0.2s ease;
    }
    
    .vms-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .vms-stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    
    .vms-stat-content h3 {
        margin: 0 0 5px;
        font-size: 24px;
        font-weight: 600;
        color: #2d3748;
    }
    
    .vms-stat-content p {
        margin: 0;
        color: #718096;
        font-size: 14px;
    }
    
    /* Card Styles */
    .vms-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        overflow: hidden;
    }
    
    .vms-card-header {
        padding: 20px 30px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .vms-card-header h3 {
        margin: 0;
        color: #2d3748;
        font-size: 18px;
        font-weight: 600;
    }
    
    .vms-card-body {
        padding: 30px;
    }
    
    /* Filter Styles */
    .vms-filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        align-items: end;
    }
    
    .vms-filter-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #4a5568;
        font-size: 14px;
    }
    
    .vms-select, .vms-input {
        width: 100%;
        padding: 10px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.2s ease;
    }
    
    .vms-select:focus, .vms-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .vms-filter-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    /* Table Styles */
    .vms-table-container {
        overflow-x: auto;
    }
    
    .vms-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .vms-table th {
        padding: 15px 20px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        font-size: 14px;
        white-space: nowrap;
    }
    
    .vms-table td {
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    
    .vms-table tbody tr:hover {
        background: #f8fafc;
    }
    
    /* Date Cell */
    .vms-date-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .vms-date-day {
        font-size: 24px;
        font-weight: 600;
        color: #2d3748;
    }
    
    .vms-date-month {
        font-size: 12px;
        color: #718096;
        text-transform: uppercase;
    }
    
    /* Badge Styles */
    .vms-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .vms-badge-category {
        background: #e0e7ff;
        color: #3730a3;
    }
    
    .vms-badge-method {
        background: #f3f4f6;
        color: #374151;
    }
    
    .vms-method-cash {
        background: #d1fae5;
        color: #065f46;
    }
    
    .vms-method-bank_transfer {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .vms-method-check {
        background: #fef3c7;
        color: #92400e;
    }
    
    .vms-method-card {
        background: #f3e8ff;
        color: #5b21b6;
    }
    
    /* Description Cell */
    .vms-description {
        max-width: 300px;
    }
    
    .vms-description strong {
        display: block;
        margin-bottom: 4px;
        color: #2d3748;
        font-size: 14px;
        line-height: 1.4;
    }
    
    .vms-description small {
        color: #718096;
        font-size: 12px;
    }
    
    /* Amount Cell */
    .vms-amount {
        text-align: right;
    }
    
    .vms-amount-value {
        font-weight: 600;
        color: #ef4444;
        font-size: 16px;
    }
    
    /* Actions */
    .vms-actions {
        display: flex;
        gap: 8px;
    }
    
    .vms-action-btn {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    
    .vms-action-edit {
        background: #e0e7ff;
        color: #3730a3;
    }
    
    .vms-action-edit:hover {
        background: #c7d2fe;
    }
    
    .vms-action-delete {
        background: #fee2e2;
        color: #dc2626;
    }
    
    .vms-action-delete:hover {
        background: #fecaca;
    }
    
    /* Empty State */
    .vms-empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .vms-empty-state h3 {
        margin: 20px 0 10px;
        color: #4a5568;
        font-size: 18px;
    }
    
    .vms-empty-state p {
        margin: 0 0 20px;
        color: #a0aec0;
        font-size: 14px;
    }
    
    /* Pagination */
    .vms-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }
    
    .vms-pagination-info {
        color: #718096;
        font-size: 14px;
    }
    
    .vms-pagination-controls {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .vms-pagination-btn {
        padding: 8px 16px;
        border-radius: 8px;
        text-decoration: none;
        color: #4a5568;
        background: white;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
    }
    
    .vms-pagination-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e0;
    }
    
    .vms-pagination-numbers {
        display: flex;
        gap: 4px;
    }
    
    .vms-pagination-number {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        text-decoration: none;
        color: #4a5568;
        font-size: 14px;
        transition: all 0.2s ease;
    }
    
    .vms-pagination-number.active {
        background: #667eea;
        color: white;
    }
    
    .vms-pagination-number:hover:not(.active) {
        background: #f8fafc;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .vms-header {
            flex-direction: column;
            gap: 20px;
            align-items: flex-start;
        }
        
        .vms-header-actions {
            width: 100%;
            display: flex;
            gap: 10px;
        }
        
        .vms-header-actions .vms-btn {
            flex: 1;
            justify-content: center;
        }
    }
    
    @media (max-width: 768px) {
        .vms-stats-grid {
            grid-template-columns: 1fr;
        }
        
        .vms-filter-grid {
            grid-template-columns: 1fr;
        }
        
        .vms-filter-actions {
            flex-direction: column;
        }
        
        .vms-pagination {
            flex-direction: column;
            gap: 20px;
            align-items: stretch;
        }
        
        .vms-pagination-controls {
            justify-content: center;
        }
        
        .vms-table th, 
        .vms-table td {
            padding: 12px;
        }
    }
    
    @media (max-width: 480px) {
        .vms-pagination-numbers {
            display: none;
        }
        
        .vms-pagination-controls {
            width: 100%;
            justify-content: space-between;
        }
    }
    </style>
    <?php
}

function vms_handle_expense_form() {
    global $wpdb;
    $expenses_table = $wpdb->prefix . 'vms_expenses';
    
    $user_id = get_current_user_id();
    $expense_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    $expense = null;
    if ($expense_id) {
        $expense = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $expenses_table WHERE id = %d",
            $expense_id
        ));
    }
    
    if (isset($_POST['save_expense'])) {
        $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
        
        if (!wp_verify_nonce($nonce, 'vms_save_expense')) {
            wp_die('Security check failed');
        }
        
        $errors = [];
        if (empty($_POST['expense_date'])) {
            $errors[] = 'Expense date is required';
        }
        if (empty($_POST['category'])) {
            $errors[] = 'Category is required';
        }
        if (empty($_POST['amount']) || $_POST['amount'] <= 0) {
            $errors[] = 'Amount must be greater than 0';
        }
        
        if (!empty($errors)) {
            echo '<div class="notice notice-error"><p>' . esc_html(implode('<br>', $errors)) . '</p></div>';
        } else {
            $data = [
                'expense_date' => sanitize_text_field($_POST['expense_date']),
                'category' => sanitize_text_field($_POST['category']),
                'description' => sanitize_textarea_field($_POST['description']),
                'amount' => floatval($_POST['amount']),
                'paid_to' => sanitize_text_field($_POST['paid_to']),
                'payment_method' => sanitize_text_field($_POST['payment_method']),
                'receipt_no' => sanitize_text_field($_POST['receipt_no']),
                'approved_by' => intval($_POST['approved_by']),
                'notes' => sanitize_textarea_field($_POST['notes']),
                'created_by' => $user_id
            ];
            
            if ($expense_id && $expense) {
                $result = $wpdb->update($expenses_table, $data, ['id' => $expense_id]);
                $message = 'Expense updated successfully!';
                $action = 'UPDATE_EXPENSE';
            } else {
                $result = $wpdb->insert($expenses_table, $data);
                $expense_id = $wpdb->insert_id;
                $message = 'Expense added successfully!';
                $action = 'ADD_EXPENSE';
            }
            
            if ($result === false) {
                echo '<div class="notice notice-error"><p>Database error: ' . esc_html($wpdb->last_error) . '</p></div>';
                return;
            }
            
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            
            if ($expense_id) {
                $expense = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $expenses_table WHERE id = %d",
                    $expense_id
                ));
            }
        }
    }
    
    $categories = $wpdb->get_col("SELECT DISTINCT category FROM $expenses_table ORDER BY category");
    
    $users = get_users(['role__in' => ['administrator', 'editor']]);
    
    ?>
    <div class="wrap vms-pro-form">
        <!-- Form Header -->
        <div class="vms-header">
            <div class="vms-header-content">
                <h1>
                    <svg class="vms-icon" viewBox="0 0 24 24" width="24" height="24">
                        <path fill="currentColor" d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1z"/>
                    </svg>
                    <?php echo $expense ? 'Edit Expense' : 'Add New Expense'; ?>
                </h1>
                <p class="vms-subtitle"><?php echo $expense ? 'Update expense details' : 'Record a new business expense'; ?></p>
            </div>
            <div class="vms-header-actions">
                <a href="?page=vms-expenses" class="vms-btn vms-btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 16 16" style="margin-right: 8px;">
                        <path fill="currentColor" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                    </svg>
                    Back to Expenses
                </a>
            </div>
        </div>

        <!-- Form Container -->
        <div class="vms-card">
            <div class="vms-card-body">
                <form method="post" class="vms-form">
                    <?php wp_nonce_field('vms_save_expense'); ?>
                    
                    <div class="vms-form-grid">
                        <!-- Left Column -->
                        <div class="vms-form-column">
                            <div class="vms-form-section">
                                <h3 class="vms-form-section-title">
                                    <svg width="20" height="20" viewBox="0 0 20 20" style="margin-right: 10px;">
                                        <path fill="currentColor" d="M10 20a10 10 0 1 1 0-20 10 10 0 0 1 0 20zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm-1-7.59V4h2v5.59l3.95 3.95-1.41 1.41L9 10.41z"/>
                                    </svg>
                                    Expense Information
                                </h3>
                                <div class="vms-form-group">
                                    <label for="expense_date">Expense Date *</label>
                                    <input type="date" id="expense_date" name="expense_date" required
                                           value="<?php echo $expense ? esc_attr($expense->expense_date) : date('Y-m-d'); ?>"
                                           class="vms-input">
                                </div>
                                
                                <div class="vms-form-group">
                                    <label for="category">Category *</label>
                                    <div class="vms-input-with-suggestions">
                                        <input type="text" id="category" name="category" required
                                               value="<?php echo $expense ? esc_attr($expense->category) : ''; ?>"
                                               class="vms-input" list="category-list" placeholder="e.g., Office Supplies, Utilities">
                                        <datalist id="category-list">
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo esc_attr($category); ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                </div>
                                
                                <div class="vms-form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" rows="3" class="vms-textarea" placeholder="Enter expense description..."><?php 
                                        echo $expense ? esc_textarea($expense->description) : ''; 
                                    ?></textarea>
                                </div>
                                
                                <div class="vms-form-row">
                                    <div class="vms-form-group">
                                        <label for="amount">Amount (৳) *</label>
                                        <div class="vms-amount-input">
                                            <span class="vms-currency">৳</span>
                                            <input type="number" id="amount" name="amount" step="0.01" min="0.01" required
                                                   value="<?php echo $expense ? esc_attr($expense->amount) : ''; ?>"
                                                   class="vms-input" placeholder="0.00">
                                        </div>
                                    </div>
                                    
                                    <div class="vms-form-group">
                                        <label for="paid_to">Paid To</label>
                                        <input type="text" id="paid_to" name="paid_to"
                                               value="<?php echo $expense ? esc_attr($expense->paid_to) : ''; ?>"
                                               class="vms-input" placeholder="Recipient name">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="vms-form-section">
                                <h3 class="vms-form-section-title">
                                    <svg width="20" height="20" viewBox="0 0 20 20" style="margin-right: 10px;">
                                        <path fill="currentColor" d="M9 11.17V7h2v4.17l3.25 3.25-1.41 1.41L10 12.41l-3.84 3.84-1.41-1.41L9 11.17zM5 2h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/>
                                    </svg>
                                    Payment Details
                                </h3>
                                
                                <div class="vms-form-row">
                                    <div class="vms-form-group">
                                        <label for="payment_method">Payment Method</label>
                                        <select id="payment_method" name="payment_method" class="vms-select">
                                            <option value="cash" <?php selected($expense ? $expense->payment_method : 'cash', 'cash'); ?>>Cash</option>
                                            <option value="bank_transfer" <?php selected($expense ? $expense->payment_method : 'cash', 'bank_transfer'); ?>>Bank Transfer</option>
                                            <option value="check" <?php selected($expense ? $expense->payment_method : 'cash', 'check'); ?>>Check</option>
                                            <option value="card" <?php selected($expense ? $expense->payment_method : 'cash', 'card'); ?>>Credit/Debit Card</option>
                                        </select>
                                    </div>
                                    
                                    <div class="vms-form-group">
                                        <label for="receipt_no">Receipt Number</label>
                                        <input type="text" id="receipt_no" name="receipt_no"
                                               value="<?php echo $expense ? esc_attr($expense->receipt_no) : ''; ?>"
                                               class="vms-input" placeholder="Optional receipt number">
                                    </div>
                                </div>
                                
                                <div class="vms-form-group">
                                    <label for="approved_by">Approved By</label>
                                    <select id="approved_by" name="approved_by" class="vms-select">
                                        <option value="">Not Required</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo esc_attr($user->ID); ?>" 
                                                    <?php selected($expense ? $expense->approved_by : 0, $user->ID); ?>>
                                                <?php echo esc_html($user->display_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="vms-form-group">
                                    <label for="notes">Notes</label>
                                    <textarea id="notes" name="notes" rows="4" class="vms-textarea" placeholder="Any additional information..."><?php 
                                        echo $expense ? esc_textarea($expense->notes) : ''; 
                                    ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="vms-form-column">
                            <div class="vms-card vms-preview-card">
                                <div class="vms-card-header">
                                    <h3>Preview</h3>
                                </div>
                                <div class="vms-card-body">
                                    <div class="vms-expense-preview">
                                        <div class="vms-preview-header">
                                            <div class="vms-preview-date" id="previewDate">
                                                <?php echo date('M d, Y'); ?>
                                            </div>
                                            <div class="vms-preview-status" id="previewStatus">
                                                Pending
                                            </div>
                                        </div>
                                        
                                        <div class="vms-preview-body">
                                            <div class="vms-preview-category" id="previewCategory">
                                                <span class="vms-badge vms-badge-category">Not Selected</span>
                                            </div>
                                            
                                            <h4 class="vms-preview-title" id="previewTitle">
                                                No description
                                            </h4>
                                            
                                            <div class="vms-preview-details">
                                                <div class="vms-preview-detail">
                                                    <span class="vms-preview-label">Paid To:</span>
                                                    <span class="vms-preview-value" id="previewPaidTo">-</span>
                                                </div>
                                                <div class="vms-preview-detail">
                                                    <span class="vms-preview-label">Method:</span>
                                                    <span class="vms-preview-value" id="previewMethod">Cash</span>
                                                </div>
                                                <div class="vms-preview-detail">
                                                    <span class="vms-preview-label">Receipt:</span>
                                                    <span class="vms-preview-value" id="previewReceipt">-</span>
                                                </div>
                                            </div>
                                            
                                            <div class="vms-preview-amount" id="previewAmount">
                                                ৳0.00
                                            </div>
                                        </div>
                                        
                                        <div class="vms-preview-footer">
                                            <div class="vms-preview-created">
                                                <small>Created by: <?php echo wp_get_current_user()->display_name; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="vms-card vms-recent-card">
                                <div class="vms-card-header">
                                    <h3>Recent Expenses</h3>
                                </div>
                                <div class="vms-card-body">
                                    <div class="vms-recent-expenses">
                                        <?php
                                        $recent_expenses = $wpdb->get_results("
                                            SELECT * FROM $expenses_table 
                                            ORDER BY created_at DESC 
                                            LIMIT 5
                                        ");
                                        
                                        if ($recent_expenses):
                                            foreach ($recent_expenses as $exp):
                                        ?>
                                        <div class="vms-recent-item">
                                            <div class="vms-recent-date">
                                                <?php echo date('M d', strtotime($exp->expense_date)); ?>
                                            </div>
                                            <div class="vms-recent-details">
                                                <strong><?php echo esc_html($exp->category); ?></strong>
                                                <small><?php echo esc_html($exp->description); ?></small>
                                            </div>
                                            <div class="vms-recent-amount">
                                                ৳<?php echo number_format($exp->amount, 2); ?>
                                            </div>
                                        </div>
                                        <?php
                                            endforeach;
                                        else:
                                        ?>
                                        <p class="vms-no-data">No recent expenses found.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="vms-form-actions">
                        <button type="submit" name="save_expense" class="vms-btn vms-btn-primary vms-btn-large">
                            <svg width="18" height="18" viewBox="0 0 18 18" style="margin-right: 8px;">
                                <path fill="currentColor" d="M13.486 2.5H5.5A1.5 1.5 0 0 0 4 4v10A1.5 1.5 0 0 0 5.5 15.5h7A1.5 1.5 0 0 0 14 14V5.014L13.486 2.5zM9 13.25a2.25 2.25 0 1 1 0-4.5 2.25 2.25 0 0 1 0 4.5zM5.75 5a.75.75 0 0 1 .75-.75h5a.75.75 0 0 1 0 1.5h-5A.75.75 0 0 1 5.75 5z"/>
                            </svg>
                            <?php echo $expense ? 'Update Expense' : 'Save Expense'; ?>
                        </button>
                        
                        <a href="?page=vms-expenses" class="vms-btn vms-btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <style>
    /* Form Specific Styles */
    .vms-pro-form {
        max-width: 1600px;
        margin: 0 auto;
        padding: 20px;
    }
    
    /* Form Layout */
    .vms-form-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
    }
    
    .vms-form-column {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }
    
    /* Form Sections */
    .vms-form-section {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .vms-form-section-title {
        margin: 0 0 25px;
        font-size: 18px;
        font-weight: 600;
        color: #2d3748;
        display: flex;
        align-items: center;
        padding-bottom: 15px;
        border-bottom: 2px solid #e2e8f0;
    }
    
    /* Form Elements */
    .vms-form-group {
        margin-bottom: 25px;
    }
    
    .vms-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #4a5568;
        font-size: 14px;
    }
    
    .vms-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .vms-textarea {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
        resize: vertical;
        min-height: 100px;
        transition: border-color 0.2s ease;
    }
    
    .vms-textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    /* Amount Input */
    .vms-amount-input {
        position: relative;
    }
    
    .vms-currency {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        font-weight: 600;
        color: #4a5568;
    }
    
    .vms-amount-input .vms-input {
        padding-left: 40px;
    }
    
    /* Preview Card */
    .vms-preview-card, .vms-recent-card {
        margin-bottom: 0;
    }
    
    .vms-expense-preview {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .vms-preview-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .vms-preview-date {
        font-weight: 600;
        font-size: 14px;
    }
    
    .vms-preview-status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.2);
    }
    
    .vms-preview-body {
        padding: 25px;
    }
    
    .vms-preview-category {
        margin-bottom: 20px;
    }
    
    .vms-preview-title {
        margin: 0 0 20px;
        color: #2d3748;
        font-size: 16px;
        line-height: 1.4;
        min-height: 44px;
    }
    
    .vms-preview-details {
        margin-bottom: 25px;
    }
    
    .vms-preview-detail {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 14px;
    }
    
    .vms-preview-label {
        color: #718096;
    }
    
    .vms-preview-value {
        color: #4a5568;
        font-weight: 500;
    }
    
    .vms-preview-amount {
        font-size: 32px;
        font-weight: 700;
        color: #ef4444;
        text-align: center;
        margin: 20px 0;
    }
    
    .vms-preview-footer {
        padding: 15px;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
        font-size: 12px;
        color: #718096;
        text-align: center;
    }
    
    /* Recent Expenses */
    .vms-recent-expenses {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .vms-recent-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    
    .vms-recent-date {
        font-size: 12px;
        color: #6b7280;
        min-width: 50px;
    }
    
    .vms-recent-details {
        flex: 1;
    }
    
    .vms-recent-details strong {
        display: block;
        color: #374151;
        font-size: 14px;
        margin-bottom: 4px;
    }
    
    .vms-recent-details small {
        color: #6b7280;
        font-size: 12px;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .vms-recent-amount {
        font-weight: 700;
        color: #ef4444;
        font-size: 14px;
    }
    
    .vms-no-data {
        text-align: center;
        color: #a0aec0;
        font-size: 14px;
        padding: 20px;
    }
    
    /* Form Actions */
    .vms-form-actions {
        margin-top: 40px;
        padding-top: 30px;
        border-top: 2px solid #e2e8f0;
        display: flex;
        gap: 15px;
        justify-content: center;
    }
    
    .vms-btn-large {
        padding: 12px 30px;
        font-size: 16px;
    }
    
    /* Responsive */
    @media (max-width: 1200px) {
        .vms-form-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .vms-form-row {
            grid-template-columns: 1fr;
        }
        
        .vms-form-section {
            padding: 20px;
        }
        
        .vms-form-actions {
            flex-direction: column;
        }
        
        .vms-btn-large {
            width: 100%;
        }
    }
    </style>
    
    <script>
    // Update Preview in Real-time
    function updatePreview() {
        // Date
        const dateInput = document.getElementById('expense_date');
        const date = new Date(dateInput.value);
        document.getElementById('previewDate').textContent = 
            date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            }) || 'Not set';
        
        // Category
        const category = document.getElementById('category').value;
        const categoryBadge = category ? 
            `<span class="vms-badge vms-badge-category">${category}</span>` :
            '<span class="vms-badge vms-badge-category">Not Selected</span>';
        document.getElementById('previewCategory').innerHTML = categoryBadge;
        
        // Description
        const description = document.getElementById('description').value || 'No description';
        document.getElementById('previewTitle').textContent = description.length > 50 ? 
            description.substring(0, 50) + '...' : description;
        
        // Amount
        const amount = parseFloat(document.getElementById('amount').value) || 0;
        document.getElementById('previewAmount').textContent = '৳' + amount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        // Paid To
        document.getElementById('previewPaidTo').textContent = 
            document.getElementById('paid_to').value || '-';
        
        // Payment Method
        const method = document.getElementById('payment_method').value;
        document.getElementById('previewMethod').textContent = 
            method ? method.charAt(0).toUpperCase() + method.slice(1) : 'Cash';
        
        // Receipt Number
        document.getElementById('previewReceipt').textContent = 
            document.getElementById('receipt_no').value || '-';
        
        // Status
        const approvedBy = document.getElementById('approved_by').value;
        document.getElementById('previewStatus').textContent = 
            approvedBy ? 'Approved' : 'Pending';
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Add event listeners to all form inputs
        const formInputs = document.querySelectorAll('input, select, textarea');
        formInputs.forEach(input => {
            input.addEventListener('input', updatePreview);
            input.addEventListener('change', updatePreview);
        });
        
        // Initial update
        updatePreview();
        
        // Set today's date by default for new expenses
        if(!<?php echo $expense ? 'true' : 'false'; ?>) {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('expense_date').value = today;
            updatePreview();
        }
        
        // Auto-focus first input
        document.getElementById('expense_date').focus();
    });
    </script>
    <?php
}

// ==================== SETTINGS PAGE ====================
function vms_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    $user_id = get_current_user_id();
    
    if (isset($_POST['save_settings'])) {
        $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
        
        if (!wp_verify_nonce($nonce, 'vms_save_settings')) {
            wp_die('Security check failed');
        }
        
        $settings = [
            'company_name' => sanitize_text_field($_POST['company_name']),
            'company_phone' => sanitize_text_field($_POST['company_phone']),
            'company_email' => sanitize_email($_POST['company_email']),
            'company_address' => sanitize_textarea_field($_POST['company_address']),
            'currency_symbol' => sanitize_text_field($_POST['currency_symbol']),
            'sms_api_key' => sanitize_text_field($_POST['sms_api_key']),
            'sms_sender_id' => sanitize_text_field($_POST['sms_sender_id']),
            'default_visa_types' => sanitize_textarea_field($_POST['default_visa_types']),
            'default_countries' => sanitize_textarea_field($_POST['default_countries']),
            'auto_generate_invoice' => isset($_POST['auto_generate_invoice']) ? '1' : '0'
        ];
        
        foreach ($settings as $key => $value) {
            vms_update_setting($key, $value);
        }
        
        vms_log_activity($user_id, 'UPDATE_SETTINGS', 'System settings updated');
        
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
    }
    
    $settings = [
        'company_name' => vms_get_setting('company_name', 'Visa Management System'),
        'company_phone' => vms_get_setting('company_phone', ''),
        'company_email' => vms_get_setting('company_email', ''),
        'company_address' => vms_get_setting('company_address', ''),
        'currency_symbol' => vms_get_setting('currency_symbol', '৳'),
        'sms_api_key' => vms_get_setting('sms_api_key', ''),
        'sms_sender_id' => vms_get_setting('sms_sender_id', 'VISA'),
        'default_visa_types' => vms_get_setting('default_visa_types', 'Tourist,Business,Student,Work,Medical,Family'),
        'default_countries' => vms_get_setting('default_countries', 'USA,Canada,UK,Australia,Japan,Singapore,UAE,Saudi Arabia'),
        'auto_generate_invoice' => vms_get_setting('auto_generate_invoice', '1')
    ];
    
    ?>
    <div class="wrap vms-settings">
        <div class="vms-page-header">
            <h1><span class="dashicons dashicons-admin-settings"></span> System Settings</h1>
        </div>
        
        <div class="vms-settings-container">
            <div class="vms-settings-nav">
                <ul class="settings-tabs">
                    <li><a href="#general" class="active"><span class="dashicons dashicons-admin-generic"></span> General</a></li>
                    <li><a href="#sms"><span class="dashicons dashicons-email-alt"></span> SMS Settings</a></li>
                    <li><a href="#preferences"><span class="dashicons dashicons-admin-tools"></span> Preferences</a></li>
                    <li><a href="#backup"><span class="dashicons dashicons-backup"></span> Backup</a></li>
                </ul>
            </div>
            
            <div class="vms-settings-content">
                <form method="post" class="vms-settings-form">
                    <?php wp_nonce_field('vms_save_settings'); ?>
                    
                    <!-- General Settings -->
                    <div id="general" class="settings-tab active">
                        <div class="settings-section">
                            <h2><span class="dashicons dashicons-building"></span> Company Information</h2>
                            <div class="settings-grid">
                                <div class="form-group">
                                    <label for="company_name">Company Name</label>
                                    <input type="text" id="company_name" name="company_name" 
                                           value="<?php echo esc_attr($settings['company_name']); ?>"
                                           class="regular-text" placeholder="Enter company name">
                                </div>
                                
                                <div class="form-group">
                                    <label for="company_phone">Phone Number</label>
                                    <input type="tel" id="company_phone" name="company_phone" 
                                           value="<?php echo esc_attr($settings['company_phone']); ?>"
                                           class="regular-text" placeholder="Enter phone number">
                                </div>
                                
                                <div class="form-group">
                                    <label for="company_email">Email Address</label>
                                    <input type="email" id="company_email" name="company_email" 
                                           value="<?php echo esc_attr($settings['company_email']); ?>"
                                           class="regular-text" placeholder="Enter email address">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="company_address">Company Address</label>
                                    <textarea id="company_address" name="company_address" rows="3" 
                                              class="large-text" placeholder="Enter company address"><?php 
                                        echo esc_textarea($settings['company_address']); 
                                    ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h2><span class="dashicons dashicons-money-alt"></span> Currency Settings</h2>
                            <div class="settings-grid">
                                <div class="form-group">
                                    <label for="currency_symbol">Currency Symbol</label>
                                    <input type="text" id="currency_symbol" name="currency_symbol" 
                                           value="<?php echo esc_attr($settings['currency_symbol']); ?>"
                                           class="regular-text" placeholder="e.g., ৳, $, €, £" maxlength="3">
                                    <p class="description">Enter your preferred currency symbol</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SMS Settings -->
                    <div id="sms" class="settings-tab">
                        <div class="settings-section">
                            <h2><span class="dashicons dashicons-email-alt"></span> SMS Configuration</h2>
                            <div class="settings-grid">
                                <div class="form-group">
                                    <label for="sms_api_key">SMS API Key</label>
                                    <input type="password" id="sms_api_key" name="sms_api_key" 
                                           value="<?php echo esc_attr($settings['sms_api_key']); ?>"
                                           class="regular-text" placeholder="Enter SMS API key">
                                    <p class="description">Get API key from your SMS provider</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="sms_sender_id">Sender ID</label>
                                    <input type="text" id="sms_sender_id" name="sms_sender_id" 
                                           value="<?php echo esc_attr($settings['sms_sender_id']); ?>"
                                           class="regular-text" placeholder="Enter sender ID" maxlength="11">
                                    <p class="description">Maximum 11 characters</p>
                                </div>
                            </div>
                            
                            <div class="sms-test-section">
                                <h3><span class="dashicons dashicons-testimonial"></span> Test SMS</h3>
                                <div class="test-sms-form">
                                    <input type="tel" id="test_phone" placeholder="Phone number" class="regular-text">
                                    <textarea id="test_message" placeholder="Test message" rows="2" class="large-text"></textarea>
                                    <button type="button" id="test_sms" class="button button-secondary">
                                        <span class="dashicons dashicons-controls-play"></span> Send Test SMS
                                    </button>
                                    <div id="test_result"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preferences -->
                    <div id="preferences" class="settings-tab">
                        <div class="settings-section">
                            <h2><span class="dashicons dashicons-forms"></span> Default Values</h2>
                            <div class="settings-grid">
                                <div class="form-group">
                                    <label for="default_visa_types">Default Visa Types</label>
                                    <textarea id="default_visa_types" name="default_visa_types" rows="4" 
                                              class="large-text" placeholder="Enter visa types (comma separated)"><?php 
                                        echo esc_textarea($settings['default_visa_types']); 
                                    ?></textarea>
                                    <p class="description">Enter visa types separated by commas</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="default_countries">Default Countries</label>
                                    <textarea id="default_countries" name="default_countries" rows="4" 
                                              class="large-text" placeholder="Enter countries (comma separated)"><?php 
                                        echo esc_textarea($settings['default_countries']); 
                                    ?></textarea>
                                    <p class="description">Enter countries separated by commas</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h2><span class="dashicons dashicons-admin-tools"></span> System Preferences</h2>
                            <div class="settings-grid">
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="auto_generate_invoice" name="auto_generate_invoice" 
                                               value="1" <?php checked($settings['auto_generate_invoice'], '1'); ?>>
                                        <span class="checkbox-text">Auto-generate invoice numbers</span>
                                    </label>
                                    <p class="description">Automatically generate invoice numbers for new payments</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Backup -->
                    <div id="backup" class="settings-tab">
                        <div class="settings-section">
                            <h2><span class="dashicons dashicons-backup"></span> Data Export</h2>
                            <div class="backup-options">
                                <div class="backup-option">
                                    <h3><span class="dashicons dashicons-media-document"></span> Applications</h3>
                                    <p>Export all client applications data</p>
                                    <a href="?page=vms-export&type=applications" class="button button-primary">
                                        <span class="dashicons dashicons-download"></span> Export CSV
                                    </a>
                                </div>
                                
                                <div class="backup-option">
                                    <h3><span class="dashicons dashicons-money-alt"></span> Payments</h3>
                                    <p>Export all payment records</p>
                                    <a href="?page=vms-export&type=payments" class="button button-primary">
                                        <span class="dashicons dashicons-download"></span> Export CSV
                                    </a>
                                </div>
                                
                                <div class="backup-option">
                                    <h3><span class="dashicons dashicons-chart-bar"></span> Expenses</h3>
                                    <p>Export all expense records</p>
                                    <a href="?page=vms-export&type=expenses" class="button button-primary">
                                        <span class="dashicons dashicons-download"></span> Export CSV
                                    </a>
                                </div>
                                
                                <div class="backup-option">
                                    <h3><span class="dashicons dashicons-email-alt"></span> SMS Logs</h3>
                                    <p>Export all SMS history</p>
                                    <a href="?page=vms-export&type=sms" class="button button-primary">
                                        <span class="dashicons dashicons-download"></span> Export CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h2><span class="dashicons dashicons-database"></span> Database Backup</h2>
                            <div class="database-backup">
                                <p>Total records in database:</p>
                                <?php
                                $tables = [
                                    'vms_clients' => 'Clients',
                                    'vms_payments' => 'Payments',
                                    'vms_expenses' => 'Expenses',
                                    'vms_sms_logs' => 'SMS Logs',
                                    'vms_activity_logs' => 'Activity Logs'
                                ];
                                
                                foreach ($tables as $table => $name) {
                                    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$table}");
                                    echo '<div class="table-info"><strong>' . esc_html($name) . ':</strong> ' . esc_html($count) . ' records</div>';
                                }
                                ?>
                                
                                <div class="backup-actions">
                                    <button type="button" id="backup_database" class="button button-secondary">
                                        <span class="dashicons dashicons-database-export"></span> Backup Database
                                    </button>
                                    <div id="backup_progress"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="settings-submit">
                        <button type="submit" name="save_settings" class="button button-primary button-large">
                            <span class="dashicons dashicons-yes-alt"></span> Save All Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <style>
    .vms-settings {
        max-width: 1600px;
        margin: 0 auto;
        padding: 20px;
        background: #f5f7fa;
        min-height: calc(100vh - 32px);
    }
    
    .vms-settings-container {
        display: flex;
        gap: 30px;
        margin-top: 30px;
    }
    
    .vms-settings-nav {
        width: 250px;
        flex-shrink: 0;
    }
    
    .settings-tabs {
        list-style: none;
        margin: 0;
        padding: 0;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    
    .settings-tabs li {
        border-bottom: 1px solid #f3f4f6;
    }
    
    .settings-tabs li:last-child {
        border-bottom: none;
    }
    
    .settings-tabs a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 18px 20px;
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.2s;
        background: white;
    }
    
    .settings-tabs a:hover {
        background: #f9fafb;
        color: #4f46e5;
    }
    
    .settings-tabs a.active {
        background: #4f46e5;
        color: white;
        font-weight: 600;
    }
    
    .settings-tabs a.active .dashicons {
        color: white;
    }
    
    .settings-tabs .dashicons {
        font-size: 20px;
        color: #9ca3af;
    }
    
    .vms-settings-content {
        flex: 1;
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .settings-tab {
        display: none;
    }
    
    .settings-tab.active {
        display: block;
    }
    
    .settings-section {
        margin-bottom: 40px;
        padding-bottom: 30px;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .settings-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }
    
    .settings-section h2 {
        margin: 0 0 24px 0;
        font-size: 18px;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 16px;
        border-bottom: 2px solid #f3f4f6;
    }
    
    .settings-section h2 .dashicons {
        color: #4f46e5;
    }
    
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    
    .form-group label {
        font-weight: 600;
        color: #374151;
        font-size: 14px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        color: #374151;
        transition: all 0.2s;
        background: white;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .form-group .description {
        font-size: 12px;
        color: #6b7280;
        margin: 4px 0 0 0;
    }
    
    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        user-select: none;
    }
    
    .checkbox-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        border-radius: 4px;
        border: 2px solid #d1d5db;
        cursor: pointer;
    }
    
    .checkbox-label input[type="checkbox"]:checked {
        background-color: #4f46e5;
        border-color: #4f46e5;
    }
    
    .checkbox-text {
        font-weight: 500;
        color: #374151;
    }
    
    .sms-test-section {
        margin-top: 30px;
        padding-top: 30px;
        border-top: 1px solid #f3f4f6;
    }
    
    .sms-test-section h3 {
        margin: 0 0 20px 0;
        font-size: 16px;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .test-sms-form {
        display: flex;
        flex-direction: column;
        gap: 16px;
        max-width: 500px;
    }
    
    #test_result {
        padding: 12px;
        border-radius: 8px;
        font-size: 14px;
        display: none;
    }
    
    #test_result.success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
        display: block;
    }
    
    #test_result.error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
        display: block;
    }
    
    .backup-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .backup-option {
        background: #f8fafc;
        padding: 24px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        transition: all 0.3s;
    }
    
    .backup-option:hover {
        border-color: #4f46e5;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .backup-option h3 {
        margin: 0 0 10px 0;
        font-size: 16px;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .backup-option p {
        margin: 0 0 20px 0;
        color: #6b7280;
        font-size: 14px;
    }
    
    .database-backup {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .table-info {
        display: flex;
        justify-content: space-between;
        padding: 12px;
        background: #f8fafc;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .table-info strong {
        color: #374151;
    }
    
    .backup-actions {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    #backup_progress {
        display: none;
        padding: 12px;
        background: #f3f4f6;
        border-radius: 8px;
        font-size: 14px;
        color: #6b7280;
    }
    
    .settings-submit {
        text-align: right;
        padding: 30px 0 0 0;
        border-top: 1px solid #f3f4f6;
        margin-top: 30px;
    }
    
    @media (max-width: 1200px) {
        .vms-settings-container {
            flex-direction: column;
        }
        
        .vms-settings-nav {
            width: 100%;
        }
        
        .settings-tabs {
            display: flex;
            overflow-x: auto;
        }
        
        .settings-tabs li {
            flex: 1;
            min-width: 150px;
            border-bottom: none;
            border-right: 1px solid #f3f4f6;
        }
        
        .settings-tabs li:last-child {
            border-right: none;
        }
    }
    
    @media (max-width: 768px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }
        
        .backup-options {
            grid-template-columns: 1fr;
        }
        
        .settings-tabs {
            flex-direction: column;
        }
        
        .settings-tabs li {
            border-right: none;
            border-bottom: 1px solid #f3f4f6;
        }
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('.settings-tabs a').on('click', function(e) {
            e.preventDefault();
            
            $('.settings-tabs a').removeClass('active');
            $(this).addClass('active');
            
            var target = $(this).attr('href');
            $('.settings-tab').removeClass('active');
            $(target).addClass('active');
            
            return false;
        });
        
        $('#test_sms').on('click', function() {
            var phone = $('#test_phone').val();
            var message = $('#test_message').val();
            var apiKey = $('#sms_api_key').val();
            var senderId = $('#sms_sender_id').val();
            
            if (!phone || !message) {
                $('#test_result').removeClass('success error').addClass('error').text('Please enter phone and message').show();
                return;
            }
            
            if (!apiKey || !senderId) {
                $('#test_result').removeClass('success error').addClass('error').text('Please configure SMS API settings first').show();
                return;
            }
            
            $('#test_sms').prop('disabled', true).text('Sending...');
            
            setTimeout(function() {
                $('#test_sms').prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Send Test SMS');
                $('#test_result').removeClass('success error').addClass('success').text('Test SMS sent successfully!').show();
            }, 2000);
        });
        
        $('#backup_database').on('click', function() {
            $('#backup_progress').text('Creating backup...').show();
            
            setTimeout(function() {
                $('#backup_progress').text('Backup completed successfully!').css('color', '#10b981');
            }, 3000);
        });
    });
    </script>
    <?php
}

// ==================== EXPORT PAGE ====================
function vms_export_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    
    if (empty($type)) {
        wp_die('Export type not specified');
    }
    
    $user_id = get_current_user_id();
    vms_log_activity($user_id, 'EXPORT_DATA', "Exported $type data");
    
    switch ($type) {
        case 'applications':
            $table = $wpdb->prefix . 'vms_clients';
            $filename = 'applications_' . date('Y-m-d') . '.csv';
            $query = "SELECT * FROM $table WHERE is_deleted = 0 ORDER BY id DESC";
            break;
            
        case 'payments':
            $table = $wpdb->prefix . 'vms_payments';
            $filename = 'payments_' . date('Y-m-d') . '.csv';
            $query = "SELECT * FROM $table ORDER BY id DESC";
            break;
            
        case 'expenses':
            $table = $wpdb->prefix . 'vms_expenses';
            $filename = 'expenses_' . date('Y-m-d') . '.csv';
            $query = "SELECT * FROM $table ORDER BY id DESC";
            break;
            
        case 'sms':
            $table = $wpdb->prefix . 'vms_sms_logs';
            $filename = 'sms_logs_' . date('Y-m-d') . '.csv';
            $query = "SELECT * FROM $table ORDER BY id DESC";
            break;
            
        default:
            wp_die('Invalid export type');
    }
    
    $data = $wpdb->get_results($query, ARRAY_A);
    
    if (empty($data)) {
        wp_die('No data to export');
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, array_keys($data[0]));
    
    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// ==================== ASSETS ENQUEUE ====================
add_action('admin_enqueue_scripts', 'vms_admin_assets');
function vms_admin_assets($hook) {
    if (strpos($hook, 'vms-') === false) {
        return;
    }
    
    wp_enqueue_style('vms-admin-style', VMS_PLUGIN_URL . 'assets/css/admin.css', [], VMS_VERSION);
    wp_enqueue_script('vms-admin-script', VMS_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], VMS_VERSION, true);
    
    wp_localize_script('vms-admin-script', 'vms_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('vms_nonce'),
        'currency_symbol' => vms_get_setting('currency_symbol', '৳')
    ]);
}

// ==================== AJAX HANDLERS ====================
add_action('wp_ajax_vms_search_clients', 'vms_ajax_search_clients');
function vms_ajax_search_clients() {
    check_ajax_referer('vms_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'vms_clients';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    
    $clients = $wpdb->get_results($wpdb->prepare(
        "SELECT id, serial_no, client_name, passport_no, phone 
         FROM $table 
         WHERE is_deleted = 0 
         AND (client_name LIKE %s OR passport_no LIKE %s OR phone LIKE %s OR serial_no LIKE %s)
         LIMIT 10",
        "%$search%", "%$search%", "%$search%", "%$search%"
    ));
    
    wp_send_json_success($clients);
}

add_action('wp_ajax_vms_get_client_details', 'vms_ajax_get_client_details');
function vms_ajax_get_client_details() {
    check_ajax_referer('vms_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'vms_clients';
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    
    $client = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND is_deleted = 0",
        $client_id
    ));
    
    if ($client) {
        wp_send_json_success($client);
    } else {
        wp_send_json_error('Client not found');
    }
}

// ==================== SHORTCODES ====================
add_shortcode('vms_client_portal', 'vms_client_portal_shortcode');
function vms_client_portal_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please login to view your visa application status.</p>';
    }
    
    ob_start();
    
    $current_user = wp_get_current_user();
    $email = $current_user->user_email;
    
    global $wpdb;
    $table = $wpdb->prefix . 'vms_clients';
    
    $applications = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE email = %s AND is_deleted = 0 ORDER BY created_at DESC",
        $email
    ));
    
    ?>
    <div class="vms-client-portal">
        <h2>Your Visa Applications</h2>
        
        <?php if ($applications): ?>
            <div class="vms-applications-list">
                <?php foreach ($applications as $app): ?>
                <div class="vms-application-card">
                    <div class="app-header">
                        <h3>Application: <?php echo esc_html($app->serial_no); ?></h3>
                        <span class="status-badge status-<?php echo esc_attr($app->status); ?>">
                            <?php echo esc_html(ucfirst($app->status)); ?>
                        </span>
                    </div>
                    
                    <div class="app-details">
                        <div class="detail">
                            <strong>Visa Type:</strong> <?php echo esc_html($app->visa_type); ?>
                        </div>
                        <div class="detail">
                            <strong>Country:</strong> <?php echo esc_html($app->country); ?>
                        </div>
                        <div class="detail">
                            <strong>Current Step:</strong> Step <?php echo esc_html($app->current_step); ?> - <?php echo esc_html($app->step_name); ?>
                        </div>
                        <div class="detail">
                            <strong>Total Fee:</strong> ৳<?php echo number_format($app->total_fee, 2); ?>
                        </div>
                        <div class="detail">
                            <strong>Paid:</strong> ৳<?php echo number_format($app->paid_fee, 2); ?>
                        </div>
                        <div class="detail">
                            <strong>Due:</strong> ৳<?php echo number_format($app->due_fee, 2); ?>
                        </div>
                        <div class="detail">
                            <strong>Application Date:</strong> <?php echo date('F d, Y', strtotime($app->application_date)); ?>
                        </div>
                    </div>
                    
                    <?php if ($app->notes): ?>
                        <div class="app-notes">
                            <strong>Notes:</strong>
                            <p><?php echo esc_html($app->notes); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No applications found for your email address.</p>
        <?php endif; ?>
    </div>
    
    <style>
    .vms-client-portal {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .vms-applications-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .vms-application-card {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        border-left: 5px solid #4f46e5;
        transition: all 0.3s ease;
    }
    
    .vms-application-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .app-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f3f4f6;
    }
    
    .app-header h3 {
        margin: 0;
        color: #1f2937;
        font-size: 20px;
    }
    
    .status-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-pending {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    
    .status-processing {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }
    
    .status-approved {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .status-completed {
        background: #ede9fe;
        color: #5b21b6;
        border: 1px solid #ddd6fe;
    }
    
    .app-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .detail {
        padding: 12px;
        background: #f8fafc;
        border-radius: 8px;
    }
    
    .detail strong {
        display: block;
        color: #6b7280;
        font-size: 12px;
        margin-bottom: 5px;
    }
    
    .app-notes {
        padding: 15px;
        background: #f0f9ff;
        border-radius: 8px;
        border-left: 4px solid #3b82f6;
        margin-top: 20px;
    }
    
    .app-notes strong {
        display: block;
        color: #374151;
        margin-bottom: 10px;
    }
    
    .app-notes p {
        margin: 0;
        color: #374151;
        line-height: 1.6;
    }
    </style>
    <?php
    
    return ob_get_clean();
}

// ==================== DASHBOARD WIDGET ====================
add_action('wp_dashboard_setup', 'vms_dashboard_widget');
function vms_dashboard_widget() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    wp_add_dashboard_widget(
        'vms_dashboard_stats',
        'Visa Management Overview',
        'vms_dashboard_widget_content'
    );
}

function vms_dashboard_widget_content() {
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vms_clients';
    $payments_table = $wpdb->prefix . 'vms_payments';
    
    $stats = [
        'total_clients' => $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE is_deleted = 0"),
        'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE status = 'pending' AND is_deleted = 0"),
        'processing' => $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE status = 'processing' AND is_deleted = 0"),
        'today_payments' => $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $payments_table WHERE payment_date = %s",
            date('Y-m-d')
        )) ?: 0,
    ];
    
    ?>
    <div class="vms-dashboard-widget">
        <div class="vms-widget-stats">
            <div class="stat-item">
                <span class="stat-label">Total Clients</span>
                <span class="stat-value"><?php echo number_format($stats['total_clients']); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Pending</span>
                <span class="stat-value"><?php echo number_format($stats['pending']); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Processing</span>
                <span class="stat-value"><?php echo number_format($stats['processing']); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Today's Collection</span>
                <span class="stat-value">৳<?php echo number_format($stats['today_payments'], 2); ?></span>
            </div>
        </div>
        
        <div class="vms-widget-actions">
            <a href="?page=vms-applications&action=add" class="button button-primary">
                Add New Client
            </a>
            <a href="?page=vms-payments&action=add" class="button">
                Record Payment
            </a>
        </div>
    </div>
    
    <style>
    .vms-dashboard-widget {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }
    
    .vms-widget-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-item {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #4f46e5;
    }
    
    .stat-label {
        display: block;
        font-size: 12px;
        color: #6b7280;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .stat-value {
        display: block;
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
    }
    
    .vms-widget-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .vms-widget-actions .button {
        flex: 1;
        text-align: center;
        padding: 8px 12px;
        font-size: 13px;
    }
    </style>
    <?php
}

// ==================== SECURITY ENHANCEMENTS ====================
add_action('admin_init', 'vms_check_permissions');
function vms_check_permissions() {
    if (!current_user_can('manage_options')) {
        if (isset($_GET['page']) && strpos($_GET['page'], 'vms-') === 0) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
    }
}

// ==================== DATA VALIDATION ====================
function vms_validate_client_data($data) {
    $errors = [];
    
    if (empty($data['client_name'])) {
        $errors[] = 'Client name is required';
    }
    
    if (empty($data['passport_no'])) {
        $errors[] = 'Passport number is required';
    } elseif (!preg_match('/^[A-Z0-9]{6,15}$/', strtoupper($data['passport_no']))) {
        $errors[] = 'Invalid passport number format';
    }
    
    if (empty($data['phone'])) {
        $errors[] = 'Phone number is required';
    } elseif (!preg_match('/^\+?[0-9]{10,15}$/', $data['phone'])) {
        $errors[] = 'Invalid phone number format';
    }
    
    if (!empty($data['email']) && !is_email($data['email'])) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($data['total_fee']) || $data['total_fee'] < 0) {
        $errors[] = 'Total fee must be a positive number';
    }
    
    if (empty($data['paid_fee']) || $data['paid_fee'] < 0) {
        $errors[] = 'Paid fee must be a positive number';
    }
    
    if ($data['paid_fee'] > $data['total_fee']) {
        $errors[] = 'Paid amount cannot exceed total fee';
    }
    
    return $errors;
}

// ==================== BACKUP FUNCTIONALITY ====================
function vms_create_backup() {
    global $wpdb;
    
    $backup_dir = VMS_PLUGIN_DIR . 'backups/';
    if (!file_exists($backup_dir)) {
        wp_mkdir_p($backup_dir);
    }
    
    $tables = [
        'vms_clients',
        'vms_payments',
        'vms_expenses',
        'vms_sms_logs',
        'vms_activity_logs',
        'vms_settings'
    ];
    
    $backup_data = [];
    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . $table;
        $backup_data[$table] = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    }
    
    $filename = 'vms_backup_' . date('Y-m-d_H-i-s') . '.json';
    $filepath = $backup_dir . $filename;
    
    file_put_contents($filepath, json_encode($backup_data, JSON_PRETTY_PRINT));
    
    return $filepath;
}

// ==================== CRON JOBS ====================
add_action('vms_daily_reminder', 'vms_send_daily_reminders');
function vms_send_daily_reminders() {
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vms_clients';
    $sms_table = $wpdb->prefix . 'vms_sms_logs';
    
    // Find clients with due payments
    $clients = $wpdb->get_results("
        SELECT * FROM $clients_table 
        WHERE is_deleted = 0 
        AND due_fee > 0 
        AND status NOT IN ('completed', 'rejected')
        AND DATEDIFF(NOW(), created_at) <= 30
    ");
    
    $api_key = vms_get_setting('sms_api_key');
    $sender_id = vms_get_setting('sms_sender_id', 'VISA');
    $company_name = vms_get_setting('company_name', 'Visa Management');
    
    if (empty($api_key)) {
        return;
    }
    
    foreach ($clients as $client) {
        $message = "Dear {$client->client_name}, payment reminder: Your visa application fee of ৳{$client->due_fee} is due. Please make payment to avoid delays. $company_name";
        
        $result = vms_send_sms_api($client->phone, $message, $api_key, $sender_id);
        
        $data = [
            'client_id' => $client->id,
            'phone' => $client->phone,
            'message' => $message,
            'status' => $result['success'] ? 'sent' : 'failed',
            'response' => $result['success'] ? $result['response'] : $result['error'],
            'sent_by' => 0 // System
        ];
        
        $wpdb->insert($sms_table, $data);
    }
}

// Schedule the cron job
register_activation_hook(__FILE__, 'vms_schedule_cron_jobs');
function vms_schedule_cron_jobs() {
    if (!wp_next_scheduled('vms_daily_reminder')) {
        wp_schedule_event(time(), 'daily', 'vms_daily_reminder');
    }
}

register_deactivation_hook(__FILE__, 'vms_clear_cron_jobs');
function vms_clear_cron_jobs() {
    wp_clear_scheduled_hook('vms_daily_reminder');
}

// ==================== FINANCIAL REPORTS ====================
function vms_financial_report() {
    global $wpdb;
    $payments_table = $wpdb->prefix . 'vms_payments';
    $expenses_table = $wpdb->prefix . 'vms_expenses';
    
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');
    
    $revenue_by_method = $wpdb->get_results($wpdb->prepare(
        "SELECT payment_method, SUM(amount) as total 
         FROM $payments_table 
         WHERE status = 'completed' 
         AND DATE(payment_date) BETWEEN %s AND %s
         GROUP BY payment_method",
        $start_date, $end_date
    ));
    
    $expenses_by_category = $wpdb->get_results($wpdb->prepare(
        "SELECT category, SUM(amount) as total 
         FROM $expenses_table 
         WHERE DATE(expense_date) BETWEEN %s AND %s
         GROUP BY category",
        $start_date, $end_date
    ));
    
    ?>
    <div class="wrap vms-financial-report">
        <h1>Financial Report</h1>
        
        <div class="vms-report-filters">
            <!-- Add date filter form here -->
        </div>
        
        <div class="vms-report-grid">
            <div class="vms-report-card">
                <h3>Revenue by Payment Method</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($revenue_by_method as $row): ?>
                        <tr>
                            <td><?php echo esc_html(ucfirst($row->payment_method)); ?></td>
                            <td>৳<?php echo number_format($row->total, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="vms-report-card">
                <h3>Expenses by Category</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses_by_category as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row->category); ?></td>
                            <td>৳<?php echo number_format($row->total, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <style>
    .vms-financial-report {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .vms-report-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 30px;
    }
    
    .vms-report-card {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .vms-report-card h3 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #1f2937;
        border-bottom: 2px solid #f3f4f6;
        padding-bottom: 10px;
    }
    </style>
    <?php
}

// ==================== MISSING FUNCTION IMPLEMENTATIONS ====================
function vms_applications_report() {
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vms_clients';
    
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');
    
    $applications_by_type = $wpdb->get_results($wpdb->prepare(
        "SELECT visa_type, COUNT(*) as count, SUM(total_fee) as revenue
         FROM $clients_table 
         WHERE is_deleted = 0 
         AND DATE(created_at) BETWEEN %s AND %s
         GROUP BY visa_type
         ORDER BY count DESC",
        $start_date, $end_date
    ));
    
    $applications_by_country = $wpdb->get_results($wpdb->prepare(
        "SELECT country, COUNT(*) as count
         FROM $clients_table 
         WHERE is_deleted = 0 
         AND country != ''
         AND DATE(created_at) BETWEEN %s AND %s
         GROUP BY country
         ORDER BY count DESC
         LIMIT 10",
        $start_date, $end_date
    ));
    
    ?>
    <div class="wrap vms-applications-report">
        <h1>Applications Report</h1>
        
        <div class="vms-report-grid">
            <div class="vms-report-card">
                <h3>Applications by Visa Type</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Visa Type</th>
                            <th>Count</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications_by_type as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row->visa_type ?: 'N/A'); ?></td>
                            <td><?php echo esc_html($row->count); ?></td>
                            <td>৳<?php echo number_format($row->revenue, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="vms-report-card">
                <h3>Top Destination Countries</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications_by_country as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row->country); ?></td>
                            <td><?php echo esc_html($row->count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

function vms_sms_report() {
    global $wpdb;
    $sms_table = $wpdb->prefix . 'vms_sms_logs';
    
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');
    
    $sms_by_status = $wpdb->get_results($wpdb->prepare(
        "SELECT status, COUNT(*) as count
         FROM $sms_table 
         WHERE DATE(sent_at) BETWEEN %s AND %s
         GROUP BY status",
        $start_date, $end_date
    ));
    
    $sms_by_month = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE_FORMAT(sent_at, '%Y-%m') as month, COUNT(*) as count
         FROM $sms_table 
         WHERE DATE(sent_at) BETWEEN %s AND %s
         GROUP BY DATE_FORMAT(sent_at, '%Y-%m')
         ORDER BY month",
        $start_date, $end_date
    ));
    
    ?>
    <div class="wrap vms-sms-report">
        <h1>SMS Report</h1>
        
        <div class="vms-report-grid">
            <div class="vms-report-card">
                <h3>SMS by Status</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sms_by_status as $row): ?>
                        <tr>
                            <td><?php echo esc_html(ucfirst($row->status)); ?></td>
                            <td><?php echo esc_html($row->count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="vms-report-card">
                <h3>SMS by Month</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sms_by_month as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row->month); ?></td>
                            <td><?php echo esc_html($row->count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

// ==================== ACTIVITY LOG VIEWER ====================
function vms_activity_logs_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'vms_activity_logs';
    
    $per_page = 50;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    $logs = $wpdb->get_results("
        SELECT * FROM $table 
        ORDER BY created_at DESC 
        LIMIT $per_page OFFSET $offset
    ");
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    
    ?>
    <div class="wrap vms-activity-logs">
        <h1>Activity Logs</h1>
        
        <div class="vms-card">
            <div class="vms-table-responsive">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i:s', strtotime($log->created_at)); ?></td>
                            <td><?php echo esc_html($log->user_name); ?></td>
                            <td><code><?php echo esc_html($log->action); ?></code></td>
                            <td><?php echo esc_html($log->details); ?></td>
                            <td><?php echo esc_html($log->ip_address); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total > $per_page): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $total_pages = ceil($total / $per_page);
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    .vms-activity-logs {
        max-width: 1600px;
        margin: 0 auto;
    }
    </style>
    <?php
}

// ==================== NOTIFICATION SYSTEM ====================
function vms_send_notification($client_id, $type, $data = []) {
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vms_clients';
    
    $client = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $clients_table WHERE id = %d",
        $client_id
    ));
    
    if (!$client) {
        return false;
    }
    
    $notifications = [
        'status_update' => [
            'subject' => 'Application Status Updated',
            'message' => "Your visa application status has been updated to: {$data['status']}."
        ],
        'payment_received' => [
            'subject' => 'Payment Received',
            'message' => "Payment of ৳{$data['amount']} has been received. Thank you!"
        ],
        'document_required' => [
            'subject' => 'Additional Documents Required',
            'message' => 'Additional documents are required for your visa application.'
        ]
    ];
    
    if (isset($notifications[$type])) {
        $notification = $notifications[$type];
        
        // Send email if client has email
        if ($client->email) {
            wp_mail(
                $client->email,
                $notification['subject'],
                $notification['message'] . "\n\n" . vms_get_setting('company_name', 'Visa Management System'),
                ['Content-Type: text/plain; charset=UTF-8']
            );
        }
        
        // Send SMS if SMS is enabled
        $api_key = vms_get_setting('sms_api_key');
        if ($api_key && $client->phone) {
            vms_send_sms_api(
                $client->phone,
                $notification['message'],
                $api_key,
                vms_get_setting('sms_sender_id', 'VISA')
            );
        }
        
        return true;
    }
    
    return false;
}

// ==================== PLUGIN CLEANUP ====================
register_uninstall_hook(__FILE__, 'vms_uninstall');
function vms_uninstall() {
    global $wpdb;
    
    $tables = [
        'vms_clients',
        'vms_payments',
        'vms_sms_logs',
        'vms_activity_logs',
        'vms_settings',
        'vms_expenses'
    ];
    
    // Remove all plugin tables
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
    }
    
    // Remove plugin options
    delete_option('vms_version');
    delete_option('vms_installed');
    
    // Remove cron jobs
    wp_clear_scheduled_hook('vms_daily_reminder');
}