import intlTelInput from 'intl-tel-input/intlTelInputWithUtils';
import spanish from 'intl-tel-input/locale/es';
import 'intl-tel-input/styles';
import '../css/vita-phone.css';

const selector = 'input[type="tel"],input[name="phone"],input[name$="_phone"],input[name="reply_whatsapp"],input[data-vita-phone]';
const instances = new Map();
const descriptions = new Map();
const message = 'Ingresa un número válido para el país seleccionado.';
const nativeValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
let queued = false;

export function internationalValue(value) {
    const text = String(value || '').trim();
    if (/^00[\d\s().-]+$/.test(text)) return '+' + text.slice(2);
    // Existing international values were sometimes stored without the plus sign.
    if (/^[\d\s().-]+$/.test(text) && text.replace(/\D/g, '').length > 9) return '+' + text;
    return text;
}

function isValid(state) {
    const value = state.display.value.trim();
    return !value || (/^[0-9]+$/.test(value) && state.widget.isValidNumberPrecise() === true);
}

function showStatus(state, validate = false) {
    const { input, display, widget, status } = state;
    const country = widget.getSelectedCountry();
    const value = display.value.trim();
    const invalid = validate && !!value && !isValid(state);
    input.setCustomValidity(invalid ? message : '');
    display.setCustomValidity(invalid ? message : '');
    if (invalid) input.setAttribute('aria-invalid', 'true');
    else input.removeAttribute('aria-invalid');
    if (invalid) display.setAttribute('aria-invalid', 'true');
    else display.removeAttribute('aria-invalid');
    status.classList.toggle('vita-phone-status-error', invalid);
    status.textContent = invalid ? message : (state.notice || (country ? `${country.name} · Solo números, sin prefijo +${country.dialCode}.` : 'Selecciona el país de tu teléfono.'));
}

function removeRepeatedPrefix(state) {
    const country = state.widget.getSelectedCountry();
    const value = state.display.value;
    if (!country || !/^[0-9]+$/.test(value) || !value.startsWith(country.dialCode)) return;
    if (state.widget.isValidNumberPrecise() !== true
        && intlTelInput.utils.isValidNumberPrecise('+' + value, country.iso2, null)) {
        state.widget.setNumber('+' + value);
        state.notice = `El prefijo +${country.dialCode} ya está seleccionado. Lo quitamos del número.`;
    }
}

function sync(state) {
    if (state.display.disabled !== state.input.disabled) state.display.disabled = state.input.disabled;
    if (state.display.readOnly !== state.input.readOnly) state.display.readOnly = state.input.readOnly;
    if (state.display.required !== state.input.required) state.display.required = state.input.required;
    if (state.lastValue === state.input.value) return;
    state.widget.setNumber(internationalValue(state.input.value));
    state.lastValue = state.input.value;
    showStatus(state);
}

