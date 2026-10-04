<?php
/**
 * Admin page host for the 2.0 React app.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Core\ModuleRegistry;

/**
 * Admin page host for the 2.0 React app.
 *
 * Registers a preview submenu, renders the mount node, and enqueues the
 * compiled bundle (admin/build). Runs alongside the 1.5.x UI — additive and
 * dev-preview only until 2.0 reaches parity and takes over the main menu.
 */
class AppPage {

	const SLUG = 'nhrotm-options-table-manager';

	/**
	 * Registry supplying the modules the app renders.
	 *
	 * @var ModuleRegistry
	 */
	private $registry;

	/**
	 * Hook suffix of the per-site page (Tools → Database Cleaner).
	 *
	 * @var string|false
	 */
	private $hook = false;

	/**
	 * Hook suffix of the Network Admin page (multisite only).
	 *
	 * @var string|false
	 */
	private $network_hook = false;

	/**
	 * Store the module registry backing the app.
	 *
	 * @param ModuleRegistry $registry Booted module registry.
	 */
	public function __construct( ModuleRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Hook the admin menu and asset registration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', [ $this, 'register_network_menu' ] );
		}
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Register the app screen under Tools (Tools → Database Cleaner).
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->hook = add_management_page(
			__( 'Database Cleaner', 'nhrrob-options-table-manager' ),
			__( 'Database Cleaner', 'nhrrob-options-table-manager' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Register the Network Admin page (multisite): Settings → Database Cleaner.
	 *
	 * @return void
	 */
	public function register_network_menu() {
		$this->network_hook = add_submenu_page(
			'settings.php',
			__( 'Database Cleaner', 'nhrrob-options-table-manager' ),
			__( 'Database Cleaner', 'nhrrob-options-table-manager' ),
			'manage_network_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Output the React app's mount node.
	 *
	 * @return void
	 */
	public function render() {
		// The heading + wp-header-end marker is what tells core's common.js to
		// relocate admin notices inside .wrap. Without it they render above the
		// wrap at a different indent than the app. The app draws its own title
		// bar, so the h1 is for screen readers and notice placement only.
		?>
		<div class="wrap nhrotm-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Database Cleaner', 'nhrrob-options-table-manager' ); ?></h1>
			<hr class="wp-header-end">
			<div id="nhrotm-app"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue the compiled app only on our page, only when a build exists.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		$is_network = $this->network_hook && $hook === $this->network_hook;
		if ( ! $is_network && ( ! $this->hook || $hook !== $this->hook ) ) {
			return;
		}

		$build_dir  = NHROTM_PATH . '/admin/build';
		$asset_file = $build_dir . '/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return; // Not built yet — run `npm run build`.
		}

		$asset     = require $asset_file;
		$build_url = NHROTM_URL . '/admin/build';

		wp_enqueue_script(
			'nhrotm-app',
			$build_url . '/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$style_path = $build_dir . '/style-index.css';
		if ( file_exists( $style_path ) ) {
			// Own version, not $asset['version'] — that hash is computed from
			// the JS entry only, so a CSS-only rebuild left it unchanged and a
			// browser's cached copy of the old stylesheet never busted.
			wp_enqueue_style(
				'nhrotm-app',
				$build_url . '/style-index.css',
				[],
				(string) filemtime( $style_path )
			);
		}

		$boot = [
			'restRoot'     => esc_url_raw( rest_url() ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'pluginName'   => __( 'Database Cleaner', 'nhrrob-options-table-manager' ),
			// The Network Admin page shows one screen: the network view.
			'modules'      => $is_network
				? [
					[
						'id'    => 'network',
						'label' => __( 'Network', 'nhrrob-options-table-manager' ),
					],
				]
				: $this->modules_payload(),
			'network'      => $is_network,
			// Multisite: user meta is network-wide, so only network user managers see it.
			'canUsermeta'  => \Nhrotm\OptionsTableManager\Services\BrowseService::can_manage_usermeta(),
			'maxSnapshots' => \Nhrotm\OptionsTableManager\Managers\BackupManager::MAX_SNAPSHOTS,
		];

		/**
		 * Filter the data the React app boots with (window.nhrotmApp).
		 *
		 * Lets an add-on attach its own boot data or reshape the nav modules
		 * (e.g. hide sections for a restricted user) without core edits.
		 *
		 * @param array $boot Localized boot payload.
		 */
		$boot = apply_filters( 'nhrotm_app_boot', $boot );

		wp_localize_script( 'nhrotm-app', 'nhrotmApp', $boot );

		/**
		 * Fires after the app bundle is enqueued on its screen.
		 *
		 * Add-ons enqueue their own bundle here with 'nhrotm-app' as a
		 * dependency, so window.nhrotm (screens/icons/components API) exists
		 * before they register, and they run before the app mounts.
		 */
		do_action( 'nhrotm_app_enqueued' );
	}

	/**
	 * Registry-driven nav payload — mirrors the PHP ModuleRegistry so the
	 * React nav and add-on modules stay in sync.
	 *
	 * @return array
	 */
	private function modules_payload() {
		$payload = [];
		foreach ( $this->registry->get_modules() as $module ) {
			$payload[] = [
				'id'    => $module->id(),
				'label' => $module->label(),
			];
		}
		return $payload;
	}
}
