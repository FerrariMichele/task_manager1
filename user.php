<?php

    if (!isset($conn) || $conn == null) {
        require 'conf.php';
    }

    session_start();

    if (!isset($_SESSION['username'])) {
        header("Location: login.php");
        exit();
    }

    $username = $_SESSION['username'];

    // Fetch current user data
    $stmt = $conn->prepare("SELECT * FROM tm1_users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $email = $_POST['email'] ?? $user['email'];
        
        // Password Handling
        if (!empty($_POST['password']) && !empty($_POST['passwordConf'])) {
            if ($_POST['password'] === $_POST['passwordConf']) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            } else {
                echo "<script>alert('Passwords do not match.');</script>";
                exit();
            }
        } else {
            $password = $user['password']; // Use the existing password hash
        }

        $dob = $_POST['dob'] ?? $user['date_birth'];
        $name = $_POST['name'] ?? $user['name'];
        $surname = $_POST['surname'] ?? $user['surname'];
        $profilePicture = $user['pfp_image_url'];
    
        if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "uploads/";
            $fileInfo = pathinfo($_FILES["profilePicture"]["name"]);
            $extension = strtolower($fileInfo['extension']);
            
            // Validate file extension and MIME type
            $validExtensions = ['jpg', 'jpeg', 'png', 'gif']; // Add allowed extensions here
            $mimeType = mime_content_type($_FILES["profilePicture"]["tmp_name"]);
            $validMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
            
            if (!in_array($extension, $validExtensions) || !in_array($mimeType, $validMimeTypes)) {
                echo "<script>alert('Invalid file type. Please upload a valid image.');</script>";
            } else {
                $timestamp = time();
                $newProfilePicture = $username . "_$timestamp." . $extension;
                $targetFilePath = $targetDir . $newProfilePicture;
        
                // Backup or remove the old file
                if (!empty($user['pfp_image_url']) && file_exists($targetDir . $user['pfp_image_url'])) {
                    $backupFilePath = $targetDir . "backup_" . pathinfo($user['pfp_image_url'], PATHINFO_FILENAME) . ".$timestamp." . pathinfo($user['pfp_image_url'], PATHINFO_EXTENSION);
                    rename($targetDir . $user['pfp_image_url'], $backupFilePath);
                }
        
                // Move the uploaded file
                if (move_uploaded_file($_FILES["profilePicture"]["tmp_name"], $targetFilePath)) {
                    $profilePicture = $newProfilePicture;
                } else {
                    echo "<script>alert('Failed to upload profile picture. Retaining current picture.');</script>";
                }
            }
        }

        // Update database
        $stmt = $conn->prepare("
            UPDATE tm1_users 
            SET email = :email, date_birth = :dob, name = :name, surname = :surname, pfp_image_url = :profilePicture, password = :password 
            WHERE username = :username
        ");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':dob', $dob);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':surname', $surname);
        $stmt->bindParam(':profilePicture', $profilePicture);
        $stmt->bindParam(':password', $password);
    
        if ($stmt->execute()) {
            echo "<script>alert('Profile updated successfully!');</script>";
            header("Location: user.php");
            exit();
        } else {
            echo "<script>alert('Error updating profile. Please try again.');</script>";
        }
    }

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tanger - Edit Profile</title>
    <link rel="icon" type="image/x-icon" href="img/tanger_favi.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>

<body style="background-color: rgb(255, 245, 242);">

    <nav>
        <div class="container-fluid d-flex justify-content-center" style="background-color: coral;">
            <a class="navbar-brand" href='index.php'>
                <img src="img/tanger_no_bg.png" alt="Logo" width="120vw" class="d-inline-block align-text-top">
            </a>
        </div>
    </nav>

    <div class="container d-flex justify-content-center mt-5">
        <form method="POST" enctype="multipart/form-data">
            <h1 class="text-center mb-4" style="font-size: 2.5rem; font-weight: bold;">EDIT PROFILE</h1>

            <div class="row g-3">

                <!-- Current Profile Picture -->
                <div class="col-md-12 text-center">
                    <img src="uploads/<?php echo htmlspecialchars($user['pfp_image_url']); ?>" alt="Profile Picture" width="150" height="150" class="img-thumbnail">
                    <label class="form-label d-block mt-2" for="profilePicture">Current Profile Picture</label>
                </div>

                <!-- username -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input disabled type="username" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>"/>
                        <label class="form-label" for="username">Username</label>
                    </div>
                </div>

                <!-- Email -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required />
                        <label class="form-label" for="email">Email</label>
                    </div>
                </div>

                <!-- Password -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="password" id="password" name="password" class="form-control"/>
                        <label class="form-label" for="password">Password</label>
                    </div>
                </div>

                <!-- Password Confirmation -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="password" id="passwordConf" name="passwordConf" class="form-control"/>
                        <label class="form-label" for="passwordConf">Confirm Password</label>
                    </div>
                </div>

                <!-- Date of Birth -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="date" id="dob" name="dob" class="form-control" value="<?php echo htmlspecialchars($user['date_birth']); ?>" required />
                        <label class="form-label" for="dob">Date of Birth</label>
                    </div>
                </div>

                <!-- Name -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required />
                        <label class="form-label" for="name">Name</label>
                    </div>
                </div>

                <!-- Surname -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="text" id="surname" name="surname" class="form-control" value="<?php echo htmlspecialchars($user['surname']); ?>" required />
                        <label class="form-label" for="surname">Surname</label>
                    </div>
                </div>

                <!-- Profile Picture -->
                <div class="col-md-6">
                    <div class="form-outline">
                        <input type="file" id="profilePicture" name="profilePicture" class="form-control" accept="image/*" />
                        <label class="form-label" for="profilePicture">New Profile Picture</label>
                    </div>
                </div>

            </div>

            <div class="row g-3">

                <!-- Submit Button -->
                <div class="d-flex justify-content-center mt-4">
                    <button type="submit" class="btn btn-block btn-mb-4" style="background: rgb(248, 179, 2)">Update</button>
                </div>

                <!-- Index -->
                <div class="d-flex justify-content-center mt-4">
                    <button type="submit" class="btn btn-block btn-mb-4" style="background: rgb(248, 179, 2)" onclick="window.location.href='index.php'; return false;">Home</button>
                </div>

            </div>


        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>