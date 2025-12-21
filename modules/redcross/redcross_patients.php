<?php
require_once __DIR__ . '/../../backend/redcross_backend_patients.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records - REDCROSS</title>
    <link rel="icon" href="../../assets/logo/redcross_2.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .content-area {
            padding: 30px;
            flex: 1;
        }

        .content-area h2 {
            font-size: 2rem;
            font-weight: 600;
        }

        .recent-activity h5 {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .recent-activity,
        .table,
        .form-label,
        .form-control,
        .form-select,
        .btn {
            font-size: 0.98rem;
        }

        .recent-activity {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
        }

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
                    <a class="nav-link" href="redcross_membership.php">
                        <i class="bi bi-person-add"></i>
                        <span>Membership</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="redcross_patients.php">
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
            <div></div>
        </nav>

        <!-- Content Area -->
        <div class="content-area">
            <h2 class="mb-4">Patient Records</h2>

            <div class="recent-activity mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="mb-0">Patient Actions</h5>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#patientModal">
                        <i class="bi bi-plus-circle me-1"></i>
                        Add Patient Record
                    </button>
                </div>
                <?php if (!empty($patient_message)): ?>
                    <div class="alert alert-info py-2 mb-0"><?php echo $patient_message; ?></div>
                <?php else: ?>
                    <p class="text-muted mb-0">Use the button to add a new patient record.</p>
                <?php endif; ?>
            </div>

            <div class="recent-activity">
                <h5 class="mb-3">Patient List</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Date of Service</th>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Address</th>
                                <th>Case / Condition</th>
                                <th>Remarks</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patients)): ?>
                                <tr><td colspan="7" class="text-center text-muted">No patient records yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($patients as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['date_of_service']); ?></td>
                                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                                        <td><?php echo (int)$p['age']; ?></td>
                                        <td><?php echo htmlspecialchars($p['address']); ?></td>
                                        <td><?php echo htmlspecialchars($p['case_description']); ?></td>
                                        <td><?php echo htmlspecialchars($p['remarks']); ?></td>
                                        <td class="text-end">
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this patient record?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="patient_id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
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

    <!-- Add Patient Modal -->
    <div class="modal fade" id="patientModal" tabindex="-1" aria-labelledby="patientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="patientModalLabel">Add Patient Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="create">
                        <div class="col-md-4">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-control" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Case / Condition</label>
                            <input type="text" name="case_description" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date of Service</label>
                            <input type="date" name="date_of_service" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Remarks</label>
                            <input type="text" name="remarks" class="form-control">
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i> Save Patient
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const closeSidebar = document.getElementById('closeSidebar');

            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.add('active');
                    sidebarOverlay.classList.add('active');
                });
            }

            if (closeSidebar) {
                closeSidebar.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                });
            }

            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
        });
    </script>
</body>
</html>


