<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'PPE_Frontend' ) ) :

class PPE_Frontend {

    private static $instance = null;

    /**
     * Returns the singleton instance.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Register shortcode.
        add_shortcode( 'phone_price_estimator', array( $this, 'render_price_estimator' ) );

        // Enqueue assets on the front end.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX hooks.
        add_action( 'wp_ajax_ppe_search_devices', array( $this, 'ajax_search_devices' ) );
        add_action( 'wp_ajax_nopriv_ppe_search_devices', array( $this, 'ajax_search_devices' ) );
        add_action( 'wp_ajax_ppe_get_attributes_by_device', array( $this, 'ajax_get_attributes_by_device' ) );
        add_action( 'wp_ajax_nopriv_ppe_get_attributes_by_device', array( $this, 'ajax_get_attributes_by_device' ) );
        add_action( 'wp_ajax_ppe_get_criteria_by_brand', array( $this, 'ajax_get_criteria_by_brand' ) );
        add_action( 'wp_ajax_nopriv_ppe_get_criteria_by_brand', array( $this, 'ajax_get_criteria_by_brand' ) );
        add_action( 'wp_ajax_ppe_calculate_price', array( $this, 'ajax_calculate_price' ) );
        add_action( 'wp_ajax_nopriv_ppe_calculate_price', array( $this, 'ajax_calculate_price' ) );
    }

    /**
     * Enqueue JavaScript and CSS.
     */
    public function enqueue_assets() {
        // Ensure jQuery UI Autocomplete is enqueued.
        wp_enqueue_script( 'jquery-ui-autocomplete' );

        // Define the JS file’s full path (used for filemtime).
        $js_file_path = plugin_dir_path( __FILE__ ) . '../assets/ppe-frontend.js';

        // Use plugins_url() to build a proper URL relative to your main plugin file.
        $js_file_url = plugins_url( 'assets/ppe-frontend.js', dirname( __FILE__ ) . '/../phone-price-estimator.php' );

        wp_register_script(
            'ppe-frontend-js',
            $js_file_url,
            array( 'jquery', 'jquery-ui-autocomplete' ),
            file_exists( $js_file_path ) ? filemtime( $js_file_path ) : false,
            true
        );

        // Localize variables for AJAX.
        wp_localize_script( 'ppe-frontend-js', 'ppe_ajax_obj', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ppe_frontend_nonce' )
        ) );
        wp_enqueue_script( 'ppe-frontend-js' );

