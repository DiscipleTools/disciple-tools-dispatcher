<?php
/**
 * Rest API example class
 */


class DT_Dispatcher_Tools_Endpoints
{
    public $permissions = [ 'view_any_contacts' ];

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
                    'callback' => [ $this, 'get_user' ],
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
    }


    public function get_user( WP_REST_Request $request ) {
        if ( !$this->has_permission() ){
            return new WP_Error( "get_user", "Missing Permissions", [ 'status' => 401 ] );
        }
        global $wpdb;

        $params = $request->get_params();
        if ( !isset( $params["user"] ) ) {
            return new WP_Error( "get_user", "Missing user id", [ 'status' => 400 ] );
        }

        $user = get_user_by( "ID", $params["user"] );

        $user_status = get_user_option( 'user_status', $user->ID );

        $location_grids = DT_Mapping_Module::instance()->get_post_locations( dt_get_associated_user_id( $user->ID, 'user' ) );
        $locations = [];
        foreach ( $location_grids as $l ){
            $locations[] = [
                "grid_id" => $l["grid_id"],
                "name" => $l["name"]
            ];
        }

        $dates_unavailable = get_user_option( "user_dates_unavailable", $user->ID );
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

        $test = "";


        return [
            "display_name" => $user->display_name,
            "user_status" => $user_status,
            "locations" => $locations,
            "dates_unavailable" => $dates_unavailable ? $dates_unavailable : [],
            "user_activity" => $user_activity,

            "active_contacts" => 15,
            "update_needed_count" => 5,
            "update_needed" => [ 100, 1001 ],
            "needs_accepted_count" => 1,
            "needs_accepted" => [ 100 ],
            "times_to_accept" => [ 132, 320939, 390484, 39039 ],
            "times_to_contact_attempt" => [ 23, 39032, 4093, 33333 ]
        ];


    }

    public static function get_users() {
        if ( get_transient( 'dispatcher_user_data' ) ) {
            return maybe_unserialize( get_transient( 'dispatcher_user_data' ) );
        }

        global $wpdb;
        $user_data = $wpdb->get_results( $wpdb->prepare( "
            SELECT users.ID,
                users.display_name,
                um1.meta_value as user_status,
                count(pm.post_id) as number_assigned_to,
                count(active.post_id) as number_active,
                count(new_assigned.post_id) as number_new_assigned,
                count(update_needed.post_id) as number_update
            from $wpdb->users as users
            INNER JOIN $wpdb->usermeta as um on ( um.user_id = users.ID AND um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%multiplier%' )
            LEFT JOIN $wpdb->usermeta as um1 on ( um1.user_id = users.ID AND um1.meta_key = %s )
            INNER JOIN $wpdb->postmeta as pm on (pm.meta_key = 'assigned_to' and pm.meta_value = CONCAT( 'user-', users.ID ) )
            INNER JOIN $wpdb->postmeta as type on (type.post_id = pm.post_id and type.meta_key = 'type' and ( type.meta_value = 'media' OR type.meta_value = 'next_gen' ) )
            LEFT JOIN $wpdb->postmeta as active on (active.post_id = pm.post_id and active.meta_key = 'overall_status' and active.meta_value = 'active' )
            LEFT JOIN $wpdb->postmeta as new_assigned on (new_assigned.post_id = pm.post_id and new_assigned.meta_key = 'overall_status' and new_assigned.meta_value = 'assigned' )
            LEFT JOIN $wpdb->postmeta as update_needed on (update_needed.post_id = pm.post_id and update_needed.meta_key = 'requires_update' and update_needed.meta_value = '1' )
            GROUP by users.ID", $wpdb->prefix . 'user_status' ),
        ARRAY_A );

        $users = [];
        foreach ( $user_data as $user ) {
            $users[ $user["ID"] ] = $user;
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


        set_transient( 'dispatcher_user_data', maybe_serialize( $users ), 60 * 60 * 24 );

        return $users;
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
            if ( !empty( $body["user_status"] ) ) {
                update_user_option( $user->ID, 'user_status', $body["user_status"] );
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
                        "start_date" => $body["add_unavailability"]["start_date"],
                        "end_date" => $body["add_unavailability"]["end_date"],
                    ];
                    update_user_option( $user->ID, "user_dates_unavailable", $dates_unavailable );
                    return $dates_unavailable;
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
        }


    }

}
