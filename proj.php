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
    $query = 'SELECT DISTINCT t.id, t.title, t.description, t.date_creation, t.priority_level, t.date_completion, t.id_project, p.title AS project_title
                FROM tm1_tasks t
                JOIN tm1_projects p ON t.id_project = p.id
                ';

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
        // Get the project ID from the POST data
        $projectId = $_POST['delete'];
    
        try {
            // Start transaction to ensure all deletions are atomic
            $conn->beginTransaction();
    
            // Step 1: Delete related rows from tm1_user_task (tasks associated with the project)
            $stmt = $conn->prepare("DELETE FROM tm1_user_task WHERE id_task IN (SELECT id FROM tm1_tasks WHERE id_project = :projectId)");
            $stmt->bindParam(':projectId', $projectId, PDO::PARAM_INT);  // Ensure the project ID is bound as an integer
            $stmt->execute();
    
            // Step 2: Delete related rows from tm1_user_project (user-project associations)
            $stmt = $conn->prepare("DELETE FROM tm1_user_project WHERE id_project = :projectId");
            $stmt->bindParam(':projectId', $projectId, PDO::PARAM_INT);
            $stmt->execute();
    
            // Step 3: Delete related rows from tm1_edits (edits associated with tasks in the project)
            $stmt = $conn->prepare("DELETE FROM tm1_edits WHERE id_task IN (SELECT id FROM tm1_tasks WHERE id_project = :projectId)");
            $stmt->bindParam(':projectId', $projectId, PDO::PARAM_INT);
            $stmt->execute();
    
            // Step 4: Delete tasks from tm1_tasks (tasks related to the project)
            $stmt = $conn->prepare("DELETE FROM tm1_tasks WHERE id_project = :projectId");
            $stmt->bindParam(':projectId', $projectId, PDO::PARAM_INT);
            $stmt->execute();

            // Step 5: Delete files from tm1_project_files (files related to the project)
            $stmt = $conn->prepare('SELECT filename FROM tm1_project_files WHERE id_project = :projectId');
            $stmt->bindParam(':projectId', $projectId, PDO::PARAM_);
            $stmt->execute();
    
            // Step 6: Finally, delete the project from tm1_projects
            $stmt = $conn->prepare("DELETE FROM tm1_projects WHERE id = :projectId");
            $stmt->bindParam(':projectId', $projectId, PDO::PARAM_INT);
            $stmt->execute();
    
            // Commit the transaction
            $conn->commit();
    
            // Redirect to the projects page or show a success message
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Project deleted successfully!'
            ];
            header("Location: myproj.php");
            exit();
        } catch (Exception $e) {
            // Rollback the transaction if any error occurs
            $conn->rollBack();
            // Log the error (you can log it to a file or handle as needed)
            error_log("Error deleting project: " . $e->getMessage());
            
            // Provide a more informative error message
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Error deleting project. Please try again. ' . $e->getMessage()
            ];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
        $targetDir = "uploads/";
        $fileName = basename($_FILES["file"]["name"]);
        $targetFilePath = $targetDir . $fileName;
    
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
            // Insert file details into the database
            $stmt = $conn->prepare("INSERT INTO tm1_project_files (filename, id_project, uploader) VALUES (:filename, :id_project, :uploader)");
            $stmt->bindValue(":filename", $fileName);
            $stmt->bindValue(":id_project", $id, PDO::PARAM_INT);
            $stmt->bindValue(":uploader", $username);
            $stmt->execute();
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'File uploaded successfully!'
            ];
            header("Location: proj.php?id=$id");
            exit();
        } else {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'File upload failed.'
            ];
            header("Location: proj.php?id=$id");
            exit();
        }
    }    

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
        $fileId = $_POST['delete_file'];
    
        // Delete the file record and the file itself
        $stmt = $conn->prepare('SELECT filename FROM tm1_project_files WHERE id = :id');
        $stmt->bindValue(':id', $fileId, PDO::PARAM_INT);
        $stmt->execute();
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($file) {
            $filePath = 'uploads/' . $file['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $stmt = $conn->prepare('DELETE FROM tm1_project_files WHERE id = :id');
            $stmt->bindValue(':id', $fileId, PDO::PARAM_INT);
            $stmt->execute();
    
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'File deleted successfully!'
            ];
            header("Location: proj.php?id=$id");
            exit();
        } else {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Error: File not found.'
            ];
            header("Location: proj.php?id=$id");
            exit();
        }
    }
    
    // Handle form submission for creating a new task
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $priority = $_POST['priority'];
        $dateCompletion = $_POST['due_date'];
        $dateCreation = date('Y-m-d');

        $query = 'INSERT INTO tm1_tasks (title, description, date_creation, priority_level, date_completion, id_project)
                VALUES (:title, :description, :date_creation, :priority_level, :date_completion, :id_project)';
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->bindValue(':date_creation', $dateCreation, PDO::PARAM_STR);
        $stmt->bindValue(':priority_level', $priority, PDO::PARAM_STR);
        $stmt->bindValue(':date_completion', $dateCompletion, PDO::PARAM_STR);
        $stmt->bindValue(':id_project', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Task created successfully.'
            ];
        } else {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Error creating task.'
            ];
        }
        header("Location: proj.php?id=$id");
        exit();
    }

    // Handle task deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task_id'])) {
        $taskId = $_POST['delete_task_id'];
        
        try {
            // Start a transaction to ensure both deletions happen together
            $conn->beginTransaction();

            // Delete related rows from tm1_user_task table
            $deleteUserTaskStmt = $conn->prepare("DELETE FROM tm1_user_task WHERE id_task = :taskId");
            $deleteUserTaskStmt->bindParam(':taskId', $taskId, PDO::PARAM_INT);
            $deleteUserTaskStmt->execute();

            // Delete related rows from tm1_user_task table
            $deleteUserTaskStmt = $conn->prepare("DELETE FROM tm1_edits WHERE id_task = :taskId");
            $deleteUserTaskStmt->bindParam(':taskId', $taskId, PDO::PARAM_INT);
            $deleteUserTaskStmt->execute();

            // Now delete the task from tm1_tasks table
            $deleteTaskStmt = $conn->prepare("DELETE FROM tm1_tasks WHERE id = :taskId");
            $deleteTaskStmt->bindParam(':taskId', $taskId, PDO::PARAM_INT);
            $deleteTaskStmt->execute();

            // Commit the transaction if both deletions are successful
            $conn->commit();

            // Display success message and reload the page to reflect changes
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Task and related data deleted successfully.'
            ];
            header("Location: proj.php?id=$id");
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            // Display error message
            echo '<div class="alert alert-danger mt-3">Error deleting task: ' . $e->getMessage() . '</div>';
        $_SESSION['toast'] = [
            'type' => 'danger',
            'message' => 'Error deleting task: ' . $e->getMessage()
        ];
        header("Location: proj.php?id=$id");
        exit();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['task_id'])) {
        $username = $_POST['username'];
        $taskId = $_POST['task_id'];
    
        try {
            // Insert user-task assignment into tm1_user_task table
            $insertUserTaskStmt = $conn->prepare("
                INSERT INTO tm1_user_task (id_user, id_task, advancement_perc) 
                VALUES (:userId, :taskId, 0)
            ");
            $insertUserTaskStmt->bindParam(':userId', $username, PDO::PARAM_STR); // Use PDO::PARAM_STR if id_user is not an INT

            $insertUserTaskStmt->bindParam(':taskId', $taskId, PDO::PARAM_INT);
            $insertUserTaskStmt->execute();

            // Display success message and reload the page to reflect changes
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'User successfully added to task.'
            ];
            header("Location: proj.php?id=$id");
            exit();

        } catch (PDOException $e) {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Error adding user to task: ' . $e->getMessage()
            ];
            header("Location: proj.php?id=$id");
            exit();
        }
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
                                        else{
                                            echo '
                                            <form method="post" id="deleteForm">
                                                <input type="hidden" name="delete" value="'. $project['id'] .'">
                                                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#confirmDeleteModal">
                                                    Delete Project
                                                </button>
                                            </form>';
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
                                            echo '<img src="uploads/'. htmlspecialchars($row['pfp_image_url']).'" alt="Profile Picture" width="40" height="40" class="rounded-circle"><span>    </span>';
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
                        <div class="col-md-12 d-flex">
                            <div class="card flex-fill shadow-sm" style="width: 100%;">
                                <div class="card-header">
                                    <strong>Project Files</strong>
                                </div>
                                <div class="card-body">
                                    <?php
                                    // Fetch project files and their details
                                    $query = 'SELECT pf.id, pf.filename, pf.uploader
                                            FROM tm1_project_files pf
                                            WHERE pf.id_project = :id';

                                    $stmt = $conn->prepare($query);
                                    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
                                    $stmt->execute();
                                    $projectFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    if (empty($projectFiles)) {
                                        echo "<p>No files found.</p>";
                                    } else {
                                        echo '<ul class="list-group mb-3">';
                                        foreach ($projectFiles as $file) {
                                            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                            echo '<div>';
                                            echo '<a href="uploads/' . htmlspecialchars($file['filename'], ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($file['filename'], ENT_QUOTES, 'UTF-8') . '</a>';
                                            echo ' <small class="text-muted">uploaded by ' . htmlspecialchars($file['uploader'], ENT_QUOTES, 'UTF-8') . '</small>';
                                            echo '</div>';

                                            if($project['role_name'] !== 'viewer') {
                                                // Inside the loop to display each file
                                                echo '<div class""><form method="post" style="display: inline;">
                                                <input type="hidden" name="delete_file" value="' . htmlspecialchars($file['id'], ENT_QUOTES, 'UTF-8') . '">
                                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal" data-fileid="' . htmlspecialchars($file['id'], ENT_QUOTES, 'UTF-8') . '">Delete</button>
                                                </form>';
                                                echo '<a href="uploads/' . htmlspecialchars($file['filename'], ENT_QUOTES, 'UTF-8') . '" class="btn btn-success btn-sm" download>Download</a></div>';
                                                echo '</li>';
                                            }

                                        }
                                        echo '</ul>';
                                    }
                                    ?>

                                    <?php if ($project['role_name'] !== 'viewer'): ?>
                                        <form method="post" enctype="multipart/form-data">
                                            <input type="hidden" name="upload_file" value="1">
                                            <div class="mb-3">
                                                <label for="fileUpload" class="form-label"><strong>Upload New File</strong></label>
                                                <input type="file" class="form-control" id="fileUpload" name="file" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Upload File</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-5 mt-5 text-center">
                        <?php
                        foreach ($tasks as $task) {
                            // Fetch participants and their advancement percentages
                            $participantsStmt = $conn->prepare("
                                SELECT u.username, ut.advancement_perc, e.date_modification 
                                FROM tm1_user_task ut
                                LEFT JOIN tm1_users u ON ut.id_user = u.username
                                LEFT JOIN tm1_edits e ON ut.id_task = e.id_task
                                WHERE ut.id_task = :taskId
                                ORDER BY ut.advancement_perc DESC, e.date_modification DESC
                            ");
                            $participantsStmt->bindParam(':taskId', $task['id'], PDO::PARAM_INT);
                            $participantsStmt->execute();
                            $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                            $participantsStmt->bindParam(':taskId', $task['id'], PDO::PARAM_INT);
                            $participantsStmt->execute();
                            $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);

                            // Fetch users for the project (username instead of user ID)
                            $usersStmt = $conn->prepare("
                                SELECT id_user
                                FROM tm1_user_project 
                                WHERE id_project = :projectId
                            ");
                            $usersStmt->bindParam(':projectId', $task['id_project'], PDO::PARAM_INT);
                            $usersStmt->execute();
                            $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

                            // Display task details in a card
                            echo '<div class="col-md-3 d-flex mb-2">';
                            echo '<div class="card flex-fill" style="width: 100%;">';

                            echo '<div class="card-body">';
                            echo '<h5 class="card-title">' . htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') . '</h5>';
                            echo '<p class="card-text">' . htmlspecialchars($task['description'], ENT_QUOTES, 'UTF-8') . '</p>';
                            echo '<p class="card-text"><strong>Project: </strong>' . htmlspecialchars($task['project_title'], ENT_QUOTES, 'UTF-8') . '</p>';
                            echo '</div>';

                            echo '<ul class="list-group list-group-flush">';
                            echo '<li class="list-group-item">Priority: ' . htmlspecialchars($task['priority_level'], ENT_QUOTES, 'UTF-8') . '</li>';
                            echo '<li class="list-group-item">Created on: ' . htmlspecialchars($task['date_creation'], ENT_QUOTES, 'UTF-8') . '</li>';
                            echo '<li class="list-group-item">Due Date: ' . htmlspecialchars($task['date_completion'], ENT_QUOTES, 'UTF-8') . '</li>';
                            echo '<li class="list-group-item"><strong>Participants:</strong>';

                            // Display participants
                            echo '<ul>';
                            foreach ($participants as $participant) {
                                echo '<li>' . htmlspecialchars($participant['username'], ENT_QUOTES, 'UTF-8') .
                                    ' - Advancement: ' . htmlspecialchars($participant['advancement_perc'], ENT_QUOTES, 'UTF-8') . '%</li>';
                            }
                            echo '</ul></li>';

                            // Display the last edit date
                            if (!empty($participants) && $participants[0]['date_modification']) {
                                echo '<li class="list-group-item">Last Edit: ' . htmlspecialchars($participants[0]['date_modification'], ENT_QUOTES, 'UTF-8') . '</li>';
                            }


                            if ($project['role_name'] !== 'viewer') {
                                echo '<li class="list-group-item">';
                                echo '<form method="post" class="d-inline">';
                                echo '<label for="addUserToTask' . htmlspecialchars($task['id'], ENT_QUOTES, 'UTF-8') . '" class="form-label">Add User to Task</label>';
                                echo '<select id="addUserToTask' . htmlspecialchars($task['id'], ENT_QUOTES, 'UTF-8') . '" name="username" class="form-select mt-2">';
                                
                                // Populate the dropdown with users
                                foreach ($users as $user) {
                                    echo '<option value="' . htmlspecialchars($user['id_user'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($user['id_user'], ENT_QUOTES, 'UTF-8') . '</option>';
                                }
                                
                                echo '</select>';
                                echo '<button type="submit" class="btn btn-success btn-sm mt-2">Add User</button>';
                                echo '<input type="hidden" name="task_id" value="' . htmlspecialchars($task['id'], ENT_QUOTES, 'UTF-8') . '">';
                                echo '</form>';
                                echo '</li>';
                            }
                            echo '</ul>';
                            // Buttons section
                            echo '<div class="card-body d-flex justify-content-between">';
                            echo '<a href="task-details.php?id=' . urlencode($task['id']) . '" class="btn btn-primary btn-sm">View Details</a>';

                            if ($project['role_name'] !== 'viewer') {
                                echo '<a href="edit-task.php?id=' . urlencode($task['id']) . '" class="btn btn-secondary btn-sm">Edit Task</a>';
                                echo '<button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteTaskModal" data-taskid="' . htmlspecialchars($task['id'], ENT_QUOTES, 'UTF-8') . '">Delete Task</button>';
                            }
                            echo '</div>';

                            echo '</div>';
                            echo '</div>';
                        }
                    ?>
                    </div>



                    <?php if ($project['role_name'] !== 'viewer'): ?>
                    <div class="row mb-2 text-center">
                        <div class="col-md-12 d-flex">
                            <div class="card flex-fill shadow-sm" style="width: 100%;">
                                <div class="card-header">
                                    <strong>Create New Task</strong>
                                </div>
                                <div class="card-body">
                                        <form method="post">
                                            <input type="hidden" name="create_task" value="1">
                                            <div class="mb-3">
                                                <label for="taskTitle" class="form-label"><strong>Task Title</strong></label>
                                                <input type="text" class="form-control" id="taskTitle" name="title" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="taskDescription" class="form-label"><strong>Task Description</strong></label>
                                                <textarea class="form-control" id="taskDescription" name="description" rows="3" required></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label for="taskPriority" class="form-label"><strong>Priority Level (1-10)</strong></label>
                                                <input type="number" class="form-control" id="taskPriority" name="priority" min="1" max="10" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="taskDueDate" class="form-label"><strong>Due Date</strong></label>
                                                <input type="date" class="form-control" id="taskDueDate" name="due_date" required>
                                            </div>

                                            <script>
                                                // Set the minimum value for the due date input to tomorrow's date
                                                const dueDateInput = document.getElementById('taskDueDate');
                                                const today = new Date();
                                                const tomorrow = new Date(today);
                                                tomorrow.setDate(today.getDate() + 1); // Add 1 day to today's date
                                                const minDate = tomorrow.toISOString().split('T')[0]; // Format as YYYY-MM-DD
                                                dueDateInput.min = minDate;
                                            </script>

                                            <button type="submit" class="btn btn-primary">Create Task</button>
                                        </form>

                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

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

                            setTimeout(() => {
                                toast.hide();  // Close the toast programmatically
                            }, 7000);

                            toastList.forEach(toast => toast.show());
                        });
                    </script>

                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" role="dialog" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this project? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm File Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this file?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" id="deleteFileForm">
                        <input type="hidden" name="delete_file" id="deleteFileId">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for task deletion confirmation -->
    <div class="modal fade" id="deleteTaskModal" tabindex="-1" aria-labelledby="deleteTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteTaskModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this task? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteTaskForm" method="post" style="display: inline;">
                        <input type="hidden" name="delete_task_id" id="deleteTaskId" value="">
                        <button type="submit" class="btn btn-danger">Delete Task</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Include jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            // Programmatically submit the form when delete is confirmed
            document.getElementById('deleteForm').submit();
        });

        document.addEventListener('DOMContentLoaded', function () {
            var deleteModal = document.getElementById('deleteModal');
            deleteModal.addEventListener('show.bs.modal', function (event) {
                // Button that triggered the modal
                var button = event.relatedTarget;
                // Extract file ID from data-* attributes
                var fileId = button.getAttribute('data-fileid');
                // Update the form to include the file ID
                var deleteFileInput = document.getElementById('deleteFileId');
                deleteFileInput.value = fileId;
            });
        });

        // JavaScript to handle modal task ID population
        document.addEventListener("DOMContentLoaded", function() {
            const deleteButtons = document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target="#deleteTaskModal"]');
            
            deleteButtons.forEach(button => {
                button.addEventListener("click", function() {
                    const taskId = this.getAttribute('data-taskid');
                    document.getElementById('deleteTaskId').value = taskId; // Set the task ID to the hidden input field
                });
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>
