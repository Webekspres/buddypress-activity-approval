<?php
/**
 * List table for pending activities
 *
 * @package BPActivityApproval
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table class for pending activities
 *
 * @since 1.0.0
 */
class BP_Activity_Approval_List_Table extends WP_List_Table {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => 'activity',
            'plural'   => 'activities',
            'ajax'     => false,
        ) );
    }

    /**
     * Get table columns
     *
     * @return array
     * @since 1.0.0
     */
    public function get_columns() {
        return array(
            'cb'          => '<input type="checkbox" />',
            'content'     => __( 'Content', 'bp-activity-approval' ),
            'author'      => __( 'Author', 'bp-activity-approval' ),
            'type'        => __( 'Type', 'bp-activity-approval' ),
            'date'        => __( 'Date', 'bp-activity-approval' ),
            'status'      => __( 'Status', 'bp-activity-approval' ),
            'actions'     => __( 'Actions', 'bp-activity-approval' ),
        );
    }

    /**
     * Get sortable columns
     *
     * @return array
     * @since 1.0.0
     */
    public function get_sortable_columns() {
        return array(
            'date'   => array( 'created_at', true ),
            'author' => array( 'user_id', false ),
            'type'   => array( 'type', false ),
        );
    }

    /**
     * Get bulk actions
     *
     * @return array
     * @since 1.0.0
     */
    public function get_bulk_actions() {
        return array(
            'approve' => __( 'Approve', 'bp-activity-approval' ),
            'reject'  => __( 'Reject', 'bp-activity-approval' ),
        );
    }

    /**
     * Prepare table items
     *
     * @since 1.0.0
     */
    public function prepare_items() {
        $per_page = $this->get_items_per_page( 'activities_per_page', 20 );
        $current_page = $this->get_pagenum();

        // Get search query
        $search = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';

        // Get filter parameters
        $filters = array(
            'type'   => isset( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : '',
            'author' => isset( $_REQUEST['author'] ) ? intval( $_REQUEST['author'] ) : 0,
            'date'   => isset( $_REQUEST['date'] ) ? sanitize_text_field( $_REQUEST['date'] ) : '',
        );

        // Get sorting parameters
        $orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'created_at';
        $order = isset( $_REQUEST['order'] ) ? sanitize_text_field( $_REQUEST['order'] ) : 'DESC';

        // Get data
        $args = array(
            'per_page' => $per_page,
            'page'     => $current_page,
            'search'   => $search,
            'filters'  => $filters,
            'orderby'  => $orderby,
            'order'    => $order,
        );

        $data = $this->get_activities_data( $args );
        $total_items = $this->get_total_items( $args );

        // Set pagination
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );

        // Set table data
        $this->items = $data;

        // Set column headers
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );
    }

    /**
     * Get activities data
     *
     * @param array $args Query arguments
     * @return array
     * @since 1.0.0
     */
    private function get_activities_data( $args ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bp_activity_approval_log';
        $bp_table = buddypress()->activity->table_name;
        $users_table = $wpdb->users;

        // Build WHERE clause
        $where_conditions = array( "al.status = 'pending'" );

        // Search condition
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where_conditions[] = $wpdb->prepare( "(a.content LIKE %s OR u.display_name LIKE %s)", $search, $search );
        }

        // Filter conditions
        if ( ! empty( $args['filters']['type'] ) ) {
            $where_conditions[] = $wpdb->prepare( "a.type = %s", $args['filters']['type'] );
        }

        if ( ! empty( $args['filters']['author'] ) ) {
            $where_conditions[] = $wpdb->prepare( "a.user_id = %d", $args['filters']['author'] );
        }

        if ( ! empty( $args['filters']['date'] ) ) {
            $date_filter = $args['filters']['date'];
            switch ( $date_filter ) {
                case 'today':
                    $where_conditions[] = "DATE(al.created_at) = CURDATE()";
                    break;
                case 'week':
                    $where_conditions[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                    break;
                case 'month':
                    $where_conditions[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                    break;
            }
        }

        $where_clause = implode( ' AND ', $where_conditions );

        // Build ORDER BY clause
        $allowed_orderby = array( 'created_at', 'user_id', 'type' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        if ( $orderby === 'created_at' ) {
            $orderby = 'al.created_at';
        } elseif ( $orderby === 'user_id' ) {
            $orderby = 'u.display_name';
        } elseif ( $orderby === 'type' ) {
            $orderby = 'a.type';
        }

        // Build LIMIT clause
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        $limit = $wpdb->prepare( "LIMIT %d OFFSET %d", $args['per_page'], $offset );

        // Execute query
        $query = "
            SELECT al.*, a.content, a.type, a.user_id, a.date_recorded, u.display_name, u.user_email
            FROM {$table_name} al
            LEFT JOIN {$bp_table} a ON al.activity_id = a.id
            LEFT JOIN {$users_table} u ON a.user_id = u.ID
            WHERE {$where_clause}
            ORDER BY {$orderby} {$order}
            {$limit}
        ";

        return $wpdb->get_results( $query );
    }

    /**
     * Get total items count
     *
     * @param array $args Query arguments
     * @return int
     * @since 1.0.0
     */
    private function get_total_items( $args ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bp_activity_approval_log';
        $bp_table = buddypress()->activity->table_name;
        $users_table = $wpdb->users;

        // Build WHERE clause (same as in get_activities_data)
        $where_conditions = array( "al.status = 'pending'" );

        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where_conditions[] = $wpdb->prepare( "(a.content LIKE %s OR u.display_name LIKE %s)", $search, $search );
        }

        if ( ! empty( $args['filters']['type'] ) ) {
            $where_conditions[] = $wpdb->prepare( "a.type = %s", $args['filters']['type'] );
        }

        if ( ! empty( $args['filters']['author'] ) ) {
            $where_conditions[] = $wpdb->prepare( "a.user_id = %d", $args['filters']['author'] );
        }

        if ( ! empty( $args['filters']['date'] ) ) {
            $date_filter = $args['filters']['date'];
            switch ( $date_filter ) {
                case 'today':
                    $where_conditions[] = "DATE(al.created_at) = CURDATE()";
                    break;
                case 'week':
                    $where_conditions[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                    break;
                case 'month':
                    $where_conditions[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                    break;
            }
        }

        $where_clause = implode( ' AND ', $where_conditions );

        $query = "
            SELECT COUNT(*)
            FROM {$table_name} al
            LEFT JOIN {$bp_table} a ON al.activity_id = a.id
            LEFT JOIN {$users_table} u ON a.user_id = u.ID
            WHERE {$where_clause}
        ";

        return intval( $wpdb->get_var( $query ) );
    }

    /**
     * Render checkbox column
     *
     * @param object $item Row item
     * @return string
     * @since 1.0.0
     */
    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="activity[]" value="%d" />', $item->activity_id );
    }

    /**
     * Render content column
     *
     * @param object $item Row item
     * @return string
     * @since 1.0.0
     */
    public function column_content( $item ) {
        $content = wp_trim_words( strip_tags( $item->content ), 20, '...' );
        $content = esc_html( $content );

        // Add row actions
        $actions = array(
            'approve' => sprintf(
                '<a href="#" class="approve-activity" data-activity-id="%d">%s</a>',
                $item->activity_id,
                __( 'Approve', 'bp-activity-approval' )
            ),
            'reject' => sprintf(
                '<a href="#" class="reject-activity" data-activity-id="%d">%s</a>',
                $item->activity_id,
                __( 'Reject', 'bp-activity-approval' )
            ),
            'view' => sprintf(
                '<a href="#" class="view-activity" data-activity-id="%d">%s</a>',
                $item->activity_id,
                __( 'View Full', 'bp-activity-approval' )
            ),
        );

        return $content . $this->row_actions( $actions );
    }

    /**
     * Render author column
     *
     * @param object $item Row item
     * @return string
     * @since 1.0.0
     */
    public function column_author( $item ) {
        $user_link = get_edit_user_link( $item->user_id );
        return sprintf(
            '<a href="%s">%s</a><br><small>%s</small>',
            esc_url( $user_link ),
            esc_html( $item->display_name ),
            esc_html( $item->user_email )
        );
    }

    /**
     * Render type column
     *
     * @param object $item Row item
     * @return string
     * @since 1.0.0
     */
    public function column_type( $item ) {
        $activity_types = bp_activity_get_types();
        $type_label = isset( $activity_types[ $item->type ] ) ? $activity_types[ $item->type ] : $item->type;
        
        return sprintf(
            '<span class="activity-type activity-type-%s">%s</span>',
            esc_attr( $item->type ),
            esc_html( $type_label )
        );
    }

    /**
     * Render date column
     *
     * @param object $item Row item
     * @return string
     * @since 1.0.0
     */
    public function column_date( $item ) {
        $date = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->created_at );
        $time_diff = human_time_diff( strtotime( $item->created_at ), current_time( 'timestamp' ) );
        
        return sprintf(
            '<span title="%s">%s ago</span>',
            esc_attr( $date ),
            esc_html( $time_diff )
        );
    }

    /**
     * Render status column
     *
     * @param object $item Row item
     * @return string
     * @since 1.0.0
     */
    public function column_status( $item ) {
        $status_labels = array(
            'pending'  => __( 'Pending', 'bp-activity-approval' ),
            'approved' => __( 'Approved', 'bp-activity-approval' ),
            'rejected' => __( 'Rejected', 'bp-activity-approval' ),
        );

        $status_label = isset( $status_labels[ $item->status ] ) ? $status_labels[ $item->status ] : $item->status;
        
        return sprintf(
            '<span class="status status-%s">%s</span>',
            esc_attr( $item->status ),
            esc_html( $status_label )
        );
    }

    /**
     * Render actions column
     *
     * @param object $item Row item
     * @return string
     * @since 1.0.0
     */
    public function column_actions( $item ) {
        $actions = array();

        $actions[] = sprintf(
            '<button type="button" class="button button-small approve-activity" data-activity-id="%d">%s</button>',
            $item->activity_id,
            __( 'Approve', 'bp-activity-approval' )
        );

        $actions[] = sprintf(
            '<button type="button" class="button button-small reject-activity" data-activity-id="%d">%s</button>',
            $item->activity_id,
            __( 'Reject', 'bp-activity-approval' )
        );

        return implode( ' ', $actions );
    }

    /**
     * Display extra table navigation
     *
     * @param string $which Position of the navigation
     * @since 1.0.0
     */
    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) {
            return;
        }
        ?>
        <div class="alignleft actions">
            <?php $this->render_filters(); ?>
        </div>
        <?php
    }

    /**
     * Render filter dropdowns
     *
     * @since 1.0.0
     */
    private function render_filters() {
        // Activity type filter
        $activity_types = bp_activity_get_types();
        $selected_type = isset( $_REQUEST['type'] ) ? $_REQUEST['type'] : '';
        ?>
        <select name="type">
            <option value=""><?php esc_html_e( 'All Types', 'bp-activity-approval' ); ?></option>
            <?php foreach ( $activity_types as $type => $label ) : ?>
                <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $selected_type, $type ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php
        // Date filter
        $selected_date = isset( $_REQUEST['date'] ) ? $_REQUEST['date'] : '';
        ?>
        <select name="date">
            <option value=""><?php esc_html_e( 'All Dates', 'bp-activity-approval' ); ?></option>
            <option value="today" <?php selected( $selected_date, 'today' ); ?>><?php esc_html_e( 'Today', 'bp-activity-approval' ); ?></option>
            <option value="week" <?php selected( $selected_date, 'week' ); ?>><?php esc_html_e( 'This Week', 'bp-activity-approval' ); ?></option>
            <option value="month" <?php selected( $selected_date, 'month' ); ?>><?php esc_html_e( 'This Month', 'bp-activity-approval' ); ?></option>
        </select>

        <?php submit_button( __( 'Filter', 'bp-activity-approval' ), 'secondary', 'filter_action', false ); ?>
        <?php
    }

    /**
     * Display when no items found
     *
     * @since 1.0.0
     */
    public function no_items() {
        esc_html_e( 'Tidak ada activity yang menunggu persetujuan.', 'bp-activity-approval' );
    }
}