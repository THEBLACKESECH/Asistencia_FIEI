const sidebar = document.querySelector('[data-sidebar]');
const navToggle = document.querySelector('[data-nav-toggle]');
const flash = document.querySelector('[data-flash]');
const flashClose = document.querySelector('[data-close-flash]');
const alertModal = document.querySelector('[data-alert-modal]');
const modalClose = document.querySelector('[data-close-modal]');

if (navToggle && sidebar) {
    navToggle.addEventListener('click', () => {
        sidebar.classList.toggle('is-open');
    });
}

if (flash && flashClose) {
    flashClose.addEventListener('click', () => {
        flash.remove();
    });

    window.setTimeout(() => {
        flash.remove();
    }, 5000);
}

if (alertModal && modalClose) {
    modalClose.addEventListener('click', () => {
        alertModal.classList.remove('is-visible');
    });

    alertModal.addEventListener('click', (event) => {
        if (event.target === alertModal) {
            alertModal.classList.remove('is-visible');
        }
    });
}
