<?php

if (!defined('ABSPATH')) {
    exit;
}

class Payment_Wompi_PA_PWP_Plugin
{
    /**
     * Absolute plugin path.
     *
     * @var string
     */
    public string $plugin_path;
    /**
     * Absolute plugin URL.
     *
     * @var string
     */
    public string $plugin_url;
    /**
     * assets plugin.
     *
     * @var string
     */
    public string $assets;
    /**
     * Absolute path to plugin includes dir.
     *
     * @var string
     */
    public string $includes_path;
    /**
     * Absolute path to plugin lib dir
     *
     * @var string
     */
    public string $lib_path;
    /**
     * @var WC_Logger
     */
    public WC_Logger $logger;
    /**
     * @var bool
     */
    private bool $bootstrapped = false;

    public function __construct(
        protected $file,
        protected $version
    )
    {
        $this->plugin_path = trailingslashit(plugin_dir_path($this->file));
        $this->plugin_url = trailingslashit(plugin_dir_url($this->file));
        $this->assets = $this->plugin_url . trailingslashit('assets');
        $this->includes_path = $this->plugin_path . trailingslashit('includes');
        $this->lib_path = $this->plugin_path . trailingslashit('lib');
        $this->logger = new WC_Logger();
    }

    /**
     * Initializes the Wompi payment gateway setup, ensuring it is bootstrapped only once.
     * Handles exceptions by displaying admin notices in the admin panel if an error occurs.
     *
     * @return void
     */
    public function run_wompi(): void
    {
        try {
            if ($this->bootstrapped) {
                throw new Exception('Payment Wompi WooCommerce for Panamá can only be called once');
            }
            $this->run();
            $this->bootstrapped = true;
        } catch (Exception $e) {
            if (is_admin() && !defined('DOING_AJAX')) {
                add_action('admin_notices', function () use ($e) {
                    payment_wompi_pa_pwp_notices($e->getMessage());
                });
            }
        }
    }

    /**
     * Executes the initialization of the Wompi payment gateway by including the necessary class
     * and adding the gateway to the WooCommerce payment gateways.
     *
     * @return void
     */
    private function run(): void
    {
        if (!class_exists('\Saulmoralespa\WompiPa\Client')){
            require_once($this->lib_path . 'vendor/autoload.php');
        }

        if (!class_exists('WC_Payment_Wompi_PA')) {
            require_once($this->includes_path . 'class-gateway-payment-wompi-pa.php');
            add_filter( 'woocommerce_payment_gateways', array($this, 'add_gateway'));
        }

        if (!class_exists('Payment_Wompi_PA_PWP')) {
            require_once($this->includes_path . 'class-payment-wompi-pa-pwp.php');
        }

        require_once($this->includes_path . 'class-payment-wompi-blocks-support.php');

        add_filter('plugin_action_links_' . plugin_basename($this->file), array($this, 'plugin_action_links'));

        add_action( 'woocommerce_blocks_loaded', array($this, 'register_wc_blocks') );
        add_action( 'payment_wompi_pa_scheduled_order', array('Payment_Wompi_PA_PWP', 'validate_transaction'), 10, 2);
    }

    public function add_gateway(array $methods): array
    {
        $methods[] = 'WC_Payment_Wompi_PA';
        return $methods;
    }

    public function plugin_action_links($links): array
    {
        $plugin_links = array();
        $plugin_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=payment_wompi_pa_pwp') . '">' . 'Configuraciones' . '</a>';
        return array_merge( $plugin_links, $links );
    }

    public function register_wc_blocks(): void
    {
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                $payment_method_registry->register( new Payment_Wompi_Payment_Pa_Blocks_Support() );
            }
        );
    }

    public function log($message): void
    {
        $id = PAYMENT_WOMPI_PA_PWP_ID;
        $message = (is_array($message) || is_object($message)) ? print_r($message, true) : $message;
        $this->logger->add($id, $message);
    }
}