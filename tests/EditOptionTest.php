<?php
namespace Nhrotm\OptionsTableManager\Tests;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use Nhrotm\OptionsTableManager\Ajax;

// Define the exception class if needed
if (!class_exists('WP_Die_Exception')) {
    class WP_Die_Exception extends \Exception {}
}

class EditOptionTest extends TestCase {
    private $ajax;

    protected function setUp(): void {
        WP_Mock::setUp();
        
        // Create a partial mock of the Ajax class instead of the main plugin class
        $this->ajax = $this->getMockBuilder(Ajax::class)
            ->setMethods(['get_protected_options', 'sanitize_array_recursive'])
            ->getMock();
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
     * Test failed nonce verification
     */
    public function testFailedNonceVerification() {
        WP_Mock::userFunction('wp_die')
            ->with()
            ->andThrow(new WP_Die_Exception());
        
        // Mock sanitize_text_field to return whatever is passed to it
        WP_Mock::userFunction('sanitize_text_field')
            ->with('invalid_nonce')
            ->andReturn('invalid_nonce');

        // Mock wp_unslash to return whatever is passed to it
        WP_Mock::userFunction('wp_unslash')
            ->with('invalid_nonce')
            ->andReturn('invalid_nonce');
            
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

        // Mock sanitize_text_field to return whatever is passed to it
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });

