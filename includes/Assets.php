<?php

namespace Nhrotm\OptionsTableManager;

/**
 * Assets handler class
 */
class Assets extends App {
    /**
     * Class constructor
     */
    function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ] );
    }

    /**
     * All available scripts
     *
     * @return array
     */
    public function get_scripts() {
        return [
            'nhrotm-admin-script' => [
                'src'     => NHROTM_ASSETS . '/js/admin.js',
                'version' => filemtime( NHROTM_PATH . '/assets/js/admin.js' ),
                'deps'    => [ 'jquery' ]
            ],
            'nhrotm-datatable-script' => [
                'src'     => NHROTM_ASSETS . '/js/dataTables.min.js',
                'version' => '2.1.8',
                'deps'    => [ 'jquery' ]
            ],
        ];
    }

    /**
     * All available styles
     *
     * @return array
     */
    public function get_styles() {
        return [
            // 'nhrotm-admin-style' => [
            //     'src'     => NHROTM_ASSETS . '/css/admin.out.css',
            //     'version' => filemtime( NHROTM_PATH . '/assets/css/admin.out.css' )
            // ],
            'nhrotm-admin-style' => [
                'src'     => NHROTM_ASSETS . '/css/admin.css',
                'version' => filemtime( NHROTM_PATH . '/assets/css/admin.css' )
            ],
            'nhrotm-datatable-style' => [
                'src'     => NHROTM_ASSETS . '/css/dataTables.dataTables.min.css',
                'version' => '2.1.8'
            ],
        ];
    }

    /**
     * Register scripts and styles
     *
     * @return void
     */
    public function register_assets() {
        $scripts = $this->get_scripts();
        $styles  = $this->get_styles();

        foreach ( $scripts as $handle => $script ) {
            $deps = isset( $script['deps'] ) ? $script['deps'] : false;

            wp_register_script( $handle, $script['src'], $deps, $script['version'], true );
        }

        foreach ( $styles as $handle => $style ) {
            $deps = isset( $style['deps'] ) ? $style['deps'] : false;

            wp_register_style( $handle, $style['src'], $deps, $style['version'] );
        }

        wp_localize_script( 'nhrotm-admin-script', 'nhrotmOptionsTableManager', [
            'nonce' => wp_create_nonce( 'nhrotm-admin-nonce' ),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'confirm' => __( 'Are you sure?', 'nhrrob-options-table-manager' ),
            'error' => __( 'Something went wrong', 'nhrrob-options-table-manager' ),
            'protected_options' => $this->get_protected_options(),
            'protected_usermetas' => $this->get_protected_usermetas()
        ] );
    }
}
