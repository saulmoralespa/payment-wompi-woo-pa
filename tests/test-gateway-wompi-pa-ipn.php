<?php

use Brain\Monkey\Functions;

class WC_Payment_Wompi_PA_ConfirmationIPNTest extends WP_UnitTestCase
{
    private WC_Payment_Wompi_PA $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        Brain\Monkey\setUp();

        if (!class_exists('WC_Payment_Wompi_PA')) {
            require_once dirname(__DIR__) . '/includes/class-gateway-payment-wompi-pa.php';
        }

        // Reemplazar php://input por nuestro mock
        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }
        stream_wrapper_register('php', MockPhpInputStream::class);

        $this->gateway = $this->getMockBuilder(WC_Payment_Wompi_PA::class)
            ->onlyMethods(['is_valid_ipn_data', 'is_valid_checksum', 'update_order_status'])
            ->getMock();
        $this->gateway->key_events = 'test_key_events';
    }

    public function test_mock_function()
    {
        \Brain\Monkey\Functions\when('wc_get_order')->justReturn('mocked');
        $this->assertEquals('mocked', wc_get_order(123));
    }

    protected function tearDown(): void
    {
        // Restaurar el stream wrapper original
        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_restore('php');
        }

        parent::tearDown();
        Brain\Monkey\tearDown();
    }

    public function test_ipn_should_call_is_valid_ipn_data() {

        $order_mock = $this->getMockBuilder('WC_Order')
            ->disableOriginalConstructor()
            ->onlyMethods(['is_paid'])
            ->getMock();

        $order_id = 12345;

        Functions\when('wc_get_order')->justReturn($order_mock);

        $data = [
            'data' => [
                'transaction' => [
                    'reference' => $order_id,
                    'status' => 'APPROVED',
                    'id' => 'tx123',
                    'amount_in_cents' => 5000
                ]
            ],
            'signature' => [
                'checksum' => 'valid_checksum'
            ],
            'timestamp' => time()
        ];
        MockPhpInputStream::$mockInput = json_encode($data);

        $this->gateway->expects($this->once())
            ->method('is_valid_ipn_data')
            ->with($data)
            ->willReturn(true);

        $this->gateway->expects($this->once())
            ->method('is_valid_checksum')
            ->with($data)
            ->willReturn(true);

        $this->gateway->expects($this->never())
            ->method('update_order_status');

        $this->gateway->confirmation_ipn();
    }
}

class MockPhpInputStream {
    protected $index = 0;
    protected $data;
    public static $mockInput;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $this->data = self::$mockInput;
        $this->index = 0;
        return true;
    }

    public function stream_read($count) {
        $ret = substr($this->data, $this->index, $count);
        $this->index += strlen($ret);
        return $ret;
    }

    public function stream_write($data) {
        return strlen($data);
    }

    public function stream_eof() {
        return $this->index >= strlen($this->data);
    }

    // Métodos obligatorios aunque no se usen
    public function stream_stat() { return []; }

    public function stream_seek($offset, $whence = SEEK_SET) {
        $length = strlen($this->data);
        switch ($whence) {
            case SEEK_SET:
                if ($offset >= 0 && $offset <= $length) {
                    $this->index = $offset;
                    return true;
                }
                return false;
            case SEEK_CUR:
                $newIndex = $this->index + $offset;
                if ($newIndex >= 0 && $newIndex <= $length) {
                    $this->index = $newIndex;
                    return true;
                }
                return false;
            case SEEK_END:
                $newIndex = $length + $offset;
                if ($newIndex >= 0 && $newIndex <= $length) {
                    $this->index = $newIndex;
                    return true;
                }
                return false;
            default:
                return false;
        }
    }

    public function stream_tell() {
        return $this->index;
    }
}