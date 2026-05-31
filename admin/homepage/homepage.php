<?php
session_start(); // Start the session

// Check if the user is not logged in (session variable is not set)
if (!isset($_SESSION['admin_id'])) {
    // Redirect to login page if not logged in
    header("Location: ../../logout.php");
    exit; // Ensure no further code is executed
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Thesis Routing System</title>
    <script src="sidebar.js"></script>
    <style>
        :root {
--primary: #4366b3;
--primary-light: #0a3885;
--accent: #4a6fd1;
--light: #f5f7fd;
--dark: #333;
--success: #28a745;
--shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
--border: #e0e0e0;
--white: #ffffff;
--hover: #f5f7fd;
--active: #e5ebf8;
--text-light: #777777;
--radius: 8px;
}

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: var(--light);
        color: var(--dark);
        min-height: 100vh;
    }

    .container {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    .header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        color: white;
        padding: 1rem 2rem;
        box-shadow: var(--shadow);
    }

    .logo-container {
        display: flex;
        align-items: center;
    }

    .logo-container img {
        height: 50px;
        margin-right: 15px;
    }

    .logo {
        font-size: 1.5rem;
        font-weight: 600;
    }

    .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 2rem;
        background-color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        border-bottom: 1px solid var(--border);
    }

    .navigation {
        display: flex;
        align-items: center;
    }

    .homepage a {
        color: var(--accent);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
    }

    .homepage a:hover {
        color: var(--primary);
    }


    .user-info {
        display: flex;
        align-items: center;
    }

    .vl {
        border-left: 1px solid var(--border);
        height: 20px;
        margin: 0 10px;
    }

    .role {
        font-weight: 600;
        margin-right: 5px;
        color: var(--primary);
    }

    .content {
        flex: 1;
        padding: 2rem;
        position: relative;
        overflow: auto;
    }

    .user-name {
        color: var(--dark);
    }

    .main-content {
        display: flex;
        flex: 1;
    }

    .sidebar {
        width: 250px;
        background-color: white;
        padding: 1.5rem 0;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        border-right: 1px solid var(--border);
    }

    .menu-section {
        margin-bottom: 1.5rem;
    }

    .menu-title {
        font-weight: 600;
        color: var(--primary);
        padding: 0.5rem 1.5rem;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    .sidebar ul {
        list-style: none;
    }

    .sidebar li {
        margin-bottom: 0.25rem;
    }

    .sidebar a {
        display: block;
        padding: 0.75rem 1.5rem;
        color: var(--dark);
        text-decoration: none;
        transition: all 0.3s;
        border-left: 3px solid transparent;
    }

    .sidebar a:hover {
        background-color: var(--light);
        color: var(--accent);
        border-left: 3px solid var(--accent);
    }

    .logout {
        padding: 0 1.5rem;
        margin-top: auto;
        border-top: 1px solid var(--border);
        padding-top: 1rem;
    }

    .logout a {
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f0f0f0;
        color: #555;
        padding: 0.75rem;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
    }

    .logout a:hover {
        background-color: #e0e0e0;
        transform: translateY(-2px);
    }





    
    /* Responsive styles */
    @media (max-width: 768px) {
        .main-content {
            flex-direction: column;
        }
        
        .sidebar {
            width: 100%;
            padding: 1rem 0;
        }
        
        .top-bar {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .user-info {
            margin-top: 0.5rem;
        }
        
        .modal-layout {
            flex-direction: column;
        }
        
        .file-preview-section {
            border-right: none;
            border-bottom: 1px solid var(--border);
        }
    }

    /* Dropdown menu styling */
    .nav-menu {
        display: flex;
        flex-direction: column;
        padding: 1rem 0;
        gap: 4px;
    }

    .menu-item {
        display: flex;
        flex-direction: column;
    }

    .menu-header {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.2s ease;
        gap: 12px;
    }

    .icon {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
    }

    .icon svg {
        width: 18px;
        height: 18px;
        stroke: var(--primary);
    }

    .menu-header span {
        flex: 1;
        font-size: 14px;
        color: var(--dark);
    }

    .dropdown-icon {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s ease;
    }

    .dropdown-icon svg {
        width: 16px;
        height: 16px;
        stroke: #777777;
    }

    .expanded .dropdown-icon {
        transform: rotate(180deg);
    }

    .menu-header:hover {
        background-color: var(--light);
    }

    .dropdown-content {
        display: none;
        flex-direction: column;
        padding-left: 2.5rem;
    }

    .dropdown-content.show {
        display: flex;
    }

    .submenu-item {
        padding: 0.6rem 1rem;
        font-size: 13px;
        color: #777777;
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .submenu-item:hover {
        background-color: var(--light);
        color: var(--primary);
    }
    .welcome-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .welcome-card h1 {
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .welcome-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
    

    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo-container">
                <img src="../../assets/logo.png" alt="SMCC Logo">
                <div class="logo">Thesis Routing System</div>
            </div>
        </header>
        <div class="top-bar">
            <div class="homepage">
                <a href="homepage.php">Home Page</a>
            </div>
            <div class="user-info">
                <span class="role">Admin:</span>
                <span class="user-name"><?php echo isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Guest'; ?></span>
            </div>
        </div>
        <div class="main-content">
        <nav class="sidebar">
    <nav class="nav-menu">
        <!-- Title Proposal Section -->
        <div class="menu-item dropdown">
            <div class="menu-header">
                <div class="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </div>
                <span>Research Proposal</span>
                <div class="dropdown-icon expanded">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
            </div>
            <div class="dropdown-content show">
                <a href="titleproposal/route1.php" class="submenu-item">Route 1</a>
                <a href="titleproposal/route2.php" class="submenu-item">Route 2</a>
                <a href="titleproposal/route3.php" class="submenu-item">Route 3</a>
                <a href="titleproposal/finaldocu.php" class="submenu-item">Final Document</a>
            </div>
        </div>

        <!-- Final Defense Section -->
        <div class="menu-item dropdown">
            <div class="menu-header">
                <div class="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                    </svg>
                </div>
                <span>Final Defense</span>
                <div class="dropdown-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
            </div>
            <div class="dropdown-content">
                <a href="final/route1.php" class="submenu-item">Route 1</a>
                <a href="final/route2.php" class="submenu-item">Route 2</a>
                <a href="final/route3.php" class="submenu-item">Route 3</a>
                <a href="final/finaldocu.php" class="submenu-item">Final Document</a>
            </div>
        </div>

        <!-- Department Course Section -->
        <div class="menu-item dropdown">
            <div class="menu-header">
                <div class="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>
                <span>Department Course</span>
                <div class="dropdown-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
            </div>
            <div class="dropdown-content">
                <a href="departmentcourse/departmentcourse.php" class="submenu-item">Department Course</a>
            </div>
        </div>



        <!-- Registered Account Section -->
        <div class="menu-item dropdown">
            <div class="menu-header">
                <div class="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <span>Accounts</span>
                <div class="dropdown-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
            </div>
            <div class="dropdown-content">
                <a href="registeredaccount/panel_register.php" class="submenu-item">Panel</a>
                <a href="registeredaccount/adviser_register.php" class="submenu-item">Adviser</a>
                <a href="registeredaccount/student_register.php" class="submenu-item">Student</a>
            </div>
        </div>
    </nav>
    <div class="logout">
        <a href="../../logout.php">Logout</a>
    </div>
</nav>
            <div class="content">
                <div class="welcome-card">
                    <h1>Welcome to Thesis Routing System</h1>
                    <p>Select an option from the sidebar to manage your thesis documents and track your progress through the routing system.</p>
                </div>
                <!-- Additional content will go here -->
            </div>
        </div>
    </div>

    
</body>
</html>