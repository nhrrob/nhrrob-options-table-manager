<?php
namespace Nhrotm\OptionsTableManager\Tests;

use Nhrotm\OptionsTableManager\Managers\HistoryManager;
use Nhrotm\OptionsTableManager\Services\ActivityService;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * History rows carry a record_type so only option rows are ever restored.
 */
class HistoryRestoreTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	public function testOnlyOptionEditsAndDeletesAreRestorable() {
		$this->assertTrue( HistoryManager::is_restorable( [ 'action' => 'update', 'record_type' => 'options' ] ) );
		$this->assertTrue( HistoryManager::is_restorable( [ 'action' => 'delete', 'record_type' => 'options' ] ) );
		$this->assertTrue( HistoryManager::is_restorable( [ 'action' => 'restore_backup', 'record_type' => 'options' ] ) );

		// Meta edits share the update action but must never reach update_option().
		$this->assertFalse( HistoryManager::is_restorable( [ 'action' => 'update', 'record_type' => 'postmeta' ] ) );
		$this->assertFalse( HistoryManager::is_restorable( [ 'action' => 'update', 'record_type' => 'unknown' ] ) );
		// A create has no previous value to put back.
		$this->assertFalse( HistoryManager::is_restorable( [ 'action' => 'create', 'record_type' => 'options' ] ) );
		$this->assertFalse( HistoryManager::is_restorable( [ 'action' => 'disable_autoload', 'record_type' => 'options' ] ) );
		$this->assertFalse( HistoryManager::is_restorable( [ 'action' => 'update' ] ) );
	}

	/**
	 * @dataProvider recordTypeProvider
	 */
	public function testRecordDerivesTypeFromAction( $action, $explicit, $expected ) {
		$history = $this->createMock( HistoryManager::class );
		$history->expects( $this->once() )
			->method( 'log_change' )
			->with( 'subject', '', $action, $expected );

		( new ActivityService( $history ) )->record( $action, 'subject', '', $explicit );
	}

	public function recordTypeProvider() {
		return [
			'option update'   => [ 'update', null, 'options' ],
			'meta update'     => [ 'update', 'usermeta', 'usermeta' ],
			'meta delete'     => [ 'delete_termmeta', null, 'termmeta' ],
			'transient'       => [ 'update_transient', null, 'transients' ],
			'snapshot event'  => [ 'snapshot', null, 'event' ],
			'unknown add-on'  => [ 'nhrotmp_custom', null, 'options' ],
		];
	}
}
