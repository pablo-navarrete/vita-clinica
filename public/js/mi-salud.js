'use strict';
// Security links have no remote scripts, pixels or analytics. Scrub before any user action.
const tokenInput = document.getElementById('security-token');
if (tokenInput) {
    const token = location.hash.slice(1);
    history.replaceState(null, '', location.pathname);
    if (token) tokenInput.value = /^[a-f0-9]{64}$/.test(token) ? token : '';
}
let portalContentEvents;
window.initializePortalContent = () => {
    portalContentEvents?.abort();
    portalContentEvents = new AbortController();
// Same dropdown/search interaction as the customer selector, without inline scripts.
document.querySelectorAll('[data-country-select],[data-search-select]').forEach(select => {
    const id = select.id, wrapper = document.createElement('div');
    wrapper.className = 'portal-country-select';
    select.before(wrapper); wrapper.append(select); select.hidden = true;
    select.removeAttribute('id');
    const countries = Array.from(select.options, option => ({ code: option.value, label: option.textContent.replace(/ \([A-Z]{2}\)$/, '') }));
    const trigger = document.createElement('button');
    trigger.type = 'button'; trigger.id = id; trigger.className = 'portal-country-trigger';
    trigger.dataset.countryTrigger = '';
    trigger.setAttribute('aria-haspopup', 'listbox'); trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', id + '-results');
    trigger.innerHTML = '<span></span><svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    const dropdown = document.createElement('div'); dropdown.className = 'portal-country-dropdown'; dropdown.hidden = true;
    const searchBox = document.createElement('div'); searchBox.className = 'portal-country-search';
    const search = document.createElement('input');
    search.type = 'search'; search.id = id + '-search'; search.placeholder = select.dataset.searchPlaceholder || 'Buscar pa\u00eds'; search.autocomplete = 'off';
    search.dataset.countrySearch = ''; search.setAttribute('role', 'combobox'); search.setAttribute('aria-autocomplete', 'list');
    search.setAttribute('aria-controls', id + '-results'); search.setAttribute('aria-expanded', 'false');
    const label = document.createElement('label'); label.htmlFor = search.id; label.className = 'portal-visually-hidden'; label.textContent = search.placeholder;
    const results = document.createElement('div'); results.className = 'portal-country-options'; results.id = id + '-results'; results.setAttribute('role', 'listbox'); results.setAttribute('aria-label', select.dataset.optionsLabel || 'Pa\u00edses emisores');
    const empty = document.createElement('p'); empty.className = 'portal-country-empty'; empty.setAttribute('role', 'status'); empty.textContent = select.dataset.emptyMessage || 'No se encontraron pa\u00edses.';
    searchBox.append(label, search); dropdown.append(searchBox, results, empty); wrapper.append(trigger, dropdown);
    const fold = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    let active = 0, options = [];
    const updateLabel = () => { trigger.querySelector('span').textContent = countries.find(c => c.code === select.value)?.label || 'Selecciona un pa\u00eds'; };
    const close = (focus = false) => {
        dropdown.hidden = true; trigger.setAttribute('aria-expanded', 'false'); search.setAttribute('aria-expanded', 'false');
        if (focus) trigger.focus();
    };
    const choose = code => { select.value = code; select.dispatchEvent(new Event('change', { bubbles: true })); updateLabel(); close(true); };
    const render = () => {
        options = countries.filter(c => fold(c.label + ' ' + c.code).includes(fold(search.value.trim())));
        active = Math.min(Math.max(0, active), Math.max(0, options.length - 1)); results.replaceChildren(); empty.hidden = options.length > 0;
        options.forEach((country, index) => {
            const option = document.createElement('button'); option.type = 'button'; option.tabIndex = -1;
            option.id = id + '-option-' + index; option.setAttribute('role', 'option'); option.setAttribute('aria-selected', String(select.value === country.code));
            option.classList.toggle('is-active', index === active); option.textContent = country.label;
            option.addEventListener('mousedown', event => event.preventDefault()); option.addEventListener('click', () => choose(country.code)); results.append(option);
        });
        if (options.length) search.setAttribute('aria-activedescendant', id + '-option-' + active);
        else search.removeAttribute('aria-activedescendant');
    };
    const open = () => { dropdown.hidden = false; trigger.setAttribute('aria-expanded', 'true'); search.setAttribute('aria-expanded', 'true'); search.value = ''; active = Math.max(0, countries.findIndex(c => c.code === select.value)); render(); search.focus(); results.children[active]?.scrollIntoView({ block: 'nearest' }); };
    trigger.addEventListener('click', () => dropdown.hidden ? open() : close());
    trigger.addEventListener('keydown', event => { if (['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); open(); } });
    search.addEventListener('input', () => { active = 0; render(); results.scrollTop = 0; });
    search.addEventListener('keydown', event => {
        if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault(); active += event.key === 'ArrowDown' ? 1 : -1; render(); results.children[active]?.scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter') { event.preventDefault(); if (options[active]) choose(options[active].code); }
    });
    wrapper.addEventListener('keydown', event => { if (event.key === 'Escape') { event.preventDefault(); close(true); } });
    wrapper.addEventListener('focusout', event => { if (!wrapper.contains(event.relatedTarget)) close(); });
    document.addEventListener('click', event => { if (!wrapper.contains(event.target)) close(); }, { signal: portalContentEvents.signal });
    updateLabel();
});
// Country-specific labels and allowed categories come from the central registry.
const documentTypes = document.querySelector('[data-document-types]');
const issuingCountry = document.querySelector('[name="identification_country"]');
if (documentTypes && issuingCountry) {
    const definitions = JSON.parse(documentTypes.dataset.documentTypes);
    const refreshDocumentTypes = () => {
        const selected = documentTypes.value;
        documentTypes.replaceChildren();
        (definitions[issuingCountry.value] || []).forEach(definition => {
            const option = document.createElement('option');
            option.value = definition.value; option.textContent = definition.label;
            documentTypes.append(option);
        });
        if (Array.from(documentTypes.options).some(option => option.value === selected)) documentTypes.value = selected;
    };
    issuingCountry.addEventListener('change', refreshDocumentTypes);
    refreshDocumentTypes();
}
// Controls are server-rendered so the eye remains visible before JavaScript loads.
document.querySelectorAll('.portal-password-toggle').forEach(toggle => {
    const input = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!input) return;
    toggle.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-label', visible ? 'Ocultar contrase\u00f1a' : 'Mostrar contrase\u00f1a');
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.querySelector('[data-password-eye-open]').toggleAttribute('hidden', visible);
        toggle.querySelector('[data-password-eye-closed]').toggleAttribute('hidden', !visible);
    });
});
};
window.initializePortalContent();
// Personal workspace navigation; independent of clinic roles and branch menus.
const portalSidebar = document.getElementById('portal-sidebar');
if (portalSidebar) {
    const toggles = document.querySelectorAll('[data-portal-menu-toggle]');
    const backdrop = document.querySelector('[data-portal-menu-close]');
    const mobile = matchMedia('(max-width: 767px)');
    const preferenceKey = portalSidebar.dataset.preferenceKey;
    try { document.body.classList.toggle('portal-workspace-compact', localStorage.getItem(preferenceKey) === 'true'); } catch (_) {}
    const preview = document.createElement('div');
    preview.className = 'portal-sidebar-preview'; preview.hidden = true;
    document.body.append(preview);
    let previewTimer;
    const hidePreview = () => { clearTimeout(previewTimer); preview.hidden = true; };
    const delayHidePreview = () => { clearTimeout(previewTimer); previewTimer = setTimeout(hidePreview, 180); };
    const keepPreview = () => clearTimeout(previewTimer);
    const showPreview = event => {
        const link = event.target.closest('nav a[href]');
        if (!link || mobile.matches || !document.body.classList.contains('portal-workspace-compact')) return;
        keepPreview();
        const item = document.createElement('a'); item.href = link.href;
        const icon = document.createElement('span'); icon.className = 'portal-preview-icon';
        icon.append(link.querySelector('.vita-menu-icon,svg').cloneNode(true));
        const label = document.createElement('span'); label.textContent = link.textContent.trim();
        item.append(icon, label); preview.replaceChildren(item);
        const rect = link.getBoundingClientRect();
        preview.style.top = Math.max(8, Math.min(rect.top + rect.height / 2 - 28, innerHeight - 72)) + 'px';
        preview.style.left = portalSidebar.getBoundingClientRect().right + 12 + 'px';
        preview.hidden = false;
    };
    portalSidebar.addEventListener('mouseover', showPreview);
    portalSidebar.addEventListener('focusin', showPreview);
    portalSidebar.addEventListener('mouseout', delayHidePreview);
    portalSidebar.addEventListener('focusout', delayHidePreview);
    portalSidebar.querySelector('nav').addEventListener('scroll', hidePreview);
    preview.addEventListener('mouseenter', keepPreview); preview.addEventListener('focusin', keepPreview);
    preview.addEventListener('mouseleave', delayHidePreview); preview.addEventListener('focusout', delayHidePreview);
    window.addEventListener('resize', hidePreview);
    const syncMenu = () => {
        hidePreview();
        const open = document.body.classList.contains('portal-workspace-menu-open');
        const compact = document.body.classList.contains('portal-workspace-compact');
        portalSidebar.dataset.compact = String(compact);
        toggles.forEach(toggle => {
            toggle.setAttribute('aria-expanded', String(mobile.matches ? open : !compact));
            toggle.setAttribute('aria-label', mobile.matches ? (open ? 'Cerrar menu' : 'Abrir menu') : (compact ? 'Expandir menu' : 'Compactar menu'));
        });
        backdrop.hidden = !mobile.matches || !open;
        portalSidebar.inert = mobile.matches && !open;
    };
    const closeMenu = (restoreFocus = false) => {
        document.body.classList.remove('portal-workspace-menu-open'); syncMenu();
        if (restoreFocus) document.querySelector('.portal-mobile-menu').focus();
    };
    toggles.forEach(toggle => toggle.addEventListener('click', () => {
        if (mobile.matches) {
            const opening = !document.body.classList.contains('portal-workspace-menu-open');
            document.body.classList.toggle('portal-workspace-menu-open'); syncMenu();
            if (opening) portalSidebar.querySelector('a').focus();
            else document.querySelector('.portal-mobile-menu').focus();
        } else {
            document.body.classList.toggle('portal-workspace-compact');
            try { localStorage.setItem(preferenceKey, String(document.body.classList.contains('portal-workspace-compact'))); } catch (_) {}
            syncMenu();
        }
    }));
    backdrop.addEventListener('click', () => closeMenu(true));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            hidePreview();
            if (document.body.classList.contains('portal-workspace-menu-open')) { event.preventDefault(); closeMenu(true); }
            document.querySelectorAll('.portal-account-menu[open]').forEach(menu => { menu.open = false; menu.querySelector('summary').focus(); });
        }
        if (event.key === 'Tab' && mobile.matches && document.body.classList.contains('portal-workspace-menu-open')) {
            const focusable = Array.from(portalSidebar.querySelectorAll('a,button')).filter(el => el.getClientRects().length);
            const first = focusable[0], last = focusable[focusable.length-1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    document.addEventListener('click', event => document.querySelectorAll('.portal-account-menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; }));
    mobile.addEventListener('change', () => closeMenu());
    portalSidebar.querySelectorAll('nav a').forEach(link => link.addEventListener('click', () => { if (mobile.matches) closeMenu(); }));
    document.querySelectorAll('.portal-account-menu').forEach(menu => {
        const summary = menu.querySelector('summary'), dropdown = menu.querySelector('.portal-account-dropdown');
        let closing = false;
        summary.addEventListener('click', event => {
            if (!menu.open) return;
            event.preventDefault();
            if (closing) return;
            closing = true;
            const animation = dropdown.animate([{opacity:1, transform:'scale(1)'},{opacity:0, transform:'scale(.95)'}], {duration:75,easing:'ease-in'});
            animation.onfinish = () => { menu.open = false; closing = false; };
        });
    });
    syncMenu();
}
