<?php
/**
 * Email notification system for BuddyPress Activity Approval
 *
 * @package BPActivityApproval
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BP_Activity_Approval_Email class
 *
 * Handles all email notifications for the approval system
 */
class BP_Activity_Approval_Email {

    /**
     * Email templates directory
     *
     * @var string
     */
    private $template_dir;

    /**
     * Constructor
     */
    public function __construct() {
        $this->template_dir = BP_ACTIVITY_APPROVAL_PATH . 'templates/emails/';
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('bp_activity_approval_new_pending', array($this, 'send_new_activity_notification'));
        add_action('bp_activity_approval_approved', array($this, 'send_approval_notification'));
        add_action('bp_activity_approval_rejected', array($this, 'send_rejection_notification'));
        
        // Custom email content type
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
    }

    /**
     * Send notification to admins when new activity is pending
     *
     * @param array $activity_data Activity data
     */
    public function send_new_activity_notification($activity_data) {
        $settings = get_option('bp_activity_approval_settings', array());
        
        if (empty($settings['email_notifications']) || $settings['email_notifications'] !== 'yes') {
            return;
        }

        $admin_emails = $this->get_admin_emails();
        if (empty($admin_emails)) {
            return;
        }

        $subject = $this->get_email_subject('new_activity', $activity_data);
        $message = $this->get_email_template('new-activity', $activity_data);

        foreach ($admin_emails as $email) {
            $this->send_email($email, $subject, $message);
        }

        // Log notification
        $this->log_email_notification('new_activity', $activity_data['id'], $admin_emails);
    }

    /**
     * Send notification to user when activity is approved
     *
     * @param array $activity_data Activity data
     */
    public function send_approval_notification($activity_data) {
        $settings = get_option('bp_activity_approval_settings', array());
        
        if (empty($settings['user_notifications']) || $settings['user_notifications'] !== 'yes') {
            return;
        }

        $user = get_userdata($activity_data['user_id']);
        if (!$user || empty($user->user_email)) {
            return;
        }

        $subject = $this->get_email_subject('approved', $activity_data);
        $message = $this->get_email_template('activity-approved', $activity_data);

        $this->send_email($user->user_email, $subject, $message);

        // Log notification
        $this->log_email_notification('approved', $activity_data['id'], array($user->user_email));
    }

    /**
     * Send notification to user when activity is rejected
     *
     * @param array $activity_data Activity data
     */
    public function send_rejection_notification($activity_data) {
        $settings = get_option('bp_activity_approval_settings', array());
        
        if (empty($settings['user_notifications']) || $settings['user_notifications'] !== 'yes') {
            return;
        }

        $user = get_userdata($activity_data['user_id']);
        if (!$user || empty($user->user_email)) {
            return;
        }

        $subject = $this->get_email_subject('rejected', $activity_data);
        $message = $this->get_email_template('activity-rejected', $activity_data);

        $this->send_email($user->user_email, $subject, $message);

        // Log notification
        $this->log_email_notification('rejected', $activity_data['id'], array($user->user_email));
    }

    /**
     * Send notification to user based on status
     *
     * @param BP_Activity_Activity $activity Activity object
     * @param string $status Status (approved/rejected)
     * @param string $admin_note Admin note
     */
    public function send_user_notification($activity, $status, $admin_note = '') {
        $activity_data = array(
            'id' => $activity->id,
            'user_id' => $activity->user_id,
            'content' => $activity->content,
            'type' => $activity->type,
            'component' => $activity->component,
            'date_recorded' => $activity->date_recorded,
            'admin_note' => $admin_note
        );

        if ($status === 'approved') {
            $this->send_approval_notification($activity_data);
        } elseif ($status === 'rejected') {
            $this->send_rejection_notification($activity_data);
        }
    }

    /**
     * Get admin email addresses
     *
     * @return array
     */
    private function get_admin_emails() {
        $settings = get_option('bp_activity_approval_settings', array());
        $emails = array();

        // Custom admin emails from settings
        if (!empty($settings['admin_emails'])) {
            $custom_emails = explode(',', $settings['admin_emails']);
            foreach ($custom_emails as $email) {
                $email = trim($email);
                if (is_email($email)) {
                    $emails[] = $email;
                }
            }
        }

        // Fallback to site admin email
        if (empty($emails)) {
            $admin_email = get_option('admin_email');
            if (is_email($admin_email)) {
                $emails[] = $admin_email;
            }
        }

        // Get all administrator users if no custom emails
        if (empty($emails)) {
            $admins = get_users(array(
                'role' => 'administrator',
                'fields' => 'user_email'
            ));
            $emails = array_merge($emails, $admins);
        }

        return array_unique($emails);
    }

    /**
     * Get email subject based on type
     *
     * @param string $type Email type
     * @param array $activity_data Activity data
     * @return string
     */
    private function get_email_subject($type, $activity_data) {
        $site_name = get_bloginfo('name');
        $user = get_userdata($activity_data['user_id']);
        $username = $user ? $user->display_name : 'Unknown User';

        switch ($type) {
            case 'new_activity':
                return sprintf('[%s] New Activity Pending Approval from %s', $site_name, $username);
            
            case 'approved':
                return sprintf('[%s] Your Activity Has Been Approved', $site_name);
            
            case 'rejected':
                return sprintf('[%s] Your Activity Requires Revision', $site_name);
            
            default:
                return sprintf('[%s] Activity Update', $site_name);
        }
    }

    /**
     * Get email template
     *
     * @param string $template Template name
     * @param array $activity_data Activity data
     * @return string
     */
    private function get_email_template($template, $activity_data) {
        $template_file = $this->template_dir . $template . '.php';
        
        if (file_exists($template_file)) {
            ob_start();
            include $template_file;
            return ob_get_clean();
        }

        // Fallback to default template
        return $this->get_default_template($template, $activity_data);
    }

