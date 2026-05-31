<?php
// Enable error reporting and log errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log');

// Database connection
include '../connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve and sanitize inputs
    $school_id = mysqli_real_escape_string($conn, $_POST['school_id']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $school_year = mysqli_real_escape_string($conn, $_POST['school_year']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $course = mysqli_real_escape_string($conn, $_POST['course']);

    $adviser = mysqli_real_escape_string($conn, $_POST['adviser']);
    $adviser_email = mysqli_real_escape_string($conn, $_POST['adviser_email']);
    $group_number = mysqli_real_escape_string($conn, $_POST['group_number']);
    $members = isset($_POST['member_fullname']) ? $_POST['member_fullname'] : [];
    $group_members = json_encode($members); // Now properly defined    
    $controlNo = mysqli_real_escape_string($conn, $_POST['controlNo']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);

    // Validate passwords match
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!'); window.history.back();</script>";
        exit;
    }

    // Check if school_id already exists to prevent duplicate student registration
    $checkSql = "SELECT 1 FROM student WHERE school_id = '$school_id' LIMIT 1";
    $checkResult = $conn->query($checkSql);
    if ($checkResult && $checkResult->num_rows > 0) {
        echo "<script>alert('This school ID is already registered. Please use a different school ID.'); window.history.back();</script>";
        exit;
    }

    // Insert data into the database (no need for student_id since it's auto-incremented)
    $sql = "INSERT INTO student (title, controlNo, school_id, password, confirm_password, fullname, email, school_year, semester, department, course, adviser, adviser_email, group_number, group_members) 
            VALUES ('$title', '$controlNo','$school_id', '$password','$confirm_password', '$fullname', '$email', '$school_year', '$semester', '$department', '$course', '$adviser', '$adviser_email', '$group_number', '$group_members')";

    if ($conn->query($sql) === TRUE) {
        // Get the auto-generated student_id
        $student_id = $conn->insert_id;
        echo "<script>alert('Registration successful!'); window.location.href = 'login.php';</script>";
    } else {
        echo "<script>alert('Error: " . addslashes($conn->error) . "'); window.history.back();</script>";
    }
}

// Fetch departments from the database
$departmentQuery = "SELECT DISTINCT department FROM departmentcourse ORDER BY department";
$departmentResult = $conn->query($departmentQuery);

