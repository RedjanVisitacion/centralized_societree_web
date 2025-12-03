<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SocieTREE</title>
    <link rel="icon" href="assets/logo/societree_1.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Lilita+One&display=swap');

        *{
        font-family: "Oswald", sans-serif;
        font-weight: 500;
        font-style: normal;
        }

        body {
            background-color: white;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .navbar {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 0.5rem 0;
            position: sticky;
            top: 0;
            z-index: 1020;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: #333;
            display: flex;
            align-items: center;
        }

        .brand-text {
            font-family: 'Lilita One', sans-serif;
            font-weight: 400;
            color: #8BC53F;
            font-style: normal;
            margin-left: 0.5rem;
        }

        .brand-text-2 {
            font-family: 'Lilita One', sans-serif;
            font-weight: 400;
            color: #5b4c4a;
            font-style: normal;
        }

        .brand-text-3 {
            font-family: "Oswald", sans-serif;
            font-weight: 900;
            color: #1e174a;
            font-style: normal;
        }

        .brand-text-4 {
            font-family: "Oswald", sans-serif;
            font-weight: 800;
            color: #333;
            font-style: normal;
        }

        .nav-link {
            color: #666;
            font-weight: 600;
            margin: 0 1rem;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: #333;
        }

        /* Organization Cards Styling */
        .organization-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            background: white;
            padding: 10px;
            cursor: pointer;
        }

        .organization-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(139, 197, 63, 0.2);
            border: 3px solid #8BC53F;
        }

        .organization-icon {
            padding: 15px;
            /* background: linear-gradient(135deg, #8BC53F20, #5b4c4a10); */
            border-radius: 50%;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .card-title {
            font-family: 'Oswald', sans-serif;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: #1e174a;
            transition: all 0.3s ease;
        }

        .organization-card .card-text {
            font-size: 0.9rem;
            line-height: 1.4;
            color: #666;
            transition: all 0.3s ease;
        }

        /* Add a smooth transition for the entire card */
        .organization-card > * {
            transition: all 0.3s ease;
        }

        .footer {
            background-color: #5e4c47;
        }
        
        .footer-text {
            color: white;
        }

    </style>
</head>
<body>
    
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="assets/logo/societree_2.png" alt="Logo" height="40" class="d-inline-block align-text-top">
                <span class="brand-text">Socie</span>
                <span class="brand-text-2">TREE</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Image Carousel -->
    <div id="carouselExampleAutoplaying" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="assets/img/sample_img.jpg" class="d-block w-100" alt="...">
            </div>
            <div class="carousel-item">
                <img src="assets/img/sample_img2.jpg" class="d-block w-100" alt="...">
            </div>
            <div class="carousel-item">
                <img src="assets/img/sample_img3.jpg" class="d-block w-100" alt="...">
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>

    <!-- Tenants/Organizations Section -->
<div class="container my-5">
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h2 class="brand-text-3">USTP ORGANIZATIONS</h2>
            <p class="text-muted">Explore various student organizations in USTP Oroquieta</p>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Row 1 -->
        <!-- Organization Card 1 -->
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
            <a href="modules/usg/usg_dashboard.php" class="text-decoration-none">
                <div class="card organization-card h-100">
                    <div class="card-body text-center">
                        <div class="organization-icon mb-3">
                            <img src="assets/logo/usg_1.png" alt="USG" height="60" class="rounded-circle">
                        </div>
                        <h5 class="card-title brand-text-4">USG</h5>
                        <p class="card-text text-muted small">University of Student Government</p>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- Organization Card 2 -->
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
            <a href="modules/elecom/router.php" class="text-decoration-none">
                <div class="card organization-card h-100">
                    <div class="card-body text-center">
                        <div class="organization-icon mb-3">
                            <img src="assets/logo/elecom_1.png" alt="ELECOM" height="60" class="rounded-circle">
                        </div>
                        <h5 class="card-title brand-text-4">ELECOM</h5>
                        <p class="card-text text-muted small">Electoral Commission</p>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- Organization Card 3 -->
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
            <a href="modules/site/site_dashboard.php" class="text-decoration-none">
                <div class="card organization-card h-100">
                    <div class="card-body text-center">
                        <div class="organization-icon mb-3">
                            <img src="assets/logo/site_1.png" alt="SITE" height="60" class="rounded-circle">
                        </div>
                        <h5 class="card-title brand-text-4">SITE</h5>
                        <p class="card-text text-muted small">Society of Information Technology Enthusiasts</p>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- Organization Card 4 -->
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
            <a href="modules/pafe/pafe_dashboard.php" class="text-decoration-none">
                <div class="card organization-card h-100">
                    <div class="card-body text-center">
                        <div class="organization-icon mb-3">
                            <img src="assets/logo/pafe_1.png" alt="PAFE" height="60" class="rounded-circle">
                        </div>
                        <h5 class="card-title brand-text-4">PAFE</h5>
                        <p class="card-text text-muted small">Prime Association of Future Educators</p>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- Row 2 -->
        <!-- Organization Card 5 -->
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
            <a href="modules/afprotech/afprotech_dashboard.php" class="text-decoration-none">
                <div class="card organization-card h-100">
                    <div class="card-body text-center">
                        <div class="organization-icon mb-3">
                            <img src="assets/logo/afprotech_1.png" alt="AFPROTECH" height="60" class="rounded-circle">
                        </div>
                        <h5 class="card-title brand-text-4">AFPROTECH</h5>
                        <p class="card-text text-muted small">Association of Food Processing Technology Students</p>
                    </div>
                </div>
            </a> 
        </div>
        
        <!-- Organization Card 6 -->
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
            <a href="modules/arcu/arcu_dashboard.php" class="text-decoration-none">
                <div class="card organization-card h-100">
                    <div class="card-body text-center">
                        <div class="organization-icon mb-3">
                            <img src="assets/logo/arcu_1.png" alt="ARCU" height="60" class="rounded-circle">
                        </div>
                        <h5 class="card-title brand-text-4">ARCU</h5>
                        <p class="card-text text-muted small">Arts and Culture</p>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- Organization Card 7 -->
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
            <a href="modules/access/access_dashboard.php" class="text-decoration-none">
                <div class="card organization-card h-100">
                    <div class="card-body text-center">
                        <div class="organization-icon mb-3">
                            <img src="assets/logo/access_1.png" alt="ACCESS" height="60" class="rounded-circle">
                        </div>
                        <h5 class="card-title brand-text-4">ACCESS</h5>
                        <p class="card-text text-muted small">Active Certified Computer Enhance Student Society</p>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- Organization Card 8 -->
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
            <a href="modules/redcross/redcross_dashboard.php" class="text-decoration-none">
                <div class="card organization-card h-100">
                    <div class="card-body text-center">
                        <div class="organization-icon mb-3">
                            <img src="assets/logo/redcross_1.png" alt="REDCROSS" height="60" class="rounded-circle">
                        </div>
                        <h5 class="card-title brand-text-4">REDCROSS</h5>
                        <p class="card-text text-muted small">Red Cross Youth</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

    <footer class="footer mt-5 py-4">
        <div class="container text-center">
            <p class="footer-text mb-0">&copy; 2025 SocieTREE. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    
    <!-- Custom JavaScript -->
    <script>
        
    </script>

</body>
</html>