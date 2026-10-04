<?php
namespace Nhrotm\OptionsTableManager\Tests;

use Nhrotm\OptionsTableManager\Services\ToolsService;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Import must read both export formats (2.x and 1.x/Classic) and reject a
 * file whose checksum no longer matches.
 */
class ImportParseTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( 'wp_json_encode' )->andReturnUsing(
			function ( $data ) {
				return json_encode( $data );
			}
		);
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function parse( $json ) {
		$service = ( new \ReflectionClass( ToolsService::class ) )->newInstanceWithoutConstructor();
		$method  = new \ReflectionMethod( ToolsService::class, 'parse_import' );
		$method->setAccessible( true );
		return $method->invoke( $service, $json );
	}

	public function testReadsCurrentFormat() {
		$options = [ [ 'option_name' => 'a', 'option_value' => '1', 'autoload' => 'no' ] ];
		$rows    = $this->parse( json_encode( [ 'options' => $options, 'checksum' => md5( json_encode( $options ) ) ] ) );
		$this->assertSame( [ 'name' => 'a', 'value' => '1', 'autoload' => 'no' ], $rows['a'] );
	}

	public function testReadsClassicFormat() {
		$options = [ [ 'name' => 'b', 'value' => 'a:0:{}', 'autoload' => 'yes' ] ];
		$rows    = $this->parse( json_encode( [ 'meta' => [], 'options' => $options, 'checksum' => md5( json_encode( $options ) ) ] ) );
		$this->assertSame( 'a:0:{}', $rows['b']['value'] );
		$this->assertSame( 'yes', $rows['b']['autoload'] );
	}

	public function testRejectsChecksumMismatch() {
		$this->expectException( \Exception::class );
		$this->parse( json_encode( [ 'options' => [ [ 'name' => 'c', 'value' => 'x' ] ], 'checksum' => 'bad' ] ) );
	}
}