// Close the connection
$conn->close();
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
            --danger: #dc3545;
            --warning: #ffc107;
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
            padding: 40px 20px;
            overflow-y: auto;
        }
        
        .form-container {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 600px;
            margin: 20px 0;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .form-header h2 {
            color: var(--dark);
            font-size: 24px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-header p {
            color: #666;
            font-size: 15px;
        }
        
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section-title {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
        }
        
        .form-section-title span {
            margin-right: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            background-color: var(--primary);
            color: white;
            border-radius: 50%;
            font-size: 14px;
        }
        
        .input-group {
            margin-bottom: 15px;
        }
        
        .input-group label, #members-container label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: #555;
            font-weight: 500;
        }
        #members-container input{
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .input-group input,
        .input-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .input-group input:focus,
        .input-group select:focus,
        #members-container input:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 2px rgba(74, 111, 209, 0.2);
        }
        
        .input-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .input-row .input-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .researchers-section {
            background-color: var(--light);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .researchers-title {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 15px;
            text-align: center;
        }
        
        #members-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary {
            background-color: var(--accent);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #3a5fc1;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #e0e0e0;
            color: #555;
        }
        
        .btn-secondary:hover {
            background-color: #d0d0d0;
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 14px;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .btn-icon {
            margin-right: 6px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .form-actions button {
            flex: 1;
        }
        
        .footer {
            margin-top: 20px;
            text-align: center;
            color: #777;
            font-size: 14px;
        }
        
        @media (max-width: 992px) {
            body {
                flex-direction: column;
            }
            
            .left-panel, .right-panel {
                width: 100%;
            }
            
            .left-panel {
                padding: 30px 20px;
            }
            
            .input-row {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="left-panel">
        <div class="logo-container">
            <img src="../assets/logo.png" alt="SMCC Logo">
            <h1>Saint Michael College of Caraga</h1>
            <p>Brgy 4, Atupan St., Nasipit, Agusan del Norte</p>
        </div>
    </div>

    <div class="right-panel">
        <div class="form-container">
            <div class="form-header">
                <h2>Student Registration</h2>
                <p>Please fill in the details to register for the Thesis Routing System</p>
            </div>
            
            <form action="register.php" method="POST">
                <div class="form-section">
                    <div class="form-section-title"><span>1</span>Thesis Information</div>
                    <div class="input-group">
                        <label for="title">Thesis Title</label>
                        <input type="text" id="title" name="title" placeholder="Enter the title of your thesis" required>
                    </div>
                    <div class="input-group">
                        <label for="controlNo">Control Number</label>
                        <input type="text" id="controlNo" name="controlNo" placeholder="Enter control number" required>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="form-section-title"><span>2</span>Account Information</div>
                    <div class="input-group">
                        <label for="school_id">ID Number</label>
                        <input type="text" id="school_id" name="school_id" placeholder="Enter your ID number" required>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Create a password" required>
                        </div>
                        <div class="input-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="form-section-title"><span>3</span>Researchers Information</div>
                    <div class="researchers-section">
                        <div class="researchers-title">Thesis Researchers</div>
                        <div class="input-group">
                            <label for="fullname">Research Leader</label>
                            <input type="text" id="fullname" name="fullname" placeholder="Enter your complete name" required>
                        </div>
                        
                        
                        <div id="members-container">
                        <label for="members">Additional Members</label>
                            <input type="text" name="member_fullname[]" placeholder="Name of Member">
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addMemberField()">
                            + Add Another Member
                        </button>
                    </div>
                    
                    <div class="input-row">
                        <div class="input-group">
                            <label for="group_number">Group Number</label>
                            <input type="text" id="group_number" name="group_number" placeholder="Enter group number" required>
                        </div>
                        <div class="input-group">
                            <label for="adviser">Adviser</label>
                            <select id="adviser" name="adviser" required onchange="updateAdviserEmail()">
                                <option value="">Loading advisers...</option>
                            </select>
                            <input type="hidden" id="adviser_email" name="adviser_email">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="form-section-title"><span>4</span>Academic Information</div>
                    <div class="input-group">
                        <label for="school_year">School Year</label>
                        <select id="school_year" name="school_year" required>
                            <option value="">Select School Year</option>
                            <option value="2024-2025">2024-2025</option>
                            <option value="2025-2026">2025-2026</option>
                            <option value="2026-2027">2026-2027</option>
                            <option value="2027-2028">2027-2028</option>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester" required>
                            <option value="">Select Semester</option>
                            <option value="First Semester">First Semester</option>
                            <option value="Second Semester">Second Semester</option>
                            <option value="Summer">Summer</option>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label for="department">Department</label>
                        <select id="department" name="department" required onchange="loadCourses(this.value)">
                            <option value="">Select Department</option>
                            <?php
                            // Display departments from database
                            if ($departmentResult && $departmentResult->num_rows > 0) {
                                while($row = $departmentResult->fetch_assoc()) {
                                    echo '<option value="' . $row["department"] . '">' . $row["department"] . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label for="course">Course</label>
                        <select id="course" name="course" required>
                            <option value="">Select Department First</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success btn-block">Register</button>
                    <button type="button" class="btn btn-secondary btn-block" onclick="window.location.href='login.php'">Back to Login</button>
                </div>
            </form>
        </div>
        
        <div class="footer">
            <p>© 2025 Saint Michael College of Caraga | All Rights Reserved</p>
        </div>
    </div>
    
    <script>
        function addMemberField() {
            const container = document.getElementById('members-container');
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'member_fullname[]';
            input.placeholder = 'Name of Member';
            container.appendChild(input);
        }
        
        function loadCourses(department) {
            const courseSelect = document.getElementById('course');
            
            // Reset course dropdown
            courseSelect.innerHTML = '<option value="">Loading courses...</option>';
            
            // Use AJAX to fetch courses for the selected department
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_courses.php?department=' + encodeURIComponent(department), true);
            
            xhr.onload = function() {
                if (this.status == 200) {
                    courseSelect.innerHTML = this.responseText;
                } else {
                    courseSelect.innerHTML = '<option value="">Error loading courses</option>';
                }
            };
            
            xhr.onerror = function() {
                courseSelect.innerHTML = '<option value="">Error loading courses</option>';
            };
            
            xhr.send();
        }
        
        function loadAdvisers() {
            const adviserSelect = document.getElementById('adviser');
            
            // Use AJAX to fetch available advisers
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_advisers.php', true);
            
            xhr.onload = function() {
                if (this.status == 200) {
                    adviserSelect.innerHTML = this.responseText;
                } else {
                    adviserSelect.innerHTML = '<option value="">Error loading advisers</option>';
                }
            };
            
            xhr.onerror = function() {
                adviserSelect.innerHTML = '<option value="">Error loading advisers</option>';
            };
            
            xhr.send();
        }
        
        function updateAdviserEmail() {
            const adviserSelect = document.getElementById('adviser');
            const adviserEmailInput = document.getElementById('adviser_email');
            const selectedOption = adviserSelect.options[adviserSelect.selectedIndex];
            
            if (selectedOption && selectedOption.getAttribute('data-email')) {
                adviserEmailInput.value = selectedOption.getAttribute('data-email');
            } else {
                adviserEmailInput.value = '';
            }
        }
        
        // Load advisers when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadAdvisers();
            
            // Add change event listener to adviser select
            document.getElementById('adviser').addEventListener('change', updateAdviserEmail);
        });
    </script>
</body>
</html>