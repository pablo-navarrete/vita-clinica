<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>SPA Center - Beauty & Spa HTML Template</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="Free HTML Templates" name="keywords">
    <meta content="Free HTML Templates" name="description">

    <!-- Favicon -->
    <link href="{{ asset('img/favicon.ico')}}" rel="icon">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="{{ asset('lib/animate/animate.min.css" rel="stylesheet')}}">
    <link href="{{ asset('lib/owlcarousel/assets/owl.carousel.min.css')}}" rel="stylesheet">
    <link href="{{ asset('lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css')}}" rel="stylesheet" />

    <!-- Customized Bootstrap Stylesheet -->
    <link href="{{ asset('css/style.css')}}" rel="stylesheet">
    @include('customer.web.theme-colors')
</head>

<body>
 @include('customer.web.menu')


    @include('customer.web.banners')


    <!-- About Start -->
    <div id="quienes-somos" class="container-fluid py-5">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-6 pb-5 pb-lg-0">
                    <img class="img-fluid w-100" src="img/about.jpg" alt="">
                </div>
                <div class="col-lg-6">
                    <h6 class="d-inline-block  text-uppercase bg-light py-1 px-2" style="color: {{ themeColor(600) }} !important;">About Us</h6>
                    <h1 class="mb-4">Your Best Spa, Beauty & Skin Care Center</h1>
                    <p class="pl-4 border-left border-primary">Dolores lorem lorem ipsum sit et ipsum. Sadip sea amet diam dolore sed et. Sit rebum labore sit sit ut vero no sit. Et elitr stet dolor sed sit et sed ipsum et kasd erat duo eos et erat</p>
                    <div class="row pt-3">
                        <div class="col-6">
                            <div class="bg-light text-center p-4">
                                <h3 class="display-4 " data-toggle="counter-up"style="color: {{ themeColor(600) }} !important;">99</h3>
                                <h6 class="text-uppercase">Spa Specialist</h6>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light text-center p-4">
                                <h3 class="display-4 text-primary" data-toggle="counter-up">999</h3>
                                <h6 class="text-uppercase">Happy Clients</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- About End -->


    <!-- Service Start -->
    <div id="servicios" class="container-fluid px-0 py-5 my-5">
        <div class="row mx-0 justify-content-center text-center">
            <div class="col-lg-6">
                <h6 class="d-inline-block bg-light text-primary text-uppercase py-1 px-2">Our Service</h6>
                <h1>Spa & Beauty Services</h1>
            </div>
        </div>
        <div class="owl-carousel service-carousel">
            <div class="service-item position-relative">
                <img class="img-fluid" src="img/service-1.jpg" alt="">
                <div class="service-text text-center">
                    <h4 class="text-white font-weight-medium px-3">Body Massage</h4>
                    <p class="text-white px-3 mb-3">Elitr labore sit dolor erat est lorem diam sea ipsum diam dolor duo sit ipsum</p>
                    <div class="w-100 bg-white text-center p-4" >
                        <a class="btn btn-primary" href="">Make Order</a>
                    </div>
                </div>
            </div>
            <div class="service-item position-relative">
                <img class="img-fluid" src="img/service-2.jpg" alt="">
                <div class="service-text text-center">
                    <h4 class="text-white font-weight-medium px-3">Stone Therapy</h4>
                    <p class="text-white px-3 mb-3">Elitr labore sit dolor erat est lorem diam sea ipsum diam dolor duo sit ipsum</p>
                    <div class="w-100 bg-white text-center p-4" >
                        <a class="btn btn-primary" href="">Make Order</a>
                    </div>
                </div>
            </div>
            <div class="service-item position-relative">
                <img class="img-fluid" src="img/service-3.jpg" alt="">
                <div class="service-text text-center">
                    <h4 class="text-white font-weight-medium px-3">Facial Therapy</h4>
                    <p class="text-white px-3 mb-3">Elitr labore sit dolor erat est lorem diam sea ipsum diam dolor duo sit ipsum</p>
                    <div class="w-100 bg-white text-center p-4" >
                        <a class="btn btn-primary" href="">Make Order</a>
                    </div>
                </div>
            </div>
            <div class="service-item position-relative">
                <img class="img-fluid" src="img/service-4.jpg" alt="">
                <div class="service-text text-center">
                    <h4 class="text-white font-weight-medium px-3">Skin Care</h4>
                    <p class="text-white px-3 mb-3">Elitr labore sit dolor erat est lorem diam sea ipsum diam dolor duo sit ipsum</p>
                    <div class="w-100 bg-white text-center p-4" >
                        <a class="btn btn-primary" href="">Make Order</a>
                    </div>
                </div>
            </div>
            <div class="service-item position-relative">
                <img class="img-fluid" src="img/service-5.jpg" alt="">
                <div class="service-text text-center">
                    <h4 class="text-white font-weight-medium px-3">Stream Bath</h4>
                    <p class="text-white px-3 mb-3">Elitr labore sit dolor erat est lorem diam sea ipsum diam dolor duo sit ipsum</p>
                    <div class="w-100 bg-white text-center p-4" >
                        <a class="btn btn-primary" href="">Make Order</a>
                    </div>
                </div>
            </div>
            <div class="service-item position-relative">
                <img class="img-fluid" src="img/service-6.jpg" alt="">
                <div class="service-text text-center">
                    <h4 class="text-white font-weight-medium px-3">Face Masking</h4>
                    <p class="text-white px-3 mb-3">Elitr labore sit dolor erat est lorem diam sea ipsum diam dolor duo sit ipsum</p>
                    <div class="w-100 bg-white text-center p-4" >
                        <a class="btn btn-primary" href="">Make Order</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="row justify-content-center bg-appointment mx-0">
            <div class="col-lg-6 py-5">
                <div class="p-5 my-5" style="background: rgba(33, 30, 28, 0.7);">
                    <h1 class="text-white text-center mb-4">Make Appointment</h1>
                    <form>
                        <div class="form-row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <input type="text" class="form-control bg-transparent p-4" placeholder="Your Name" required="required" />
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <input type="email" class="form-control bg-transparent p-4" placeholder="Your Email" required="required" />
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <div class="date" id="date" data-target-input="nearest">
                                        <input type="text" class="form-control bg-transparent p-4 datetimepicker-input" placeholder="Select Date" data-target="#date" data-toggle="datetimepicker"/>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <div class="time" id="time" data-target-input="nearest">
                                        <input type="text" class="form-control bg-transparent p-4 datetimepicker-input" placeholder="Select Time" data-target="#time" data-toggle="datetimepicker"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <select class="custom-select bg-transparent px-4" style="height: 47px;">
                                        <option selected>Select A Service</option>
                                        <option value="1">Service 1</option>
                                        <option value="2">Service 1</option>
                                        <option value="3">Service 1</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <button class="btn btn-primary btn-block" type="submit" style="height: 47px;">Make Appointment</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Service End -->


    <!-- Open Hours Start -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="row">
                <div class="col-lg-6" style="min-height: 500px;">
                    <div class="position-relative h-100">
                        <img class="position-absolute w-100 h-100" src="img/opening.jpg" style="object-fit: cover;">
                    </div>
                </div>
                <div class="col-lg-6 pt-5 pb-lg-5">
                    <div class="hours-text bg-light p-4 p-lg-5 my-lg-5">
                        <h6 class="d-inline-block text-white text-uppercase bg-primary py-1 px-2">Open Hours</h6>
                        <h1 class="mb-4">Best Relax And Spa Zone</h1>
                        <p>Dolores lorem lorem ipsum sit et ipsum. Sadip sea amet diam dolore sed et. Sit rebum labore sit sit ut vero no sit. Et elitr stet dolor sed sit et sed ipsum et kasd ut. Erat duo eos et erat sed diam duo</p>
                        <ul class="list-inline">
                            <li class="h6 py-1"><i class="far fa-circle text-primary mr-3"></i>Mon – Fri : 9:00 AM - 7:00 PM</li>
                            <li class="h6 py-1"><i class="far fa-circle text-primary mr-3"></i>Saturday : 9:00 AM - 6:00 PM</li>
                            <li class="h6 py-1"><i class="far fa-circle text-primary mr-3"></i>Sunday : Closed</li>
                        </ul>
                        <a href="" class="btn btn-primary mt-2">Book Now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Open Hours End -->


    <!-- Pricing Start -->
    <div class="container-fluid bg-pricing" style="margin: 90px 0;">
        <div class="container">
            <div class="row">
                <div class="col-lg-5" style="min-height: 500px;">
                    <div class="position-relative h-100">
                        <img class="position-absolute w-100 h-100" src="img/pricing.jpg" style="object-fit: cover;">
                    </div>
                </div>
                <div class="col-lg-7 pt-5 pb-lg-5">
                    <div class="pricing-text bg-light p-4 p-lg-5 my-lg-5">
                        <div class="owl-carousel pricing-carousel">
                            <div class="bg-white">
                                <div class="d-flex align-items-center justify-content-between border-bottom border-primary p-4">
                                    <h1 class="display-4 mb-0">
                                        <small class="align-top text-muted font-weight-medium" style="font-size: 22px; line-height: 45px;">$</small>49<small class="align-bottom text-muted font-weight-medium" style="font-size: 16px; line-height: 40px;">/Mo</small>
                                    </h1>
                                    <h5 class="text-primary text-uppercase m-0">Basic Plan</h5>
                                </div>
                                <div class="p-4">
                                    <p><i class="fa fa-check text-success mr-2"></i>Full Body Massage</p>
                                    <p><i class="fa fa-check text-success mr-2"></i>Deep Tissue Massage</p>
                                    <p><i class="fa fa-check text-success mr-2"></i>Hot Stone Massage</p>
                                    <p><i class="fa fa-check text-success mr-2"></i>Tissue Body Polish</p>
                                    <p><i class="fa fa-check text-success mr-2"></i>Foot & Nail Care</p>
                                    <a href="" class="btn btn-primary my-2">Order Now</a>
                                </div>
                            </div>
                            <div class="bg-white">
                                <div class="d-flex align-items-center justify-content-between border-bottom border-primary p-4">
                                    <h1 class="display-4 mb-0">
                                        <small class="align-top text-muted font-weight-medium" style="font-size: 22px; line-height: 45px;">$</small>99<small class="align-bottom text-muted font-weight-medium" style="font-size: 16px; line-height: 40px;">/Mo</small>
                                    </h1>
                                    <h5 class="text-primary text-uppercase m-0">Family Plan</h5>
                                </div>
                                <div class="p-4">
                                    <p><i class="fa fa-check text-success mr-2"></i>Full Body Massage</p>
                                    <p><i class="fa fa-check text-success mr-2"></i>Deep Tissue Massage</p>
                                    <p><i class="fa fa-check text-success mr-2"></i>Hot Stone Massage</p>
                                    <p><i class="fa fa-check text-success mr-2"></i>Tissue Body Polish</p>
                                    <p><i class="fa fa-check text-success mr-2"></i>Foot & Nail Care</p>
                                    <a href="" class="btn btn-primary my-2">Order Now</a>
                                </div>
                            </div>
                            <div class="bg-white">
                                <div class="d-flex align-items-center justify-content-between border-bottom border-primary p-4">
                                    <h1 class="display-4 mb-0">
                                        <small class="align-top text-muted font-weight-medium" style="font-size: 22px; line-height: 45px;">$</small>149<small class="align-bottom text-muted font-weight-medium" style="font-size: 16px; line-height: 40px;">/Mo</small>
                                    </h1>
                                    <h5 class="text-primary text-uppercase m-0">VIP Plan</h5>
                                </div>
                                <div class="p-4">
                                    <p><i class="fa fa-check text-success mr-2"></i>Full Body Massage</p>
                                    <p><i class="fa fa-check text-success mr-2"></i>Deep Tissue Massage</p>
                                    <p><i class="fa fa-check text-success mr-2"></i>Hot Stone Massage</p>
                                    <p><i class="fa fa-check text-success mr-2"></i>Tissue Body Polish</p>
                                    <p><i class="fa fa-check text-success mr-2"></i>Foot & Nail Care</p>
                                    <a href="" class="btn btn-primary my-2">Order Now</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Pricing End -->


    <!-- Team Start -->
    <div id="especialidades" class="container-fluid py-5">
        <div class="container pt-5">
            <div class="row justify-content-center text-center">
                <div class="col-lg-6">
                    <h6 class="d-inline-block bg-light text-primary text-uppercase py-1 px-2">Spa Specialist</h6>
                    <h1 class="mb-5">Spa & Beauty Specialist</h1>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="team position-relative overflow-hidden mb-5">
                        <img class="img-fluid" src="img/team-1.jpg" alt="">
                        <div class="position-relative text-center">
                            <div class="team-text bg-primary text-white">
                                <h5 class="text-white text-uppercase">Olivia Mia</h5>
                                <p class="m-0">Spa & Beauty Expert</p>
                            </div>
                            <div class="team-social bg-dark text-center">
                                <a class="btn btn-outline-primary btn-square mr-2" href="#"><i class="fab fa-twitter"></i></a>
                                <a class="btn btn-outline-primary btn-square mr-2" href="#"><i class="fab fa-facebook-f"></i></a>
                                <a class="btn btn-outline-primary btn-square mr-2" href="#"><i class="fab fa-linkedin-in"></i></a>
                                <a class="btn btn-outline-primary btn-square" href="#"><i class="fab fa-instagram"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="team position-relative overflow-hidden mb-5">
                        <img class="img-fluid" src="img/team-2.jpg" alt="">
                        <div class="position-relative text-center">
                            <div class="team-text bg-primary text-white">
                                <h5 class="text-white text-uppercase">Cory Brown</h5>
                                <p class="m-0">Spa & Beauty Expert</p>
                            </div>
                            <div class="team-social bg-dark text-center">
                                <a class="btn btn-outline-primary btn-square mr-2" href="#"><i class="fab fa-twitter"></i></a>
                                <a class="btn btn-outline-primary btn-square mr-2" href="#"><i class="fab fa-facebook-f"></i></a>
                                <a class="btn btn-outline-primary btn-square mr-2" href="#"><i class="fab fa-linkedin-in"></i></a>
                                <a class="btn btn-outline-primary btn-square" href="#"><i class="fab fa-instagram"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="team position-relative overflow-hidden mb-5">
                        <img class="img-fluid" src="img/team-3.jpg" alt="">
                        <div class="position-relative text-center">
                            <div class="team-text bg-primary text-white">
                                <h5 class="text-white text-uppercase">Elizabeth Ross</h5>
                                <p class="m-0">Spa & Beauty Expert</p>
                            </div>
                            <div class="team-social bg-dark text-center">
                                <a class="btn btn-outline-primary btn-square mr-2" href="#"><i class="fab fa-twitter"></i></a>
                                <a class="btn btn-outline-primary btn-square mr-2" href="#"><i class="fab fa-facebook-f"></i></a>
                                <a class="btn btn-outline-primary btn-square mr-2" href="#"><i class="fab fa-linkedin-in"></i></a>
                                <a class="btn btn-outline-primary btn-square" href="#"><i class="fab fa-instagram"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="team position-relative overflow-hidden mb-5">
                        <img class="img-fluid" src="img/team-4.jpg" alt="">
                        <div class="position-relative text-center">
                            <div class="team-text bg-primary text-white">
                                <h5 class="text-white text-uppercase">Kelly Walke</h5>
                                <p class="m-0">Spa & Beauty Expert</p>
                            </div>
                            <div class="team-social bg-dark text-center">
                                <a class="btn btn-outline-primary btn-square mr-2" href="#"><i class="fab fa-twitter"></i></a>
                                <a class="btn btn-outline-primary btn-square mr-2" href="#"><i class="fab fa-facebook-f"></i></a>
                                <a class="btn btn-outline-primary btn-square mr-2" href="#"><i class="fab fa-linkedin-in"></i></a>
                                <a class="btn btn-outline-primary btn-square" href="#"><i class="fab fa-instagram"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Team End -->


    <!-- Testimonial Start -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-6 pb-5 pb-lg-0">
                    <img class="img-fluid w-100" src="img/testimonial.jpg" alt="">
                </div>
                <div class="col-lg-6">
                    <h6 class="d-inline-block text-primary text-uppercase bg-light py-1 px-2">Testimonial</h6>
                    <h1 class="mb-4">What Our Clients Say!</h1>
                    <div class="owl-carousel testimonial-carousel">
                        <div class="position-relative">
                            <i class="fa fa-3x fa-quote-right text-primary position-absolute" style="top: -6px; right: 0;"></i>
                            <div class="d-flex align-items-center mb-3">
                                <img class="img-fluid rounded-circle" src="img/testimonial-1.jpg" style="width: 60px; height: 60px;" alt="">
                                <div class="ml-3">
                                    <h6 class="text-uppercase">Client Name</h6>
                                    <span>Profession</span>
                                </div>
                            </div>
                            <p class="m-0">Aliquyam sed elitr elitr erat sed diam ipsum eirmod eos lorem nonumy. Tempor sea ipsum diam  sed clita dolore eos dolores magna erat dolore sed stet justo et dolor.</p>
                        </div>
                        <div class="position-relative">
                            <i class="fa fa-3x fa-quote-right text-primary position-absolute" style="top: -6px; right: 0;"></i>
                            <div class="d-flex align-items-center mb-3">
                                <img class="img-fluid rounded-circle" src="img/testimonial-2.jpg" style="width: 60px; height: 60px;" alt="">
                                <div class="ml-3">
                                    <h6 class="text-uppercase">Client Name</h6>
                                    <span>Profession</span>
                                </div>
                            </div>
                            <p class="m-0">Aliquyam sed elitr elitr erat sed diam ipsum eirmod eos lorem nonumy. Tempor sea ipsum diam  sed clita dolore eos dolores magna erat dolore sed stet justo et dolor.</p>
                        </div>
                        <div class="position-relative">
                            <i class="fa fa-3x fa-quote-right text-primary position-absolute" style="top: -6px; right: 0;"></i>
                            <div class="d-flex align-items-center mb-3">
                                <img class="img-fluid rounded-circle" src="img/testimonial-3.jpg" style="width: 60px; height: 60px;" alt="">
                                <div class="ml-3">
                                    <h6 class="text-uppercase">Client Name</h6>
                                    <span>Profession</span>
                                </div>
                            </div>
                            <p class="m-0">Aliquyam sed elitr elitr erat sed diam ipsum eirmod eos lorem nonumy. Tempor sea ipsum diam  sed clita dolore eos dolores magna erat dolore sed stet justo et dolor.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Testimonial End -->


    @include('customer.web.footer')


    <!-- Back to Top -->
    <a href="#" class="btn btn-lg btn-primary back-to-top"><i class="fa fa-angle-double-up"></i></a>


    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('lib/easing/easing.min.js')}}"></script>
    <script src="{{ asset('lib/waypoints/waypoints.min.js')}}"></script>
    <script src="{{ asset('lib/counterup/counterup.min.js')}}"></script>
    <script src="{{ asset('lib/owlcarousel/owl.carousel.min.js')}}"></script>
    <script src="{{ asset('lib/tempusdominus/js/moment.min.js')}}"></script>
    <script src="{{ asset('lib/tempusdominus/js/moment-timezone.min.js')}}"></script>
    <script src="{{ asset('lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js')}}"></script>

  

    <!-- Template Javascript -->
    <script src="{{ asset('js/main.js')}}"></script>
</body>

</html>
