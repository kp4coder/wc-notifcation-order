<?php
/*
Plugin Name: Woocommerce Pre-Order Notification
Plugin URI: https://catandog.co.il/
Description: Woocommerce Pre-Order Notification
Version: 1.0.0
Author: kp dev
Author URI: https://wordpress.org/
Domain Path: /languages
Text Domain: wpn_text_domain
*/

// plugin definitions
define( 'WPN_PLUGIN', '/woocommerce_order_notification/');

// directory define
define( 'WPN_PLUGIN_DIR', WP_PLUGIN_DIR.WPN_PLUGIN);
define( 'WPN_INCLUDES_DIR', WPN_PLUGIN_DIR.'includes/' );
$upload = wp_upload_dir();

define( 'WPN_ASSETS_DIR', WPN_PLUGIN_DIR.'assets/' );
define( 'WPN_CSS_DIR', WPN_ASSETS_DIR.'css/' );
define( 'WPN_JS_DIR', WPN_ASSETS_DIR.'js/' );
define( 'WPN_IMAGES_DIR', WPN_ASSETS_DIR.'images/' );

// URL define
define( 'WPN_PLUGIN_URL', WP_PLUGIN_URL.WPN_PLUGIN);

define( 'WPN_ASSETS_URL', WPN_PLUGIN_URL.'assets/');
define( 'WPN_IMAGES_URL', WPN_ASSETS_URL.'images/');
define( 'WPN_CSS_URL', WPN_ASSETS_URL.'css/');
define( 'WPN_JS_URL', WPN_ASSETS_URL.'js/');

// define text domain
define( 'WPN_txt_domain', 'wpn_text_domain' );

global $wpn_version;
$wpn_version = '1.0.0';

class WoocommercePreorderNotification {

    var $wpn_setting = '';

    function __construct() {
        global $wpdb;

        $this->wpn_setting = 'wpn_preorder_notification';
        $this->tbl_wpn_order_notification = $wpdb->prefix . 'wpn_order_notification';

        register_activation_hook( __FILE__,  array( &$this, 'wpn_install' ) );
        
        register_deactivation_hook( __FILE__, array( &$this, 'wpn_deactivation' ) );

        add_action( 'admin_menu', array( $this, 'wpn_add_menu' ) );

        add_action( 'init', array( $this, 'pmg_rewrite_add_rewrites' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'wpn_enqueue_scripts' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'wpn_front_enqueue_scripts' ) );

        add_action( 'plugins_loaded', array( $this, 'wpn_load_textdomain' ) );
        
    }

    function pmg_rewrite_add_rewrites() {
      add_rewrite_rule( '(addtocart)/(.*)/([A-z0-9_]+)/$', 'addtocart/?add-to-cart=$2&coupon-code=$3', 'top' );
    }

    function wpn_load_textdomain() {
        load_plugin_textdomain( WPN_txt_domain, false, basename(dirname(__FILE__)) . '/languages' ); //Loads plugin text domain for the translation
        do_action('WPN_txt_domain');
    }

    static function wpn_install() {

        global $wpdb, $wpn, $wpn_version;

        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        if ( ! wp_next_scheduled( 'wpn_send_notification' ) ) {
            wp_schedule_event( time(), 'daily', 'wpn_send_notification' );
        }

        $table_name = $wpdb->prefix . "wpn_order_notification";
        $wpn_order_notification = "CREATE TABLE $table_name (
            wpn_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            wpn_name VARCHAR(255) NOT NULL,
            wpn_phone VARCHAR(255) NOT NULL,
            wpn_first_order VARCHAR(255) NOT NULL,
            wpn_last_order VARCHAR(255) NOT NULL,
            wpn_date_diff VARCHAR(255) NOT NULL,
            wpn_total_order VARCHAR(255) NOT NULL,
            wpn_next_expect_order_date VARCHAR(255) NOT NULL,
            wpn_product_name VARCHAR(255) NOT NULL,
            wpn_discount_amount BIGINT(20) NOT NULL,
            wpn_wc_product_id VARCHAR(255) NOT NULL,
            wpn_wc_coupon VARCHAR(255) NOT NULL,
            wpn_is_block TINYINT DEFAULT 0 NOT NULL,
          PRIMARY KEY  ( wpn_id )
        ) $charset_collate;";
        dbDelta( $wpn_order_notification );

        update_option( "wpn_plugin", true );
        update_option( "wpn_version", $wpn_version );

