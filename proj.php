<?php
    // Database connection
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

    // Fetch user profile picture
    $query = "SELECT pfp_image_url FROM tm1_users WHERE username = :username";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(":username", $username);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $profile_picture = $row['pfp_image_url'] ?? "nopfp.png"; // Default if no profile picture

    // Validate project ID
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        header("Location: errorpage.php");
        exit();
    }

    // Fetch project details, user role, and associated tasks
    $query = 'SELECT p.id, p.title, p.description, p.date_creation, p.id_creator, r.role_name 
            FROM tm1_projects p
            JOIN tm1_user_project up ON p.id = up.id_project
            JOIN tm1_roles r ON up.id_role = r.id
            WHERE p.id = :id AND up.id_user = :username';

    $stmt = $conn->prepare($query);
    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->bindValue(":username", $username);
    $stmt->execute();
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        header("Location: errorpage.php");
        exit();
    }

    // Fetch associated tasks
    $query = 'SELECT t.id, t.title, t.description, t.date_creation, t.priority_level, t.date_completion, ut.advancement_perc, ut.id_user 
        FROM tm1_tasks t
        LEFT JOIN tm1_user_task ut ON t.id = ut.id_task
        WHERE t.id_project = :id';

    $stmt = $conn->prepare($query);
    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
        $projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_STRING);
        $newRole = filter_input(INPUT_POST, 'new_role', FILTER_VALIDATE_INT);
    
        if ($projectId && $userId && $newRole) {
            try {
                // Update role in the database
                $query = "UPDATE tm1_user_project 
                          SET id_role = :new_role 
                          WHERE id_project = :project_id AND id_user = :user_id";
                $stmt = $conn->prepare($query);
                $stmt->bindValue(':new_role', $newRole, PDO::PARAM_INT);
                $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
                $stmt->execute();
    
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Role updated successfully!'
                ];
            } catch (PDOException $e) {
                $_SESSION['toast'] = [
                    'type' => 'danger',
                    'message' => 'Error updating role: ' . $e->getMessage()
                ];
            }
        } else {
            $_SESSION['toast'] = [
                'type' => 'warning',
                'message' => 'Invalid input. Please try again.'
            ];
        }
    
        header("Location: proj.php?id=$projectId");
        exit();
    }

    // Handle project update if the form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
        $projectTitle = filter_input(INPUT_POST, 'projectTitle', FILTER_SANITIZE_FULL_SPECIAL_CHARS); // Changed to FILTER_SANITIZE_FULL_SPECIAL_CHARS
        $projectDescription = filter_input(INPUT_POST, 'projectDescription', FILTER_SANITIZE_FULL_SPECIAL_CHARS); // Changed to FILTER_SANITIZE_FULL_SPECIAL_CHARS

        // Validate the input data
        if (strlen($projectTitle) > 0 && strlen($projectTitle) <= 50 && strlen($projectDescription) > 0 && strlen($projectDescription) <= 256) {
            try {
                // Ensure the user is the project creator
                if ($username === $project['id_creator']) {
                    $updateQuery = "UPDATE tm1_projects SET title = :title, description = :description WHERE id = :id";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bindValue(':title', $projectTitle, PDO::PARAM_STR);
                    $updateStmt->bindValue(':description', $projectDescription, PDO::PARAM_STR);
                    $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);
                    $updateStmt->execute();

                    $_SESSION['toast'] = [
                        'type' => 'success',
                        'message' => 'Project updated successfully!'
                    ];
                } else {
                    $_SESSION['toast'] = [
                        'type' => 'danger',
                        'message' => 'You do not have permission to update this project.'
                    ];
                }
            } catch (PDOException $e) {
                $_SESSION['toast'] = [
                    'type' => 'danger',
                    'message' => 'Error updating project: ' . $e->getMessage()
                ];
            }
        } else {
            $_SESSION['toast'] = [
                'type' => 'warning',
                'message' => 'Title or description is too long or empty.'
            ];
        }

        // Redirect back to the project page to show the updated details
        header("Location: proj.php?id=$id");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
        $newMemberUsername = filter_input(INPUT_POST, 'new_member_username', FILTER_SANITIZE_STRING);

        if ($newMemberUsername) {
            try {
            // Check if the user exists
            $query = "SELECT username FROM tm1_users WHERE username = :username";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':username', $newMemberUsername, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Add the user to the project with a default role (e.g., viewer)
                $query = "INSERT INTO tm1_user_project (id_project, id_user, id_role) VALUES (:project_id, :user_id, 3)";
                $stmt = $conn->prepare($query);
                $stmt->bindValue(':project_id', $id, PDO::PARAM_INT);
                $stmt->bindValue(':user_id', $newMemberUsername, PDO::PARAM_STR);
                $stmt->execute();

                $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Member added successfully!'
                ];
            } else {
                $_SESSION['toast'] = [
                'type' => 'warning',
                'message' => 'User does not exist.'
                ];
            }
            } catch (PDOException $e) {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Error adding member.'
            ];
            }
        } else {
            $_SESSION['toast'] = [
            'type' => 'warning',
            'message' => 'Invalid input. Please try again.'
            ];
        }

        // Redirect back to the project page to show the updated details
        header("Location: proj.php?id=$id");
        exit();
    }
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tanger - Project <?php echo $id; ?></title>
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
                    <div class="row mb-2 text-center">
                        <div class="col-md-12 d-flex">
                            <div class="card flex-fill shadow-sm" style="width: 100%;">
                                <div class="card-header">
                                    <strong>Project Information</strong>
                                </div>
                                <div class="card-body">
                                    
                                    <?php if ($username === $project['id_creator']): ?>
                                        <form method="post">
                                            <input type="hidden" name="update_project" value="1">
                                            <div class="mb-3">
                                                <label for="projectTitle" class="form-label"><strong>Project Title</strong></label>
                                                <input type="text" class="form-control" id="projectTitle" name="projectTitle" maxlength="50" placeholder="Title - Max. 50 Characters" value="<?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label for="projectDescription" class="form-label"><strong>Project Description</strong></label>
                                                <textarea class="form-control" id="projectDescription" name="projectDescription" maxlength="256" placeholder="Description - Max. 256 Characters" rows="3"><?= htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                            </div>
                                            <button type="submit" class="btn mb-3"  style="background: rgb(248, 179, 2)">Update Project</button>
                                        </form>
                                    <?php else: ?>
                                        <h5 class="card-title"><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></h5>
                                        <p class="card-text"><?= htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>

                                    <p class="card-text"><strong>Created on: </strong><?= htmlspecialchars($project['date_creation'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="card-text"><strong>Your role: </strong><?= htmlspecialchars($project['role_name'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php
                                        if ($username !== $project['id_creator']) {
                                            echo '<p class="card-text"><strong>Project Owner: </strong>' . htmlspecialchars($project['id_creator'], ENT_QUOTES, 'UTF-8') . '</p>';
                                            echo '<a href="leaveproj.php?id=' . urlencode($project['id']) . '" class="card-link">Leave Project</a>';
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-2 text-center">
                        <div class="col-md-12 d-flex">
                            <div class="card flex-fill shadow-sm" style="width: 100%;">
                                <div class="card-header">
                                    <strong>Project Members</strong>
                                </div>
                                <div class="card-body">
                                    <?php
                                    // Fetch project details and associated users/roles
                                    $query = 'SELECT p.id, p.title, p.description, p.date_creation, p.id_creator, 
                                                    r.role_name, u.username, u.pfp_image_url
                                            FROM tm1_projects p
                                            JOIN tm1_user_project up ON p.id = up.id_project
                                            JOIN tm1_roles r ON up.id_role = r.id
                                            JOIN tm1_users u ON up.id_user = u.username
                                            WHERE p.id = :id';

                                    $stmt = $conn->prepare($query);
                                    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
                                    $stmt->execute();
                                    $projectDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    if (empty($projectDetails)) {
                                        echo "<p>No members found.</p>";
                                    } else {
                                        echo '<ul class="list-group mb-3">';
                                        foreach ($projectDetails as $row) {
                                            echo '<li class="list-group-item d-flex justify-content-between align-items-center"><div>';                                            
                                            echo '<img src="uploads/'. htmlspecialchars($row['pfp_image_url']).'" alt="Profile Picture" width="40" height="40" class="rounded-circle">';
                                            echo htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8');
                                            echo '<span>    </span><span class="badge bg-primary rounded-pill">' . htmlspecialchars($row['role_name'], ENT_QUOTES, 'UTF-8') . '</span></div>';
                                            if ($username == $project['id_creator'] && $row['username'] !== $project['id_creator']) {
                                                echo '<form method="post" action="">
                                                <input type="hidden" name="change_role" value="1">
                                                <input type="hidden" name="project_id" value="' . htmlspecialchars($project['id'], ENT_QUOTES, 'UTF-8') . '">
                                                <input type="hidden" name="user_id" value="' . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . '">
                                                <select name="new_role" class="form-select form-select-sm d-inline w-auto">
                                                    <option value="2"' . ($row['role_name'] === 'admin' ? ' selected' : '') . '>Admin</option>
                                                    <option value="3"' . ($row['role_name'] === 'viewer' ? ' selected' : '') . '>Viewer</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary ms-2">Change</button>
                                                </form>';
                                            }
                                            echo '</li>';
                                        }
                                        echo '</ul>';
                                    }
                                    ?>
                                    <?php if ($username === $project['id_creator']): ?>
                                        <form method="post">
                                            <input type="hidden" name="add_member" value="1">
                                            <div class="mb-3">
                                                <label for="newMemberUsername" class="form-label"><strong>Add New Member</strong></label>
                                                <input type="text" class="form-control" id="newMemberUsername" name="new_member_username" placeholder="Enter username" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Add Member</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-2 text-center">
                        <?php
                            if (empty($tasks)) {
                                echo "<p>No tasks found.</p>";
                            } else {
                                foreach ($tasks as $index => $task) {

                                }
                            }
                        ?>
                    </div>
                    
                    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1055;">
                        <?php if (isset($_SESSION['toast'])): ?>
                            <div class="toast align-items-center text-bg-<?= htmlspecialchars($_SESSION['toast']['type']) ?> border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                                <div class="d-flex">
                                    <div class="toast-body">
                                        <?= htmlspecialchars($_SESSION['toast']['message']) ?>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                            </div>
                            <?php unset($_SESSION['toast']); ?>
                        <?php endif; ?>
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const toastElList = [].slice.call(document.querySelectorAll('.toast'));
                            const toastList = toastElList.map(function (toastEl) {
                                return new bootstrap.Toast(toastEl);
                            });

                            toastList.forEach(toast => toast.show());
                        });
                    </script>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>
