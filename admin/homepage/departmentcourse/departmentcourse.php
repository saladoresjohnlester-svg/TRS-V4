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

// Fetch all departments and courses
$sql = "SELECT id, department, course FROM departmentcourse ORDER BY department ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department and Course Management - Thesis Routing System</title>
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
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
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

        .content-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .content-container h1 {
            text-align: center;
            color: var(--primary);
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

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .edit-button {
            background-color: var(--accent);
            color: white;
        }

        .edit-button:hover {
            background-color: var(--primary);
        }

        .cancel-button {
            background-color: #dc3545;
            color: white;
            margin-right: 10px;
        }

        .cancel-button:hover {
            background-color: #c82333;
        }

        .add-new-btn {
    display: block;
    margin: 1.5rem 0 1.5rem auto; /* Changed margin to align right */
    background-color: var(--accent);
    color: white;
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s;
    float: right; /* Added to ensure right alignment */
    clear: both; /* Ensures proper flow with other elements */
   
}

.add-new-btn:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
}

        input[type="text"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(74, 111, 209, 0.2);
        }

        /* Modal Styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            animation: modalFadeIn 0.3s;
        }

        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-50px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .close:hover {
            color: var(--primary);
        }

        .modal-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .modal-title {
            font-size: 1.5rem;
            color: var(--primary);
            margin: 0;
        }

        .modal-footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
        }

        .modal-footer button {
            padding: 0.75rem 1.5rem;
        }

        /* Form styling */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-control:focus {
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

            .content-container {
                padding: 1.5rem;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }

            table {
                display: block;
                overflow-x: auto;
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

        .active {
            background-color: var(--light);
            color: var(--primary);
            font-weight: 500;
        }

        .no-data {
            text-align: center;
            padding: 2rem;
            color: #777;
            font-style: italic;
        }
    </style>
    <script>
        window.onload = function () {
            // Show success message if status is success
            var status = new URLSearchParams(window.location.search).get('status');
            if (status === 'success') {
                document.getElementById('success-alert').style.display = 'block';

                // Auto-hide the success message after 5 seconds
                setTimeout(function () {
                    document.getElementById('success-alert').style.display = 'none';
                }, 5000);
            }
        }

        // Modal Functions
        function openAddModal() {
            document.getElementById("addDepartmentModal").style.display = "block";
        }

        function closeAddModal() {
            document.getElementById("addDepartmentModal").style.display = "none";
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById("addDepartmentModal");
            if (event.target == modal) {
                modal.style.display = "none";
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
                <span>Accounts</span>
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
                <div class="content-container">
                    <h1>Department and Course Management</h1>

                    <div id="success-alert" class="success-alert">
                        <strong>Success!</strong> Department and course updated successfully!
                    </div>

                    <!-- Button to add new department and course -->
                    <button onclick="openAddModal()" class="add-new-btn">
                        Add Department & Course
                    </button>
                    
                    <?php if ($result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Course</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Reset the result pointer to the beginning
                            $result->data_seek(0);
                            while ($row = $result->fetch_assoc()): 
                            ?>
                                <tr id="row_<?= $row['id'] ?>">
                                    <form action="update_department_course.php" method="POST" id="form_<?= $row['id'] ?>">
                                        <td>
                                            <span id="department_text_<?= $row['id'] ?>"><?= htmlspecialchars($row['department']) ?></span>
                                            <input type="text" name="department" value="<?= htmlspecialchars($row['department']) ?>"
                                                id="department_input_<?= $row['id'] ?>" style="display:none;" required class="form-control">
                                        </td>
                                        <td>
                                            <span id="course_text_<?= $row['id'] ?>"><?= htmlspecialchars($row['course']) ?></span>
                                            <input type="text" name="course" value="<?= htmlspecialchars($row['course']) ?>"
                                                id="course_input_<?= $row['id'] ?>" style="display:none;" required class="form-control">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        </td>
                                        <td class="action-buttons">
                                            <button type="button" onclick="enableEdit('<?= $row['id'] ?>')"
                                                id="edit_btn_<?= $row['id'] ?>" class="edit-button">Edit</button>
                                            <button type="submit" style="display:none;"
                                                id="save_btn_<?= $row['id'] ?>" class="btn-success">Save</button>
                                            <button type="button" style="display:none;"
                                                onclick="cancelEdit('<?= $row['id'] ?>')"
                                                id="cancel_btn_<?= $row['id'] ?>" class="cancel-button">Cancel</button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div class="no-data">No departments and courses have been added yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for adding new department and course -->
    <div id="addDepartmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeAddModal()">&times;</span>
                <h2 class="modal-title">Add New Department and Course</h2>
            </div>
            
            <form action="add_department_course.php" method="POST">
                <div class="form-group">
                    <label for="department">Department:</label>
                    <input type="text" id="department" name="department" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="course">Course:</label>
                    <input type="text" id="course" name="course" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeAddModal()" class="cancel-button">Cancel</button>
                    <button type="submit" class="btn-primary">Add Department and Course</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function enableEdit(id) {
            document.getElementById('department_text_' + id).style.display = 'none';
            document.getElementById('course_text_' + id).style.display = 'none';

            document.getElementById('department_input_' + id).style.display = 'inline';
            document.getElementById('course_input_' + id).style.display = 'inline';

            document.getElementById('edit_btn_' + id).style.display = 'none';
            document.getElementById('save_btn_' + id).style.display = 'inline';
            document.getElementById('cancel_btn_' + id).style.display = 'inline';
        }

        function cancelEdit(id) {
            document.getElementById('department_text_' + id).style.display = 'inline';
            document.getElementById('course_text_' + id).style.display = 'inline';

            document.getElementById('department_input_' + id).style.display = 'none';
            document.getElementById('course_input_' + id).style.display = 'none';

            document.getElementById('edit_btn_' + id).style.display = 'inline';
            document.getElementById('save_btn_' + id).style.display = 'none';
            document.getElementById('cancel_btn_' + id).style.display = 'none';
        }
    </script>
    </body>
</html>

<script src="../sidebar.js"></script>