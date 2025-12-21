<?php
require_once __DIR__ . '/../../backend/redcross_backend_membership.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership - REDCROSS</title>
    <link rel="icon" href="../../assets/logo/redcross_2.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap');

        *{
        font-family: "Oswald", sans-serif;
        font-weight: 500;
        font-style: normal;
        }

        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            background: #f80305;
            color: white;
            width: 260px;
            min-height: 100vh;
            transition: all 0.3s;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header img {
            height: 50px;
        }

        .sidebar-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .btn-close-sidebar {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
            padding: 5px;
            display: none;
        }

        .btn-close-sidebar:hover {
            opacity: 0.7;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            margin: 5px 0;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }

        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-left: 5px solid #052369;
        }

        .nav-link i {
            margin-right: 10px;
            font-size: 1.1rem;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: 260px;
            transition: margin-left 0.3s;
        }

        .top-navbar {
            background: white;
            padding: 15px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .search-box {
            width: 300px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .content-area {
            padding: 30px;
            flex: 1;
        }

        .recent-activity {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #3498db;
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #1e174a;
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .btn-close-sidebar {
                display: block;
            }
            
            .search-box {
                width: 200px;
            }
        }

        @media (max-width: 768px) {
            .top-navbar {
                padding: 15px;
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .search-box {
                width: 100%;
                order: 3;
            }
            
            .user-info {
                margin-left: auto;
            }
            
            .user-details {
                display: none;
            }
            
            .content-area {
                padding: 20px 15px;
            }
            
            .recent-activity {
                padding: 20px;
            }
        }

        @media (max-width: 576px) {
            .activity-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .activity-icon {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .sidebar-header h4 {
                font-size: 1rem;
            }
        }

        /* Overlay for mobile menu */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
    </style>
</head>
<body>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-header-content">
                <div class="logo-container">
                    <img src="../../assets/logo/redcross_2.png" alt="SITE Logo">
                    <h4>Red Cross Youth</h4>
                </div>
                <button class="btn-close-sidebar" id="closeSidebar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="redcross_dashboard.php">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_announcement.php">
                        <i class="bi bi-megaphone"></i>
                        <span>Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="redcross_membership.php">
                        <i class="bi bi-person-add"></i>
                        <span>Membership</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_patients.php">
                        <i class="bi bi-heart-pulse"></i>
                        <span>Patient Records</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_promotion.php">
                        <i class="bi bi-chevron-double-up"></i>
                        <span>Promotion</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="redcross_report.php">
                        <i class="bi bi-file-earmark"></i>
                        <span>Report</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../dashboard.php">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <button class="menu-toggle" id="menuToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="search-box">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search...">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
            <div class="user-info">
                <div class="notifications">
                    <i class="bi bi-bell fs-5"></i>
                </div>
                <div class="user-avatar">
                    <i class="bi bi-person-circle"></i>
                </div>
                <div class="user-details">
                    <div class="user-name">Tim</div>
                    <div class="user-role">Student</div>
                </div>
            </div>
        </nav>

        <!-- Content Area -->
        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">Membership</h2>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                    <i class="bi bi-plus-circle me-1"></i>
                    Add Member
                </button>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-info py-2 mb-3"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Centered modal for creating member (pattern similar to Announcement) -->
            <div class="modal fade" id="addMemberModal" tabindex="-1" aria-labelledby="addMemberModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addMemberModalLabel">Add Member</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="create">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ID Number</label>
                                    <input type="text" name="id_number" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Course</label>
                                    <select name="department" class="form-select">
                                        <option value="">Select</option>
                                        <option value="BSIT">BSIT</option>
                                        <option value="BSCS">BSCS</option>
                                        <option value="BSN">BSN</option>
                                        <option value="BSEE">BSEE</option>
                                        <option value="BSBA">BSBA</option>
                                        <option value="BFPT">BFPT</option>
                                        <option value="BTLED-IA">BTLED-IA</option>
                                        <option value="BTLED-HE">BTLED-HE</option>
                                        <option value="BTLED-ICT">BTLED-ICT</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Year &amp; Section</label>
                                    <select name="year_level" class="form-select">
                                        <option value="">Select</option>
                                        <option value="1st Year - A">1st Year - A</option>
                                        <option value="1st Year - B">1st Year - B</option>
                                        <option value="1st Year - C">1st Year - C</option>
                                        <option value="2nd Year - A">2nd Year - A</option>
                                        <option value="2nd Year - B">2nd Year - B</option>
                                        <option value="3rd Year - A">3rd Year - A</option>
                                        <option value="3rd Year - B">3rd Year - B</option>
                                        <option value="4th Year - A">4th Year - A</option>
                                        <option value="4th Year - B">4th Year - B</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control">
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary">Save Member</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="recent-activity">
                <h5 class="mb-3">Member List</h5>
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="active"     <?php echo $filter_status==='active'?'selected':''; ?>>Active</option>
                            <option value="inactive"   <?php echo $filter_status==='inactive'?'selected':''; ?>>Inactive</option>
                            <option value="suspended"  <?php echo $filter_status==='suspended'?'selected':''; ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Year &amp; Section</label>
                        <select name="year" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="1st Year - A" <?php echo $filter_year==='1st Year - A'?'selected':''; ?>>1st Year - A</option>
                            <option value="1st Year - B" <?php echo $filter_year==='1st Year - B'?'selected':''; ?>>1st Year - B</option>
                            <option value="1st Year - C" <?php echo $filter_year==='1st Year - C'?'selected':''; ?>>1st Year - C</option>
                            <option value="2nd Year - A" <?php echo $filter_year==='2nd Year - A'?'selected':''; ?>>2nd Year - A</option>
                            <option value="2nd Year - B" <?php echo $filter_year==='2nd Year - B'?'selected':''; ?>>2nd Year - B</option>
                            <option value="3rd Year - A" <?php echo $filter_year==='3rd Year - A'?'selected':''; ?>>3rd Year - A</option>
                            <option value="3rd Year - B" <?php echo $filter_year==='3rd Year - B'?'selected':''; ?>>3rd Year - B</option>
                            <option value="4th Year - A" <?php echo $filter_year==='4th Year - A'?'selected':''; ?>>4th Year - A</option>
                            <option value="4th Year - B" <?php echo $filter_year==='4th Year - B'?'selected':''; ?>>4th Year - B</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Course</label>
                        <select name="department" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="BSIT" <?php echo $filter_department==='BSIT'?'selected':''; ?>>BSIT</option>
                            <option value="BSCS" <?php echo $filter_department==='BSCS'?'selected':''; ?>>BSCS</option>
                            <option value="BSN" <?php echo $filter_department==='BSN'?'selected':''; ?>>BSN</option>
                            <option value="BSEE" <?php echo $filter_department==='BSEE'?'selected':''; ?>>BSEE</option>
                            <option value="BSBA" <?php echo $filter_department==='BSBA'?'selected':''; ?>>BSBA</option>
                            <option value="BFPT" <?php echo $filter_department==='BFPT'?'selected':''; ?>>BFPT</option>
                            <option value="BTLED-IA" <?php echo $filter_department==='BTLED-IA'?'selected':''; ?>>BTLED-IA</option>
                            <option value="BTLED-HE" <?php echo $filter_department==='BTLED-HE'?'selected':''; ?>>BTLED-HE</option>
                            <option value="BTLED-ICT" <?php echo $filter_department==='BTLED-ICT'?'selected':''; ?>>BTLED-ICT</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end justify-content-end">
                        <button type="submit" class="btn btn-outline-secondary btn-sm me-2">Filter</button>
                        <a href="redcross_membership.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Full Name</th>
                                <th>ID Number</th>
                                <th>Course</th>
                                <th>Year &amp; Section</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($members)): ?>
                            <tr><td colspan="7" class="text-center text-muted">No members found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($members as $index => $m): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($m['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($m['id_number']); ?></td>
                                    <td><?php echo htmlspecialchars($m['course']); ?></td>
                                    <td><?php echo htmlspecialchars($m['year_section']); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php
                                                if ($m['status']==='active') echo 'bg-success';
                                                elseif ($m['status']==='suspended') echo 'bg-danger';
                                                else echo 'bg-secondary';
                                            ?>">
                                            <?php echo ucfirst($m['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button"
                                                    class="btn btn-outline-primary btn-edit-member"
                                                    data-id="<?php echo $m['id']; ?>"
                                                    data-full_name="<?php echo htmlspecialchars($m['full_name'], ENT_QUOTES); ?>"
                                                    data-department="<?php echo htmlspecialchars($m['course'], ENT_QUOTES); ?>"
                                                    data-year="<?php echo htmlspecialchars($m['year_section'], ENT_QUOTES); ?>"
                                                    data-email="<?php echo htmlspecialchars($m['email'], ENT_QUOTES); ?>"
                                                    data-phone="<?php echo htmlspecialchars($m['phone'], ENT_QUOTES); ?>"
                                                    title="Edit member"
                                                    aria-label="Edit member">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="member_id" value="<?php echo $m['id']; ?>">
                                                <button type="submit"
                                                        class="btn btn-outline-danger"
                                                        onclick="return confirm('Delete this member?');"
                                                        title="Delete member"
                                                        aria-label="Delete member">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <?php if ($m['status'] === 'inactive'): ?>
                                            <div class="btn-group btn-group-sm mt-1" role="group">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="status">
                                                    <input type="hidden" name="member_id" value="<?php echo $m['id']; ?>">
                                                    <input type="hidden" name="status" value="active">
                                                    <button type="submit"
                                                            class="btn btn-success"
                                                            title="Approve member"
                                                            aria-label="Approve member">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="status">
                                                    <input type="hidden" name="member_id" value="<?php echo $m['id']; ?>">
                                                    <input type="hidden" name="status" value="suspended">
                                                    <button type="submit"
                                                            class="btn btn-danger"
                                                            title="Reject member"
                                                            aria-label="Reject member">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const closeSidebar = document.getElementById('closeSidebar');
            const editButtons = document.querySelectorAll('.btn-edit-member');

            
            // Toggle sidebar on menu button click
            menuToggle.addEventListener('click', function() {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
            });
            
            // Close sidebar methods:
            
            // 1. Close button click
            closeSidebar.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
            
            // 2. Overlay click
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });

            // 3. Auto-close when clicking menu links
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 992) {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                    }
                });
            });
            
            // 4. Window resize (close on desktop)
            window.addEventListener('resize', function() {
                if (window.innerWidth > 992) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                }
            });

            // Simple inline edit using prompt (keeps UI minimal)
            editButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id   = this.getAttribute('data-id');
                    const name = prompt('Full Name:', this.getAttribute('data-full_name'));
                    if (!name) return;
                    const dept = prompt('Department:', this.getAttribute('data-department') || '');
                    const year = prompt('Year Level:', this.getAttribute('data-year') || '');
                    const email= prompt('Email:', this.getAttribute('data-email') || '');
                    const phone= prompt('Phone:', this.getAttribute('data-phone') || '');

                    // Create a form dynamically to submit the update
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'redcross_membership.php';

                    const fields = {
                        action: 'update',
                        member_id: id,
                        full_name: name,
                        department: dept,
                        year_level: year,
                        email: email,
                        phone: phone
                    };

                    Object.keys(fields).forEach(function(key) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = fields[key];
                        form.appendChild(input);
                    });

                    document.body.appendChild(form);
                    form.submit();
                });
            });
        });
    </script>
</body>
</html>