<?php

namespace Nhrotm\OptionsTableManager;

/**
 * Assets handler class
 */
class Assets {

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
            'nhrotm-options-table-manager-admin-script' => [
                'src'     => NHROTM_ASSETS . '/js/admin.js',
                'version' => filemtime( NHROTM_PATH . '/assets/js/admin.js' ),
                'deps'    => [ 'jquery' ]
            ],
            'nhrotm-options-table-manager-datatable-script' => [
                'src'     => NHROTM_ASSETS . '/js/dataTables.min.js',
                'version' => '2.0.3',
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
            'nhrotm-options-table-manager-admin-style' => [
                'src'     => NHROTM_ASSETS . '/css/admin.out.css',
                'version' => filemtime( NHROTM_PATH . '/assets/css/admin.out.css' )
            ],
            'nhrotm-options-table-manager-datatable-style' => [
                'src'     => NHROTM_ASSETS . '/css/dataTables.dataTables.min.css',
                'version' => '2.0.3'
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

        wp_localize_script( 'nhrotm-options-table-manager-admin-script', 'nhrotmOptionsTableManager', [
            'nonce' => wp_create_nonce( 'nhrotm-options-table-manager-admin-nonce' ),
            'confirm' => __( 'Are you sure?', 'nhrotm-options-table-manager' ),
            'error' => __( 'Something went wrong', 'nhrotm-options-table-manager' ),
        ] );
    }
}
