<?php
namespace Nhrotm\OptionsTableManager\Tests;

use Nhrotm\OptionsTableManager\Services\SettingsService;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * The front-end usage tracker reads its own autoloaded flag, so saving the
 * setting must keep that flag in sync.
 */
class SettingsServiceTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( 'wp_parse_args' )->andReturnUsing(
			function ( $args, $defaults ) {
				return array_merge( $defaults, $args );
			}
		);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	public function testEnablingTrackingIsSavedInTheSingleOption() {
		WP_Mock::userFunction( 'get_option' )->andReturn( [] );
		// One autoloaded option holds every setting; there is no separate flag option.
		WP_Mock::userFunction( 'update_option' )->with( 'nhrotm_settings', \Mockery::type( 'array' ), true )->once();

		$saved = ( new SettingsService() )->update( [ 'usage_tracking_enabled' => true ] );

		$this->assertTrue( $saved['usage_tracking_enabled'] );
		$this->assertFalse( $saved['allow_html_in_values'] ); // Off unless enabled.
	}

	public function testDisablingTrackingIsSavedInTheSingleOption() {
		WP_Mock::userFunction( 'get_option' )->andReturn( [] );
		WP_Mock::userFunction( 'update_option' )->with( 'nhrotm_settings', \Mockery::type( 'array' ), true )->once();

		$saved = ( new SettingsService() )->update( [ 'usage_tracking_enabled' => false ] );
		$this->assertFalse( $saved['usage_tracking_enabled'] );
	}

	public function testMigrateNormalizesLegacyStringBooleans() {
		$legacy = [
			'nhrotm_settings'               => null,
			'nhrotm_allow_html_in_values'   => 'true',
			'nhrotm_auto_cleanup_enabled'   => 'false',
			'nhrotm_usage_tracking_enabled' => 'true',
			'nhrotm_backup_frequency'       => 'weekly',
			'nhrotm_history_retention_days' => '14',
		];
		WP_Mock::userFunction( 'get_option' )->andReturnUsing(
			function ( $name ) use ( $legacy ) {
				return isset( $legacy[ $name ] ) ? $legacy[ $name ] : null;
			}
		);
		WP_Mock::userFunction( 'delete_option' );
		WP_Mock::userFunction( 'wp_set_option_autoload' );
		WP_Mock::userFunction( 'wp_clear_scheduled_hook' );
		$saved = null;
		WP_Mock::userFunction( 'update_option' )->andReturnUsing(
			function ( $name, $value ) use ( &$saved ) {
				if ( 'nhrotm_settings' === $name ) {
					$saved = $value;
				}
				return true;
			}
		);

		( new SettingsService() )->migrate();

		$this->assertTrue( $saved['allow_html_in_values'] );
		// The old switch is gone; it was off, so no schedule is created.
		$this->assertArrayNotHasKey( 'auto_cleanup_enabled', $saved );
		$this->assertSame( [], $saved['cleanup_schedules'] );
		$this->assertTrue( $saved['usage_tracking_enabled'] );
		$this->assertSame( 'weekly', $saved['backup_frequency'] );
		$this->assertSame( 14, $saved['history_retention_days'] );
	}

	public function testEnabledDailyCleanupBecomesATransientSchedule() {
		WP_Mock::userFunction( 'get_option' )->andReturnUsing(
			function ( $name ) {
				return 'nhrotm_settings' === $name ? [ 'auto_cleanup_enabled' => true ] : null;
			}
		);
		WP_Mock::userFunction( 'delete_option' );
		WP_Mock::userFunction( 'wp_set_option_autoload' );
		WP_Mock::userFunction( 'wp_clear_scheduled_hook' )->with( 'nhrotm_daily_cleanup' )->once();
		$saved = null;
		WP_Mock::userFunction( 'update_option' )->andReturnUsing(
			function ( $name, $value ) use ( &$saved ) {
				if ( 'nhrotm_settings' === $name ) {
					$saved = $value;
				}
				return true;
			}
		);

		( new SettingsService() )->migrate();

		$this->assertSame(
			[
				'frequency' => 'daily',
				'days'      => 0,
			],
			$saved['cleanup_schedules']['expired_transients']
		);
	}
}
