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
    $stmt->bindValue(':priority_level', $priority, PDO::PARAM_STR);
    $stmt->bindValue(':date_start', $dateStart, PDO::PARAM_STR);
    $stmt->bindValue(':date_completion', $dateCompletion, PDO::PARAM_STR);
    $stmt->bindValue(':is_completed', $isCompleted, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);

    try {
        $stmt->execute();
        $_SESSION['success_message'] = "Task updated successfully.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error updating task: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
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
            <div class="col-auto sidebar">
                <div class="d-flex flex-column align-items-center align-items-sm-start px-3 pt-2 text-black min-vh-100">
                    <a class="navbar-brand mb-4 mx-auto">
                        <img src="img/tanger_no_bg.png" alt="Logo" width="150vw" class="d-inline-block align-text-top mx-auto d-block">
                    </a>
                    <ul class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-center align-items-sm-start" id="menu">
                        <li class="mb-2">
                            <a href="index.php" class="nav-link text-black px-0 align-middle">
                                <span class="ms-2 d-none d-sm-inline"><b>Dashboard</b></span>
                            </a>
                        </li>
                    </ul>
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
                                    <?php if (isset($_SESSION['success_message'])): ?>
                                        <div class="alert alert-success"> <?= $_SESSION['success_message']; ?> </div>
                                        <?php unset($_SESSION['success_message']); ?>
                                    <?php endif; ?>
                                    <?php if (isset($_SESSION['error_message'])): ?>
                                        <div class="alert alert-danger"> <?= $_SESSION['error_message']; ?> </div>
                                        <?php unset($_SESSION['error_message']); ?>
                                    <?php endif; ?>

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
                                            <select class="form-select" id="priority_level" name="priority_level" required>
                                                <option value="Low" <?= $task['priority_level'] === 'Low' ? 'selected' : '' ?>>Low</option>
                                                <option value="Medium" <?= $task['priority_level'] === 'Medium' ? 'selected' : '' ?>>Medium</option>
                                                <option value="High" <?= $task['priority_level'] === 'High' ? 'selected' : '' ?>>High</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="date_start" class="form-label">Start Date</label>
                                            <input type="date" class="form-control" id="date_start" name="date_start" value="<?= htmlspecialchars($task['date_start'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="date_completion" class="form-label">Completion Date</label>
                                            <input type="date" class="form-control" id="date_completion" name="date_completion" value="<?= htmlspecialchars($task['date_completion'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_completed" name="is_completed" <?= $task['is_completed'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="is_completed">Mark as Completed</label>
                                            </div>
                                        </div>
                                        <button type="submit" name="update_task" class="btn btn-primary">Save Changes</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
