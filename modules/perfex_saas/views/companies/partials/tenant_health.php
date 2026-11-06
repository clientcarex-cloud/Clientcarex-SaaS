<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!-- Tenant health & re-create (no-backup recovery) -->
<div class="panel_s" id="tenant_health_panel">
    <div class="panel-body">
        <div class="tw-flex tw-items-center tw-justify-between">
            <h4 class="tw-my-0 tw-font-semibold tw-text-base"><i class="fa fa-heartbeat"></i> Tenant health</h4>
            <button type="button" class="btn btn-default btn-xs" id="th_refresh"><i class="fa fa-refresh"></i> Re-check</button>
        </div>
        <div id="th_body" class="tw-mt-3"><p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Checking the tenant database…</p></div>
        <div id="th_actions" class="tw-mt-3" style="display:none">
            <hr class="tw-my-3">
            <p class="text-muted small tw-mb-2">
                <b>Re-create tenant</b> — only for a tenant whose data cannot be restored (no backup). Creates the database and MySQL user if missing,
                then installs a fresh, empty instance (tables, default settings, first administrator). It refuses to run if the database already
                contains this tenant's tables, so existing data is never touched. Protected by the same verification password as Delete.
            </p>
            <div class="form-inline">
                <select class="form-control input-sm" id="th_method"></select>
                <button type="button" class="btn btn-danger btn-sm" id="th_recreate"><i class="fa fa-repeat"></i> Re-create tenant</button>
            </div>
        </div>
        <div id="th_steps" class="tw-mt-2 small"></div>
    </div>
</div>
