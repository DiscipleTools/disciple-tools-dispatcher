<?php

class DT_Dispatcher_Functions
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
        if ( $this->has_permission() ){
            add_action( 'dt_top_nav_desktop', [ $this, 'add_nav_bar_link' ] );
            add_action( "template_redirect", [ $this, 'my_theme_redirect' ] );
            $url_path = dt_get_url_path();
            if ( strpos( $url_path, 'dispatcher-tools' ) !== false ) {
                add_filter( 'dt_metrics_menu', [ $this, 'add_menu' ], 20 );
                add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
            }
        }
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

    public function my_theme_redirect() {
        $url = dt_get_url_path();
        $plugin_dir = dirname( __FILE__ );
        if ( strpos( $url, "dispatcher-tools" ) !== false ){
            $path = $plugin_dir . '/template-dispatcher-tools.php';
            include( $path );
            die();
        }
    }

    public function add_nav_bar_link(){
        if ( current_user_can( 'view_any_contacts' ) ) : ?>
            <li><a href="<?php echo esc_url( site_url( '/dispatcher-tools/' ) ); ?>"><?php echo esc_html( "Dispatcher" ); ?></a></li>
        <?php endif;
    }


    public function add_menu( $content ) {
        $content .= '<li>
            <a href="'. site_url( '/dispatcher-tools/dash' ) .'" >' .  esc_html__( 'Overview', 'disciple_tools' ) . '</a>
            <a href="'. site_url( '/dispatcher-tools/multipliers' ) .'" >' .  esc_html__( 'Multipliers', 'disciple_tools' ) . '</a>
        </li>';
        return $content;
    }


    public function scripts() {

        wp_register_style( 'datatable-css', '//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css', [], '1.10.19' );
        wp_enqueue_style( 'datatable-css' );
        wp_register_script( 'datatable', '//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js', false, '1.10' );
        wp_register_script( 'amcharts-core', 'https://www.amcharts.com/lib/4/core.js', false, '4' );
        wp_register_script( 'amcharts-charts', 'https://www.amcharts.com/lib/4/charts.js', false, '4' );
        wp_register_script( 'amcharts-animated', 'https://www.amcharts.com/lib/4/themes/animated.js', [ 'amcharts-core' ], '4' );
        wp_enqueue_style( 'dispatcher_tools-css', plugin_dir_url( __FILE__ ) . 'styles.css', array(), filemtime( plugin_dir_path( __FILE__ ) . 'styles.css' ) );
        wp_enqueue_script( 'dt_dispatcher_tools', plugin_dir_url( __FILE__ ) . '/dispatcher-tools.js', [
            'jquery',
            'jquery-ui-core',
            'moment',
            'datatable',
            'amcharts-core',
            'amcharts-charts',
            'amcharts-animated',
        ], filemtime( plugin_dir_path( __FILE__ ) . '/dispatcher-tools.js' ), true );

        $contact_statuses = DT_Dispatcher_Endpoints::instance()->get_contact_statuses();
        wp_localize_script(
            'dt_dispatcher_tools', 'dtDispatcherTools', [
                'root'               => esc_url_raw( rest_url() ),
                'theme_uri'          => get_stylesheet_directory_uri(),
                'nonce'              => wp_create_nonce( 'wp_rest' ),
                'current_user_login' => wp_get_current_user()->user_login,
                'current_user_id'    => get_current_user_id(),
                'data'               => [
                    'contact_statuses' => $contact_statuses
                ],
                'url_path'       => dt_get_url_path()
            ]
        );
    }
}
