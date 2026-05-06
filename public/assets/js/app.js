document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form[data-confirm]');
    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || '¿Confirmas esta acción?';
            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });
});
