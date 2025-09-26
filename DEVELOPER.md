# Developer Documentation - BuddyPress Activity Approval System

## 🏗️ Architecture Overview

### Design Principles
- **SOLID Principles**: Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion
- **WordPress Standards**: Mengikuti WordPress Coding Standards dan best practices
- **Clean Architecture**: Separation of concerns dengan layer yang jelas
- **Singleton Pattern**: Untuk main plugin class dan core components
- **Observer Pattern**: Untuk hook system dan event handling

### Directory Structure
```
bp-activity-approval/
├── bp-activity-approval.php          # Main plugin file
├── includes/                         # Core classes
│   ├── class-bp-activity-approval-core.php
│   ├── class-bp-activity-approval-validator.php
│   ├── class-bp-activity-approval-admin.php
│   ├── class-bp-activity-approval-list-table.php
│   └── class-bp-activity-approval-email.php
├── admin/                           # Admin interface
│   ├── admin-settings.php
│   ├── js/
│   │   └── admin.js
│   └── css/
│       └── admin.css
├── templates/                       # Email templates
│   └── emails/
│       ├── new-activity.php
│       ├── activity-approved.php
│       └── activity-rejected.php
├── logs/                           # Debug logs (auto-created)
├── README.md                       # User documentation
├── INSTALLATION.md                 # Installation guide
└── DEVELOPER.md                    # This file
```

### Class Hierarchy
```
BP_Activity_Approval (Main)
├── BP_Activity_Approval_Core (Business Logic)
├── BP_Activity_Approval_Validator (Content Validation)
├── BP_Activity_Approval_Admin (Admin Interface)
├── BP_Activity_Approval_List_Table (Data Display)
└── BP_Activity_Approval_Email (Notifications)
```

## 🔧 Core Components

### Main Plugin Class
```php
class BP_Activity_Approval {
    private static $instance = null;
    public $core;
    public $validator;
    public $admin;
    public $email;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->define_constants();
        $this->load_dependencies();
        $this->init_hooks();
    }
}
```

### Core Business Logic
```php
class BP_Activity_Approval_Core {
    // Intercept activities before save
    public function intercept_activity_save($activity) {
        // Validation logic
        // Status setting
        // Logging
        return $activity;
    }
    
    // Handle approval/rejection
    public function handle_approval($activity_id, $action, $admin_note = '') {
        // Update activity status
        // Send notifications
        // Log action
    }
}
```

### Validation System
```php
class BP_Activity_Approval_Validator {
    private $rules = [];
    
    public function validate($activity_data) {
        foreach ($this->rules as $rule => $config) {
            if (!$this->apply_rule($rule, $activity_data, $config)) {
                return false;
            }
        }
        return true;
    }
}
```

## 🎣 Hook System

### Action Hooks

#### Plugin Lifecycle
```php
// Plugin activation
do_action('bp_activity_approval_activated');

// Plugin deactivation
do_action('bp_activity_approval_deactivated');

// Plugin loaded
do_action('bp_activity_approval_loaded');
```

#### Activity Processing
```php
// Before activity validation
do_action('bp_activity_approval_before_validation', $activity_data);

// After activity validation
do_action('bp_activity_approval_after_validation', $activity_data, $is_valid);

// Before activity save
do_action('bp_activity_approval_before_save', $activity_data);

// After activity approved
do_action('bp_activity_approval_approved', $activity_data, $admin_id, $admin_note);

// After activity rejected
do_action('bp_activity_approval_rejected', $activity_data, $admin_id, $admin_note);
```

#### Email System
```php
// Before email sent
do_action('bp_activity_approval_before_email', $email_data, $email_type);

// After email sent
do_action('bp_activity_approval_after_email', $email_data, $email_type, $sent_status);

// Email failed
do_action('bp_activity_approval_email_failed', $email_data, $error);
```

### Filter Hooks

