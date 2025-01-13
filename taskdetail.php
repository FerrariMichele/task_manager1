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

    $id = $_GET['id'] ?? null;

    if (!$id) {
        header("Location: errorpage.php");
        exit();
    }

    // Check if the user is part of the project or task
    $accessCheckQuery = '
    SELECT 1
    FROM tm1_tasks t
    JOIN tm1_user_project up ON t.id_project = up.id_project
    WHERE up.id_user = :username AND t.id = :task_id
    ';


    $stmt = $conn->prepare($accessCheckQuery);
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->bindValue(':task_id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $hasAccess = $stmt->fetchColumn();

    if (!$hasAccess) {
        header("Location: errorpage.php");
        exit();
    }

    // Fetch user profile picture
    $query = "SELECT pfp_image_url FROM tm1_users WHERE username = :username";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(":username", $username);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $profile_picture = $row['pfp_image_url'] ?? "nopfp.png"; // Default if no profile picture

    // Fetch task details, user progress, and edits associated with the task
    $query = 'SELECT 
                t.id AS task_id, 
                t.title AS task_title, 
                t.description AS task_description, 
                t.date_creation AS task_date_creation, 
                t.priority_level, 
                t.date_start, 
                t.date_completion, 
                t.is_completed, 
                t.id_project, 
                ut.advancement_perc, 
                e.id AS edit_id, 
                e.date_modification, 
                e.time_modification, 
                e.id_user AS edit_user
            FROM tm1_tasks t
            LEFT JOIN tm1_user_task ut ON t.id = ut.id_task
            LEFT JOIN tm1_edits e ON t.id = e.id_task
            WHERE t.id = :id';

    $stmt = $conn->prepare($query);
    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $task = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$task) {
        header("Location: errorpage.php");
        exit();
    }

    // Query to fetch users associated with the task and their progress
    $users_query = 'SELECT ut.id_user, u.username, ut.advancement_perc 
                    FROM tm1_user_task ut
                    JOIN tm1_users u ON ut.id_user = u.username
                    WHERE ut.id_task = :id';
    $users_stmt = $conn->prepare($users_query);
    $users_stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query to fetch edits associated with the task
    $edits_query = 'SELECT e.id, e.date_modification, e.time_modification, e.id_user
                    FROM tm1_edits e
                    WHERE e.id_task = :id';
    $edits_stmt = $conn->prepare($edits_query);
    $edits_stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $edits_stmt->execute();
    $edits = $edits_stmt->fetchAll(PDO::FETCH_ASSOC);


    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_advancement'])) {
        // Retrieve and sanitize inputs
        $taskId = filter_input(INPUT_POST, 'task_id', FILTER_SANITIZE_NUMBER_INT);
        $advancementPerc = filter_input(INPUT_POST, 'advancementPerc', FILTER_SANITIZE_NUMBER_INT);

        // Validate inputs
        if ($taskId && $advancementPerc !== false && $advancementPerc >= 0 && $advancementPerc <= 100) {

            $conn->beginTransaction(); // Start transaction

            try {
                // Update the user's advancement percentage for the task
                $updateQuery = "UPDATE tm1_user_task 
                                SET advancement_perc = :advancement_perc 
                                WHERE id_task = :task_id AND id_user = :username";

                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bindValue(':advancement_perc', $advancementPerc, PDO::PARAM_INT);
                $updateStmt->bindValue(':task_id', $taskId, PDO::PARAM_INT);
                $updateStmt->bindValue(':username', $username, PDO::PARAM_STR);
                $updateStmt->execute();

                // Log the change in tm1_edits
                $editQuery = "INSERT INTO tm1_edits (id, date_modification, time_modification, id_task, id_user) 
                            VALUES (NULL, :date_modification, :time_modification, :task_id, :username)";

                $editStmt = $conn->prepare($editQuery);
                $editStmt->bindValue(':date_modification', date('Y-m-d'), PDO::PARAM_STR);
                $editStmt->bindValue(':time_modification', date('H:i:s'), PDO::PARAM_STR);
                $editStmt->bindValue(':task_id', $taskId, PDO::PARAM_INT);
                $editStmt->bindValue(':username', $username, PDO::PARAM_STR);
                $editStmt->execute();

                // Commit the transaction
                $conn->commit();

                // Success message
                $_SESSION['success_message'] = "Your progress has been updated successfully.";
            } catch (PDOException $e) {
                // Rollback the transaction on error
                $conn->rollBack();

                // Error message
                $_SESSION['error_message'] = "Failed to update progress: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = "Invalid input. Please ensure your progress is a number between 0 and 100.";
        }

        // Redirect back to avoid form re-submission
        header("Location: taskdetail.php?id=".$taskId);
        exit;
    }
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tanger - Project <?php echo $id; ?></title>
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
                    <div class="row mb-2 text-center">
                        <div class="col-md-12 d-flex">
                            <div class="card flex-fill shadow-sm" style="width: 100%;">
                                <div class="card-header">
                                    <strong>Task Information</strong>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($task[0]['task_title'], ENT_QUOTES, 'UTF-8') ?></h5>
                                    <p class="card-text"><?= htmlspecialchars($task[0]['task_description'], ENT_QUOTES, 'UTF-8') ?></p>

                                    <p class="card-text"><strong>Created on: </strong><?= htmlspecialchars($task[0]['task_date_creation'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="card-text"><strong>Priority Level: </strong><?= htmlspecialchars($task[0]['priority_level'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="card-text"><strong>Start Date: </strong><?= htmlspecialchars($task[0]['date_start'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="card-text"><strong>Completion Date: </strong><?= htmlspecialchars($task[0]['date_completion'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="card-text"><strong>Completion Status: </strong><?= $task[0]['is_completed'] ? 'Completed' : 'In Progress' ?></p>

                                    <!-- Users and their progress -->
                                    <h6 class="card-subtitle mb-2 text-muted">Users and Progress</h6>
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th scope="col">Username</th>
                                                <th scope="col">Progress (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td>
                                                        <?php if ($user['id_user'] == $username): ?>
                                                            <form method="post" action="">
                                                                <input type="hidden" name="update_advancement" value="1">
                                                                <input type="hidden" name="task_id" value="<?= htmlspecialchars($task[0]['task_id'], ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="number" class="form-control" name="advancementPerc" value="<?= htmlspecialchars($user['advancement_perc'], ENT_QUOTES, 'UTF-8') ?>" min="0" max="100">
                                                                <button type="submit" class="btn btn-warning mt-2">Update Progress</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($user['advancement_perc'], ENT_QUOTES, 'UTF-8') ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                    <!-- List of edits -->
                                    <h6 class="card-subtitle mb-2 text-muted">Edit History</h6>
                                    <?php if (!empty($edits)): ?>
                                        <ul class="list-group">
                                            <?php foreach ($edits as $edit): ?>
                                                <li class="list-group-item">
                                                    <strong>Edited by:</strong> <?= htmlspecialchars($edit['id_user'], ENT_QUOTES, 'UTF-8') ?><br>
                                                    <strong>Date:</strong> <?= htmlspecialchars($edit['date_modification'], ENT_QUOTES, 'UTF-8') ?> 
                                                    <strong>Time:</strong> <?= htmlspecialchars($edit['time_modification'], ENT_QUOTES, 'UTF-8') ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>No edits made yet.</p>
                                    <?php endif; ?>
                                <a href="proj.php?id=<?= htmlspecialchars($task[0]['id_project'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary mt-3">Back to Project</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>