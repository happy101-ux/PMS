<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - PMS</title>

  <!-- Bootstrap CSS -->
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/bootstrap-icons.css" rel="stylesheet">

  <!-- Sidebar Styles -->
  <?php include '../components/styles-sidebar.php'; ?>

  <style>
    /* Enhanced Sidebar Styles */
    #sidebar {
      width: 280px;
      transition: width 0.3s;
    }
    #sidebar.collapsed {
      width: 70px;
    }

    .main-content {
      margin-left: 290px;
      margin-top: 60px;
      transition: margin-left 0.3s;
    }
    #sidebar.collapsed ~ .main-content {
      margin-left: 80px;
    }

    /* Sidebar Sections */
    .sidebar-section {
      margin-bottom: 1.5rem;
    }
    .sidebar-section-title {
      color: #adb5bd;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 0.75rem 1rem 0.5rem;
      margin: 0;
      border-bottom: 1px solid #495057;
    }
    #sidebar.collapsed .sidebar-section-title {
      display: none;
    }

    /* Enhanced Navigation Items */
    .nav-item {
      margin-bottom: 2px;
    }
    .nav-link {
      padding: 0.75rem 1rem;
      border-radius: 6px;
      transition: all 0.3s ease;
      position: relative;
    }
    .nav-link:hover {
      background-color: #495057;
      transform: translateX(5px);
    }
    .nav-link.active {
      background-color: #0d6efd;
      color: white !important;
    }
    
    /* Icon spacing */
    .nav-link i {
      margin-right: 0.5rem;
      width: 20px;
      text-align: center;
    }
    #sidebar.collapsed .nav-link span {
      display: none;
    }

    /* Dropdown Menu */
    .dropdown-menu {
      background-color: #343a40;
      border: none;
      border-radius: 6px;
      box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    .dropdown-item {
      color: #fff;
      padding: 0.5rem 1rem;
      transition: all 0.3s ease;
    }
    .dropdown-item:hover {
      background-color: #495057;
      color: #fff;
      transform: translateX(5px);
    }

    /* Responsive Design */
    @media (max-width: 991px) {
      #sidebar {
        transform: translateX(-100%);
        z-index: 1050;
      }
      #sidebar.show {
        transform: translateX(0);
      }
      .main-content {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div id="sidebar" class="bg-dark text-white vh-100 position-fixed rounded-end shadow" 
       style="top:56px; left:0; overflow-y:auto; z-index: 1000;">
   
    <div class="p-0">
      <!-- Toggle Button -->
      <div class="d-flex justify-content-end p-2 border-bottom border-secondary">
        <button id="sidebarToggle" class="btn btn-outline-light btn-sm">
          <i class="bi bi-list"></i>
        </button>
      </div>

      <!-- Navigation Sections -->
      <ul class="nav flex-column p-2">
        
        <!-- Main Dashboard Section -->
        <li class="nav-item sidebar-section">
          <p class="sidebar-section-title">Main</p>
          <a href="../admin/enhanced_dashboard.php" class="nav-link text-white">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
          </a>
        </li>

        <!-- Case Management Section -->
        <li class="nav-item sidebar-section">
          <p class="sidebar-section-title">Case Management</p>
          <a href="../components/new_case.php" class="nav-link text-white">
            <i class="bi bi-file-earmark-plus"></i>
            <span>New Complaint</span>
          </a>
          <a href="../components/view_complaint.php" class="nav-link text-white">
            <i class="bi bi-journal-text"></i>
            <span>View Complaints</span>
          </a>
        </li>

        <!-- Department Dashboards -->
        <li class="nav-item sidebar-section">
          <p class="sidebar-section-title">Departments</p>
          <a href="../components/cid_dashboard.php" class="nav-link text-white">
            <i class="bi bi-shield-check"></i>
            <span>CID Dashboard</span>
          </a>
          <a href="../components/oic_dashboard.php" class="nav-link text-white">
            <i class="bi bi-card-checklist"></i>
            <span>OIC Dashboard</span>
          </a>
          <a href="../components/nco_dashboard.php" class="nav-link text-white">
            <i class="bi bi-tools"></i>
            <span>NCO Dashboard</span>
          </a>
        </li>

        <!-- Personnel Management -->
        <li class="nav-item sidebar-section">
          <p class="sidebar-section-title">Personnel</p>
          <a href="../components/add_staff.php" class="nav-link text-white">
            <i class="bi bi-person-plus"></i>
            <span>Add Officer</span>
          </a>
          <a href="../components/view_officer.php" class="nav-link text-white">
            <i class="bi bi-people"></i>
            <span>View Officers</span>
          </a>
        </li>

        <!-- Operations -->
        <li class="nav-item sidebar-section">
          <p class="sidebar-section-title">Operations</p>
          <a href="#" class="nav-link text-white">
            <i class="bi bi-list-task"></i>
            <span>My Duties</span>
          </a>
          <a href="../components/duty_management.php" class="nav-link text-white">
            <i class="bi bi-calendar-check"></i>
            <span>Duty Management</span>
          </a>
          <a href="../components/case_allocation.php" class="nav-link text-white">
            <i class="bi bi-arrow-left-right"></i>
            <span>Case Allocation</span>
          </a>
        </li>

        <!-- Investigation Tools -->
        <li class="nav-item sidebar-section">
          <p class="sidebar-section-title">Investigations</p>
          <a href="#" class="nav-link text-white">
            <i class="bi bi-search"></i>
            <span>My Investigations</span>
          </a>
          <a href="../components/cid_reports.php" class="nav-link text-white">
            <i class="bi bi-file-text"></i>
            <span>CID Reports</span>
          </a>
        </li>

        <!-- Resources -->
        <li class="nav-item sidebar-section">
          <p class="sidebar-section-title">Resources</p>
          <a href="../components/Manage_Resources.php" class="nav-link text-white">
            <i class="bi bi-box-seam"></i>
            <span>Manage Resources</span>
          </a>
          <a href="#" class="nav-link text-white">
            <i class="bi bi-tools"></i>
            <span>Request Resources</span>
          </a>
          <a href="../components/resource_request.php" class="nav-link text-white">
            <i class="bi bi-arrow-up-circle"></i>
            <span>Resource Requests</span>
          </a>
        </li>

        <!-- Admin Tools (Role-based) -->
        <?php if (isset($roleManager) && $roleManager->hasAccess($officerid ?? 0, 'Chief Inspector')): ?>
        <li class="nav-item sidebar-section">
          <p class="sidebar-section-title">Admin Tools</p>
          
          <!-- Generate Reports Dropdown -->
          <div class="nav-item dropdown">
            <a class="nav-link text-white dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-graph-up"></i>
              <span>Reports</span>
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="#">
                <i class="bi bi-file-earmark-text"></i> Generate Reports
              </a></li>
              <li><a class="dropdown-item" href="#">
                <i class="bi bi-bar-chart"></i> Analytics
              </a></li>
            </ul>
          </div>

          <!-- System Management -->
          <div class="nav-item dropdown">
            <a class="nav-link text-white dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-gear"></i>
              <span>System Management</span>
            </a>
            <ul class="dropdown-menu">
              <?php if (isset($roleManager) && $roleManager->hasAccess($officerid ?? 0, 'ADMIN')): ?>
              <li><a class="dropdown-item" href="../admin/user_management.php">
                <i class="bi bi-people-gear"></i> User Management
              </a></li>
              <li><a class="dropdown-item" href="#">
                <i class="bi bi-sliders"></i> System Settings
              </a></li>
              <?php endif; ?>
              <li><a class="dropdown-item" href="#">
                <i class="bi bi-shield-check"></i> Access Control
              </a></li>
            </ul>
          </div>
        </li>
        <?php endif; ?>

        <!-- User Account Section -->
        <li class="nav-item sidebar-section">
          <p class="sidebar-section-title">Account</p>
          <a href="../components/edit_profile.php" class="nav-link text-white">
            <i class="bi bi-person"></i>
            <span>Profile</span>
          </a>
          <a href="../login.php" class="nav-link text-white">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
          </a>
        </li>

      </ul>
    </div>
  </div>

  <!-- Main Content Placeholder -->
  <div id="mainContent" class="main-content">
    <!-- Page content goes here -->
  </div>

  <!-- Bootstrap JS -->
  <script src="../assets/js/bootstrap.bundle.min.js"></script>

  <!-- Enhanced Sidebar Toggle JS -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const sidebar = document.getElementById('sidebar');
      const toggleBtn = document.getElementById('sidebarToggle');
      const mainContent = document.querySelector('.main-content');

      // Toggle sidebar collapse
      toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        
        // Toggle icon
        const icon = toggleBtn.querySelector('i');
        if (sidebar.classList.contains('collapsed')) {
          icon.className = 'bi bi-list';
        } else {
          icon.className = 'bi bi-x-lg';
        }
      });

      // Handle responsive behavior
      function handleResize() {
        if (window.innerWidth <= 991) {
          sidebar.classList.remove('collapsed');
          sidebar.style.transform = 'translateX(-100%)';
          sidebar.classList.add('show');
        } else {
          sidebar.style.transform = 'translateX(0)';
          sidebar.classList.remove('show');
        }
      }

      // Initial responsive check
      handleResize();
      
      // Listen for window resize
      window.addEventListener('resize', handleResize);

      // Close sidebar when clicking outside on mobile
      document.addEventListener('click', function(event) {
        if (window.innerWidth <= 991 && 
            !sidebar.contains(event.target) && 
            !toggleBtn.contains(event.target) &&
            sidebar.classList.contains('show')) {
          sidebar.style.transform = 'translateX(-100%)';
          sidebar.classList.remove('show');
        }
      });

      // Set active nav item based on current page
      const currentPath = window.location.pathname;
      const navLinks = document.querySelectorAll('#sidebar .nav-link');
      
      navLinks.forEach(link => {
        if (link.getAttribute('href') && 
            currentPath.includes(link.getAttribute('href').replace('../', ''))) {
          link.classList.add('active');
        }
      });
    });
  </script>
</body>
</html>
