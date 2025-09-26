/**
 * Admin JavaScript for BuddyPress Activity Approval
 *
 * @package BPActivityApproval
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var BPActivityApproval = {
        
        /**
         * Initialize the admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initModal();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Single activity approval/rejection
            $(document).on('click', '.approve-activity', this.handleSingleApprove);
            $(document).on('click', '.reject-activity', this.handleSingleReject);
            $(document).on('click', '.view-activity', this.handleViewActivity);
            
            // Bulk actions
            $('#doaction, #doaction2').on('click', this.handleBulkAction);
            
            // Modal actions
            $(document).on('click', '#confirm-approve', this.confirmApprove);
            $(document).on('click', '#confirm-reject', this.confirmReject);
            $(document).on('click', '#cancel-action, .modal-close', this.closeModal);
            
            // Form validation
            $('#activity-approval-form').on('submit', this.validateForm);
        },

        /**
         * Initialize modal
         */
        initModal: function() {
            if ($('#activity-approval-modal').length === 0) {
                $('body').append(this.getModalHTML());
            }
        },

        /**
         * Handle single activity approval
         */
        handleSingleApprove: function(e) {
            e.preventDefault();
            
            var activityId = $(this).data('activity-id');
            var activityContent = $(this).closest('tr').find('.column-content').text().trim();
            
            BPActivityApproval.showModal('approve', activityId, activityContent);
        },

        /**
         * Handle single activity rejection
         */
        handleSingleReject: function(e) {
            e.preventDefault();
            
            var activityId = $(this).data('activity-id');
            var activityContent = $(this).closest('tr').find('.column-content').text().trim();
            
            BPActivityApproval.showModal('reject', activityId, activityContent);
        },

        /**
         * Handle view full activity content
         */
        handleViewActivity: function(e) {
            e.preventDefault();
            
            var activityId = $(this).data('activity-id');
            BPActivityApproval.showActivityDetails(activityId);
        },

        /**
         * Handle bulk actions
         */
        handleBulkAction: function(e) {
            var action = $(this).siblings('select[name="action"]').val();
            if (action === '-1') {
                action = $(this).siblings('select[name="action2"]').val();
            }
            
            if (action === '-1' || (action !== 'approve' && action !== 'reject')) {
                return true;
            }
            
            var checkedItems = $('input[name="activity[]"]:checked');
            if (checkedItems.length === 0) {
                alert(bpActivityApproval.strings.error);
                return false;
            }
            
            if (!confirm(bpActivityApproval.strings.confirmBulk)) {
                return false;
            }
            
            return true;
        },

        /**
         * Show modal for approval/rejection
         */
        showModal: function(action, activityId, content) {
            var modal = $('#activity-approval-modal');
            var title = action === 'approve' ? 'Approve Activity' : 'Reject Activity';
            var buttonText = action === 'approve' ? 'Approve' : 'Reject';
            var buttonClass = action === 'approve' ? 'button-primary' : 'button-secondary';
            
            modal.find('.modal-title').text(title);
            modal.find('.activity-content').text(content);
            modal.find('#admin-note').val('');
            modal.find('#confirm-' + action).show().siblings('.confirm-button').hide();
            modal.find('.confirm-button').removeClass('button-primary button-secondary').addClass(buttonClass);
            
            modal.data('activity-id', activityId);
            modal.data('action', action);
            modal.show();
            
            // Focus on textarea
            setTimeout(function() {
                modal.find('#admin-note').focus();
            }, 100);
        },

        /**
         * Show activity details modal
         */
        showActivityDetails: function(activityId) {
            var data = {
                action: 'bp_get_activity_details',
                activity_id: activityId,
                nonce: bpActivityApproval.nonce
            };
            
            $.post(bpActivityApproval.ajaxUrl, data, function(response) {
                if (response.success) {
                    BPActivityApproval.showDetailsModal(response.data);
                } else {
                    alert(response.data.message || bpActivityApproval.strings.error);
                }
            });
        },

        /**
         * Show details modal
         */
        showDetailsModal: function(data) {
            var modal = $('#activity-details-modal');
            if (modal.length === 0) {
                $('body').append(this.getDetailsModalHTML());
                modal = $('#activity-details-modal');
            }
            
            modal.find('.activity-full-content').html(data.content);
            modal.find('.activity-author').text(data.author);
            modal.find('.activity-date').text(data.date);
            modal.find('.activity-type').text(data.type);
            modal.show();
        },

        /**
         * Confirm approval
         */
        confirmApprove: function() {
            var modal = $('#activity-approval-modal');
            var activityId = modal.data('activity-id');
            var adminNote = modal.find('#admin-note').val();
            
            BPActivityApproval.processAction('approve', activityId, adminNote);
        },

        /**
         * Confirm rejection
         */
        confirmReject: function() {
            var modal = $('#activity-approval-modal');
            var activityId = modal.data('activity-id');
            var adminNote = modal.find('#admin-note').val();
            
            BPActivityApproval.processAction('reject', activityId, adminNote);
        },

        /**
         * Process approval/rejection action
         */
        processAction: function(action, activityId, adminNote) {
            var button = $('#confirm-' + action);
            var originalText = button.text();
            
            // Show loading state
            button.prop('disabled', true).text(bpActivityApproval.strings.processing);
            
            var data = {
                action: 'bp_' + action + '_activity',
                activity_id: activityId,
                admin_note: adminNote,
                nonce: bpActivityApproval.nonce
            };
            
            $.post(bpActivityApproval.ajaxUrl, data, function(response) {
                if (response.success) {
                    // Show success message
                    BPActivityApproval.showNotice(response.data.message, 'success');
                    
                    // Remove row from table or reload page
                    var row = $('input[value="' + activityId + '"]').closest('tr');
                    row.fadeOut(300, function() {
                        $(this).remove();
                        BPActivityApproval.updatePendingCount();
                    });
                    
                    BPActivityApproval.closeModal();
                } else {
                    BPActivityApproval.showNotice(response.data.message || bpActivityApproval.strings.error, 'error');
                }
            }).fail(function() {
                BPActivityApproval.showNotice(bpActivityApproval.strings.error, 'error');
            }).always(function() {
                // Reset button state
                button.prop('disabled', false).text(originalText);
            });
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.approval-modal').hide();
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut();
            }, 5000);
        },

        /**
         * Update pending count in admin bar
         */
        updatePendingCount: function() {
            var currentCount = parseInt($('#wp-admin-bar-bp-activity-approval .pending-count').text()) || 0;
            var newCount = Math.max(0, currentCount - 1);
            
            if (newCount === 0) {
                $('#wp-admin-bar-bp-activity-approval').fadeOut();
            } else {
                $('#wp-admin-bar-bp-activity-approval .pending-count').text(newCount);
                $('#wp-admin-bar-bp-activity-approval').removeClass('count-' + currentCount).addClass('count-' + newCount);
            }
        },

        /**
         * Validate form before submission
         */
        validateForm: function(e) {
            var form = $(this);
            var requiredFields = form.find('[required]');
            var isValid = true;
            
            requiredFields.each(function() {
                if (!$(this).val().trim()) {
                    $(this).addClass('error');
                    isValid = false;
                } else {
                    $(this).removeClass('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                BPActivityApproval.showNotice('Please fill in all required fields.', 'error');
            }
            
            return isValid;
        },

        /**
         * Get modal HTML
         */
        getModalHTML: function() {
            return `
                <div id="activity-approval-modal" class="approval-modal" style="display: none;">
                    <div class="modal-backdrop"></div>
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title"></h3>
                            <button type="button" class="modal-close">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="activity-preview">
                                <h4>Activity Content:</h4>
                                <div class="activity-content"></div>
                            </div>
                            <div class="admin-note-section">
                                <label for="admin-note">Admin Note (Optional):</label>
                                <textarea id="admin-note" rows="3" placeholder="Add a note about this decision..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="confirm-approve" class="button confirm-button" style="display: none;">Approve</button>
                            <button type="button" id="confirm-reject" class="button confirm-button" style="display: none;">Reject</button>
                            <button type="button" id="cancel-action" class="button">Cancel</button>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Get details modal HTML
         */
        getDetailsModalHTML: function() {
            return `
                <div id="activity-details-modal" class="approval-modal" style="display: none;">
                    <div class="modal-backdrop"></div>
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title">Activity Details</h3>
                            <button type="button" class="modal-close">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="activity-meta">
                                <p><strong>Author:</strong> <span class="activity-author"></span></p>
                                <p><strong>Date:</strong> <span class="activity-date"></span></p>
                                <p><strong>Type:</strong> <span class="activity-type"></span></p>
                            </div>
                            <div class="activity-full-content"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="button modal-close">Close</button>
                        </div>
                    </div>
                </div>
            `;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        BPActivityApproval.init();
    });

    // Handle modal backdrop clicks
    $(document).on('click', '.modal-backdrop', function() {
        BPActivityApproval.closeModal();
    });

    // Handle escape key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) { // Escape key
            BPActivityApproval.closeModal();
        }
    });

})(jQuery);