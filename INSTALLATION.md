# Panduan Instalasi BuddyPress Activity Approval System

## 🔧 Persiapan Sistem

### Persyaratan Minimum
```
WordPress: 5.0+
BuddyPress: 5.0+
PHP: 7.4+
MySQL: 5.6+
Memory Limit: 128MB+
Max Execution Time: 30s+
```

### Cek Kompatibilitas
```php
// Jalankan di wp-admin/tools.php atau via WP-CLI
function check_bp_activity_approval_requirements() {
    $requirements = [
        'WordPress' => version_compare(get_bloginfo('version'), '5.0', '>='),
        'BuddyPress' => function_exists('bp_is_active') && version_compare(BP_VERSION, '5.0', '>='),
        'PHP' => version_compare(PHP_VERSION, '7.4', '>='),
        'MySQL' => version_compare($GLOBALS['wpdb']->db_version(), '5.6', '>=')
    ];
    
    foreach ($requirements as $name => $met) {
        echo $name . ': ' . ($met ? '✅ OK' : '❌ FAILED') . "\n";
    }
}
```

## 📦 Metode Instalasi

### Metode 1: Upload Manual (Recommended)

#### Step 1: Download & Extract
```bash
# Download plugin files
# Extract ke folder sementara
unzip bp-activity-approval.zip
```

#### Step 2: Upload via FTP/cPanel
```bash
# Upload ke direktori plugins
/wp-content/plugins/bp-activity-approval/

# Set permissions
chmod 755 /wp-content/plugins/bp-activity-approval/
chmod 644 /wp-content/plugins/bp-activity-approval/*.php
```

#### Step 3: Aktivasi
1. Login ke WordPress Admin
2. Buka `Plugins > Installed Plugins`
3. Cari "BuddyPress Activity Approval"
4. Klik "Activate"

### Metode 2: WP-CLI (Advanced)

```bash
# Navigate ke WordPress root
cd /path/to/wordpress/

# Install plugin
wp plugin install bp-activity-approval.zip

# Activate plugin
wp plugin activate bp-activity-approval

# Verify installation
wp plugin list | grep bp-activity-approval
```

### Metode 3: WordPress Admin Upload

1. Login ke WordPress Admin
2. Buka `Plugins > Add New`
3. Klik "Upload Plugin"
4. Pilih file `bp-activity-approval.zip`
5. Klik "Install Now"
6. Klik "Activate Plugin"

## ⚙️ Konfigurasi Awal

### Step 1: Verifikasi Instalasi

#### Cek Plugin Status
```php
// Via WordPress Admin
Plugins > Installed Plugins
Status: "Active" dengan warna hijau

// Via Database
SELECT * FROM wp_options WHERE option_name = 'active_plugins';
// Harus ada 'bp-activity-approval/bp-activity-approval.php'
```

#### Cek Database Tables
```sql
-- Tabel logs harus terbuat otomatis
SHOW TABLES LIKE 'wp_bp_activity_approval_logs';

-- Cek struktur tabel
DESCRIBE wp_bp_activity_approval_logs;
```

### Step 2: Pengaturan Dasar

#### Akses Settings
1. Buka `Settings > Activity Approval`
2. Atau `Activity > Settings` (jika ada)

#### Konfigurasi Minimal
```php
// General Settings
Auto-approve Admins: ✅ Enabled
Auto-approve Moderators: ✅ Enabled
Activity Types: ✅ Status Updates, ✅ Comments

// Email Settings
Admin Notifications: ✅ Enabled
Admin Email: your-admin@domain.com
User Notifications: ✅ Enabled

// Content Validation
Min Content Length: 10
Max Content Length: 5000
Blocked Words: (kosongkan dulu)
```

### Step 3: Test Sistem

#### Test Basic Functionality
```php
// 1. Buat test user (non-admin)
// 2. Login sebagai test user
// 3. Post activity update
// 4. Cek apakah masuk ke pending
// 5. Login sebagai admin
// 6. Cek dashboard approval
```

#### Test Email Notifications
```php
// Di Settings > Activity Approval
// Scroll ke Email Settings
// Klik "Send Test Email"
// Cek inbox admin email
```

## 🔧 Konfigurasi Lanjutan

### Database Optimization

#### Index Performance
```sql
-- Tambah index untuk performa optimal
ALTER TABLE wp_bp_activity_approval_logs 
ADD INDEX idx_activity_action (activity_id, action);

ALTER TABLE wp_bp_activity_approval_logs 
ADD INDEX idx_created_at (created_at);

ALTER TABLE wp_bp_activity_approval_logs 
ADD INDEX idx_admin_id (admin_id);
```

#### Cleanup Old Logs
```sql
-- Hapus log lebih dari 90 hari (opsional)
DELETE FROM wp_bp_activity_approval_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### Email Configuration

#### SMTP Setup (Recommended)
```php
// wp-config.php - Tambahkan konfigurasi SMTP
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls');

