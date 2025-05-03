<?php
/**
 * Plugin Name: Payment Wompi WooCommerce for Panamá
 * Description: Integración de Wompi para WooCommerce disponible en Panamá.
 * Version: 1.0.0
 * Author: Saul Morales Pacheco
 * Author URI: https://saulmoralespa.com
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * WC tested up to: 9.8
 * WC requires at least: 9.6
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if(!defined('PAYMENT_WOMPI_PA_PWP_VERSION')){
    define('PAYMENT_WOMPI_PA_PWP_VERSION', '1.0.0');
}

if(!defined('PAYMENT_WOMPI_PA_PWP_ID')){
    define('PAYMENT_WOMPI_PA_PWP_ID', 'payment_wompi_pa_pwp');
}

add_action( 'plugins_loaded', 'payment_wompi_pa_pwp_init');
add_action(
    'before_woocommerce_init',
    function () {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__
            );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                __FILE__,
                false
            );
        }
    }
);

function payment_wompi_pa_pwp_init(): void
{
    if(!payment_wompi_pa_pwp_requirements()) return;

    payment_wompi_pa_pwp()->run_wompi();
}

function payment_wompi_pa_pwp_notices(string $notice): void
{
    ?>
    <div class="error notice">
        <p><?php echo $notice; ?></p>
    </div>
    <?php
}

function payment_wompi_pa_pwp_requirements(): bool
{
    if ( !version_compare(PHP_VERSION, '8.1.0', '>=') ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            add_action(
                'admin_notices',
                function() {
                    payment_wompi_pa_pwp_notices( 'Payment Wompi WooCommerce for Panamá: Requiere la versión de php >= 8.1');
                }
            );
        }
        return false;
    }

    $shop_currency = get_option('woocommerce_currency');

    if ($shop_currency != 'USD') {
        if (is_admin() && !defined('DOING_AJAX')) {
            $currency_notice = sprintf(
                __('El plugin <strong>Payment Wompi WooCommerce for Panamá</strong> requiere que la moneda de la tienda sea <strong>USD</strong>. Actualmente, está configurada en una moneda no compatible. %s.', 'woocommerce'),
                '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=general#s2id_woocommerce_currency')) . '" style="font-weight:bold;">' . __('Haga clic aquí para cambiar la configuración', 'woocommerce') . '</a>'
            );

            add_action(
                'admin_notices',
                function () use ($currency_notice) {
                    payment_wompi_pa_pwp_notices($currency_notice);
                }
            );
        }
        return false;
    }


    return true;
}

function payment_wompi_pa_pwp()
{
    static $plugin;
    if (!isset($plugin)){
        require_once('includes/class-payment-wompi-pa-pwp-plugin.php');
        $plugin = new Payment_Wompi_PA_PWP_Plugin(__FILE__, PAYMENT_WOMPI_PA_PWP_VERSION);
    }
    return $plugin;
}