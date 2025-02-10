<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class PPE_Admin_Display
 *
 * Handles the Admin UI: devices, attributes, evaluation criteria, import/export, etc.
 */
if ( ! class_exists( 'PPE_Admin_Display' ) ) :

class PPE_Admin_Display {

    /**
     * Singleton instance.
     *
     * @var PPE_Admin|null
     */
    private static $instance = null;
    private $devices_table;
    private $attributes_table;
    private $attr_options_table;

    /**
     * Singleton instance.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor: sets up admin menus & table references.
     */
    private function __construct() {
        global $wpdb;
        $this->devices_table      = $wpdb->prefix . Phone_Price_Estimator::$devices_table;
        $this->attributes_table   = $wpdb->prefix . Phone_Price_Estimator::$attributes_table;
        $this->attr_options_table = $wpdb->prefix . Phone_Price_Estimator::$attr_options_table;

        // Hook in admin menus/pages
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
    }

    /**
     * Add top-level & submenus
     */
    public function register_menus() {
        // Main menu: "Phone Estimator"
        add_menu_page(
            __( 'Phone Estimator', 'phone-price-estimator' ),
            __( 'Phone Estimator', 'phone-price-estimator' ),
            'manage_options',
            'ppe-main-menu',
            array( $this, 'admin_devices_page' ),
            'dashicons-admin-tools'
        );

        // Submenu for Devices (already handled by top-level callback)
        add_submenu_page(
            'ppe-main-menu',
            __( 'Devices', 'phone-price-estimator' ),
            __( 'Devices', 'phone-price-estimator' ),
            'manage_options',
            'ppe-main-menu',
            array( $this, 'admin_devices_page' )
        );

        // Submenu for Attributes
        add_submenu_page(
            'ppe-main-menu',
            __( 'Attributes', 'phone-price-estimator' ),
            __( 'Attributes', 'phone-price-estimator' ),
            'manage_options',
            'ppe-attributes',
            array( $this, 'admin_attributes_page' )
        );

        // Submenu for Import/Export
        add_submenu_page(
            'ppe-main-menu',
            __( 'Import/Export', 'phone-price-estimator' ),
            __( 'Import/Export', 'phone-price-estimator' ),
            'manage_options',
            'ppe-import-export',
            array( $this, 'admin_import_export_page' )
        );
    }

    /**
     * Admin page callback: Devices (stub for demonstration).
     */
    public function admin_devices_page() {
        echo '<div class="wrap"><h1>Devices Management</h1>';
        echo '<p>Here you would list/add/edit devices.</p></div>';
    }

    /**
     * Admin page callback: Attributes (stub for demonstration).
     */
    public function admin_attributes_page() {
        echo '<div class="wrap"><h1>Attributes Management</h1>';
        echo '<p>Here you would list/add/edit attributes and their options.</p></div>';
    }

    /**
     * Admin page callback: Import/Export
     */
    public function admin_import_export_page() {
        // Handle Export
        if ( isset($_POST['ppe_export_csv']) && check_admin_referer('ppe_export_csv_nonce', 'ppe_export_csv_nonce') ) {
            $this->export_csv();
        }

        // Handle Import
        if ( isset($_POST['ppe_import_csv']) && check_admin_referer('ppe_import_csv_nonce', 'ppe_import_csv_nonce') ) {
            $file = $_FILES['ppe_csv_file']['tmp_name'];
            $this->import_csv($file);
            echo '<div class="updated notice"><p>Import complete!</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>Import/Export</h1>

            <h2>Export Data</h2>
            <form method="post">
                <?php wp_nonce_field( 'ppe_export_csv_nonce', 'ppe_export_csv_nonce' ); ?>
                <input type="submit" name="ppe_export_csv" class="button button-primary" value="<?php esc_attr_e( 'Export as CSV', 'phone-price-estimator' ); ?>">
            </form>
            <hr>

            <h2>Import Data</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'ppe_import_csv_nonce', 'ppe_import_csv_nonce' ); ?>
                <input type="file" name="ppe_csv_file" accept=".csv" required>
                <input type="submit" name="ppe_import_csv" class="button button-primary" value="Import CSV">
            </form>
        </div>
        <?php
    }

