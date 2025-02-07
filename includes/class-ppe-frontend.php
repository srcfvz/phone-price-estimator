<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class PPE_Frontend
 *
 * Manages the front-end display, shortcodes, and Ajax calls.
 */
if ( ! class_exists( 'PPE_Frontend' ) ) :

class PPE_Frontend {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register shortcode
        add_shortcode( 'phone_price_estimator', array( $this, 'render_price_estimator' ) );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Ajax for auto-complete
        add_action( 'wp_ajax_ppe_search_devices', array( $this, 'ajax_search_devices' ) );
        add_action( 'wp_ajax_nopriv_ppe_search_devices', array( $this, 'ajax_search_devices' ) );

        // Ajax for calculation
        add_action( 'wp_ajax_ppe_calculate_price', array( $this, 'ajax_calculate_price' ) );
        add_action( 'wp_ajax_nopriv_ppe_calculate_price', array( $this, 'ajax_calculate_price' ) );
    }

    /**
     * Enqueue JS & CSS
     */
    public function enqueue_assets() {
        // Use jQuery UI for auto-complete (or replace with your own library).
        wp_enqueue_script( 'jquery-ui-autocomplete' );

        // Front-end JS
        wp_register_script(
            'ppe-frontend-js',
            plugin_dir_url(__FILE__) . '../assets/ppe-frontend.js',
            array('jquery', 'jquery-ui-autocomplete'),
            '1.0.0',
            true
        );

        // Localize for AJAX
        wp_localize_script('ppe-frontend-js', 'ppe_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ppe_frontend_nonce')
        ));

        wp_enqueue_script('ppe-frontend-js');

        // Front-end CSS
        wp_enqueue_style(
            'ppe-frontend-css',
            plugin_dir_url(__FILE__) . '../assets/ppe-frontend.css',
            array(),
            '1.0.0'
        );
    }

    /**
     * The [phone_price_estimator] shortcode handler
     */
    public function render_price_estimator( $atts ) {
        // Get attributes from DB
        $attributes = Phone_Price_Estimator::get_all_attributes();

        ob_start(); 
        ?>
        <div class="ppe-estimator-wrapper">
            <h3><?php esc_html_e('Phone Price Estimator', 'phone-price-estimator'); ?></h3>
            
            <label for="ppe_device_search"><?php esc_html_e('Select Your Device:', 'phone-price-estimator'); ?></label>
            <input type="text" id="ppe_device_search" placeholder="<?php esc_attr_e('Type to search devices...', 'phone-price-estimator'); ?>" />

            <input type="hidden" id="ppe_selected_device_id" value="" />

            <div id="ppe-attributes-container">
                <!-- Dynamically load attribute questions here -->
                <?php if ( $attributes ): ?>
                    <?php foreach ( $attributes as $attr ): ?>
                        <div class="ppe-attribute-block">
                            <label><?php echo esc_html( $attr->attribute_name ); ?></label>
                            <select data-attribute-id="<?php echo esc_attr( $attr->id ); ?>">
                                <option value=""><?php esc_html_e('Select an option', 'phone-price-estimator'); ?></option>
                                <?php if ( $attr->options ): ?>
                                    <?php foreach ( $attr->options as $opt ): ?>
                                        <option value="<?php echo esc_attr($opt->id); ?>">
                                            <?php 
                                            // e.g. "Light Scratches (-10)" or "Heavy Scratches (-5%)"
                                            $suffix = ( $attr->discount_type === 'percentage' ) ? '%' : '';
                                            echo esc_html( 
                                                $opt->option_label . ' (-' . $opt->discount_value . $suffix . ')' 
                                            );
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><?php esc_html_e('No attributes found. Please ask the site admin to add some.', 'phone-price-estimator'); ?></p>
                <?php endif; ?>
            </div>

            <button id="ppe_calculate_btn"><?php esc_html_e('Calculate Price', 'phone-price-estimator'); ?></button>

            <div id="ppe_result_container">
                <p>
                  <?php esc_html_e('Estimated Price:', 'phone-price-estimator'); ?> 
                  <span id="ppe_estimated_price">-</span>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Ajax: search devices for auto-complete
     */
    public function ajax_search_devices() {
        check_ajax_referer('ppe_frontend_nonce');

        $search  = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $devices = Phone_Price_Estimator::get_devices($search);

        $results = array();
        if ( $devices ) {
            foreach ( $devices as $d ) {
                $results[] = array(
                    'label'     => $d->device_name,
                    'value'     => $d->device_name,
                    'device_id' => $d->id
                );
            }
        }
        wp_send_json( $results );
    }

    /**
     * Ajax: calculate final price
     */
    public function ajax_calculate_price() {
        check_ajax_referer('ppe_frontend_nonce');

        $device_id     = intval( $_POST['device_id'] );
        $selected_attr = isset($_POST['selected_attr']) ? (array) $_POST['selected_attr'] : array();

        // Calculate
        $final_price = Phone_Price_Estimator::calculate_final_price( $device_id, $selected_attr );

        wp_send_json_success( array( 'final_price' => $final_price ) );
    }
}

endif; // end if class_exists
