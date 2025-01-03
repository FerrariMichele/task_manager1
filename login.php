<?php
if (!isset($conn) || $conn == null) {
    require 'conf.php';
}

session_start();
if (isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validate inputs
    if (empty($username) || empty($password)) {
        echo "<script>alert('Please fill in both fields.');</script>";
    } else {
        // Check the database for the user
        $stmt = $conn->prepare("SELECT * FROM tm1_users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify the password
            if (password_verify($password, $user['password'])) {
                // Store username in session
                $_SESSION['username'] = $user['username'];

                // Redirect to the index page
                header("Location: index.php");
                exit();
            } else {
                echo "<script>alert('Incorrect username and password combination.');</script>";
            }
        } else {
            echo "<script>alert('Incorrect username and password combination.');</script>";
        }
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tanger - Login</title>
    <link rel="icon" type="image/x-icon" href="img/tanger_favi.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>

<body style="background-color: rgb(255, 245, 242);">

    <nav>
        <div class="container-fluid d-flex justify-content-center" style="background-color: coral;">
            <a class="navbar-brand">
                <img src="img/tanger_no_bg.png" alt="Logo" width="120vw" class="d-inline-block align-text-top">
            </a>
        </div>
    </nav>

    <div class="container d-flex justify-content-center mt-5">
        <form method="POST">
            <h1 class="text-center mb-4" style="font-size: 2.5rem; font-weight: bold;">LOGIN</h1>

            <!-- Username -->
            <div data-mdb-input-init class="form-outline mb-4">
                <input type="text" name="username" id="username" class="form-control" required />
                <label class="form-label" for="username">Username</label>
            </div>

            <!-- Password -->
            <div data-mdb-input-init class="form-outline mb-4">
                <input type="password" name="password" id="password" class="form-control" required />
                <label class="form-label" for="password">Password</label>
            </div>

            <!-- Submit button -->
            <div class="d-flex justify-content-center">
                <button type="submit" class="btn btn-block btn-mb-4" style="background: rgb(248, 179, 2)">Sign in</button>
            </div>

            <!-- Register link -->
            <div class="text-center mt-4">
                <p>Not a member? <a href="register.php">Register</a></p>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>