        // Mock wp_unslash to return whatever is passed to it
        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });
            
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

        // Mock sanitize_text_field to return whatever is passed to it
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });

        // Mock wp_unslash to return whatever is passed to it
        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });
            
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

        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        // Mock protected options method
        $this->ajax->expects($this->once())
            ->method('get_protected_options')
            ->willReturn([]);

        // Mock wp_send_json_error
        WP_Mock::userFunction('wp_send_json_error')
            ->with('Failed to update option')
            ->once();

        // Mock WP globals with malicious serialized object
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'test_option',
            'option_value' => 'O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}'
        ];

        // Call the method (expect wp_die to be called)
        $this->expectException(WP_Die_Exception::class);
        $this->ajax->edit_option();
    }

    /**
     * Test successful option update with a serialized array value
     */
    public function testSuccessfulOptionUpdateWithSerializedArray() {
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
            
        // The serialized array to test
        $serializedData = 'a:4:{s:8:"username";s:6:"nhrrob";s:6:"preset";s:7:"default";s:13:"cacheDuration";i:43200;s:12:"postsPerPage";i:10;}';
        
        // Expected unserialized array
        $unserializedData = [
            'username' => 'nhrrob',
            'preset' => 'default',
            'cacheDuration' => 43200,
            'postsPerPage' => 10
        ];
        
        // Mock WordPress functions
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });
            
        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });
            
        // Mock get_option for the original value
        WP_Mock::userFunction('get_option')
            ->with('test_option')
            ->andReturn('original_serialized_value');
            
        // Mock is_serialized checks
        WP_Mock::userFunction('is_serialized')
            ->with('original_serialized_value')
            ->andReturn(true);
            
        WP_Mock::userFunction('is_serialized')
            ->with($serializedData)
            ->andReturn(true);
        
        // Mock the array sanitization
        $this->ajax->expects($this->once())
            ->method('sanitize_array_recursive')
            ->with($this->equalTo($unserializedData))
            ->willReturn($unserializedData);
        
        // Mock update_option
        WP_Mock::userFunction('update_option')
            ->with('test_option', $unserializedData, null)
            ->once()
            ->andReturn(true);
        
        // Mock wp_send_json_success
        WP_Mock::userFunction('wp_send_json_success')
            ->with('Option updated successfully')
            ->once();
        
        // Mock wp_die function
        WP_Mock::userFunction('wp_die')
            ->once();
        
        // Mock $_POST data
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'test_option',
            'option_value' => $serializedData
        ];
        
        // Mock protected options method
        $this->ajax->expects($this->once())
            ->method('get_protected_options')
            ->willReturn([]);
        
        // Call the method
        $this->ajax->edit_option();
    }

    /**
     * Test option update with modified serialized array
     */
    public function testSuccessfulOptionUpdateWithModifiedSerializedArray() {
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

        // The original serialized array
        $originalSerializedData = 'a:4:{s:8:"username";s:6:"nhrrob";s:6:"preset";s:7:"default";s:13:"cacheDuration";i:43200;s:12:"postsPerPage";i:10;}';
        
        // The modified serialized array (changed username and postsPerPage)
        // Make sure this doesn't match the object serialization pattern in preg_match
        $modifiedSerializedData = 'a:4:{s:8:"username";s:7:"nhrrob2";s:6:"preset";s:7:"default";s:13:"cacheDuration";i:43200;s:12:"postsPerPage";i:20;}';
        
        // Expected unserialized array after modification
        $modifiedUnserializedData = [
            'username' => 'nhrrob2',
            'preset' => 'default',
            'cacheDuration' => 43200,
            'postsPerPage' => 20
        ];

        // Mock WordPress functions
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });

        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        // Mock get_option to return the original serialized data
        WP_Mock::userFunction('get_option')
            ->with('test_option')
            ->once()
            ->andReturn($originalSerializedData);

        // Mock is_serialized for the original value - this check happens in the function
        WP_Mock::userFunction('is_serialized')
            ->with($originalSerializedData)
            ->once()
            ->andReturn(true);
            
        // Mock is_serialized for the modified value
        WP_Mock::userFunction('is_serialized')
            ->with($modifiedSerializedData)
            ->once()
            ->andReturn(true);

        // Note: We're not mocking preg_match as it's a PHP internal function
        // The serialized data provided doesn't match the object serialization pattern
        // so the real preg_match() will return 0

        // Mock sanitization of the array
        $this->ajax->expects($this->once())
            ->method('sanitize_array_recursive')
            ->with($this->equalTo($modifiedUnserializedData))
            ->willReturn($modifiedUnserializedData);

        // Mock the update_option function
        WP_Mock::userFunction('update_option')
            ->with('test_option', $modifiedUnserializedData, null)
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
            'option_value' => $modifiedSerializedData
        ];

        // Mock protected options method
        $this->ajax->expects($this->once())
            ->method('get_protected_options')
            ->willReturn([]);

        // Call the method
        $this->ajax->edit_option();
    }

    /**
     * Test option update with a JSON encoded array
     */
    public function testSuccessfulOptionUpdateWithJSONArray() {
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

        // JSON data - this is valid JSON that will be processed by the real json_decode() function
        // Important: Make sure this JSON doesn't match the object serialization pattern
        // that would be caught by the real preg_match() function in edit_option()
        $jsonData = '{"username":"nhrrob","preset":"default","cacheDuration":43200,"postsPerPage":10}';
        
        // Expected decoded array that will be returned by the real json_decode()
        $decodedData = [
            'username' => 'nhrrob',
            'preset' => 'default',
            'cacheDuration' => 43200,
            'postsPerPage' => 10
        ];

        // Mock WordPress functions
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });

        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        // Mock get_option to return the original value
        $originalValue = 'some_non_serialized_value';
        WP_Mock::userFunction('get_option')
            ->with('test_option')
            ->once()
            ->andReturn($originalValue);

        // Mock is_serialized for the original value
        WP_Mock::userFunction('is_serialized')
            ->with($originalValue)
            ->once()
            ->andReturn(false);

        // No need to mock preg_match() as it's a PHP internal function
        // The JSON data provided doesn't match the serialization pattern
        // so the real preg_match() will return 0

        // Mock sanitization of the array
        $this->ajax->expects($this->once())
            ->method('sanitize_array_recursive')
            ->with($this->equalTo($decodedData))
            ->willReturn($decodedData);

        // Mock the update_option function
        WP_Mock::userFunction('update_option')
            ->with('test_option', $decodedData, null)
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
     * Test option update with complex nested serialized array including booleans
     */
    public function testSuccessfulOptionUpdateWithComplexNestedArray() {
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

        // The complex serialized array with nested structures and boolean
        $complexSerializedData = 'a:3:{s:17:"holiday_24_notice";a:4:{s:5:"start";i:1738607502;s:10:"recurrence";b:0;s:7:"refresh";s:5:"6.1.1";s:6:"expire";i:1736553599;}s:7:"version";s:5:"1.1.0";s:6:"review";a:3:{s:5:"start";i:1739212330;s:10:"recurrence";i:30;s:7:"refresh";s:5:"6.1.1";}}';
        
        // Expected unserialized array
        $complexUnserializedData = [
            'holiday_24_notice' => [
                'start' => 1738607502,
                'recurrence' => false,
                'refresh' => '6.1.1',
                'expire' => 1736553599
            ],
            'version' => '1.1.0',
            'review' => [
                'start' => 1739212330,
                'recurrence' => 30,
                'refresh' => '6.1.1'
            ]
        ];

        // Mock WordPress functions
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });

        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        // Mock get_option to return some original value
        WP_Mock::userFunction('get_option')
            ->with('complex_option')
            ->once()
            ->andReturn('original_value');

        // Mock is_serialized for both checks
        WP_Mock::userFunction('is_serialized')
            ->with('original_value')
            ->once()
            ->andReturn(false);

        WP_Mock::userFunction('is_serialized')
            ->with($complexSerializedData)
            ->once()
            ->andReturn(true);

        // Mock sanitization of the array to preserve structure including booleans
        $this->ajax->expects($this->once())
            ->method('sanitize_array_recursive')
            ->with($this->equalTo($complexUnserializedData))
            ->willReturn($complexUnserializedData);

        // Mock the update_option function
        WP_Mock::userFunction('update_option')
            ->with('complex_option', $complexUnserializedData, null)
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
            'option_name' => 'complex_option',
            'option_value' => $complexSerializedData
        ];

        // Mock protected options method
        $this->ajax->expects($this->once())
            ->method('get_protected_options')
            ->willReturn([]);

        // Call the method
        $this->ajax->edit_option();
    }
}