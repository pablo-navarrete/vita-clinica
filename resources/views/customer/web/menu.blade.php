    <!-- Topbar Start -->
    <div class="container-fluid bg-light d-none d-lg-block" style="background-color: {{ themeColor(100) }} !important;">
        <div class="row py-2 px-lg-5">
            <div class="col-lg-6 text-left mb-2 mb-lg-0">
                <div class="d-inline-flex align-items-center">
                    <small><i class="fa fa-phone-alt mr-2"></i>+012 345 6789</small>
                    <small class="px-3">|</small>
                    <small><i class="fa fa-envelope mr-2"></i>info@example.com</small>
                </div>
            </div>
            <div class="col-lg-6 text-right">
                <div class="d-inline-flex align-items-center">
                    <a class="px-2" href="" style="color: {{ themeColor(800) }} !important;">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a class="px-2" href="" style="color: {{ themeColor(800) }} !important;">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a class="px-2" href="" style="color: {{ themeColor(800) }} !important;">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a class="px-2" href="" style="color: {{ themeColor(800) }} !important;">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a class="px-2" href="" style="color: {{ themeColor(800) }} !important;">
                        <i class="fab fa-youtube"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- Topbar End -->


    <!-- Navbar Start -->
    <div class="container-fluid p-0">
        <nav class="navbar navbar-expand-lg bg-white navbar-light py-3 py-lg-0 px-lg-5">
           <a href="index.php"
   class="navbar-brand ml-lg-3"
   style="display:flex; align-items:center; gap:10px;">

    @if(isset(app('logos_by_location')['header']))
        <img src="{{ asset('storage/' . app('logos_by_location')['header']->url) }}"
             style="height:80px; width:auto; object-fit:contain;">
    @endif

    <h1 class="m-0"
        style="color: {{ themeColor(800) }} !important; margin:0;">
        {{ app()->bound('customer') ? app('customer')->name : config('app.name') }}
    </h1>

</a>
            <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-between px-lg-3" id="navbarCollapse">
                <div class="navbar-nav m-auto py-0">
                    <a href="index.php" class="nav-item nav-link active" style="color: {{ themeColor(600) }} !important;">
                        INICIO
                    </a>
                    <a href="about.html" class="nav-item nav-link">QUIENES SOMOS</a>
                    <a href="service.html" class="nav-item nav-link">SERVICIOS</a>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">ESPECIALIDADES</a>
                        <div class="dropdown-menu rounded-0 m-0">
                            <a href="appointment.html" class="dropdown-item">TRAUMATOLOGÍA</a>
                            <a href="opening.html" class="dropdown-item">MEDICO GENERAL</a>
                            <a href="team.html" class="dropdown-item">PSICOLOGÍA</a>
                            <a href="testimonial.html" class="dropdown-item">Testimonial</a>
                        </div>
                    </div>
                    <a href="contact.html" class="nav-item nav-link">CONTACTO</a>
                </div>
                <a href="" class="btn d-none d-lg-block" style="background-color: {{ themeColor(600) }} !important; color:white;" >WHATSAPP</a>
            </div>
        </nav>
    </div>
    <!-- Navbar End -->