        // Do the same for CSS.
        $css_file_url = plugins_url( 'assets/ppe-frontend.css', dirname( __FILE__ ) . '/../phone-price-estimator.php' );
        wp_enqueue_style(
            'ppe-frontend-css',
            $css_file_url,
            array(),
            '1.1.0'
        );
    }

    /**
     * Shortcode handler: renders the price estimator form.
     */
    public function render_price_estimator( $atts ) {
        ob_start();
        ?>
        <div class="ppe-estimator-wrapper">
            <h3><?php esc_html_e( 'Phone Price Estimator', 'phone-price-estimator' ); ?></h3>

            <label for="ppe_device_search">
                <?php esc_html_e( 'Select Your Device:', 'phone-price-estimator' ); ?>
            </label>
            <input type="text"
                   id="ppe_device_search"
                   placeholder="<?php esc_attr_e( 'Type to search devices...', 'phone-price-estimator' ); ?>" />

            <input type="hidden" id="ppe_selected_device_id" value="" />
            <input type="hidden" id="ppe_selected_device_brand" value="" />

            <div id="ppe-attributes-container">
                <!-- Device attributes will be loaded here via AJAX -->
            </div>

            <!-- Container for evaluation criteria -->
            <div id="ppe-criteria-container">
                <!-- Evaluation criteria will be loaded here based on the selected device brand -->
            </div>

            <button id="ppe_calculate_btn"><?php esc_html_e( 'Calculate Price', 'phone-price-estimator' ); ?></button>

            <div id="ppe_result_container">
                <p>
                    <?php esc_html_e( 'Estimated Price:', 'phone-price-estimator' ); ?> 
                    <span id="ppe_estimated_price">-</span>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Search devices for auto-complete.
     */
    public function ajax_search_devices() {
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( $_POST['_ajax_nonce'], 'ppe_frontend_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed!' ) );
            wp_die();
        }
        $search  = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        $devices = Phone_Price_Estimator::get_devices( $search );
        $results = array();
        if ( $devices ) {
            foreach ( $devices as $d ) {
                $results[] = array(
                    'label'     => $d->device_name,
                    'value'     => $d->device_name,
                    'device_id' => $d->id,
                    'brand'     => $d->brand
                );
            }
        }
        wp_send_json( $results );
    }

    /**
     * AJAX: Get device-specific attributes.
     */
    public function ajax_get_attributes_by_device() {
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( $_POST['_ajax_nonce'], 'ppe_frontend_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed!' ) );
            wp_die();
        }
        $device_id = isset( $_POST['device_id'] ) ? intval( $_POST['device_id'] ) : 0;
        if ( ! $device_id ) {
            wp_send_json_error( 'No device ID provided' );
        }
        $attributes = Phone_Price_Estimator::get_attributes_by_device( $device_id );
        if ( empty( $attributes ) ) {
            wp_send_json_success( array( 'html' => '<p>No attributes found for this device.</p>' ) );
        }
        ob_start();
        foreach ( $attributes as $attr ) :
            ?>
            <div class="ppe-attribute-block">
                <label><?php echo esc_html( $attr->attribute_name ); ?></label>
                <select data-attribute-id="<?php echo esc_attr( $attr->id ); ?>">
                    <option value=""><?php esc_html_e( 'Select an option', 'phone-price-estimator' ); ?></option>
                    <?php if ( $attr->options ) : ?>
                        <?php foreach ( $attr->options as $opt ) : 
                            $suffix    = ( $attr->discount_type === 'percentage' ) ? '%' : '';
                            $opt_label = sprintf( '%s (-%s%s)', $opt->option_label, $opt->discount_value, $suffix );
                        ?>
                            <option value="<?php echo esc_attr( $opt->id ); ?>"><?php echo esc_html( $opt_label ); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <?php
        endforeach;
        $html = ob_get_clean();
        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * AJAX: Load evaluation criteria based on device brand.
     */
    public function ajax_get_criteria_by_brand() {
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( $_POST['_ajax_nonce'], 'ppe_frontend_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed!' ) );
            wp_die();
        }
        $brand = isset( $_POST['brand'] ) ? sanitize_text_field( $_POST['brand'] ) : '';
        error_log( 'AJAX: Received brand: ' . $brand );
        if ( empty( $brand ) ) {
            wp_send_json_error( 'No brand provided.' );
        }
        $criteria = Phone_Price_Estimator::get_criteria_by_brand( $brand );
        if ( empty( $criteria ) ) {
            error_log( 'No criteria found for brand: ' . $brand );
            wp_send_json_success( array( 'html' => '<p>No evaluation criteria found for this device.</p>' ) );
        }
        ob_start();
        foreach ( $criteria as $c ) :
            ?>
            <div class="ppe-criteria-block">
                <label><?php echo esc_html( $c->criteria_text ); ?></label>
                <div class="ppe-criteria-options">
                    <label><input type="radio" name="criteria_<?php echo esc_attr( $c->id ); ?>" value="yes"> <?php esc_html_e( 'Da', 'phone-price-estimator' ); ?></label>
                    <label><input type="radio" name="criteria_<?php echo esc_attr( $c->id ); ?>" value="no" checked> <?php esc_html_e( 'Nu', 'phone-price-estimator' ); ?></label>
                </div>
            </div>
            <?php
        endforeach;
        $html = ob_get_clean();
        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * AJAX: Calculate the final price.
     */
    public function ajax_calculate_price() {
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( $_POST['_ajax_nonce'], 'ppe_frontend_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed!' ) );
            wp_die();
        }
        $device_id         = intval( $_POST['device_id'] );
        $selected_criteria = isset( $_POST['selected_criteria'] ) ? (array) $_POST['selected_criteria'] : array();
        $final_price = Phone_Price_Estimator::calculate_final_price( $device_id, $selected_criteria );
        wp_send_json_success( array( 'final_price' => $final_price ) );
    }
}

endif;
