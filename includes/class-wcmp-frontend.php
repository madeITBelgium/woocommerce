<?php
use WPO\WC\MyParcel\Compatibility\WC_Core as WCX;
use WPO\WC\MyParcel\Compatibility\Order   as WCX_Order;
use WPO\WC\MyParcel\Compatibility\Product as WCX_Product;

/**
 * Frontend views
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( 'WooCommerce_MyParcel_Frontend' ) ) :

    class WooCommerce_MyParcel_Frontend {

        /*
         * @var WooCommerce_MyParcel_Frontend_Settings
         */
        private $frontend_settings;
        const RADIO_CHECKED = 'on';

        function __construct()	{
            // Customer Emails
            if (isset(WooCommerce_MyParcel()->general_settings['email_tracktrace'])) {
                add_action( 'woocommerce_email_before_order_table', array( $this, 'track_trace_email' ), 10, 2 );
            }

            // Track & trace in my account
            if (isset(WooCommerce_MyParcel()->general_settings['myaccount_tracktrace'])) {
                add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'track_trace_myaccount' ), 10, 2 );
            }

            // pickup address in email
            // woocommerce_email_customer_details:
            // @10 = templates/email-customer-details.php
            // @20 = templates/email-addresses.php
            add_action( 'woocommerce_email_customer_details', array( $this, 'email_pickup_html'), 19, 3 );

            // pickup address on thank you page
            add_action( 'woocommerce_thankyou', array( $this, 'thankyou_pickup_html'), 10, 1 );

            // WooCommerce PDF Invoices & Packing Slips Premium Templates compatibility
            add_filter( 'wpo_wcpdf_templates_replace_myparcel_delivery_date', array( $this, 'wpo_wcpdf_delivery_date' ), 10, 2 );
            add_filter( 'wpo_wcpdf_templates_replace_myparcel_tracktrace', array( $this, 'wpo_wcpdf_tracktrace' ), 10, 2 );
            add_filter( 'wpo_wcpdf_templates_replace_myparcel_tracktrace_link', array( $this, 'wpo_wcpdf_tracktrace_link' ), 10, 2 );
            add_filter( 'wpo_wcpdf_templates_replace_myparcel_delivery_options', array( $this, 'wpo_wcpdf_delivery_options' ), 10, 2 );

            // Delivery options
            if (isset(WooCommerce_MyParcel()->checkout_settings['myparcel_checkout'])) {
                add_action( apply_filters( 'wc_myparcel_delivery_options_location', 'woocommerce_after_checkout_billing_form' ), array( $this, 'output_delivery_options' ), 10, 1 );
            }

            // Save delivery options data
            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_delivery_options' ), 10, 2 );

            // Delivery options fees
            add_action( 'woocommerce_cart_calculate_fees', array( $this, 'get_delivery_options_fees' ) );

            // Output most expensive shipping class in frontend data
            add_action( 'woocommerce_checkout_after_order_review', array( $this, 'output_shipping_data' ) );
            add_action( 'woocommerce_update_order_review_fragments', array( $this, 'order_review_fragments' ) );

            /* @todo remove require_once() */
            require_once( WooCommerce_MyParcel()->plugin_path() . '/includes/class-wcmp-frontend-settings.php' );

            $this->frontend_settings = new WooCommerce_MyParcel_Frontend_Settings();
        }

        public function track_trace_email( $order, $sent_to_admin ) {

            if ( $sent_to_admin ) return;

            if ( WCX_Order::get_status( $order ) != 'completed') return;

            $order_id = WCX_Order::get_id( $order );

            $tracktrace_links = WooCommerce_MyParcel()->admin->get_tracktrace_links ( $order_id );
            if ( !empty($tracktrace_links) ) {
                $email_text = __( 'You can track your order with the following PostNL track&trace code:', 'woocommerce-myparcel' );
                $email_text = apply_filters( 'wcmyparcel_email_text', $email_text, $order );
                ?>
                <p><?php echo $email_text.' '.implode(', ', $tracktrace_links); ?></p>

                <?php
            }
        }

        public function email_pickup_html( $order, $sent_to_admin = false, $plain_text = false ) {
            WooCommerce_MyParcel()->admin->show_order_delivery_options( $order );
        }

        public function thankyou_pickup_html( $order_id ) {
            $order = wc_get_order( $order_id );
            WooCommerce_MyParcel()->admin->show_order_delivery_options( $order );
        }

        public function track_trace_myaccount( $actions, $order ) {
            $order_id = WCX_Order::get_id( $order );
            if ( $consignments = WooCommerce_MyParcel()->admin->get_tracktrace_shipments( $order_id ) ) {
                foreach ($consignments as $key => $consignment) {
                    $actions['myparcel_tracktrace_'.$consignment['tracktrace']] = array(
                        'url'  => $consignment['tracktrace_url'],
                        'name' => apply_filters( 'wcmyparcel_myaccount_tracktrace_button', __( 'Track&Trace', 'wooocommerce-myparcel' ) )
                    );
                }
            }

            return $actions;
        }

        //  @deprecated ?
        public function wpo_wcpdf_delivery_options( $replacement, $order ) {

            ob_start();
            WooCommerce_MyParcel()->admin->show_order_delivery_options( $order );
            return ob_get_clean();
        }


        // @deprecated
        public function wpo_wcpdf_delivery_date( $replacement, $order ) {
            if ($delivery_date = WooCommerce_MyParcel()->export->get_delivery_date( $order ) ) {
                $formatted_date = date_i18n( apply_filters( 'wcmyparcel_delivery_date_format', wc_date_format() ), strtotime( $delivery_date ) );
                return $formatted_date;
            }
            return $replacement;
        }

        public function wpo_wcpdf_tracktrace( $replacement, $order ) {
            if ( $shipments = WooCommerce_MyParcel()->admin->get_tracktrace_shipments( WCX_Order::get_id( $order ) ) ) {
                $tracktrace = array();
                foreach ($shipments as $shipment) {
                    if (!empty($shipment['tracktrace'])) {
                        $tracktrace[] = $shipment['tracktrace'];
                    }
                }
                $replacement = implode(', ', $tracktrace);
            }
            return $replacement;
        }

        public function wpo_wcpdf_tracktrace_link( $replacement, $order ) {
            $tracktrace_links = WooCommerce_MyParcel()->admin->get_tracktrace_links ( WCX_Order::get_id( $order ) );
            if ( !empty($tracktrace_links) ) {
                $replacement = implode(', ', $tracktrace_links);
            }
            return $replacement;
        }

        /**
         *
         * Output some stuff.
         * Return to hide delivery options
         */

        public function output_delivery_options() {

            $chosen_shipping_methods = WC()->session->chosen_shipping_methods;
            if ( empty($myparcel_delivery_options_always_display) && !empty($chosen_shipping_methods) && is_array($chosen_shipping_methods) ) {
                $shipping_country = WC()->customer->get_shipping_country();
                if ($shipping_country != 'NL') {
                    return;
                }
                $chosen_shipping_method = array_shift($chosen_shipping_methods);
                $shipping_class = $this->get_cart_shipping_class();
                $package_type = WooCommerce_MyParcel()->export->get_package_type_from_shipping_method( $chosen_shipping_method, $shipping_class, $shipping_country );
                if ($package_type != 1) { // parcel
                    return;
                }
            }


            // Don't load when cart doesn't need shipping
            if ( false == WC()->cart->needs_shipping()) {
                return;
            }

            $urlJs       = WooCommerce_MyParcel()->plugin_url() . "/assets/delivery-options/js/myparcel.js";

            $jsonConfig  = $this->get_checkout_config();
            $myparcelShippingMethods = json_encode($this->get_shipping_methods());


            echo "<script> myParcelConfig = {$jsonConfig}; myparcel_delivery_options_shipping_methods = {$myparcelShippingMethods} </script>";
            require_once(WooCommerce_MyParcel()->plugin_path().'/includes/views/wcmp-delivery-options-template.php');

            return;

        }


        // XXX Move to Jquery ?
        public function output_shipping_data() {
            $shipping_data = $this->get_shipping_data();
            printf('<div class="myparcel-shipping-data">%s</div>', $shipping_data);
        }

        // XXX Move to Jquery ?
        public function get_shipping_data() {

            if ($shipping_class = $this->get_cart_shipping_class()) {
                $shipping_data = sprintf('<input type="hidden" value="%s" id="myparcel_highest_shipping_class" name="myparcel_highest_shipping_class">', $shipping_class);
                return $shipping_data;
            }

            return false;
        }

        /**
         * Save delivery options to order when used
         *
         * @param  int   $order_id
         * @param  array $posted
         *
         * @return void
         */

        public function save_delivery_options( $order_id, $posted ) {
            $order = WCX::get_order( $order_id );

            if ($_POST['myparcel_highest_shipping_class'] != NULL ) {
                WCX_Order::update_meta_data( $order, '_myparcel_highest_shipping_class', $_POST['myparcel_highest_shipping_class'] );
		    }elseif (isset($_POST['shipping_method'])) {
                WCX_Order::update_meta_data( $order, '_myparcel_highest_shipping_class', $_POST['shipping_method'][0] );
            }

            if (isset($_POST['myparcel-signature-selector'])) {
                WCX_Order::update_meta_data( $order, '_myparcel_signed', self::RADIO_CHECKED );
            }

            if (isset($_POST['method-myparcel-only-recipient-selector'])) {
                WCX_Order::update_meta_data( $order, '_myparcel_only_recipient', self::RADIO_CHECKED );
            }

            if (!empty($_POST['mypa-post-nl-data'])) {

                $delivery_options = json_decode( stripslashes( $_POST['mypa-post-nl-data']), true );
                WCX_Order::update_meta_data( $order, '_myparcel_delivery_options', $delivery_options );
            }

        }

        /**
         * Get delivery fee in your order overview, at the front of the website
         *
         * @param $cart
         */
        public function get_delivery_options_fees() {
            if ( ! $_POST || ( is_admin() && ! is_ajax() ) ) {
                return;
            }

            if ( isset( $_POST['post_data'] ) ) {
                // non-default post data for AJAX calls
                parse_str( $_POST['post_data'], $post_data );
            } else {
                // checkout finalization
                $post_data = $_POST;
            }

            /*  check for delivery options & add fees*/
            if ( ! empty( $post_data['mypa-post-nl-data'] ) ) {
                $delivery_options = json_decode( stripslashes( $post_data['mypa-post-nl-data'] ), true );

                /*  Fees for delivery time options*/
                if ( isset( $delivery_options['time'] ) ) {
                    $time = array_shift( $delivery_options['time'] ); // take first element in time array
                    if ( isset( $time['price_comment'] ) ) {
                        switch ( $time['price_comment'] ) {
                            case 'morning':
                                if ( ! empty( WooCommerce_MyParcel()->checkout_settings['morning_fee'] ) ) {
                                    $fee      = WooCommerce_MyParcel()->checkout_settings['morning_fee'];
                                    $fee_name = __( 'Morning delivery', 'woocommerce-myparcel' );

                                    $this->add_fee_signature($delivery_options, 'Signature on delivery');
                                }
                                break;
                            case 'standard':

                                $this->add_fee_signature($delivery_options, 'Signature on delivery');
                                $this->add_fee_only_recipient($delivery_options, 'Home address only');

                                break;
                            case 'avond':
                                if ( ! empty( WooCommerce_MyParcel()->checkout_settings['night_fee'] ) ) {
                                    $fee      = WooCommerce_MyParcel()->checkout_settings['night_fee'];
                                    $fee_name = __( 'Evening delivery', 'woocommerce-myparcel' );

                                    $this->add_fee_signature($delivery_options, 'Signature on delivery');
                                }
                                break;
                        }

                        if ( ! empty( $fee ) ) {
                            $this->add_fee( $fee_name, $fee );
                        }
                    }
                }

                /* Fees for pickup & pickup express */
                if ( isset( $delivery_options['price_comment'] ) ) {
                    switch ( $delivery_options['price_comment'] ) {
                        case 'retail':
                            if ( ! empty( WooCommerce_MyParcel()->checkout_settings['pickup_fee'] ) ) {
                                $fee      = WooCommerce_MyParcel()->checkout_settings['pickup_fee'];
                                $fee_name = __( 'PostNL pickup', 'woocommerce-myparcel' );
                            }
                            break;
                        case 'retailexpress':
                            if ( ! empty( WooCommerce_MyParcel()->checkout_settings['pickup_express_fee'] ) ) {
                                $fee      = WooCommerce_MyParcel()->checkout_settings['pickup_express_fee'];
                                $fee_name = __( 'PostNL pickup express', 'woocommerce-myparcel' );
                            }
                            break;
                    }

                    if ( ! empty( $fee ) ) {
                        $this->add_fee( $fee_name, $fee );
                    }
                }

            }
        }


        /**
         * Get shipping tax class
         * adapted from WC_Tax::get_shipping_tax_rates
         *
         * assumes per order shipping (per item shipping not supported for myparcel yet)
         * @return string tax class
         */
        public function get_shipping_tax_class() {
            $shipping_tax_class = get_option( 'woocommerce_shipping_tax_class' );
            // WC3.0+ sets 'inherit' for taxes based on items, empty for 'standard'
            if ( version_compare( WOOCOMMERCE_VERSION, '3.0', '>=' ) && 'inherit' !== $shipping_tax_class ) {
                $shipping_tax_class = '' === $shipping_tax_class ? 'standard' : $shipping_tax_class;
                return $shipping_tax_class;
            } elseif ( !empty( $shipping_tax_class ) && 'inherit' !== $shipping_tax_class ) {
                return $shipping_tax_class;
            }

            if ( $shipping_tax_class == 'inherit' ) {
                $shipping_tax_class = '';
            }

            // See if we have an explicitly set shipping tax class
            if ( $shipping_tax_class ) {
                $tax_class = 'standard' === $shipping_tax_class ? '' : $shipping_tax_class;
            }

            $location          = WC_Tax::get_tax_location( '' );


            // XXX refactor completely
            if ( sizeof( $location ) === 4 ) {
                list( $country, $state, $postcode, $city ) = $location;

                // This will be per order shipping - loop through the order and find the highest tax class rate
                $cart_tax_classes = WC()->cart->get_cart_item_tax_classes();
                // If multiple classes are found, use the first one. Don't bother with standard rate, we can get that later.
                if ( sizeof( $cart_tax_classes ) > 1 && ! in_array( '', $cart_tax_classes ) ) {
                    $tax_classes = WC_Tax::get_tax_classes();

                    foreach ( $tax_classes as $tax_class ) {
                        $tax_class = sanitize_titel( $tax_class );
                        if ( in_array( $tax_class, $cart_tax_classes ) ) {
                            // correct $tax_class is now set
                            break;
                        }
                    }

                    // If a single tax class is found, use it
                } elseif ( sizeof( $cart_tax_classes ) == 1 ) {
                    $tax_class = array_pop( $cart_tax_classes );
                }

                // no rate = standard rate
                if (empty($tax_class)) {
                    $tax_class = 'standard';
                }
            }

            return $tax_class;
        }

        /**
         * Get the most expensive shipping class in the cart
         * Requires WC2.4+
         *
         * Only supports 1 package, takes the first
         * @return [type] [description]
         */
        public function get_cart_shipping_class() {
            if ( version_compare( WOOCOMMERCE_VERSION, '2.4', '<' ) ) {
                return false;
            }

            $chosen_method = isset( WC()->session->chosen_shipping_methods[ 0 ] ) ? WC()->session->chosen_shipping_methods[ 0 ] : '';

            // get package
            $packages = WC()->shipping->get_packages();
            $package = current($packages);


            $shipping_method = WooCommerce_MyParcel()->export->get_shipping_method($chosen_method);
            if (empty($shipping_method)) {
                return false;
            }

            // get shipping classes from package
            $found_shipping_classes = $shipping_method->find_shipping_classes( $package );
            // return print_r( $found_shipping_classes, true );

            $highest_class = WooCommerce_MyParcel()->export->get_shipping_class( $shipping_method, $found_shipping_classes );

            return $highest_class;
        }



        public function order_review_fragments( $fragments ) {
            $myparcel_shipping_data = $this->get_shipping_data();

//            echo '<pre>';var_dump($myparcel_shipping_data);echo '</pre>';die();
            $fragments['.myparcel-shipping-data'] = $myparcel_shipping_data;
            return $fragments;
        }

        // converts price string to float value, assuming no thousand-separators used
        // XXX money format
        public function normalize_price( $price ) {
            $price = str_replace(',', '.', $price);
            $price = floatval($price);

            return $price;
        }

        private function get_checkout_config()
        {

            $myParcelConfig = [

                "address"=> [
                    "cc" => '',
                    "postalCode" => '',
                    "number" => '',
                    "city" =>''
                ],
                "txtWeekDays" => [
                    'Zondag',
                    'Maandag',
                    'Dinsdag',
                    'Woensdag',
                    'Donderdag',
                    'Vrijdag',
                    'Zaterdag'
                ],
                "translateENtoNL" => [
                    'monday' => 'maandag',
                    'tuesday' => 'dindsag',
                    'wednesday' => 'woensdag',
                    'thursday' => 'donderdag',
                    'friday' => 'vrijdag',
                    'saturday' => 'zaterdag',
                    'sunday' => 'zondag'
                ],
                "config" => [
                    "apiBaseUrl" =>  $this->frontend_settings->get_api_url(),
                    "carrier" =>  "1",

                    "priceMorningDelivery" =>  $this->frontend_settings->get_price_morning(),
                    "priceNormalDelivery" =>  "",
                    "priceEveningDelivery" =>  $this->frontend_settings->get_price_evening(),
                    "priceSignature" =>  $this->frontend_settings->get_price_signature(),
                    "priceOnlyRecipient" => $this->frontend_settings->get_price_only_recipient(),
                    "pricePickup" =>  $this->frontend_settings->get_price_pickup(),
                    "pricePickupExpress" =>  $this->frontend_settings->get_price_pickup_express(),

                    "deliveryTitel" => $this->frontend_settings->at_home_delivery_titel(),
                    "pickupTitel" => $this->frontend_settings->pickup_titel(),
                    "deliveryMorningTitel" => $this->frontend_settings->morning_titel(),
                    "deliveryStandardTitel" => $this->frontend_settings->standard_titel(),
                    "deliveryEveningTitel" => $this->frontend_settings->evening_titel(),
                    "signatureTitel" =>  $this->frontend_settings->signature_titel(),
                    "onlyRecipientTitel" =>  $this->frontend_settings->only_recipient_titel(),

                    "allowMondayDelivery" =>  $this->frontend_settings->is_monday_enabled(),
                    "allowMorningDelivery" =>  $this->frontend_settings->is_morning_enabled(),
                    "allowEveningDelivery" =>  $this->frontend_settings->is_evening_enabled(),
                    "allowSignature" =>  $this->frontend_settings->is_signature_enabled(),
                    "allowOnlyRecipient" =>  $this->frontend_settings->is_only_recipient_enabled(),
                    "allowPickupPoints" =>  $this->frontend_settings->is_pickup_enabled(),
                    "allowPickupExpress" =>  $this->frontend_settings->is_pickup_express_enabled(),

                    "dropOffDays" =>  $this->frontend_settings->get_dropoff_days(),
                    "saturdayCutoffTime" =>  $this->frontend_settings->get_saturday_cutoff_time(),
                    "cutoffTime" =>  $this->frontend_settings->get_cutoff_time(),
                    "deliverydaysWindow" =>  $this->frontend_settings->get_deliverydays_window(),
                    "dropoffDelay" => $this->frontend_settings->get_dropoff_delay()
                ],
              ];

            return json_encode( $myParcelConfig );

            // Use cutoff_time and saturday_cutoff_time on saturdays
        }

        /**
         * Get shipping methods associated with parcels to enable delivery options
         */
        private function get_shipping_methods() {

            if (
                $this->frontend_settings->get_checkout_display() != 'all_methods' &&
                isset( WooCommerce_MyParcel()->export_defaults['shipping_methods_package_types'][1] )
            ) {
                return WooCommerce_MyParcel()->export_defaults['shipping_methods_package_types'][1];
            }

            return array();
        }

        /**
         * check if delivery method must hide
         */
        private function is_hide_delivery_method() {
            if ($this->frontend_settings->get_checkout_display() == 'all_methods' ) {
                return false;
            }

            // determine whether to pre-hide iframe (prevents flashing)
            $chosen_shipping_methods = WC()->session->chosen_shipping_methods;
            if ( empty($chosen_shipping_methods) || !is_array($chosen_shipping_methods) ) {
                return false;
            }

            $shipping_country = WC()->customer->get_shipping_country();
            if ($shipping_country != 'NL') {
                return true;
            }

            return false;
        }

        /**
         * @return null|array
         */
        private function get_post_data() {

//            WC()->cart->add_fee( 'test fee  ', 10,0, 'standard');
//            var_dump($_POST);
            if ( ! $_POST || ( is_admin() && ! is_ajax() ) ) {
                return null;
            }

            if ( isset( $_POST['post_data'] ) ) {
                // non-default post data for AJAX calls
                parse_str( $_POST['post_data'], $post_data );

                return $post_data;
            }

            // checkout finalization
            return $_POST;
        }

        private function add_fee( $fee_name, $fee ) {
            $fee = $this->normalize_price( $fee );
            // get shipping tax data
            $shipping_tax_class = $this->get_shipping_tax_class();
            if ( $shipping_tax_class ) {
                if ($shipping_tax_class == 'standard') {
                    $shipping_tax_class = '';
                }
                WC()->cart->add_fee( $fee_name, $fee, true, $shipping_tax_class );
            } else {
                WC()->cart->add_fee( $fee_name, $fee );
            }
        }

        /**
         *
         * Check witch delivery option is selected
         *
         * @param $post_data
         * @param $post_data_value
         * @param $delivery_type
         * @param $backend_setting
         * @param $delivery_titel
         *
         * @return bool
         */
        private function add_fee_from_setting( $post_data, $post_data_value ,$delivery_type, $backend_setting, $delivery_titel ) {
            // Fee for "delivery" option
            if (isset($post_data[$post_data_value]) && $post_data[$post_data_value] == $delivery_type) {
                if ( ! empty( WooCommerce_MyParcel()->checkout_settings[$backend_setting] ) ) {
                    $fee      = WooCommerce_MyParcel()->checkout_settings[$backend_setting];
                    $fee_name = __( $delivery_titel, 'woocommerce-myparcel' );
                    $this->add_fee( $fee_name, $fee );

                    return true;
                }
            }

            return false;
        }

        private function add_fee_signature( $delivery_options, $delivery_titel ) {
            if ( $delivery_options['signed'] !== 1){
                return;
            }

            $fee      = WooCommerce_MyParcel()->checkout_settings['signed_fee'];

            if ( ! empty( $fee ) ) {
                $fee_name = __( $delivery_titel, 'woocommerce-myparcel' );
                $this->add_fee( $fee_name, $fee );
            }
        }

        private function add_fee_only_recipient( $delivery_options, $delivery_titel ) {
            if ( $delivery_options['only_recipient'] !== 1){
                return;
            }

            $fee      = WooCommerce_MyParcel()->checkout_settings['only_recipient_fee'];

            if ( ! empty( $fee ) ) {
                $fee_name = __( $delivery_titel, 'woocommerce-myparcel' );
                $this->add_fee( $fee_name, $fee );
            }
        }

    }

endif; // class_exists

return new WooCommerce_MyParcel_Frontend();
