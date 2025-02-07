<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Phone_Price_Estimator
 *
 * Handles database setup, price calculations, and data access.
 */
class Phone_Price_Estimator {

    private static $instance = null;

    // Define the table name suffixes (we'll create these on activation).
    public static $devices_table      = 'phone_price_estimator_devices';
    public static $attributes_table   = 'phone_price_estimator_attributes';
    public static $attr_options_table = 'phone_price_estimator_attribute_options';

    /**
     * Get the singleton instance.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton.
     */
    private function __construct() {
        // Nothing special yet.
    }

    /**
     * Create required database tables on activation.
     */
    public static function create_database_tables() {
        global $wpdb;

        $devices_table   = $wpdb->prefix . self::$devices_table;
        $attributes_table= $wpdb->prefix . self::$attributes_table;
        $options_table   = $wpdb->prefix . self::$attr_options_table;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Devices table
        $sql_devices = "CREATE TABLE IF NOT EXISTS {$devices_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            device_name VARCHAR(255) NOT NULL,
            brand VARCHAR(255) DEFAULT '',
            base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (id)
        ) {$wpdb->get_charset_collate()};";
        dbDelta($sql_devices);

        // Attributes table
        $sql_attributes = "CREATE TABLE IF NOT EXISTS {$attributes_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            attribute_name VARCHAR(255) NOT NULL,
            discount_type ENUM('fixed','percentage') NOT NULL DEFAULT 'fixed',
            PRIMARY KEY (id)
        ) {$wpdb->get_charset_collate()};";
        dbDelta($sql_attributes);

        // Attribute options table
        $sql_options = "CREATE TABLE IF NOT EXISTS {$options_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            attribute_id BIGINT(20) UNSIGNED NOT NULL,
            option_label VARCHAR(255) NOT NULL,
            discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            INDEX (attribute_id)
        ) {$wpdb->get_charset_collate()};";
        dbDelta($sql_options);
    }

    /**
     * (Optional) Drop tables on deactivation/uninstall.
     */
    public static function drop_database_tables() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . self::$devices_table );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . self::$attributes_table );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . self::$attr_options_table );
    }

    /**
     * Get devices for auto-complete or listing.
     */
    public static function get_devices( $search_term = '' ) {
        global $wpdb;
        $devices_table = $wpdb->prefix . self::$devices_table;
        
        if ( ! empty( $search_term ) ) {
            $like = '%' . $wpdb->esc_like( $search_term ) . '%';
            $sql  = $wpdb->prepare(
                "SELECT * FROM $devices_table 
                 WHERE device_name LIKE %s 
                 ORDER BY device_name ASC", 
                $like
            );
        } else {
            $sql = "SELECT * FROM $devices_table ORDER BY device_name ASC";
        }
        return $wpdb->get_results($sql);
    }

    /**
     * Get all attributes with their options.
     */
    public static function get_all_attributes() {
        global $wpdb;
        $attributes_table = $wpdb->prefix . self::$attributes_table;
        $options_table    = $wpdb->prefix . self::$attr_options_table;

        // Get attributes
        $attributes = $wpdb->get_results("SELECT * FROM $attributes_table");
        if ( ! $attributes ) {
            return array();
        }
        // For each attribute, get options
        foreach ( $attributes as $attr ) {
            $attr->options = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $options_table WHERE attribute_id = %d ORDER BY id ASC",
                    $attr->id
                )
            );
        }
        return $attributes;
    }

    /**
     * Calculate the final price given the device and user-selected options.
     */
    public static function calculate_final_price( $device_id, $selected_options = array() ) {
        global $wpdb;
        $devices_table = $wpdb->prefix . self::$devices_table;

        // Fetch device
        $device = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $devices_table WHERE id = %d",
                $device_id
            )
        );

        if ( ! $device ) {
            return 0; // Device not found
        }

        $base_price  = floatval( $device->base_price );
        $final_price = $base_price;

        // Each selected_option is an array: [ 'attribute_id' => X, 'option_id' => Y ]
        foreach ( $selected_options as $attribute_id => $option_id ) {
            // Get attribute + option
            $attribute = self::get_attribute_by_id( $attribute_id );
            if ( $attribute ) {
                $option = self::get_option_by_id( $option_id );
                if ( $option ) {
                    $discount_type  = $attribute->discount_type;
                    $discount_value = floatval( $option->discount_value );

                    if ( $discount_type === 'fixed' ) {
                        // Subtract fixed amount
                        $final_price -= $discount_value;
                    } else {
                        // Percentage discount
                        $percentage = $discount_value / 100; 
                        $final_price -= ( $base_price * $percentage );
                    }
                }
            }
        }

        // Ensure final price is never below 0
        $final_price = max( $final_price, 0 );

        return $final_price;
    }

    /**
     * Get a single attribute by ID (with discount_type).
     */
    public static function get_attribute_by_id( $attribute_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::$attributes_table;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $attribute_id)
        );
    }

    /**
     * Get a single option by ID (with discount_value).
     */
    public static function get_option_by_id( $option_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::$attr_options_table;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $option_id)
        );
    }
}