#### Content Processing
```php
// Modify validation rules
add_filter('bp_activity_approval_validation_rules', function($rules) {
    $rules['custom_rule'] = [
        'callback' => 'custom_validation_function',
        'message' => 'Custom validation failed'
    ];
    return $rules;
});

// Modify auto-approval logic
add_filter('bp_activity_approval_auto_approve', function($auto_approve, $user_id, $activity_type) {
    // Custom logic for auto-approval
    if (user_can($user_id, 'custom_capability')) {
        return true;
    }
    return $auto_approve;
}, 10, 3);

// Modify activity status
add_filter('bp_activity_approval_activity_status', function($status, $activity_data) {
    // Custom status logic
    return $status;
}, 10, 2);
```

#### Email Templates
```php
// Modify email subject
add_filter('bp_activity_approval_email_subject', function($subject, $email_type, $activity_data) {
    if ($email_type === 'new_activity') {
        $subject = '[URGENT] ' . $subject;
    }
    return $subject;
}, 10, 3);

// Modify email content
add_filter('bp_activity_approval_email_content', function($content, $email_type, $activity_data) {
    // Add custom content
    return $content;
}, 10, 3);

// Modify email headers
add_filter('bp_activity_approval_email_headers', function($headers, $email_type) {
    $headers[] = 'X-Priority: 1';
    return $headers;
}, 10, 2);
```

#### Admin Interface
```php
// Modify admin columns
add_filter('bp_activity_approval_admin_columns', function($columns) {
    $columns['custom_field'] = 'Custom Field';
    return $columns;
});

// Modify bulk actions
add_filter('bp_activity_approval_bulk_actions', function($actions) {
    $actions['custom_action'] = 'Custom Action';
    return $actions;
});
```

## 🗄️ Database Schema

### Main Table: wp_bp_activity_approval_logs
```sql
CREATE TABLE wp_bp_activity_approval_logs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    activity_id bigint(20) NOT NULL,
    action varchar(50) NOT NULL,
    admin_id bigint(20) DEFAULT NULL,
    admin_note text,
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY activity_id (activity_id),
    KEY admin_id (admin_id),
    KEY action (action),
    KEY created_at (created_at)
);
```

### Database Operations
```php
class BP_Activity_Approval_DB {
    public static function log_action($activity_id, $action, $admin_id = null, $admin_note = '') {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'bp_activity_approval_logs',
            [
                'activity_id' => $activity_id,
                'action' => $action,
                'admin_id' => $admin_id,
                'admin_note' => $admin_note,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%s', '%s']
        );
    }
    
    public static function get_logs($activity_id = null, $limit = 50) {
        global $wpdb;
        
        $where = $activity_id ? $wpdb->prepare("WHERE activity_id = %d", $activity_id) : "";
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bp_activity_approval_logs 
             {$where} 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
    }
}
```

## 🔌 API Reference

### Core Methods

#### BP_Activity_Approval_Core
```php
// Get pending activities count
public function get_pending_count($user_id = null)

// Get pending activities
public function get_pending_activities($args = [])

// Approve activity
public function approve_activity($activity_id, $admin_note = '')

// Reject activity
public function reject_activity($activity_id, $admin_note = '')

// Bulk approve
public function bulk_approve($activity_ids, $admin_note = '')

// Bulk reject
public function bulk_reject($activity_ids, $admin_note = '')

// Get approval logs
public function get_approval_logs($activity_id = null)
```

#### BP_Activity_Approval_Validator
```php
// Validate activity
public function validate($activity_data)

// Add validation rule
public function add_rule($name, $callback, $message)

// Remove validation rule
public function remove_rule($name)

// Check spam
public function is_spam($content)

// Sanitize content
public function sanitize_content($content)
```

#### BP_Activity_Approval_Email
```php
// Send admin notification
public function send_admin_notification($activity_data)

// Send user notification
public function send_user_notification($activity_data, $status, $admin_note = '')

// Send test email
public function send_test_email($email)

// Get email statistics
public function get_email_stats()
```

