<?php
namespace Nhrotm\OptionsTableManager\Tests;

use Nhrotm\OptionsTableManager\Services\ActivityService;
use Nhrotm\OptionsTableManager\Services\CleanupService;
use Nhrotm\OptionsTableManager\Services\CronService;
use Nhrotm\OptionsTableManager\Services\NetworkService;
use Nhrotm\OptionsTableManager\Services\TablesService;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * The destructive database-cleaner actions must refuse what they should never touch.
 */
class SafetyRulesTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function tables( array $rows ) {
		$service = $this->getMockBuilder( TablesService::class )
			->setConstructorArgs( [ $this->createMock( ActivityService::class ) ] )
			->onlyMethods( [ 'all' ] )
			->getMock();
		$service->method( 'all' )->willReturn( $rows );
		return $service;
	}

	public function testCoreAndInstalledPluginTablesCannotBeDroppedOrEmptied() {
		WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
		$service = $this->tables(
			[
				[ 'name' => 'wp_options', 'kind' => 'core', 'removable' => false ],
				[ 'name' => 'wp_wc_orders', 'kind' => 'plugin', 'removable' => false ],
			]
		);
		// Even with the name typed correctly.
		$this->assertIsString( $service->run( 'wp_options', 'drop', 'wp_options' ) );
		$this->assertIsString( $service->run( 'wp_options', 'empty', 'wp_options' ) );
		$this->assertIsString( $service->run( 'wp_wc_orders', 'drop', 'wp_wc_orders' ) );
	}

	public function testMainSiteOfANetworkNeedsANetworkAdministrator() {
		WP_Mock::userFunction( 'is_multisite' )->andReturn( true );
		WP_Mock::userFunction( 'is_main_site' )->andReturn( true );
		WP_Mock::userFunction( 'current_user_can' )->with( 'manage_network' )->andReturn( false );
		$service = $this->tables( [ [ 'name' => 'wp_old_plugin', 'kind' => 'leftover', 'removable' => true ] ] );
		// Even a harmless action, and even with the name typed correctly.
		$this->assertIsString( $service->run( 'wp_old_plugin', 'optimize' ) );
		$this->assertIsString( $service->run( 'wp_old_plugin', 'drop', 'wp_old_plugin' ) );
	}

	public function testLeftoverTableNeedsItsNameTypedExactly() {
		WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
		$service = $this->tables( [ [ 'name' => 'wp_old_plugin', 'kind' => 'leftover', 'removable' => true ] ] );
		$this->assertIsString( $service->run( 'wp_old_plugin', 'drop', '' ) );
		$this->assertIsString( $service->run( 'wp_old_plugin', 'drop', 'WP_OLD_PLUGIN' ) );
		$this->assertTrue( $service->run( 'wp_old_plugin', 'drop', 'wp_old_plugin' ) );
	}

	public function testUnknownTableOrActionIsRefused() {
		$service = $this->tables( [ [ 'name' => 'wp_options', 'kind' => 'core', 'removable' => false ] ] );
		$this->assertIsString( $service->run( 'wp_options`; DROP TABLE wp_posts; --', 'optimize' ) );
		$this->assertIsString( $service->run( 'wp_options', 'truncate_everything' ) );
	}

	public function testCoreCronEventsCannotBeDeleted() {
		WP_Mock::userFunction( '_get_cron_array' )->andReturn( [ 100 => [ 'wp_version_check' => [ 'sig' => [ 'args' => [] ] ] ] ] );
		WP_Mock::userFunction( 'wp_unschedule_event' )->never();
		WP_Mock::userFunction( 'wp_unschedule_hook' )->never();

		$cron = new CronService( $this->createMock( ActivityService::class ) );
		$this->assertFalse( $cron->delete( 'wp_version_check', 100, 'sig' ) );
		$this->assertFalse( $cron->delete( 'wp_version_check', 100, 'sig', true ) );
	}

	public function testAnEventThatIsNotScheduledCanNeitherRunNorBeDeleted() {
		WP_Mock::userFunction( '_get_cron_array' )->andReturn( [ 100 => [ 'real_hook' => [ 'sig' => [ 'args' => [] ] ] ] ] );
		WP_Mock::userFunction( 'wp_unschedule_event' )->never();

		$cron = new CronService( $this->createMock( ActivityService::class ) );
		// A hook name supplied by the request is never executed unless that exact event is stored.
		$this->assertFalse( $cron->run( 'wp_delete_user', 100, 'sig' ) );
		$this->assertFalse( $cron->run( 'real_hook', 999, 'sig' ) );
		$this->assertFalse( $cron->delete( 'real_hook', 100, 'other-sig' ) );
	}

	public function testCoreNetworkOptionsAreProtected() {
		$network = new NetworkService();
		foreach ( [ 'site_admins', 'active_sitewide_plugins', 'siteurl', 'main_site', 'auth_salt' ] as $key ) {
			$this->assertTrue( $network->is_protected( $key ), $key );
		}
		$this->assertFalse( $network->is_protected( '_site_transient_update_plugins' ) );
		$this->assertFalse( $network->is_protected( 'some_plugin_network_setting' ) );
	}

	public function testMonthlyRecurrenceIsRegisteredAndEveryFrequencyIsKnown() {
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		$schedules = CleanupService::cron_schedules( [] );
		$this->assertSame( 30 * 86400, $schedules['nhrotm_monthly']['interval'] );
		$this->assertContains( 'nhrotm_monthly', CleanupService::FREQUENCIES );
	}
}
