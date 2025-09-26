<?php
/**
 * Validator class for BuddyPress Activity Approval System
 *
 * @package BPActivityApproval
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Validator class for activity data validation
 *
 * @since 1.0.0
 */
class BP_Activity_Approval_Validator {

    /**
     * Validation rules
     *
     * @var array
     * @since 1.0.0
     */
    private $validation_rules;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_validation_rules();
    }

    /**
     * Initialize validation rules
     *
     * @since 1.0.0
     */
    private function init_validation_rules() {
        $settings = get_option( 'bp_activity_approval_settings', array() );
        
        $this->validation_rules = array(
            'content_length' => array(
                'min' => isset( $settings['min_content_length'] ) ? intval( $settings['min_content_length'] ) : 10,
                'max' => isset( $settings['max_content_length'] ) ? intval( $settings['max_content_length'] ) : 5000,
            ),
            'blocked_words' => isset( $settings['blocked_words'] ) ? array_map( 'trim', explode( ',', $settings['blocked_words'] ) ) : array(),
            'required_fields' => isset( $settings['required_fields'] ) ? $settings['required_fields'] : array( 'content' ),
            'allowed_html' => isset( $settings['allowed_html'] ) ? $settings['allowed_html'] : array(
                'a' => array( 'href' => array(), 'title' => array() ),
                'br' => array(),
                'em' => array(),
                'strong' => array(),
                'p' => array(),
            ),
        );

        // Apply filters to allow customization
        $this->validation_rules = apply_filters( 'bp_activity_approval_validation_rules', $this->validation_rules );
    }

    /**
     * Validate activity data
     *
     * @param BP_Activity_Activity $activity Activity object
     * @return bool|WP_Error True if valid, WP_Error if invalid
     * @since 1.0.0
     */
    public function validate_activity( $activity ) {
        $errors = new WP_Error();

        // Validate required fields
        $this->validate_required_fields( $activity, $errors );

        // Validate content length
        $this->validate_content_length( $activity, $errors );

        // Validate blocked words
        $this->validate_blocked_words( $activity, $errors );

        // Validate HTML content
        $this->validate_html_content( $activity, $errors );

        // Validate user permissions
        $this->validate_user_permissions( $activity, $errors );

        // Validate activity type
        $this->validate_activity_type( $activity, $errors );

        // Custom validation hook
        do_action( 'bp_activity_approval_custom_validation', $activity, $errors );

        // Return errors if any, otherwise true
        return $errors->has_errors() ? $errors : true;
    }

    /**
     * Validate required fields
     *
     * @param BP_Activity_Activity $activity Activity object
     * @param WP_Error $errors Error object
     * @since 1.0.0
     */
    private function validate_required_fields( $activity, $errors ) {
        foreach ( $this->validation_rules['required_fields'] as $field ) {
            if ( empty( $activity->$field ) ) {
                $errors->add(
                    'missing_required_field',
                    sprintf(
                        __( 'Field %s wajib diisi.', 'bp-activity-approval' ),
                        $field
                    )
                );
            }
        }
    }

    /**
     * Validate content length
     *
     * @param BP_Activity_Activity $activity Activity object
     * @param WP_Error $errors Error object
     * @since 1.0.0
     */
    private function validate_content_length( $activity, $errors ) {
        if ( empty( $activity->content ) ) {
            return;
        }

        $content_length = mb_strlen( strip_tags( $activity->content ) );
        
        if ( $content_length < $this->validation_rules['content_length']['min'] ) {
            $errors->add(
                'content_too_short',
                sprintf(
                    __( 'Konten terlalu pendek. Minimal %d karakter diperlukan.', 'bp-activity-approval' ),
                    $this->validation_rules['content_length']['min']
                )
            );
        }

        if ( $content_length > $this->validation_rules['content_length']['max'] ) {
            $errors->add(
                'content_too_long',
                sprintf(
                    __( 'Konten terlalu panjang. Maksimal %d karakter diizinkan.', 'bp-activity-approval' ),
                    $this->validation_rules['content_length']['max']
                )
            );
        }
    }

    /**
     * Validate blocked words
     *
     * @param BP_Activity_Activity $activity Activity object
     * @param WP_Error $errors Error object
     * @since 1.0.0
     */
    private function validate_blocked_words( $activity, $errors ) {
        if ( empty( $this->validation_rules['blocked_words'] ) || empty( $activity->content ) ) {
            return;
        }

        $content_lower = mb_strtolower( $activity->content );
        
        foreach ( $this->validation_rules['blocked_words'] as $blocked_word ) {
            if ( empty( $blocked_word ) ) {
                continue;
            }
            
            $blocked_word_lower = mb_strtolower( trim( $blocked_word ) );
            
            if ( strpos( $content_lower, $blocked_word_lower ) !== false ) {
                $errors->add(
                    'blocked_word_found',
                    __( 'Konten mengandung kata yang tidak diizinkan.', 'bp-activity-approval' )
                );
                break;
            }
        }
    }

    /**
     * Validate HTML content
     *
     * @param BP_Activity_Activity $activity Activity object
     * @param WP_Error $errors Error object
     * @since 1.0.0
     */
    private function validate_html_content( $activity, $errors ) {
        if ( empty( $activity->content ) ) {
            return;
        }

        // Check if content contains HTML
        if ( $activity->content !== strip_tags( $activity->content ) ) {
            // Sanitize HTML content
            $sanitized_content = wp_kses( $activity->content, $this->validation_rules['allowed_html'] );
            
            // If sanitized content is different, it means there were disallowed HTML tags
            if ( $sanitized_content !== $activity->content ) {
                $errors->add(
                    'invalid_html',
                    __( 'Konten mengandung HTML tag yang tidak diizinkan.', 'bp-activity-approval' )
                );
            }
        }
    }

    /**
     * Validate user permissions
     *
     * @param BP_Activity_Activity $activity Activity object
     * @param WP_Error $errors Error object
     * @since 1.0.0
     */
    private function validate_user_permissions( $activity, $errors ) {
        // Check if user exists and is active
        $user = get_user_by( 'id', $activity->user_id );
        
        if ( ! $user ) {
            $errors->add(
                'invalid_user',
                __( 'User tidak valid.', 'bp-activity-approval' )
            );
            return;
        }

        // Check if user is suspended or banned
        if ( function_exists( 'bp_is_user_suspended' ) && bp_is_user_suspended( $activity->user_id ) ) {
            $errors->add(
                'user_suspended',
                __( 'User sedang dalam status suspended.', 'bp-activity-approval' )
            );
        }

        // Check user capabilities for specific activity types
        $this->validate_user_activity_permissions( $activity, $errors );
    }

    /**
     * Validate user permissions for specific activity types
     *
     * @param BP_Activity_Activity $activity Activity object
     * @param WP_Error $errors Error object
     * @since 1.0.0
     */
    private function validate_user_activity_permissions( $activity, $errors ) {
        $user_can_post = true;

        switch ( $activity->type ) {
            case 'activity_update':
                $user_can_post = bp_activity_do_mentions() || current_user_can( 'bp_moderate' );
                break;
                
            case 'activity_comment':
                $user_can_post = bp_activity_can_comment();
                break;
                
            case 'new_blog_post':
                $user_can_post = current_user_can( 'publish_posts' );
                break;
                
            default:
                $user_can_post = apply_filters( 'bp_activity_approval_user_can_post_' . $activity->type, true, $activity );
                break;
        }

        if ( ! $user_can_post ) {
            $errors->add(
                'insufficient_permissions',
                __( 'User tidak memiliki izin untuk membuat activity jenis ini.', 'bp-activity-approval' )
            );
        }
    }

    /**
     * Validate activity type
     *
     * @param BP_Activity_Activity $activity Activity object
     * @param WP_Error $errors Error object
     * @since 1.0.0
     */
    private function validate_activity_type( $activity, $errors ) {
        // Get allowed activity types
        $allowed_types = bp_activity_get_types();
        
        if ( ! array_key_exists( $activity->type, $allowed_types ) ) {
            $errors->add(
                'invalid_activity_type',
                __( 'Jenis activity tidak valid.', 'bp-activity-approval' )
            );
        }

        // Check if this activity type requires approval
        $settings = get_option( 'bp_activity_approval_settings', array() );
        $require_approval_for = isset( $settings['require_approval_for'] ) ? $settings['require_approval_for'] : array();
        
        if ( ! in_array( $activity->type, $require_approval_for, true ) ) {
            // This activity type doesn't require approval, but we still validate it
            return;
        }
    }

    /**
     * Sanitize activity content
     *
     * @param string $content Activity content
     * @return string Sanitized content
     * @since 1.0.0
     */
    public function sanitize_content( $content ) {
        // Remove excessive whitespace
        $content = preg_replace( '/\s+/', ' ', $content );
        
        // Sanitize HTML
        $content = wp_kses( $content, $this->validation_rules['allowed_html'] );
        
        // Trim content
        $content = trim( $content );
        
        return apply_filters( 'bp_activity_approval_sanitize_content', $content );
    }

    /**
     * Check if content contains spam patterns
     *
     * @param string $content Content to check
     * @return bool True if spam detected
     * @since 1.0.0
     */
    public function is_spam_content( $content ) {
        // Basic spam detection patterns
        $spam_patterns = array(
            '/\b(?:viagra|cialis|casino|poker|lottery|winner|congratulations)\b/i',
            '/\b(?:click here|buy now|limited time|act now|free money)\b/i',
            '/(?:http[s]?:\/\/[^\s]+){3,}/', // Multiple URLs
            '/(.)\1{10,}/', // Repeated characters
        );

        foreach ( $spam_patterns as $pattern ) {
            if ( preg_match( $pattern, $content ) ) {
                return true;
            }
        }

        // Check for excessive capitalization
        $caps_ratio = $this->calculate_caps_ratio( $content );
        if ( $caps_ratio > 0.7 ) {
            return true;
        }

        return apply_filters( 'bp_activity_approval_is_spam_content', false, $content );
    }

    /**
     * Calculate capitalization ratio
     *
     * @param string $content Content to analyze
     * @return float Capitalization ratio
     * @since 1.0.0
     */
    private function calculate_caps_ratio( $content ) {
        $letters = preg_replace( '/[^a-zA-Z]/', '', $content );
        $total_letters = mb_strlen( $letters );
        
        if ( $total_letters === 0 ) {
            return 0;
        }
        
        $caps_letters = preg_replace( '/[^A-Z]/', '', $letters );
        $caps_count = mb_strlen( $caps_letters );
        
        return $caps_count / $total_letters;
    }

    /**
     * Get validation rules
     *
     * @return array Validation rules
     * @since 1.0.0
     */
    public function get_validation_rules() {
        return $this->validation_rules;
    }

    /**
     * Update validation rules
     *
     * @param array $rules New validation rules
     * @since 1.0.0
     */
    public function update_validation_rules( $rules ) {
        $this->validation_rules = wp_parse_args( $rules, $this->validation_rules );
    }
}