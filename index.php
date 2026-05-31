<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thesis Routing System - SMCC</title>
    <style>
        :root {
            --primary: #002366;
            --primary-light: #0a3885;
            --accent: #4a6fd1;
            --light: #f5f7fd;
            --dark: #333;
            --success: #28a745;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            min-height: 100vh;
            background-color: var(--light);
        }
        
        .left-panel {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            width: 40%;
            position: relative;
            overflow: hidden;
        }
        
        .left-panel::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -250px;
            left: -100px;
            z-index: 1;
        }
        
        .left-panel::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            top: -150px;
            right: -50px;
            z-index: 1;
        }
        
        .logo-container {
            position: relative;
            z-index: 2;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .left-panel img {
            max-width: 100px;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.2));
            margin-bottom: 20px;
        }
        
        .left-panel h1 {
            margin: 0;
            font-size: 28px;
            text-align: center;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
        }
        
        .left-panel p {
            margin: 10px 0;
            text-align: center;
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.5;
            max-width: 350px;
            position: relative;
            z-index: 2;
        }
        
        .right-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 60%;
            padding: 40px;
        }
        
        .system-title {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .system-title h2 {
            font-size: 28px;
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .system-title p {
            color: #666;
            font-size: 16px;
        }
        
        .role-buttons {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 400px;
            box-shadow: var(--shadow);
        }
        
        .role-button {
            display: flex;
            align-items: center;
            margin: 15px 0;
            padding: 16px 24px;
            background-color: white;
            color: var(--dark);
            text-align: left;
            text-decoration: none;
            border-radius: 8px;
            font-size: 17px;
            font-weight: 500;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .role-button:hover {
            background-color: var(--accent);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .role-icon {
            background-color: var(--light);
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--primary);
            font-size: 18px;
            transition: all 0.3s ease;
        }
        
        .role-button:hover .role-icon {
            background-color: white;
        }
        
        .role-text {
            flex-grow: 1;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #777;
            font-size: 14px;
        }
        
        @media (max-width: 900px) {
            body {
                flex-direction: column;
            }
            
            .left-panel, .right-panel {
                width: 100%;
                padding: 30px 20px;
            }
            
            .left-panel {
                padding-top: 40px;
                padding-bottom: 40px;
            }
        }
    </style>
</head>

<body>
    <div class="left-panel">
        <div class="logo-container">
            <img src="assets/logo.png" alt="SMCC Logo">
            <h1>Saint Michael College of Caraga</h1>
            <p>Brgy 4, Atupan St., Nasipit, Agusan del Norte</p>
        </div>
    </div>
    
    <div class="right-panel">
        <div class="system-title">
            <h2>THESIS ROUTING SYSTEM</h2>
            <p>Select your role to continue</p>
        </div>
        
        <div class="role-buttons">
            <a href="student/login.php" class="role-button">
                <div class="role-icon">👨‍🎓</div>
                <div class="role-text">Student</div>
                <div>→</div>
            </a>
            
            <a href="panel/login.php" class="role-button">
                <div class="role-icon">👥</div>
                <div class="role-text">Panel</div>
                <div>→</div>
            </a>
            
            <a href="adviser/login.php" class="role-button">
                <div class="role-icon">👨‍🏫</div>
                <div class="role-text">Adviser</div>
                <div>→</div>
            </a>
            
            <a href="admin/login.php" class="role-button">
                <div class="role-icon">⚙️</div> 
                <div class="role-text">Admin</div>
                <div>→</div>    
            </a>
        </div>
        <div class="footer">
            <p>© 2025 Saint Michael College of Caraga | All Rights Reserved</p>
            <p>Adviser: Rea Mie A. Omas-as</p>
            <p>Lacaran, Ell Jay</p>
            <p>CCIS</p>
        </div>
    </div>
</body>
</html>