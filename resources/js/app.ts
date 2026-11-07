import { createInertiaApp } from '@inertiajs/vue3';
import '../css/app.css';

const root = document.documentElement;
document.querySelectorAll<HTMLElement>('[data-theme-toggle]').forEach((button) => {
    button.setAttribute('aria-pressed', String(root.dataset.bsTheme === 'dark'));
});

const themeToggle = (button: HTMLElement) => {
    const current = root.dataset.bsTheme === 'dark' ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';

    root.dataset.bsTheme = next;
    button.setAttribute('aria-pressed', String(next === 'dark'));

    try {
        window.localStorage.setItem('affiliate-theme', next);
    } catch {
        // The current page still changes theme when storage is unavailable.
    }
};

document.addEventListener('click', (event) => {
    const target = event.target;

    const button = target instanceof Element ? target.closest<HTMLElement>('[data-theme-toggle]') : null;

    if (button) {
        themeToggle(button);
    }
});

if (document.querySelector('#app[data-page]')) {
    createInertiaApp();
}
