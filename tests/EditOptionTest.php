<?php
namespace Nhrotm\OptionsTableManager\Tests;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use Nhrotm\OptionsTableManager\Ajax;

class EditOptionTest extends TestCase {
    private $ajax;

    protected function setUp(): void {
        WP_Mock::setUp();
        
        // Create a partial mock of the Ajax class instead of the main plugin class
        $this->ajax = $this->getMockBuilder(Ajax::class)
            ->setMethods(['get_protected_options', 'sanitize_array_recursive'])
            ->getMock();

        // // Create a partial mock of the plugin class
        // $this->plugin = $this->getMockBuilder(\Nhrotm_Options_Table_Manager::class)
        //     // ->onlyMethods(['get_protected_options', 'sanitize_array_recursive'])
        //     ->getMock();
    }

    protected function tearDown(): void {
        WP_Mock::tearDown();
    }

    /**
     * Test successful option update with a simple string value
     */
    public function testSuccessfulOptionUpdateWithStringValue() {
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

        // Mock user capabilities
        WP_Mock::userFunction('current_user_can')
            ->with('manage_options')
            ->once()
            ->andReturn(true);

        // Mock sanitization and option update functions
        // WP_Mock::userFunction('sanitize_text_field')
        //     ->with('test_value')
        //     ->once()
        //     ->andReturn('test_value');

        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Simply return the input for any call
            });

        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        WP_Mock::userFunction('get_option')
            ->with('test_option')
            ->once()
            ->andReturn('original_value');

        WP_Mock::userFunction('is_serialized')
            ->andReturn(false);

        WP_Mock::userFunction('update_option')
            ->with('test_option', 'test_value', null)
            ->once()
            ->andReturn(true);

        // Mock wp_send_json_success
        WP_Mock::userFunction('wp_send_json_success')
            ->with('Option updated successfully')
            ->once();

        // Mock wp_die function
        WP_Mock::userFunction('wp_die')
            ->once();

        // Mock WP globals
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'test_option',
            'option_value' => 'test_value'
        ];

        // Mock protected options method
        $this->ajax->expects($this->once())
            ->method('get_protected_options')
            ->willReturn([]);

        $this->ajax->edit_option();
    }

    /**
     * Test option update with JSON array value
     */
    public function testSuccessfulOptionUpdateWithJsonArray() {
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

        // Mock user capabilities
        WP_Mock::userFunction('current_user_can')
            ->with('manage_options')
            ->once()
            ->andReturn(true);

        // This is correctly formatted JSON that will decode properly with the real json_decode()
        $jsonData = '{"key1":"value1","key2":"value2"}';

        // Mock sanitize_text_field for multiple calls with different parameters
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Simply return the input for any call
            });

        // Mock other WordPress functions
        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        WP_Mock::userFunction('get_option')
            ->with('test_option')
            ->once()
            ->andReturn('original_value');

        WP_Mock::userFunction('is_serialized')
            ->andReturn(false);

        // Mock sanitization and option update functions
        $sanitizedArray = ['key1' => 'value1', 'key2' => 'value2'];
        $this->ajax->expects($this->once())
            ->method('sanitize_array_recursive')
            ->with($this->equalTo(['key1' => 'value1', 'key2' => 'value2']))
            ->willReturn($sanitizedArray);

        WP_Mock::userFunction('update_option')
            ->with('test_option', $sanitizedArray, null)
            ->once()
            ->andReturn(true);

        // Mock wp_send_json_success
        WP_Mock::userFunction('wp_send_json_success')
            ->with('Option updated successfully')
            ->once();

        // Mock wp_die function
        WP_Mock::userFunction('wp_die')
            ->once();

        // Mock WP globals
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'test_option',
            'option_value' => $jsonData
        ];

        // Mock protected options method
        $this->ajax->expects($this->once())
            ->method('get_protected_options')
            ->willReturn([]);

        // Call the method
        $this->ajax->edit_option();
    }

    /**
     * Test failed nonce verification
     */
    public function testFailedNonceVerification() {
        WP_Mock::userFunction('wp_die')
            ->with()
            ->andThrow(new WP_Die_Exception());
            
        // Mock nonce verification failure
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('invalid_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(false);

        // Mock wp_send_json_error
        WP_Mock::userFunction('wp_send_json_error')
            ->with('Invalid nonce')
            ->once();

        // Mock WP globals
        $_POST = [
            'nonce' => 'invalid_nonce'
        ];

        // Call the method (expect wp_die to be called)
        $this->expectException(WP_Die_Exception::class);
        
        $this->ajax->edit_option();
    }

    /**
     * Test insufficient user permissions
     */
    public function testInsufficientPermissions() {
        WP_Mock::userFunction('wp_die')
            ->with()
            ->andThrow(new WP_Die_Exception());

        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

        // Mock user capabilities failure
        WP_Mock::userFunction('current_user_can')
            ->with('manage_options')
            ->once()
            ->andReturn(false);

        // Mock wp_send_json_error
        WP_Mock::userFunction('wp_send_json_error')
            ->with('Insufficient permissions')
            ->once();

        // Mock WP globals
        $_POST = [
            'nonce' => 'test_nonce'
        ];

        // Call the method (expect wp_die to be called)
        $this->expectException(WP_Die_Exception::class);
        $this->ajax->edit_option();
    }

    /**
     * Test attempt to update a protected option
     */
    public function testProtectedOptionUpdate() {
        WP_Mock::userFunction('wp_die')
            ->with()
            ->andThrow(new WP_Die_Exception());

        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

        // Mock user capabilities
        WP_Mock::userFunction('current_user_can')
            ->with('manage_options')
            ->once()
            ->andReturn(true);
        
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Simply return the input for any call
            });

        // Mock protected options method
        $this->ajax->expects($this->once())
            ->method('get_protected_options')
            ->willReturn(['protected_option']);

        // Mock wp_send_json_error
        WP_Mock::userFunction('wp_send_json_error')
            ->with('This option is protected and cannot be edited')
            ->once();

        // Mock WP globals
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'protected_option',
            'option_value' => 'test_value'
        ];

        // Call the method (expect wp_die to be called)
        $this->expectException(WP_Die_Exception::class);
        $this->ajax->edit_option();
    }

    /**
     * Test prevention of object serialization exploit
     */
    public function testPreventObjectSerializationExploit() {
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

        // Mock user capabilities
        WP_Mock::userFunction('current_user_can')
            ->with('manage_options')
            ->once()
            ->andReturn(true);

        // Mock protected options method
        $this->plugin->expects($this->once())
            ->method('get_protected_options')
            ->willReturn([]);

        // Mock wp_send_json_error
        WP_Mock::userFunction('wp_send_json_error')
            ->with('Object serialization is not allowed')
            ->once();

        // Mock WP globals with malicious serialized object
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'test_option',
            'option_value' => 'O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}'
        ];

        // Call the method (expect wp_die to be called)
        $this->expectException(\WP_Die_Exception::class);
        $this->plugin->edit_option();
    }
}

class WP_Die_Exception extends \Exception {}