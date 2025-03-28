<?php
namespace Nhrotm\OptionsTableManager\Tests;

use Nhrotm\OptionsTableManager\Managers\OptionsTableManager;
use PHPUnit\Framework\TestCase;
use WP_Mock;
use Nhrotm\OptionsTableManager\Services\ValidationService;

// Define the exception class if needed
if (!class_exists('WP_Die_Exception')) {
    class WP_Die_Exception extends \Exception {}
}

class EditOptionTest extends TestCase {
    private $options_manager;
    private $validation_service;

    protected function setUp(): void {
        WP_Mock::setUp();
        
        // Create mocks for dependencies
        $this->validation_service = $this->createMock(ValidationService::class);
        $this->options_manager = $this->getMockBuilder(OptionsTableManager::class)
            ->setConstructorArgs([$this->validation_service])
            ->onlyMethods(['validate_permissions', 'is_protected_item'])
            ->getMock();
    }

    protected function tearDown(): void {
        WP_Mock::tearDown();
        unset($_POST);
    }

    /**
     * Test successful option update with a simple string value
     */ 
    public function testSuccessfulPlainStringUpdate() {
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

        $this->options_manager->expects($this->once())
            ->method('validate_permissions');

        // Mock protected item check
        $this->options_manager->expects($this->once())
            ->method('is_protected_item')
            ->with('test_option')
            ->willReturn(false);

        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Simply return the input for any call
            });

        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        WP_Mock::userFunction('is_serialized')
            ->andReturn(false);

        WP_Mock::userFunction('update_option')
            ->with('test_option', 'test_value', null)
            ->once()
            ->andReturn(true);

        // Mock WP globals
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'test_option',
            'option_value' => 'test_value'
        ];

        $result = $this->options_manager->edit_record();
        $this->assertTrue($result);
    }

    /**
     * Test option update with a JSON encoded array
     */
    public function testSuccessfulJSONArrayUpdate() {
        // Prepare JSON data
        $jsonData = json_encode(['key1' => 'value1', 'key2' => 123]);
        $decodedData = ['key1' => 'value1', 'key2' => 123];
        
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

        // Mock permissions validation
        $this->options_manager->expects($this->once())
            ->method('validate_permissions');

        // Mock protected item check
        $this->options_manager->expects($this->once())
            ->method('is_protected_item')
            ->with('json_option')
            ->willReturn(false);

        // Mock update_option
        WP_Mock::userFunction('update_option')
            ->with('json_option', $decodedData, null)
            ->once()
            ->andReturn(true);

        // Mock WordPress functions
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });

        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        WP_Mock::userFunction('sanitize_key')
            ->andReturnUsing(function($input) {
                return $input;
            });
            
        WP_Mock::userFunction('is_email')
            ->andReturn(false);

        // Set up $_POST data
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'json_option',
            'option_value' => $jsonData
        ];

        $result = $this->options_manager->edit_record();
        $this->assertTrue($result);
    }

    /**
     * Test successful option update with a serialized array value
     */
    public function testSuccessfulSerializedArrayUpdate() {
        // Prepare serialized data
        $serializedData = serialize(['key1' => 'value1', 'key2' => 123]);
        $unserializedData = ['key1' => 'value1', 'key2' => 123];
        
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

        // Mock is_serialized
        WP_Mock::userFunction('is_serialized')
            ->with($serializedData)
            ->once()
            ->andReturn(true);
        
        // Mock WordPress functions
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });
            
        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        WP_Mock::userFunction('sanitize_key')
            ->andReturnUsing(function($input) {
                return $input;
            });

        WP_Mock::userFunction('is_email')
            ->andReturn(false);

        // Mock permissions validation
        $this->options_manager->expects($this->once())
            ->method('validate_permissions');

        // Mock protected item check
        $this->options_manager->expects($this->once())
            ->method('is_protected_item')
            ->with('serialized_option')
            ->willReturn(false);
        
        // Mock update_option
        WP_Mock::userFunction('update_option')
            ->with('serialized_option', $unserializedData, null)
            ->once()
            ->andReturn(true);

        // Set up $_POST data
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'serialized_option',
            'option_value' => $serializedData
        ];

        $result = $this->options_manager->edit_record();
        $this->assertTrue($result);
    }

    /**
     * Test invalid nonce
     */
    public function testInvalidNonce() {
        // Mock nonce verification failure
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('invalid_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(false);
        
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });

        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        // Set up $_POST data
        $_POST = [
            'nonce' => 'invalid_nonce',
            'option_name' => 'test_option',
            'option_value' => 'test_value'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid nonce');

        $this->options_manager->edit_record();
    }

    /**
     * Test protected option update
     */
    public function testProtectedOptionUpdate() {
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });

        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        // Mock permissions validation
        $this->options_manager->expects($this->once())
            ->method('validate_permissions');

        // Mock protected item check
        $this->options_manager->expects($this->once())
            ->method('is_protected_item')
            ->with('protected_option')
            ->willReturn(true);

        // Set up $_POST data
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'protected_option',
            'option_value' => 'test_value'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This option is protected and cannot be edited');

        $this->options_manager->edit_record();
    }

    /**
     * Test missing option name
     */
    public function testMissingOptionName() {
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });

        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        // Mock permissions validation
        $this->options_manager->expects($this->once())
            ->method('validate_permissions');

        // Set up $_POST data without option name
        $_POST = [
            'nonce' => 'test_nonce',
            'option_value' => 'test_value'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Option name is required');

        $this->options_manager->edit_record();
    }

    /**
     * Test missing option value
     */
    public function testMissingOptionValue() {
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input; // Return the input for any call
            });

        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        // Mock permissions validation
        $this->options_manager->expects($this->once())
            ->method('validate_permissions');

        // Set up $_POST data without option value
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'test_option'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Option value is required');

        $this->options_manager->edit_record();
    }

    public function testInsufficientPermissions() {
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

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
    
        // Mock permissions validation to throw an exception
        $this->options_manager->expects($this->once())
            ->method('validate_permissions')
            ->willThrowException(new \Exception('Insufficient permissions'));
    
        // Set up $_POST data
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'test_option',
            'option_value' => 'test_value'
        ];
    
        // Expect an exception to be thrown
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient permissions');

        $this->options_manager->edit_record();
    }

    public function testPreventObjectSerializationExploit() {
        // Malicious serialized object that could potentially exploit unserialize
        $maliciousObject = serialize(new \stdClass());
    
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);

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

        WP_Mock::userFunction('sanitize_key')
            ->andReturnUsing(function($input) {
                return $input;
            });

        WP_Mock::userFunction('is_email')
            ->andReturn(false);

        WP_Mock::userFunction('update_option')
            ->andReturn(false);
    
        // Mock permissions validation
        $this->options_manager->expects($this->once())
            ->method('validate_permissions');
    
        // Mock protected item check
        $this->options_manager->expects($this->once())
            ->method('is_protected_item')
            ->with('exploit_option')
            ->willReturn(false);
    
        // Mock is_serialized to return true
        WP_Mock::userFunction('is_serialized')
            ->with($maliciousObject)
            ->once()
            ->andReturn(true);
    
        // Set up $_POST data with malicious serialized object
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'exploit_option',
            'option_value' => $maliciousObject
        ];
    
        // Expect the method to handle the exploit attempt
        $result = $this->options_manager->edit_record();
        $this->assertFalse($result);
    }

    public function testSuccessfulOptionUpdateWithModifiedSerializedArray() {
        // Prepare serialized array data
        $serializedData = serialize(['key1' => 'value1', 'key2' => 'value2']);
        $decodedData = ['key1' => 'value1', 'key2' => 'value2'];
    
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);
    
        // Mock permissions validation
        $this->options_manager->expects($this->once())
            ->method('validate_permissions');
    
        // Mock protected item check
        $this->options_manager->expects($this->once())
            ->method('is_protected_item')
            ->with('serialized_option')
            ->willReturn(false);
    
        // Mock is_serialized to return true
        WP_Mock::userFunction('is_serialized')
            ->with($serializedData)
            ->once()
            ->andReturn(true);
    
        // Mock update_option
        WP_Mock::userFunction('update_option')
            ->with('serialized_option', $decodedData, null)
            ->once()
            ->andReturn(true);
    
        // Mock WordPress utility functions
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input;
            });
    
        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        WP_Mock::userFunction('sanitize_key')
            ->andReturnUsing(function($input) {
                return $input;
            });

        WP_Mock::userFunction('is_email')
            ->andReturn(false);
    
        // Set up $_POST data
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'serialized_option',
            'option_value' => $serializedData
        ];
    
        $result = $this->options_manager->edit_record();
        $this->assertTrue($result);
    }

    public function testSuccessfulOptionUpdateWithComplexNestedArray() {
        // Prepare complex nested array
        $complexData = [
            'key1' => 'value1',
            'nested' => [
                'subkey1' => 'subvalue1',
                'subarray' => [
                    'deepkey' => 'deepvalue'
                ]
            ],
            'numeric_array' => [1, 2, 3]
        ];
        $jsonData = json_encode($complexData);
    
        // Mock nonce verification
        WP_Mock::userFunction('wp_verify_nonce')
            ->with('test_nonce', 'nhrotm-admin-nonce')
            ->once()
            ->andReturn(true);
    
        // Mock permissions validation
        $this->options_manager->expects($this->once())
            ->method('validate_permissions');
    
        // Mock protected item check
        $this->options_manager->expects($this->once())
            ->method('is_protected_item')
            ->with('complex_option')
            ->willReturn(false);

    
        // Mock update_option
        WP_Mock::userFunction('update_option')
            ->with('complex_option', $complexData, null)
            ->once()
            ->andReturn(true);
    
        // Mock WordPress utility functions
        WP_Mock::userFunction('sanitize_text_field')
            ->andReturnUsing(function($input) {
                return $input;
            });
    
        WP_Mock::userFunction('wp_unslash')
            ->andReturnUsing(function($input) {
                return $input;
            });

        WP_Mock::userFunction('sanitize_key')
            ->andReturnUsing(function($input) {
                return $input;
            });

        WP_Mock::userFunction('is_email')
            ->andReturn(false);
    
        // Mock is_serialized to return false for JSON
        WP_Mock::userFunction('is_serialized')
            ->andReturn(false);
    
        // Set up $_POST data
        $_POST = [
            'nonce' => 'test_nonce',
            'option_name' => 'complex_option',
            'option_value' => $jsonData
        ];
    
        $result = $this->options_manager->edit_record();
        $this->assertTrue($result);
    }
}