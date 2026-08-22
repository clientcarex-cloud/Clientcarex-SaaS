<?php

namespace modules\banner\core;

class Apiinit
{
    public static function the_da_vinci_code($module_name)
    {
        // Always return true; no verification
        return true;
    }

    public static function ease_of_mind($module_name)
    {
        // No functional checks; stub remains
        return true;
    }

    public static function activate($module)
    {
        // Skip license prompt; do nothing
        return true;
    }

    public static function pre_validate($module_name, $code = '', $username = '')
    {
        // Bypass pre-validation, always success
        return ['status' => true];
    }
}
