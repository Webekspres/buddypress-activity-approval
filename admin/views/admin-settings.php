<?php
/**
 * Admin Settings Page Template
 *
 * @package BPActivityApproval
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('bp_activity_approval_settings', array());
$email_stats = BP_Activity_Approval()->email->get_email_stats();
?>

<div class="wrap bp-activity-approval-admin">
    <div class="bp-activity-approval-header">
        <h1><?php _e('Activity Approval Settings', 'bp-activity-approval'); ?></h1>
        <p class="description"><?php _e('Configure the BuddyPress Activity Approval System settings.', 'bp-activity-approval'); ?></p>
    </div>

    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully!', 'bp-activity-approval'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php" id="activity-approval-form">
        <?php
        settings_fields('bp_activity_approval_settings');
        do_settings_sections('bp_activity_approval_settings');
        ?>

        <div class="bp-activity-approval-settings">
            <table class="form-table" role="presentation">
                <tbody>
                    <!-- General Settings -->
                    <tr>
                        <th scope="row">
                            <h3><?php _e('General Settings', 'bp-activity-approval'); ?></h3>
                        </th>
                        <td></td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="auto_approve_admins"><?php _e('Auto-approve Admin Posts', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="auto_approve_admins" name="bp_activity_approval_settings[auto_approve_admins]" value="yes" <?php checked(isset($settings['auto_approve_admins']) ? $settings['auto_approve_admins'] : '', 'yes'); ?>>
                            <label for="auto_approve_admins"><?php _e('Automatically approve activities from administrators', 'bp-activity-approval'); ?></label>
                            <p class="setting-description"><?php _e('When enabled, activities posted by users with administrator role will be automatically approved.', 'bp-activity-approval'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="auto_approve_moderators"><?php _e('Auto-approve Moderator Posts', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="auto_approve_moderators" name="bp_activity_approval_settings[auto_approve_moderators]" value="yes" <?php checked(isset($settings['auto_approve_moderators']) ? $settings['auto_approve_moderators'] : '', 'yes'); ?>>
                            <label for="auto_approve_moderators"><?php _e('Automatically approve activities from moderators', 'bp-activity-approval'); ?></label>
                            <p class="setting-description"><?php _e('When enabled, activities posted by users with moderator capabilities will be automatically approved.', 'bp-activity-approval'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="activity_types"><?php _e('Activity Types to Moderate', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <?php
                            $activity_types = array(
                                'activity_update' => __('Status Updates', 'bp-activity-approval'),
                                'activity_comment' => __('Activity Comments', 'bp-activity-approval'),
                                'new_blog_post' => __('Blog Posts', 'bp-activity-approval'),
                                'new_blog_comment' => __('Blog Comments', 'bp-activity-approval'),
                                'friendship_created' => __('New Friendships', 'bp-activity-approval'),
                                'created_group' => __('Group Creation', 'bp-activity-approval'),
                                'joined_group' => __('Group Joins', 'bp-activity-approval')
                            );
                            
                            $selected_types = isset($settings['activity_types']) ? $settings['activity_types'] : array('activity_update');
                            
                            foreach ($activity_types as $type => $label):
                            ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="bp_activity_approval_settings[activity_types][]" value="<?php echo esc_attr($type); ?>" <?php checked(in_array($type, $selected_types)); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="setting-description"><?php _e('Select which types of activities should require approval.', 'bp-activity-approval'); ?></p>
                        </td>
                    </tr>

                    <!-- Content Validation -->
                    <tr>
                        <th scope="row">
                            <h3><?php _e('Content Validation', 'bp-activity-approval'); ?></h3>
                        </th>
                        <td></td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="min_content_length"><?php _e('Minimum Content Length', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="min_content_length" name="bp_activity_approval_settings[min_content_length]" value="<?php echo esc_attr(isset($settings['min_content_length']) ? $settings['min_content_length'] : 10); ?>" min="0" max="1000" class="small-text">
                            <span><?php _e('characters', 'bp-activity-approval'); ?></span>
                            <p class="setting-description"><?php _e('Minimum number of characters required for activity content. Set to 0 to disable.', 'bp-activity-approval'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="max_content_length"><?php _e('Maximum Content Length', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="max_content_length" name="bp_activity_approval_settings[max_content_length]" value="<?php echo esc_attr(isset($settings['max_content_length']) ? $settings['max_content_length'] : 5000); ?>" min="0" max="10000" class="small-text">
                            <span><?php _e('characters', 'bp-activity-approval'); ?></span>
                            <p class="setting-description"><?php _e('Maximum number of characters allowed for activity content. Set to 0 to disable.', 'bp-activity-approval'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="blocked_words"><?php _e('Blocked Words', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <textarea id="blocked_words" name="bp_activity_approval_settings[blocked_words]" rows="5" cols="50" class="large-text"><?php echo esc_textarea(isset($settings['blocked_words']) ? $settings['blocked_words'] : ''); ?></textarea>
                            <p class="setting-description"><?php _e('Enter blocked words or phrases, one per line. Activities containing these words will be automatically rejected.', 'bp-activity-approval'); ?></p>
                        </td>
                    </tr>

                    <!-- Email Notifications -->
                    <tr>
                        <th scope="row">
                            <h3><?php _e('Email Notifications', 'bp-activity-approval'); ?></h3>
                        </th>
                        <td></td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="email_notifications"><?php _e('Admin Notifications', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="email_notifications" name="bp_activity_approval_settings[email_notifications]" value="yes" <?php checked(isset($settings['email_notifications']) ? $settings['email_notifications'] : 'yes', 'yes'); ?>>
                            <label for="email_notifications"><?php _e('Send email notifications to administrators when new activities are pending', 'bp-activity-approval'); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="user_notifications"><?php _e('User Notifications', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="user_notifications" name="bp_activity_approval_settings[user_notifications]" value="yes" <?php checked(isset($settings['user_notifications']) ? $settings['user_notifications'] : 'yes', 'yes'); ?>>
                            <label for="user_notifications"><?php _e('Send email notifications to users when their activities are approved or rejected', 'bp-activity-approval'); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="admin_emails"><?php _e('Admin Email Addresses', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="admin_emails" name="bp_activity_approval_settings[admin_emails]" value="<?php echo esc_attr(isset($settings['admin_emails']) ? $settings['admin_emails'] : get_option('admin_email')); ?>" class="large-text">
                            <p class="setting-description"><?php _e('Enter email addresses separated by commas. Leave empty to use all administrator emails.', 'bp-activity-approval'); ?></p>
                        </td>
                    </tr>

                    <!-- Email Statistics -->
                    <tr>
                        <th scope="row">
                            <label><?php _e('Email Statistics', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <div class="approval-stats">
                                <div class="stat-card">
                                    <span class="stat-number"><?php echo number_format($email_stats['total_sent']); ?></span>
                                    <span class="stat-label"><?php _e('Total Sent', 'bp-activity-approval'); ?></span>
                                </div>
                                <div class="stat-card">
                                    <span class="stat-number"><?php echo number_format($email_stats['sent_today']); ?></span>
                                    <span class="stat-label"><?php _e('Sent Today', 'bp-activity-approval'); ?></span>
                                </div>
                                <div class="stat-card">
                                    <span class="stat-number"><?php echo number_format($email_stats['sent_this_week']); ?></span>
                                    <span class="stat-label"><?php _e('This Week', 'bp-activity-approval'); ?></span>
                                </div>
                                <div class="stat-card">
                                    <span class="stat-number"><?php echo number_format($email_stats['sent_this_month']); ?></span>
                                    <span class="stat-label"><?php _e('This Month', 'bp-activity-approval'); ?></span>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Test Email -->
                    <tr>
                        <th scope="row">
                            <label for="test_email"><?php _e('Test Email', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="test_email" placeholder="<?php _e('Enter email address', 'bp-activity-approval'); ?>" class="regular-text">
                            <button type="button" id="send-test-email" class="button"><?php _e('Send Test Email', 'bp-activity-approval'); ?></button>
                            <p class="setting-description"><?php _e('Send a test email to verify the notification system is working correctly.', 'bp-activity-approval'); ?></p>
                            <div id="test-email-result" style="margin-top: 10px;"></div>
                        </td>
                    </tr>

                    <!-- Advanced Settings -->
                    <tr>
                        <th scope="row">
                            <h3><?php _e('Advanced Settings', 'bp-activity-approval'); ?></h3>
                        </th>
                        <td></td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="enable_spam_detection"><?php _e('Spam Detection', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="enable_spam_detection" name="bp_activity_approval_settings[enable_spam_detection]" value="yes" <?php checked(isset($settings['enable_spam_detection']) ? $settings['enable_spam_detection'] : '', 'yes'); ?>>
                            <label for="enable_spam_detection"><?php _e('Enable basic spam detection for activities', 'bp-activity-approval'); ?></label>
                            <p class="setting-description"><?php _e('Automatically flag potential spam content for manual review.', 'bp-activity-approval'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="delete_data_on_uninstall"><?php _e('Delete Data on Uninstall', 'bp-activity-approval'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="delete_data_on_uninstall" name="bp_activity_approval_settings[delete_data_on_uninstall]" value="yes" <?php checked(isset($settings['delete_data_on_uninstall']) ? $settings['delete_data_on_uninstall'] : '', 'yes'); ?>>
                            <label for="delete_data_on_uninstall"><?php _e('Delete all plugin data when uninstalling', 'bp-activity-approval'); ?></label>
                            <p class="setting-description"><?php _e('Warning: This will permanently delete all approval logs and settings when the plugin is uninstalled.', 'bp-activity-approval'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php submit_button(__('Save Settings', 'bp-activity-approval')); ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Test email functionality
    $('#send-test-email').on('click', function() {
        var button = $(this);
        var email = $('#test_email').val();
        var resultDiv = $('#test-email-result');
        
        if (!email) {
            resultDiv.html('<div class="notice notice-error inline"><p><?php _e("Please enter an email address.", "bp-activity-approval"); ?></p></div>');
            return;
        }
        
        button.prop('disabled', true).text('<?php _e("Sending...", "bp-activity-approval"); ?>');
        resultDiv.empty();
        
        $.post(ajaxurl, {
            action: 'bp_send_test_email',
            email: email,
            nonce: '<?php echo wp_create_nonce("bp_test_email_nonce"); ?>'
        }, function(response) {
            if (response.success) {
                resultDiv.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
            } else {
                resultDiv.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
            }
        }).fail(function() {
            resultDiv.html('<div class="notice notice-error inline"><p><?php _e("Failed to send test email. Please try again.", "bp-activity-approval"); ?></p></div>');
        }).always(function() {
            button.prop('disabled', false).text('<?php _e("Send Test Email", "bp-activity-approval"); ?>');
        });
    });
});
</script>