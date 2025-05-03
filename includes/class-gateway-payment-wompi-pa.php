<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Payment_Wompi_PA extends WC_Payment_Gateway
{
    public string $debug;

    public bool $isTest;

    public string $key_private;

    public string $key_public;

    public string $key_integrety;

    public string $key_events;

    public function __construct()
    {
        $this->id = PAYMENT_WOMPI_PA_PWP_ID;
        $this->icon = payment_wompi_pa_pwp()->plugin_url . 'assets/img/logo.png';
        $this->method_title = __('Pago con Wompi');
        $this->method_description = __('Pago a través de Wompi');
        $this->title = $this->get_option('title');
        $this->description  = $this->get_option( 'description' );
        $this->has_fields = true;
        $this->supports = [
            'products'
        ];

        $this->countries = ['PA'];
        $this->debug = $this->get_option( 'debug' );
        $this->isTest = (bool)$this->get_option( 'environment' );

        if ($this->isTest){
            $this->key_private = $this->get_option('sandbox_key_private');
            $this->key_public = $this->get_option('sandbox_key_public');
            $this->key_integrety = $this->get_option('sandbox_key_integrety');
            $this->key_events = $this->get_option('sandbox_key_events');
        }else{
            $this->key_private = $this->get_option('key_private');
            $this->key_public = $this->get_option('key_public');
            $this->key_integrety = $this->get_option('key_integrety');
            $this->key_events = $this->get_option('key_events');
        }

        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_'.strtolower(get_class($this)), array($this, 'confirmation_ipn'));
        add_action('wp', array($this, 'validate_transaction'));
    }

    public function init_form_fields(): void
    {
        $this->form_fields = require( dirname( __FILE__ ) . '/admin/settings.php' );
    }

    public function needs_setup(): bool
    {
        return !$this->is_available();
    }

    public function is_available(): bool
    {
        if(!parent::is_available() ||
            !$this->key_private ||
            !$this->key_public ||
            !$this->key_integrety ||
            !$this->key_events) {
            return false;
        }

        return true;
    }

    public function validate_password_field($key, $value)
    {
        $index_key_public =  $key === 'sandbox_key_private' ? 'sandbox_key_public' : 'key_public';
        $index_key_integrety =  $key === 'sandbox_key_private' ? 'sandbox_key_integrety' : 'key_integrety';
        $index_key_events =  $key === 'sandbox_key_private' ? 'sandbox_key_events' : 'key_events';
        $key_public = $_POST["woocommerce_{$this->id}_{$index_key_public}"] ?? null;
        $key_integrety = $_POST["woocommerce_{$this->id}_{$index_key_integrety}"] ?? null;
        $key_events = $_POST["woocommerce_{$this->id}_{$index_key_events}"] ?? null;

        $validation_rules = [
            'sandbox_key_private' => [
                'message' => 'La llave privada debe contener "prv_test_"',
                'key' => 'prv_test_'
            ],
            'key_private' => [
                'message' => 'La llave privada debe contener "prv_prod_"',
                'key' => 'prv_prod_'
            ]
        ];

        if(isset($validation_rules[$key]) &&
            $value &&
            !str_contains($value, $validation_rules[$key]['key'])) {
            WC_Admin_Settings::add_error($validation_rules[$key]['message']);
        }

        if($value &&
            $key_public &&
            $key_integrety &&
            $key_events &&
            !Payment_Wompi_PA_PWP::test_connect($value, $key_public, $key_integrety)) {

            WC_Admin_Settings::add_error("Credenciales inválidas");
            $value = '';
        }

        return $value;
    }

    public function process_payment($order_id): array
    {
        $order = new WC_Order($order_id);
        $end_point = 'https://checkout.wompi.pa/p/';

        $amont_in_cents = (int) ($order->get_total() * 100);
        $signature = "{$order_id}{$amont_in_cents}{$order->get_currency()}{$this->key_integrety}";
        $signature_integrity = hash('sha256', $signature);
        $order_received_url = add_query_arg( 'payment_method', $this->id, $order->get_checkout_order_received_url() );

        $params = [
            'public-key' => $this->key_public,
            'currency' => $order->get_currency(),
            'amount-in-cents' => $amont_in_cents,
            'reference' => $order_id,
            'signature:integrity' => $signature_integrity,
            'redirect-url' => $order_received_url,
            'customer-data:email' => $order->get_billing_email(),
            'customer-data:full-name' => $order->get_formatted_billing_full_name(),
            'customer-data:phone-number' => $order->get_billing_phone(),
            /*'shipping-address:address-line-1' => $this->get_shipping_address_line_1($order),
            'shipping-address:country' => $order->get_shipping_country() ?: $order->get_billing_country(),
            'shipping-address:phone-number' => $order->get_shipping_phone() ?: $order->get_billing_phone(),
            'shipping-address:city' => $order->get_shipping_city() ?: $order->get_billing_city()*/
        ];

        $url = $end_point . "?" . http_build_query($params);

        if ($this->debug === 'yes'){
            payment_wompi_pa_pwp()->log($params);
        }

        return [
            'result' => 'success',
            'redirect' => $url
        ];

    }

    public function confirmation_ipn(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if ($this->debug === 'yes') {
            payment_wompi_pa_pwp()->log('confirmation_ipn: ' . print_r($data, true));
        }

        if (!$this->is_valid_ipn_data($data)) {
            return;
        }

        $order_id = $data['data']['transaction']['reference'];
        $status = $data['data']['transaction']['status'];
        $transaction_id = $data['data']['transaction']['id'];

        if (!$this->is_valid_checksum($data)) {
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        if ($order->is_paid()) {
            return;
        }

        $this->update_order_status($order, $status, $transaction_id);
    }

    protected function is_valid_ipn_data(array $data): bool
    {
        $required_fields = [
            'data.transaction.reference',
            'data.transaction.id',
            'data.transaction.status',
            'data.transaction.amount_in_cents',
            'signature.checksum',
            'timestamp'
        ];

        foreach ($required_fields as $field) {
            if (!$this->array_has_key($data, $field)) {
                return false;
            }
        }

        return true;
    }

    protected function is_valid_checksum(array $data): bool
    {
        $signature = "{$data['data']['transaction']['id']}{$data['data']['transaction']['status']}{$data['data']['transaction']['amount_in_cents']}{$data['timestamp']}{$this->key_events}";
        $checksum = hash('sha256', $signature);

        return $data['signature']['checksum'] === $checksum;
    }

    protected function update_order_status(WC_Order $order, string $status, string $transaction_id): void
    {
        if ($status === 'APPROVED') {
            $order->payment_complete($transaction_id);
        } elseif (in_array($status, ['VOIDED', 'DECLINED', 'ERROR'], true)) {
            $order->update_status('failed');
        }
    }

    private function array_has_key(array $array, string $key): bool
    {
        $keys = explode('.', $key);
        foreach ($keys as $k) {
            if (!isset($array[$k])) {
                return false;
            }
            $array = $array[$k];
        }
        return true;
    }

    public function validate_transaction(): void
    {
        if(!is_order_received_page() ||
            empty($_REQUEST['key']) ||
            empty($_REQUEST['payment_method']) ||
            empty($_REQUEST['id'])
        ) {
            return;
        }

        $transaction_id = $_REQUEST['id'];
        $order_id = wc_get_order_id_by_order_key($_REQUEST['key']);

        Payment_Wompi_PA_PWP::validate_transaction($transaction_id, $order_id);
    }
}