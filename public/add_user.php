<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User</title>
</head>
<body>
    <h2>Add a New User</h2>
    <form action="process_user.php" method="post">
        <label for="idno">ID Number:</label>
        <input type="number" name="idno" required><br>

        <label for="lastname">Last Name:</label>
        <input type="text" name="lastname" required><br>

        <label for="firstname">First Name:</label>
        <input type="text" name="firstname" required><br>

        <label for="middlename">Middle Name:</label>
        <input type="text" name="middlename"><br>

        <label for="course">Course:</label>
        <input type="text" name="course"><br>

        <label for="level">Level:</label>
        <input type="text" name="level"><br>

        <label for="email">Email:</label>
        <input type="email" name="email" required><br>

        <label for="username">Username:</label>
        <input type="text" name="username" required><br>

        <label for="password">Password:</label>
        <input type="password" name="password" required><br>

        <label for="role">Role:</label>
        <select name="role">
            <option value="admin">Admin</option>
            <option value="user">User</option>
        </select><br>

        <button type="submit">Add User</button>
    </form>
</body>
</html>
