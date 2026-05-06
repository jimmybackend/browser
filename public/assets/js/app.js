document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form[data-confirm]');

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || '¿Confirmas esta acción?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const searchInput = document.querySelector('#home-search');
    if (searchInput) {
        searchInput.focus({ preventScroll: true });
    }
});
