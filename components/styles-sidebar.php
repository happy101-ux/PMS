<style>
        body {
            padding-top: 56px; /* Space for navbar */
            overflow-x: hidden;
        }

        /* Enhanced Sidebar */
        #sidebar {
            width: 280px;
            position: fixed;
            top: 56px; /* Height of navbar */
            left: 0;
            height: calc(100vh - 56px);
            background: linear-gradient(180deg, #212529 0%, #1a1d20 100%);
            padding: 0;
            border-top-right-radius: 0;
            border-bottom-right-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            z-index: 1020;
        }

        /* Sidebar Collapse State */
        #sidebar.collapsed {
            width: 70px;
        }

        #sidebar.collapsed .nav-link span {
            opacity: 0;
            visibility: hidden;
        }

        #sidebar.collapsed .sidebar-section-title {
            display: none;
        }

        /* Sidebar Toggle Button */
        .sidebar-toggle-container {
            padding: 1rem;
            border-bottom: 1px solid #495057;
            background-color: rgba(0, 0, 0, 0.1);
        }

        #sidebarToggle {
            background: none;
            border: 1px solid #6c757d;
            color: #adb5bd;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        #sidebarToggle:hover {
            background-color: #495057;
            border-color: #adb5bd;
            color: #fff;
        }

        /* Navigation Container */
        .sidebar-nav-container {
            height: calc(100% - 60px);
            overflow-y: auto;
            padding: 0.5rem 0;
        }

        .sidebar-nav-container::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-nav-container::-webkit-scrollbar-track {
            background: #343a40;
        }

        .sidebar-nav-container::-webkit-scrollbar-thumb {
            background: #6c757d;
            border-radius: 2px;
        }

        /* Sidebar Sections */
        .sidebar-section {
            margin-bottom: 1rem;
        }

        .sidebar-section-title {
            color: #adb5bd;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.5rem 1rem;
            margin: 0 0 0.5rem 0;
            border-bottom: 1px solid #495057;
            transition: all 0.3s ease;
        }

        /* Enhanced Navigation Links */
        .nav-link {
            color: #e9ecef !important;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
            position: relative;
        }

        .nav-link:hover {
            background: linear-gradient(90deg, #495057 0%, #6c757d 100%);
            color: #fff !important;
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .nav-link.active {
            background: linear-gradient(90deg, #0d6efd 0%, #0b5ed7 100%);
            color: #fff !important;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
        }

        .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
            font-size: 1rem;
        }

        .nav-link span {
            font-weight: 500;
            transition: all 0.3s ease;
        }

        /* Dropdown Styling */
        .dropdown {
            position: relative;
        }

        .dropdown-toggle::after {
            margin-left: auto;
            transition: transform 0.3s ease;
        }

        .dropdown.show .dropdown-toggle::after {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            background: linear-gradient(180deg, #343a40 0%, #2d3339 100%);
            border: none;
            border-radius: 8px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            margin-top: 0.5rem;
            min-width: 200px;
        }

        .dropdown-item {
            color: #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            border-radius: 4px;
            margin: 0.25rem 0.5rem;
        }

        .dropdown-item:hover {
            background: linear-gradient(90deg, #495057 0%, #6c757d 100%);
            color: #fff;
            transform: translateX(5px);
        }

        .dropdown-item i {
            margin-right: 0.5rem;
            width: 16px;
            text-align: center;
        }

        /* Main Content Area */
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            padding: 2rem;
            min-height: calc(100vh - 56px);
        }

        .main-content.full-width {
            margin-left: 70px;
        }

        /* Enhanced Navbar Styling */
        nav.navbar {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            z-index: 1030;
        }

        /* Mobile Responsiveness */
        @media (max-width: 991px) {
            #sidebar {
                transform: translateX(-100%);
                width: 280px;
                box-shadow: 4px 0 12px rgba(0, 0, 0, 0.15);
            }

            #sidebar.show {
                transform: translateX(0);
            }

            #sidebar.collapsed {
                transform: translateX(-100%);
                width: 280px;
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .main-content.full-width {
                margin-left: 0;
            }

            /* Mobile sidebar toggle */
            .sidebar-mobile-toggle {
                display: block !important;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1rem 0.5rem;
            }

            #sidebar {
                width: 100vw;
                top: 56px;
                height: calc(100vh - 56px);
            }
        }

        /* Animation for sidebar sections */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .nav-item {
            animation: slideInLeft 0.3s ease forwards;
        }

        /* Loading state for main content */
        .main-content.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .main-content.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Status indicators */
        .nav-link .badge {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            font-size: 0.65rem;
            padding: 0.25em 0.4em;
        }

        /* User info display */
        .user-info {
            padding: 1rem;
            background-color: rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin: 0.5rem;
            border: 1px solid #495057;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #0d6efd, #6610f2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
    </style>