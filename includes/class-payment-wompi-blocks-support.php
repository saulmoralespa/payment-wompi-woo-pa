<?php

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Payment_Wompi_Payment_Pa_Blocks_Support extends AbstractPaymentMethodType
{
    private $gateway;

    protected $name = 'payment_wompi_pa_pwp';

    public function initialize(): void
    {
        $this->settings = get_option( "woocommerce_{$this->name}_settings", array() );
        $this->gateway = new WC_Payment_Wompi_PA();
    }

    public function is_active(): bool
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles(): array
    {
        $asset_path   = plugin_dir_path( __DIR__ ) . 'assets/build/index.asset.php';

        $version      = null;
        $dependencies = array();
        if( file_exists( $asset_path ) ) {
            $asset        = require $asset_path;
            $version      = isset( $asset[ 'version' ] ) ? $asset[ 'version' ] : $version;
            $dependencies = isset( $asset[ 'dependencies' ] ) ? $asset[ 'dependencies' ] : $dependencies;
        }

        wp_register_script(
            'wc-payment-wompi-pa-blocks-integration',
            plugin_dir_url( __DIR__ ) . 'assets/build/index.js',
            $dependencies,
            $version,
            true
        );

        return array( 'wc-payment-wompi-pa-blocks-integration' );

    }

    public function get_supported_features(): array
    {
        return array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] );
    }

    public function get_payment_method_data(): array
    {
        return array(
            'title'        => $this->get_setting( 'title' ),
            'description'  => $this->get_setting( 'description' ),
            'icon'         => plugin_dir_url( __DIR__ ) . 'assets/img/logo.png',
            'supports'  => $this->get_supported_features(),
        );
    }
}