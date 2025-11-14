<?php
/**
 * Header Component for Police Management System
 * Reusable HTML header with meta tags, styles, and common elements
 */

require_once __DIR__ . '/../core/AuthManager.php';

class HeaderComponent {
    private $title;
    private $description;
    private $keywords;
    private $additionalCSS;
    private $additionalJS;
    private $pageSpecificJS;
    private $includeBootstrap;
    private $includeBootstrapIcons;
    private $includeCustomCSS;
    
    public function __construct($title = 'Police Management System', $options = []) {
        $this->title = $title;
        $this->description = $options['description'] ?? 'Comprehensive police management and reporting system';
        $this->keywords = $options['keywords'] ?? 'police, management, system, cases, officers, reports';
        $this->additionalCSS = $options['additionalCSS'] ?? [];
        $this->additionalJS = $options['additionalJS'] ?? [];
        $this->pageSpecificJS = $options['pageSpecificJS'] ?? '';
        $this->includeBootstrap = $options['includeBootstrap'] ?? true;
        $this->includeBootstrapIcons = $options['includeBootstrapIcons'] ?? true;
        $this->includeCustomCSS = $options['includeCustomCSS'] ?? true;
    }
    
    /**
     * Render the complete header
     */
    public function render() {
        $this->renderDOCTYPE();
        $this->renderHTMLStart();
        $this->renderHead();
        $this->renderBodyStart();
        $this->renderNavigation();
        $this->renderAlertMessages();
        $this->renderPageHeader();
    }
    
    /**
     * Render DOCTYPE
     */
    private function renderDOCTYPE() {
        echo '<!DOCTYPE html>' . PHP_EOL;
    }
    
    /**
     * Render HTML start tag
     */
    private function renderHTMLStart() {
        echo '<html lang="en">' . PHP_EOL;
    }
    
    /**
     * Render head section
     */
    private function renderHead() {
        echo '<head>' . PHP_EOL;
        $this->renderMetaTags();
        $this->renderTitle();
        $this->renderFavicon();
        $this->renderStylesheets();
        $this->renderAdditionalHeadContent();
        echo '</head>' . PHP_EOL;
    }
    
    /**
     * Render meta tags
     */
    private function renderMetaTags() {
        echo '<meta charset="UTF-8">' . PHP_EOL;
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . PHP_EOL;
        echo '<meta name="description" content="' . htmlspecialchars($this->description) . '">' . PHP_EOL;
        echo '<meta name="keywords" content="' . htmlspecialchars($this->keywords) . '">' . PHP_EOL;
        echo '<meta name="author" content="Police Management System">' . PHP_EOL;
        echo '<meta name="robots" content="noindex, nofollow">' . PHP_EOL; // Security: Prevent indexing
        
        // Security headers
        echo '<meta http-equiv="X-UA-Compatible" content="IE=edge">' . PHP_EOL;
        echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net; style-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net; font-src \'self\' cdn.jsdelivr.net;">' . PHP_EOL;
    }
    
    /**
     * Render title
     */
    private function renderTitle() {
        echo '<title>' . htmlspecialchars($this->title) . '</title>' . PHP_EOL;
    }
    
    /**
     * Render favicon
     */
    private function renderFavicon() {
        echo '<link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">' . PHP_EOL;
        echo '<link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">' . PHP_EOL;
    }
    
    /**
     * Render stylesheets
     */
    private function renderStylesheets() {
        // Bootstrap CSS
        if ($this->includeBootstrap) {
            echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">' . PHP_EOL;
        }
        
        // Bootstrap Icons
        if ($this->includeBootstrapIcons) {
            echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">' . PHP_EOL;
        }
        
        // Custom CSS
        if ($this->includeCustomCSS) {
            echo '<link href="/assets/css/styles.css" rel="stylesheet">' . PHP_EOL;
        }
        
        // Additional CSS files
        foreach ($this->additionalCSS as $css) {
            echo '<link href="' . htmlspecialchars($css) . '" rel="stylesheet">' . PHP_EOL;
        }
        
        // Inline CSS for common styles
        $this->renderInlineCSS();
    }
    
    /**
     * Render inline CSS
     */
    private function renderInlineCSS() {
        echo '<style>' . PHP_EOL;
        echo '
        /* Base styles */
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            line-height: 1.6;
        }
        
        /* Page wrapper */
        .page-wrapper {
            margin-top: 76px; /* Account for fixed navbar */
            min-height: calc(100vh - 76px);
            padding: 20px;
        }
        
