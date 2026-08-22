<?php
if (isset($member)) {
    $is_admin = is_admin($member->staffid);
}

$tm_permissions = get_available_staff_permissions($funcData);

// Avatar tint per feature — derived from the slug so a newly registered
// permission gets a stable colour without anyone maintaining a map.
$tm_palette = [
    ['#eef2ff', '#4338ca'],
    ['#fef2f2', '#b91c1c'],
    ['#f0fdf4', '#15803d'],
    ['#fffbeb', '#b45309'],
    ['#f5f3ff', '#6d28d9'],
    ['#ecfeff', '#0e7490'],
    ['#fdf2f8', '#be185d'],
    ['#f0f9ff', '#0369a1'],
];

// Capabilities flagged not_applicable can never be granted, so they are left
// out of every counter — the same rule the accordion's JS applies.
$tm_countable  = [];
$tm_total_caps = 0;
foreach ($tm_permissions as $feature => $permission) {
    $tm_countable[$feature] = 0;
    foreach ($permission['capabilities'] as $name) {
        if (is_array($name) && ! empty($name['not_applicable'])) {
            continue;
        }
        $tm_countable[$feature]++;
    }
    $tm_total_caps += $tm_countable[$feature];
}
?>
<div class="tm-perm">
    <div class="tm-perm-toolbar">
        <div class="tm-perm-search">
            <i class="fa fa-search"></i>
            <input type="text" class="tm-perm-search-input" placeholder="Search module or capability…"
                autocomplete="off">
            <a href="#" class="tm-perm-search-clear" title="Clear" style="display:none;"><i
                    class="fa fa-times-circle"></i></a>
        </div>

        <label class="tm-perm-filter">
            <input type="checkbox" class="tm-perm-granted-only">
            <span>Only granted</span>
        </label>

        <button type="button" class="tm-perm-toggle-all" data-state="collapsed">
            <i class="fa fa-angle-double-down"></i> <span>Expand all</span>
        </button>

        <span class="tm-perm-summary">
            <b class="tm-perm-granted-total">0</b> of <?= (int) $tm_total_caps; ?> granted
        </span>
    </div>

    <?php // Kept as table.roles with data-name on both rows — core's
          // init_roles_permissions() walks exactly that structure. ?>
    <table class="table roles no-margin tm-perm-table">
        <?php $tm_i = 0;
        foreach ($tm_permissions as $feature => $permission) {
            $tm_tint = $tm_palette[$tm_i++ % count($tm_palette)];

            // Search haystack: module name, slug and every capability label
            $tm_haystack = $permission['name'] . ' ' . $feature;
            foreach ($permission['capabilities'] as $capability => $name) {
                $tm_haystack .= ' ' . $capability . ' ' . (! is_array($name) ? $name : $name['name']);
            }
            ?>
        <tbody class="tm-perm-group" data-search="<?= e(mb_strtolower($tm_haystack)); ?>">
            <tr class="tm-perm-head-row" data-name="<?= e($feature); ?>">
                <td colspan="2">
                    <div class="tm-perm-head">
                        <span class="tm-perm-caret"><i class="fa fa-chevron-right"></i></span>
                        <span class="tm-perm-avatar"
                            style="background:<?= $tm_tint[0]; ?>; color:<?= $tm_tint[1]; ?>;"><?= e(mb_strtoupper(mb_substr($permission['name'], 0, 1))); ?></span>
                        <span class="tm-perm-title">
                            <b><?= e($permission['name']); ?></b>
                            <small><?= e($feature); ?></small>
                        </span>
                        <span class="tm-perm-meta">
                            <span class="tm-perm-count"><b>0</b> / <?= (int) $tm_countable[$feature]; ?></span>
                            <a href="#" class="tm-perm-selectall">Select all</a>
                        </span>
                    </div>
                </td>
            </tr>
            <tr class="tm-perm-body-row" data-name="<?= e($feature); ?>">
                <td colspan="2">
                    <div class="tm-perm-caps">
                        <?php if (isset($permission['before'])) {
                            echo '<div class="tm-perm-extra">' . $permission['before'] . '</div>';
                        } ?>

                        <div class="tm-perm-cap-grid">
                            <?php foreach ($permission['capabilities'] as $capability => $name) {
                                $checked  = '';
                                $disabled = '';
                                if ((isset($is_admin) && $is_admin)
                           || (is_array($name) && isset($name['not_applicable']) && $name['not_applicable'])
                           || (
                               ($capability == 'view_own' || $capability == 'view'
                                  && array_key_exists('view_own', $permission['capabilities']) && array_key_exists('view', $permission['capabilities']))
                                && (
                                    (isset($member)
                                 && staff_can(($capability == 'view' ? 'view_own' : 'view'), $feature, $member->staffid))
                                || (isset($role)
                                 && has_role_permission($role->roleid, ($capability == 'view' ? 'view_own' : 'view'), $feature))
                                )
                           )
                                ) {
                                    $disabled = ' disabled ';
                                } elseif ((isset($member) && staff_can($capability, $feature, $member->staffid))
                            || isset($role) && has_role_permission($role->roleid, $capability, $feature)) {
                                    $checked = ' checked ';
                                } ?>
                            <div class="checkbox tm-perm-cap">
                                <input
                                    <?php if ($capability == 'view') { ?>
                                data-can-view <?php } ?>
                                <?php if ($capability == 'view_own') { ?>
                                data-can-view-own <?php } ?>
                                <?php if (is_array($name) && isset($name['not_applicable']) && $name['not_applicable']) { ?>
                                data-not-applicable="true" <?php } ?>
                                type="checkbox"
                                <?= e($checked); ?>
                                class="capability"
                                id="<?= $feature . '_' . $capability; ?>"
                                name="permissions[<?= e($feature); ?>][]"
                                value="<?= e($capability); ?>"
                                <?= e($disabled); ?>>
                                <label for="<?= $feature . '_' . $capability; ?>">
                                    <?= ! is_array($name) ? $name : $name['name']; ?>
                                </label>
                                <?php
                                if (isset($permission['help']) && array_key_exists($capability, $permission['help'])) {
                                    echo '<i class="fa-regular fa-circle-question" data-toggle="tooltip" data-title="' . e($permission['help'][$capability]) . '"></i>';
                                } ?>
                            </div>
                            <?php } ?>
                        </div>

                        <?php if (isset($permission['after'])) {
                            echo '<div class="tm-perm-extra">' . $permission['after'] . '</div>';
                        } ?>
                    </div>
                </td>
            </tr>
        </tbody>
        <?php } ?>
    </table>

    <div class="tm-perm-empty" style="display:none;">
        <i class="fa fa-search"></i>
        <span>No module matches your search.</span>
    </div>
