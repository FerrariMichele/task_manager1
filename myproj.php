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

    $query = "SELECT pfp_image_url FROM tm1_users WHERE username = :username";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(":username", $username);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $profile_picture = $row['pfp_image_url'] ?? "nopfp.png"; // Default if no profile picture

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_user_proj_id'], $_POST['remove_user_proj_user'])) {
        $projId = $_POST['remove_user_proj_id'];
        $username = $_POST['remove_user_proj_user'];
    
        // Start a transaction to ensure atomicity
        $conn->beginTransaction();
    
        try {
            // Step 1: Remove the user from the project
            $stmt = $conn->prepare('DELETE FROM tm1_user_project WHERE id_project = :proj_id AND id_user = :username');
            $stmt->bindValue(':proj_id', $projId, PDO::PARAM_INT);
            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
    
            // Step 2: Remove the user from tasks associated with the project
            $stmt = $conn->prepare('DELETE FROM tm1_user_task 
                                    WHERE id_task IN (
                                        SELECT id FROM tm1_tasks WHERE id_project = :proj_id
                                    ) AND id_user = :username');
            $stmt->bindValue(':proj_id', $projId, PDO::PARAM_INT);
            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
    
            // Commit the transaction
            $conn->commit();
    
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'User removed from project and associated tasks successfully!'
            ];
        } catch (Exception $e) {
            // Roll back the transaction in case of an error
            $conn->rollBack();
    
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Error: Could not remove user from project and tasks. ' . $e->getMessage()
            ];
        }
    
        // Redirect back to the project page
        header("Location: leaveproj.php");
        exit();
    } 
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
                        Your Projects:
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
                        Projects You Are Part Of:
                    </div>
                    <div class="row mb-2">
                        <?php
                        $displayedProjects = [];
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
                                        <div class="card-body d-flex align-items-center justify-content-between">
                                            <a href="proj.php?id=' . urlencode($project['id']) . '" class="card-link">View Details</a>
                                            <form method="post">
                                                <input type="hidden" name="remove_user_proj_id" value="' . htmlspecialchars($project['id'], ENT_QUOTES, 'UTF-8') . '">
                                                <input type="hidden" name="remove_user_proj_user" value="' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '">
                                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#removeUserProjModal" data-projid="' . htmlspecialchars($project['id'], ENT_QUOTES, 'UTF-8') . '" data-username="' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '">Leave Project</button>
                                            </form>
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
                </div>
            </div>

            <div class="modal fade" id="removeUserProjModal" tabindex="-1" aria-labelledby="removeUserProjModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="removeUserProjModalLabel">Confirm Removal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Are you sure you want to leave the project? This action cannot be undone.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <form id="removeUserProjForm" method="post" action="" style="display: inline;">
                                <input type="hidden" name="remove_user_proj_id" id="removeUserProjId" value="">
                                <input type="hidden" name="remove_user_proj_user" id="removeUserProjUser" value="">
                                <button type="submit" class="btn btn-danger">Leave Project</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const removeButtons = document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target="#removeUserProjModal"]');

                    removeButtons.forEach(button => {
                        button.addEventListener("click", function() {
                            const projId = this.getAttribute('data-projid');
                            const username = this.getAttribute('data-username');
                            document.getElementById('removeUserProjId').value = projId;
                            document.getElementById('removeUserProjUser').value = username;
                        });
                    });
                });
            </script>

        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>