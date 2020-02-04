<?php
/**
 * Rest API example class
 */


class DT_Dispatcher_Endpoints
{
    public $permissions = [ 'list_users', 'manage_dt' ];

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }

    public function has_permission(){
        $pass = false;
        foreach ( $this->permissions as $permission ){
            if ( current_user_can( $permission ) ){
                $pass = true;
            }
        }
        return $pass;
    }

    public function add_api_routes() {


        $namespace = 'dispatcher-tools/v1';

        register_rest_route(
            $namespace, '/user', [
                [
                    'methods'  => "GET",
                    'callback' => [ $this, 'get_user_endpoint' ],
                ],
            ]
        );
        register_rest_route(
            $namespace, '/user', [
                [
                    'methods'  => "POST",
                    'callback' => [ $this, 'update_settings_on_user' ],
                ],
            ]
        );
        register_rest_route(
            $namespace, '/get_users', [
                [
                    'methods'  => "GET",
                    'callback' => [ $this, 'get_users_endpoints' ],
                ],
            ]
        );
    }

    public function get_dt_user( $user_id ) {
        global $wpdb;
        $user = get_user_by( "ID", $user_id );

        $user_status = get_user_option( 'user_status', $user->ID );
        $workload_status = get_user_option( 'workload_status', $user->ID );

        $location_grids = DT_Mapping_Module::instance()->get_post_locations( dt_get_associated_user_id( $user->ID, 'user' ) );
        $locations = [];
        foreach ( $location_grids as $l ){
            $locations[] = [
                "grid_id" => $l["grid_id"],
                "name" => $l["name"]
            ];
        }

        $dates_unavailable = get_user_option( "user_dates_unavailable", $user->ID );
        foreach ( $dates_unavailable as &$range ) {
            $range["start_date"] = dt_format_date( $range["start_date"] );
            $range["end_date"] = dt_format_date( $range["end_date"] );
        }
        $user_activity = $wpdb->get_results( $wpdb->prepare("
            SELECT * from $wpdb->dt_activity_log
            WHERE user_id = %s
            AND action IN ( 'comment', 'field_update', 'connected_to', 'logged_in', 'created', 'disconnected_from', 'decline', 'assignment_decline' )
            ORDER BY `hist_time` DESC
            LIMIT 100
        ", $user->ID));
        $post_settings = apply_filters( "dt_get_post_type_settings", [], "contacts" );
        foreach ( $user_activity as $a ){
            if ( $a->action === 'field_update' || $a->action === 'connected to' || $a->action === 'disconnected from' ){
                if ( $a->object_type === "contacts" ){
                    $a->object_note = "Updated contact " . $a->object_name;
                }
                if ( $a->object_type === "groups" ){
                    $a->object_note = "Updated group " . $a->object_name;
                }
            }
            if ( $a->action == 'comment' ){
                if ( $a->meta_key === "contacts" ){
                    $a->object_note = "Commented on contact " . $a->object_name;
                }
                if ( $a->meta_key === "groups" ){
                    $a->object_note = "Commented on group " . $a->object_name;
                }
            }
            if ( $a->action == 'created' ){
                if ( $a->object_type === "contacts" ){
                    $a->object_note = "Created contact " . $a->object_name;
                }
                if ( $a->object_type === "groups" ){
                    $a->object_note = "Created group " . $a->object_name;
                }
            }
            if ( $a->action === "logged_in" ){
                $a->object_note = "Logged In";
            }
            if ( $a->action === 'assignment_decline' ){
                $a->object_note = "Declined assignment on " . $a->object_name;
            }
        }

        $month_start = strtotime( date( 'Y-m-01' ) );
        $last_month_start = strtotime( 'first day of last month' );
        $this_year = strtotime( "first day of january this year" );
        //number of assigned contacts
        $assigned_counts = $wpdb->get_results( $wpdb->prepare( "
            SELECT 
            COUNT( CASE WHEN date_assigned.hist_time >= %d THEN 1 END ) as this_month,
            COUNT( CASE WHEN date_assigned.hist_time >= %d AND date_assigned.hist_time < %d THEN 1 END ) as last_month,
            COUNT( CASE WHEN date_assigned.hist_time >= %d THEN 1 END ) as this_year,
            COUNT( date_assigned.histid ) as all_time
            FROM $wpdb->dt_activity_log as date_assigned
            INNER JOIN $wpdb->postmeta as type ON ( date_assigned.object_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
            WHERE date_assigned.meta_key = 'assigned_to' 
                AND date_assigned.object_type = 'contacts' 
                AND date_assigned.meta_value = %s
        ", $month_start, $last_month_start, $month_start, $this_year, 'user-' . $user->ID ), ARRAY_A );

        $to_accept = Disciple_Tools_Contacts::search_viewable_contacts( [
            'overall_status' => [ 'assigned' ],
            'assigned_to'    => [ $user->ID ]
        ] );
        $update_needed = Disciple_Tools_Contacts::search_viewable_contacts( [
            'requires_update' => [ "true" ],
            'assigned_to'     => [ $user->ID ],
            'overall_status' => [ '-closed' ],
            'sort' => 'last_modified'
        ] );
        if ( sizeof( $update_needed["contacts"] ) > 5 ) {
            $update_needed["contacts"] = array_slice( $update_needed["contacts"], 0, 5 );
        }
        if ( sizeof( $to_accept["contacts"] ) > 10 ) {
            $to_accept["contacts"] = array_slice( $to_accept["contacts"], 0, 10 );
        }
        foreach ( $update_needed["contacts"] as &$contact ){
            $now = time();
            $last_modified = get_post_meta( $contact->ID, "last_modified", true );
            $days_different = (int) round( ( $now - (int) $last_modified ) / ( 60 * 60 * 24 ) );
            $contact->last_modified_msg = esc_attr( sprintf( __( '%s days since last update', 'disciple_tools' ), $days_different ), 'disciple_tools' );
        }
        $my_active_contacts = self::count_active_contacts();
        $notification_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT count(id)
            FROM `$wpdb->dt_notifications`
            WHERE
                user_id = %d
                AND is_new = '1'",
            $user->ID
        ) );

        $days_active_results = $wpdb->get_results( $wpdb->prepare( "
            SELECT FROM_UNIXTIME(`hist_time`, '%%Y-%%m-%%d') as day,
            count(histid) as activity_count
            FROM $wpdb->dt_activity_log 
            WHERE user_id = %s 
            group by day 
            ORDER BY `day` ASC",
            $user->ID
        ), ARRAY_A);
        $days_active = [];
        foreach ( $days_active_results as $a ){
            $days_active[$a["day"]] = $a;
        }
        $first = isset( $days_active_results[0]['day'] ) ? strtotime( $days_active_results[0]['day'] ) : time();
        $first_week_start = date( 'Y-m-d', strtotime( '-' . date( 'w', $first )  . ' days', $first ) );
        $current = strtotime( $first_week_start );
        $daily_activity = [];
        while ( $current < time() ) {

            $activity = $days_active[date( 'Y-m-d', $current )]["activity_count"] ?? 0;

            $daily_activity[] = [
                "day" => dt_format_date( $current ),
                "weekday" => date( 'l', $current ),
                "week_start" => date( 'Y-m-d', strtotime( '-' . date( 'w', $current ) . ' days', $current ) ),
                "activity_count" => $activity,
                "activity" => $activity > 0 ? 1 : 0
            ];


            $current += 24 * 60 * 60;
        }

        $times = self::times( $user->ID );

        $contact_statuses = $this->get_contact_statuses( $user->ID );

        $user_response = [
            "display_name" => $user->display_name,
            "user_status" => $user_status,
            "workload_status" => $workload_status,
            "locations" => $locations,
            "dates_unavailable" => $dates_unavailable ? $dates_unavailable : [],
            "user_activity" => $user_activity,
            "active_contacts" => $my_active_contacts,
            "update_needed" => $update_needed,
            "unread_notifications" => $notification_count ? $notification_count : 0,
            "needs_accepted" => $to_accept,
            "days_active" => $daily_activity,
            "times" => $times,
            "assigned_counts" => isset( $assigned_counts[0] ) ? $assigned_counts[0] : [],
            "contact_statuses" => $contact_statuses
        ];

        if ( current_user_can( "promote_users" ) ){
            $user_response["roles"] = $user->roles;
        }

        return $user_response;


    }

    public function get_user_endpoint( WP_REST_Request $request ) {
        if ( !$this->has_permission() ) {
            return new WP_Error( "get_user", "Missing Permissions", [ 'status' => 401 ] );
        }

        $params = $request->get_params();
        if ( !isset( $params["user"] ) ) {
            return new WP_Error( "get_user", "Missing user id", [ 'status' => 400 ] );
        }
        return $this->get_dt_user( $params["user"] );
    }

    private static function count_active_contacts(){
        global $wpdb;
        $my_active_contacts = $wpdb->get_var( $wpdb->prepare( "
            SELECT count(a.ID)
            FROM $wpdb->posts as a
            INNER JOIN $wpdb->postmeta as assigned_to
            ON a.ID=assigned_to.post_id
              AND assigned_to.meta_key = 'assigned_to'
              AND assigned_to.meta_value = CONCAT( 'user-', %s )
            JOIN $wpdb->postmeta as b
              ON a.ID=b.post_id
                 AND b.meta_key = 'overall_status'
                     AND b.meta_value = 'active'
            WHERE a.post_status = 'publish'
            AND post_type = 'contacts'
            AND a.ID NOT IN (
                SELECT post_id FROM $wpdb->postmeta
                WHERE meta_key = 'type' AND meta_value = 'user'
                GROUP BY post_id
            )
        ", get_current_user_id() ) );
        return $my_active_contacts;
    }

    public function get_users_endpoints( WP_REST_Request $request ){
        if ( !$this->has_permission() ){
            return new WP_Error( "get_user", "Missing Permissions", [ 'status' => 401 ] );
        }
        $params = $request->get_params();
        $refresh = isset( $params["refresh"] ) && $params["refresh"] = "1";
        return self::get_users( $refresh );
    }

    public static function get_users( $refresh = false ) {
        $users = [];
        if ( !$refresh && get_transient( 'dispatcher_user_data' ) ) {
            $users = maybe_unserialize( get_transient( 'dispatcher_user_data' ) );
        }

        if ( empty( $users ) ) {
            global $wpdb;
            $user_data = $wpdb->get_results( $wpdb->prepare( "
                SELECT users.ID,
                    users.display_name,
                    count(pm.post_id) as number_assigned_to,
                    count(active.post_id) as number_active,
                    count(new_assigned.post_id) as number_new_assigned,
                    count(update_needed.post_id) as number_update,
                    um.meta_value as roles
                from $wpdb->users as users
                INNER JOIN $wpdb->usermeta as um on ( um.user_id = users.ID AND um.meta_key = %s )
                LEFT JOIN $wpdb->postmeta as pm on ( pm.meta_key = 'assigned_to' and pm.meta_value = CONCAT( 'user-', users.ID ) AND pm.post_id NOT IN (
                    SELECT post_id
                    FROM $wpdb->postmeta
                    WHERE meta_key = 'type' AND meta_value = 'user'
                    GROUP BY post_id
                ))
                LEFT JOIN $wpdb->posts as p on ( p.ID = pm.post_id and p.post_type = 'contacts' )
                LEFT JOIN $wpdb->postmeta as active on (active.post_id = p.ID and active.meta_key = 'overall_status' and active.meta_value = 'active' )
                LEFT JOIN $wpdb->postmeta as new_assigned on (new_assigned.post_id = p.ID and new_assigned.meta_key = 'overall_status' and new_assigned.meta_value = 'assigned' )
                LEFT JOIN $wpdb->postmeta as update_needed on (update_needed.post_id = p.ID and update_needed.meta_key = 'requires_update' and update_needed.meta_value = '1' )
                GROUP by users.ID, um.meta_value
            ", $wpdb->prefix . 'capabilities' ),
            ARRAY_A );

            $users = [];
            foreach ( $user_data as $user ) {
                $users[ $user["ID"] ] = $user;
            }
            $user_statuses = $wpdb->get_results( $wpdb->prepare( "
                SELECT * FROM $wpdb->usermeta
                WHERE meta_key = %s
            ", $wpdb->prefix . 'user_status' ), ARRAY_A );
            foreach ( $user_statuses as $meta_row ){
                if ( isset( $users[ $meta_row["user_id"] ] ) ) {
                    $users[$meta_row["user_id"]]["user_status"] = $meta_row["meta_value"];
                }
            }
            $user_workloads = $wpdb->get_results( $wpdb->prepare( "
                SELECT * FROM $wpdb->usermeta
                WHERE meta_key = %s
            ", $wpdb->prefix . 'workload_status' ), ARRAY_A );
            foreach ( $user_workloads as $meta_row ){
                if ( isset( $users[ $meta_row["user_id"] ] ) ) {
                    $users[$meta_row["user_id"]]["workload_status"] = $meta_row["meta_value"];
                }
            }


            $last_activity = $wpdb->get_results( "
                SELECT user_id,
                    MAX(hist_time) as last_activity
                from $wpdb->dt_activity_log as log
                GROUP by user_id",
            ARRAY_A);
            foreach ( $last_activity as $a ){
                if ( isset( $users[ $a["user_id"] ] ) ) {
                    $users[$a["user_id"]]["last_activity"] = $a["last_activity"];
                }
            }

            if ( !empty( $users ) ){
                set_transient( 'dispatcher_user_data', maybe_serialize( $users ), 60 * 60 * 24 );
            }
        }
        if ( current_user_can( "list_users" ) ) {
            return $users;
        } else {
            $multipliers = [];
            foreach ( $users as $user_id => $user ) {
                $user_roles = maybe_unserialize( $user["roles"] );
                if ( in_array( "multiplier", $user_roles ) ){
                    unset( $user["roles"] );
                    $multipliers[$user_id] = $user;
                }
            }
            return $multipliers;
        }

    }

    public function update_settings_on_user( WP_REST_Request $request ){
        if ( !$this->has_permission() ){
            return new WP_Error( "update user", "Missing Permissions", [ 'status' => 401 ] );
        }

        $get_params = $request->get_params();
        $body = $request->get_json_params();

        if ( isset( $get_params["user"] ) ) {
            delete_transient( 'dispatcher_user_data' );
            $user = get_user_by( "ID", $get_params["user"] );
            if ( !$user ){
                return new WP_Error( "user_id", "User does not exist", [ 'status' => 400 ] );
            }
            if ( empty( $user->caps ) ) {
                return new WP_Error( "user_id", "Cannot update this user", [ 'status' => 400 ] );
            }
            if ( !empty( $body["user_status"] ) ) {
                update_user_option( $user->ID, 'user_status', $body["user_status"] );
            }
            if ( !empty( $body["workload_status"] ) ) {
                update_user_option( $user->ID, 'workload_status', $body["workload_status"] );
            }
            if ( !empty( $body["add_location"] ) ){
                Disciple_Tools_Users::add_user_location( $body["add_location"], $user->ID );
            }
            if ( !empty( $body["remove_location"] ) ){
                Disciple_Tools_Users::delete_user_location( $body["remove_location"], $user->ID );
            }
            if ( !empty( $body["add_unavailability"] ) ){
                if ( !empty( $body["add_unavailability"]["start_date"] ) && !empty( $body["add_unavailability"]["end_date"] ) ) {
                    $dates_unavailable = get_user_option( "user_dates_unavailable", $user->ID );
                    if ( !$dates_unavailable ){
                        $dates_unavailable = [];
                    }
                    $max_id = 0;
                    foreach ( $dates_unavailable as $range ){
                        $max_id = max( $max_id, $range["id"] ?? 0 );
                    }

                    $dates_unavailable[] = [
                        "id" => $max_id + 1,
                        "start_date" => strtotime( $body["add_unavailability"]["start_date"] ),
                        "end_date" => strtotime( $body["add_unavailability"]["end_date"] ),
                    ];
                    update_user_option( $user->ID, "user_dates_unavailable", $dates_unavailable );
                    return $this->get_dt_user( $user->ID );
                }
            }
            if ( !empty( $body["remove_unavailability"] ) ) {
                $dates_unavailable = get_user_option( "user_dates_unavailable", $user->ID );
                foreach ( $dates_unavailable as $index => $range ) {
                    if ( $body["remove_unavailability"] === $range["id"] ){
                        unset( $dates_unavailable[$index] );
                    }
                }
                $dates_unavailable = array_values( $dates_unavailable );
                update_user_option( $user->ID, "user_dates_unavailable", $dates_unavailable );
                return $dates_unavailable;
            }
            if ( isset( $body["save_roles"] ) ){
                // If the current user can't promote users or edit this particular user, bail.
                if ( !current_user_can( 'promote_users' ) ) {
                    return false;
                }

                // Create a new user object.
                $u = new WP_User( $user->ID );

                // If we have an array of roles.
                if ( ! empty( $body['save_roles'] ) ) {

                    // Get the current user roles.
                    $old_roles = (array) $u->roles;

                    // Sanitize the posted roles.
                    $new_roles = array_map( 'dt_multi_role_sanitize_role', array_map( 'sanitize_text_field', wp_unslash( $body['save_roles'] ) ) );

                    // Loop through the posted roles.
                    foreach ( $new_roles as $new_role ) {

                        // If the user doesn't already have the role, add it.
                        if ( dt_multi_role_is_role_editable( $new_role ) && ! in_array( $new_role, (array) $user->roles ) ) {
                            if ( $new_role !== "administrator" ){
                                $u->add_role( $new_role );
                            }
                        }
                    }

                    // Loop through the current user roles.
                    foreach ( $old_roles as $old_role ) {

                        // If the role is editable and not in the new roles array, remove it.
                        if ( dt_multi_role_is_role_editable( $old_role ) && ! in_array( $old_role, $new_roles ) ) {
                            if ( $old_role !== "administrator" ) {
                                $u->remove_role( $old_role );
                            }
                        }
                    }

                    // If the posted roles are empty.
                } else {

                    // Loop through the current user roles.
                    foreach ( (array) $u->roles as $old_role ) {

                        // Remove the role if it is editable.
                        if ( dt_multi_role_is_role_editable( $old_role ) ) {
                            $u->remove_role( $old_role );
                        }
                    }
                }
                return $this->get_dt_user( $user->ID );
            }
        }
        return false;
    }

    public static function get_dash_stats(){
        global $wpdb;
        $stats = self::query_project_hero_stats();
        $multipliers = self::get_users();

        $month_start = strtotime( date( 'Y-m-01' ) );
        $last_month_start = strtotime( 'first day of last month' );
        $this_year = strtotime( "first day of january this year" );
        //number of assigned contacts
        $assigned_counts = $wpdb->get_results( $wpdb->prepare( "
            SELECT 
            COUNT( CASE WHEN date_assigned.hist_time >= %d THEN 1 END ) as this_month,
            COUNT( CASE WHEN date_assigned.hist_time >= %d AND date_assigned.hist_time < %d THEN 1 END ) as last_month,
            COUNT( CASE WHEN date_assigned.hist_time >= %d THEN 1 END ) as this_year,
            COUNT( date_assigned.histid ) as all_time
            FROM $wpdb->dt_activity_log as date_assigned
            INNER JOIN $wpdb->postmeta as type ON ( date_assigned.object_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
            WHERE date_assigned.meta_key = 'assigned_to'
                AND date_assigned.object_type = 'contacts' 
                AND date_assigned.old_value <> ''
        ", $month_start, $last_month_start, $month_start, $this_year ), ARRAY_A );

        $multipliers_stats = [
            "status" => []
        ];
        foreach ( $multipliers as $m ) {
            if ( !isset( $m["user_status"] ) ) {
                $m["user_status"] = "";
            }
            if ( !isset( $multipliers_stats["status"][$m["user_status"]] ) ){
                $multipliers_stats["status"][$m["user_status"]] = 0;
            }
            $multipliers_stats["status"][ $m["user_status"] ] += 1;
        }
        $results = [
            'total_contacts' => $stats["total_contacts"],
            'active_contacts' => $stats['active_contacts'],
            'needs_accepted' => $stats['needs_accept'],
            'updates_needed' => $stats['needs_update'],
            'multiplier_stats' => $multipliers_stats,
            'assigned_counts' => isset( $assigned_counts[0] ) ? $assigned_counts[0] : []
        ];
        return $results;
    }

    private static function query_project_hero_stats() {
        global $wpdb;

        $numbers = [];
        $numbers["total_contacts"] = Disciple_Tools_Counter::critical_path( 'new_contacts' );


        $results = $wpdb->get_results( "
            SELECT (
                SELECT count(a.ID)
                FROM $wpdb->posts as a
                JOIN $wpdb->postmeta as b
                ON a.ID = b.post_id
                AND b.meta_key = 'overall_status'
                AND b.meta_value = 'active'
                WHERE a.post_status = 'publish'
                AND a.post_type = 'contacts'
                AND a.ID NOT IN (
                    SELECT post_id FROM $wpdb->postmeta
                    WHERE meta_key = 'type' AND meta_value = 'user'
                    GROUP BY post_id
                )
            )
            as active_contacts,
            (SELECT count(a.ID)
                FROM $wpdb->posts as a
                    JOIN $wpdb->postmeta as b
                    ON a.ID=b.post_id
                       AND b.meta_key = 'accepted'
                       AND b.meta_value = ''
                JOIN $wpdb->postmeta as d
                ON a.ID=d.post_id
                   AND d.meta_key = 'overall_status'
                   AND d.meta_value = 'assigned'
                WHERE a.post_status = 'publish'
                AND a.post_type = 'contacts'
                AND a.ID NOT IN (
                    SELECT post_id FROM $wpdb->postmeta
                    WHERE meta_key = 'type' AND meta_value = 'user'
                    GROUP BY post_id
               )
            ) 
            as needs_accept,
            (SELECT count(a.ID)
                FROM $wpdb->posts as a
                    JOIN $wpdb->postmeta as b
                    ON a.ID=b.post_id
                       AND b.meta_key = 'requires_update'
                       AND b.meta_value = '1'
               JOIN $wpdb->postmeta as d
               ON a.ID=d.post_id
                      AND d.meta_key = 'overall_status'
               AND d.meta_value = 'active'
               WHERE a.post_status = 'publish'
                AND a.post_type = 'contacts'
                AND a.ID NOT IN (
                    SELECT post_id FROM $wpdb->postmeta
                    WHERE meta_key = 'type' AND meta_value = 'user'
                    GROUP BY post_id
                ))
            as needs_update,
            (SELECT count(a.ID)
                FROM $wpdb->posts as a
                JOIN $wpdb->postmeta as d
                ON a.ID=d.post_id
                    AND d.meta_key = 'group_status'
                AND d.meta_value = 'active'
                JOIN $wpdb->postmeta as e
                  ON a.ID=e.post_id
                     AND e.meta_key = 'group_type'
                     AND e.meta_value != 'team'
                WHERE a.post_status = 'publish'
                AND a.post_type = 'groups')
            as `groups`,
            (SELECT count(a.ID)
                FROM $wpdb->posts as a
                JOIN $wpdb->postmeta as d
                ON a.ID=d.post_id
                    AND d.meta_key = 'group_status'
                AND d.meta_value = 'active'
                JOIN $wpdb->postmeta as e
                  ON a.ID=e.post_id
                     AND e.meta_key = 'group_type'
                     AND e.meta_value = 'team'
                WHERE a.post_status = 'publish'
                AND a.post_type = 'groups')
            as `teams`
        ", ARRAY_A );

        if ( empty( $results ) ) {
            return new WP_Error( __METHOD__, 'No results from the personal count query' );
        }

        foreach ( $results[0] as $key => $value ) {
            $numbers[$key] = $value;
        }

        return $numbers;
    }


    private static function times( $user_id ){
        global $wpdb;
        $user_assigned_to = 'user-' . esc_sql( $user_id );
        $contact_attempts = $wpdb->get_results( $wpdb->prepare( "
            SELECT contacts.ID,
                MAX(date_assigned.hist_time) as date_assigned, 
                MIN(date_attempted.hist_time) as date_attempted, 
                MIN(date_attempted.hist_time) - MAX(date_assigned.hist_time) as time,
                contacts.post_title as name
            from $wpdb->posts as contacts
            INNER JOIN $wpdb->postmeta as pm on ( contacts.ID = pm.post_id AND pm.meta_key = 'assigned_to' )
            INNER JOIN $wpdb->dt_activity_log as date_attempted on ( date_attempted.meta_key = 'seeker_path' and date_attempted.object_type = 'contacts' AND date_attempted.object_id = contacts.ID AND date_attempted.meta_value ='attempted' )
            INNER JOIN $wpdb->dt_activity_log as date_assigned on ( 
                date_assigned.meta_key = 'assigned_to' 
                AND date_assigned.object_type = 'contacts' 
                AND date_assigned.object_id = contacts.ID
                AND date_assigned.meta_value = %s )
            WHERE date_attempted.hist_time > date_assigned.hist_time
            AND pm.meta_value = %s
            AND date_assigned.hist_time = ( 
                SELECT MAX(hist_time) FROM $wpdb->dt_activity_log a WHERE
                a.meta_key = 'assigned_to' 
                AND a.object_type = 'contacts' 
                AND a.object_id = contacts.ID )  
            AND contacts.ID NOT IN (
                SELECT post_id FROM $wpdb->postmeta
                WHERE meta_key = 'type' AND meta_value = 'user'
                GROUP BY post_id )
            GROUP by contacts.ID
            ORDER BY date_attempted desc
            LIMIT 10
        ", $user_assigned_to, $user_assigned_to ), ARRAY_A);
        $un_attempted_contacts = $wpdb->get_results( $wpdb->prepare( "
            SELECT contacts.ID,
                MAX(date_assigned.hist_time) as date_assigned, 
                %d - MAX(date_assigned.hist_time) as time,
                contacts.post_title as name
            from $wpdb->posts as contacts
            INNER JOIN $wpdb->postmeta as pm on ( contacts.ID = pm.post_id AND pm.meta_key = 'assigned_to' )
            INNER JOIN $wpdb->postmeta as pm1 on ( contacts.ID = pm1.post_id AND pm1.meta_key = 'seeker_path' and pm1.meta_value = 'none' )
            INNER JOIN $wpdb->postmeta as pm2 on ( contacts.ID = pm2.post_id AND pm2.meta_key = 'overall_status' and pm2.meta_value = 'active' )
            INNER JOIN $wpdb->dt_activity_log as date_assigned on ( 
                date_assigned.meta_key = 'assigned_to' 
                AND date_assigned.object_type = 'contacts' 
                AND date_assigned.object_id = contacts.ID
                AND date_assigned.meta_value = %s )
            WHERE pm.meta_value = %s
            AND contacts.ID NOT IN (
                SELECT post_id FROM $wpdb->postmeta
                WHERE meta_key = 'type' AND meta_value = 'user'
                GROUP BY post_id )
            GROUP by contacts.ID
            ORDER BY date_assigned asc
            LIMIT 10
        ", time(), $user_assigned_to, $user_assigned_to ), ARRAY_A);
        $contact_accepts = $wpdb->get_results( $wpdb->prepare( "
            SELECT contacts.ID,
                MAX(date_assigned.hist_time) as date_assigned, 
                MIN(date_accepted.hist_time) as date_accepted, 
                MIN(date_accepted.hist_time) - MAX(date_assigned.hist_time) as time,
                contacts.post_title as name
            from $wpdb->posts as contacts
            INNER JOIN $wpdb->postmeta as pm on ( contacts.ID = pm.post_id AND pm.meta_key = 'assigned_to' )
            INNER JOIN $wpdb->dt_activity_log as date_accepted on ( 
                date_accepted.meta_key = 'overall_status' 
                AND date_accepted.object_type = 'contacts' 
                AND date_accepted.object_id = contacts.ID 
                AND date_accepted.meta_value = 'active' )
            INNER JOIN $wpdb->dt_activity_log as date_assigned on ( 
                date_assigned.meta_key = 'assigned_to' 
                AND date_assigned.object_type = 'contacts' 
                AND date_assigned.object_id = contacts.ID
                AND date_assigned.user_id != %d
                AND date_assigned.meta_value = %s )
            WHERE date_accepted.hist_time > date_assigned.hist_time
            AND pm.meta_value = %s
            AND date_assigned.hist_time = ( 
                SELECT MAX(hist_time) FROM $wpdb->dt_activity_log a WHERE
                a.meta_key = 'assigned_to' 
                AND a.object_type = 'contacts' 
                AND a.object_id = contacts.ID )
            AND contacts.ID NOT IN (
                SELECT post_id FROM $wpdb->postmeta
                WHERE meta_key = 'type' AND meta_value = 'user'
                GROUP BY post_id )
            GROUP by contacts.ID
            ORDER BY date_accepted desc
            LIMIT 10
        ", esc_sql( $user_id ), $user_assigned_to, $user_assigned_to ), ARRAY_A);
        $un_accepted_contacts = $wpdb->get_results( $wpdb->prepare( "
            SELECT contacts.ID,
                MAX(date_assigned.hist_time) as date_assigned, 
                %d - MAX(date_assigned.hist_time) as time,
                contacts.post_title as name
            from $wpdb->posts as contacts
            INNER JOIN $wpdb->postmeta as pm on ( contacts.ID = pm.post_id AND pm.meta_key = 'assigned_to' )
            INNER JOIN $wpdb->postmeta as pm1 on ( contacts.ID = pm1.post_id AND pm1.meta_key = 'overall_status' and pm1.meta_value = 'assigned' )
            INNER JOIN $wpdb->dt_activity_log as date_assigned on ( 
                date_assigned.meta_key = 'assigned_to' 
                AND date_assigned.object_type = 'contacts' 
                AND date_assigned.object_id = contacts.ID
                AND date_assigned.meta_value = %s )
            WHERE pm.meta_value = %s
            AND contacts.ID NOT IN (
                SELECT post_id FROM $wpdb->postmeta
                WHERE meta_key = 'type' AND meta_value = 'user'
                GROUP BY post_id )
            GROUP by contacts.ID
            ORDER BY date_assigned asc
            LIMIT 10
        ", time(), $user_assigned_to, $user_assigned_to ), ARRAY_A);


        return [
            'contact_attempts' => $contact_attempts,
            'contact_accepts' => $contact_accepts,
            'unaccepted_contacts' => $un_accepted_contacts,
            'unattempted_contacts' => $un_attempted_contacts
        ];
    }


    public static function get_contact_statuses( $user_id = null ){
        global $wpdb;
        $post_settings = apply_filters( "dt_get_post_type_settings", [], "contacts" );
        if ( $user_id ){
            $contact_statuses = $wpdb->get_results( $wpdb->prepare( "
                SELECT COUNT(pm1.meta_value) as count, pm1.meta_value as status FROM $wpdb->posts p
                INNER JOIN $wpdb->postmeta pm ON ( pm.post_id = p.ID AND pm.meta_key = 'assigned_to' AND pm.meta_value = %s )
                INNER JOIN $wpdb->postmeta pm1 ON ( pm1.post_id = p.ID AND pm1.meta_key = 'overall_status' )
                INNER JOIN $wpdb->postmeta as type ON ( p.ID = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                WHERE p.post_type = 'contacts'
                GROUP BY pm1.meta_value
            ", 'user-' . $user_id ), ARRAY_A );
        } else {
            $contact_statuses = $wpdb->get_results( "
                SELECT COUNT(pm.meta_value) as count, pm.meta_value as status FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta as type ON ( pm.post_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                WHERE pm.meta_key = 'overall_status'
                GROUP BY pm.meta_value
            ", ARRAY_A );
        }
        if ( $user_id ){
            $reason_closed = $wpdb->get_results( $wpdb->prepare( "
                SELECT COUNT(pm1.meta_value) as count, pm1.meta_value as reason FROM $wpdb->posts p
                INNER JOIN $wpdb->postmeta pm ON ( pm.post_id = p.ID AND pm.meta_key = 'assigned_to' AND pm.meta_value = %s )
                INNER JOIN $wpdb->postmeta pm1 ON ( pm1.post_id = p.ID AND pm1.meta_key = 'reason_closed' )
                INNER JOIN $wpdb->postmeta as type ON ( p.ID = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                WHERE p.post_type = 'contacts'
                GROUP BY pm1.meta_value
            ", 'user-' . $user_id ), ARRAY_A );
        } else {
            $reason_closed = $wpdb->get_results( "
                SELECT COUNT(pm.meta_value) as count, pm.meta_value as reason FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta as type ON ( pm.post_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                WHERE pm.meta_key = 'reason_closed'
                GROUP BY pm.meta_value
            ", ARRAY_A );
        }
        foreach ( $reason_closed as &$reason ){
            if ( isset( $post_settings["fields"]["reason_closed"]['default'][ $reason['reason'] ]['label'] ) ) {
                $reason['reason'] = $post_settings["fields"]["reason_closed"]['default'][$reason['reason']]['label'];
            }
            if ( $reason['reason'] === '' ){
                $reason['reason'] = "No reason set";
            }
        }
        if ( $user_id ){
            $reason_paused = $wpdb->get_results( $wpdb->prepare( "
                SELECT COUNT(pm1.meta_value) as count, pm1.meta_value as reason FROM $wpdb->posts p
                INNER JOIN $wpdb->postmeta pm ON ( pm.post_id = p.ID AND pm.meta_key = 'assigned_to' AND pm.meta_value = %s )
                INNER JOIN $wpdb->postmeta pm1 ON ( pm1.post_id = p.ID AND pm1.meta_key = 'reason_paused' )
                INNER JOIN $wpdb->postmeta as type ON ( p.ID = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                GROUP BY pm1.meta_value
            ", 'user-' . $user_id ), ARRAY_A );
        } else {
            $reason_paused = $wpdb->get_results( "
                SELECT COUNT(pm.meta_value) as count, pm.meta_value as reason FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta as type ON ( pm.post_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                WHERE pm.meta_key = 'reason_paused'
                GROUP BY pm.meta_value
            ", ARRAY_A );
        }
        foreach ( $reason_paused as &$reason ){
            if ( isset( $post_settings["fields"]["reason_paused"]['default'][ $reason['reason'] ]['label'] ) ) {
                $reason['reason'] = $post_settings["fields"]["reason_paused"]['default'][$reason['reason']]['label'];
            }
            if ( $reason['reason'] === '' ){
                $reason['reason'] = "No reason set";
            }
        }
        if ( $user_id ){
            $reason_unassignable = $wpdb->get_results( $wpdb->prepare( "
                SELECT COUNT(pm1.meta_value) as count, pm1.meta_value as reason FROM $wpdb->posts p
                INNER JOIN $wpdb->postmeta pm ON ( pm.post_id = p.ID AND pm.meta_key = 'assigned_to' AND pm.meta_value = %s )
                INNER JOIN $wpdb->postmeta pm1 ON ( pm1.post_id = p.ID AND pm1.meta_key = 'reason_unassignable' )
                INNER JOIN $wpdb->postmeta as type ON ( p.ID = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                GROUP BY pm1.meta_value
            ", 'user-' . $user_id ), ARRAY_A );
        } else {
            $reason_unassignable = $wpdb->get_results( "
                SELECT COUNT(pm.meta_value) as count, pm.meta_value as reason FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta as type ON ( pm.post_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                WHERE pm.meta_key = 'reason_unassignable'
                GROUP BY pm.meta_value
            ", ARRAY_A );
        }
        foreach ( $reason_unassignable  as &$reason ){
            if ( isset( $post_settings["fields"]["reason_unassignable "]['default'][ $reason['reason'] ]['label'] ) ) {
                $reason['reason'] = $post_settings["fields"]["reason_unassignable "]['default'][$reason['reason']]['label'];
            }
            if ( $reason['reason'] === '' ){
                $reason['reason'] = "No reason set";
            }
        }

        foreach ( $contact_statuses as &$status ){
            if ( $status['status'] === 'closed' ){
                $status['reasons'] = $reason_closed;
            } elseif ( $status['status'] === 'paused' ){
                $status['reasons'] = $reason_paused;
            } elseif ( $status['status'] === 'unassignable' ){
                $status['reasons'] = $reason_unassignable;
            } else {
                $status['reasons'] = [];
            }
            if ( isset( $post_settings["fields"]["overall_status"]['default'][ $status['status'] ]['label'] ) ) {
                $status['status'] = $post_settings["fields"]["overall_status"]['default'][$status['status']]['label'];
            }
        }
        return $contact_statuses;
    }
}
