<!-- Templates search box — pass $search_type (sms | whatsapp | email | ai_call_agent) -->
<div class="ccx-search-box">
    <i class="fa fa-search"></i>
    <input type="text" class="templates-search-input" data-type="<?= $search_type; ?>"
        id="<?= $search_type; ?>-templates-search" placeholder="Search templates…" autocomplete="off">
    <a href="#" class="ccx-search-clear templates-search-clear" data-type="<?= $search_type; ?>" title="Clear"
        style="display:none;"><i class="fa fa-times-circle"></i></a>
</div>
