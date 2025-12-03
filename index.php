<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SocieTREE</title>
    <link rel="icon" href="assets/logo/societree_1.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap');

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

        .container-fluid {
            min-height: 100vh;
        }
        
        .image-container {
            height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .side-image {
            width: 70%;
            height: 70%;
            object-fit: cover;
            transform: translateX(100px) translateY(-60px);
        }

        .image-caption {
            position: absolute;
            bottom: 50px;
            left: 50px;
            color: #343a40;
            max-width: 700px;
        }

        .image-caption h2 {
            font-size: 3rem;
            font-weight: 700;
            margin: 0;
        }

        .highlight-text {
            font-weight: 700;
            color: #1E174A;
        }

        .login-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            min-height: 650px;
            margin-left: 180px;
        }
        
        .logo {
            width: 200px;
            height: auto;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 2rem;
            display: block;
        }
        
        .form-control {
            border-radius: 15px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #cfdaceff;
        }
        
        .btn-login {
            background-color: #8BC53F;
            color: white;
            border: none;
            border-radius: 25px;
            padding: 0.75rem;
            width: 100%;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .btn-login:hover {
            background-color: #70a130;
        }
        
        .forgot-password {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .forgot-password:hover {
            color: #007bff;
            text-decoration: underline;
        }
        
        .welcome-text {
            font-size: 2rem;
            font-weight: 700;
            color: #343a40;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 991.98px) {
            .image-container {
                height: auto;
                min-height: 40vh;
                padding: 2rem 0 1rem;
            }
            
            .side-image {
                width: 90%;
                height: auto;
                object-fit: contain;
                transform: none;
            }
            
            .image-caption {
                position: static;
                text-align: center;
                padding: 1rem;
                margin-top: 1rem;
            }
            
            .image-caption h2 {
                font-size: 2rem;
            }
            
            .login-card {
                margin-left: auto;
                margin-right: auto;
                margin-top: 0;
                min-height: auto;
                padding: 2rem 1.5rem;
            }
            
            .col-md-6.d-flex {
                padding: 1rem;
            }
        }

        @media (max-width: 575.98px) {
            .side-image {
                width: 90%;
                max-height: 250px;
            }
            
            .image-caption h2 {
                font-size: 1.5rem;
            }
            
            .login-card {
                padding: 1.5rem 1rem;
            }
            
            .logo {
                width: 150px;
            }
            
            .welcome-text {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    
    <div class="container-fluid">
        <div class="row min-vh-100">
            <!-- For desktop: Image and caption on the right -->
            <div class="col-md-6 d-none d-md-block p-0">
                <div class="image-container">
                    <img src="assets/img/societree_img.png" alt="Side Image" class="side-image">
                    <div class="image-caption">
                        <h2>THE INNOVATORS OF TOMORROW, <span class="highlight-text">TRAILBLAZERS</span></h2>
                    </div>
                </div>
            </div>
            
            <!-- For mobile: Image and caption first -->
            <div class="col-12 d-md-none">
                <div class="image-container">
                    <img src="assets/img/societree_img.png" alt="Side Image" class="side-image">
                </div>
                <div class="image-caption">
                    <h2>THE INNOVATORS OF TOMORROW, <span class="highlight-text">TRAILBLAZERS</span></h2>
                </div>
            </div>

            <!-- Login card - appears after image and caption on mobile -->
            <div class="col-md-6 d-flex justify-content-center align-items-center">
                <div class="login-card">
                    <!-- Logo -->
                     <img src="assets/logo/societree_1.png" alt="Logo" class="logo">
                    
                    <!-- Login Form -->
                    <form id="loginForm">
                        <div class="mb-3">
                            <h1 class="welcome-text">WELCOME</h1>
                        </div>
                        <div class="mb-3">
                            <label for="idNumber" class="form-label">ID NUMBER</label>
                            <input type="text" class="form-control" id="idNumber" name="student_id" placeholder="Enter your ID number" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">PASSWORD</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                        </div>
                        <button type="submit" class="btn-login" id="loginBtn">LOGIN</button>
                        <div class="text-center">
                            <a href="#" class="forgot-password">FORGOT PASSWORD?</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    
    <!-- Custom JavaScript -->
    <script>
      (function(){
        const form = document.getElementById('loginForm');
        const btn = document.getElementById('loginBtn');
        if (!form) return;
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const student_id = document.getElementById('idNumber').value.trim();
          const password = document.getElementById('password').value;
          if (!student_id || !password) return;
          const original = btn.textContent;
          btn.disabled = true; btn.textContent = 'Signing in...';
          try {
            const res = await fetch('backend/login.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ student_id, password, accept_terms: true })
            });
            const data = await res.json().catch(()=>({success:false,message:'Invalid response'}));
            if (res.ok && data && data.success) {
              window.location.href = 'dashboard.php';
            } else {
              alert(data && data.message ? data.message : 'Login failed');
            }
          } catch(err) {
            alert('Network error. Please try again.');
          } finally {
            btn.disabled = false; btn.textContent = original;
          }
        });
      })();
    </script>

</body>
</html>