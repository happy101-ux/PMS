<?php
// Include header HTML (meta tags, CSS, etc.)


// Database connection
include 'config/database.php';
//component link
//include 'components/styles.php'
 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Police Management System - Modern Law Enforcement Solution</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #7e22ce 50%, #9333ea 75%, #3b82f6 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            color: #fff;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
            overflow-x: hidden;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        body::before {
            content: '';
            position: fixed;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(255, 255, 255, 0.06) 0%, transparent 50%);
            animation: float 25s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"><animate attributeName="cy" values="20;80;20" dur="10s" repeatCount="indefinite"/></circle><circle cx="80" cy="40" r="1.5" fill="rgba(255,255,255,0.1)"><animate attributeName="cy" values="40;10;40" dur="8s" repeatCount="indefinite"/></circle><circle cx="50" cy="60" r="1" fill="rgba(255,255,255,0.1)"><animate attributeName="cy" values="60;90;60" dur="12s" repeatCount="indefinite"/></circle></svg>');
            pointer-events: none;
            z-index: 0;
            opacity: 0.3;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(5deg); }
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            padding: 30px 0;
            text-align: center;
            animation: fadeInDown 0.8s ease-out;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 15px;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.3);
            animation: pulse 3s ease-in-out infinite, rotate 20s linear infinite;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logo-icon:hover {
            transform: scale(1.15) rotate(360deg);
            box-shadow: 0 12px 30px rgba(255, 255, 255, 0.4);
        }

        .logo-icon::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 20px;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shine 3s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .header h1 {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #e0e7ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 2px;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .header .tagline {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 10px;
            font-weight: 300;
            letter-spacing: 1px;
        }

        /* Main Content */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 40px 0;
            animation: fadeIn 1s ease-out 0.3s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Welcome Card */
        .welcome-card {
            background: rgba(255, 255, 255, 0.12);
            padding: 50px 40px;
            border-radius: 25px;
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .welcome-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .welcome-card h2 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: #fff;
            font-weight: 700;
        }

        .welcome-card p {
            font-size: 1.1rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 35px;
        }

        .btn-custom {
            display: inline-block;
            width: 100%;
            padding: 18px 40px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-align: center;
        }

        .btn-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .btn-custom:hover::before {
            left: 100%;
        }

