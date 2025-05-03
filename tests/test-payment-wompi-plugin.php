<?php

class Test_Payment_Wompi_PA_Plugin extends WP_UnitTestCase
{
    private Payment_Wompi_PA_PWP_Plugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('Payment_Wompi_PA_PWP_Plugin')) {
            require_once dirname(__DIR__) . '/includes/class-payment-wompi-pa-pwp-plugin.php';
        }

        $file = '/path/to/plugin/file.php';
        $version = '1.0.0';

        $this->plugin = $this->getMockBuilder(Payment_Wompi_PA_PWP_Plugin::class)
            ->setConstructorArgs([$file, $version])
            ->onlyMethods(['run'])
            ->addMethods(['is_admin', 'add_action'])
            ->getMock();
    }

    public function test_plugin_initialization(): void
    {
        $this->assertInstanceOf(Payment_Wompi_PA_PWP_Plugin::class, $this->plugin);
        $this->assertStringContainsString('assets', $this->plugin->assets);
        $this->assertStringContainsString('includes', $this->plugin->includes_path);
        $this->assertStringContainsString('lib', $this->plugin->lib_path);
        $this->assertInstanceOf(WC_Logger::class, $this->plugin->logger);
    }

    public function test_add_gateway(): void
    {
        $methods = [];
        $updated_methods = $this->plugin->add_gateway($methods);

        $this->assertCount(1, $updated_methods);
        $this->assertEquals('WC_Payment_Wompi_PA', $updated_methods[0]);
    }

    public function test_run_wompi_bootstrapped(): void
    {
        $this->plugin->run_wompi();
        $this->plugin->run_wompi();
        $this->assertNotFalse(has_action('admin_notices'));
    }

    public function test_logger_functionality(): void
    {
        $mock_logger = $this->createMock(WC_Logger::class);
        $mock_logger->expects($this->once())
            ->method('add')
            ->with(
                $this->equalTo(PAYMENT_WOMPI_PA_PWP_ID),
                $this->equalTo('Test message')
            );
        $this->plugin->logger = $mock_logger;
        $this->plugin->log('Test message');
    }
}