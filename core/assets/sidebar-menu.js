(function () {
    'use strict';

    var toggle = document.querySelector('[data-sidebar-toggle]');
    if (!toggle) return;

    var role = toggle.getAttribute('data-sidebar-toggle') || 'portal';
    var sidebar = document.getElementById(toggle.getAttribute('aria-controls'));
    var backdrop = document.querySelector('[data-sidebar-backdrop]');
    var icon = toggle.querySelector('[data-sidebar-icon]');
    var mobileQuery = window.matchMedia('(max-width: 800px)');
    var storageKey = 'ptc-sidebar-collapsed-' + role;
    var desktopCollapsed = false;

    if (!sidebar) return;

    try {
        desktopCollapsed = window.localStorage.getItem(storageKey) === 'true';
    } catch (error) {
        desktopCollapsed = false;
    }

    function isMobile() {
        return mobileQuery.matches;
    }

    function setToggleState() {
        var open = isMobile()
            ? document.body.classList.contains('sidebar-open')
            : !desktopCollapsed;
        var label = isMobile()
            ? (open ? 'Close navigation' : 'Open navigation')
            : (open ? 'Collapse navigation' : 'Expand navigation');

        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        if (icon) icon.textContent = isMobile() && open ? 'close' : 'menu';
    }

    function applyState() {
        if (isMobile()) {
            document.body.classList.remove('sidebar-collapsed');
            if (backdrop) {
                backdrop.hidden = !document.body.classList.contains('sidebar-open');
            }
        } else {
            document.body.classList.remove('sidebar-open');
            document.body.classList.toggle('sidebar-collapsed', desktopCollapsed);
            document.body.style.overflow = '';
            if (backdrop) backdrop.hidden = true;
        }

        setToggleState();
    }

    function closeMobileMenu(restoreFocus) {
        document.body.classList.remove('sidebar-open');
        document.body.style.overflow = '';
        if (backdrop) backdrop.hidden = true;
        setToggleState();
        if (restoreFocus) toggle.focus();
    }

    toggle.addEventListener('click', function () {
        if (isMobile()) {
            var shouldOpen = !document.body.classList.contains('sidebar-open');
            document.body.classList.toggle('sidebar-open', shouldOpen);
            document.body.style.overflow = shouldOpen ? 'hidden' : '';
            if (backdrop) backdrop.hidden = !shouldOpen;
            setToggleState();
            if (shouldOpen) {
                var firstLink = sidebar.querySelector('a.nav-item');
                if (firstLink) firstLink.focus();
            }
            return;
        }

        desktopCollapsed = !desktopCollapsed;
        try {
            window.localStorage.setItem(storageKey, desktopCollapsed ? 'true' : 'false');
        } catch (error) {
            // Keep the control functional when storage is unavailable.
        }
        applyState();
    });

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            closeMobileMenu(true);
        });
    }

    sidebar.addEventListener('click', function (event) {
        if (!isMobile() || !document.body.classList.contains('sidebar-open')) return;
        var link = event.target.closest('a.nav-item');
        if (!link || link.classList.contains('dropdown-toggle')) return;
        closeMobileMenu(true);
    });

    document.addEventListener('keydown', function (event) {
        if (!isMobile() || !document.body.classList.contains('sidebar-open')) return;

        if (event.key === 'Escape') {
            closeMobileMenu(true);
            return;
        }

        if (event.key === 'Tab') {
            var focusable = Array.prototype.slice.call(sidebar.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'))
                .filter(function (element) {
                    return element.getClientRects().length > 0;
                });
            if (!focusable.length) return;

            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    if (mobileQuery.addEventListener) {
        mobileQuery.addEventListener('change', applyState);
    } else {
        mobileQuery.addListener(applyState);
    }

    applyState();
})();