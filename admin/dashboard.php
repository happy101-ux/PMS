<?php
session_start();
include '../components/navbar.php';

// Check if user is logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

// Include required files
require_once '../config/database.php';
require_once '../RoleManager.php';

// Initialize RoleManager with user
$officerid = $_SESSION['officerid'];
$roleManager = new RoleManager($pdo, $officerid);

// Get user information and dashboard data
$userInfo = $roleManager->getUserInfo();
$dashboardData = $roleManager->getDashboardData();
$dashboardActions = $roleManager->getDashboardActions();
$isAdmin = $roleManager->isAdmin();
$userRole = $roleManager->getUserRole();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Police Management System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .top-navbar {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .nav-item {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 6px;
            padding: 6px 12px;
            margin: 3px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 0.85rem;
            font-weight: 400;
            white-space: nowrap;
            cursor: pointer;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-1px);
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }
        
        .nav-item.active {
            background: rgba(255, 255, 255, 0.4);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }
        
        .dashboard-overview {
            background: white;
            border-radius: 8px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .overview-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .overview-body {
            padding: 20px;
            text-align: center;
        }
        
        .modal-content {
            border: none;
            border-radius: 0;
            box-shadow: 0 0 0;
            height: calc(100vh - 80px);
            margin: 0;
            position: fixed;
            top: 80px;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1050;
            background: white;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 0;
            flex-shrink: 0;
            padding: 8px 20px;
        }
        
        .modal-body {
            padding: 0;
            height: calc(100% - 50px);
            overflow: hidden;
            position: relative;
        }
        
        .modal-footer {
            border-top: 1px solid #dee2e6;
            padding: 6px 20px;
            flex-shrink: 0;
        }
        
        .component-frame {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .loading-content {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: #f8f9fa;
        }
        
        .role-badge {
            font-size: 0.7rem;
            margin-left: 3px;
        }
        
        /* Step Form Styles */
        .step-form {
            padding: 20px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .step-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .step-progress {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step-indicator {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .step-indicator.active {
            background: #007bff;
            color: white;
        }
        
        .step-indicator.completed {
            background: #28a745;
            color: white;
        }
        
        .step-content {
            flex: 1;
            overflow-y: auto;
        }
        
        .step-form-group {
            margin-bottom: 20px;
        }
        
        .step-form-group label {
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
        }
        
        .step-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal.fade .modal-dialog {
            animation: slideUp 0.4s ease-out;
        }
        
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        /* Compact navbar adjustments */
        .navbar-brand {
            font-size: 1rem;
        }
        
        .navbar-nav .nav-link {
            font-size: 0.8rem;
            padding: 0.4rem 0.6rem;
        }
        
        .dropdown-menu {
            font-size: 0.8rem;
        }
        
        .badge {
            font-size: 0.6rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid mt-2">
        <!-- Compact Quick Access Navigation Bar -->
        <div class="top-navbar">
            <div class="row">
                <div class="col-12 mb-1">
                    <h6 class="text-white mb-2"><i class="bi bi-lightning me-1"></i>Quick Access</h6>
                </div>
                <div class="col-12">
                    <?php foreach($dashboardActions as $actionGroup): ?>
                        <?php foreach($actionGroup as $action): ?>
                        <button onclick="openModal('<?php echo htmlspecialchars($action['title']); ?>', '<?php echo htmlspecialchars($action['url']); ?>', '<?php echo htmlspecialchars($action['icon']); ?>')" class="nav-item" title="<?php echo htmlspecialchars($action['title']); ?>">
                            <i class="bi <?php echo htmlspecialchars($action['icon']); ?>"></i>
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($action['title']); ?></span>
                            <?php if (!empty($action['badge'])): ?>
                                <span class="badge <?php echo htmlspecialchars($action['badge_class']); ?> role-badge">
                                    <?php echo htmlspecialchars($action['badge']); ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Dashboard Overview -->
        <div class="dashboard-overview">
            <div class="overview-header">
                <h5 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Dashboard Overview</h5>
            </div>
            <div class="overview-body">
                <div class="row">
                    <div class="col-12 mb-4">
                        <h6 class="text-muted">Welcome to the Police Management System</h6>
                        <p class="text-muted">Click any button above to access system components</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Full-Screen Modal for Component Content -->
    <div class="modal fade" id="componentModal" tabindex="-1" aria-labelledby="componentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="componentModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Component Preview
                    </h5>
                    <button type="button" class="btn-close btn-close-white" onclick="closeModal()" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Component content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="bi bi-x me-1"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" id="refreshComponentBtn" onclick="refreshComponent()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Modal-based component loading system
        document.addEventListener('DOMContentLoaded', function() {
            initializeNavigation();
        });

        function initializeNavigation() {
            // Add click handlers to all navigation items
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Remove active class from all items
                    navItems.forEach(i => i.classList.remove('active'));
                    // Add active class to clicked item
                    this.classList.add('active');
                });
            });
        }

        // Open modal with actual component page
        function openModal(title, url, icon) {
            const modalElement = document.getElementById('componentModal');
            const modal = new bootstrap.Modal(modalElement);
            const modalTitle = document.getElementById('componentModalLabel');
            const modalBody = document.getElementById('modalBody');
            const refreshBtn = document.getElementById('refreshComponentBtn');
            
            // Set modal title
            modalTitle.innerHTML = `<i class="bi ${icon} me-2"></i>${title}`;
            
            // Check if this is a complaint form and needs step interface
            if (title.toLowerCase().includes('complaint') || title.toLowerCase().includes('new case') || title.toLowerCase().includes('add case')) {
                loadStepForm(title, modalBody);
            } else {
                // Show loading in modal body
                modalBody.innerHTML = `
                    <div class="loading-content">
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-3" role="status"></div>
                            <p>Loading ${title}...</p>
                        </div>
                    </div>
                `;
                
                // Load the actual component page
                loadComponentPage(url, modalBody);
            }
            
            // Store the current URL for refresh
            refreshBtn.setAttribute('data-url', url);
            refreshBtn.setAttribute('data-title', title);
            refreshBtn.setAttribute('data-icon', icon);
            
            // Show modal
            modal.show();
        }

        // Create step form for complaint/case forms
        function loadStepForm(title, container) {
            container.innerHTML = `
                <div class="step-form">
                    <div class="step-header">
                        <h5>${title}</h5>
                        <p class="text-muted">Follow the steps to complete your submission</p>
                    </div>
                    
                    <div class="step-progress">
                        <div class="step-indicator active" data-step="1">1</div>
                        <div class="step-indicator" data-step="2">2</div>
                        <div class="step-indicator" data-step="3">3</div>
                        <div class="step-indicator" data-step="4">4</div>
                    </div>
                    
                    <div class="step-content" id="stepContent">
                        <!-- Step content will be loaded here -->
                    </div>
                    
                    <div class="step-navigation">
                        <button type="button" class="btn btn-secondary" id="prevBtn" onclick="previousStep()" style="display: none;">
                            <i class="bi bi-arrow-left me-1"></i>Previous
                        </button>
                        <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextStep()">
                            Next<i class="bi bi-arrow-right ms-1"></i>
                        </button>
                        <button type="button" class="btn btn-success" id="submitBtn" onclick="submitForm()" style="display: none;">
                            <i class="bi bi-check me-1"></i>Submit
                        </button>
                    </div>
                </div>
            `;
            
            // Load first step
            showStep(1);
        }

        // Show specific step
        function showStep(step) {
            const stepContent = document.getElementById('stepContent');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');
            
            // Update step indicators
            document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
                indicator.classList.remove('active', 'completed');
                if (index + 1 < step) {
                    indicator.classList.add('completed');
                } else if (index + 1 === step) {
                    indicator.classList.add('active');
                }
            });
            
            // Load step content
            const stepData = getStepContent(step);
            stepContent.innerHTML = stepData;
            
            // Update navigation buttons
            prevBtn.style.display = step > 1 ? 'block' : 'none';
            nextBtn.style.display = step < 4 ? 'block' : 'none';
            submitBtn.style.display = step === 4 ? 'block' : 'none';
        }

        // Get content for each step
        function getStepContent(step) {
            switch(step) {
                case 1:
                    return `
                        <h6 class="mb-3">Basic Information</h6>
                        <div class="step-form-group">
                            <label for="complainantName">Complainant Name *</label>
                            <input type="text" class="form-control" id="complainantName" required>
                        </div>
                        <div class="step-form-group">
                            <label for="complainantPhone">Phone Number *</label>
                            <input type="tel" class="form-control" id="complainantPhone" required>
                        </div>
                        <div class="step-form-group">
                            <label for="complainantEmail">Email Address</label>
                            <input type="email" class="form-control" id="complainantEmail">
                        </div>
                        <div class="step-form-group">
                            <label for="incidentDate">Date of Incident *</label>
                            <input type="date" class="form-control" id="incidentDate" required>
                        </div>
                    `;
                case 2:
                    return `
                        <h6 class="mb-3">Location & Type</h6>
                        <div class="step-form-group">
                            <label for="incidentLocation">Location of Incident *</label>
                            <input type="text" class="form-control" id="incidentLocation" placeholder="Enter full address" required>
                        </div>
                        <div class="step-form-group">
                            <label for="incidentType">Type of Incident *</label>
                            <select class="form-control" id="incidentType" required>
                                <option value="">Select incident type</option>
                                <option value="theft">Theft</option>
                                <option value="assault">Assault</option>
                                <option value="vandalism">Vandalism</option>
                                <option value="fraud">Fraud</option>
                                <option value="traffic">Traffic Violation</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="step-form-group">
                            <label for="incidentPriority">Priority Level *</label>
                            <select class="form-control" id="incidentPriority" required>
                                <option value="">Select priority</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    `;
                case 3:
                    return `
                        <h6 class="mb-3">Incident Description</h6>
                        <div class="step-form-group">
                            <label for="incidentDescription">Detailed Description *</label>
                            <textarea class="form-control" id="incidentDescription" rows="5" placeholder="Provide a detailed description of what happened..." required></textarea>
                        </div>
                        <div class="step-form-group">
                            <label for="witnesses">Witnesses (if any)</label>
                            <textarea class="form-control" id="witnesses" rows="3" placeholder="List any witnesses with their contact information"></textarea>
                        </div>
                        <div class="step-form-group">
                            <label for="evidence">Evidence Available</label>
                            <textarea class="form-control" id="evidence" rows="3" placeholder="Describe any physical evidence, photos, videos, etc."></textarea>
                        </div>
                    `;
                case 4:
                    return `
                        <h6 class="mb-3">Review & Submit</h6>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Please review all information before submitting your complaint.
                        </div>
                        <div class="step-form-group">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Summary of Your Complaint</h6>
                                    <div id="formSummary">
                                        <!-- Summary will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="step-form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="acceptTerms" required>
                                <label class="form-check-label" for="acceptTerms">
                                    I confirm that all information provided is accurate and complete *
                                </label>
                            </div>
                        </div>
                        <div class="step-form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="acknowledgeProcess">
                                <label class="form-check-label" for="acknowledgeProcess">
                                    I understand the complaint process and expected timeline
                                </label>
                            </div>
                        </div>
                    `;
                default:
                    return '<p>Invalid step</p>';
            }
        }

        // Navigation functions
        let currentStep = 1;
        
        function nextStep() {
            if (validateCurrentStep()) {
                if (currentStep < 4) {
                    currentStep++;
                    showStep(currentStep);
                    if (currentStep === 4) {
                        populateSummary();
                    }
                }
            }
        }
        
        function previousStep() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        }
        
        function validateCurrentStep() {
            // Basic validation - in real implementation, you'd validate each field
            const requiredFields = document.querySelectorAll(`#stepContent [required]`);
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            return isValid;
        }
        
        function populateSummary() {
            const summary = document.getElementById('formSummary');
            summary.innerHTML = `
                <p><strong>Complainant:</strong> ${document.getElementById('complainantName').value || 'Not provided'}</p>
                <p><strong>Phone:</strong> ${document.getElementById('complainantPhone').value || 'Not provided'}</p>
                <p><strong>Email:</strong> ${document.getElementById('complainantEmail').value || 'Not provided'}</p>
                <p><strong>Date:</strong> ${document.getElementById('incidentDate').value || 'Not provided'}</p>
                <p><strong>Location:</strong> ${document.getElementById('incidentLocation').value || 'Not provided'}</p>
                <p><strong>Type:</strong> ${document.getElementById('incidentType').value || 'Not provided'}</p>
                <p><strong>Priority:</strong> ${document.getElementById('incidentPriority').value || 'Not provided'}</p>
            `;
        }
        
        function submitForm() {
            if (document.getElementById('acceptTerms').checked) {
                alert('Form submitted successfully! Your complaint has been registered.');
                closeModal();
            } else {
                alert('Please accept the terms and conditions to proceed.');
            }
        }

        // Load component page content (for non-step forms)
        function loadComponentPage(url, container) {
            // Show loading
            container.innerHTML = `
                <div class="loading-content">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p>Loading component...</p>
                    </div>
                </div>
            `;
            
            // Create iframe to load the actual component
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.className = 'component-frame';
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = 'none';
            
            iframe.onload = function() {
                // Hide loading once iframe loads
                console.log('Component loaded successfully');
            };
            
            iframe.onerror = function() {
                // Show error if iframe fails to load
                container.innerHTML = `
                    <div class="loading-content">
                        <div class="text-center">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                            <p class="mt-3">Failed to load component</p>
                            <button class="btn btn-primary" onclick="refreshComponent()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Retry
                            </button>
                        </div>
                    </div>
                `;
            };
            
            // Replace loading with iframe
            container.innerHTML = '';
            container.appendChild(iframe);
        }

        // Refresh component function
        function refreshComponent() {
            const refreshBtn = document.getElementById('refreshComponentBtn');
            const url = refreshBtn.getAttribute('data-url');
            const title = refreshBtn.getAttribute('data-title');
            const icon = refreshBtn.getAttribute('data-icon');
            const modalBody = document.getElementById('modalBody');
            
            if (url) {
                loadComponentPage(url, modalBody);
            }
        }

        // Close modal function
        function closeModal() {
            const modalElement = document.getElementById('componentModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
            
            // Clear active states when modal closes
            setTimeout(() => {
                document.querySelectorAll('.nav-item').forEach(btn => btn.classList.remove('active'));
                currentStep = 1; // Reset step counter
            }, 300);
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // ESC key to clear active state and close modal
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>