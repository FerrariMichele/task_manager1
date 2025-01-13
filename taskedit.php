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

    $username = htmlspecialchars($_SESSION['username']);

    $id = $_GET['id'] ?? null;
    if (!$id) {
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

    // Fetch user role
    $roleCheckQuery = 'SELECT id_role FROM tm1_user_project WHERE id_user = :username AND id_project = (SELECT id_project FROM tm1_tasks WHERE id = :id)';
    $stmt = $conn->prepare($roleCheckQuery);
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $userRole = $stmt->fetchColumn();

    // Check if the user has permission to edit
    if (!in_array($userRole, [1, 2])) {
        header("Location: errorpage.php");
        exit();
    }


    // Fetch task details
    $query = 'SELECT * FROM tm1_tasks WHERE id = :id';
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        header("Location: errorpage.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
        $title = htmlspecialchars($_POST['title']);
        $description = htmlspecialchars($_POST['description']);
        $priority = htmlspecialchars($_POST['priority_level']);
        $dateStart = htmlspecialchars($_POST['date_start']);
        $dateCompletion = htmlspecialchars($_POST['date_completion']);
        if($dateStart > $dateCompletion) {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Start Date can\'t be past Due date'
            ];
            header("Location: taskedit.php?id=" . $id);
            exit();
        }
        $isCompleted = isset($_POST['is_completed']) ? 1 : 0;

        $updateQuery = 'UPDATE tm1_tasks SET 
            title = :title, 
            description = :description, 
            priority_level = :priority_level, 
            date_start = :date_start, 
            date_completion = :date_completion, 
            is_completed = :is_completed
            WHERE id = :id';

        $stmt = $conn->prepare($updateQuery);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->bindValue(':priority_level', $priority, PDO::PARAM_INT);
        $stmt->bindValue(':date_start', $dateStart, PDO::PARAM_STR);
        $stmt->bindValue(':date_completion', $dateCompletion, PDO::PARAM_STR);
        $stmt->bindValue(':is_completed', $isCompleted, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        try {
            $stmt->execute();
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Task updated successfully.'
            ];
        } catch (PDOException $e) {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Error updating task: ' . $e->getMessage()
            ];
        }

        header("Location: taskedit.php?id=" . $id);
        exit();
    }
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Task - Task <?php echo $id; ?></title>
    <link rel="icon" type="image/x-icon" href="img/tanger_favi.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

                    <div class="row mb-2 text-center">
                        <div class="col-md-12 d-flex">
                            <div class="card flex-fill shadow-sm" style="width: 100%;">
                                <div class="card-header">
                                    <strong>Edit Task Information</strong>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="">
                                        <div class="mb-3">
                                            <label for="title" class="form-label">Task Title</label>
                                            <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="4" required><?= htmlspecialchars($task['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="priority_level" class="form-label">Priority Level</label>
                                            <input type="number" class="form-control" id="priority_level" name="priority_level" min="1" max="10" required value="<?= htmlspecialchars($task['priority_level'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="date_start" class="form-label">Start Date</label>
                                            <input type="date" class="form-control" id="date_start" name="date_start" value="<?= htmlspecialchars($task['date_start'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="date_completion" class="form-label">Completion Date</label>
                                            <input type="date" class="form-control" id="date_completion" name="date_completion" value="<?= htmlspecialchars($task['date_completion'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                        <div class="mb-3 d-flex align-items-center">
                                            <input class="form-check-input me-2" type="checkbox" id="is_completed" name="is_completed" <?= $task['is_completed'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_completed">Mark as Completed</label>
                                        </div>
                                        <button type="submit" name="update_task" class="btn btn-primary">Save Changes</button>
                                    </form>
                                    <a href="proj.php?id=<?= htmlspecialchars($task['id_project'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary mt-3">Back to Project</a>
                                </div>
                            </div>
                        </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
