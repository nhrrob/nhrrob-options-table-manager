<?php
namespace Nhrotm\OptionsTableManager\Tests;

use Nhrotm\OptionsTableManager\Traits\GlobalTrait;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Role/capability user meta must be protected for every site in a network
 * and for any table prefix, not just the literal wp_ keys.
 */
class ProtectedUsermetaTest extends TestCase {

	private $subject;

	protected function setUp(): void {
		WP_Mock::setUp();
		$GLOBALS['wpdb']->base_prefix = 'xyz_';
		$GLOBALS['wpdb']->blogid      = 3;
		$this->subject                = new class() {
			use GlobalTrait;
		};
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		$GLOBALS['wpdb']->base_prefix = 'wp_';
		$GLOBALS['wpdb']->blogid      = 1;
	}

	public function testProtectsEverySitesRoleKeysForAnyPrefix() {
		foreach ( [ 'xyz_capabilities', 'xyz_user_level', 'xyz_2_capabilities', 'xyz_17_user_level', 'xyz_5_user-settings' ] as $key ) {
			$this->assertTrue( $this->subject->is_protected_usermeta( $key ), $key );
		}
	}

	public function testLeavesOtherKeysEditable() {
		foreach ( [ 'my_plugin_capabilities_cache', 'xyz_2_something', 'wp_capabilities_backup', 'billing_phone' ] as $key ) {
			$this->assertFalse( $this->subject->is_protected_usermeta( $key ), $key );
		}
	}
}