        $wpn->pmg_rewrite_add_rewrites();
        flush_rewrite_rules();
        
    }

    static function wpn_deactivation() {
        // deactivation process here
    }

    function wpn_get_sub_menu() {
        $wpn_admin_menu = array(
            array(
                'name' => __('Pre-Order Notification', WPN_txt_domain),
                'cap'  => 'manage_options',
                'slug' => $this->wpn_setting,
            )
        );
        return $wpn_admin_menu;
    }

    function wpn_add_menu() {

        $wpn_main_page_name = __('Pre-Order Notification', WPN_txt_domain);
        $wpn_main_page_capa = 'manage_options';
        $wpn_main_page_slug = $this->wpn_setting; 

        $wpn_get_sub_menu   = $this->wpn_get_sub_menu();
        /* set capablity here.... Right now manage_options capability given to all page and sub pages. <span class="dashicons dashicons-money"></span>*/     
        add_menu_page($wpn_main_page_name, $wpn_main_page_name, $wpn_main_page_capa, $wpn_main_page_slug, array( &$this, 'wpn_route' ), 'dashicons-bell', 50 );

        foreach ($wpn_get_sub_menu as $wpn_menu_key => $wpn_menu_value) {
            add_submenu_page(
                $wpn_main_page_slug, 
                $wpn_menu_value['name'], 
                $wpn_menu_value['name'], 
                $wpn_menu_value['cap'], 
                $wpn_menu_value['slug'], 
                array( $this, 'wpn_route') 
            );  
        }
    }

    function wpn_is_activate(){
        if(get_option("wpn_plugin")) {
            return true;
        } else {
            return false;
        }
    }

    function wpn_admin_slugs() {
        $wpn_pages_slug = array(
            $this->wpn_setting,
            'wpn_item_part',
            'wpn_background',
            'wpn_sub_product',
            'sub_product_version',
            'wpn_part_color',
            'product'
        );
        return $wpn_pages_slug;
    }

    function wpn_is_page() {
        $slug_val = '';
        if( isset( $_REQUEST['page'] ) ) {
            $slug_val = $_REQUEST['page'];
        } else if( isset( $_REQUEST['taxonomy'] ) ) {
            $slug_val = $_REQUEST['taxonomy'];
        } else if( isset( $_REQUEST['post_type'] ) ) {
            $slug_val = $_REQUEST['post_type'];
        } else if( get_post_type() ) {
            $slug_val = get_post_type();
        }

        if( in_array( $slug_val, $this->wpn_admin_slugs() ) ) {
            return true;
        } else {
            return false;
        }
    } 

    function wpn_admin_msg( $key ) { 
        $admin_msg = array(
            "no_tax" => __("No matching tax rates found.", WPN_txt_domain)
        );

        if( $key == 'script' ){
            $script = '<script type="text/javascript">';
            $script.= 'var __wpn_msg = '.json_encode($admin_msg);
            $script.= '</script>';
            return $script;
        } else {
            return isset($admin_msg[$key]) ? $admin_msg[$key] : false;
        }
    }

    function wpn_enqueue_scripts() {
        global $wpn_version;
        /* must register style and than enqueue */
        if( $this->wpn_is_page() ) {
            /*********** register and enqueue styles ***************/
            wp_register_style( 'wpn_admin_style_css',  WPN_CSS_URL.'wpn_admin_style.css', false, $wpn_version );
            wp_enqueue_style( 'wpn_admin_style_css' );


            /*********** register and enqueue scripts ***************/
            echo $this->wpn_admin_msg( 'script' );
            wp_register_script( 'wpn_admin_js', WPN_JS_URL.'wpn_admin_js.js?rand='.rand(1,9), 'jQuery', $wpn_version, true );
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'wpn_admin_js' );

        }
    }

    function wpn_front_enqueue_scripts() {
        global $wpn_version;
        // need to check here if its front section than enqueue script
        // if( $ncm_template_loader->ncm_is_front_page() ) {
        /*********** register and enqueue styles ***************/

            wp_register_style( 
                'wpn_front_css',  
                WPN_CSS_URL.'wpn_front.css?rand='.rand(1,999), 
                false, 
                $wpn_version 
            );

            wp_enqueue_style( 'wpn_front_css' );


            /*********** register and enqueue scripts ***************/
            echo "<script> var ajaxurl = '".admin_url( 'admin-ajax.php' )."'; </script>";

            wp_register_script( 
                'wpn_front_js', 
                WPN_JS_URL.'wpn_front.js?rand='.rand(1,999), 
                'jQuery', 
                $wpn_version, 
                true 
            );

            wp_enqueue_script( 'wpn_front_js' );
        // }
        
    }

    function wpn_route() {
        global $wpn, $wpn_settings;
        if( isset($_REQUEST['page']) && $_REQUEST['page'] != '' ){
            switch ( $_REQUEST['page'] ) {
                case $this->wpn_setting:
                    $wpn_settings->wpn_display_settings();
                    break;
            }
        }
    }

    function wpn_write_log( $content = '', $file_name = 'wpn_log.txt' ) {
        $file = __DIR__ . '/log/' . $file_name;    
        $file_content = "=============== Write At => " . date( "y-m-d H:i:s" ) . " =============== \r\n";
        $file_content .= $content . "\r\n\r\n";
        file_put_contents( $file, $file_content, FILE_APPEND | LOCK_EX );
    }
    
}


// begin!
global $wpn;
$wpn = new WoocommercePreorderNotification();

if( $wpn->wpn_is_activate() && file_exists( WPN_INCLUDES_DIR . "wpn_settings.class.php" ) ) {
    include_once( WPN_INCLUDES_DIR . "wpn_settings.class.php" );
}