### AJAX Endpoints

#### Admin Actions
```javascript
// Approve single activity
wp.ajax.post('bp_activity_approval_approve', {
    activity_id: 123,
    admin_note: 'Approved',
    nonce: bp_activity_approval.nonce
});

// Reject single activity
wp.ajax.post('bp_activity_approval_reject', {
    activity_id: 123,
    admin_note: 'Rejected due to spam',
    nonce: bp_activity_approval.nonce
});

// Bulk actions
wp.ajax.post('bp_activity_approval_bulk_action', {
    action: 'approve',
    activity_ids: [123, 456, 789],
    admin_note: 'Bulk approved',
    nonce: bp_activity_approval.nonce
});
```

#### Response Format
```json
{
    "success": true,
    "data": {
        "message": "Activity approved successfully",
        "activity_id": 123,
        "new_status": "approved",
        "pending_count": 5
    }
}
```

## 🎨 Customization Guide

### Custom Validation Rules
```php
// Add custom validation rule
add_filter('bp_activity_approval_validation_rules', function($rules) {
    $rules['no_external_links'] = [
        'callback' => function($activity_data) {
            $content = $activity_data['content'];
            return !preg_match('/https?:\/\/(?!yourdomain\.com)/', $content);
        },
        'message' => 'External links are not allowed'
    ];
    return $rules;
});
```

### Custom Email Templates
```php
// Create custom template directory
/wp-content/themes/your-theme/bp-activity-approval/emails/

// Custom template: new-activity-custom.php
<?php
// Custom email template
$subject = "New Activity Needs Review";
$message = "
<h2>New Activity Submitted</h2>
<p><strong>Author:</strong> {$activity_data['user_name']}</p>
<p><strong>Content:</strong> {$activity_data['content']}</p>
<p><a href='{$approval_url}'>Review Now</a></p>
";
?>

// Use custom template
add_filter('bp_activity_approval_email_template', function($template, $type) {
    if ($type === 'new_activity') {
        return 'new-activity-custom.php';
    }
    return $template;
}, 10, 2);
```

### Custom Admin Columns
```php
// Add custom column
add_filter('bp_activity_approval_admin_columns', function($columns) {
    $columns['word_count'] = 'Word Count';
    return $columns;
});

// Populate custom column
add_action('bp_activity_approval_admin_column_word_count', function($activity) {
    echo str_word_count(strip_tags($activity->content));
});
```

### Custom Bulk Actions
```php
// Add custom bulk action
add_filter('bp_activity_approval_bulk_actions', function($actions) {
    $actions['mark_spam'] = 'Mark as Spam';
    return $actions;
});

// Handle custom bulk action
add_action('bp_activity_approval_handle_bulk_mark_spam', function($activity_ids, $admin_note) {
    foreach ($activity_ids as $activity_id) {
        // Custom spam handling logic
        bp_activity_update_meta($activity_id, 'is_spam', true);
    }
});
```

## 🧪 Testing Framework

### Unit Tests Setup
```php
// tests/bootstrap.php
<?php
// WordPress test environment
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
    require dirname(__FILE__) . '/../bp-activity-approval.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

require $_tests_dir . '/includes/bootstrap.php';
?>
```

### Test Cases
```php
// tests/test-core.php
class Test_BP_Activity_Approval_Core extends WP_UnitTestCase {
    
    public function setUp() {
        parent::setUp();
        $this->core = BP_Activity_Approval()->core;
    }
    
    public function test_activity_interception() {
        // Create test activity
        $activity_data = [
            'user_id' => 1,
            'content' => 'Test activity content',
            'type' => 'activity_update'
        ];
        
        // Test interception
        $result = $this->core->intercept_activity_save($activity_data);
        
        // Assert activity is set to pending
        $this->assertEquals('pending', $result['status']);
    }
    
    public function test_approval_process() {
        // Create pending activity
        $activity_id = $this->create_test_activity();
        
        // Test approval
        $result = $this->core->approve_activity($activity_id, 'Test approval');
        
        // Assert success
        $this->assertTrue($result);
        
        // Assert status changed
        $activity = bp_activity_get_specific(['activity_ids' => [$activity_id]]);
        $this->assertEquals('approved', $activity['activities'][0]->status);
    }
}
```

