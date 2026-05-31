<?php
session_start(); // Start the session

// Check if the user is not logged in (session variable is not set)
if (!isset($_SESSION['student_id'])) {
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
    <title>Student Dashboard - Thesis Routing System</title>
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
                <span class="role">Student:</span>
                <span
                    class="user-name"><?php echo isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Guest'; ?></span>
            </div>
        </div>
        <div class="main-content">
            <nav class="sidebar">
                <nav class="nav-menu">
                    <!-- Title Proposal Section -->
                    <div class="menu-item dropdown">
                        <div class="menu-header">
                            <div class="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                            </div>
                            <span>Research Proposal</span>
                            <div class="dropdown-icon expanded">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
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
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                                </svg>
                            </div>
                            <span>Final Defense</span>
                            <div class="dropdown-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
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
                </nav>
                <div class="logout">
                    <a href="../../logout.php">Logout</a>
                </div>
            </nav>
            <div class="content">
                <div class="welcome-card">
                    <h1>Welcome to Thesis Routing System</h1>
                    <p>Select an option from the sidebar to manage your thesis documents and track your progress through
                        the routing system.</p>
                </div>
                <!-- Additional content will go here -->
            </div>
        </div>
    </div>
</body>

</html>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuHeaders = document.querySelectorAll('.menu-header');
    const path = window.location.pathname;

    menuHeaders.forEach(header => {
        const dropdownContent = header.nextElementSibling;
        const label = header.querySelector('span').textContent.trim().toLowerCase();

        // Default: close all
        header.querySelector('.dropdown-icon').classList.remove('expanded');
        dropdownContent.classList.remove('show');

        // Expand the right one based on URL
        if (path.includes('/titleproposal/') && label.includes('research proposal')) {
            header.querySelector('.dropdown-icon').classList.add('expanded');
            dropdownContent.classList.add('show');
        } else if (path.includes('/final/') && label === 'final defense') {
            header.querySelector('.dropdown-icon').classList.add('expanded');
            dropdownContent.classList.add('show');
        }

        // Accordion behavior
        header.addEventListener('click', function() {
            menuHeaders.forEach(h => {
                const icon = h.querySelector('.dropdown-icon');
                const content = h.nextElementSibling;
                
                if (h !== this) {
                    icon.classList.remove('expanded');
                    content.classList.remove('show');
                }
            });

            // Toggle the clicked one
            this.querySelector('.dropdown-icon').classList.toggle('expanded');
            dropdownContent.classList.toggle('show');
        });
    });
});
</script>