// Atau gunakan plugin seperti WP Mail SMTP
```

#### Email Template Customization
```php
// Copy template ke theme
mkdir -p /wp-content/themes/your-theme/bp-activity-approval/emails/

// Copy files
cp /wp-content/plugins/bp-activity-approval/templates/emails/* \
   /wp-content/themes/your-theme/bp-activity-approval/emails/
```

### Security Hardening

#### File Permissions
```bash
# Plugin directory
chmod 755 /wp-content/plugins/bp-activity-approval/
chmod 644 /wp-content/plugins/bp-activity-approval/*.php

# Logs directory (jika ada)
chmod 755 /wp-content/plugins/bp-activity-approval/logs/
chmod 644 /wp-content/plugins/bp-activity-approval/logs/*.log
```

#### Security Headers
```php
// .htaccess di plugin directory
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>

<Files "bp-activity-approval.php">
    Order Allow,Deny
    Allow from all
</Files>
```

## 🚀 Production Deployment

### Pre-deployment Checklist

#### Environment Check
- [ ] WordPress & BuddyPress versions compatible
- [ ] PHP version 7.4+
- [ ] MySQL version 5.6+
- [ ] Memory limit adequate (128MB+)
- [ ] Email delivery configured
- [ ] Backup system ready

#### Testing Checklist
- [ ] Plugin activation successful
- [ ] Database tables created
- [ ] Admin dashboard accessible
- [ ] Email notifications working
- [ ] Activity interception working
- [ ] Approval/rejection working
- [ ] Bulk actions working
- [ ] User notifications working

### Deployment Steps

#### Step 1: Backup
```bash
# Database backup
mysqldump -u user -p database > backup_pre_approval.sql

# Files backup
tar -czf wordpress_backup.tar.gz /path/to/wordpress/
```

#### Step 2: Deploy
```bash
# Upload plugin files
rsync -avz bp-activity-approval/ user@server:/wp-content/plugins/bp-activity-approval/

# Set permissions
ssh user@server "chmod -R 755 /wp-content/plugins/bp-activity-approval/"
```

#### Step 3: Activate & Configure
```bash
# Via WP-CLI
wp plugin activate bp-activity-approval

# Or via WordPress Admin
# Plugins > Activate
```

#### Step 4: Verify
```bash
# Check plugin status
wp plugin list | grep bp-activity-approval

# Check database
wp db query "SHOW TABLES LIKE 'wp_bp_activity_approval_logs';"

# Test functionality
wp eval "echo BP_Activity_Approval()->core->get_pending_count();"
```

## 🔍 Troubleshooting Instalasi

### Masalah Umum

#### Plugin Tidak Muncul
```php
// Cek file permissions
ls -la /wp-content/plugins/bp-activity-approval/

// Cek syntax errors
php -l /wp-content/plugins/bp-activity-approval/bp-activity-approval.php
```

#### Database Table Tidak Terbuat
```php
// Manual create table
function bp_activity_approval_manual_create_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bp_activity_approval_logs';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        activity_id bigint(20) NOT NULL,
        action varchar(50) NOT NULL,
        admin_id bigint(20) DEFAULT NULL,
        admin_note text,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY activity_id (activity_id),
        KEY admin_id (admin_id),
        KEY action (action)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Jalankan function ini
bp_activity_approval_manual_create_table();
```

#### Email Tidak Terkirim
```php
// Test email function
function test_wp_mail() {
    $to = 'test@example.com';
    $subject = 'Test Email';
    $message = 'This is a test email.';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    $sent = wp_mail($to, $subject, $message, $headers);
    
    if($sent) {
        echo 'Email sent successfully!';
    } else {
        echo 'Email failed to send.';
        // Check error log
        error_log('WP Mail failed');
    }
}
```

#### Memory Limit Issues
```php
// wp-config.php
ini_set('memory_limit', '256M');
define('WP_MEMORY_LIMIT', '256M');

// .htaccess
php_value memory_limit 256M
```

### Debug Mode

#### Enable Debug
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Plugin specific debug
define('BP_ACTIVITY_APPROVAL_DEBUG', true);
```

#### Check Logs
```bash
# WordPress debug log
tail -f /wp-content/debug.log

# Plugin specific logs (jika ada)
tail -f /wp-content/plugins/bp-activity-approval/logs/debug.log
```

## 📞 Support Instalasi

### Self-Help Resources
1. Cek WordPress debug log
2. Review plugin requirements
3. Test dengan default theme
4. Disable other plugins temporarily

### Professional Support
Jika mengalami kesulitan instalasi yang kompleks, hubungi developer untuk bantuan profesional.

---

**Instalasi berhasil? Lanjut ke [README.md](README.md) untuk panduan penggunaan lengkap.**