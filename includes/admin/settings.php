<?php

wc_enqueue_js("
    jQuery(function($) {
        const selectors = {
            wompiFields: '#woocommerce_payment_wompi_pa_pwp_key_private, #woocommerce_payment_wompi_pa_pwp_key_public, #woocommerce_payment_wompi_pa_pwp_key_integrety, #woocommerce_payment_wompi_pa_pwp_key_events',
            wompiSandboxFields: '#woocommerce_payment_wompi_pa_pwp_sandbox_key_private, #woocommerce_payment_wompi_pa_pwp_sandbox_key_public, #woocommerce_payment_wompi_pa_pwp_sandbox_key_integrety, #woocommerce_payment_wompi_pa_pwp_sandbox_key_events',
            environmentSelector: '#woocommerce_payment_wompi_pa_pwp_environment'
        };
        
        function toggleFields() {
            const {
                wompiFields,
                wompiSandboxFields,
                environmentSelector
            } = selectors;
            
            const isProduction = $(environmentSelector).val() === '0';
            const paymentFields = isProduction ? wompiFields : wompiSandboxFields;
            
            $(wompiSandboxFields + ',' + wompiFields).closest('tr').hide();
            
            $(paymentFields).closest('tr').show();
        }
        
        $(selectors.environmentSelector).change(toggleFields).change();
    });
");

$webhook_url = '<a target="_blank" href="https://comercios.wompi.pa/developers">' . __( 'Configurar URL de eventos') . '</a>';

return apply_filters("payment_wompi_pa_pwp_settings", [
        'ipn' => array(
            'title' => $webhook_url,
            'type' => 'title',
            'description' => trailingslashit(get_bloginfo( 'url' )) . trailingslashit('wc-api') . strtolower(get_class($this))
        ),
        'enabled' => array(
            'title' => __('Habilitar/Deshabilitar'),
            'type' => 'checkbox',
            'label' => __('Habilitar Wompi'),
            'default' => 'no'
        ),
        'title' => array(
            'title' => __('Título'),
            'type' => 'text',
            'description' => __('Corresponde al título que el usuario ve durante el checkout'),
            'default' => __('Wompi'),
            'desc_tip' => true,
        ),
        'description' => array(
            'title' => __('Descripción'),
            'type' => 'textarea',
            'description' => __('Corresponde a la descripción que el usuario verá durante el checkout'),
            'default' => __('Con Wompi tienes diferentes alternativas de pagos'),
            'desc_tip' => true,
        ),
        'debug' => array(
            'title' => __('Depurador'),
            'type' => 'checkbox',
            'label' => __('Registros de depuración, se guarda en el registro de pago'),
            'default' => 'no'
        ),
        'environment' => array(
            'title' => __('Ambiente'),
            'type' => 'select',
            'class' => 'wc-enhanced-select',
            'description' => __('Entorno de pruebas o producción'),
            'desc_tip' => true,
            'default' => true,
            'options' => array(
                0 => __('Producción'),
                1 => __('Pruebas'),
            ),
        ),
        'api' => array(
            'title' => __('Credenciales API'),
            'type' => 'title',
            'description' => __('Llaves de API para el entorno de producción y pruebas')
        ),
        'sandbox_key_public' => array(
            'title' => __('Llave pública'),
            'type' => 'text',
            'description' => __('Llave pública para el entorno de pruebas'),
            'desc_tip' => false
        ),
        'sandbox_key_private' => array(
            'title' => __('Llave privada'),
            'type' => 'password',
            'description' => __('Llave privada para el entorno de pruebas'),
            'desc_tip' => false
        ),
        'sandbox_key_events' => array(
            'title' => __('Llave Eventos'),
            'type' => 'password',
            'description' => __('La llave de eventos del comercio para fines de pruebas'),
            'desc_tip' => false
        ),
        'sandbox_key_integrety' => array(
            'title' => __('Llave de integridad'),
            'type' => 'text',
            'description' => __('Llave de integridad para el entorno de pruebas'),
            'desc_tip' => false
        ),
        'key_public' => array(
            'title' => __('Llave pública'),
            'type' => 'text',
            'description' => __('Llave pública para el entorno de producción'),
            'desc_tip' => false
        ),
        'key_private' => array(
            'title' => __('Llave privada'),
            'type' => 'password',
            'description' => __('Llave privada para el entorno de producción'),
            'desc_tip' => false
        ),
        'key_events' => array(
            'title' => __('Llave Eventos'),
            'type' => 'password',
            'description' => __('La llave de eventos del comercio para fines de producciÃ³n'),
            'desc_tip' => false
        ),
        'key_integrety' => array(
            'title' => __('Llave de integridad'),
            'type' => 'text',
            'description' => __('Llave de integridad para el entorno de producción'),
            'desc_tip' => false
        )
    ]
);