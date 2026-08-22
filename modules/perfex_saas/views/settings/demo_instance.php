<?php

defined('BASEPATH') or exit('No direct script access allowed');
?>
<div class="tw-flex tw-flex-col">
    <div class="row">
        <div class="col-md-8 form-group">
            <label for="w-full">
                <?php
                $key = 'perfex_saas_demo_instance';
                $reset_key = $key . '_reset_hour';
                $reset = get_option($reset_key);
                ?>
                <?= perfex_saas_input_label_with_hint($key, $key . '_hint'); ?>
            </label>
            <?php
            $demo_tenant_slugs = get_option($key);
            $demo_tenant_slugs = empty($demo_tenant_slugs) ? [] : json_decode($demo_tenant_slugs);
            ?>
            <?php $CI->load->view(PERFEX_SAAS_MODULE_NAME . '/includes/tenant_select', ['name' => 'settings[' . $key . '][]', 'value' => $demo_tenant_slugs, 'multiple' => 'multiple', 'id' => $key]); ?>
        </div>
        <div class="col-md-4 form-group">
            <label class="invisible"><?= _l($reset_key); ?></label>
            <div class="input-group" data-toggle="tooltip" data-title="<?= _l($reset_key); ?>">
                <input min="0" step="0.1" type="number" name="settings[<?= $reset_key; ?>]"
                    value="<?= empty($reset) ? '24' : $reset; ?>" class="form-control"
                    placeholder="<?= _l($reset_key); ?>" />
                <span class="input-group-addon tw-px-2 tw-border-l-0">
                    hours
                </span>
            </div>
        </div>
    </div>

    <div class="tw-mt-4 tw-mb-4">
        <hr />
    </div>

    <?php render_yes_no_option('perfex_saas_demo_seeding_clone_mode', _l('perfex_saas_demo_seeding_clone_mode'), _l('perfex_saas_demo_seeding_clone_mode_hint')); ?>

    <div class="tw-mt-4 tw-mb-4">
        <hr />
    </div>
    <h4>
        <?= _l('perfex_saas_demo_instance_login_credentials'); ?>
    </h4>
    <p>
        <?= _l('perfex_saas_demo_instance_login_credentials_hint'); ?>
    </p>

    <?php
    $CI = &get_instance();
    $key = 'perfex_saas_demo_instance_credentials';
    $default_cred_id  = 'serv' . time();
    $default_service = [$default_cred_id => ['title' => '', 'email' => '', 'password' => '', 'type' => 'admin']];
    $demo_instance_credentials = array_merge($default_service, (array)json_decode(get_option($key) ?? '', true));
    ?>
    <div class="tw-mt-4 tw-mb-4">

        <div class="tw-flex tw-justify-end tw-mb-2">
            <button type="button" id="add-demo-instance-credential"
                class="btn btn-primary pull-right"><?= _l('add_new'); ?> <i class="fa fa-plus"></i></button>

        </div>
        <div class="tw-mt-4 tw-flex tw-flex-col tw-gap-6 demo-instance-credentials">
            <!-- placehodler input to allow full clearing/removal of credentials -->
            <input type="hidden" name="settings[<?= $key; ?>][]" />
            <?php
            foreach ($demo_instance_credentials as $cred_id => $value) : if (empty($value)) continue; ?>
            <?php if ($cred_id === $default_cred_id) echo "<template id='demo-instance-credential-template' data-id='$cred_id'>"; ?>
            <div class="demo-instance-credential" data-id="<?= $cred_id; ?>">
                <div class="tw-mb-4 tw-flex tw-flex-wrap tw-items-start tw-justify-between">
                    <label class="col-sm-4">
                        <span><?= $value['title']; ?></span>
                        <button type="button" onclick="removeDemoInstanceCredentialRow(this);"
                            class="btn btn-danger btn-xs pull-right delete"><i class="fa fa-trash"></i></button>
                    </label>
                    <div class="col-sm-8">
                        <input data-toggle="tooltip" data-title="<?= _l('perfex_saas_title'); ?>"
                            name="settings[<?= $key; ?>][<?= $cred_id; ?>][title]" value="<?= $value['title']; ?>"
                            class="form-control" placeholder="<?= _l('perfex_saas_title'); ?>" required />
                        <input data-toggle="tooltip" data-title="<?= _l('perfex_saas_email'); ?>"
                            name="settings[<?= $key; ?>][<?= $cred_id; ?>][email]" value="<?= $value['email']; ?>"
                            type="email" class="form-control" placeholder="<?= _l('perfex_saas_email'); ?>" required />
                        <input data-toggle="tooltip" data-title="<?= _l('perfex_saas_password'); ?>"
                            name="settings[<?= $key; ?>][<?= $cred_id; ?>][password]" value="<?= $value['password']; ?>"
                            class="form-control" placeholder="<?= _l('perfex_saas_password'); ?>" type="password"
                            required />
                        <select class="form-control tw-border-neutral-300 tw-border tw-rounded-md"
                            name="settings[<?= $key; ?>][<?= $cred_id; ?>][type]" required>
                            <option value="admin" <?= $value['type'] == 'admin' ? 'selected' : ''; ?>>
                                <?= _l('perfex_saas_admin'); ?></option>
                            <option value="staff" <?= $value['type'] == 'staff' ? 'selected' : ''; ?>>
                                <?= _l('staff'); ?></option>
                            <option value="client" <?= $value['type'] == 'client' ? 'selected' : ''; ?>>
                                <?= _l('client'); ?></option>
                        </select>
                    </div>
                </div>
                <hr class="col-xs-12" />
            </div>
            <?php if ($cred_id === $default_cred_id) echo '</template>'; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    $key = 'perfex_saas_demo_instance_credentials_note';
    echo render_textarea('settings[' . $key . ']', perfex_saas_input_label_with_hint($key, $key . '_hint'), get_option($key));
    ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function getRndInteger(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }
    $("#add-demo-instance-credential").on('click', function() {
        let template = $("template#demo-instance-credential-template").clone();

        let newId = 'cred' + (Math.floor(Date.now() / getRndInteger(10, 100)) + getRndInteger(10,
            1000));
        let id = template.attr('data-id');

        if (id && !$("[data-id='" + newId + "']").length && $(
                ".demo-instance-credentials .demo-instance-credential").length < 50) {
            template = template.html();
            template = template.replaceAll(`][${id}]`, `][${newId}]`);
            template = $(template);
            template.attr('data-id', newId);
            template.find("label span").text("");
            template.find("input,select").val("");

            $(".demo-instance-credentials").append(template);
        }
    });
});

function removeDemoInstanceCredentialRow(obj) {
    if (confirm(appLang['confirm_action_prompt'])) {
        $(obj).closest('.demo-instance-credential').remove();
    }
}
</script>