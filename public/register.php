<?php
require __DIR__ . '/../config/db.php';
$error = '';
$error1 = '';
$error2 = '';
$error3 = '';

$course_sql = "SELECT * FROM courses";
$course_result = $conn->query($course_sql);
$courses = [];
if ($course_result->num_rows > 0) {
    while ($row = $course_result->fetch_assoc()) {
        $courses[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idno = filter_input(INPUT_POST, 'idno');
    $lastname = filter_input(INPUT_POST, 'lastname');
    $firstname = filter_input(INPUT_POST, 'firstname');
    $middlename = filter_input(INPUT_POST, 'middlename');
    $course = filter_input(INPUT_POST, 'course');
    $level = filter_input(INPUT_POST, 'level');
    $email = filter_input(INPUT_POST, 'email');
    $username = filter_input(INPUT_POST, 'username');
    $password = $_POST['password'];
    
    // Automatically set role to 'student'
    $role = 'Student';

    // Check for duplicate idno, email, username as before...
    $check_sql = "SELECT * FROM users WHERE idno = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $idno);
    $stmt->execute();
    $result1 = $stmt->get_result();
    $stmt->close();
    
    if ($result1->num_rows > 0) {
        $error = "Id number already exists!";
    } else {
        $check_sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result2 = $stmt->get_result();
        $stmt->close();

        if($result2->num_rows > 0){
            $error1 = "Email already exists!";
        } else {
            $check_sql = "SELECT * FROM users WHERE username = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result3 = $stmt->get_result();
            $stmt->close();
            if ($result3->num_rows > 0) {
                $error2 = "Username already exists!";
            } else {
                if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&£#])[A-Za-z\d@$!%*?&£#]{8,}$/', $password)){
                    $error3 = "Password must be at least 8 characters, should include a mix of uppercase, lowercase, numbers, and special characters.";
                } else {
                    // Hash the password
                    $password = password_hash($password, PASSWORD_DEFAULT);

                    // Create a default profile picture filename from initials:
                    // Take the first letter of firstname and the first letter of lastname.
                    $initials = '';
                    if (!empty($firstname)) {
                        $initials .= strtoupper(substr($firstname, 0, 1));
                    }
                    if (!empty($lastname)) {
                        $initials .= strtoupper(substr($lastname, 0, 1));
                    }
                    // For example, "JD.png"
                    $profile_picture = $initials . '.png';

                    // Insert the new user into the database, including the profile_picture column.
                    $sql = "INSERT INTO users (idno, lastname, firstname, middlename, course, level, email, username, password, role, profile_picture) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    // Adjust the types. Assuming idno is an integer:
                    $stmt->bind_param("issssisssss", $idno, $lastname, $firstname, $middlename, $course, $level, $email, $username, $password, $role, $profile_picture);
            
                    if ($stmt->execute()) {
                        header("Location: register.php?success=true");
                        exit();
                    } else {
                        echo "<p style='color:red;'>Error: " . $stmt->error . "</p>";
                    }
                    $stmt->close();
                }               
            }
        }
    }

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
        #successDialog {
            border: none;
            border-radius: 8px;
            background: white;
            color: black;
            padding: 20px;
            width: 300px; 
            text-align: center;
            box-shadow: 0 4px 10px rgba(255, 255, 255, 0.3);
            position: fixed;
            top: 13%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .modal-content {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        #closeDialog {
            align-self: center; 
            background: #7952b3;
            color: white;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 10px;
            width:auto;
            height: auto;
        }

        #closeDialog:hover {
            background: #7952b3;
        }
        input#lastname.form-control {
    background-color: transparent !important;
}
    </style>
</head>
<body>
<dialog id="successDialog">
    <div class="modal-content">
        <p>Registration Successful!</p>
        <button id="closeDialog">OK</button>
    </div>