### Integration Tests
```php
// tests/test-integration.php
class Test_BP_Activity_Approval_Integration extends WP_UnitTestCase {
    
    public function test_email_notification_flow() {
        // Mock email function
        add_filter('wp_mail', function($args) {
            $this->email_sent = true;
            $this->email_args = $args;
            return true;
        });
        
        // Create activity that triggers email
        $activity_id = $this->create_test_activity();
        
        // Assert email was sent
        $this->assertTrue($this->email_sent);
        $this->assertContains('New Activity', $this->email_args['subject']);
    }
}
```

## 🔧 Debugging & Troubleshooting

### Debug Mode
```php
// Enable debug mode
define('BP_ACTIVITY_APPROVAL_DEBUG', true);

// Debug logging
if (defined('BP_ACTIVITY_APPROVAL_DEBUG') && BP_ACTIVITY_APPROVAL_DEBUG) {
    error_log('BP Activity Approval: ' . $message);
}
```

### Common Issues

#### Hook Not Firing
```php
// Check if BuddyPress is loaded
if (!function_exists('bp_is_active')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>BuddyPress is required</p></div>';
    });
}

// Check if activity component is active
if (!bp_is_active('activity')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>BuddyPress Activity component must be active</p></div>';
    });
}
```

#### Database Issues
```php
// Check table exists
global $wpdb;
$table_name = $wpdb->prefix . 'bp_activity_approval_logs';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

if (!$table_exists) {
    // Recreate table
    BP_Activity_Approval::create_tables();
}
```

#### Email Issues
```php
// Test email configuration
function test_email_config() {
    $test_email = wp_mail(
        get_option('admin_email'),
        'Test Email',
        'This is a test email from BP Activity Approval',
        ['Content-Type: text/html; charset=UTF-8']
    );
    
    if (!$test_email) {
        error_log('Email test failed');
        return false;
    }
    
    return true;
}
```

## 📊 Performance Optimization

### Database Optimization
```php
// Add indexes for better performance
function optimize_approval_logs_table() {
    global $wpdb;
    
    $wpdb->query("ALTER TABLE {$wpdb->prefix}bp_activity_approval_logs 
                  ADD INDEX idx_activity_action (activity_id, action)");
    
    $wpdb->query("ALTER TABLE {$wpdb->prefix}bp_activity_approval_logs 
                  ADD INDEX idx_created_at (created_at)");
}
```

### Caching Strategy
```php
// Cache pending count
function get_cached_pending_count() {
    $cache_key = 'bp_activity_approval_pending_count';
    $count = wp_cache_get($cache_key, 'bp_activity_approval');
    
    if (false === $count) {
        $count = $this->get_pending_count();
        wp_cache_set($cache_key, $count, 'bp_activity_approval', 300); // 5 minutes
    }
    
    return $count;
}

// Invalidate cache on approval/rejection
function invalidate_pending_count_cache() {
    wp_cache_delete('bp_activity_approval_pending_count', 'bp_activity_approval');
}
```

### Batch Processing
```php
// Process large datasets in batches
function bulk_approve_batch($activity_ids, $batch_size = 50) {
    $batches = array_chunk($activity_ids, $batch_size);
    
    foreach ($batches as $batch) {
        foreach ($batch as $activity_id) {
            $this->approve_activity($activity_id);
        }
        
        // Prevent timeout
        if (function_exists('wp_suspend_cache_addition')) {
            wp_suspend_cache_addition(true);
        }
        
        // Small delay to prevent overwhelming the server
        usleep(100000); // 0.1 second
    }
}
```

