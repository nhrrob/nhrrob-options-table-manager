<?php

namespace Nhrrob\NhrrobOptionsTableManager;

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
            'nhrrob-options-table-manager-admin-script' => [
                'src'     => NHRROB_OPTIONS_TABLE_MANAGER_ASSETS . '/js/admin.js',
                'version' => filemtime( NHRROB_OPTIONS_TABLE_MANAGER_PATH . '/assets/js/admin.js' ),
                'deps'    => [ 'jquery' ]
            ],
            'nhrrob-options-table-manager-datatable-script' => [
                'src'     => '//cdn.datatables.net/2.0.3/js/dataTables.min.js',
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
            'nhrrob-options-table-manager-admin-style' => [
                'src'     => NHRROB_OPTIONS_TABLE_MANAGER_ASSETS . '/css/admin.out.css',
                'version' => filemtime( NHRROB_OPTIONS_TABLE_MANAGER_PATH . '/assets/css/admin.out.css' )
            ],
            'nhrrob-options-table-manager-datatable-style' => [
                'src'     => '//cdn.datatables.net/2.0.3/css/dataTables.dataTables.min.css',
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

        wp_localize_script( 'nhrrob-options-table-manager-admin-script', 'nhrrobOptionsTableManager', [
            'nonce' => wp_create_nonce( 'nhrrob-options-table-manager-admin-nonce' ),
            'confirm' => __( 'Are you sure?', 'nhrrob-options-table-manager' ),
            'error' => __( 'Something went wrong', 'nhrrob-options-table-manager' ),
        ] );
    }
}
