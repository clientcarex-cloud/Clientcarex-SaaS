
function ccx_switch_view(view) {
    if (view === 'list') {
        $('#ccx_kanban_view').addClass('hide');
        $('#ccx_list_view').removeClass('hide');
        // Update ARIA on view buttons
        $('.btn-group[aria-label="View mode"] button[aria-label="List view"]').attr('aria-pressed', 'true');
        $('.btn-group[aria-label="View mode"] button[aria-label="Kanban view"]').attr('aria-pressed', 'false');
    } else {
        $('#ccx_list_view').addClass('hide');
        $('#ccx_kanban_view').removeClass('hide');
        // Update ARIA on view buttons
        $('.btn-group[aria-label="View mode"] button[aria-label="List view"]').attr('aria-pressed', 'false');
        $('.btn-group[aria-label="View mode"] button[aria-label="Kanban view"]').attr('aria-pressed', 'true');
    }
}


function ccx_lead_profile(id) {
    // Use a unique modal ID (#ccx-lead-modal) so the core Perfex CRM JS
    // (which owns #lead-modal) cannot intercept or replace our content.
    if ($('#ccx-lead-modal').length === 0) {
        $('body').append(
            '<div class="modal fade" id="ccx-lead-modal" tabindex="-1" role="dialog">' +
            '<div class="modal-dialog modal-lg" role="document">' +
            '<div class="modal-content"></div>' +
            '</div></div>'
        );
    } else {
        // Ensure LG sizing when reusing existing modal
        $('#ccx-lead-modal .modal-dialog').removeClass('modal-xl modal-xxl').addClass('modal-lg');
    }
    // Load module's own lead modal — completely independent from core Perfex CRM
    requestGet('ccx_leads/lead_modal/' + id).done(function (html) {
        $('#ccx-lead-modal .modal-content').html(html);
        $('#ccx-lead-modal').modal('show');
    }).fail(function (error) {
        alert_float('danger', error.responseText || 'Failed to load lead data.');
    });
}

function ccx_close_slideover() {
    $('#ccx_lead_slideover').removeClass('open');
    setTimeout(function () {
        $('#ccx_slideover_body').html('');
    }, 300);
}

function ccx_leads_new_lead() {
    // Use module's own independent new lead form (not core init_lead)
    if ($('#ccx-lead-modal').length === 0) {
        $('body').append(
            '<div class="modal fade" id="ccx-lead-modal" tabindex="-1" role="dialog">' +
            '<div class="modal-dialog modal-xxl" role="document">' +
            '<div class="modal-content"></div>' +
            '</div></div>'
        );
    } else {
        // Ensure XXL sizing when reusing existing modal
        $('#ccx-lead-modal .modal-dialog').removeClass('modal-lg modal-xl').addClass('modal-xxl');
    }

    requestGet('ccx_leads/new_lead').done(function (html) {
        $('#ccx-lead-modal .modal-content').html(html);
        $('#ccx-lead-modal').modal('show');

        // Initialize Perfex UI components
        init_selectpicker();
        init_datepicker();
        init_color_pickers();

        // Bind the form submission handler
        var $form = $('#ccx_new_lead_form');
        $form.off('submit').on('submit', function (e) {
            e.preventDefault();
            var $btn = $form.find('.ccx-new-lead-save-btn');
            $btn.addClass('disabled').attr('disabled', true);

            $.post($form.attr('action'), $form.serialize())
                .done(function (response) {
                    response = JSON.parse(response);
                    if (response.success) {
                        alert_float('success', response.message);
                        $('#ccx-lead-modal').modal('hide');

                        // Reload DataTable if visible
                        if (typeof ccx_leads_table !== 'undefined' && $.fn.DataTable.isDataTable('.table-ccx-leads')) {
                            ccx_leads_table.ajax.reload(null, false);
                        }
                    } else {
                        alert_float('danger', response.message || 'Failed to create lead.');
                    }
                    $btn.removeClass('disabled').attr('disabled', false);
                })
                .fail(function (error) {
                    alert_float('danger', error.responseText || 'An error occurred.');
                    $btn.removeClass('disabled').attr('disabled', false);
                });
            return false;
        });
    }).fail(function (error) {
        alert_float('danger', error.responseText || 'Failed to load new lead form.');
    });
}

function delete_lead(id) {
    if (confirm_delete()) {
        requestGetJSON('leads/delete/' + id).done(function (response) {
            if ($.fn.DataTable.isDataTable('.table-ccx-leads')) {
                ccx_leads_table.ajax.reload(null, false);
            } else {
                window.location.reload();
            }
            ccx_close_slideover();
        }).fail(function () {
            window.location.reload();
        });
    }
}

$(function () {
    // Kanban card click - open lead in the core Perfex CRM lead modal
    $('body').on('click', '.kanban-card', function () {
        var id = $(this).data('lead-id');
        if (id) {
            ccx_lead_profile(id);
        }
    });

    // Custom edit toggle for CCX lead modal (replaces core [lead-edit] handler)
    $('body').on('click', '[ccx-lead-edit]', function (e) {
        e.preventDefault();
        var $modal = $('#ccx-lead-modal');
        var $leadEdit = $modal.find('.lead-edit');
        $modal.find('.lead-view').toggleClass('hide');
        $leadEdit.toggleClass('hide');

        // Initialize UI components when entering edit mode
        if (!$leadEdit.hasClass('hide')) {
            init_selectpicker();
            init_datepicker();
            init_tags_inputs();
            init_color_pickers();
            validate_lead_form();

            var $address = $modal.find('#address');
            if ($address.length > 0 && $address.is('textarea')) {
                var scrollHeight = $address[0].scrollHeight;
                $address.height(0).height(scrollHeight - 15);
                $address.css('padding-top', '9px');
            }
        }
    });

    // Auto-populate Company field from Name field and hide Company field
    $('body').on('shown.bs.modal', '#ccx-lead-modal', function (e) {
        // Check if we are in the CCX Leads module context
        if ($('.table-ccx-leads').length > 0 || $('#ccx_leads_view_wrapper').length > 0) {
            var $companyInput = $('#ccx-lead-modal input[name="company"]');
            var $nameInput = $('#ccx-lead-modal input[name="name"]');

            if ($companyInput.length > 0) {
                // Hide the company input wrapper (usually .form-group)
                $companyInput.closest('.form-group').addClass('hide');

                // If creating new lead (company might be empty), sync it with name
                if ($nameInput.length > 0) {
                    // Initial sync if name already has value
                    if ($nameInput.val() && !$companyInput.val()) {
                        $companyInput.val($nameInput.val());
                    }

                    // Sync on change
                    $nameInput.on('input blur change', function () {
                        $companyInput.val($(this).val());
                    });
                }
            }
        }

        // Initialize Perfex UI components for the loaded modal content
        init_selectpicker();
        init_datepicker();
        init_tags_inputs();
        init_color_pickers();

    });

    // Keyboard support for status filter (Enter / Space)
    $('body').on('keydown', '.ccx-status-filter-item', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });
});
