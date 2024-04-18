<?php

namespace Nhrrob\NhrrobOptionsTableManager\Admin;

use Nhrrob\NhrrobOptionsTableManager\Traits\GlobalTrait;

/**
 * The Menu handler class
 */
class SettingsPage extends Page
{
    use GlobalTrait;

    /**
     * Initialize the class
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Handles the settings page
     *
     * @return void
     */
    public function view()
    {
        ob_start();
		include NHRROB_OPTIONS_TABLE_MANAGER_VIEWS_PATH . '/admin/settings/index.php';
        $content = ob_get_clean();
        echo wp_kses( $content, $this->allowed_html() );
    }
}