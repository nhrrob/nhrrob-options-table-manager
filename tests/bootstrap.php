<?php
/**
 * PHPUnit bootstrap.
 *
 * Runs under the CLI test runner, never through WordPress — so it must not
 * guard on ABSPATH. An ABSPATH guard here exits before the autoloader loads,
 * which silently aborts the whole suite with a passing exit code.
 *
 * @package Nhrotm\OptionsTableManager
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

WP_Mock::bootstrap();

/**
 * Minimal $wpdb stub.
 *
 * Managers read `$wpdb->prefix` and the table properties in their constructors,
 * so a global $wpdb must exist before any manager is instantiated. Tests that
 * exercise queries mock the individual methods they need.
 */
class Nhrotm_Test_WPDB {

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * Options table name.
	 *
	 * @var string
	 */
	public $options = 'wp_options';

	/**
	 * Usermeta table name.
	 *
	 * @var string
	 */
	public $usermeta = 'wp_usermeta';

	/**
	 * Postmeta table name.
	 *
	 * @var string
	 */
	public $postmeta = 'wp_postmeta';

	/**
	 * Commentmeta table name.
	 *
	 * @var string
	 */
	public $commentmeta = 'wp_commentmeta';

	/**
	 * Termmeta table name.
	 *
	 * @var string
	 */
	public $termmeta = 'wp_termmeta';

	/**
	 * Users table name.
	 *
	 * @var string
	 */
	public $users = 'wp_users';

	/**
	 * Posts table name.
	 *
	 * @var string
	 */
	public $posts = 'wp_posts';

	/**
	 * Escape a LIKE operand.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	/**
	 * Swallow any other wpdb call a unit test doesn't explicitly mock.
	 *
	 * @param string $name Method name.
	 * @param array  $args Arguments.
	 * @return null
	 */
	public function __call( $name, $args ) {
		return null;
	}
}

$GLOBALS['wpdb'] = new Nhrotm_Test_WPDB();
