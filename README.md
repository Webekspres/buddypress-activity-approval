# BuddyPress Activity Approval System

Plugin WordPress profesional untuk sistem persetujuan manual aktivitas BuddyPress dengan standar enterprise dan clean architecture.

## 📋 Deskripsi

Plugin ini menyediakan sistem persetujuan manual yang komprehensif untuk aktivitas BuddyPress, memungkinkan administrator untuk mereview dan menyetujui konten sebelum dipublikasikan. Dibangun dengan standar WordPress Coding Standards dan prinsip SOLID.

## ✨ Fitur Utama

### 🔒 Sistem Persetujuan
- **Intercept Otomatis**: Hook `bp_activity_before_save` untuk menangkap semua aktivitas
- **Status Pending**: Semua aktivitas baru diset ke status "pending" secara default
- **Validasi Konten**: Sistem validasi multi-layer dengan aturan yang dapat dikonfigurasi
- **Auto-approval**: Opsi untuk auto-approve berdasarkan role pengguna

### 🎛️ Admin Dashboard
- **Interface Intuitif**: Dashboard khusus untuk review dan approval
- **Filter Canggih**: Filter berdasarkan jenis konten, tanggal, dan status
- **Bulk Actions**: Approve/reject multiple aktivitas sekaligus
- **Real-time Updates**: AJAX-powered untuk pengalaman yang smooth
- **Activity Preview**: Preview konten lengkap sebelum approval

### 📧 Sistem Notifikasi Email
- **Template Profesional**: Email HTML responsive dengan branding
- **Notifikasi Real-time**: Pemberitahuan instan ke admin untuk aktivitas baru
- **User Feedback**: Notifikasi ke user saat aktivitas disetujui/ditolak
- **Direct Links**: Link langsung ke dashboard approval dalam email
- **Email Statistics**: Tracking dan statistik pengiriman email

### 🛡️ Validasi & Keamanan
- **Content Validation**: Validasi panjang konten, kata terlarang, HTML
- **Spam Detection**: Deteksi spam otomatis dengan pattern recognition
- **Permission Checks**: Validasi permission dan capability pengguna
- **Sanitization**: Pembersihan konten dengan WordPress standards

## 🚀 Instalasi

### Persyaratan Sistem
- WordPress 5.0+
- BuddyPress 5.0+
- PHP 7.4+
- MySQL 5.6+

### Langkah Instalasi

1. **Upload Plugin**
   ```bash
   # Via WordPress Admin
   Plugins > Add New > Upload Plugin > Pilih file zip

   # Via FTP
   Upload folder ke /wp-content/plugins/
   ```

2. **Aktivasi Plugin**
   ```bash
   Plugins > Installed Plugins > Activate "BuddyPress Activity Approval"
   ```

3. **Konfigurasi Awal**
   - Buka `Settings > Activity Approval`
   - Konfigurasikan pengaturan sesuai kebutuhan
   - Test sistem notifikasi email

## ⚙️ Konfigurasi

### Pengaturan Umum

#### Auto-approval Settings
```php
// Auto-approve untuk administrator
auto_approve_admins = true

// Auto-approve untuk moderator
auto_approve_moderators = true

// Jenis aktivitas yang dimoderasi
activity_types = ['activity_update', 'activity_comment']
```

#### Content Validation
```php
// Panjang konten minimum (karakter)
min_content_length = 10

// Panjang konten maksimum (karakter)
max_content_length = 5000

// Kata-kata terlarang (satu per baris)
blocked_words = "spam\nscam\nfake"
```

### Pengaturan Email

#### Notifikasi Admin
```php
// Aktifkan notifikasi admin
email_notifications = true

// Email admin (pisahkan dengan koma)
admin_emails = "admin@site.com,moderator@site.com"
```

#### Notifikasi User
```php
// Aktifkan notifikasi user
user_notifications = true
```

### Pengaturan Advanced

#### Spam Detection
```php
// Aktifkan deteksi spam
enable_spam_detection = true

// Hapus data saat uninstall
delete_data_on_uninstall = false
```

## 🎯 Penggunaan

### Admin Dashboard

#### Akses Dashboard
1. Login sebagai Administrator
2. Buka `Activity > Pending Approval`
3. Review aktivitas yang pending

#### Review Aktivitas
1. **View Content**: Klik "View" untuk melihat konten lengkap
2. **Approve**: Klik "Approve" untuk menyetujui
3. **Reject**: Klik "Reject" untuk menolak dengan catatan
4. **Bulk Actions**: Pilih multiple items untuk aksi massal

#### Filter & Search
```php
// Filter berdasarkan:
- Jenis aktivitas (Status Update, Comment, dll)
- Tanggal (Hari ini, Minggu ini, Bulan ini)
- Author (Nama pengguna)
- Status (Pending, Approved, Rejected)
```

### Admin Bar Integration
- **Pending Count**: Jumlah aktivitas pending di admin bar
- **Quick Access**: Link langsung ke dashboard approval
- **Real-time Updates**: Counter update otomatis

## 🔧 Customization

### Hook & Filter

#### Action Hooks
```php
// Sebelum aktivitas disimpan
do_action('bp_activity_approval_before_save', $activity_data);

// Setelah aktivitas disetujui
do_action('bp_activity_approval_approved', $activity_data);

// Setelah aktivitas ditolak
do_action('bp_activity_approval_rejected', $activity_data);

// Sebelum email dikirim
do_action('bp_activity_approval_before_email', $email_data);
```

