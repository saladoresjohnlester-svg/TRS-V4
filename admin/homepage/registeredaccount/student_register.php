<?php
// Start the session
session_start();

// Check if the user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../logout.php");
    exit;
}

// Database connection
include '../../../connection.php';

// Handle delete request (use student_id as primary key)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student_id'])) {
    $delete_student_id = (int) $_POST['delete_student_id'];

    if ($delete_student_id > 0) {
        $delete_sql = "DELETE FROM student WHERE student_id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $delete_student_id);
        
        if ($stmt->execute()) {
            header("Location: student_register.php?status=delete_success");
            exit;
        } else {
            header("Location: student_register.php?status=delete_error");
            exit;
        }
        $stmt->close();
    }
}

// Also select student_id so we can use it for actions
$sql = "SELECT student_id, fullname, department, school_id, password, confirm_password, email FROM student ORDER BY fullname ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Thesis Routing System</title>
    <style>
        :root {
            --primary: #002366;
            --primary-light: #0a3885;
            --accent: #4a6fd1;
            --light: #f5f7fd;
            --dark: #333;
            --success: #28a745;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border: #e0e0e0;
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
        
        .user-info a {
            color: white;
            margin-right: 15px;
            padding: 8px 15px;
            background-color: var(--accent);
            border-radius: 5px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .user-info a:hover {
            background-color: var(--primary);
        }
        
        .vl {
            border-left: 2px solid var(--border);
            height: 25px;
            margin: 0 15px;
        }
        
        .role {
            font-weight: 600;
            margin-right: 5px;
            color: var(--primary);
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
        
        .content {
            flex: 1;
            padding: 2rem;
            position: relative;
            overflow: auto;
        }
        
        .form-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-container h1 {
            text-align: center;
            color: var(--primary);
            margin-bottom: 1.5rem;
            font-size: 1.75rem;
        }
        
        .success-alert {
            display: none;
            background-color: #d4edda;
            color: #155724;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 5px;
            border-left: 5px solid var(--success);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
            background-color: white;
        }
        
        table th, table td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid var(--border);
        }
        
        table th {
            background-color: var(--light);
            color: var(--primary);
            font-weight: 600;
        }
        
        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        table tr:hover {
            background-color: #f5f5f5;
        }
        
        button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        button[type="button"] {
            background-color: var(--accent);
            color: white;
        }
        
        button[type="submit"] {
            background-color: var(--success);
            color: white;
        }
        
        button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .delete-button {
            background-color: #dc3545;
            color: #fff;
            margin-left: 0.25rem;
        }
        
        input[type="text"], input[type="password"] {
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            width: 100%;
            font-size: 0.9rem;
        }
        
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(74, 111, 209, 0.2);
        }
        
        input[type="email"] {
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            width: 100%;
            font-size: 0.9rem;
        }
        
        input[type="email"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(74, 111, 209, 0.2);
        }
        
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
            
            .form-container {
                padding: 1.5rem;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
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

    .dropdown-container {
        display: flex;
        gap: 8px;
        margin-left: 15px;
    }

    .dropdown-container select {
        padding: 0.5rem;
        border-radius: 4px;
        border: 1px solid var(--border);
        background-color: white;
        font-family: inherit;
        cursor: pointer;
    }

    #external-submit-button {
        background-color: var(--accent);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
    }

    #external-submit-button:hover {
        background-color: var(--primary);
        transform: translateY(-2px);
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

    .content {
        flex: 1;
        padding: 2rem;
        position: relative;
        overflow: auto;
    }

    /* Table Styling */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
        background-color: white;
        box-shadow: var(--shadow);
        border-radius: 8px;
        overflow: hidden;
    }

    th, td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid var(--border);
    }

    th {
        background-color: var(--primary);
        color: white;
        font-weight: 600;
    }

    tr:nth-child(even) {
        background-color: rgba(0, 0, 0, 0.02);
    }

    tr:hover {
        background-color: rgba(0, 0, 0, 0.03);
    }

    /* Button Styling */
    button {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
    }

    .view-button {
        background-color: var(--accent);
        color: white;
        margin-right: 0.5rem;
    }

    .view-button:hover {
        background-color: var(--primary);
    }

    /* Modal Styling */
    .modal {
        position: fixed;
        z-index: 999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.6);
        display: none;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background-color: #fff;
        width: 95%;
        height: 90%;
        display: flex;
        flex-direction: column;
        border-radius: 8px;
        overflow: hidden;
        position: relative;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    .modal-layout {
        display: flex;
        height: 100%;
        width: 100%;
    }

    .file-preview-section,
    .routing-form-section {
        flex: 1;
        padding: 1rem;
        overflow-y: auto;
    }

    .file-preview-section {
        border-right: 1px solid var(--border);
    }

    .routing-form-section {
        background-color: #f9f9f9;
        font-size: 0.85rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 5px;
        margin-bottom: 10px;
    }

    .form-grid-container {
        display: grid;
        grid-template-columns: repeat(8, 1fr);
        border: 1px solid var(--border);
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 1rem;
    }

    .form-grid-container > div {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem;
        font-size: 0.8rem;
        border: 1px solid var(--border);
        background-color: white;
        text-align: center;
    }

    .close-button {
        position: absolute;
        top: 10px;
        right: 20px;
        font-size: 28px;
        cursor: pointer;
        color: var(--dark);
        transition: all 0.3s;
        z-index: 1000;
    }

    .close-button:hover {
        color: var(--accent);
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

    /* Additional utilities */
    input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    .selected {
        background-color: var(--light);
    }
    
    .add-panel-button {
        background-color: var(--primary);
        color: white;
    }
    </style>
    <script>
        window.onload = function () {
            // Show success message if status is success
            var status = new URLSearchParams(window.location.search).get('status');
            if (status === 'success') {
                document.getElementById('success-alert').style.display = 'block';
                
                // Auto-hide the success message after 5 seconds
                setTimeout(function() {
                    document.getElementById('success-alert').style.display = 'none';
                }, 5000);
            }
        }
    </script>
</head>

<body>
    <div class="container">
        <header class="header">
            <div class="logo-container">
                <img src="../../../assets/logo.png" alt="SMCC Logo">
                <div class="logo">Thesis Routing System</div>
            </div>
        </header>
        <div class="top-bar">
            <div class="homepage">
                <a href="../../homepage/homepage.php">Home Page</a>
            </div>
            <div class="user-info">
                <div class="vl"></div>
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
                <a href="../titleproposal/route1.php" class="submenu-item">Route 1</a>
                <a href="../titleproposal/route2.php" class="submenu-item">Route 2</a>
                <a href="../titleproposal/route3.php" class="submenu-item">Route 3</a>
                <a href="../titleproposal/finaldocu.php" class="submenu-item">Endorsement Form</a>
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
                <a href="../final/route1.php" class="submenu-item">Route 1</a>
                <a href="../final/route2.php" class="submenu-item">Route 2</a>
                <a href="../final/route3.php" class="submenu-item">Route 3</a>
                <a href="../final/finaldocu.php" class="submenu-item">Final Document</a>
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
                <a href="../departmentcourse/departmentcourse.php" class="submenu-item">Department Course</a>
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
                <span> Accounts</span>
                <div class="dropdown-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
            </div>
            <div class="dropdown-content">
                <a href="../registeredaccount/panel_register.php" class="submenu-item">Panel</a>
                <a href="../registeredaccount/adviser_register.php" class="submenu-item">Adviser</a>
                <a href="../registeredaccount/student_register.php" class="submenu-item">Student</a>
            </div>
        </div>
    </nav>
    <div class="logout">
        <a href="../../../logout.php">Logout</a>
    </div>
</nav>
            <div class="content">
                <div class="form-container">
                    <h1>List of Registered Students</h1>
                    <div id="success-alert" class="success-alert">
                        <strong>Success!</strong> Student updated successfully!
                    </div>
                    
                    <!-- Search Bar -->
                    <div class="search-container" style="margin: 20px 0; text-align: right;">
                        <input type="text" id="searchInput" placeholder="Search by name..." style="padding: 8px; width: 250px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>

                    <?php if ($result->num_rows > 0): ?>
                        <table border="1" cellpadding="10" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>Department</th>
                                    <th>ID Number</th>
                                    <th>Email</th>
                                    <th>Password</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr id="row_<?= $row['school_id'] ?>">
                                        <form action="update_student_inline.php" method="POST" id="form_<?= $row['school_id'] ?>">
                                            <input type="hidden" name="student_id" value="<?= (int) $row['student_id'] ?>">
                                            <td>
                                                <span id="fullname_text_<?= $row['school_id'] ?>"><?= htmlspecialchars($row['fullname']) ?></span>
                                                <input type="text" name="fullname" value="<?= htmlspecialchars($row['fullname']) ?>" id="fullname_input_<?= $row['school_id'] ?>" style="display:none;" required>
                                            </td>
                                            <td>
                                                <span id="department_text_<?= $row['school_id'] ?>"><?= htmlspecialchars($row['department']) ?></span>
                                                <input type="text" name="department" value="<?= htmlspecialchars($row['department']) ?>" id="department_input_<?= $row['school_id'] ?>" style="display:none;" required>
                                            </td>
                                            <td>
                                                <span id="schoolid_text_<?= $row['school_id'] ?>"><?= htmlspecialchars($row['school_id']) ?></span>
                                                <input type="text" name="school_id" value="<?= htmlspecialchars($row['school_id']) ?>" id="schoolid_input_<?= $row['school_id'] ?>" style="display:none;" required oninput="validateIdNumber('<?= $row['school_id'] ?>')">
                                                <input type="hidden" name="original_school_id" value="<?= htmlspecialchars($row['school_id']) ?>">
                                                <small id="id-warning_<?= $row['school_id'] ?>" style="color:red; display:none;">ID Number should only contain digits!</small>
                                            </td>
                                            <td>
                                                <span id="email_text_<?= $row['school_id'] ?>"><?= htmlspecialchars($row['email']) ?></span>
                                                <input type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>" id="email_input_<?= $row['school_id'] ?>" style="display:none;">
                                            </td>
                                            <td>
                                                <span id="password_text_<?= $row['school_id'] ?>"><?= htmlspecialchars($row['password']) ?></span>
                                                <div id="password_inputs_<?= $row['school_id'] ?>" style="display:none;">
                                                    <input type="text" name="password" value="<?= htmlspecialchars($row['password']) ?>" id="password_input_<?= $row['school_id'] ?>" placeholder="New Password" required oninput="checkPasswordMatch('<?= $row['school_id'] ?>')">
                                                    <br>
                                                    <input type="text" name="confirm_password" value="<?= htmlspecialchars($row['confirm_password']) ?>" id="confirm_password_input_<?= $row['school_id'] ?>" placeholder="Confirm Password" required oninput="checkPasswordMatch('<?= $row['school_id'] ?>')">
                                               
                                                    <br>
                                                    <small id="mismatch_<?= $row['school_id'] ?>" style="color:red; display:none;">Passwords do not match!</small>
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" onclick="enableEdit('<?= $row['school_id'] ?>')" id="edit_btn_<?= $row['school_id'] ?>">Edit</button>
                                                <button type="submit" style="display:none;" id="save_btn_<?= $row['school_id'] ?>" disabled>Save</button>
                                                <button type="button" style="display:none;" onclick="cancelEdit('<?= $row['school_id'] ?>')" id="cancel_btn_<?= $row['school_id'] ?>">Cancel</button>
                                                <button type="button" class="delete-button" onclick="deleteStudent(<?= (int) $row['student_id'] ?>)">Delete</button>
                                            </td>
                                        </form>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No students have been registered yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<script>
        function enableEdit(school_id) {
            document.getElementById('fullname_text_' + school_id).style.display = 'none';
            document.getElementById('department_text_' + school_id).style.display = 'none';
            document.getElementById('schoolid_text_' + school_id).style.display = 'none';
            document.getElementById('password_text_' + school_id).style.display = 'none';
            document.getElementById('email_text_' + school_id).style.display = 'none';

            document.getElementById('fullname_input_' + school_id).style.display = 'inline';
            document.getElementById('department_input_' + school_id).style.display = 'inline';
            document.getElementById('schoolid_input_' + school_id).style.display = 'inline';
            document.getElementById('password_inputs_' + school_id).style.display = 'block';
            document.getElementById('email_input_' + school_id).style.display = 'inline';

            document.getElementById('edit_btn_' + school_id).style.display = 'none';
            document.getElementById('save_btn_' + school_id).style.display = 'inline';
            document.getElementById('cancel_btn_' + school_id).style.display = 'inline';
            
            document.getElementById('save_btn_' + school_id).disabled = false;
        }

        function cancelEdit(school_id) {
            document.getElementById('fullname_text_' + school_id).style.display = 'inline';
            document.getElementById('department_text_' + school_id).style.display = 'inline';
            document.getElementById('schoolid_text_' + school_id).style.display = 'inline';
            document.getElementById('password_text_' + school_id).style.display = 'inline';
            document.getElementById('email_text_' + school_id).style.display = 'inline';

            document.getElementById('fullname_input_' + school_id).style.display = 'none';
            document.getElementById('department_input_' + school_id).style.display = 'none';
            document.getElementById('schoolid_input_' + school_id).style.display = 'none';
            document.getElementById('password_inputs_' + school_id).style.display = 'none';
            document.getElementById('email_input_' + school_id).style.display = 'none';

            document.getElementById('edit_btn_' + school_id).style.display = 'inline';
            document.getElementById('save_btn_' + school_id).style.display = 'none';
            document.getElementById('cancel_btn_' + school_id).style.display = 'none';

            document.getElementById('mismatch_' + school_id).style.display = 'none';
        }

        function checkPasswordMatch(school_id) {
            var password = document.getElementById('password_input_' + school_id).value;
            var confirm_password = document.getElementById('confirm_password_input_' + school_id).value;
            var mismatchText = document.getElementById('mismatch_' + school_id);
            var saveBtn = document.getElementById('save_btn_' + school_id);

            if (password !== "" || confirm_password !== "") {
                if (password !== confirm_password) {
                    mismatchText.style.display = 'block';
                    saveBtn.disabled = true;
                } else {
                    mismatchText.style.display = 'none';
                    saveBtn.disabled = false;
                }
            } else {
                mismatchText.style.display = 'none';
                saveBtn.disabled = false;
            }
        }

        function deleteStudent(studentId) {
            if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'student_register.php';

                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_student_id';
                input.value = studentId;
                form.appendChild(input);

                document.body.appendChild(form);
                form.submit();
            }
        }

        window.onload = function() {
            // Show success message based on status
            var status = new URLSearchParams(window.location.search).get('status');
            var successAlert = document.getElementById('success-alert');
            var successMessage = document.getElementById('success-message');
            
            if (status === 'success') {
                successMessage.textContent = 'Student updated successfully!';
                successAlert.style.display = 'block';
                setTimeout(function() {
                    successAlert.style.display = 'none';
                }, 5000);
            } else if (status === 'delete_success') {
                successMessage.textContent = 'Student deleted successfully!';
                successAlert.style.display = 'block';
                setTimeout(function() {
                    successAlert.style.display = 'none';
                }, 5000);
            } else if (status === 'delete_error') {
                successAlert.style.backgroundColor = '#f8d7da';
                successAlert.style.color = '#721c24';
                successAlert.style.borderLeft = '5px solid #dc3545';
                successMessage.textContent = 'Error deleting student!';
                successAlert.style.display = 'block';
                setTimeout(function() {
                    successAlert.style.display = 'none';
                }, 5000);
            }
            
            // Add search functionality
            document.getElementById('searchInput').addEventListener('keyup', function() {
                var searchValue = this.value.toLowerCase();
                var rows = document.querySelectorAll('tbody tr');
                
                rows.forEach(function(row) {
                    var fullnameSpan = row.querySelector('span[id^="fullname_text_"]');
                    if (fullnameSpan) {
                        var fullname = fullnameSpan.textContent.toLowerCase();
                        if (fullname.includes(searchValue)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
            });
        }
    </script>
</body>
</html>

<script src="../sidebar.js"></script>