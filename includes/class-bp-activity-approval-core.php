<?php
/**
 * Core functionality for BuddyPress Activity Approval System
 *
 * @package BPActivityApproval
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Core class for handling activity approval logic
 *
 * @since 1.0.0
 */
class BP_Activity_Approval_Core {

    /**
     * Validator instance
     *
     * @var BP_Activity_Approval_Validator
     * @since 1.0.0
     */
    private $validator;

    /**
     * Email handler instance
     *
     * @var BP_Activity_Approval_Email
     * @since 1.0.0
     */
    private $email_handler;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->validator = new BP_Activity_Approval_Validator();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Hook into BuddyPress activity before save
        add_action( 'bp_activity_before_save', array( $this, 'intercept_activity_save' ), 10, 1 );
        
        // Hook into activity query to hide pending activities
        add_filter( 'bp_activity_get_where_conditions', array( $this, 'filter_pending_activities' ), 10, 2 );
        
        // Fix undefined array keys in bp_activity_get results
        add_filter( 'bp_activity_get', array( $this, 'fix_activity_array_structure' ), 5, 2 );
        
        // Add custom activity meta
        add_action( 'bp_activity_after_save', array( $this, 'save_approval_meta' ), 10, 1 );
        
        // Handle approval actions
        add_action( 'wp_ajax_bp_approve_activity', array( $this, 'handle_approve_activity' ) );
        add_action( 'wp_ajax_bp_reject_activity', array( $this, 'handle_reject_activity' ) );
        
