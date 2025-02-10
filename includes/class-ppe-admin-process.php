<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class PPE_Admin_Process
 *
 * Handles the back-end logic / processing:
 *  - Database operations for devices and attributes
 *  - Import/Export CSV
 *  - AJAX callbacks that modify data
 */
if ( ! class_exists( 'PPE_Admin_Process' ) ) :

class PPE_Admin_Process {

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

		// Register any AJAX hooks that modify data (e.g. update_attribute_callback):
		add_action( 'wp_ajax_ppe_update_attribute', array( $this, 'update_attribute_callback' ) );
	}

	/**
	 * Handle updating an attribute's discount_type, etc. via AJAX.
	 */
	public function update_attribute_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ppe_update_attribute_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce.' );
		}
		$attribute_id  = isset( $_POST['attribute_id'] ) ? intval( $_POST['attribute_id'] ) : 0;
		$discount_type = isset( $_POST['discount_type'] ) ? sanitize_text_field( $_POST['discount_type'] ) : '';

		if ( ! $attribute_id || ! in_array( $discount_type, array('fixed', 'percentage'), true ) ) {
			wp_send_json_error( 'Invalid data.' );
		}
		global $wpdb;
		$updated = $wpdb->update(
			$this->attributes_table,
			array( 'discount_type' => $discount_type ),
			array( 'id' => $attribute_id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			wp_send_json_error( 'Database update failed.' );
		}
		wp_send_json_success();
	}

	/**
	 * Delete a device (and associated attributes + options).
	 */
	public function delete_device( $device_id ) {
		global $wpdb;
		$wpdb->delete( $this->devices_table, array( 'id' => $device_id ), array( '%d' ) );
		$attributes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$this->attributes_table} WHERE phone_id = %d",
				$device_id
			)
		);
		if ( $attributes ) {
			foreach ( $attributes as $attribute ) {
				$wpdb->delete( $this->attr_options_table, array( 'attribute_id' => $attribute->id ), array( '%d' ) );
			}
			$wpdb->delete( $this->attributes_table, array( 'phone_id' => $device_id ), array( '%d' ) );
		}
		Phone_Price_Estimator::clear_devices_cache();
	}

	/**
	 * Update a device record.
	 */
	public function update_device( $device_id, $device_name, $brand, $base_price ) {
		global $wpdb;
		$updated = $wpdb->update(
			$this->devices_table,
			array(
				'device_name' => $device_name,
				'brand'       => $brand,
				'base_price'  => $base_price,
			),
			array( 'id' => $device_id ),
			array( '%s', '%s', '%f' ),
			array( '%d' )
		);
		if ( false !== $updated ) {
			Phone_Price_Estimator::clear_devices_cache();
			return true;
		}
		return false;
	}

	/**
	 * Insert a new attribute for a device.
	 */
	public function insert_attribute( $phone_id, $attribute_name, $discount_type ) {
		global $wpdb;
		$wpdb->insert(
			$this->attributes_table,
			array(
				'phone_id'       => $phone_id,
				'attribute_name' => $attribute_name,
				'discount_type'  => $discount_type,
			),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * Import CSV processing.
	 */
	public function handle_import_csv( $file ) {
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

		// Skip header row
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
						$wpdb->insert(
							$this->devices_table,
							array(
								'device_name' => $device_name,
								'brand'       => $brand,
								'base_price'  => $base_price,
							)
						);
						$imported_count++;
					}
					break;

				case 'Attribute Option':
					// Implement your attribute/option import logic here
					// ...
					break;

				default:
					// Possibly 'Attribute' or unknown type
					break;
			}
		}
		fclose( $handle );

		Phone_Price_Estimator::clear_devices_cache();
		return $imported_count;
	}

	/**
	 * Export CSV processing.
	 */
	public function handle_export_csv() {
		global $wpdb;
		$devices      = $wpdb->get_results( "SELECT * FROM {$this->devices_table}" );
		$attributes   = $wpdb->get_results( "SELECT * FROM {$this->attributes_table}" );
		$attr_options = $wpdb->get_results( "SELECT * FROM {$this->attr_options_table}" );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=phone_estimator_export_' . current_time( 'YmdHis' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
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

		if ( $attributes ) {
			foreach ( $attributes as $att ) {
				$related_opts = array_filter( $attr_options, function( $opt ) use ( $att ) {
					return (int) $opt->attribute_id === (int) $att->id;
				});
				if ( $related_opts ) {
					foreach ( $related_opts as $opt ) {
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
						));
					}
				} else {
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
					));
				}
			}
		}
		fclose( $output );
		exit;
	}
}

endif;