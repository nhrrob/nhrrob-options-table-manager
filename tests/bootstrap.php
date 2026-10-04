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

// Time constants WordPress defines in wp-includes/default-constants.php.
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'DAY_IN_SECONDS', 86400 );
}

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
	 * Network-wide table prefix.
	 *
	 * @var string
	 */
	public $base_prefix = 'wp_';

	/**
	 * Current site id.
	 *
	 * @var int
	 */
	public $blogid = 1;

	/**
	 * Table prefix for the current site (wp_ on the main site, wp_2_ …).
	 *
	 * @return string
	 */
	public function get_blog_prefix() {
		return $this->base_prefix . ( $this->blogid > 1 ? $this->blogid . '_' : '' );
	}

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