        // Add admin bar menu for pending count
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );
    }

    /**
     * Intercept activity save and set to pending status
     *
     * @param BP_Activity_Activity $activity Activity object
     * @since 1.0.0
     */
    public function intercept_activity_save( $activity ) {
        // Skip if activity is already saved (update scenario)
        if ( ! empty( $activity->id ) ) {
            return;
        }

        // Get plugin settings
        $settings = get_option( 'bp_activity_approval_settings', array() );
        
        // Check if this activity type requires approval - default to all activity types
        $require_approval_for = isset( $settings['require_approval_for'] ) ? $settings['require_approval_for'] : array( 'activity_update', 'activity_comment' );
        
        // For now, require approval for all new activities (can be configured later)
        if ( ! in_array( $activity->type, $require_approval_for, true ) ) {
            // Still require approval for main activity types
            if ( ! in_array( $activity->type, array( 'activity_update', 'activity_comment' ), true ) ) {
                return;
            }
        }

        // Skip approval for administrators if setting is enabled
        if ( isset( $settings['auto_approve_admin'] ) && $settings['auto_approve_admin'] && current_user_can( 'manage_options' ) ) {
            return;
        }

        // Validate activity data
        $validation_result = $this->validator->validate_activity( $activity );
        
        if ( is_wp_error( $validation_result ) ) {
            // Log validation error
            error_log( 'BP Activity Approval - Validation failed: ' . $validation_result->get_error_message() );
            return;
        }

        // Set activity to pending status - don't hide completely, just mark as pending
        // We'll handle visibility in the filter_pending_activities method
        
        // Add custom meta to mark as pending approval
        add_action( 'bp_activity_after_save', function( $saved_activity ) use ( $activity ) {
            if ( $saved_activity->id === $activity->id ) {
                $this->mark_activity_pending( $saved_activity );
            }
        }, 5 );
    }

    /**
     * Mark activity as pending approval
     *
     * @param BP_Activity_Activity $activity Activity object
     * @since 1.0.0
     */
    private function mark_activity_pending( $activity ) {
        global $wpdb;

        // Insert into approval log table
        $table_name = $wpdb->prefix . 'bp_activity_approval_log';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'activity_id' => $activity->id,
                'user_id'     => $activity->user_id,
                'status'      => 'pending',
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s' )
        );

        if ( $result ) {
            // Add activity meta
            bp_activity_update_meta( $activity->id, 'approval_status', 'pending' );
            bp_activity_update_meta( $activity->id, 'approval_requested_at', current_time( 'mysql' ) );
            
            // Send email notification to admin
            $this->send_admin_notification( $activity );
            
            // Log the action
            $this->log_approval_action( $activity->id, 'pending', 'System automatically set to pending approval' );
        }
    }

    /**
     * Filter out pending activities from public queries
     *
     * @param array $where_conditions Where conditions
     * @param array $r Query arguments
     * @return array Modified where conditions
     * @since 1.0.0
     */
    public function filter_pending_activities( $where_conditions, $r ) {
        global $wpdb;

        // Don't filter in admin area
        if ( is_admin() ) {
            return $where_conditions;
        }

        // Don't filter for administrators (they can see all activities)
        if ( current_user_can( 'manage_options' ) ) {
            return $where_conditions;
        }

        // Check if approval log table exists
        $approval_table = $wpdb->prefix . 'bp_activity_approval_log';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$approval_table}'" );
        
        if ( ! $table_exists ) {
            return $where_conditions;
        }
        
        // Get current user ID
        $current_user_id = get_current_user_id();
        
        // Add condition to exclude pending activities, except for activity owners
        $bp = buddypress();
        
        if ( $current_user_id > 0 ) {
            // Logged-in users can see their own pending activities
            $where_conditions['approval_filter'] = "a.id NOT IN (
                SELECT activity_id FROM {$approval_table} 
                WHERE status = 'pending' AND activity_id NOT IN (
                    SELECT id FROM {$bp->activity->table_name} 
                    WHERE user_id = {$current_user_id}
                )
            )";
        } else {
            // Non-logged-in users cannot see any pending activities
            $where_conditions['approval_filter'] = "a.id NOT IN (
                SELECT activity_id FROM {$approval_table} 
                WHERE status = 'pending'
            )";
        }

        return $where_conditions;
    }

    /**
     * Save approval metadata after activity save
     *
     * @param BP_Activity_Activity $activity Activity object
     * @since 1.0.0
     */
    public function save_approval_meta( $activity ) {
        // This method can be used for additional meta saving if needed
        // Currently handled in mark_activity_pending method
    }

    /**
     * Handle activity approval via AJAX
     *
     * @since 1.0.0
     */
    public function handle_approve_activity() {
        // Verify nonce and permissions
        if ( ! wp_verify_nonce( $_POST['nonce'], 'bp_activity_approval_nonce' ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Akses ditolak.', 'bp-activity-approval' ) );
        }

        $activity_id = intval( $_POST['activity_id'] );
        $admin_note = sanitize_textarea_field( $_POST['admin_note'] ?? '' );

        $result = $this->approve_activity( $activity_id, $admin_note );

        if ( $result ) {
            wp_send_json_success( array(
                'message' => __( 'Activity berhasil disetujui.', 'bp-activity-approval' )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Gagal menyetujui activity.', 'bp-activity-approval' )
            ) );
        }
    }

    /**
     * Handle activity rejection via AJAX
     *
     * @since 1.0.0
     */
    public function handle_reject_activity() {
        // Verify nonce and permissions
        if ( ! wp_verify_nonce( $_POST['nonce'], 'bp_activity_approval_nonce' ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Akses ditolak.', 'bp-activity-approval' ) );
        }

        $activity_id = intval( $_POST['activity_id'] );
        $admin_note = sanitize_textarea_field( $_POST['admin_note'] ?? '' );

        $result = $this->reject_activity( $activity_id, $admin_note );

        if ( $result ) {
            wp_send_json_success( array(
                'message' => __( 'Activity berhasil ditolak.', 'bp-activity-approval' )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Gagal menolak activity.', 'bp-activity-approval' )
            ) );
        }
    }

    /**
     * Approve an activity
     *
     * @param int $activity_id Activity ID
     * @param string $admin_note Admin note
     * @return bool Success status
     * @since 1.0.0
     */
    public function approve_activity( $activity_id, $admin_note = '' ) {
        global $wpdb;

        // Get activity object
        $activity = new BP_Activity_Activity( $activity_id );
        
        if ( empty( $activity->id ) ) {
            return false;
        }

        // Remove pending status text from activity action before approval
        $this->remove_pending_status_from_action( $activity );

        // Update activity to show sitewide
        $activity->hide_sitewide = 0;
        $activity->save();

        // Update approval log
        $table_name = $wpdb->prefix . 'bp_activity_approval_log';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'status'     => 'approved',
                'admin_id'   => get_current_user_id(),
                'admin_note' => $admin_note,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'activity_id' => $activity_id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            // Update activity meta
            bp_activity_update_meta( $activity_id, 'approval_status', 'approved' );
            bp_activity_update_meta( $activity_id, 'approved_at', current_time( 'mysql' ) );
            bp_activity_update_meta( $activity_id, 'approved_by', get_current_user_id() );
            
            // Log the action
            $this->log_approval_action( $activity_id, 'approved', $admin_note );
            
            // Send notification to user
            $this->send_user_notification( $activity, 'approved', $admin_note );
            
            return true;
        }

        return false;
    }

    /**
     * Remove pending status text from activity action
     *
     * @param BP_Activity_Activity $activity Activity object
     * @since 1.0.0
     */
    private function remove_pending_status_from_action( $activity ) {
        if ( empty( $activity->action ) ) {
            return;
        }

        // Remove pending status text and HTML tags
        $patterns = array(
            '/<span[^>]*class="bp-activity-pending-status"[^>]*>.*?<\/span>/',
            '/\s*\(pending approval by admin\)/',
        );

        $clean_action = preg_replace( $patterns, '', $activity->action );
        $clean_action = trim( $clean_action );

        if ( $clean_action !== $activity->action ) {
            $activity->action = $clean_action;
        }
    }

    /**
     * Reject an activity
     *
     * @param int $activity_id Activity ID
     * @param string $admin_note Admin note
     * @return bool Success status
     * @since 1.0.0
     */
    public function reject_activity( $activity_id, $admin_note = '' ) {
        global $wpdb;

        // Get activity object
        $activity = new BP_Activity_Activity( $activity_id );
        
        if ( empty( $activity->id ) ) {
            return false;
        }

        // Update approval log
        $table_name = $wpdb->prefix . 'bp_activity_approval_log';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'status'     => 'rejected',
                'admin_id'   => get_current_user_id(),
                'admin_note' => $admin_note,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'activity_id' => $activity_id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            // Update activity meta
            bp_activity_update_meta( $activity_id, 'approval_status', 'rejected' );
            bp_activity_update_meta( $activity_id, 'rejected_at', current_time( 'mysql' ) );
            bp_activity_update_meta( $activity_id, 'rejected_by', get_current_user_id() );
            
            // Log the action
            $this->log_approval_action( $activity_id, 'rejected', $admin_note );
            
            // Send notification to user
            $this->send_user_notification( $activity, 'rejected', $admin_note );
            
            // Optionally delete the activity
            // $activity->delete();
            
            return true;
        }

        return false;
    }

    /**
     * Send admin notification email
     *
     * @param BP_Activity_Activity $activity Activity object
     * @since 1.0.0
     */
    private function send_admin_notification( $activity ) {
        $settings = get_option( 'bp_activity_approval_settings', array() );
        
        if ( ! isset( $settings['email_notifications'] ) || ! $settings['email_notifications'] ) {
            return;
        }

        $email_handler = bp_activity_approval()->get_component( 'email' );
        if ( $email_handler ) {
            $activity_data = array(
                'id' => $activity->id,
                'user_id' => $activity->user_id,
                'content' => $activity->content,
                'type' => $activity->type,
                'component' => $activity->component,
                'date_recorded' => $activity->date_recorded
            );
            $email_handler->send_new_activity_notification( $activity_data );
        }
    }

    /**
     * Send user notification email
     *
     * @param BP_Activity_Activity $activity Activity object
     * @param string $status Approval status
     * @param string $admin_note Admin note
     * @since 1.0.0
     */
    private function send_user_notification( $activity, $status, $admin_note = '' ) {
        $email_handler = bp_activity_approval()->get_component( 'email' );
        if ( $email_handler ) {
            $email_handler->send_user_notification( $activity, $status, $admin_note );
        }
    }

    /**
     * Log approval action
     *
     * @param int $activity_id Activity ID
     * @param string $action Action performed
     * @param string $note Additional note
     * @since 1.0.0
     */
    private function log_approval_action( $activity_id, $action, $note = '' ) {
        $log_entry = sprintf(
            '[%s] Activity ID: %d, Action: %s, User: %s, Note: %s',
            current_time( 'mysql' ),
            $activity_id,
            $action,
            wp_get_current_user()->user_login,
            $note
        );
        
        error_log( 'BP Activity Approval: ' . $log_entry );
    }

    /**
     * Add admin bar menu for pending activities count
     *
     * @param WP_Admin_Bar $wp_admin_bar Admin bar object
     * @since 1.0.0
     */
    public function add_admin_bar_menu( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $pending_count = $this->get_pending_activities_count();
        
        if ( $pending_count > 0 ) {
            $wp_admin_bar->add_menu( array(
                'id'    => 'bp-activity-approval',
                'title' => sprintf(
                    '<span class="ab-icon dashicons dashicons-clock"></span> %s <span class="awaiting-mod count-%d"><span class="pending-count">%d</span></span>',
                    __( 'Pending Activities', 'bp-activity-approval' ),
                    $pending_count,
                    $pending_count
                ),
                'href'  => admin_url( 'admin.php?page=bp-activity-approval' ),
            ) );
        }
    }

    /**
     * Get count of pending activities
     *
     * @return int Pending count
     * @since 1.0.0
     */
    public function get_pending_activities_count() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bp_activity_approval_log';
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
                'pending'
            )
        );
        
        return intval( $count );
    }

    /**
     * Get pending activities for admin dashboard
     *
     * @param array $args Query arguments
     * @return array Activities data
     * @since 1.0.0
     */
    public function get_pending_activities( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        $table_name = $wpdb->prefix . 'bp_activity_approval_log';
        $bp_table = buddypress()->activity->table_name;
        
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        
        $query = $wpdb->prepare(
            "SELECT al.*, a.content, a.type, a.user_id, a.date_recorded
             FROM {$table_name} al
             LEFT JOIN {$bp_table} a ON al.activity_id = a.id
             WHERE al.status = %s
             ORDER BY al.{$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            'pending',
            $args['per_page'],
            $offset
        );
        
        return $wpdb->get_results( $query );
    }

    /**
     * Fix undefined array keys in bp_activity_get results and add approval status display
     *
     * @param array $activities Activity results from bp_activity_get
     * @param array $r Query arguments
     * @return array Fixed activity results
     * @since 1.0.0
     */
    public function fix_activity_array_structure( $activities, $r ) {
        if ( ! is_array( $activities ) || ! isset( $activities['activities'] ) ) {
            return $activities;
        }

        foreach ( $activities['activities'] as &$activity ) {
            if ( ! is_object( $activity ) ) {
                continue;
            }

            // Ensure action field exists
            if ( empty( $activity->action ) ) {
                // Generate default action based on activity type
                if ( ! empty( $activity->type ) ) {
                    switch ( $activity->type ) {
                        case 'activity_update':
                            $activity->action = sprintf( 
                                '<a href="%s">%s</a> posted an update', 
                                bp_core_get_user_domain( $activity->user_id ), 
                                bp_core_get_user_displayname( $activity->user_id ) 
                            );
                            break;
                        case 'activity_comment':
                            $activity->action = sprintf( 
                                '<a href="%s">%s</a> posted a comment', 
                                bp_core_get_user_domain( $activity->user_id ), 
                                bp_core_get_user_displayname( $activity->user_id ) 
                            );
                            break;
                        default:
                            $activity->action = sprintf( 
                                '<a href="%s">%s</a> posted an activity', 
                                bp_core_get_user_domain( $activity->user_id ), 
                                bp_core_get_user_displayname( $activity->user_id ) 
                            );
                            break;
                    }
                }
            }

            // Add approval status display
            $this->add_approval_status_display( $activity );
        }

        return $activities;
    }

    /**
     * Add approval status display to activity action
     *
     * @param object $activity Activity object
     * @since 1.0.0
     */
    private function add_approval_status_display( &$activity ) {
        if ( ! isset( $activity->id ) ) {
            return;
        }

        // Get approval status from meta
        $approval_status = bp_activity_get_meta( $activity->id, 'approval_status', true );
        
        if ( $approval_status === 'pending' ) {
            // Check if current user can see pending status
            if ( $this->can_user_see_pending_status( $activity ) ) {
                // Add pending status text to action
                $pending_text = ' <span class="bp-activity-pending-status" style="color: #d63638; font-weight: bold;">(pending approval by admin)</span>';
                
                // Ensure action exists before modifying
                if ( ! isset( $activity->action ) ) {
                    $activity->action = '';
                }
                
                // Only add if not already present
                if ( strpos( $activity->action, 'pending approval by admin' ) === false ) {
                    $activity->action .= $pending_text;
                }
            }
        }
    }

    /**
     * Check if current user can see pending approval status
     *
     * @param object $activity Activity object
     * @return bool True if user can see pending status
     * @since 1.0.0
     */
    private function can_user_see_pending_status( $activity ) {
        // Admin can always see pending status
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        // Activity owner can see their own pending status
        if ( isset( $activity->user_id ) && get_current_user_id() === (int) $activity->user_id ) {
            return true;
        }

        return false;
    }
}