function publishNumber(state) {
    const value = state.display.value.trim();
    const number = value ? (state.widget.getNumber() || value) : '';
    state.lastValue = number;
    if (state.input.value !== number) {
        state.input.value = number;
        state.input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function normalize(state) {
    sync(state);
    publishNumber(state);
    state.lastValue = state.input.value;
    showStatus(state, true);
}

function mount(input) {
    if (instances.has(input) || input.closest('.iti')) return;
    const labels = Array.from(input.labels || []);
    const display = document.createElement('input');
    display.type = 'tel';
    display.autocomplete = 'tel';
    display.inputMode = 'numeric';
    display.pattern = '[0-9]*';
    display.maxLength = 40;
    display.className = input.className;
    display.required = input.required;
    display.disabled = input.disabled;
    display.readOnly = input.readOnly;
    for (const attribute of ['aria-label','aria-describedby','aria-labelledby']) {
        if (input.hasAttribute(attribute)) display.setAttribute(attribute, input.getAttribute(attribute));
    }
    if (input.id) {
        display.id = input.id + '-national';
        labels.forEach(label => { label.htmlFor = display.id; });
    }
    input.value = internationalValue(input.value);
    display.value = input.value;
    input.before(display);
    const widget = intlTelInput(display, {
        initialCountry: 'cl', countryNameLocale: 'es', uiTranslations: spanish,
        countryOrder: ['cl','ar','pe','bo','co','ve','br','ec','uy','py','mx','es','us','ca'],
        numberDisplayFormat: intlTelInput.NUMBER_FORMAT.E164,
        separateDialCode: true,
        formatAsYouType: false,
        allowedNumberTypes: null, strictMode: false, allowPhonewords: false,
        allowNumberExtensions: false, placeholderNumberPolicy: intlTelInput.PLACEHOLDER_POLICY.AGGRESSIVE,
        countrySelectorMode: intlTelInput.COUNTRY_SELECTOR_MODE.DROPDOWN,
        dropdownParent: document.body, matchDropdownWidth: true,
        containerClass: 'vita-phone-control',
    });
    const status = document.createElement('span');
    status.className = 'vita-phone-status';
    status.id = `vita-phone-status-${widget.id}`;
    display.setAttribute('aria-describedby', [display.getAttribute('aria-describedby'),status.id].filter(Boolean).join(' '));
    const wrapper = display.closest('.iti');
    input.type = 'hidden';
    wrapper.append(input);
    wrapper.after(status);
    const state = { input, display, widget, status, lastValue: input.value, notice: '' };
    instances.set(input, state);
    // Alpine, jQuery .val(), autofill and edit modals also update the country flag.
    Object.defineProperty(input, 'value', {
        configurable: true,
        get() { return nativeValue.get.call(this); },
        set(value) { nativeValue.set.call(this, value); schedule(); },
    });
    display.addEventListener('beforeinput', event => {
        if (!event.data || /^[0-9]+$/.test(event.data)) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        // A complete number from autofill can be accepted without displaying its prefix.
        if (/^(?:\+|00)[0-9\s().-]{6,}$/.test(event.data)) {
            state.notice = 'El prefijo ya se muestra junto a la bandera. Lo quitamos del número.';
            widget.setNumber(internationalValue(event.data));
            publishNumber(state);
        } else {
            state.notice = 'Escribe solo números. El país seleccionado agrega el prefijo automáticamente.';
        }
        showStatus(state);
    }, true);
    display.addEventListener('paste', event => {
        event.preventDefault();
        event.stopImmediatePropagation();
        const pasted = event.clipboardData?.getData('text').trim() || '';
        if (!/^\+?[0-9\s().-]+$/.test(pasted)) {
            state.notice = 'Escribe solo números, sin letras ni prefijo.';
            showStatus(state);
            return;
        }
        if (/^(?:\+|00)/.test(pasted)) {
            state.notice = 'El prefijo ya se muestra junto a la bandera. Lo quitamos del número.';
            widget.setNumber(internationalValue(pasted));
        } else {
            display.setRangeText(pasted.replace(/\D/g, ''), display.selectionStart, display.selectionEnd, 'end');
            removeRepeatedPrefix(state);
        }
        publishNumber(state);
        showStatus(state);
    }, true);
    display.addEventListener('input', event => {
        if (!(event instanceof CustomEvent)) state.notice = '';
        display.value = display.value.replace(/\D/g, '');
        removeRepeatedPrefix(state);
        publishNumber(state);
        showStatus(state);
    });
    input.addEventListener('input', () => sync(state));
    display.addEventListener('countrychange', () => showStatus(state));
    display.addEventListener('blur', () => normalize(state));
    display.addEventListener('focus', () => sync(state));
    input.focus = (...args) => display.focus(...args);
    input.select = () => display.select();
    widget.promise.then(() => { sync(state); publishNumber(state); showStatus(state); });
    showStatus(state);
}

function scan() {
    queued = false;
    document.querySelectorAll(selector).forEach(mount);
    document.querySelectorAll('a[href^="tel:"],a[href^="https://wa.me/"],[data-phone-display]').forEach(decorateDisplay);
    for (const [input, state] of instances) {
        if (!input.isConnected) {
            state.widget.destroy(); state.status.remove(); instances.delete(input); delete input.value; delete input.focus; delete input.select;
        } else sync(state);
    }
}

function decorateDisplay(element) {
    const href = element.getAttribute('href') || '';
    const value = href.startsWith('tel:') ? href.slice(4) : href.startsWith('https://wa.me/')
        ? '+' + href.slice(14).split('?')[0] : element.textContent.trim();
    const existing = element.querySelector('.vita-phone-display-flag');
    if (existing && element.dataset.vitaPhoneDisplayValue === value) return;
    existing?.remove();
    element.dataset.vitaPhoneDisplayValue = value;
    if (!value) return;
    let country = descriptions.get(value);
    if (country === undefined) {
        const holder = document.createElement('div'), probe = document.createElement('input');
        holder.append(probe); probe.value = internationalValue(value);
        const widget = intlTelInput(probe, { initialCountry:'cl', countryNameLocale:'es',
            countrySelectorMode:intlTelInput.COUNTRY_SELECTOR_MODE.OFF, allowedNumberTypes:null, strictMode:false });
        country = widget.isValidNumberPrecise() ? widget.getSelectedCountry() : null;
        descriptions.set(value, country); widget.destroy();
    }
    if (!country) return;
    const flag = document.createElement('span');
    flag.className = `iti__flag iti__${country.iso2} vita-phone-display-flag`;
    flag.setAttribute('role','img'); flag.setAttribute('aria-label',country.name); flag.title = country.name;
    element.prepend(flag);
}

function schedule() {
    if (!queued) { queued = true; queueMicrotask(scan); }
}

function start() {
    scan();
    new MutationObserver(schedule).observe(document.body, { childList:true, subtree:true, attributes:true, attributeFilter:['type','name','data-vita-phone','value','disabled','readonly','required'] });
    document.addEventListener('submit', event => {
        let firstInvalid = null;
        for (const state of instances.values()) {
            if (state.input.form !== event.target || state.input.disabled) continue;
            normalize(state);
            if (!isValid(state) && !firstInvalid) firstInvalid = state.display;
        }
        if (firstInvalid) { event.preventDefault(); event.stopImmediatePropagation(); firstInvalid.reportValidity(); }
    }, true);
    document.addEventListener('reset', () => setTimeout(() => {
        for (const state of instances.values()) { state.lastValue = null; if (!state.input.value.trim()) state.widget.setSelectedCountry('cl'); sync(state); }
    }, 0), true);
}

window.VitaPhone = {
    refresh: schedule,
    setNumber(input, value) { if (typeof input === 'string') input = document.querySelector(input); if (!input) return; input.value = value || ''; if (instances.has(input)) sync(instances.get(input)); },
    getNumber(input) { if (typeof input === 'string') input = document.querySelector(input); const state = instances.get(input); if (!state) return input?.value || ''; sync(state); return isValid(state) ? state.widget.getNumber() : input.value; },
    validate(input) { const state = instances.get(input); if (!state) return true; normalize(state); return isValid(state); },
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
