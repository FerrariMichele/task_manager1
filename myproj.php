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

    // Unified query to fetch all projects
    $query = "
    SELECT DISTINCT 
        p.id, 
        p.title, 
        p.description, 
        p.date_creation, 
        p.id_creator, 
        COALESCE(r.role_name, 'creator') AS role_name,
        (CASE WHEN p.id_creator = :username THEN 'creator' ELSE 'participant' END) AS user_role
    FROM 
        tm1_projects p
    LEFT JOIN 
        tm1_user_project up ON p.id = up.id_project
    LEFT JOIN 
        tm1_roles r ON up.id_role = r.id
    LEFT JOIN 
        tm1_users u ON up.id_user = u.username
    WHERE 
        p.id_creator = :username OR u.username = :username";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

            <div class="col content">
                <div class="container-fluid px-0">
                    <div class="row mb-2 text-center">
                        <strong><?= date("l, F j, Y") ?></strong>
                    </div>

                    <!-- Section for "Your Projects" -->
                    <div class="row mb-2">
                        <p>Your Projects:</p>
                    </div>
                    <div class="row mb-2">
                        <?php
                        $displayedProjects = [];
                        foreach ($projects as $project) {
                            if ($project['user_role'] === 'creator' && !in_array($project['id'], $displayedProjects)) {
                                $displayedProjects[] = $project['id'];
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
                                            <a href="proj.php?id=' . urlencode($project['id']) . '" class="card-link">View Details</a>
                                        </div>
                                    </div>
                                </div>';
                            }
                        }
                        if (empty($displayedProjects)) {
                            echo "<p>No projects found.</p>";
                        }
                        ?>
                    </div>

                    <!-- Section for "Projects You Are Part Of" -->
                    <div class="row mb-2">
                        <p>Projects You Are Part Of:</p>
                    </div>
                    <div class="row mb-2">
                        <?php
                        foreach ($projects as $project) {
                            if ($project['user_role'] === 'participant' && !in_array($project['id'], $displayedProjects)) {
                                $displayedProjects[] = $project['id'];
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
                                            <a href="proj.php?id=' . urlencode($project['id']) . '" class="card-link">View Details</a>
                                            <a href="leaveproj.php?id=' . urlencode($project['id']) . '" class="card-link">Leave Project</a>
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