    /**
     * Export CSV
     */
    private function export_csv() {
        global $wpdb;

        // Grab all data
        $devices      = $wpdb->get_results("SELECT * FROM {$this->devices_table}");
        $attributes   = $wpdb->get_results("SELECT * FROM {$this->attributes_table}");
        $attr_options = $wpdb->get_results("SELECT * FROM {$this->attr_options_table}");

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=phone_estimator_export.csv');

        $output = fopen('php://output', 'w');

        // Combined header row
        fputcsv($output, [
            'type', 
            'device_name', 
            'brand', 
            'base_price', 
            'attribute_name', 
            'discount_type', 
            'option_label', 
            'discount_value',
            'applicable_brands',
            'active',
            'attribute_id'
        ) );

        // Export devices
        if ( $devices ) {
            foreach ( $devices as $dev ) {
                fputcsv( $output, array(
                    'Device',
                    $dev->device_name,
                    $dev->brand,
                    $dev->base_price,
                    '',
                    '',
                    '',
                    '',
                    ''
                ) );
            }
        }

        // Export each attribute + its options
        if ( $attributes ) {
            foreach ( $attributes as $att ) {
                if ( $attr_options ) {
                    // for each option that belongs to this attribute
                    foreach ( $attr_options as $opt ) {
                        if ( $opt->attribute_id == $att->id ) {
                            fputcsv($output, [
                                'Attribute Option',
                                '',
                                '',
                                '',
                                $att->attribute_name,
                                $att->discount_type,
                                $opt->option_label,
                                $opt->discount_value,
                                $att->id
                            ]);
                        }
                    }
                }
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Import CSV
     */
    private function import_csv($file) {
        global $wpdb;

        if ( ! file_exists($file) || ! is_readable($file) ) {
            error_log("Error: File not found or not readable: " . $file);
            return;
        }

        $handle = fopen($file, 'r');
        if ( ! $handle ) {
            error_log("Error: Could not open file: " . $file);
            return;
        }

        // Skip header row
        fgetcsv($handle);

        $imported_count = 0;

        while ( ( $row = fgetcsv($handle, 1000, ',') ) !== false ) {
            $type = isset($row[0]) ? trim($row[0]) : '';

            switch ($type) {
                case 'Device':
                    $device_name = isset($row[1]) ? sanitize_text_field($row[1]) : '';
                    $brand       = isset($row[2]) ? sanitize_text_field($row[2]) : '';
                    $base_price  = isset($row[3]) ? floatval($row[3]) : 0;

                    if ( ! empty($device_name) ) {
                        $wpdb->insert($this->devices_table, [
                            'device_name' => $device_name,
                            'brand'       => $brand,
                            'base_price'  => $base_price,
                        ]);
                        $imported_count++;
                    }
                    break;

                case 'Attribute Option':
                    $attribute_name  = isset($row[4]) ? sanitize_text_field($row[4]) : '';
                    $discount_type   = isset($row[5]) ? sanitize_text_field($row[5]) : 'fixed';
                    $option_label    = isset($row[6]) ? sanitize_text_field($row[6]) : '';
                    $discount_value  = isset($row[7]) ? floatval($row[7]) : 0;
                    $attribute_id_csv= isset($row[8]) ? intval($row[8]) : null;

                    // 1. Find or create the attribute:
                    $attribute_id = null;

                    if ( $attribute_id_csv ) {
                        // If the CSV line claims a specific attribute ID
                        $existing_attribute = $wpdb->get_row(
                            $wpdb->prepare("SELECT * FROM {$this->attributes_table} WHERE id = %d", $attribute_id_csv)
                        );
                        if ( $existing_attribute ) {
                            $attribute_id = $existing_attribute->id;
                        } else {
                            // That attribute ID doesn't exist
                            error_log("Error: Attribute with ID $attribute_id_csv not found. Skipping option: " . $option_label);
                            continue;
                        }
                    } else {
                        // or use the attribute_name
                        $existing_attribute = $wpdb->get_row(
                            $wpdb->prepare("SELECT * FROM {$this->attributes_table} WHERE attribute_name = %s", $attribute_name)
                        );
                        if ( $existing_attribute ) {
                            $attribute_id = $existing_attribute->id;
                        } else {
                            // create a new attribute
                            $wpdb->insert($this->attributes_table, [
                                'attribute_name' => $attribute_name,
                                'discount_type'  => $discount_type,
                            ]);
                            $attribute_id = $wpdb->insert_id;
                        }
                    }

                    // 2. Insert the attribute option (only if attribute_id is found)
                    if ( $attribute_id ) {
                        $wpdb->insert($this->attr_options_table, [
                            'attribute_id'  => $attribute_id,
                            'option_label'  => $option_label,
                            'discount_value'=> $discount_value,
                        ]);
                        $imported_count++;
                    }
                    break;

                default:
                    // Unknown row type, skip or log as needed.
                    break;
            }
        }

        fclose($handle);

        // Provide feedback:
        echo '<div class="updated notice"><p>' . $imported_count . ' rows imported.</p></div>';
    }

}

endif; // end if class_exists
