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

    private static $instance = null;
    private $devices_table;
    private $attributes_table;
    private $attr_options_table;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->devices_table      = $wpdb->prefix . Phone_Price_Estimator::$devices_table;
        $this->attributes_table   = $wpdb->prefix . Phone_Price_Estimator::$attributes_table;
        $this->attr_options_table = $wpdb->prefix . Phone_Price_Estimator::$attr_options_table;

        add_action( 'admin_menu', array( $this, 'register_menus' ) );
    }

    public function register_menus() {
        add_menu_page(
            __( 'Phone Estimator', 'phone-price-estimator' ),
            __( 'Phone Estimator', 'phone-price-estimator' ),
            'manage_options',
            'ppe-main-menu',
            array( $this, 'admin_devices_page' ),
            'dashicons-admin-tools'
        );

        add_submenu_page(
            'ppe-main-menu',
            __( 'Devices', 'phone-price-estimator' ),
            __( 'Devices', 'phone-price-estimator' ),
            'manage_options',
            'ppe-main-menu',
            array( $this, 'admin_devices_page' )
        );

        add_submenu_page(
            'ppe-main-menu',
            __( 'Attributes', 'phone-price-estimator' ),
            __( 'Attributes', 'phone-price-estimator' ),
            'manage_options',
            'ppe-attributes',
            array( $this, 'admin_attributes_page' )
        );

        add_submenu_page(
            'ppe-main-menu',
            __( 'Evaluation Criteria', 'phone-price-estimator' ),
            __( 'Evaluation Criteria', 'phone-price-estimator' ),
            'manage_options',
            'ppe-criteria',
            array( $this, 'admin_criteria_page' )
        );

        add_submenu_page(
            'ppe-main-menu',
            __( 'Import/Export', 'phone-price-estimator' ),
            __( 'Import/Export', 'phone-price-estimator' ),
            'manage_options',
            'ppe-import-export',
            array( $this, 'admin_import_export_page' )
        );

        add_submenu_page(
            'ppe-main-menu',
            __( 'Edit Device', 'phone-price-estimator' ),
            __( 'Edit Device', 'phone-price-estimator' ),
            'manage_options',
            'ppe-edit-device',
            array( $this, 'admin_edit_device_page' )
        );
    }

    public function admin_devices_page() {
        global $wpdb;
        $devices = $wpdb->get_results( "SELECT * FROM {$this->devices_table} ORDER BY device_name ASC" );
        echo '<div class="wrap"><h1>' . esc_html__( 'Devices Management', 'phone-price-estimator' ) . '</h1>';
        if ( $devices ) {
            echo '<table class="widefat fixed striped"><thead><tr>';
            echo '<th>' . esc_html__( 'ID', 'phone-price-estimator' ) . '</th>';
            echo '<th>' . esc_html__( 'Device Name', 'phone-price-estimator' ) . '</th>';
            echo '<th>' . esc_html__( 'Brand', 'phone-price-estimator' ) . '</th>';
            echo '<th>' . esc_html__( 'Base Price', 'phone-price-estimator' ) . '</th>';
            echo '<th>' . esc_html__( 'Actions', 'phone-price-estimator' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $devices as $device ) {
                $edit_url = add_query_arg(
                    array( 'page' => 'ppe-edit-device', 'device_id' => $device->id ),
                    admin_url( 'admin.php' )
                );
                echo '<tr>';
                echo '<td>' . esc_html( $device->id ) . '</td>';
                echo '<td>' . esc_html( $device->device_name ) . '</td>';
                echo '<td>' . esc_html( $device->brand ) . '</td>';
                echo '<td>' . esc_html( $device->base_price ) . '</td>';
                echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'phone-price-estimator' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'No devices found.', 'phone-price-estimator' ) . '</p>';
        }
        echo '</div>';
    }

    public function admin_attributes_page() {
        // Placeholder for attributes management interface.
        echo '<div class="wrap"><h1>' . esc_html__( 'Attributes Management', 'phone-price-estimator' ) . '</h1>';
        echo '<p>' . esc_html__( 'Attributes management interface goes here.', 'phone-price-estimator' ) . '</p>';
        echo '</div>';
    }

    public function admin_criteria_page() {
        global $wpdb;

        // Handle deletion
        if ( isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['criterion_id']) ) {
            $criterion_id = intval($_GET['criterion_id']);
            if ( !isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'ppe_delete_criterion_nonce') ) {
                wp_die(__('Nonce verification failed', 'phone-price-estimator'));
            }
            $wpdb->delete($wpdb->prefix . 'phone_price_estimator_criteria', array('id' => $criterion_id), array('%d'));
            echo '<div class="updated notice"><p>' . esc_html__('Criterion deleted successfully.', 'phone-price-estimator') . '</p></div>';
        }

        // Handle editing
        if ( isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['criterion_id']) ) {
            $criterion_id = intval($_GET['criterion_id']);
            $criterion = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}phone_price_estimator_criteria WHERE id = %d", $criterion_id));
            if ( $criterion ) {
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_criterion_nonce'])) {
                    check_admin_referer('ppe_edit_criterion_nonce', 'edit_criterion_nonce');
                    $criteria_text = isset($_POST['criteria_text']) ? sanitize_text_field($_POST['criteria_text']) : '';
                    $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
                    $applicable_brands = isset($_POST['applicable_brands']) ? sanitize_text_field($_POST['applicable_brands']) : '';
                    $active = isset($_POST['active']) ? 1 : 0;
                    if (!empty($criteria_text) && !empty($applicable_brands)) {
                        $wpdb->update($wpdb->prefix . 'phone_price_estimator_criteria',
                            array(
                                'criteria_text' => $criteria_text,
                                'discount_value' => $discount_value,
                                'applicable_brands' => $applicable_brands,
                                'active' => $active,
                            ),
                            array('id' => $criterion_id),
                            array('%s','%f','%s','%d'),
                            array('%d')
                        );
                        echo '<div class="updated notice"><p>' . esc_html__('Criterion updated successfully.', 'phone-price-estimator') . '</p></div>';
                    } else {
                        echo '<div class="error notice"><p>' . esc_html__('Please fill in all required fields.', 'phone-price-estimator') . '</p></div>';
                    }
                    $criterion = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}phone_price_estimator_criteria WHERE id = %d", $criterion_id));
                }
                echo '<div class="wrap">';
                echo '<h1>' . esc_html__('Edit Criterion', 'phone-price-estimator') . '</h1>';
                echo '<form method="post">';
                wp_nonce_field('ppe_edit_criterion_nonce', 'edit_criterion_nonce');
                echo '<table class="form-table">';
                echo '<tr><th scope="row"><label for="criteria_text">' . esc_html__('Criterion Text', 'phone-price-estimator') . '</label></th>';
                echo '<td><input type="text" id="criteria_text" name="criteria_text" value="' . esc_attr($criterion->criteria_text) . '" class="regular-text" required></td></tr>';
                echo '<tr><th scope="row"><label for="discount_value">' . esc_html__('Discount Value', 'phone-price-estimator') . '</label></th>';
                echo '<td><input type="number" step="0.01" id="discount_value" name="discount_value" value="' . esc_attr($criterion->discount_value) . '" class="regular-text" required></td></tr>';
                echo '<tr><th scope="row"><label for="applicable_brands">' . esc_html__('Applicable Brands (comma separated)', 'phone-price-estimator') . '</label></th>';
                echo '<td><input type="text" id="applicable_brands" name="applicable_brands" value="' . esc_attr($criterion->applicable_brands) . '" class="regular-text" required></td></tr>';
                echo '<tr><th scope="row">' . esc_html__('Active', 'phone-price-estimator') . '</th>';
                echo '<td><input type="checkbox" id="active" name="active" value="1" ' . checked($criterion->active, 1, false) . '></td></tr>';
                echo '</table>';
                submit_button(__('Update Criterion', 'phone-price-estimator'));
                echo '</form>';
                echo '</div>';
                return;
            } else {
                echo '<div class="error notice"><p>' . esc_html__('Criterion not found.', 'phone-price-estimator') . '</p></div>';
            }
        }

        // Default view: add new criterion and list existing ones
        echo '<div class="wrap"><h1>' . esc_html__('Evaluation Criteria', 'phone-price-estimator') . '</h1>';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_criterion_nonce'])) {
            check_admin_referer('ppe_new_criterion_nonce', 'new_criterion_nonce');
            $criteria_text = isset($_POST['criteria_text']) ? sanitize_text_field($_POST['criteria_text']) : '';
            $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
            $applicable_brands = isset($_POST['applicable_brands']) ? sanitize_text_field($_POST['applicable_brands']) : '';
            $active = isset($_POST['active']) ? 1 : 0;
            if (!empty($criteria_text) && !empty($applicable_brands)) {
                $wpdb->insert(
                    $wpdb->prefix . 'phone_price_estimator_criteria',
                    array(
                        'criteria_text' => $criteria_text,
                        'discount_value' => $discount_value,
                        'applicable_brands' => $applicable_brands,
                        'active' => $active,
                    ),
                    array('%s', '%f', '%s', '%d')
                );
                echo '<div class="updated notice"><p>' . esc_html__('New criterion added successfully.', 'phone-price-estimator') . '</p></div>';
            } else {
                echo '<div class="error notice"><p>' . esc_html__('Please fill in all required fields.', 'phone-price-estimator') . '</p></div>';
            }
        }
        echo '<h2>' . esc_html__('Add New Criterion', 'phone-price-estimator') . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('ppe_new_criterion_nonce', 'new_criterion_nonce');
        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="criteria_text">' . esc_html__('Criterion Text', 'phone-price-estimator') . '</label></th>';
        echo '<td><input type="text" id="criteria_text" name="criteria_text" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="discount_value">' . esc_html__('Discount Value', 'phone-price-estimator') . '</label></th>';
        echo '<td><input type="number" step="0.01" id="discount_value" name="discount_value" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="applicable_brands">' . esc_html__('Applicable Brands (comma separated)', 'phone-price-estimator') . '</label></th>';
        echo '<td><input type="text" id="applicable_brands" name="applicable_brands" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Active', 'phone-price-estimator') . '</th>';
        echo '<td><input type="checkbox" id="active" name="active" value="1" checked></td></tr>';
        echo '</table>';
        submit_button(__('Add Criterion', 'phone-price-estimator'));
        echo '</form>';

        $criteria = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}phone_price_estimator_criteria ORDER BY id ASC");
        echo '<h2>' . esc_html__('Existing Criteria', 'phone-price-estimator') . '</h2>';
        if ($criteria) {
            echo '<table class="widefat fixed striped"><thead><tr>';
            echo '<th>' . esc_html__('ID', 'phone-price-estimator') . '</th>';
            echo '<th>' . esc_html__('Text', 'phone-price-estimator') . '</th>';
            echo '<th>' . esc_html__('Discount Value', 'phone-price-estimator') . '</th>';
            echo '<th>' . esc_html__('Applicable Brands', 'phone-price-estimator') . '</th>';
            echo '<th>' . esc_html__('Active', 'phone-price-estimator') . '</th>';
            echo '<th>' . esc_html__('Actions', 'phone-price-estimator') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($criteria as $c) {
                echo '<tr>';
                echo '<td>' . esc_html($c->id) . '</td>';
                echo '<td>' . esc_html($c->criteria_text) . '</td>';
                echo '<td>' . esc_html($c->discount_value) . '</td>';
                echo '<td>' . esc_html($c->applicable_brands) . '</td>';
                echo '<td>' . ($c->active ? esc_html__('Yes', 'phone-price-estimator') : esc_html__('No', 'phone-price-estimator')) . '</td>';
                $edit_url = add_query_arg(
                    array('page' => 'ppe-criteria', 'action' => 'edit', 'criterion_id' => $c->id),
                    admin_url('admin.php')
                );
                $delete_url = add_query_arg(
                    array('page' => 'ppe-criteria', 'action' => 'delete', 'criterion_id' => $c->id, 'nonce' => wp_create_nonce('ppe_delete_criterion_nonce')),
                    admin_url('admin.php')
                );
                echo '<td><a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'phone-price-estimator') . '</a> | <a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this criterion?', 'phone-price-estimator')) . '\');">' . esc_html__('Delete', 'phone-price-estimator') . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__('No criteria found.', 'phone-price-estimator') . '</p>';
        }
        echo '</div>';
    }

    public function admin_import_export_page() {
        if ( isset( $_POST['ppe_export_csv'] ) && check_admin_referer( 'ppe_export_csv_nonce', 'ppe_export_csv_nonce' ) ) {
            $this->export_csv();
        }
        if ( isset( $_POST['ppe_import_csv'] ) && check_admin_referer( 'ppe_import_csv_nonce', 'ppe_import_csv_nonce' ) ) {
            $tmp_file = $_FILES['ppe_csv_file']['tmp_name'];
            $upload_dir = wp_upload_dir();
            $target_dir = trailingslashit( $upload_dir['basedir'] ) . 'phone-estimator/';
            if ( ! file_exists( $target_dir ) ) {
                wp_mkdir_p( $target_dir );
            }
            $file_name   = sanitize_file_name( basename( $_FILES['ppe_csv_file']['name'] ) );
            $timestamp   = current_time( 'YmdHis' );
            $target_file = $target_dir . $timestamp . '-' . $file_name;
            if ( move_uploaded_file( $tmp_file, $target_file ) ) {
                $imported_count = $this->import_csv( $target_file );
                echo '<div class="updated notice"><p>' . esc_html__( 'Import complete! ', 'phone-price-estimator' ) . esc_html( $imported_count ) . ' ' . esc_html__( 'rows imported.', 'phone-price-estimator' ) . '</p></div>';
            } else {
                echo '<div class="error notice"><p>' . esc_html__( 'Failed to move the uploaded CSV file.', 'phone-price-estimator' ) . '</p></div>';
            }
        }
        if ( isset( $_POST['ppe_clear_database'] ) && check_admin_referer( 'ppe_clear_database_nonce', 'ppe_clear_database_nonce' ) ) {
            global $wpdb;
            $wpdb->query( "TRUNCATE TABLE {$this->devices_table}" );
            $wpdb->query( "TRUNCATE TABLE {$this->attributes_table}" );
            $wpdb->query( "TRUNCATE TABLE {$this->attr_options_table}" );
            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}phone_price_estimator_criteria" );
            echo '<div class="updated notice"><p>' . esc_html__( 'Database cleared successfully.', 'phone-price-estimator' ) . '</p></div>';
            Phone_Price_Estimator::clear_devices_cache();
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
            <hr>
            <h2><?php esc_html_e( 'Clear Database', 'phone-price-estimator' ); ?></h2>
            <form method="post" onsubmit="return confirm('Are you sure you want to clear the database? This will delete all plugin data but leave the tables intact.');">
                <?php wp_nonce_field( 'ppe_clear_database_nonce', 'ppe_clear_database_nonce' ); ?>
                <input type="submit" name="ppe_clear_database" class="button button-secondary" value="<?php esc_attr_e( 'Clear Database', 'phone-price-estimator' ); ?>">
            </form>
        </div>
        <?php
    }

    private function import_csv( $file ) {
        global $wpdb;
        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            error_log( "Error: File not found or not readable: " . $file );
            return 0;
        }
        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            error_log( "Error: Could not open file: " . $file );
            return 0;
        }
        // Skip header row.
        fgetcsv( $handle );
        $imported_count = 0;
        while ( ( $row = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
            $type = isset( $row[0] ) ? trim( $row[0] ) : '';
            if ( $type === 'Device' ) {
                // Expected: type,device_name,brand,base_price, ... (rest ignored)
                $device_name = isset( $row[1] ) ? sanitize_text_field( $row[1] ) : '';
                $brand = isset( $row[2] ) ? sanitize_text_field( $row[2] ) : '';
                $base_price = isset( $row[3] ) ? floatval( $row[3] ) : 0;
                if ( ! empty( $device_name ) ) {
                    $wpdb->insert(
                        $wpdb->prefix . Phone_Price_Estimator::$devices_table,
                        array(
                            'device_name' => $device_name,
                            'brand'       => $brand,
                            'base_price'  => $base_price,
                        ),
                        array( '%s', '%s', '%f' )
                    );
                    $imported_count++;
                }
            } elseif ( $type === 'Evaluation Criterion' ) {
                // Expected CSV columns:
                // 0: type
                // 1: device_name (ignored)
                // 2: brand (ignored)
                // 3: base_price (ignored)
                // 4: criteria_text
                // 5: discount_value
                // 6: applicable_brands
                // 7: active (optional)
                $criteria_text = isset( $row[4] ) ? sanitize_text_field( $row[4] ) : '';
                $discount_value = isset( $row[5] ) ? floatval( $row[5] ) : 0;
                $applicable_brands = isset( $row[6] ) ? sanitize_text_field( $row[6] ) : '';
                $active = isset( $row[7] ) ? intval( $row[7] ) : 1;
                if ( ! empty( $criteria_text ) && ! empty( $applicable_brands ) ) {
                    $wpdb->insert(
                        $wpdb->prefix . 'phone_price_estimator_criteria',
                        array(
                            'criteria_text'    => $criteria_text,
                            'discount_value'   => $discount_value,
                            'applicable_brands'=> $applicable_brands,
                            'active'           => $active,
                        ),
                        array( '%s', '%f', '%s', '%d' )
                    );
                    $imported_count++;
                }
            } elseif ( $type === 'Attribute Option' ) {
                // Optionally, handle attribute options if needed.
            }
            // Add further conditions for other row types as desired.
        }
        fclose( $handle );
        Phone_Price_Estimator::clear_devices_cache();
        return $imported_count;
    }

    private function export_csv() {
        global $wpdb;
        $devices      = $wpdb->get_results( "SELECT * FROM {$this->devices_table}" );
        $attributes   = $wpdb->get_results( "SELECT * FROM {$this->attributes_table}" );
        $attr_options = $wpdb->get_results( "SELECT * FROM {$this->attr_options_table}" );
        $criteria     = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}phone_price_estimator_criteria" );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=phone_estimator_export_' . current_time( 'YmdHis' ) . '.csv' );

        $output = fopen( 'php://output', 'w' );
        // CSV header row
        fputcsv( $output, array(
            'type',
            'device_name',
            'brand',
            'base_price',
            'criteria_text',
            'discount_value',
            'applicable_brands',
            'active',
            'attribute_id'
        ) );

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

        if ( $criteria ) {
            foreach ( $criteria as $crit ) {
                fputcsv( $output, array(
                    'Evaluation Criterion',
                    '', // device_name empty
                    '', // brand empty
                    '', // base_price empty
                    $crit->criteria_text,
                    $crit->discount_value,
                    $crit->applicable_brands,
                    $crit->active,
                    ''
                ) );
            }
        }
        // Optionally, export attributes and attribute options.
        fclose( $output );
        exit;
    }
}

endif;