## 🔒 Security Considerations

### Input Validation
```php
// Sanitize all inputs
function sanitize_admin_input($input) {
    if (is_array($input)) {
        return array_map([$this, 'sanitize_admin_input'], $input);
    }
    
    return sanitize_text_field($input);
}

// Validate nonces
function verify_admin_nonce($action = 'bp_activity_approval_admin') {
    if (!wp_verify_nonce($_POST['nonce'], $action)) {
        wp_die(__('Security check failed', 'bp-activity-approval'));
    }
}
```

### Capability Checks
```php
// Check user capabilities
function check_admin_capability() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions', 'bp-activity-approval'));
    }
}

// Custom capability for moderators
function add_moderator_capability() {
    $role = get_role('editor');
    if ($role) {
        $role->add_cap('bp_moderate_activities');
    }
}
```

### SQL Injection Prevention
```php
// Use prepared statements
function get_activities_by_status($status, $limit = 20) {
    global $wpdb;
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bp_activity 
         WHERE status = %s 
         ORDER BY date_recorded DESC 
         LIMIT %d",
        $status,
        $limit
    ));
}
```

## 📈 Monitoring & Analytics

### Activity Metrics
```php
// Track approval rates
function get_approval_metrics($days = 30) {
    global $wpdb;
    
    $date_from = date('Y-m-d', strtotime("-{$days} days"));
    
    $metrics = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as total_activities,
            SUM(CASE WHEN action = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN action = 'rejected' THEN 1 ELSE 0 END) as rejected,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, NOW())) as avg_response_time
         FROM {$wpdb->prefix}bp_activity_approval_logs 
         WHERE created_at >= %s",
        $date_from
    ));
    
    return $metrics;
}
```

### Performance Monitoring
```php
// Monitor query performance
function monitor_query_performance() {
    add_action('shutdown', function() {
        if (defined('SAVEQUERIES') && SAVEQUERIES) {
            global $wpdb;
            
            $slow_queries = array_filter($wpdb->queries, function($query) {
                return $query[1] > 0.1; // Queries slower than 100ms
            });
            
            if (!empty($slow_queries)) {
                error_log('BP Activity Approval slow queries: ' . print_r($slow_queries, true));
            }
        }
    });
}
```

## 🚀 Deployment & CI/CD

### Build Process
```bash
#!/bin/bash
# build.sh

# Create build directory
mkdir -p build/bp-activity-approval

# Copy plugin files
cp -r includes build/bp-activity-approval/
cp -r admin build/bp-activity-approval/
cp -r templates build/bp-activity-approval/
cp bp-activity-approval.php build/bp-activity-approval/
cp README.md build/bp-activity-approval/

# Create zip file
cd build
zip -r bp-activity-approval.zip bp-activity-approval/

echo "Build complete: build/bp-activity-approval.zip"
```

### Automated Testing
```yaml
# .github/workflows/test.yml
name: Test Plugin

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 7.4
        
    - name: Install WordPress Test Suite
      run: |
        bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest
        
    - name: Run Tests
      run: phpunit
```

## 📚 Additional Resources

### WordPress Development
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Plugin Development Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Hooks Reference](https://developer.wordpress.org/reference/hooks/)

### BuddyPress Development
- [BuddyPress Developer Documentation](https://codex.buddypress.org/developer/)
- [BuddyPress Activity API](https://codex.buddypress.org/developer/activity-streams/)

### Testing & Quality
- [WordPress Unit Testing](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [PHP_CodeSniffer for WordPress](https://github.com/WordPress/WordPress-Coding-Standards)

---

**Dokumentasi ini akan terus diperbarui seiring dengan pengembangan plugin. Untuk kontribusi atau pertanyaan teknis, silakan hubungi tim developer.**