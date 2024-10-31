<?php
/**
 * Plugin Name: Recommat
 * Plugin URI: http://recommat.com
 * Description: A.I. based Product recommendation engine. Require your PHP installation have redis extension installed.
 * Version: 1.0
 * Author: Joe Tsui
 * Author URI: https://github.com/joetsuihk
 */

defined( 'ABSPATH' ) or die( 'Keep Silent' );

/**
 * Main plugin class
 */
if ( ! class_exists( 'Recommat' ) ) {
    final class Recommat {

        protected static $_version = '0.1';
        protected static $_instance = null;
			
        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }
            
            return self::$_instance;
        }
        
        public function __construct() {
            $this->constants();
            $this->language();
            $this->includes();
            $this->hooks();
            do_action( 'recommat_loaded', $this );
        }

        public function define( $name, $value, $case_insensitive = false ) {
            if ( ! defined( $name ) ) {
                define( $name, $value, $case_insensitive );
            }
        }

        public function constants() {
            $this->define( 'RECOMMAT_VERSION', $this->version() );
            $this->define( 'RECOMMAT_PLUGIN_DIRNAME', dirname( plugin_basename( __FILE__ ) ) );
        }

        public function includes() {
            if ( $this->is_required_php_version() && $this->is_wc_active() ) {
                require_once 'includes/engine.php';
                require_once 'includes/functions.php';
                require_once 'includes/misc.php';
                if ( is_admin() ) {
                    require_once 'includes/admin-pages.php';
                }
            }
        }

        public function version() {
            return esc_attr( self::$_version );
        }
        
        public function is_pro_active() {
            return class_exists( 'Recommat_Pro' );
        }

        public function get_pro_link( $medium = 'go-pro' ) {
            
            $link_args[ 'utm_source' ]   = 'wp-admin-plugins';
            $link_args[ 'utm_medium' ]   = esc_attr( $medium );
            $link_args[ 'utm_campaign' ] = 'recommat';
            $link_args[ 'utm_term' ]     = sanitize_title( $this->get_parent_theme_name() );
                        
            return add_query_arg( $link_args, 'https://recommat.com/recommat-pro-version/' );
        }

        public function get_parent_theme_name() {
            return wp_get_theme( get_template() )->get( 'Name' );
        }
        
        public function is_required_php_version() {
            return version_compare( PHP_VERSION, '5.6.0', '>=' );
        }
        
        public function is_required_wc_version() {
            return version_compare( WC_VERSION, '4.5', '>' );
        }

        public function is_required_redis_version() {
            return class_exists( 'Redis' );
        }
        
        public function wc_version_requirement_notice() {
            if ( $this->is_wc_active() && ! $this->is_required_wc_version() ) {
                $class   = 'notice notice-error';
                $message = sprintf( esc_html__( "Currently, you are using older version of WooCommerce. It's recommended to use latest version of WooCommerce to work with %s.", 'recommat' ), esc_html__( 'WooCommerce Variation Gallery', 'recommat' ) );
                printf( '<div class="%1$s"><p><strong>%2$s</strong></p></div>', $class, $message );
            }
        }
        
        public function php_requirement_notice() {
            if ( ! $this->is_required_php_version() ) {
                $class   = 'notice notice-error';
                $text    = esc_html__( 'Please check PHP version requirement.', 'recommat' );
                $link    = esc_url( 'https://docs.woocommerce.com/document/server-requirements/' );
                $message = wp_kses( __( "It's required to use latest version of PHP to use <strong>Additional Variation Images Gallery for WooCommerce</strong>.", 'recommat' ), array( 'strong' => array() ) );
                
                printf( '<div class="%1$s"><p>%2$s <a target="_blank" href="%3$s">%4$s</a></p></div>', $class, $message, $link, $text );
            }
        }
        
        public function wc_requirement_notice() {
            
            if ( ! $this->is_wc_active() ) {
                
                $class = 'notice notice-error';
                
                $text    = esc_html__( 'WooCommerce', 'recommat' );
                $link    = esc_url( add_query_arg( array(
                                                       'tab'       => 'plugin-information',
                                                       'plugin'    => 'woocommerce',
                                                       'TB_iframe' => 'true',
                                                       'width'     => '640',
                                                       'height'    => '500',
                                                   ), admin_url( 'plugin-install.php' ) ) );
                $message = wp_kses( __( "<strong>Additional Variation Images Gallery for WooCommerce</strong> is an add-on of ", 'recommat' ), array( 'strong' => array() ) );
                
                printf( '<div class="%1$s"><p>%2$s <a class="thickbox open-plugin-details-modal" href="%3$s"><strong>%4$s</strong></a></p></div>', $class, $message, $link, $text );
            }
        }

        public function redis_requirement_notice() {
            if ( $this->is_wc_active() && ! $this->is_required_redis_version() ) {
                $class   = 'notice notice-error';
                $message = sprintf( esc_html__( "Currently, you are using older version of WooCommerce. It's recommended to use latest version of WooCommerce to work with %s.", 'recommat' ), esc_html__( 'Recommat', 'recommat' ) );
                printf( '<div class="%1$s"><p><strong>%2$s</strong></p></div>', $class, $message );
            }
        }
        
        public function language() {
            load_plugin_textdomain( 'recommat', false, trailingslashit( RECOMMAT_PLUGIN_DIRNAME ) . 'languages' );
        }
        
        public function is_wc_active() {
            return class_exists( 'WooCommerce' );
        }

        public static function plugin_activated() {
            update_option( 'activate-recommat', 'yes' );
            if ( ! wp_next_scheduled( 'recommat_hourly_cron_hook' ) ) {
                wp_schedule_event( time(), 'hourly', 'recommat_hourly_cron_hook' );
            }
        }
        
        public static function plugin_deactivated() {
            delete_option( 'activate-recommat' );
            wp_clear_scheduled_hook( 'recommat_hourly_cron_hook' );
        }

        public function hooks() {
				
            add_action( 'admin_notices', array( $this, 'php_requirement_notice' ) );
            add_action( 'admin_notices', array( $this, 'wc_requirement_notice' ) );
            add_action( 'admin_notices', array( $this, 'wc_version_requirement_notice' ) );
            add_action( 'admin_notices', array( $this, 'redis_requirement_notice' ) );
        }
    }

    function recommat() {

        $instance = Recommat::instance();

        //define cron handler
        add_action( 'recommat_hourly_cron_hook', 'recommat_hourly_cron_exec' );

        return $instance;
    }
    
    add_action( 'plugins_loaded', 'recommat', 20 );
    
    register_activation_hook( __FILE__, array( 'Recommat', 'plugin_activated' ) );
    register_deactivation_hook( __FILE__, array( 'Recommat', 'plugin_deactivated' ) );

}