.btn-primary.btn-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
            box-shadow: 
                0 6px 20px rgba(102, 126, 234, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-primary.btn-custom:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 
                0 10px 30px rgba(102, 126, 234, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        /* Features Section */
        .features-card {
            background: rgba(255, 255, 255, 0.12);
            padding: 50px 40px;
            border-radius: 25px;
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .features-card h2 {
            font-size: 2rem;
            margin-bottom: 30px;
            color: #fff;
            font-weight: 700;
            text-align: center;
        }

        .features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .feature-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
            animation: fadeIn 1s ease-out both;
        }

        .feature-item:nth-child(1) { animation-delay: 0.4s; }
        .feature-item:nth-child(2) { animation-delay: 0.5s; }
        .feature-item:nth-child(3) { animation-delay: 0.6s; }
        .feature-item:nth-child(4) { animation-delay: 0.7s; }

        .feature-item:hover {
            transform: translateY(-8px) scale(1.03);
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .feature-item:hover .feature-icon {
            transform: scale(1.2) rotate(10deg);
            animation: bounce 0.6s ease;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0) scale(1.2) rotate(10deg); }
            50% { transform: translateY(-10px) scale(1.2) rotate(10deg); }
        }

        .feature-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            display: block;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }

        .feature-item h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #fff;
            font-weight: 600;
        }

        .feature-item p {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.6;
        }

        /* Stats Section */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 40px 0;
            animation: fadeIn 1s ease-out 0.8s both;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.12);
            padding: 30px 20px;
            border-radius: 20px;
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.08) rotate(2deg);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #e0e7ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            display: inline-block;
            animation: countUp 1.5s ease-out;
            position: relative;
        }

        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.5);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .stat-label {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.9);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 30px 0;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            animation: fadeIn 1s ease-out 1s both;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .stats-section {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }

            .header .tagline {
                font-size: 1rem;
            }

            .logo-icon {
                width: 60px;
                height: 60px;
                font-size: 30px;
            }

            .welcome-card,
            .features-card {
                padding: 35px 25px;
            }

            .welcome-card h2,
            .features-card h2 {
                font-size: 1.5rem;
            }

            .stats-section {
                grid-template-columns: 1fr;
            }

            .stat-number {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .logo-section {
                flex-direction: column;
                gap: 15px;
            }

            .welcome-card,
            .features-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="logo-section">
                <div class="logo-icon">🚔</div>
                <div>
                    <h1>Police Management System</h1>
                    <p class="tagline">Modern Law Enforcement Solution</p>
                </div>
            </div>
        </header>

        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Availability</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">100%</div>
                <div class="stat-label">Secure</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">Real-time</div>
                <div class="stat-label">Updates</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">Easy</div>
                <div class="stat-label">Management</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Welcome Card -->
    <div class="welcome-card">
                <h2>Welcome to PMS</h2>
                <p>
                    A comprehensive platform designed to streamline police operations, 
                    enhance efficiency, and improve public safety management. Our system 
                    provides powerful tools for managing cases, personnel, resources, and 
                    generating detailed reports.
                </p>
                <a href="login.php" class="btn btn-primary btn-custom">Bwana Please Login</a>
            </div>

            <!-- Features Card -->
            <div class="features-card">
                <h2>Key Features</h2>
                <div class="features-grid">
                    <div class="feature-item">
                        <span class="feature-icon">📋</span>
                        <h3>Case Management</h3>
                        <p>Track and manage all cases efficiently with detailed records and status updates.</p>
                    </div>
                    <div class="feature-item">
                        <span class="feature-icon">👥</span>
                        <h3>Staff Management</h3>
                        <p>Comprehensive personnel management with profiles, assignments, and performance tracking.</p>
                    </div>
                    <div class="feature-item">
                        <span class="feature-icon">📊</span>
                        <h3>Reports & Analytics</h3>
                        <p>Generate detailed reports and analytics to make data-driven decisions.</p>
                    </div>
                    <div class="feature-item">
                        <span class="feature-icon">🔒</span>
                        <h3>Secure Access</h3>
                        <p>Advanced security features to protect sensitive information and ensure data integrity.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> Police Management System. All rights reserved.</p>
            <p style="margin-top: 10px; font-size: 0.85rem;">Designed for efficient law enforcement operations</p>
        </footer>
    </div>

    <script>
        // Add interactive particle effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stat numbers
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach((stat, index) => {
                setTimeout(() => {
                    stat.style.animation = 'countUp 1s ease-out';
                }, index * 200);
            });

            // Add parallax effect to cards
            const cards = document.querySelectorAll('.welcome-card, .features-card, .stat-card');
            document.addEventListener('mousemove', (e) => {
                const mouseX = e.clientX / window.innerWidth;
                const mouseY = e.clientY / window.innerHeight;
                
                cards.forEach((card, index) => {
                    const speed = (index + 1) * 0.5;
                    const x = (mouseX - 0.5) * speed;
                    const y = (mouseY - 0.5) * speed;
                    card.style.transform = `translate(${x}px, ${y}px)`;
                });
            });

            // Add click ripple effect
            document.querySelectorAll('.stat-card, .feature-item, .btn-custom').forEach(element => {
                element.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => ripple.remove(), 600);
                });
            });

            // Add floating animation to feature items
            setInterval(() => {
                document.querySelectorAll('.feature-item').forEach((item, index) => {
                    setTimeout(() => {
                        item.style.animation = 'floatUp 3s ease-in-out infinite';
                    }, index * 200);
                });
            }, 1000);
        });

        // Add ripple effect styles
        const style = document.createElement('style');
        style.textContent = `
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: ripple-animation 0.6s ease-out;
                pointer-events: none;
            }
            
            @keyframes ripple-animation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            @keyframes floatUp {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-5px); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
