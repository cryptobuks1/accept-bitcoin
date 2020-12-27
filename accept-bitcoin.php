<?php
/**
 * Plugin Name: Accept Bitcoin
 * Version: 0.1
 * Text Domain: accept-bitcoin
 */

function init_accept_bitcoin_class() {
    class WC_Gateway_Accept_Bitcoin extends WC_Payment_Gateway {

        private $xpub = 'xpub6CgLKcjNqrRYwsdSugBLAJwTjCLBx71ysmzfVFM38tceNHrM4SDF4XBs1mk94aCmaFdWKqkTdY9u3KEYWSesAou8omgmGrCWxZQekyGBp9X';

        function __construct() {
            
            // Currently not being used......
            // $this->default_settings = array(
            //     'enabled' => 'no',
            //     'title' => __('Bitcoin', 'accept-bitcoin'),
            //     'description' => __('Pay using Bitcoin.', 'accpept-bitcoin'),
            // );

            $this->id = 'accept_bitcoin'; // Unique ID for payment gateway
            $this->icon = apply_filters( 'accept_bitcoin_icon', plugin_dir_url(__FILE__) . 'img/bitcoin-symbol.png' );
            $this->has_fields = false; // Don't add any fields to checkout
            $this->method_title = __('Accept Bitcoin', 'accept-bitcoin'); // Title of the payment method shown on the admin page.
            $this->method_description = __('Accept Bitcoin payments.', 'accept-bitcoin'); // Description of the payment method shown on the admin page.

            // Your constructor should also define and load settings fields:
            $this->init_form_fields();
            $this->init_settings();
            
            // Define user set variables.
            $this->title = ( get_option('woocommerce_accept_bitcoin_settings')['title'] ) ? get_option('woocommerce_accept_bitcoin_settings')['title'] : __( 'Bitcoin', 'accpept-bitcoin' );
            $this->description  = ( get_option('woocommerce_accept_bitcoin_settings')['description'] ) ? get_option('woocommerce_accept_bitcoin_settings')['description'] : __( 'Pay using Bitcoin.', 'accpept-bitcoin' );
            // $this->instructions = 'Instructions goes here...'; // Don't know where they are shown...

            // Finally, you need to add a save hook for your settings:
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Add content to order thank you page
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'render_thank_you_page_content' ) );

            // Add callback listener
            //'woocommerce_api_'.strtolower(get_class($this)) will result in 'woocommerce_api_wc_mygateway'
            // add_action('woocommerce_api_'.strtolower(get_class($this)), array(&$this, 'callback_listener'));
            add_action('woocommerce_api_wc_gateway_accept_bitcoin', array( $this, 'callback_listener' ));

            // For testing
            add_action('wp_footer', array( $this, 'test' ));

        }

        function init_form_fields() {

            $settings = get_option('woocommerce_accept_bitcoin_settings', false);

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Accept Bitcoin', 'accept-bitcoin' ),
                    'default' => isset( $settings['enabled'] ) ? $settings['enabled'] : 'no',
                ),
                'title' => array(
                    'title' => __( 'Title', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default' => isset( $settings['title'] ) ? $settings['title'] : __( 'Bitcoin', 'accpept-bitcoin' ),
                    'desc_tip'      => true,
                ),
                // Should be instructions....
                'description' => array(
                    'title' => __( 'Customer Message', 'woocommerce' ),
                    'type' => 'textarea',
                    'default' => isset( $settings['description'] ) ? $settings['description'] : __( 'Pay using Bitcoin.', 'accpept-bitcoin' ),
                )
            );
        }
        
        function process_admin_options() {

            $settings = get_option('woocommerce_accept_bitcoin_settings', false);

            if( isset( $_POST['woocommerce_accept_bitcoin_enabled'] ) ) {
                $settings['enabled'] = 'yes';
            } else {
                $settings['enabled'] = 'no';
            }

            if( isset( $_POST['woocommerce_accept_bitcoin_title'] ) ) {
                $settings['title'] = sanitize_text_field( $_POST['woocommerce_accept_bitcoin_title'] );
            }

            if( isset( $_POST['woocommerce_accept_bitcoin_description'] ) ) {
                $settings['description'] = sanitize_text_field( $_POST['woocommerce_accept_bitcoin_description'] );
            }

            update_option('woocommerce_accept_bitcoin_settings', $settings);

        }

        function process_payment( $order_id ) {

            global $woocommerce;
            $order = new WC_Order( $order_id );

            if ( $order->get_total() > 0 ) {

                // Mark as on-hold (we're awaiting payment)
                $order->update_status('on-hold', __( 'Awaiting Bitcoin payment', 'accept-bitcoin' ));

                // Convert to BTC
                $currency = $order->get_currency();
                $amount = $order->get_total();
                $btc_amount = $this->convert_to_btc($currency, $amount);
                $exchange_rate = $amount / $btc_amount;

                $btc_address = '1J19TLLqu8DH2cv3ze7g1xZNwyyXWyGLKc'; // To be replaced

                update_post_meta($order_id, '_accept_bitcoin_btc_address', $btc_address);
                update_post_meta($order_id, '_accept_bitcoin_btc_amount', $btc_amount);
                update_post_meta($order_id, '_accept_bitcoin_exchange_rate', $exchange_rate);

                $order->add_order_note( sprintf(
                    __('%s %s converted to %s BTC (exchange rate %s) and should be paid to %s.', 'accept-bitcoin'),
                    $amount,
                    $currency,
                    $btc_amount,
                    $exchange_rate,
                    $btc_address
                ), $is_customer_note = 1 );

            } else {
                $order->payment_complete();
            }
            
            // Remove cart
            $woocommerce->cart->empty_cart();
        
            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order )
            );

        }

        function convert_to_btc($currency, $amount) {

            $url = 'https://blockchain.info/tobtc?currency=' . $currency . '&value=' . $amount;
            $response = wp_remote_get( esc_url_raw( $url ) );
            $response_body = wp_remote_retrieve_body( $response );

            $btc_amount = floatval($response_body);
    
            return $btc_amount;

        }

        function get_qr_code_url($address, $amount) {

            $qr_code_data = 'bitcoin:' . $address . '?amount=' . $amount;
            $size = '300x300';
            $qr_code_url = 'https://chart.googleapis.com/chart?cht=qr&chs=' . $size . '&chl=' . $qr_code_data;
            
            return $qr_code_url;

        }

        function test() {
            
        }

        function render_thank_you_page_content( $order_id ) {

            $btc_address = get_post_meta($order_id, '_accept_bitcoin_btc_address', true);
            $btc_amount = get_post_meta($order_id, '_accept_bitcoin_btc_amount', true);
            $exchange_rate = get_post_meta($order_id, '_accept_bitcoin_exchange_rate', true);

            ob_start();

            require('templates/thank-you-page.php');

            $html = ob_get_contents();

            ob_end_clean();

            echo $html;
            
        }

        function callback_listener() {

            if( ! isset( $_GET['order_id'] ) || ! isset( $_GET['order_key'] ) ) {
                error_log('missing order_id or order_key');
                return false;
            }

            $order_id = $_GET['order_id'];
            $order_key = $_GET['order_key'];

            // Make sure the post exists and is a WooCommerce order
            if( ! get_post_type( $order_id ) || get_post_type( $order_id ) !== 'shop_order' ) {
                error_log('order does not exist');
                return false;
            }
            
            $order = new WC_Order( $order_id );

            // Verify the order key
            if( $order->get_order_key() !== $order_key ) {
                error_log('invaid order_key');
                return false;
            }

            $order->payment_complete();

            error_log('order ' . $order_id . ' marked as paid.');
            return true;

        }

    }
}
add_action( 'plugins_loaded', 'init_accept_bitcoin_class' );


// Tell WooCommerce that our payment method exists
function add_accept_bitcoin_class( $methods ) {
    $methods[] = 'WC_Gateway_Accept_Bitcoin'; 
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_accept_bitcoin_class' );