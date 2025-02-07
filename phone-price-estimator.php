<?php
/**
 * Plugin Name: Phone Price Estimator
 * Plugin URI:  https://example.com
 * Description: Estimate phone trade-in values based on dynamic conditions.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * Text Domain: phone-price-estimator
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Require our main classes.
require_once plugin_dir_path(__FILE__) . 'includes/class-phone-price-estimator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ppe-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ppe-frontend.php';

if ( ! class_exists( 'Phone_Price_Estimator_Plugin' ) ) :

class Phone_Price_Estimator_Plugin {

    /**
     * Constructor: Hook into WordPress
     */
    public function __construct() {

        // Activation / Deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate_plugin' ) );

        // Initialize plugin (after all plugins loaded)
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
    }

    /**
     * Run on plugin activation
     */
    public function activate_plugin() {
        // Create or upgrade DB tables, etc.
        Phone_Price_Estimator::create_database_tables();
    }

    /**
     * Run on plugin deactivation
     */
    public function deactivate_plugin() {
        // Optionally drop tables or keep data
        // Phone_Price_Estimator::drop_database_tables();
    }

    /**
     * Initialize the plugin
     */
    public function init_plugin() {
        // Load text domain for i18n
        load_plugin_textdomain( 
            'phone-price-estimator',
            false, 
            dirname( plugin_basename( __FILE__ ) ) . '/languages/'
        );

        // Initialize main classes
        Phone_Price_Estimator::instance();
        PPE_Admin::instance();
        PPE_Frontend::instance();
    }
}

endif; // end if class_exists

// Instantiate the main plugin class.
new Phone_Price_Estimator_Plugin();
