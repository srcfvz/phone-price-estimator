<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class PPE_Admin
 *
 * Handles WP Admin pages for devices, attributes, and Import/Export.
 */
if ( ! class_exists( 'PPE_Admin' ) ) :

class PPE_Admin {

    /**
     * Singleton instance.
     *
     * @var PPE_Admin|null
     */
    private static $instance = null;

    // Store actual table names (with prefix) for easy reuse.
    private $devices_table;
    private $attributes_table;
    private $attr_options_table;

    /**
     * Get the singleton instance.
     *
     * @return PPE_Admin
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor: sets up admin menus & table references.
     */
    private function __construct() {
        global $wpdb;

        // Build real table names from static suffixes in Phone_Price_Estimator.
        $this->devices_table      = $wpdb->prefix . Phone_Price_Estimator::$devices_table;
        $this->attributes_table   = $wpdb->prefix . Phone_Price_Estimator::$attributes_table;
        $this->attr_options_table = $wpdb->prefix . Phone_Price_Estimator::$attr_options_table;

        // Hook in admin menus/pages.
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
    }

    /**
     * Register top-level and submenus.
     */
    public function register_menus() {
        // Main menu: "Phone Estimator".
        add_menu_page(
            __( 'Phone Estimator', 'phone-price-estimator' ),
            __( 'Phone Estimator', 'phone-price-estimator' ),
            'manage_options',
            'ppe-main-menu',
            array( $this, 'admin_devices_page' ),
            'dashicons-admin-tools'
        );

        // Submenu for Devices (reusing the main page callback).
        add_submenu_page(
            'ppe-main-menu',
            __( 'Devices', 'phone-price-estimator' ),
            __( 'Devices', 'phone-price-estimator' ),
            'manage_options',
            'ppe-main-menu',
            array( $this, 'admin_devices_page' )
        );

        // Submenu for Attributes.
        add_submenu_page(
            'ppe-main-menu',
            __( 'Attributes', 'phone-price-estimator' ),
            __( 'Attributes', 'phone-price-estimator' ),
            'manage_options',
            'ppe-attributes',
            array( $this, 'admin_attributes_page' )
        );

        // Submenu for Import/Export.
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
     * Admin page callback: Devices Management.
     */
    public function admin_devices_page() {
        global $wpdb;
        $devices = $wpdb->get_results( "SELECT * FROM {$this->devices_table} ORDER BY device_name ASC" );

        echo '<div class="wrap"><h1>' . esc_html__( 'Devices Management', 'phone-price-estimator' ) . '</h1>';

        if ( $devices ) {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'ID', 'phone-price-estimator' ) . '</th>';
            echo '<th>' . esc_html__( 'Device Name', 'phone-price-estimator' ) . '</th>';
            echo '<th>' . esc_html__( 'Brand', 'phone-price-estimator' ) . '</th>';
            echo '<th>' . esc_html__( 'Base Price', 'phone-price-estimator' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $devices as $device ) {
                echo '<tr>';
                echo '<td>' . esc_html( $device->id ) . '</td>';
                echo '<td>' . esc_html( $device->device_name ) . '</td>';
                echo '<td>' . esc_html( $device->brand ) . '</td>';
                echo '<td>' . esc_html( $device->base_price ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'No devices found.', 'phone-price-estimator' ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Admin page callback: Attributes Management.
     */
    public function admin_attributes_page() {
        global $wpdb;
        $attributes = $wpdb->get_results( "SELECT * FROM {$this->attributes_table} ORDER BY attribute_name ASC" );

        echo '<div class="wrap"><h1>' . esc_html__( 'Attributes Management', 'phone-price-estimator' ) . '</h1>';

        if ( $attributes ) {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'ID', 'phone-price-estimator' ) . '</th>';
            echo '<th>' . esc_html__( 'Attribute Name', 'phone-price-estimator' ) . '</th>';
            echo '<th>' . esc_html__( 'Discount Type', 'phone-price-estimator' ) . '</th>';
            echo '<th>' . esc_html__( 'Options', 'phone-price-estimator' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $attributes as $attribute ) {
                // Retrieve options for the current attribute.
                $options = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM {$this->attr_options_table} WHERE attribute_id = %d ORDER BY id ASC",
                    $attribute->id
                ) );

                if ( $options ) {
                    $options_list = array();
                    foreach ( $options as $option ) {
                        $options_list[] = sprintf( '%s (%s)', esc_html( $option->option_label ), esc_html( $option->discount_value ) );
                    }
                    $options_html = implode( ', ', $options_list );
                } else {
                    $options_html = esc_html__( 'No options', 'phone-price-estimator' );
                }

                echo '<tr>';
                echo '<td>' . esc_html( $attribute->id ) . '</td>';
                echo '<td>' . esc_html( $attribute->attribute_name ) . '</td>';
                echo '<td>' . esc_html( $attribute->discount_type ) . '</td>';
                echo '<td>' . esc_html( $options_html ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'No attributes found.', 'phone-price-estimator' ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Admin page callback: Import/Export.
     */
    public function admin_import_export_page() {
        // Handle Export.
        if ( isset( $_POST['ppe_export_csv'] ) && check_admin_referer( 'ppe_export_csv_nonce', 'ppe_export_csv_nonce' ) ) {
            $this->export_csv();
        }

        // Handle Import.
        if ( isset( $_POST['ppe_import_csv'] ) && check_admin_referer( 'ppe_import_csv_nonce', 'ppe_import_csv_nonce' ) ) {
            $tmp_file = $_FILES['ppe_csv_file']['tmp_name'];

            // Determine the upload directory for the CSV.
            $upload_dir = wp_upload_dir();
            $target_dir = trailingslashit( $upload_dir['basedir'] ) . 'phone-estimator/';

            // Create the directory if it doesn't exist.
            if ( ! file_exists( $target_dir ) ) {
                wp_mkdir_p( $target_dir );
            }

            // Define a target file name.
            $file_name   = sanitize_file_name( basename( $_FILES['ppe_csv_file']['name'] ) );
            $timestamp   = current_time( 'YmdHis' );
            $target_file = $target_dir . $timestamp . '-' . $file_name;

            // Move the uploaded file from the temporary location to your permanent folder.
            if ( move_uploaded_file( $tmp_file, $target_file ) ) {
                // Process the CSV file from the new location.
                $this->import_csv( $target_file );
                echo '<div class="updated notice"><p>' . esc_html__( 'Import complete! Data appended and file saved at:', 'phone-price-estimator' ) . ' ' . esc_html( $target_file ) . '</p></div>';
            } else {
                echo '<div class="error notice"><p>' . esc_html__( 'Failed to move the uploaded CSV file.', 'phone-price-estimator' ) . '</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import/Export', 'phone-price-estimator' ); ?></h1>

            <h2><?php esc_html_e( 'Export Data', 'phone-price-estimator' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'ppe_export_csv_nonce', 'ppe_export_csv_nonce' ); ?>
                <input type="submit" name="ppe_export_csv" class="button button-primary" value="<?php esc_attr_e( 'Export as CSV', 'phone-price-estimator' ); ?>">
            </form>

            <hr>

            <h2><?php esc_html_e( 'Import Data', 'phone-price-estimator' ); ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'ppe_import_csv_nonce', 'ppe_import_csv_nonce' ); ?>
                <input type="file" name="ppe_csv_file" accept=".csv" required>
                <input type="submit" name="ppe_import_csv" class="button button-primary" value="<?php esc_attr_e( 'Import CSV', 'phone-price-estimator' ); ?>">
            </form>
        </div>
        <?php
    }

    /**
     * Export CSV.
     */
    private function export_csv() {
        global $wpdb;

        // Retrieve all data.
        $devices      = $wpdb->get_results( "SELECT * FROM {$this->devices_table}" );
        $attributes   = $wpdb->get_results( "SELECT * FROM {$this->attributes_table}" );
        $attr_options = $wpdb->get_results( "SELECT * FROM {$this->attr_options_table}" );

        // Set headers for CSV download.
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=phone_estimator_export_' . current_time( 'YmdHis' ) . '.csv' );

        $output = fopen( 'php://output', 'w' );

        // Combined header row.
        fputcsv( $output, array(
            'type',
            'device_name',
            'brand',
            'base_price',
            'attribute_name',
            'discount_type',
            'option_label',
            'discount_value',
            'attribute_id'
        ) );

        // Export devices.
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

        // Export attributes and their options.
        if ( $attributes ) {
            foreach ( $attributes as $att ) {
                // Gather options for the attribute.
                if ( $attr_options ) {
                    foreach ( $attr_options as $opt ) {
                        if ( $opt->attribute_id == $att->id ) {
                            fputcsv( $output, array(
                                'Attribute Option',
                                '',
                                '',
                                '',
                                $att->attribute_name,
                                $att->discount_type,
                                $opt->option_label,
                                $opt->discount_value,
                                $att->id
                            ) );
                        }
                    }
                } else {
                    // In case there are no options, still export the attribute.
                    fputcsv( $output, array(
                        'Attribute',
                        '',
                        '',
                        '',
                        $att->attribute_name,
                        $att->discount_type,
                        '',
                        '',
                        $att->id
                    ) );
                }
            }
        }

        fclose( $output );
        exit;
    }

    /**
     * Import CSV.
     *
     * Reads the CSV file line by line and appends its data to the database.
     *
     * @param string $file Path to the CSV file.
     */
    private function import_csv( $file ) {
        global $wpdb;

        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            error_log( "Error: File not found or not readable: " . $file );
            return;
        }

        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            error_log( "Error: Could not open file: " . $file );
            return;
        }

        // Skip header row.
        fgetcsv( $handle );

        $imported_count = 0;

        while ( ( $row = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
            $type = isset( $row[0] ) ? trim( $row[0] ) : '';

            switch ( $type ) {
                case 'Device':
                    $device_name = isset( $row[1] ) ? sanitize_text_field( $row[1] ) : '';
                    $brand       = isset( $row[2] ) ? sanitize_text_field( $row[2] ) : '';
                    $base_price  = isset( $row[3] ) ? floatval( $row[3] ) : 0;

                    if ( ! empty( $device_name ) ) {
                        $wpdb->insert( $this->devices_table, array(
                            'device_name' => $device_name,
                            'brand'       => $brand,
                            'base_price'  => $base_price,
                        ) );
                        $imported_count++;
                    }
                    break;

                case 'Attribute Option':
                    $attribute_name   = isset( $row[4] ) ? sanitize_text_field( $row[4] ) : '';
                    $discount_type    = isset( $row[5] ) ? sanitize_text_field( $row[5] ) : 'fixed';
                    $option_label     = isset( $row[6] ) ? sanitize_text_field( $row[6] ) : '';
                    $discount_value   = isset( $row[7] ) ? floatval( $row[7] ) : 0;
                    $attribute_id_csv = isset( $row[8] ) ? intval( $row[8] ) : null;

                    // Find or create the attribute.
                    $attribute_id = null;

                    if ( $attribute_id_csv ) {
                        // Check if the attribute exists by ID.
                        $existing_attribute = $wpdb->get_row( $wpdb->prepare(
                            "SELECT * FROM {$this->attributes_table} WHERE id = %d",
                            $attribute_id_csv
                        ) );
                        if ( $existing_attribute ) {
                            $attribute_id = $existing_attribute->id;
                        } else {
                            error_log( "Error: Attribute with ID $attribute_id_csv not found. Skipping option: " . $option_label );
                            continue;
                        }
                    } else {
                        // Find the attribute by name.
                        $existing_attribute = $wpdb->get_row( $wpdb->prepare(
                            "SELECT * FROM {$this->attributes_table} WHERE attribute_name = %s",
                            $attribute_name
                        ) );
                        if ( $existing_attribute ) {
                            $attribute_id = $existing_attribute->id;
                        } else {
                            // Create a new attribute.
                            $wpdb->insert( $this->attributes_table, array(
                                'attribute_name' => $attribute_name,
                                'discount_type'  => $discount_type,
                            ) );
                            $attribute_id = $wpdb->insert_id;
                        }
                    }

                    // Insert the attribute option if an attribute ID is available.
                    if ( $attribute_id ) {
                        $wpdb->insert( $this->attr_options_table, array(
                            'attribute_id'   => $attribute_id,
                            'option_label'   => $option_label,
                            'discount_value' => $discount_value,
                        ) );
                        $imported_count++;
                    }
                    break;

                default:
                    // Unknown row type; skip.
                    break;
            }
        }

        fclose( $handle );

        echo '<div class="updated notice"><p>' . esc_html( $imported_count ) . ' rows imported.</p></div>';
    }
}

endif; // end if class_exists
