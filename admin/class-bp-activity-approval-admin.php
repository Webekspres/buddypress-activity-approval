<?php
/**
 * Admin dashboard for BuddyPress Activity Approval System
 *
 * @package BPActivityApproval
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin dashboard class
 *
 * @since 1.0.0
 */
class BP_Activity_Approval_Admin {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'admin_init', array( $this, 'handle_bulk_actions' ) );
        add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );
    }

    /**
     * Add admin menu pages
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        $pending_count = bp_activity_approval()->get_component( 'core' )->get_pending_activities_count();
        $menu_title = $pending_count > 0 ? 
            sprintf( __( 'Activity Approval %s', 'bp-activity-approval' ), '<span class="awaiting-mod">' . $pending_count . '</span>' ) :
            __( 'Activity Approval', 'bp-activity-approval' );

        // Main menu page
        $hook = add_menu_page(
            __( 'Activity Approval', 'bp-activity-approval' ),
            $menu_title,
            'manage_options',
            'bp-activity-approval',
            array( $this, 'display_approval_page' ),
            'dashicons-yes-alt',
            30
        );

        // Settings submenu
        add_submenu_page(
            'bp-activity-approval',
            __( 'Settings', 'bp-activity-approval' ),
            __( 'Settings', 'bp-activity-approval' ),
            'manage_options',
            'bp-activity-approval-settings',
            array( $this, 'display_settings_page' )
        );

        // Logs submenu
        add_submenu_page(
            'bp-activity-approval',
            __( 'Approval Logs', 'bp-activity-approval' ),
            __( 'Logs', 'bp-activity-approval' ),
            'manage_options',
            'bp-activity-approval-logs',
            array( $this, 'display_logs_page' )
        );

        // Add screen options
        add_action( "load-$hook", array( $this, 'add_screen_options' ) );
    }

    /**
     * Add screen options
     *
     * @since 1.0.0
     */
    public function add_screen_options() {
        $option = 'per_page';
        $args = array(
            'label'   => __( 'Activities per page', 'bp-activity-approval' ),
            'default' => 20,
            'option'  => 'activities_per_page'
        );
        add_screen_option( $option, $args );
    }

    /**
     * Set screen option
     *
     * @param bool $status Screen option status
     * @param string $option Option name
     * @param mixed $value Option value
     * @return mixed
     * @since 1.0.0
     */
    public function set_screen_option( $status, $option, $value ) {
        return $value;
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'bp-activity-approval' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'bp-activity-approval-admin',
            BP_ACTIVITY_APPROVAL_URL . 'admin/js/admin.js',
            array( 'jquery', 'wp-util' ),
            BP_ACTIVITY_APPROVAL_VERSION,
            true
        );

        wp_enqueue_style(
            'bp-activity-approval-admin',
            BP_ACTIVITY_APPROVAL_URL . 'admin/css/admin.css',
            array(),
            BP_ACTIVITY_APPROVAL_VERSION
        );

        wp_localize_script( 'bp-activity-approval-admin', 'bpActivityApproval', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'bp_activity_approval_nonce' ),
            'strings' => array(
                'confirmApprove' => __( 'Apakah Anda yakin ingin menyetujui activity ini?', 'bp-activity-approval' ),
                'confirmReject'  => __( 'Apakah Anda yakin ingin menolak activity ini?', 'bp-activity-approval' ),
                'confirmBulk'    => __( 'Apakah Anda yakin ingin melakukan aksi bulk ini?', 'bp-activity-approval' ),
                'processing'     => __( 'Memproses...', 'bp-activity-approval' ),
                'error'          => __( 'Terjadi kesalahan. Silakan coba lagi.', 'bp-activity-approval' ),
            )
        ) );
    }

    /**
     * Display main approval page
     *
     * @since 1.0.0
     */
    public function display_approval_page() {
        $list_table = new BP_Activity_Approval_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Activity Approval', 'bp-activity-approval' ); ?></h1>
            
            <?php $this->display_admin_notices(); ?>
            
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
                <?php $list_table->search_box( __( 'Search activities', 'bp-activity-approval' ), 'activity' ); ?>
            </form>

            <form method="post" id="activities-filter">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Display settings page
     *
     * @since 1.0.0
     */
    public function display_settings_page() {
        if ( isset( $_POST['submit'] ) ) {
            $this->save_settings();
        }

        $settings = get_option( 'bp_activity_approval_settings', array() );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Activity Approval Settings', 'bp-activity-approval' ); ?></h1>
            
            <?php $this->display_admin_notices(); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'bp_activity_approval_settings', 'bp_activity_approval_settings_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Require Approval For', 'bp-activity-approval' ); ?></th>
                        <td>
                            <?php $this->render_activity_types_checkboxes( $settings ); ?>
                            <p class="description"><?php esc_html_e( 'Pilih jenis activity yang memerlukan persetujuan admin.', 'bp-activity-approval' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Auto-approve Admin Posts', 'bp-activity-approval' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_approve_admin" value="1" <?php checked( isset( $settings['auto_approve_admin'] ) ? $settings['auto_approve_admin'] : 0, 1 ); ?> />
                                <?php esc_html_e( 'Otomatis setujui posts dari administrator', 'bp-activity-approval' ); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Email Notifications', 'bp-activity-approval' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_notifications" value="1" <?php checked( isset( $settings['email_notifications'] ) ? $settings['email_notifications'] : 1, 1 ); ?> />
                                <?php esc_html_e( 'Kirim notifikasi email ke admin', 'bp-activity-approval' ); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Content Validation', 'bp-activity-approval' ); ?></th>
                        <td>
                            <p><label for="min_content_length"><?php esc_html_e( 'Panjang konten minimum:', 'bp-activity-approval' ); ?></label></p>
                            <input type="number" id="min_content_length" name="min_content_length" value="<?php echo esc_attr( isset( $settings['min_content_length'] ) ? $settings['min_content_length'] : 10 ); ?>" min="0" />
                            
                            <p><label for="max_content_length"><?php esc_html_e( 'Panjang konten maksimum:', 'bp-activity-approval' ); ?></label></p>
                            <input type="number" id="max_content_length" name="max_content_length" value="<?php echo esc_attr( isset( $settings['max_content_length'] ) ? $settings['max_content_length'] : 5000 ); ?>" min="1" />
                            
                            <p><label for="blocked_words"><?php esc_html_e( 'Kata-kata yang diblokir (pisahkan dengan koma):', 'bp-activity-approval' ); ?></label></p>
                            <textarea id="blocked_words" name="blocked_words" rows="3" cols="50"><?php echo esc_textarea( isset( $settings['blocked_words'] ) ? $settings['blocked_words'] : '' ); ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Display logs page
     *
     * @since 1.0.0
     */
    public function display_logs_page() {
        $logs_table = new BP_Activity_Approval_Logs_Table();
        $logs_table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Approval Logs', 'bp-activity-approval' ); ?></h1>
            
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
                <?php $logs_table->search_box( __( 'Search logs', 'bp-activity-approval' ), 'logs' ); ?>
            </form>

            <form method="post" id="logs-filter">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
                <?php $logs_table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render activity types checkboxes
     *
     * @param array $settings Current settings
     * @since 1.0.0
     */
    private function render_activity_types_checkboxes( $settings ) {
        $activity_types = bp_activity_get_types();
        $require_approval_for = isset( $settings['require_approval_for'] ) ? $settings['require_approval_for'] : array();
        
        foreach ( $activity_types as $type => $label ) {
            $checked = in_array( $type, $require_approval_for, true );
            ?>
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="require_approval_for[]" value="<?php echo esc_attr( $type ); ?>" <?php checked( $checked ); ?> />
                <?php echo esc_html( $label ); ?> <code>(<?php echo esc_html( $type ); ?>)</code>
            </label>
            <?php
        }
    }

    /**
     * Save settings
     *
     * @since 1.0.0
     */
    private function save_settings() {
        if ( ! wp_verify_nonce( $_POST['bp_activity_approval_settings_nonce'], 'bp_activity_approval_settings' ) ) {
            wp_die( esc_html__( 'Nonce verification failed.', 'bp-activity-approval' ) );
        }

        $settings = array(
            'require_approval_for' => isset( $_POST['require_approval_for'] ) ? array_map( 'sanitize_text_field', $_POST['require_approval_for'] ) : array(),
            'auto_approve_admin'   => isset( $_POST['auto_approve_admin'] ) ? 1 : 0,
            'email_notifications'  => isset( $_POST['email_notifications'] ) ? 1 : 0,
            'min_content_length'   => intval( $_POST['min_content_length'] ?? 10 ),
            'max_content_length'   => intval( $_POST['max_content_length'] ?? 5000 ),
            'blocked_words'        => sanitize_textarea_field( $_POST['blocked_words'] ?? '' ),
        );

        update_option( 'bp_activity_approval_settings', $settings );
        
        add_settings_error(
            'bp_activity_approval_settings',
            'settings_saved',
            __( 'Settings berhasil disimpan.', 'bp-activity-approval' ),
            'success'
        );
    }

    /**
     * Handle bulk actions
     *
     * @since 1.0.0
     */
    public function handle_bulk_actions() {
        if ( ! isset( $_POST['action'] ) && ! isset( $_POST['action2'] ) ) {
            return;
        }

        $action = $_POST['action'] !== '-1' ? $_POST['action'] : $_POST['action2'];
        
        if ( ! in_array( $action, array( 'approve', 'reject' ), true ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'bulk-activities' ) ) {
            wp_die( esc_html__( 'Nonce verification failed.', 'bp-activity-approval' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'bp-activity-approval' ) );
        }

        $activity_ids = isset( $_POST['activity'] ) ? array_map( 'intval', $_POST['activity'] ) : array();
        
        if ( empty( $activity_ids ) ) {
            return;
        }

        $core = bp_activity_approval()->get_component( 'core' );
        $success_count = 0;
        
        foreach ( $activity_ids as $activity_id ) {
            if ( $action === 'approve' ) {
                $result = $core->approve_activity( $activity_id, __( 'Bulk approval', 'bp-activity-approval' ) );
            } else {
                $result = $core->reject_activity( $activity_id, __( 'Bulk rejection', 'bp-activity-approval' ) );
            }
            
            if ( $result ) {
                $success_count++;
            }
        }

        $message = sprintf(
            _n(
                '%d activity berhasil %s.',
                '%d activities berhasil %s.',
                $success_count,
                'bp-activity-approval'
            ),
            $success_count,
            $action === 'approve' ? __( 'disetujui', 'bp-activity-approval' ) : __( 'ditolak', 'bp-activity-approval' )
        );

        add_settings_error(
            'bp_activity_approval',
            'bulk_action_success',
            $message,
            'success'
        );
    }

    /**
     * Display admin notices
     *
     * @since 1.0.0
     */
    private function display_admin_notices() {
        settings_errors( 'bp_activity_approval_settings' );
        settings_errors( 'bp_activity_approval' );
    }
}