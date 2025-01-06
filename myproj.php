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
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tanger - Projects</title>
    <link rel="icon" type="image/x-icon" href="img/tanger_favi.png">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', { 'packages': ['corechart'] });
    </script>
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
                        Your projects:
                    </div>
                    <div class="row mb-2">
                    <?php
                        // Fetch projects linked to the user along with roles
                        $query = "SELECT 
                                    id, 
                                    title, 
                                    description, 
                                    date_creation
                                FROM 
                                    tm1_projects
                                WHERE 
                                    id_creator = :username";
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':username', $username);
                        $stmt->execute();
                        $projects_created = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (empty($projects_created)) {
                            echo "<p>No projects found.</p>";
                        } else {
                            foreach ($projects_created as $project) {
                                echo '
                                <div class="col-md-3 d-flex mb-2">
                                    <div class="card flex-fill" style="width: 100%;">
                                        <div class="card-body">
                                            <h5 class="card-title">' . htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') . '</h5>
                                            <p class="card-text">' . htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8') . '</p>
                                        </div>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item">Created on: ' . htmlspecialchars($project['date_creation'], ENT_QUOTES, 'UTF-8') . '</li>
                                        </ul>
                                        <div class="card-body">
                                            <a href="proj.php?id=' . urlencode($project['id']) . '" class="card-link">View Details</a>
                                        </div>
                                    </div>
                                </div>';
                            }
                        }                        
                        ?>
                    </div>
                    <div class="row mb-2">
                        Projects you are a part of:
                    </div>
                    <div class="row mb-2">
                    <?php
                        $query = "SELECT 
                                    p.id, 
                                    p.title, 
                                    p.description, 
                                    p.date_creation, 
                                    p.id_creator, 
                                    r.role_name
                                    FROM 
                                    tm1_projects p
                                    JOIN 
                                    tm1_user_project up ON p.id = up.id_project
                                    JOIN 
                                    tm1_roles r ON up.id_role = r.id
                                    JOIN 
                                    tm1_users u ON up.id_user = u.username
                                    WHERE 
                                    u.username = :username";
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':username', $username);
                        $stmt->execute();
                        $projects_part_of = $stmt->fetchAll(PDO::FETCH_ASSOC);

            
                        if (empty($projects_part_of)) {
                            echo "<p>No projects found.</p>";
                        } else {
                            foreach ($projects_part_of as $project) {
                                echo '
                                <div class="col-md-3 d-flex mb-2">
                                    <div class="card flex-fill" style="width: 100%;">
                                        <div class="card-body">
                                            <h5 class="card-title">' . htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') . '</h5>
                                            <p class="card-text">' . htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8') . '</p>
                                        </div>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item">Created on: ' . htmlspecialchars($project['date_creation'], ENT_QUOTES, 'UTF-8') . '</li>
                                            <li class="list-group-item">Creator ID: ' . htmlspecialchars($project['id_creator'], ENT_QUOTES, 'UTF-8') . '</li>
                                            <li class="list-group-item">Role: ' . htmlspecialchars($project['role_name'], ENT_QUOTES, 'UTF-8') . '</li>
                                        </ul>
                                        <div class="card-body">
                                            <a href="proj.php?id=' . urlencode($project['id']) . '" class="card-link">View Details</a>';
                                            if ($project['role_name'] !== 'creator') {
                                                echo '<a href="leaveproj.php?id=' . urlencode($project['id']) . '" class="card-link">Leave Project</a>';
                                            }
                                            echo '
                                        </div>
                                    </div>
                                </div>';
                            }
                        }
                        
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>