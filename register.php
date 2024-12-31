<?php
    /*if (!isset($conn) || $conn == null) {
        require 'conf.php';
    }*/

    session_start();
    if(isset($_SESSION['username'])){
        header("Location: index.php");
    }else{
        header("");
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        require 'conf.php';

        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $passwordConf = $_POST['passwordConf'];
        $dob = $_POST['dob'];
        $name = $_POST['name'];
        $surname = $_POST['surname'];
        
        if(isset($_POST['profilePicture'])){
            $profilePicture = $_POST['profilePicture'];
        } else{
            $profilePicture = 'nopfp.png';
        }

        $profilePicture = $_POST['profilePicture'];

        if ($password != $passwordConf) {

            echo "<script>alert('Passwords do not match!');</script>";

        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                echo "<script>alert('Username or email already in use!');</script>";
            } else {
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, dob, name, surname, profilePicture) VALUES (:username, :email, :password, :dob, :name, :surname, :profilePicture)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $password);
                $stmt->bindParam(':dob', $dob);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':surname', $surname);
                $stmt->bindParam(':profilePicture', $profilePicture);
                $stmt->execute();

                echo "<script>alert('Registration successful!');</script>";
                header("Location: login.php");
            }
        }
    }

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tanger - Register</title>
    <link rel="icon" type="image/x-icon" href="img/tanger_favi.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>

<body style="background-color: rgb(255, 245, 242);">

    <nav>
        <div class="container-fluid d-flex justify-content-center" style="background-color: coral;">
            <a class="navbar-brand" href="#">
                <img src="img/tanger_no_bg.png" alt="Logo" width="120vw" class="d-inline-block align-text-top">
            </a>
        </div>
    </nav>

    <div class="container d-flex justify-content-center mt-5">
        <form method="POST">
            <h1 class="text-center mb-4" style="font-size: 2.5rem; font-weight: bold;">REGISTER</h1>
            
            <div class="row g-3">

                <!-- Username -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="text" id="username" class="form-control" required />
                        <label class="form-label" for="username">Username</label>
                    </div>
                </div>

                <!-- Email -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="email" id="email" class="form-control" required />
                        <label class="form-label" for="email">Email</label>
                    </div>
                </div>

                <!-- Password -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="password" id="password" class="form-control" required />
                        <label class="form-label" for="password">Password</label>
                    </div>
                </div>

                <!-- Password Confirmation -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="password" id="passwordConf" class="form-control" required />
                        <label class="form-label" for="passwordConf">Password</label>
                    </div>
                </div>

                <!-- Date of Birth -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="date" id="dob" class="form-control" required />
                        <label class="form-label" for="dob">Date of Birth</label>
                    </div>
                </div>

                <!-- Name -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="text" id="name" class="form-control" required />
                        <label class="form-label" for="name">Name</label>
                    </div>
                </div>

                <!-- Surname -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="text" id="surname" class="form-control" required />
                        <label class="form-label" for="surname">Surname</label>
                    </div>
                </div>

                <!-- Profile Picture -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="file" id="profilePicture" class="form-control" accept="image/*"/> <!-- Se non mette l'immagine viene assegnata quella standard-->
                        <label class="form-label" for="profilePicture">Profile Picture</label>
                    </div>
                </div>

            </div>

            <!-- Submit Button -->
            <div class="d-flex justify-content-center">
                <button type="submit" data-mdb-button-init data-mdb-ripple-init class="btn btn-block btn-mb-4" style="background: rgb(248, 179, 2)">Register</button>
            </div>

            <!-- Sign-in Link -->
            <div class="text-center mt-4">
                <p>Already a member? <a href="login.php">Sign in</a></p>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>