#### Filter Hooks
```php
// Modifikasi validasi rules
add_filter('bp_activity_approval_validation_rules', function($rules) {
    $rules['custom_rule'] = 'Custom validation';
    return $rules;
});

// Modifikasi template email
add_filter('bp_activity_approval_email_template', function($template, $type) {
    // Custom email template
    return $template;
}, 10, 2);

// Modifikasi auto-approval logic
add_filter('bp_activity_approval_auto_approve', function($auto_approve, $user_id, $activity_type) {
    // Custom auto-approval logic
    return $auto_approve;
}, 10, 3);
```

### Custom Email Templates

#### Lokasi Template
```
/wp-content/plugins/bp-activity-approval/templates/emails/
├── new-activity.php          # Template notifikasi admin
├── activity-approved.php     # Template approval user
└── activity-rejected.php     # Template rejection user
```

#### Custom Template
```php
// Buat folder di theme
/wp-content/themes/your-theme/bp-activity-approval/emails/

// Copy dan modifikasi template
cp plugin/templates/emails/new-activity.php theme/bp-activity-approval/emails/
```

### Database Schema

#### Tabel Logs
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
    KEY action (action)
);
```

## 🔍 Troubleshooting

### Masalah Umum

#### Plugin Tidak Aktif
```php
// Cek dependency BuddyPress
if (!function_exists('bp_is_active')) {
    // BuddyPress tidak aktif
    add_action('admin_notices', 'bp_activity_approval_bp_required_notice');
}
```

#### Email Tidak Terkirim
```php
// Test email function
$email = new BP_Activity_Approval_Email();
$result = $email->send_test_email('test@example.com');

// Cek log email
$stats = $email->get_email_stats();
```

#### Database Error
```php
// Recreate tables
register_activation_hook(__FILE__, 'bp_activity_approval_create_tables');
bp_activity_approval_create_tables();
```

### Debug Mode

#### Aktifkan Debug
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Plugin debug
define('BP_ACTIVITY_APPROVAL_DEBUG', true);
```

#### Log Location
```
/wp-content/debug.log
/wp-content/plugins/bp-activity-approval/logs/
```

## 📊 Performance

### Optimasi Database
```php
// Index untuk performa
ALTER TABLE wp_bp_activity_approval_logs ADD INDEX idx_activity_action (activity_id, action);
ALTER TABLE wp_bp_activity_approval_logs ADD INDEX idx_created_at (created_at);
```

### Caching
```php
// Object caching untuk pending count
wp_cache_set('bp_activity_approval_pending_count', $count, 'bp_activity_approval', 3600);
```

### Batch Processing
```php
// Bulk operations dengan batch
$batch_size = 50;
$activities = array_chunk($activity_ids, $batch_size);
```

## 🔒 Security

### Nonce Verification
```php
// Semua AJAX requests menggunakan nonce
wp_verify_nonce($_POST['nonce'], 'bp_activity_approval_nonce');
```

### Capability Checks
```php
// Cek permission sebelum action
if (!current_user_can('manage_options')) {
    wp_die(__('Insufficient permissions'));
}
```

### Data Sanitization
```php
// Sanitize semua input
$content = wp_kses_post($_POST['content']);
$admin_note = sanitize_textarea_field($_POST['admin_note']);
```

## 🧪 Testing

### Unit Tests
```bash
# Setup testing environment
composer install
./vendor/bin/phpunit

# Run specific test
./vendor/bin/phpunit tests/test-approval-core.php
```

### Manual Testing Checklist
- [ ] Aktivitas baru masuk ke pending
- [ ] Admin dapat approve/reject
- [ ] Email notifikasi terkirim
- [ ] Bulk actions berfungsi
- [ ] Filter dan search bekerja
- [ ] Validasi konten aktif
- [ ] Auto-approval sesuai setting

## 📈 Monitoring

### Activity Logs
```php
// View logs
$logs = BP_Activity_Approval_Core::get_approval_logs();

// Export logs
$csv = BP_Activity_Approval_Core::export_logs_csv();
```

### Email Statistics
```php
$stats = BP_Activity_Approval()->email->get_email_stats();
echo "Total emails sent: " . $stats['total_sent'];
```

### Performance Metrics
```php
// Query performance
$start_time = microtime(true);
// ... operations ...
$execution_time = microtime(true) - $start_time;
```

## 🔄 Maintenance

### Regular Tasks

#### Weekly
- Review approval logs
- Check email delivery rates
- Monitor pending activities count

#### Monthly
- Database optimization
- Log cleanup (older than 90 days)
- Performance review

#### Quarterly
- Plugin updates
- Security audit
- Backup verification

### Backup & Recovery
```bash
# Database backup
mysqldump -u user -p database wp_bp_activity_approval_logs > backup.sql

# Plugin files backup
tar -czf bp-activity-approval-backup.tar.gz wp-content/plugins/bp-activity-approval/
```

## 🆘 Support

### Dokumentasi
- [WordPress Codex](https://codex.wordpress.org/)
- [BuddyPress Developer Docs](https://codex.buddypress.org/developer/)

### Community
- [WordPress Support Forums](https://wordpress.org/support/)
- [BuddyPress Community](https://buddypress.org/support/)

### Professional Support
Untuk dukungan enterprise dan customization, hubungi developer.

## 📝 Changelog

### Version 1.0.0
- Initial release
- Core approval system
- Admin dashboard
- Email notifications
- Content validation
- Spam detection

## 📄 License

Plugin ini dilisensikan di bawah GPL v2 atau yang lebih baru.

## 👨‍💻 Developer

Dikembangkan dengan standar WordPress dan prinsip clean code untuk memastikan maintainability dan scalability jangka panjang.

---

**© 2025 BuddyPress Activity Approval System. All rights reserved.**