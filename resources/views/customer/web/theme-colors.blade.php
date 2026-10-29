<style>
    :root {
        --clinic-primary: {{ themeColor(600) }};
        --clinic-primary-hover: {{ themeColor(800) }};
        --clinic-primary-active: {{ themeColor(800) }};
        --clinic-primary-light: {{ themeColor(50) }};
        --clinic-primary-lighter: {{ themeColor(100) }};
        --primary: {{ themeColor(600) }};
    }

    html, body { max-width: 100%; overflow-x: clip; }

    @media (max-width: 991.98px) {
        .public-clinic-brand { min-width: 0; max-width: calc(100% - 3.75rem); gap: .5rem !important; white-space: normal; }
        .public-clinic-logo { width: auto !important; max-width: 3.5rem; max-height: 3.5rem !important; }
        .public-clinic-name { min-width: 0; overflow-wrap: anywhere; font-size: clamp(1.05rem, 4.8vw, 1.65rem); line-height: 1.15; }
        .public-clinic-brand + .navbar-toggler { flex: 0 0 auto; margin-left: auto; }
        .navbar-light .navbar-collapse .navbar-nav { align-items: stretch; }
        .navbar-light .navbar-collapse .navbar-nav .nav-link { padding: .65rem .5rem; }
        .website-topbar-contact { gap: .3rem .8rem; }
        .website-topbar-contact small { font-size: .75rem; overflow-wrap: anywhere; }
        .public-admin-bar > .d-flex { gap: .2rem .4rem !important; }
        .public-admin-bar .public-admin-link { max-width: 100%; white-space: normal; line-height: 1.25; }
        .public-admin-bar form { display: inline-flex !important; }
    }

    @media (max-width: 575.98px) {
        .public-admin-bar .public-admin-link { padding: .3rem .4rem !important; font-size: .72rem; }
        .navbar.navbar-light { padding: .75rem 1rem; }
        .website-topbar-contact { max-width: 100%; }
    }

    a,
    .text-primary,
    a.text-primary:hover,
    a.text-primary:focus,
    .navbar-light .navbar-nav .nav-link:hover,
    .navbar-light .navbar-nav .nav-link.active {
        color: var(--clinic-primary) !important;
    }

    html body .navbar .public-navigation-cta,
    html body .navbar .public-navigation-cta:hover,
    html body .navbar .public-navigation-cta:focus {
        color: #fff !important;
        text-decoration: none;
    }

    html body .navbar .public-navigation-cta {
        transition: background-color .2s ease, border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    html body .navbar .public-navigation-cta:hover,
    html body .navbar .public-navigation-cta:focus-visible {
        background-color: var(--clinic-primary-hover) !important;
        border-color: var(--clinic-primary-hover) !important;
        box-shadow: 0 8px 20px rgba(0, 0, 0, .28);
        color: #fff !important;
        transform: translateY(-3px) scale(1.03);
    }

    .navbar-light .navbar-nav .nav-link {
        position: relative;
        border-radius: 0;
        color: #111827 !important;
        background: transparent !important;
        box-shadow: none !important;
        transition: color .18s ease;
    }

    .navbar-light .navbar-collapse .navbar-nav {
        direction: ltr !important;
    }

    @media (min-width: 992px) {
        .navbar-light .navbar-collapse .navbar-nav {
            flex-direction: row !important;
            justify-content: flex-start;
        }
    }

    .navbar-light .navbar-nav .nav-link::after {
        position: absolute;
        right: 15px;
        bottom: 17px;
        left: 15px;
        height: 2px;
        background: {{ themeColor(600) }};
        content: '';
        transform: scaleX(0);
        transform-origin: left;
        transition: transform .18s ease;
    }

    .navbar-light .navbar-nav .nav-link:hover,
    .navbar-light .navbar-nav .nav-link.active {
        color: {{ themeColor(600) }} !important;
    }

    .navbar-light .navbar-nav .nav-link:hover::after,
    .navbar-light .navbar-nav .nav-link.active::after {
        transform: scaleX(1);
    }

    .navbar-light .navbar-nav .nav-link.active {
        font-weight: 700;
    }

    @media (max-width: 991.98px) {
        .navbar-light .navbar-nav .nav-link::after {
            bottom: 4px;
        }
    }

    .bg-primary,
    .btn-primary,
    .btn.btn-primary,
    .btn-primary.disabled,
    .btn-primary:disabled,
    .btn-primary:not(:disabled):not(.disabled):active,
    .show > .btn-primary.dropdown-toggle,
    .progress-bar,
    .custom-control-input:checked ~ .custom-control-label::before,
    .custom-checkbox .custom-control-input:indeterminate ~ .custom-control-label::before,
    .custom-radio .custom-control-input:indeterminate ~ .custom-control-label::before,
    .custom-range::-webkit-slider-thumb,
    .custom-range::-moz-range-thumb,
    .badge-primary,
    .page-item.active .page-link,
    .list-group-item.active,
    .nav-pills .nav-link.active,
    .nav-pills .show > .nav-link,
    .dropdown-item.active,
    .dropdown-item:active,
    .team .team-text,
    .testimonial-carousel .owl-dot.active {
        background-color: var(--clinic-primary) !important;
        border-color: var(--clinic-primary) !important;
    }

    html body .btn.btn-primary,
    html body .btn-primary,
    html body .btn-outline-primary {
        color: #fff !important;
        background-color: var(--clinic-primary) !important;
        border-color: var(--clinic-primary) !important;
    }

    .btn-outline-primary:hover,
    .btn-outline-primary:focus,
    .btn-outline-primary:active {
        color: #fff !important;
        background-color: var(--clinic-primary-hover) !important;
        border-color: var(--clinic-primary-hover) !important;
    }

    .btn-primary:focus,
    .btn-outline-primary:focus,
    .custom-control-input:focus ~ .custom-control-label::before,
    .custom-range:focus::-webkit-slider-thumb,
    .custom-range:focus::-moz-range-thumb {
        box-shadow: 0 0 0 .2rem {{ themeColor(200) }} !important;
    }

    .btn-link,
    .btn-link:hover,
    .btn-link:focus,
    .page-link:hover {
        color: var(--clinic-primary-hover) !important;
    }

    .border-primary,
    .testimonial-carousel .owl-dot {
        border-color: var(--clinic-primary) !important;
    }

    html body .btn.btn-primary:hover,
    html body .btn.btn-primary:focus,
    html body .btn.btn-primary:active,
    html body .btn-primary.disabled:hover,
    html body .btn-primary:disabled:hover,
    html body .btn-primary:not(:disabled):not(.disabled):active,
    html body .btn-primary:not(:disabled):not(.disabled).active,
    html body .show > .btn-primary.dropdown-toggle,
    html body a.bg-primary:hover,
    html body a.bg-primary:focus,
    html body button.bg-primary:hover,
    html body button.bg-primary:focus,
    html body .btn.btn-outline-primary:hover,
    html body .btn.btn-outline-primary:focus,
    html body .btn.btn-outline-primary:active,
    html body .btn.btn-outline-light:hover,
    html body .btn.btn-outline-light:focus,
    html body .btn.btn-outline-light:active {
        background-color: var(--clinic-primary-hover) !important;
        border-color: var(--clinic-primary-hover) !important;
    }

    .btn-primary:not(:disabled):not(.disabled):active:focus {
        box-shadow: 0 0 0 .2rem {{ themeColor(200) }} !important;
    }

    .btn-outline-primary.disabled,
    .btn-outline-primary:disabled,
    .btn-outline-primary:not(:disabled):not(.disabled):active,
    .btn-outline-primary:not(:disabled):not(.disabled).active,
    .show > .btn-outline-primary.dropdown-toggle {
        color: #fff !important;
        background-color: var(--clinic-primary) !important;
        border-color: var(--clinic-primary) !important;
    }

    .btn-outline-light {
        color: #fff !important;
        background-color: var(--clinic-primary) !important;
        border-color: var(--clinic-primary) !important;
    }

    .bg-light,
    .btn-light,
    .btn-light.disabled,
    .btn-light:disabled {
        background-color: var(--clinic-primary-light) !important;
        border-color: var(--clinic-primary-light) !important;
    }

    .text-light {
        color: var(--clinic-primary-light) !important;
    }

    .border-light {
        border-color: var(--clinic-primary-lighter) !important;
    }

    .page-link {
        color: var(--clinic-primary) !important;
    }

    .badge-primary,
    .page-item.active .page-link,
    .list-group-item.active {
        color: #fff !important;
    }

    .testimonial-carousel .owl-dot {
        background-color: var(--clinic-primary-light) !important;
    }

    .footer .btn-primary i {
        color: #fff !important;
    }
</style>
