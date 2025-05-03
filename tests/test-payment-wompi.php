<?php

class Test_Payment_Wompi_PA extends WP_UnitTestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('payment_wompi_pa_pwp')) {
            require_once dirname(__DIR__) . '/payment-wompi-woo-pa.php';
        }
    }

    public function test_plugin_loaded()
    {
        $this->assertTrue(function_exists('payment_wompi_pa_pwp'), 'La función principal del plugin no está definida.');
    }

    public function test_requirements_met()
    {
        update_option('woocommerce_currency', 'USD');
        $this->assertTrue(payment_wompi_pa_pwp_requirements(), 'Los requisitos del plugin no se cumplen.');
    }
}