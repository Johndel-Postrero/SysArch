<?php
require __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idno = filter_input(INPUT_POST, 'idno', FILTER_SANITIZE_NUMBER_INT);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
    $middlename = filter_input(INPUT_POST, 'middlename', FILTER_SANITIZE_STRING);
    $course = filter_input(INPUT_POST, 'course', FILTER_SANITIZE_STRING);
    $level = filter_input(INPUT_POST, 'level', FILTER_SANITIZE_NUMBER_INT);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $check_sql = "SELECT * FROM users WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p style='color:red;'>Username or Email already exists!</p>";
    } else {
        $sql = "INSERT INTO users (idno, lastname, firstname, middlename, course, level, email, username, password) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssisss", $idno, $lastname, $firstname, $middlename, $course, $level, $email, $username, $password);

        if ($stmt->execute()) {
            echo "<p style='color:green;'>Registration successful!</p>";
        } else {
            echo "<p style='color:red;'>Error: " . $stmt->error . "</p>";
        }
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.phptutorial.net/app/css/style.css">
    <link rel="stylesheet" href="fonts/material-design-iconic-font/css/material-design-iconic-font.min.css">
    <link rel="stylesheet" href="css/style.css">
    <title>Register</title>
    <style>
        .image-holder{
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .image-holder .logo{
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .image-holder .logo h1{
            font-size: 25px;
        }
        .image-holder .logo img{
            width: 20%;
        }
        :root {
            --input-background-color: none;
        }
        input {
            background-color: var(--input-background-color);
        }
    </style>
</head>
<body>
    <div class="wrapper" style="background-image: url('inc/computer.png');">
        <div class="inner">
            <div class="image-holder">
                <div class="logo">
                    <img src="inc/CCS_LOGO.png" alt="CCS LOGO">
                    <div style="width: 60%;"><h1>SIT-IN MONITORING SYSTEM</h1></div>
                </div>
                <img src="inc/graphs.svg" alt="graphics" style="width: 95%;">
            </div>
            <form action="register.php" method="post">
                <h3>Sign Up</h3>
                <div class="form-wrapper">
                    <input type="number" name="idno" id="idno" placeholder="ID Number" class="form-control" required >
                    <i class="zmdi zmdi-card"></i>
                </div>
                <div class="form-group">
                    <input type="text" name="lastname" id="lastname" required placeholder="Last Name" class="form-control">
                    <input type="text" name="firstname" id="firstname" required placeholder="First Name" class="form-control" style="margin-right: 25px;">  
                    <input type="text" name="middlename" id="middlename" placeholder="Middle Name" class="form-control">
                </div>
                <div class="form-group">
                    <div class="form-wrapper" style="width: 50%; margin-right: 25px;">
                        <select nid="course" name="course" required class="form-control">
                            <option value="" disabled selected>Course</option>
                            <option value="BSIT">BSIT</option>
                            <option value="BSCS">BSCS</option>
                        </select>
                        <i class="zmdi zmdi-caret-down" style="font-size: 17px; bottom: 30px;"></i>
                    </div>
                    <div class="form-wrapper" style="width: 50%;">
                        <select id="level" name="level" required required class="form-control">
                            <option value="" disabled selected>Year Level</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                        <i class="zmdi zmdi-caret-down" style="font-size: 17px; bottom: 30px;"></i>
                    </div>
                </div>
                <div class="form-wrapper">
                    <input type="email" name="email" id="email" required placeholder="Email Address" class="form-control">
                    <i class="zmdi zmdi-email"></i>
                </div>
                <div class="form-wrapper">
                    <input type="text" name="username" id="username" required placeholder="Username" class="form-control">
                    <i class="zmdi zmdi-account"></i>
                </div>
                <div class="form-wrapper">
                    <input type="password" name="password" id="password" required placeholder="Password" class="form-control">
                    <i class="zmdi zmdi-lock"></i>
                </div>
                <div>
                    <label for="agree">
                        <input type="checkbox" name="agree" id="agree" required> I agree
                        with the <a href="#" title="terms of service">terms of service</a>
                    </label>
                </div>
                <button type="submit" style="margin-top: 0;">Register</button>
                <footer>Already have an account? <a href="login.php">Login here</a></footer>
            </form>
        </div>
    </div>
</body>
</html>