    /**
     * Get default email template
     *
     * @param string $template Template name
     * @param array $activity_data Activity data
     * @return string
     */
    private function get_default_template($template, $activity_data) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $user = get_userdata($activity_data['user_id']);
        $username = $user ? $user->display_name : 'Unknown User';
        $content_preview = wp_trim_words(strip_tags($activity_data['content']), 20);
        
        $admin_url = admin_url('admin.php?page=bp-activity-approval');
        $activity_url = bp_activity_get_permalink($activity_data['id']);

        switch ($template) {
            case 'new-activity':
                return $this->build_html_template(array(
                    'title' => 'New Activity Pending Approval',
                    'content' => "
                        <p>Hello Administrator,</p>
                        <p>A new activity has been submitted and is waiting for your approval:</p>
                        <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;'>
                            <strong>Author:</strong> {$username}<br>
                            <strong>Content Preview:</strong> {$content_preview}
                        </div>
                        <p>Please review and take appropriate action:</p>
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='{$admin_url}' style='background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>Review Activity</a>
                        </p>
                    "
                ));

            case 'activity-approved':
                return $this->build_html_template(array(
                    'title' => 'Your Activity Has Been Approved',
                    'content' => "
                        <p>Hello {$username},</p>
                        <p>Great news! Your activity has been approved and is now visible to the community.</p>
                        <div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #00a32a; margin: 20px 0;'>
                            <strong>Content:</strong> {$content_preview}
                        </div>
                        <p>Thank you for contributing to our community!</p>
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='{$activity_url}' style='background: #00a32a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>View Your Activity</a>
                        </p>
                    "
                ));

            case 'activity-rejected':
                $admin_note = !empty($activity_data['admin_note']) ? $activity_data['admin_note'] : 'No specific reason provided.';
                return $this->build_html_template(array(
                    'title' => 'Your Activity Requires Revision',
                    'content' => "
                        <p>Hello {$username},</p>
                        <p>Your recent activity submission requires some revision before it can be published.</p>
                        <div style='background: #fff3cd; padding: 15px; border-left: 4px solid #856404; margin: 20px 0;'>
                            <strong>Content:</strong> {$content_preview}<br><br>
                            <strong>Admin Note:</strong> {$admin_note}
                        </div>
                        <p>Please review the feedback and feel free to submit a revised version.</p>
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='{$site_url}' style='background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>Visit Site</a>
                        </p>
                    "
                ));

            default:
                return $this->build_html_template(array(
                    'title' => 'Activity Update',
                    'content' => "<p>Your activity status has been updated.</p>"
                ));
        }
    }

    /**
     * Build HTML email template
     *
     * @param array $data Template data
     * @return string
     */
    private function build_html_template($data) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $current_year = date('Y');
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$data['title']}</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #eee; }
                .footer a { color: #0073aa; text-decoration: none; }
                @media only screen and (max-width: 600px) {
                    .container { width: 100% !important; }
                    .content { padding: 20px !important; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$site_name}</h1>
                </div>
                <div class='content'>
                    <h2>{$data['title']}</h2>
                    {$data['content']}
                </div>
                <div class='footer'>
                    <p>&copy; {$current_year} {$site_name}. All rights reserved.</p>
                    <p><a href='{$site_url}'>Visit our website</a></p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Send email
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $message Email message
     * @return bool
     */
    private function send_email($to, $subject, $message) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Set HTML content type for emails
     *
     * @return string
     */
    public function set_html_content_type() {
        return 'text/html';
    }

    /**
     * Log email notification
     *
     * @param string $type Notification type
     * @param int $activity_id Activity ID
     * @param array $recipients Email recipients
     */
    private function log_email_notification($type, $activity_id, $recipients) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bp_activity_approval_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'activity_id' => $activity_id,
                'action' => 'email_sent',
                'admin_id' => get_current_user_id(),
                'admin_note' => sprintf('Email notification (%s) sent to: %s', $type, implode(', ', $recipients)),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Send test email
     *
     * @param string $email Test email address
     * @return bool
     */
    public function send_test_email($email) {
        if (!is_email($email)) {
            return false;
        }

        $subject = '[' . get_bloginfo('name') . '] Test Email - Activity Approval System';
        $message = $this->build_html_template(array(
            'title' => 'Test Email',
            'content' => "
                <p>This is a test email from the BuddyPress Activity Approval System.</p>
                <p>If you received this email, the notification system is working correctly.</p>
                <p><strong>Test sent at:</strong> " . current_time('F j, Y g:i a') . "</p>
            "
        ));

        return $this->send_email($email, $subject, $message);
    }

    /**
     * Get email statistics
     *
     * @return array
     */
    public function get_email_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bp_activity_approval_logs';
        
        $stats = array(
            'total_sent' => 0,
            'sent_today' => 0,
            'sent_this_week' => 0,
            'sent_this_month' => 0
        );

        // Total emails sent
        $stats['total_sent'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE action = 'email_sent'"
        );

        // Emails sent today
        $stats['sent_today'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE action = 'email_sent' AND DATE(created_at) = %s",
                current_time('Y-m-d')
            )
        );

        // Emails sent this week
        $stats['sent_this_week'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE action = 'email_sent' AND YEARWEEK(created_at) = %s",
                date('oW')
            )
        );

        // Emails sent this month
        $stats['sent_this_month'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE action = 'email_sent' AND YEAR(created_at) = %d AND MONTH(created_at) = %d",
                date('Y'),
                date('n')
            )
        );

        return $stats;
    }
}