        /* Card styling */
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            border-radius: 8px 8px 0 0 !important;
        }
        
        /* Button styling */
        .btn {
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        /* Form styling */
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        /* Table styling */
        .table {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: var(--dark-color);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
        
        /* Alert styling */
        .alert {
            border: none;
            border-radius: 8px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #155724;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #721c24;
        }
        
        .alert-warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: #856404;
        }
        
        .alert-info {
            background-color: rgba(23, 162, 184, 0.1);
            color: #0c5460;
        }
        
        /* Loading spinner */
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
        
        /* Utility classes */
        .text-truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .shadow-sm {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
        }
        
        .shadow {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }
        
        .shadow-lg {
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175) !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .page-wrapper {
                padding: 10px;
                margin-top: 70px;
            }
            
            .card {
                margin-bottom: 15px;
            }
            
            .table-responsive {
                border-radius: 8px;
            }
        }
        ';
        echo '</style>' . PHP_EOL;
    }
    
    /**
     * Render additional head content
     */
    private function renderAdditionalHeadContent() {
        // Additional JS files
        foreach ($this->additionalJS as $js) {
            echo '<script src="' . htmlspecialchars($js) . '"></script>' . PHP_EOL;
        }
        
        // Page specific JavaScript
        if (!empty($this->pageSpecificJS)) {
            echo '<script>' . PHP_EOL;
            echo $this->pageSpecificJS . PHP_EOL;
            echo '</script>' . PHP_EOL;
        }
    }
    
    /**
     * Render body start tag
     */
    private function renderBodyStart() {
        echo '<body>' . PHP_EOL;
    }
    
    /**
     * Render navigation
     */
    private function renderNavigation() {
        require_once __DIR__ . '/NavigationComponent.php';
        renderNavigation();
    }
    
    /**
     * Render alert messages
     */
    private function renderAlertMessages() {
        // Display session messages
        if (isset($_SESSION['success'])) {
            echo '<div class="container-fluid mt-3">' . PHP_EOL;
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . PHP_EOL;
            echo '<i class="bi bi-check-circle me-2"></i>' . htmlspecialchars($_SESSION['success']) . PHP_EOL;
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            unset($_SESSION['success']);
        }
        
        if (isset($_SESSION['error'])) {
            echo '<div class="container-fluid mt-3">' . PHP_EOL;
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . PHP_EOL;
            echo '<i class="bi bi-exclamation-triangle me-2"></i>' . htmlspecialchars($_SESSION['error']) . PHP_EOL;
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            unset($_SESSION['error']);
        }
        
        if (isset($_SESSION['warning'])) {
            echo '<div class="container-fluid mt-3">' . PHP_EOL;
            echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">' . PHP_EOL;
            echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>' . htmlspecialchars($_SESSION['warning']) . PHP_EOL;
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            unset($_SESSION['warning']);
        }
        
        if (isset($_SESSION['info'])) {
            echo '<div class="container-fluid mt-3">' . PHP_EOL;
            echo '<div class="alert alert-info alert-dismissible fade show" role="alert">' . PHP_EOL;
            echo '<i class="bi bi-info-circle me-2"></i>' . htmlspecialchars($_SESSION['info']) . PHP_EOL;
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            unset($_SESSION['info']);
        }
    }
    
    /**
     * Render page header section
     */
    private function renderPageHeader() {
        // This can be overridden by child classes or called explicitly
        // Default implementation shows current page title based on URL
        $currentPage = basename($_SERVER['PHP_SELF'], '.php');
        $pageTitle = ucwords(str_replace('_', ' ', $currentPage));
        
        if ($currentPage !== 'index' && $currentPage !== 'login') {
            echo '<div class="container-fluid">' . PHP_EOL;
            echo '<div class="row">' . PHP_EOL;
            echo '<div class="col-12">' . PHP_EOL;
            echo '<div class="page-header mb-4">' . PHP_EOL;
            echo '<h1 class="page-title"><i class="bi bi-file-earmark-text me-2"></i>' . htmlspecialchars($pageTitle) . '</h1>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
        }
    }
    
    /**
     * Add custom CSS file
     */
    public function addCSS($cssFile) {
        $this->additionalCSS[] = $cssFile;
    }
    
    /**
     * Add custom JS file
     */
    public function addJS($jsFile) {
        $this->additionalJS[] = $jsFile;
    }
    
    /**
     * Set page-specific JavaScript
     */
    public function setPageJS($jsCode) {
        $this->pageSpecificJS = $jsCode;
    }
    
    /**
     * Disable Bootstrap
     */
    public function disableBootstrap() {
        $this->includeBootstrap = false;
    }
    
    /**
     * Disable Bootstrap Icons
     */
    public function disableBootstrapIcons() {
        $this->includeBootstrapIcons = false;
    }
    
    /**
     * Disable custom CSS
     */
    public function disableCustomCSS() {
        $this->includeCustomCSS = false;
    }
}

// Convenience function
function renderHeader($title = 'Police Management System', $options = []) {
    $header = new HeaderComponent($title, $options);
    $header->render();
}
?>