</dialog>
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
                    <input type="number" name="idno" id="idno" required placeholder="ID Number" class="form-control <?php echo $error ? 'error' : ''; ?>" style="margin-bottom: <?php echo $error ? '10px' : '25px'; ?>;" value="<?php echo isset($_POST['idno']) ? htmlspecialchars($_POST['idno']) : ''; ?>">
                    <i class="zmdi zmdi-card"></i>
                </div>
                <?php if ($error): ?>
                    <div class="error-message" style="color: red;"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <div class="form-group" style="background-color: transparent !important;">
                    <input  style="background-color: transparent !important;" type="text" name="lastname" id="lastname" required placeholder="Last Name" class="form-control" value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>">
                    <input type="text" name="firstname" id="firstname" required placeholder="First Name" class="form-control" style="margin-right: 25px;" value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>">  
                    <input type="text" name="middlename" id="middlename" placeholder="Middle Name" class="form-control" value="<?php echo isset($_POST['middlename']) ? htmlspecialchars($_POST['middlename']) : ''; ?>">
                </div>
                <div class="form-group">
                    <div class="form-wrapper" style="width: 50%; margin-right: 25px;">
                        <select id="course" name="course" required class="form-control">
                            <option value="" disabled <?php echo !isset($_POST['course']) ? 'selected' : ''; ?>>Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo isset($_POST['course']) && $_POST['course'] == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="zmdi zmdi-caret-down" style="font-size: 17px; bottom: 30px;"></i>
                    </div>
                    <div class="form-wrapper" style="width: 50%;">
                        <select id="level" name="level" required required class="form-control">
                            <option value="" disabled <?php echo !isset($_POST['level']) ? 'selected' : ''; ?>>Year Level</option>
                            <option value="1"<?php echo isset($_POST['level']) && $_POST['level'] == '1' ? 'selected' : ''; ?>>1</option>
                            <option value="2"<?php echo isset($_POST['level']) && $_POST['level'] == '1' ? 'selected' : ''; ?>>2</option>
                            <option value="3"<?php echo isset($_POST['level']) && $_POST['level'] == '1' ? 'selected' : ''; ?>>3</option>
                            <option value="4"<?php echo isset($_POST['level']) && $_POST['level'] == '1' ? 'selected' : ''; ?>>4</option>
                        </select>
                        <i class="zmdi zmdi-caret-down" style="font-size: 17px; bottom: 30px;"></i>
                    </div>
                </div>
                <div class="form-wrapper">
                    <input style="background-color: transparent !important;" type="email" name="email" id="email" required placeholder="Email Address" class="form-control <?php echo $error1 ? 'error' : ''; ?>" style="margin-bottom: <?php echo $error1 ? '10px' : '25px'; ?>;" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <i class="zmdi zmdi-email"></i>
                </div>
                <?php if ($error1): ?>
                    <div class="error-message" style="color: red;"><?php echo htmlspecialchars($error1); ?></div>
                <?php endif; ?>
                <div class="form-wrapper">
                    <input style="background-color: transparent !important;" type="text" name="username" id="username" required placeholder="Username" class="form-control <?php echo $error2 ? 'error' : ''; ?>" style="margin-bottom: <?php echo $error2 ? '10px' : '25px'; ?>;" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <i class="zmdi zmdi-account"></i>
                </div>
                <?php if ($error2): ?>
                    <div class="error-message" style="color: red;"><?php echo htmlspecialchars($error2); ?></div>
                <?php endif; ?>
                <div class="form-wrapper">
                    <input type="password" name="password" id="password" required placeholder="Password" class="form-control <?php echo $error3 ? 'error' : ''; ?>" style="margin-bottom: <?php echo $error3 ? '10px' : '25px'; ?>;" value="<?php echo isset($_POST['password']) ? htmlspecialchars($_POST['password']) : ''; ?>">
                    <i class="zmdi zmdi-lock" id="togglePassword" style="cursor: pointer;"></i>
                </div>
                <?php if ($error3): ?>
                    <div class="error-message" style="color: red;"><?php echo htmlspecialchars($error3); ?></div>
                <?php endif; ?>

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
<script>
const inputsWithErrors = document.querySelectorAll('.form-control.error');

inputsWithErrors.forEach(input => {
    input.addEventListener('focus', function() {
        input.classList.remove('error');

        const errorDiv = input.closest('.form-wrapper').nextElementSibling;

        if (errorDiv && errorDiv.classList.contains('error-message')) {
            errorDiv.style.display = 'none'; 
        }
    });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get("success") === "true") {
        const dialog = document.getElementById("successDialog");
        if (dialog) {
            dialog.showModal();

            window.history.replaceState({}, document.title, "register.php");
        }
    }

    document.getElementById("closeDialog").addEventListener("click", function () {
        document.getElementById("successDialog").close();
        window.location.href = "login.php"; 
    });
});

document.getElementById("togglePassword").addEventListener("click", function() {
    var passwordInput = document.getElementById("password");
    var icon = document.getElementById("togglePassword");

    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove("zmdi-lock");
        icon.classList.add("zmdi-lock-open");
    } else {
        passwordInput.type = "password";
        icon.classList.remove("zmdi-lock-open");
        icon.classList.add("zmdi-lock");
    }
});
</script>

</html>
