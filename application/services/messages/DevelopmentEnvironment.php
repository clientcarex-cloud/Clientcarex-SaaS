<?php

namespace app\services\messages;

defined('BASEPATH') or exit('No direct script access allowed');

use app\services\messages\AbstractMessage;

class DevelopmentEnvironment extends AbstractMessage
{
    protected $alertClass = 'warning';

    public function isVisible()
    {
        return ENVIRONMENT != 'production' && is_admin();
    }

    public function getMessage()
    {
        return '';
    }
}