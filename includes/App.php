<?php
namespace Nhrotm\OptionsTableManager;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Traits\GlobalTrait;

/**
 * Controller Class
 */
class App
{

    use GlobalTrait;

    protected $page_slug;

    public function __construct()
    {
        $this->page_slug = 'nhrotm-options-table-manager';
    }
}