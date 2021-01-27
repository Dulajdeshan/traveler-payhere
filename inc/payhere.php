<?php
/**
 * Created by PhpStorm.
 * User: Dungdt
 * Date: 12/15/2015
 * Time: 3:19 PM
 */


if (!class_exists('ST_Payhere_Payment_Gateway')) {
    class ST_Payhere_Payment_Gateway extends STAbstactPaymentGateway
    {
        static private $_ints;
        private $default_status = true;
        private $_gatewayObject = null;
        private $_gateway_id = 'st_payhere';

        private $url;
        private $merchant_id;
        private $merchant_secret;


        function __construct()
        {
            add_filter('st_payment_gateway_st_payhere_name', array($this, 'get_name'));
            try {
                $this->_gatewayObject = '';

            } catch (Exception $e) {
                $this->default_status = false;
            }
        }


        function get_option_fields()
        {
            return array(
                array(
                    'id' => 'payhere_sandbox',
                    'label' => __('Sandbox Mode', 'traveler-payhere'),
                    'type' => 'on-off',
                    'std' => 'on',
                    'section' => 'option_pmgateway',
                    'desc' => esc_html__( "Sandbox/Live Mode", 'traveler-onepay' ),
                    'condition' => 'pm_gway_st_payhere_enable:is(on)'
                ),
                array(
                    'id' => 'payhere_merchant_id',
                    'label' => __('Merchant ID', 'traveler-payhere'),
                    'type' => 'text',
                    'section' => 'option_pmgateway',
                    'desc' => __('Merchant ID', 'traveler-payhere'),
                    'condition' => 'pm_gway_st_payhere_enable:is(on)'
                ),
                array(
                    'id' => 'payhere_merchant_secret',
                    'label' => __('Merchant Secret', 'traveler-payhere'),
                    'type' => 'text',
                    'section' => 'option_pmgateway',
                    'desc' => __('Merchant Secret', 'traveler-payhere'),
                    'condition' => 'pm_gway_st_payhere_enable:is(on)'
                ),
                array(
                    'id' => 'payhere_app_id',
                    'label' => __('Business App Id', 'traveler-payhere'),
                    'type' => 'text',
                    'section' => 'option_pmgateway',
                    'desc' => __('Help: https://support.payhere.lk/api-&-mobile-sdk/payhere-retrieval', 'traveler-payhere'),
                    'condition' => 'pm_gway_st_payhere_enable:is(on)'
                ),
                array(
                    'id' => 'payhere_app_secret',
                    'label' => __('Business App Secret', 'traveler-payhere'),
                    'type' => 'text',
                    'section' => 'option_pmgateway',
                    'desc' => __('Help: https://support.payhere.lk/api-&-mobile-sdk/payhere-retrieval', 'traveler-payhere'),
                    'condition' => 'pm_gway_st_payhere_enable:is(on)'
                ),
                
            

            );
        }

        public function setDefaultParam()
        {
            $this->url = st()->get_option('payhere_url', '');
            $this->merchant_id = st()->get_option('payhere_merchant_id', '');
            $this->merchant_secret = st()->get_option('payhere_merchant_secret', '');
            $this->app_id = st()->get_option('payhere_app_id', '');
            $this->app_secret = st()->get_option('payhere_app_secret','');
            $this->sandbox = st()->get_option('payhere_sandbox', 'on');

            if ('on' == $this->sandbox) {

                $this->checkout_url = 'https://sandbox.payhere.lk/pay/checkout';
                $this->authorization_url = 'https://sandbox.payhere.lk/merchant/v1/oauth/token';
                $this->retrieve_url = 'https://sandbox.payhere.lk/merchant/v1/payment/search?order_id=';

            } else {

                $this->checkout_url = 'https://www.payhere.lk/pay/checkout';
                $this->authorization_url = 'https://www.payhere.lk/merchant/v1/oauth/token';
                $this->retrieve_url = 'https://www.payhere.lk/merchant/v1/payment/search?order_id=';

            }
        }

        function _pre_checkout_validate()
        {
            if (TravelHelper::get_current_currency('name') == 'USD') {
                return true;
            }else if(TravelHelper::get_current_currency('name') == 'LKR') {
                return true;
            }

            STTemplate::set_message(__('This payment only supports LKR & USD currency', 'traveler-payhere'));
        }

        function do_checkout($order_id)
        {
            $payment = STInput::post('st_payment_gateway');

        

            $this->setDefaultParam();


            $params = $this->get_purchase_data($order_id);


            $payhere_form_array = array();

            foreach ($params as $key => $value) {

                $payhere_form_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';

            }

            $form = sprintf('<h4>Redirecting....</h4>

             <form action="%s" method="post" id="st_form_payhere_submit">%s</form>

    						<script>document.getElementById(\'st_form_payhere_submit\').submit();</script>', $this->checkout_url, implode('', $payhere_form_array));

             return [

                'status' => true,

                'redirect_form' => $form

            ];

        }

        private function get_purchase_data($new_order)

        {
            $total = get_post_meta($new_order, 'total_price', true);

            $total = round((float)$total, 2);

            $amount = number_format((float)$total, 2, '.', '');
            $format_amt = $this->formatAmount($amount);
            $service_id = (int)get_post_meta($new_order, 'item_id', true);
            $first_name = get_post_meta($new_order, 'st_first_name', true);
            $last_name = get_post_meta($new_order, 'st_last_name', true);
            $address_1 = get_post_meta($new_order, 'st_address', true);
            $address_2 = get_post_meta($new_order, 'st_address2', true);
            $city = get_post_meta($new_order, 'st_city', true);
            $country = get_post_meta($new_order, 'st_country', true);
            $email_address = get_post_meta($new_order, 'st_email', true);
            $phone = get_post_meta($new_order, 'st_phone', true);
            $currency = TravelHelper::get_current_currency('name');

           
            $params = array(
                'merchant_id' => $this->merchant_id,
                'return_url' => $this->get_return_url($new_order, true),
                'cancel_url' => $this->get_cancel_url($new_order),
                'notify_url' => $this->get_return_url($new_order, true),
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email_address,
                'phone' => $phone,
                'address' =>  $address_1. ' '. $address_2,
                'city' => $city,
                'country'=> $country,
                'order_id' => $new_order,
                'items' => get_the_title($service_id),
                'currency' => $currency,
                'amount' => $amount
                
            );

    

            //$the_string = $this->merchant_id . $new_order . $format_amt . $currency . strtoupper(md5($this->merchant_secret));

           // $params['hash'] = $this->generate_sha1_signature($the_string);

            return $params;

        }



        public function formatAmount($amt)
        {
            $remove_dot = str_replace('.', '', $amt);
            $remove_comma = str_replace(',', '', $remove_dot);
            return $remove_comma;
        }

        private function generate_sha1_signature($params)

        {
            return strtoupper(md5($params));
        }

        private function generate_authorization_code() {
            $this->setDefaultParam();

            return base64_encode($this->app_id.':'.$this->app_secret);
        }


        function complete_purchase($order_id)
        {
            return true;
        }



        function check_complete_purchase($order_id)
        {

            $this->setDefaultParam();

            if ($this->validate_response($order_id)) {
                return ['status' => true];
            } else {
                return ['status' => false];
            }
           


        }

        function getPayhereOrderData($order_id, $access_token) {
            $this->setDefaultParam();

            $url = $this->retrieve_url.$order_id;

            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '.$access_token,
                'Content-Type: application/json'
            ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            
            $jsonArrayResponse = json_decode($response, true);

            if($jsonArrayResponse['status'] == 1) {
                
                if($jsonArrayResponse['data'][0]['status'] == 'RECEIVED') {
                    return true;
                }else {
                    return false;
                }
                
            }else {
                return false;
            }
        
        

        }


        function getAccessToken() {

            $this->setDefaultParam();
            $authorization_code = $this->generate_authorization_code();

            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => $this->authorization_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic '.$authorization_code,
                'Content-Type: application/x-www-form-urlencoded'
            ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);


            $jsonArrayResponse = json_decode($response, true);

            return $jsonArrayResponse['access_token'];

        }



        public function validate_response($order_id)

        {
            $access_token = $this->getAccessToken();

            $order_response = $this->getPayhereOrderData($order_id,$access_token);
            if($order_response) {
                return true;
            }else {
                return false;
            }

        }

        public function backendResponsive()

        {

            if (isset($_GET['backendResponsive']) && $_GET['backendResponsive'] == 'payhere' && isset($_GET['orderID'])) {

                $this->setDefaultParam();

                if ($this->validate_response($_GET['orderId'])) {

                    update_post_meta($_GET['orderID'], 'status', 'complete');

                    STCart::send_mail_after_booking($_GET['orderID'], true);

                    do_action('st_booking_change_status', 'complete', $_GET['orderID'], 'st_payhere');
                    return true;
                } else {
                    return false;
                }

            }
        }

        function get_return_url($order_id, $backend = false)

        {
            $order_token_code = get_post_meta($order_id, 'order_token_code', TRUE);

            if (!$order_token_code) {

                $array = [

                    'gateway_name' => $this->getGatewayId(),

                    'order_code' => $order_id,

                    'status' => TRUE

                ];

            } else {

                $array = [

                    'gateway_name' => $this->getGatewayId(),

                    'order_token_code' => $order_token_code,

                    'status' => TRUE

                ];

            }

            if ($backend) {

                $array['backendResponsive'] = 'payhere';

                $array['orderID'] = $order_id;

            }

            return add_query_arg($array, STCart::get_success_link());

        }

        function html()
        {
            echo Traveler_Payhere_Payment::get_inst()->loadTemplate('payhere');
        }

        function get_name()
        {
            return __('Payhere', 'traveler-payhere');
        }

        function get_default_status()
        {
            return $this->default_status;
        }

        function is_available($item_id = false)
        {
            if (st()->get_option('pm_gway_st_payhere_enable') == 'off') {
                return false;
            } else {
                if (!st()->get_option('payhere_merchant_id')) {
                    return false;
                }
            }

            if ($item_id) {
                $meta = get_post_meta($item_id, 'is_meta_payment_gateway_st_payhere', true);
                if ($meta == 'off') {
                    return false;
                }
            }

            return true;
        }

        function getGatewayId()
        {
            return $this->_gateway_id;
        }

        function is_check_complete_required()
        {
            return true;
        }

        function get_logo()
        {
            return Traveler_Payhere_Payment::get_inst()->pluginUrl . 'assets/img/us.png';
        }

        static function instance()
        {
            if (!self::$_ints) {
                self::$_ints = new self();
            }

            return self::$_ints;
        }

        static function add_payment($payment)
        {
            $payment['st_payhere'] = self::instance();

            return $payment;
        }
    }

    add_filter('st_payment_gateways', array('ST_Payhere_Payment_Gateway', 'add_payment'));
}