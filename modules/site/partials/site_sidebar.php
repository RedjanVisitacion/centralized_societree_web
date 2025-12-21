<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-header-content">
            <div class="logo-container">
                <img src="../../assets/logo/site_2.png" alt="SITE Logo">
                <h4>Society of Information Technology Enthusiasts</h4>
            </div>
            <button class="btn-close-sidebar" id="closeSidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($site_active === 'dashboard') ? 'active' : ''; ?>" href="site_dashboard.php">
                    <i class="bi bi-house-door"></i>
                    <span>Home</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($site_active === 'announcement') ? 'active' : ''; ?>" href="site_announcement.php">
                    <i class="bi bi-megaphone"></i>
                    <span>Announcement</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($site_active === 'event') ? 'active' : ''; ?>" href="site_event.php">
                    <i class="bi bi-calendar-event"></i>
                    <span>Event</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($site_active === 'service') ? 'active' : ''; ?>" href="site_service.php">
                    <i class="bi bi-wrench-adjustable"></i>
                    <span>Services</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($site_active === 'penalties') ? 'active' : ''; ?>" href="site_penalties.php">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>Penalties</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($site_active === 'chat') ? 'active' : ''; ?>" href="site_chat.php">
                    <i class="bi bi-chat-dots"></i>
                    <span>Chat</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($site_active === 'report') ? 'active' : ''; ?>" href="site_report.php">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Reports</span>
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
