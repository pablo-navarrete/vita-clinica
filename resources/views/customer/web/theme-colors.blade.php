<style>
    :root {
        --clinic-primary: {{ themeColor(600) }};
        --clinic-primary-hover: {{ themeColor(800) }};
        --clinic-primary-active: {{ themeColor(800) }};
        --clinic-primary-light: {{ themeColor(50) }};
        --clinic-primary-lighter: {{ themeColor(100) }};
        --primary: {{ themeColor(600) }};
    }

    a,
    .text-primary,
    a.text-primary:hover,
    a.text-primary:focus,
    .navbar-light .navbar-nav .nav-link:hover,
    .navbar-light .navbar-nav .nav-link.active {
        color: var(--clinic-primary) !important;
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
