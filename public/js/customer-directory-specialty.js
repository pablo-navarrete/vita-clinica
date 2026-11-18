(() => {
    const picker = document.querySelector('[data-specialty-picker]');
    if (!picker) return;
    const select = picker.querySelector('select');
    const input = picker.querySelector('input');
    const list = picker.querySelector('[role="listbox"]');
    const status = picker.querySelector('[role="status"]');
    const options = Array.from(select.options).filter(option => !option.hidden)
        .map(option => ({ value: option.value, label: option.textContent }));
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    let matches = [], active = -1;
    select.hidden = true;
    input.hidden = false;
    picker.querySelector('[data-specialty-label]').htmlFor = input.id;
    input.value = select.value ? select.selectedOptions[0].textContent : '';
    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };
    const choose = option => {
        select.value = option.value;
        input.value = option.value ? option.label : '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    };
    const highlight = () => {
        Array.from(list.children).forEach((item, index) => item.setAttribute('aria-selected', String(index === active)));
        if (active >= 0) {
            input.setAttribute('aria-activedescendant', list.children[active].id);
            list.children[active].scrollIntoView({ block: 'nearest' });
        }
    };
    const render = (all = false) => {
        matches = options.filter(option => all || normalize(option.label).includes(normalize(input.value)));
        active = -1;
        input.removeAttribute('aria-activedescendant');
        list.replaceChildren();
        matches.forEach((option, index) => {
            const item = document.createElement('li');
            item.id = 'directory-specialty-option-' + index;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', 'false');
            item.textContent = option.label;
            item.addEventListener('pointerdown', event => event.preventDefault());
            item.addEventListener('click', () => choose(option));
            list.append(item);
        });
        status.textContent = matches.length ? matches.length + ' opciones disponibles.' : 'No hay especialidades disponibles para ese texto.';
        list.hidden = !matches.length;
        input.setAttribute('aria-expanded', String(matches.length > 0));
    };
    input.addEventListener('focus', () => render(true));
    input.addEventListener('click', () => { if (list.hidden) render(true); });
    input.addEventListener('input', () => { select.value = ''; render(); });
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') { close(); return; }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) render(true);
            if (!matches.length) return;
            active = active < 0 ? (event.key === 'ArrowDown' ? 0 : matches.length - 1)
                : (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length;
            highlight();
        } else if (event.key === 'Enter' && !list.hidden) {
            event.preventDefault();
            if (active >= 0) choose(matches[active]);
            else if (matches.length === 1) choose(matches[0]);
        }
    });
    input.addEventListener('blur', () => {
        if (!select.value && input.value) {
            const exact = options.find(option => normalize(option.label) === normalize(input.value));
            if (exact) choose(exact);
            else input.value = '';
        }
        close();
    });
})();
