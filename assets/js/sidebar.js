// Enhanced Sidebar JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar functionality
    initializeSidebar();
    initializeNavigation();
    initializeDropdowns();
    initializeMobileMenu();
    
    // Set active navigation item based on current page
    setActiveNavigationItem();
});

/**
 * Initialize main sidebar functionality
 */
function initializeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    
    if (!sidebar || !toggleBtn) return;

    // Toggle sidebar collapse/expand
    toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        sidebar.classList.toggle('collapsed');
        
        // Update toggle button icon and text
        updateToggleButton(sidebar.classList.contains('collapsed'));
        
        // Save state to localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        
        // Emit custom event for other components
        window.dispatchEvent(new CustomEvent('sidebarToggle', {
            detail: { collapsed: sidebar.classList.contains('collapsed') }
        }));
    });

    // Restore sidebar state from localStorage
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') {
        sidebar.classList.add('collapsed');
        updateToggleButton(true);
    }
    
    // Initial button state update
    updateToggleButton(sidebar.classList.contains('collapsed'));
}

/**
 * Update toggle button appearance based on state
 */
function updateToggleButton(isCollapsed) {
    const toggleBtn = document.getElementById('sidebarToggle');
    const icon = toggleBtn.querySelector('i');
    
    if (isCollapsed) {
        icon.className = 'bi bi-list';
        toggleBtn.title = 'Expand Sidebar';
        toggleBtn.setAttribute('aria-expanded', 'false');
    } else {
        icon.className = 'bi bi-x-lg';
        toggleBtn.title = 'Collapse Sidebar';
        toggleBtn.setAttribute('aria-expanded', 'true');
    }
}

/**
 * Initialize navigation functionality
 */
function initializeNavigation() {
    const navLinks = document.querySelectorAll('#sidebar .nav-link');
    
    navLinks.forEach(link => {
        // Add click tracking
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            const isExternal = href && (href.startsWith('http') || href.startsWith('//'));
            const isDropdown = this.classList.contains('dropdown-toggle');
            
            // Don't handle external links or dropdown toggles
            if (isExternal || isDropdown) return;
            
            // Handle internal navigation
            if (href && href !== '#') {
                e.preventDefault();
                navigateToPage(href);
            }
        });
        
        // Add hover effects
        link.addEventListener('mouseenter', function() {
            if (this.querySelector('i')) {
                this.querySelector('i').style.transform = 'scale(1.1)';
            }
        });
        
        link.addEventListener('mouseleave', function() {
            if (this.querySelector('i')) {
                this.querySelector('i').style.transform = 'scale(1)';
            }
        });
    });
}

/**
 * Initialize dropdown functionality
 */
function initializeDropdowns() {
    const dropdownToggles = document.querySelectorAll('#sidebar .dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdown = this.parentElement;
            const isOpen = dropdown.classList.contains('show');
            
            // Close all other dropdowns
            closeAllDropdowns();
            
            // Toggle current dropdown
            if (!isOpen) {
                dropdown.classList.add('show');
                this.setAttribute('aria-expanded', 'true');
                
                // Add dropdown animation
                const menu = dropdown.querySelector('.dropdown-menu');
                if (menu) {
                    menu.style.opacity = '0';
                    menu.style.transform = 'translateY(-10px)';
                    menu.style.display = 'block';
                    
                    requestAnimationFrame(() => {
                        menu.style.transition = 'all 0.3s ease';
                        menu.style.opacity = '1';
                        menu.style.transform = 'translateY(0)';
                    });
                }
            } else {
                dropdown.classList.remove('show');
                this.setAttribute('aria-expanded', 'false');
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            closeAllDropdowns();
        }
    });
}

/**
 * Close all dropdowns
 */
