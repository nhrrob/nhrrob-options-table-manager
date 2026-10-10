<?php
namespace Nhrotm\OptionsTableManager\Tests;

use Nhrotm\OptionsTableManager\Core\Abilities;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * What AI agents and MCP clients may do is a fixed, reviewed list: adding an
 * ability (or loosening one) must be a deliberate change to this test.
 */
class AbilitiesTest extends TestCase {

	const READ_ONLY = [
		'nhrotm/get-health',
		'nhrotm/list-cleanup-types',
		'nhrotm/preview-cleanup',
		'nhrotm/get-autoload-report',
		'nhrotm/list-tables',
		'nhrotm/list-cron-events',
		'nhrotm/search-options',
		'nhrotm/list-activity',
	];

	const WRITES = [
		'nhrotm/run-cleanup',
		'nhrotm/disable-option-autoload',
		'nhrotm/optimize-table',
		'nhrotm/create-snapshot',
	];

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	public function testTheAbilityListIsExactlyTheReviewedOne() {
		$this->assertSame(
			array_merge( self::READ_ONLY, self::WRITES ),
			array_keys( ( new Abilities() )->definitions() )
		);
	}

	public function testEveryAbilityIsBehindTheCapabilityGate() {
		$abilities = new Abilities();
		foreach ( $abilities->definitions() as $name => $args ) {
			$this->assertSame( [ $abilities, 'can_manage' ], $args['permission_callback'], $name );
			$this->assertSame( Abilities::CATEGORY, $args['category'], $name );
			$this->assertIsCallable( $args['execute_callback'], $name );
		}

		WP_Mock::userFunction( 'current_user_can' )->with( 'manage_options' )->andReturn( false );
		$this->assertFalse( $abilities->can_manage() );
	}

	public function testAnnotationsTellAgentsWhichAbilitiesChangeData() {
		foreach ( ( new Abilities() )->definitions() as $name => $args ) {
			$annotations = $args['meta']['annotations'];
			$this->assertSame( in_array( $name, self::READ_ONLY, true ), $annotations['readonly'], $name );
			// Deleting rows is the only irreversible thing on offer.
			$this->assertSame( 'nhrotm/run-cleanup' === $name, $annotations['destructive'], $name );
		}
	}
}
