(() => {
    const toggle = document.querySelector('.menu-toggle');
    const navigation = document.getElementById('primary-navigation');

    if (toggle && navigation) {
        const mobile = window.matchMedia('(max-width: 1100px)');
        const setOpen = (open) => {
            toggle.setAttribute('aria-expanded', String(open));
            navigation.hidden = mobile.matches && !open;
        };

        setOpen(false);
        toggle.classList.add('is-ready');
        navigation.closest('.app-layout, .app-shell')?.classList.add('navigation-ready');
        toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                toggle.focus();
            }
        });
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.site-sidebar') && !event.target.closest('.mobile-header') && !event.target.closest('.app-shell')) setOpen(false);
        });
        document.addEventListener('focusin', (event) => {
            if (!event.target.closest('.site-sidebar') && !event.target.closest('.mobile-header') && !event.target.closest('.app-shell')) setOpen(false);
        });
        mobile.addEventListener('change', () => {
            const focusWasInMenu = navigation.contains(document.activeElement);
            setOpen(false);
            if (mobile.matches && focusWasInMenu) toggle.focus();
        });
    }

    // Associate the existing form labels with their adjacent fields.
    let fieldIndex = 0;
    document.querySelectorAll('label:not([for])').forEach((label) => {
        if (label.querySelector('input, select, textarea')) return;
        const control = label.nextElementSibling;
        if (!control?.matches('input:not([type="hidden"]), select, textarea')) return;
        if (!control.id) {
            let id;
            do { id = 'field-' + ++fieldIndex; } while (document.getElementById(id));
            control.id = id;
        }
        label.htmlFor = control.id;
    });

    document.querySelectorAll('.table-wrap').forEach((wrapper) => {
        wrapper.tabIndex = 0;
        wrapper.setAttribute('role', 'region');
        const title = wrapper.closest('.card')?.querySelector('h2');
        wrapper.setAttribute('aria-label', title?.textContent.trim() || 'Tabel data');
    });
})();