</div>

<script>
    // This view renders before init_tail(), so jQuery is not loaded yet — wait
    // for it rather than throwing on `$`.
    (function () {
        function boot() {
            if (typeof window.jQuery === 'undefined') {
                return setTimeout(boot, 30);
            }
            window.jQuery(function ($) { tmPermInit($); });
        }

        function tmPermInit($) {
            var $root = $('.tm-perm');
            if (!$root.length) {
                return;
            }

            var $groups = $root.find('.tm-perm-group');
            var $search = $root.find('.tm-perm-search-input');
            var $grantedOnly = $root.find('.tm-perm-granted-only');
            var $toggleAll = $root.find('.tm-perm-toggle-all');

            // "not applicable" capabilities can never be granted, so they are
            // excluded from every count and from Select all.
            function capsOf($group) {
                return $group.find('input.capability').not('[data-not-applicable="true"]');
            }

            function refreshCounts() {
                var grantedTotal = 0;

                $groups.each(function () {
                    var $group = $(this);
                    var $caps = capsOf($group);
                    var checked = $caps.filter(':checked').length;

                    grantedTotal += checked;
                    $group.find('.tm-perm-count b').text(checked);
                    $group.toggleClass('tm-granted', checked > 0);
                    $group.find('.tm-perm-selectall').text(
                        $caps.length > 0 && checked === $caps.length ? 'Clear all' : 'Select all'
                    );
                });

                $root.find('.tm-perm-granted-total').text(grantedTotal);
            }

            function syncToggleAllBtn() {
                var $visible = $groups.not('.tm-hidden');
                var allOpen = $visible.length > 0 && $visible.length === $visible.filter('.open').length;

                $toggleAll.attr('data-state', allOpen ? 'expanded' : 'collapsed');
                $toggleAll.find('span').text(allOpen ? 'Collapse all' : 'Expand all');
                $toggleAll.find('i').attr('class', allOpen ? 'fa fa-angle-double-up' : 'fa fa-angle-double-down');
            }

            // Search + "only granted" are applied together; a group that survives
            // a text search is auto-expanded so the match is visible.
            function applyFilter(autoExpand) {
                var q = $.trim(($search.val() || '').toLowerCase());
                var tokens = q ? q.split(/\s+/) : [];
                var grantedOnly = $grantedOnly.prop('checked');
                var shown = 0;

                $root.find('.tm-perm-search-clear').toggle(q.length > 0);

                $groups.each(function () {
                    var $group = $(this);
                    var haystack = $group.attr('data-search') || '';
                    var ok = true;

                    for (var i = 0; i < tokens.length; i++) {
                        if (haystack.indexOf(tokens[i]) === -1) {
                            ok = false;
                            break;
                        }
                    }
                    if (ok && grantedOnly && !$group.hasClass('tm-granted')) {
                        ok = false;
                    }

                    $group.toggleClass('tm-hidden', !ok);
                    if (ok) {
                        shown++;
                        if (autoExpand && tokens.length) {
                            $group.addClass('open');
                        }
                    } else {
                        $group.removeClass('open');
                    }
                });

                $root.find('.tm-perm-empty').toggle(shown === 0);
                syncToggleAllBtn();
            }

            $root.on('click', '.tm-perm-head', function (e) {
                if ($(e.target).closest('.tm-perm-selectall').length) {
                    return;
                }
                $(this).closest('.tm-perm-group').toggleClass('open');
                syncToggleAllBtn();
            });

            $root.on('click', '.tm-perm-selectall', function (e) {
                e.preventDefault();
                var $group = $(this).closest('.tm-perm-group');
                var $caps = capsOf($group).not(':disabled');
                var check = $caps.filter(':checked').length !== $caps.length;

                $caps.prop('checked', check).trigger('change');
                $group.addClass('open');
                refreshCounts();
            });

            $toggleAll.on('click', function () {
                var expand = $(this).attr('data-state') !== 'expanded';
                $groups.not('.tm-hidden').toggleClass('open', expand);
                syncToggleAllBtn();
            });

            $search.on('input', function () {
                applyFilter(true);
            }).on('keydown', function (e) {
                if (e.which === 27) {
                    $(this).val('');
                    applyFilter(false);
                }
            });

            $root.on('click', '.tm-perm-search-clear', function (e) {
                e.preventDefault();
                $search.val('').focus();
                applyFilter(false);
            });

            $grantedOnly.on('change', function () {
                applyFilter(false);
            });

            $root.on('change', 'input.capability', refreshCounts);

            // Picking a role repopulates the checkboxes over AJAX without firing
            // change on each one — recount once the request lands.
            $(document).ajaxComplete(function (e, xhr, settings) {
                if ((settings.url || '').indexOf('role_changed') !== -1) {
                    setTimeout(function () {
                        refreshCounts();
                        applyFilter(false);
                    }, 0);
                }
            });

            refreshCounts();
            applyFilter(false);
        }

        boot();
    })();
</script>
