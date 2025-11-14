<?php
// Include header HTML (meta tags, CSS, etc.)


// Database connection
include 'config/database.php';
//component link
//include 'components/styles.php'
 
?>

<head>
    <style>
        


        body {
            background: linear-gradient(to right, #0d6efd, #198754);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .welcome-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(8px);
            text-align: center;
            box-shadow: 0px 5px 20px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
        }
        .welcome-card h1 {
            font-weight: bold;
            margin-bottom: 15px;
        }
        .welcome-card p {
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        .btn-custom {
            width: 45%;
            margin: 5px;
            padding: 10px;
            font-size: 1rem;
            border-radius: 8px;
        }

        
/* Primary color style */
.btn-primary.btn-custom {
    background-color: #007bff; /* Blue shade */
    color: #fff;
    box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
}

.btn-primary.btn-custom:hover {
    background-color: #0056b3; /* Darker blue on hover */
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 123, 255, 0.4);
}

/* Optional active/click effect */
.btn-primary.btn-custom:active {
    transform: translateY(0);
    box-shadow: 0 3px 6px rgba(0, 123, 255, 0.3);
}

    </style>
</head>
<body>
    <div class="welcome-card">
        <h2>🚔</h2>
        <h1>Police Management System </h1>
        <p>Efficiently manage resources,cases, staff,and reports with ease.</p>
        
        <a href="login.php" class="btn btn-primary btn-custom"> Bwana Please Login</a>
       <!-- <a href="register.php" class="btn btn-success btn-custom">Register</a>-->
    </div>
</body>
</html>
