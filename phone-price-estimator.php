<?php
/**
 * Plugin Name: Phone Price Estimator
 * ...
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Required classes
require_once plugin_dir_path(__FILE__) . 'includes/class-phone-price-estimator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ppe-admin-process.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ppe-admin-display.php';  // new
require_once plugin_dir_path(__FILE__) . 'includes/class-ppe-frontend.php';

if ( ! class_exists( 'Phone_Price_Estimator_Plugin' ) ) :

class Phone_Price_Estimator_Plugin {

    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate_plugin' ) );
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
    }

    public function activate_plugin() {
        Phone_Price_Estimator::create_database_tables();
    }

    public function deactivate_plugin() {
        // Phone_Price_Estimator::drop_database_tables(); // optional
    }

    public function init_plugin() {
        load_plugin_textdomain(
            'phone-price-estimator',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages/'
        );

        // Initialize main classes
        Phone_Price_Estimator::instance();
        PPE_Admin_Process::instance();   // logic
        PPE_Admin_Display::instance();   // UI
        PPE_Frontend::instance();        // shortcodes, front-end
    }
}

endif;

new Phone_Price_Estimator_Plugin();
