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

    // Table name suffixes.
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

    private function __construct() {
        // Nothing special here.
    }

    /**
     * Create required database tables on activation.
     */
    public static function create_database_tables() {
        global $wpdb;

        $devices_table    = $wpdb->prefix . self::$devices_table;
        $attributes_table = $wpdb->prefix . self::$attributes_table;
        $options_table    = $wpdb->prefix . self::$attr_options_table;
        $criteria_table   = $wpdb->prefix . 'phone_price_estimator_criteria';

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
            phone_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            attribute_name VARCHAR(255) NOT NULL,
            discount_type ENUM('fixed','percentage') NOT NULL DEFAULT 'fixed',
            PRIMARY KEY (id),
            INDEX (phone_id)
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

        // New: Evaluation Criteria table
        $sql_criteria = "CREATE TABLE IF NOT EXISTS {$criteria_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            criteria_text VARCHAR(255) NOT NULL,
            discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            applicable_brands TEXT NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) {$wpdb->get_charset_collate()};";
        dbDelta($sql_criteria);
    }

    /**
     * (Optional) Drop tables on deactivation/uninstall.
     */
    public static function drop_database_tables() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . self::$devices_table );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . self::$attributes_table );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . self::$attr_options_table );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}phone_price_estimator_criteria" );
    }

    /**
     * Get devices for auto-complete or listing.
     */
    public static function get_devices( $search_term = '' ) {
        global $wpdb;
        $devices_table = $wpdb->prefix . self::$devices_table;

        $cache_key = 'ppe_devices_' . md5($search_term);
        $devices   = get_transient($cache_key);

        if ( false === $devices ) {
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
            $devices = $wpdb->get_results($sql);
            
    $devices = array_map(function($device) {
        return (object) array(
            'id' => intval($device->id),
            'device_name' => esc_html($device->device_name),
            'brand' => esc_html($device->brand),
        );
    }, $devices);
    set_transient($cache_key, $devices, 3600);
    
        }

        return $devices;
    }

    /**
     * Clear any devices cache.
     */
    public static function clear_devices_cache() {
        global $wpdb;
        $transients = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%_transient_ppe_devices_%'");
        foreach ( $transients as $option_name ) {
            $transient_key = str_replace('_transient_', '', $option_name);
            delete_transient($transient_key);
        }
    }

    /**
     * (Optional) Clear criteria cache.
     *
     * Dacă folosești transient caching pentru criterii, implementează aici.
     */
    public static function clear_criteria_cache() {
        // Pentru moment, nu avem caching dedicat pentru criterii.
        // Poți adăuga ștergerea transientelor aferente, dacă implementezi caching.
    }

    /**
     * Get attributes and options for a given device.
     */
    public static function get_attributes_by_device( $device_id ) {
        global $wpdb;
        $attributes_table = $wpdb->prefix . self::$attributes_table;
        $options_table    = $wpdb->prefix . self::$attr_options_table;

        $sql = $wpdb->prepare(
            "SELECT * FROM $attributes_table
             WHERE phone_id = %d
             ORDER BY id ASC",
            $device_id
        );
        $attributes = $wpdb->get_results($sql);
        if ( ! $attributes ) {
            return array();
        }
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
     * Calculate final price using submitted evaluation criteria.
     */
    public static function calculate_final_price( $device_id, $selected_criteria = array() ) {
        global $wpdb;
        $devices_table = $wpdb->prefix . self::$devices_table;

        // Fetch device
        $device = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $devices_table WHERE id = %d", $device_id ) );
        if ( ! $device ) {
            return 0; // Device not found
        }

        $base_price  = (float) $device->base_price;
        $final_price = $base_price;

        // Pentru fiecare criteriu cu răspuns "yes", scade discount-ul asociat
        foreach ( $selected_criteria as $criteria_id => $answer ) {
            if ( strtolower($answer) === 'yes' ) {
                $criterion = $wpdb->get_row( $wpdb->prepare( "SELECT discount_value FROM {$wpdb->prefix}phone_price_estimator_criteria WHERE id = %d", $criteria_id ) );
                if ( $criterion ) {
                    $final_price -= (float) $criterion->discount_value;
                }
            }
        }
        return max( $final_price, 0 );
    }

    /**
     * Get evaluation criteria by brand.
     *
     * Returnează rândurile unde applicable_brands este "All" sau conține brand-ul dat.
     */
    public static function get_criteria_by_brand( $brand ) {
        global $wpdb;
        $criteria_table = $wpdb->prefix . 'phone_price_estimator_criteria';
        $sql = $wpdb->prepare(
            "SELECT * FROM $criteria_table 
             WHERE active = 1 AND (applicable_brands = %s OR applicable_brands LIKE %s)
             ORDER BY id ASC",
             'All', '%' . $wpdb->esc_like( $brand ) . '%'
        );
        error_log('Criteria request for brand: ' . $brand);
        error_log('SQL query: ' . $sql);
        return $wpdb->get_results($sql);
    }

    // Alte metode (legacy) rămân neschimbate.
}