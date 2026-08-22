<br />

<?php if (!empty($credentials)) : ?>
<p class="text-center"><?= $note; ?></p>
<?php endif; ?>

<?php if (!empty($credentials)) : ?>
<div class="table-responsive" style="padding: 0;">
    <table class="table">
        <?php foreach ($credentials as $key => $value) : ?>
        <tr style="cursor:pointer;" data-id="<?= $key; ?>" data-role="<?= $value['type']; ?>" class="credential-row"
            data-email="<?= $value['email']; ?>" data-password="<?= $value['password']; ?>">
            <td><?= $value['title']; ?></td>
            <td><?= $value['email']; ?></td>
            <td><?= $value['password']; ?></td>
            <td><a href="javascript:;" class="copy-icon"><i class="">📋</i></button></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>


<?php if (!empty($credentials)) : ?>
<script>
"use strict";
document.addEventListener("DOMContentLoaded", function() {

    let storageKey = 'demo-default-cred-id';
    let baseUrl = '<?= base_url(); ?>';

    // Function to populate input fields by type
    function populateInputs(row, auto = false) {

        if (!row) return;

        const email = row.getAttribute('data-email');
        const password = row.getAttribute('data-password');
        const role = row.getAttribute('data-role');
        const rowId = row.getAttribute('data-id');
        const isAdminView = window.location.pathname.includes('admin');
        const redirectEndpoint = isAdminView ? 'login' : 'admin';

        const shouldRedirect = (role != 'client' && !isAdminView) || (role == 'client' && isAdminView);
        if (!auto && shouldRedirect) {
            window.localStorage.setItem(storageKey, rowId);
            window.location = baseUrl + redirectEndpoint;
            return;
        }

        // Get the input fields by type and set their values
        const emailInput = document.querySelector('#email') ?? document.querySelector('input[type="email"]');
        emailInput.value = email;
        document.querySelector('input[type="password"]').value = password;
    }

    // Get all the rows that contain credentials
    const rows = document.querySelectorAll('.credential-row');

    // Add event listeners to each row
    rows.forEach(row => {
        // When a row is clicked
        row.addEventListener('click', function() {
            // Populate the input fields with the email and password
            populateInputs(row);
        });
    });

    // Add event listener to the copy icon in each row (in case the icon is clicked instead)
    const copyIcons = document.querySelectorAll('.copy-icon');

    copyIcons.forEach(icon => {
        icon.addEventListener('click', function(event) {
            // Prevent the row click event from firing when the icon is clicked
            event.stopPropagation();

            // Get the parent row of the clicked icon
            const row = icon.closest('tr');
            populateInputs(row);
        });
    });

    let autoCredId = window.localStorage.getItem(storageKey);
    if (autoCredId) {
        window.localStorage.removeItem(storageKey)
        populateInputs(document.querySelector(`tr[data-id=${autoCredId}]`), true);
    }
});
</script>
<?php endif; ?>