function closeAllDropdowns() {
    const openDropdowns = document.querySelectorAll('#sidebar .dropdown.show');
    openDropdowns.forEach(dropdown => {
        dropdown.classList.remove('show');
        const toggle = dropdown.querySelector('.dropdown-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
}

/**
 * Initialize mobile menu functionality
 */
function initializeMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    
    if (!sidebar || !toggleBtn) return;

    function handleResize() {
        const isMobile = window.innerWidth <= 991;
        
        if (isMobile) {
            // On mobile, ensure sidebar is hidden by default
            sidebar.classList.remove('collapsed');
            sidebar.style.transform = 'translateX(-100%)';
            sidebar.classList.remove('show');
            toggleBtn.style.display = 'block';
        } else {
            // On desktop, restore normal functionality
            sidebar.style.transform = 'translateX(0)';
            toggleBtn.style.display = 'block';
        }
    }
    
    // Initial responsive check
    handleResize();
    
    // Listen for window resize
    window.addEventListener('resize', handleResize);
    
    // Mobile menu overlay
    createMobileOverlay();
}

/**
 * Create mobile menu overlay
 */
function createMobileOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'sidebarOverlay';
    overlay.className = 'position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50';
    overlay.style.zIndex = '1010';
    overlay.style.display = 'none';
    overlay.style.opacity = '0';
    overlay.style.transition = 'opacity 0.3s ease';
    
    document.body.appendChild(overlay);
    
    // Close sidebar when clicking overlay
    overlay.addEventListener('click', function() {
        closeMobileSidebar();
    });
    
    return overlay;
}

/**
 * Open mobile sidebar
 */
function openMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (window.innerWidth > 991) return;
    
    sidebar.style.transform = 'translateX(0)';
    sidebar.classList.add('show');
    
    if (overlay) {
        overlay.style.display = 'block';
        requestAnimationFrame(() => {
            overlay.style.opacity = '1';
        });
    }
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

/**
 * Close mobile sidebar
 */
function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (window.innerWidth > 991) return;
    
    sidebar.style.transform = 'translateX(-100%)';
    sidebar.classList.remove('show');
    
    if (overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 300);
    }
    
    // Restore body scroll
    document.body.style.overflow = '';
}

/**
 * Navigate to a page with loading state
 */
function navigateToPage(href) {
    const mainContent = document.getElementById('mainContent');
    
    if (!mainContent) return;
    
    // Add loading state
    mainContent.classList.add('loading');
    
    // If it's a local file, try to load via fetch
    if (!href.startsWith('http') && !href.startsWith('//')) {
        fetch(href)
            .then(response => {
                if (!response.ok) throw new Error('Page not found');
                return response.text();
            })
            .then(html => {
                // Insert content
                mainContent.innerHTML = html;
                
                // Update URL without page reload
                if (history.pushState) {
                    history.pushState(null, null, href);
                }
                
                // Trigger custom event for content load
                window.dispatchEvent(new CustomEvent('contentLoaded', {
                    detail: { url: href }
                }));
            })
            .catch(error => {
                console.error('Navigation error:', error);
                mainContent.innerHTML = `
                    <div class="alert alert-danger">
                        <h5>Error Loading Page</h5>
                        <p>Could not load the requested page: ${error.message}</p>
                        <a href="${href}" class="btn btn-primary" target="_blank">Open in New Tab</a>
                    </div>
                `;
            })
            .finally(() => {
                mainContent.classList.remove('loading');
            });
    } else {
        // External link - open in new tab
        window.open(href, '_blank', 'noopener,noreferrer');
    }
}

/**
 * Set active navigation item based on current page
 */
function setActiveNavigationItem() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('#sidebar .nav-link:not(.dropdown-toggle)');
    
    // Remove active class from all links
    navLinks.forEach(link => {
        link.classList.remove('active');
    });
    
    // Find matching link
    let activeLink = null;
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href !== '#') {
            // Convert relative paths for comparison
            const linkPath = new URL(href, window.location.origin).pathname;
            if (currentPath.includes(linkPath.replace('../', ''))) {
                activeLink = link;
            }
        }
    });
    
    // If no exact match, try partial matches
    if (!activeLink) {
        const pathSegments = currentPath.split('/');
        const currentPage = pathSegments[pathSegments.length - 1];
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && href.includes(currentPage)) {
                activeLink = link;
            }
        });
    }
    
    // Set active state
    if (activeLink) {
        activeLink.classList.add('active');
        
        // Expand parent dropdown if exists
        const dropdown = activeLink.closest('.dropdown');
        if (dropdown) {
            dropdown.classList.add('show');
            const toggle = dropdown.querySelector('.dropdown-toggle');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
        }
    }
}

/**
 * Utility function to show notifications
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = `
        top: 70px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    `;
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

/**
 * Keyboard navigation support
 */
document.addEventListener('keydown', function(e) {
    // ESC key to close mobile sidebar
    if (e.key === 'Escape' && window.innerWidth <= 991) {
        closeMobileSidebar();
        closeAllDropdowns();
    }
    
    // Ctrl/Cmd + B to toggle sidebar
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.toggle('collapsed');
            updateToggleButton(sidebar.classList.contains('collapsed'));
        }
    }
});

// Export functions for global access
window.Sidebar = {
    openMobile: openMobileSidebar,
    closeMobile: closeMobileSidebar,
    closeDropdowns: closeAllDropdowns,
    showNotification: showNotification,
    navigateToPage: navigateToPage
};

// Listen for custom events
window.addEventListener('openMobileSidebar', openMobileSidebar);
window.addEventListener('closeMobileSidebar', closeMobileSidebar);
