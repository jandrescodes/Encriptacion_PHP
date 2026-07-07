document.addEventListener('click', function (event) {
    const revokeButton = event.target.closest('.js-revoke-session');
    if (!revokeButton) {
        return;
    }

    const id = revokeButton.getAttribute('data-session-id');
    const csrf = revokeButton.getAttribute('data-csrf');

    if (!id) {
        return;
    }

    Swal.fire({
        title: 'Revoke this session?',
        text: 'The device using this session will be logged out immediately.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, revoke',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'post';
        form.action = `${APP_URL}/sessions/revoke`;

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf';
        csrfInput.value = csrf;

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'session_id';
        idInput.value = id;

        const btnInput = document.createElement('input');
        btnInput.type = 'hidden';
        btnInput.name = 'btnrevoke';
        btnInput.value = '1';

        form.appendChild(csrfInput);
        form.appendChild(idInput);
        form.appendChild(btnInput);
        document.body.appendChild(form);
        form.submit();
    });
});
