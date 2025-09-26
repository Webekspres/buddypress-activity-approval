<?php
/**
 * Plugin Name: BuddyPress Activity Approval System
 * Plugin URI: https://webekspres.id
 * Description: Sistem persetujuan manual untuk semua postingan BuddyPress Activity dengan dashboard admin dan notifikasi email.
 * Version: 1.0.0
 * Author: Webekpsres
 * Author URI: https://webekspres.id
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bp-activity-approval
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package BPActivityApproval
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'BP_ACTIVITY_APPROVAL_VERSION', '1.0.0' );
define( 'BP_ACTIVITY_APPROVAL_PLUGIN_FILE', __FILE__ );
define( 'BP_ACTIVITY_APPROVAL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BP_ACTIVITY_APPROVAL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BP_ACTIVITY_APPROVAL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'BP_ACTIVITY_APPROVAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'BP_ACTIVITY_APPROVAL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class following Singleton pattern
 *
 * @since 1.0.0
 */
final class BP_Activity_Approval {

    /**
     * Single instance of the class
     *
     * @var BP_Activity_Approval
     * @since 1.0.0
     */
    private static $instance = null;

    /**
     * Plugin components
     *
     * @var array
     * @since 1.0.0
     */
    private $components = array();

    /**
     * Get single instance of the class
     *
     * @return BP_Activity_Approval
     * @since 1.0.0
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize the plugin
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize plugin components
     *
     * @since 1.0.0
     */
    public function init() {
        // Hanya inisialisasi jika dependency check berhasil
        if ( ! $this->check_dependencies() ) {
            return;
        }

        $this->load_dependencies();
        $this->init_components();
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        add_action( 'bp_loaded', array( $this, 'init' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
        register_activation_hook( BP_ACTIVITY_APPROVAL_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( BP_ACTIVITY_APPROVAL_PLUGIN_FILE, array( $this, 'deactivate' ) );
    }

    /**
     * Load plugin dependencies
     *
     * @since 1.0.0
     */
    private function load_dependencies() {
        // Core classes
        require_once BP_ACTIVITY_APPROVAL_PLUGIN_DIR . 'includes/class-bp-activity-approval-core.php';
        require_once BP_ACTIVITY_APPROVAL_PLUGIN_DIR . 'includes/class-bp-activity-approval-email.php';
        require_once BP_ACTIVITY_APPROVAL_PLUGIN_DIR . 'includes/class-bp-activity-approval-validator.php';
        
        // Admin classes
        if ( is_admin() ) {
            require_once BP_ACTIVITY_APPROVAL_PLUGIN_DIR . 'admin/class-bp-activity-approval-admin.php';
            require_once BP_ACTIVITY_APPROVAL_PLUGIN_DIR . 'admin/class-bp-activity-approval-list-table.php';
        }
    }

    /**
     * Initialize plugin components
     *
     * @since 1.0.0
     */
    private function init_components() {
        $this->components['core']  = new BP_Activity_Approval_Core();
        $this->components['email'] = new BP_Activity_Approval_Email();
        
        if ( is_admin() ) {
            $this->components['admin'] = new BP_Activity_Approval_Admin();
        }
    }

    /**
     * Check plugin dependencies
     *
     * @since 1.0.0
     */
    public function check_dependencies() {
        // BuddyPress sudah pasti loaded karena hook bp_loaded
        if ( ! function_exists( 'bp_is_active' ) ) {
            add_action( 'admin_notices', array( $this, 'buddypress_missing_notice' ) );
            return false;
        }

        // Cek apakah komponen Activity aktif
        if ( ! bp_is_active( 'activity' ) ) {
            add_action( 'admin_notices', array( $this, 'activity_component_missing_notice' ) );
            return false;
        }

        return true;
    }

    /**
     * Load plugin textdomain
     *
     * @since 1.0.0
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'bp-activity-approval',
            false,
            dirname( BP_ACTIVITY_APPROVAL_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Plugin activation
     *
     * @since 1.0.0
     */
    public function activate() {
        // Create database tables if needed
        $this->create_database_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     *
     * @since 1.0.0
     */
    public function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook( 'bp_activity_approval_cleanup' );
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     *
     * @since 1.0.0
     */
    private function create_database_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bp_activity_approval_log';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            activity_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            admin_id bigint(20) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            admin_note text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY activity_id (activity_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Set default plugin options
     *
     * @since 1.0.0
     */
    private function set_default_options() {
        $default_options = array(
            'auto_approve_admin' => true,
            'email_notifications' => true,
            'admin_email' => get_option( 'admin_email' ),
            'email_template' => 'default',
            'require_approval_for' => array( 'activity_update', 'activity_comment' ),
        );

        add_option( 'bp_activity_approval_settings', $default_options );
    }

    /**
     * BuddyPress missing notice
     *
     * @since 1.0.0
     */
    public function buddypress_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: Plugin name */
                    esc_html__( '%s memerlukan BuddyPress untuk berfungsi. Silakan install dan aktifkan BuddyPress terlebih dahulu.', 'bp-activity-approval' ),
                    '<strong>BuddyPress Activity Approval System</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Activity component missing notice
     *
     * @since 1.0.0
     */
    public function activity_component_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: Plugin name */
                    esc_html__( '%s memerlukan komponen Activity BuddyPress untuk diaktifkan. Silakan aktifkan komponen Activity di pengaturan BuddyPress.', 'bp-activity-approval' ),
                    '<strong>BuddyPress Activity Approval System</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Get plugin component
     *
     * @param string $component Component name
     * @return object|null
     * @since 1.0.0
     */
    public function get_component( $component ) {
        return isset( $this->components[ $component ] ) ? $this->components[ $component ] : null;
    }

    /**
     * Prevent cloning
     *
     * @since 1.0.0
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     *
     * @since 1.0.0
     */
    public function __wakeup() {}
}

/**
 * Initialize the plugin
 *
 * @return BP_Activity_Approval
 * @since 1.0.0
 */
function bp_activity_approval() {
    return BP_Activity_Approval::get_instance();
}

// Initialize the plugin
bp_activity_approval();