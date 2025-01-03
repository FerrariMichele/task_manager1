<?php
  if (!isset($conn) || $conn == null) {
      require 'conf.php';
  }

  session_start();
  if (isset($_SESSION['username'])) {
      header("Location: index.php");
      exit();
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

      // Default profile picture
      $profilePicture = 'nopfp.png'; 

      // Handle profile picture upload
      if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] === UPLOAD_ERR_OK) {
          $targetDir = "uploads/";

          // Get file extension
          $fileInfo = pathinfo($_FILES["profilePicture"]["name"]);
          $extension = strtolower($fileInfo['extension']); // Ensure the extension is lowercase

          // Set the new file name as username.extension
          $timestamp = time();
          $profilePicture = $username . "_$timestamp." . $extension;
          $targetFilePath = $targetDir . $profilePicture;

      }

      // Check if passwords match
      if ($password != $passwordConf) {
          echo "<script>alert('Passwords do not match!');</script>";
      } else {
          // Check if username or email already exists
          $stmt = $conn->prepare("SELECT * FROM tm1_users WHERE username = :username OR email = :email");
          $stmt->bindParam(':username', $username);
          $stmt->bindParam(':email', $email);
          $stmt->execute();

          if ($stmt->rowCount() > 0) {
              echo "<script>alert('Username or email already in use!');</script>";
          } else {
              // Hash the password before saving it to the database
              $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

              // Insert the new user
              $stmt = $conn->prepare("INSERT INTO tm1_users (username, email, password, date_birth, name, surname, pfp_image_url) VALUES (:username, :email, :password, :dob, :name, :surname, :profilePicture)");
              $stmt->bindParam(':username', $username);
              $stmt->bindParam(':email', $email);
              $stmt->bindParam(':password', $hashedPassword);
              $stmt->bindParam(':dob', $dob);
              $stmt->bindParam(':name', $name);
              $stmt->bindParam(':surname', $surname);
              $stmt->bindParam(':profilePicture', $profilePicture);

              if ($stmt->execute()) {
                    $_SESSION['username'] = $username;
                    // Move the uploaded file to the target directory
                    if (!move_uploaded_file($_FILES["profilePicture"]["tmp_name"], $targetFilePath)) {
                        echo "<script>alert('Failed to upload profile picture. Using default.');</script>";
                        $profilePicture = 'nopfp.png'; // Revert to default if upload fails
                    }
                    echo "<script>alert('Registration successful!');</script>";
                    header("Location: login.php");
                    exit();
              } else {
                  echo "<script>alert('Error during registration. Please try again.');</script>";
              }
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
            <a class="navbar-brand">
                <img src="img/tanger_no_bg.png" alt="Logo" width="120vw" class="d-inline-block align-text-top">
            </a>
        </div>
    </nav>

    <div class="container d-flex justify-content-center mt-5">
		<form method="POST" enctype="multipart/form-data">
            <h1 class="text-center mb-4" style="font-size: 2.5rem; font-weight: bold;">REGISTER</h1>

            <div class="row g-3">

                <!-- Username -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="text" name="username" id="username" class="form-control" required />
                        <label class="form-label" for="username">Username</label>
                    </div>
                </div>

                <!-- Email -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="email" name="email" id="email" class="form-control" required />
                        <label class="form-label" for="email">Email</label>
                    </div>
                </div>

                <!-- Password -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="password" name="password" id="password" class="form-control" required />
                        <label class="form-label" for="password">Password</label>
                    </div>
                </div>

                <!-- Password Confirmation -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="password" name="passwordConf" id="passwordConf" class="form-control" required />
                        <label class="form-label" for="passwordConf">Confirm Password</label>
                    </div>
                </div>

                <!-- Date of Birth -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="date" name="dob" id="dob" class="form-control" required />
                        <label class="form-label" for="dob">Date of Birth</label>
                    </div>
                </div>

                <!-- Name -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="text" name="name" id="name" class="form-control" required />
                        <label class="form-label" for="name">Name</label>
                    </div>
                </div>

                <!-- Surname -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="text" name="surname" id="surname" class="form-control" required />
                        <label class="form-label" for="surname">Surname</label>
                    </div>
                </div>

                <!-- Profile Picture -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="file" name="profilePicture" id="profilePicture" class="form-control" accept="image/*"/>
                        <label class="form-label" for="profilePicture">Profile Picture</label>
                    </div>
                </div>

            </div>

            <!-- Submit Button -->
            <div class="d-flex justify-content-center">
                <button type="submit" class="btn btn-block btn-mb-4" style="background: rgb(248, 179, 2)">Register</button>
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
