<?php
if (!isset($conn) || $conn == null) {
    require 'conf.php';
}

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);

// Fetch profile picture
$query = "SELECT pfp_image_url FROM tm1_users WHERE username = :username";
$stmt = $conn->prepare($query);
$stmt->bindValue(":username", $username);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $row['pfp_image_url'] ?? "nopfp.png"; // Default if no profile picture

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_project'])) {
    $title = htmlspecialchars($_POST['title']);
    $description = htmlspecialchars($_POST['description']);
    $date_creation = date('Y-m-d');
    $id_creator = $username; // Username dalla sessione
    
    try {
        // Inserisci il progetto nella tabella tm1_projects
        $query = "INSERT INTO tm1_projects (title, description, date_creation, id_creator) 
                  VALUES (:title, :description, :date_creation, :id_creator)";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':date_creation', $date_creation);
        $stmt->bindValue(':id_creator', $id_creator);
        $stmt->execute();
        
        // Recupera l'ID del progetto appena creato
        $project_id = $conn->lastInsertId();

        // Inserisci nella tabella tm1_user_project con ruolo di creator (id_role = 1)
        $query = "INSERT INTO tm1_user_project (id_user, id_project, id_role) 
                  VALUES (:id_user, :id_project, :id_role)";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':id_user', $id_creator);
        $stmt->bindValue(':id_project', $project_id);
        $stmt->bindValue(':id_role', 1); // Creator
        $stmt->execute();
        echo "<script>alert('Project created successfully!');</script>";
    } catch (PDOException $e) {
        echo "<script>alert(Error: " . htmlspecialchars($e->getMessage()) . ");</script>";
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tanger - Create New Project</title>
    <link rel="icon" type="image/x-icon" href="img/tanger_favi.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <style>
        body {
            background-color: rgb(255, 245, 242);
            margin: 0;
        }

        .sidebar {
            background-color: coral;
            position: fixed;
            height: 100vh;
            width: 250px;
            overflow: hidden;
        }

        .content {
            margin-left: 250px;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }

            .content {
                margin-left: 200px;
            }
        }

        .container-fluid {
            width: 100%;
            padding: 0;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-auto sidebar">
                <div class="d-flex flex-column align-items-center align-items-sm-start px-3 pt-2 text-black min-vh-100">
                    <a class="navbar-brand mb-4 mx-auto">
                        <img src="img/tanger_no_bg.png" alt="Logo" width="150vw" class="d-inline-block align-text-top mx-auto d-block">
                    </a>
                    <ul class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-center align-items-sm-start" id="menu">
                        <li class="mb-2">
                            <a href="#submenu1" data-bs-toggle="collapse" class="nav-link text-black px-0 align-middle">
                                <i class="fs-5 bi-speedometer2"></i>
                                <span class="ms-2 d-none d-sm-inline"><b>Dashboard</b></span>
                            </a>
                            <ul class="collapse show nav flex-column ms-2" id="submenu1" data-bs-parent="#menu">
                                <li class="w-100">
                                    <a href="index.php" class="nav-link text-black px-0">
                                        <span class="d-none d-sm-inline">Home</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="mytasks.php" class="nav-link text-black px-0">
                                        <span class="d-none d-sm-inline">My Tasks</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="myproj.php" class="nav-link text-black px-0">
                                        <span class="d-none d-sm-inline">My Projects</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="newproj.php" class="nav-link text-black px-0">
                                        <span class="d-none d-sm-inline">New Project</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                    <hr class="text-black w-100">
                    <div class="dropdown pb-4">
                        <a href="#" class="d-flex align-items-center text-black text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="uploads/<?= htmlspecialchars($profile_picture); ?>" alt="Profile Picture" width="40" height="40" class="rounded-circle">
                            <span class="ms-2 d-none d-sm-inline"><?= htmlspecialchars($username); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                            <li><a class="dropdown-item" href="user.php">Profile</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <form method="post" action="">
                                    <button type="submit" name="logout" class="dropdown-item">Log Out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col content">
                <div class="container-fluid px-0">
                    <div class="row mb-2 text-center">
                        <strong><?= date("l, F j, Y") ?></strong>
                    </div>
                    <div class="row mb-2">
                        <form method="POST">
                            <h2 class="text-center mb-4" style="font-size: 2rem; font-weight: bold;">Create New Project</h2>
                            <div class="row g-3">

                                <!-- Project Title -->
                                <div class="col-md-6">
                                    <div class="form-outline">
                                        <input type="text" name="title" id="title" class="form-control" required />
                                        <label class="form-label" for="title">Project Title</label>
                                    </div>
                                </div>

                                <!-- Project Description -->
                                <div class="col-md-6">
                                    <div class="form-outline">
                                        <textarea name="description" id="description" class="form-control" rows="3" required></textarea>
                                        <label class="form-label" for="description">Project Description</label>
                                    </div>
                                </div>

                            </div>

                            <!-- Submit Button -->
                            <div class="d-flex justify-content-center mt-4">
                                <button type="submit" name="create_project" class="btn btn-block btn-mb-4" style="background: rgb(248, 179, 2)">Create Project</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>