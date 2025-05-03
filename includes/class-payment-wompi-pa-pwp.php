<?php

if (!defined('ABSPATH')) {
    exit;
}

use Saulmoralespa\WompiPa\Client;

class Payment_Wompi_PA_PWP
{
    private static ?Client $wompi = null;

    private static $settings = null;

    public static function test_connect(string $key_private, string $key_public, string $key_integrety): bool
    {
        try {
            $wompi = new Client($key_private, $key_public, $key_integrety);
            if (str_contains($key_private, 'test_')) $wompi->sandbox();
            $wompi->getAcceptanceTokens();

            $data = [
                "name" => "Pago de arriendo edificio Lombardía - AP 505",
                "description" => "Arriendo mensual",
                "single_use" => false,
                "collect_shipping" => false,
                "currency" => "USD",
                "amount_in_cents" => 500000
            ];
            $wompi->createPaymentLink($data);
        } catch (Exception $exception) {
            payment_wompi_pa_pwp()->log($exception->getMessage());
            return false;
        }

        return true;
    }

    public static function get_instance(): ?Client
    {
        $id = PAYMENT_WOMPI_PA_PWP_ID;

        if (isset(self::$settings) && isset(self::$wompi)) return self::$wompi;

        self::$settings = get_option("woocommerce_{$id}_settings", null);

        if (!isset(self::$settings)) return null;

        self::$settings = (object)self::$settings;

        if (self::$settings->enabled === 'no') return null;

        if (self::$settings->environment) {
            self::$settings->key_private = self::$settings->sandbox_key_private;
            self::$settings->key_public = self::$settings->sandbox_key_public;
            self::$settings->key_integrety = self::$settings->sandbox_key_integrety;
            self::$settings->key_events = self::$settings->sandbox_key_events;
        }

        self::$wompi = new Client(self::$settings->key_private, self::$settings->key_public, self::$settings->key_integrety);
        if (self::$settings->environment) {
            self::$wompi->sandbox();
        }

        return self::$wompi;
    }

    public static function validate_transaction($transaction_id, int $order_id): void
    {
        if (!self::get_instance()) return;

        try {
            $order = new WC_Order($order_id);

            if($order->get_transaction_id()) return;

            $transaction = self::get_instance()->getTransaction($transaction_id);
            $transaction_id = $transaction['data']['id'];
            $status = $transaction['data']['status'];
            $reference = (int)$transaction['data']['reference'];

            if($reference !== $order_id) return;

            switch ($status) {
                case 'APPROVED':
                    $order->payment_complete($transaction_id);
                    break;
                case 'PENDING':
                    wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'payment_wompi_pa_scheduled_order', [$order_id, $transaction_id]);
                    break;
                default:
                    $order->update_status('failed');
            }
        } catch (Exception $exception) {
            payment_wompi_pa_pwp()->log($exception->getMessage());
        }
    }
}