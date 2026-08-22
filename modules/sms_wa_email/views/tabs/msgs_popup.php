<div role="tabpanel" class="tab-pane" id="msgs_popup">
    <div class="row">
        <div class="col-md-12">
            <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-text-gray-800 tw-mb-4">
                Company Message Limits
            </h4>
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="msgs-popup-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>SMS (Limit / Sent)</th>
                            <th>Official WA (Limit / Sent)</th>
                            <th>Email (Limit / Sent)</th>
                            <th>AI Call (Limit / Sent)</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="msgs-popup-tbody">
                        <tr>
                            <td colspan="7" class="text-center">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Limits Modal -->
<div class="modal fade" id="editLimitsModal" tabindex="-1" role="dialog" aria-labelledby="editLimitsModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="editLimitsModalLabel">Manage Message Limits</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_limit_client_id" value="">

                <h5 class="tw-font-bold tw-text-lg tw-mb-4" id="edit_limit_company_name"></h5>

                <div class="row">
                    <!-- SMS -->
                    <div class="col-md-4 tw-mb-4">
                        <label class="tw-font-medium text-muted">SMS</label>
                        <div class="input-group">
                            <span class="input-group-addon" style="width: 60px;">Limit</span>
                            <input type="number" id="edit_sms_limit" class="form-control" value="0">
                        </div>
                        <div class="input-group tw-mt-2">
                            <span class="input-group-addon" style="width: 60px;">Sent</span>
                            <input type="number" id="edit_sms_count" class="form-control" value="0">
                        </div>
                    </div>

                    <!-- Official WA -->
                    <div class="col-md-4 tw-mb-4">
                        <label class="tw-font-medium text-muted">Official WA</label>
                        <div class="input-group">
                            <span class="input-group-addon" style="width: 60px;">Limit</span>
                            <input type="number" id="edit_wa_limit" class="form-control" value="0">
                        </div>
                        <div class="input-group tw-mt-2">
                            <span class="input-group-addon" style="width: 60px;">Sent</span>
                            <input type="number" id="edit_wa_count" class="form-control" value="0">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="col-md-4 tw-mb-4">
                        <label class="tw-font-medium text-muted">Email</label>
                        <div class="input-group">
                            <span class="input-group-addon" style="width: 60px;">Limit</span>
                            <input type="number" id="edit_email_limit" class="form-control" value="0">
                        </div>
                        <div class="input-group tw-mt-2">
                            <span class="input-group-addon" style="width: 60px;">Sent</span>
                            <input type="number" id="edit_email_count" class="form-control" value="0">
                        </div>
                    </div>

                    <!-- AI Call -->
                    <div class="col-md-4 tw-mb-4">
                        <label class="tw-font-medium text-muted">AI Call</label>
                        <div class="input-group">
                            <span class="input-group-addon" style="width: 60px;">Limit</span>
                            <input type="number" id="edit_ai_call_limit" class="form-control" value="0">
                        </div>
                        <div class="input-group tw-mt-2">
                            <span class="input-group-addon" style="width: 60px;">Sent</span>
                            <input type="number" id="edit_ai_call_count" class="form-control" value="0">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="save_limits_btn" data-loading-text="Saving...">Save
                    Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        var companiesData = [];
        var dtInstance = null;

        function getProgressBar(limit, sent) {
            limit = parseInt(limit) || 0;
            sent = parseInt(sent) || 0;

            if (limit === 0) return \'<span class="label label-default tw-text-xs">No Limit Set</span>\';

            var percentage = Math.min(100, Math.round((sent / limit) * 100));
            var colorClass = \'progress-bar-success\';

            if (percentage >= 90) colorClass = \'progress-bar-danger\';
            else if (percentage >= 70) colorClass = \'progress-bar-warning\';

            return `
                <div class="tw-flex tw-justify-between tw-text-xs tw-mb-1">
                    <span>${sent} / ${limit}</span>
                    <span class="tw-font-bold">${percentage}%</span>
                </div>
                <div class="progress tw-mb-0" style="height: 6px; border-radius: 4px;">
                    <div class="progress-bar ${colorClass}" role="progressbar" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100" style="width: ${percentage}%;">
                    </div>
                </div>
            `;
        }

        function fetchCompanyLimits() {
            $.get(admin_url + 'sms_wa_email/get_company_limits', function (response) {
                companiesData = JSON.parse(response);
                renderCompanyLimits();
            });
        }

        function renderCompanyLimits() {
            if (dtInstance) {
                dtInstance.destroy();
            }

            var html = '';
            $.each(companiesData, function (index, c) {
                html += `
                    <tr>
                        <td class="tw-font-medium">${$('<div>').text(c.company).html()}</td>
                        <td>${getProgressBar(c.sms_limit, c.sms_count)}</td>
                        <td>${getProgressBar(c.wa_limit, c.wa_count)}</td>
                        <td>${getProgressBar(c.email_limit, c.email_count)}</td>
                        <td>${getProgressBar(c.ai_call_limit, c.ai_call_count)}</td>
                        <td class="text-center" style="vertical-align: middle;">
                            <a href="#" class="btn btn-default btn-icon edit-limit-btn" data-index="${index}" title="Edit Limits">
                                <i class="fa fa-pencil"></i>
                            </a>
                        </td>
                    </tr>
                `;
            });
            $('#msgs-popup-tbody').html(html);

            dtInstance = $('#msgs-popup-table').DataTable({
                "pageLength": 25,
                "bLengthChange": true,
                "responsive": true,
                "order": [[0, "asc"]],
                "language": {
                    "emptyTable": "No companies found in database"
                }
            });
        }

        $('#msgs_popup').on('click', '.edit-limit-btn', function (e) {
            e.preventDefault();
            var index = $(this).data('index');
            var c = companiesData[index];

            $('#edit_limit_client_id').val(c.client_id);
            $('#edit_limit_company_name').text(c.company);

            $('#edit_sms_limit').val(c.sms_limit);
            $('#edit_sms_count').val(c.sms_count);

            $('#edit_wa_limit').val(c.wa_limit);
            $('#edit_wa_count').val(c.wa_count);

            $('#edit_email_limit').val(c.email_limit);
            $('#edit_email_count').val(c.email_count);

            $('#edit_ai_call_limit').val(c.ai_call_limit);
            $('#edit_ai_call_count').val(c.ai_call_count);

            $('#editLimitsModal').modal('show');
        });

        $('#save_limits_btn').on('click', function () {
            var btn = $(this);
            btn.button('loading');

            var data = {
                client_id: $('#edit_limit_client_id').val(),
                sms_limit: $('#edit_sms_limit').val(),
                sms_count: $('#edit_sms_count').val(),
                wa_limit: $('#edit_wa_limit').val(),
                wa_count: $('#edit_wa_count').val(),
                email_limit: $('#edit_email_limit').val(),
                email_count: $('#edit_email_count').val(),
                ai_call_limit: $('#edit_ai_call_limit').val(),
                ai_call_count: $('#edit_ai_call_count').val()
            };

            $.post(admin_url + 'sms_wa_email/save_company_limits', data).done(function (response) {
                fetchCompanyLimits();
                $('#editLimitsModal').modal('hide');
                alert_float('success', 'Limits updated successfully');
            }).always(function () {
                btn.button('reset');
            });
        });

        // Initial fetch on tab show
        /* 
        We use the shown.bs.tab event to render DataTables when it becomes visible,
        otherwise the columns might misalign if initialized while hidden.
        */
        var fetchedOnce = false;
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr("href");
            if (target === '#msgs_popup' && !fetchedOnce) {
                fetchedOnce = true;
                if (dtInstance) { dtInstance.destroy(); }
                fetchCompanyLimits();
            }
        });

        // Handling window resize if needed
        $(window).on('resize', function () {
            if (dtInstance && $('#msgs_popup').hasClass('active')) {
                dtInstance.columns.adjust().responsive.recalc();
            }
        });
    });
</script>