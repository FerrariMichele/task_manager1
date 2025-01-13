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
    <title>Tanger - Tasks</title>
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
                        Your tasks:
                    </div>
                    <div class="row mb-2">
                        <?php
                        // Fetch tasks sorted by closest due date
                        $stmt = $conn->prepare("SELECT t.*, ut.advancement_perc, p.title AS project_title, up.id_role
                                                FROM tm1_tasks t
                                                JOIN tm1_user_task ut ON t.id = ut.id_task
                                                LEFT JOIN tm1_projects p ON t.id_project = p.id
                                                LEFT JOIN tm1_user_project up ON up.id_user = ut.id_user AND up.id_project = t.id_project
                                                WHERE ut.id_user = :id_user  AND t.is_completed = 0
                                                ORDER BY t.date_completion");
                        $stmt->bindParam(':id_user', $username);
                        $stmt->execute();
                        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($tasks)) {
                            echo "<p>No tasks found.</p>";
                        }
                        else {
                            foreach ($tasks as $index => $task) {
                                $advancement = htmlspecialchars($task['advancement_perc'], ENT_QUOTES, 'UTF-8');
                                echo '
                                <div class="col-md-3 d-flex mb-2">
                                    <div class="card flex-fill';
                                    if($task['date_completion'] < date("Y-m-d")) {
                                        echo ' text-bg-danger';
                                    }
                                    echo '" style="width: 100%;">
                                        <div id="chart_' . $index . '" style="width: 100%; height: 10vw;"></div>
                                        <div class="card-body">
                                            <h5 class="card-title">' . htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8');
                                            if($task['date_completion'] < date("Y-m-d")) {
                                                echo '<strong> - EXPIRED</strong>';
                                            }
                                            echo'</h5>
                                            <p class="card-text">' . htmlspecialchars($task['description'], ENT_QUOTES, 'UTF-8') . '</p>
                                            <p class="card-text"><strong>Project: </strong>' . htmlspecialchars($task['project_title'], ENT_QUOTES, 'UTF-8') . '</p>
                                        </div>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item">Priority: ' . htmlspecialchars($task['priority_level'], ENT_QUOTES, 'UTF-8') . '</li>
                                            <li class="list-group-item">Created on: ' . htmlspecialchars($task['date_creation'], ENT_QUOTES, 'UTF-8') . '</li>
                                            <li class="list-group-item">Due Date: ' . htmlspecialchars($task['date_completion'], ENT_QUOTES, 'UTF-8') . '</li>
                                            <li class="list-group-item">Advancement: ' . htmlspecialchars($task['advancement_perc'], ENT_QUOTES, 'UTF-8') . '%</li>
                                        </ul>
                                        <div class="card-body">
                                            <a href="taskdetail.php?id=' . urlencode($task['id']) . '" class="card-link">View Details</a>';
                                    if ($task['id_role'] != 3) {
                                        echo '<a href="taskedit.php?id=' . urlencode($task['id']) . '" class="card-link">Edit Task</a>';
                                    }
                                        echo '</div>
                                    </div>
                                </div>';
    
                                ?>
                                    <script type="text/javascript">
                                        function drawCharts() {
                                            // Loop through the charts to redraw them dynamically
                                            <?php foreach ($tasks as $index => $task): ?>
                                                (function () {
                                                    var container = document.getElementById("chart_<?= $index ?>");
                                                    var width = container.getBoundingClientRect().width;
                                                    var height = Math.min(container.getBoundingClientRect().height, width * 0.6); // Maintain aspect ratio
    
                                                    var data = google.visualization.arrayToDataTable([
                                                        ["Effort", "Amount"],
                                                        ["Completed", <?= $task['advancement_perc'] ?>],
                                                        ["Remaining", <?= 100 - $task['advancement_perc'] ?>]
                                                    ]);
    
                                                    var options = {
                                                        pieHole: 0.5,
                                                        pieSliceTextStyle: { color: "black" },
                                                        legend: "none",
                                                        width: width,
                                                        height: height
                                                    };
    
                                                    var chart = new google.visualization.PieChart(container);
                                                    chart.draw(data, options);
                                                })();
                                            <?php endforeach; ?>
                                        }
    
                                        google.charts.setOnLoadCallback(drawCharts);
    
                                        window.addEventListener("resize", drawCharts);
                                    </script>
                                <?php
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