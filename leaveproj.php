<?php
if (!isset($conn) || $conn == null) {
    require 'conf.php';
}

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION["username"];

// Validate project ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: errorpage.php");
    exit();
}

try {
    $result = deleteUserProjectLink($conn, $username, $id);
    if ($result) {
        $message = 'You left the project';
    } else {
        $message = 'Unable to leave the project. Try again later.';
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}


function deleteUserProjectLink($conn, $username, $projectId) {
    // Validate project ID
    if (!is_int($projectId) || $projectId <= 0) {
        throw new InvalidArgumentException("Invalid project ID.");
    }

    // Prepare the SQL query
    $query = "DELETE FROM tm1_user_project 
              WHERE id_user = :username
              AND id_project = :projectId";

    // Use prepared statements to avoid SQL injection
    $stmt = $conn->prepare($query);

    if ($stmt) {
        // Bind the parameters
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->bindValue(':projectId', $projectId, PDO::PARAM_INT);

        // Execute the query
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                return true; // Row deleted successfully
            } else {
                return false; // No rows matched
            }
        } else {
            throw new Exception("Execution failed: " . implode(", ", $stmt->errorInfo()));
        }
    } else {
        throw new Exception("Statement preparation failed: " . implode(", ", $conn->errorInfo()));
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tanger - Leave </title>
    <link rel="icon" type="image/x-icon" href="img/tanger_favi.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>

<body style="background-color: rgb(255, 245, 242);">

    <nav>
        <div class="container-fluid d-flex justify-content-center" style="background-color: coral;">
            <a class="navbar-brand">
                <img src="img/tanger_no_bg.png" alt="Logo" width="120vw" class="d-inline-block align-text-top">
            </a>
        </div>
    </nav>

    <div class="container d-flex justify-content-center mt-5">
        <div class="text-center">
            <h1 class="display-1"><?php echo isset($message) ? $message : ''; ?></h1>
            <p class="lead">If it was a mistake contact the project owner</p>
            <img src="img/proud_tangerine.webp" alt="Good Bye" class="img-fluid mb-4" style="max-width: 400px;">
            <div class="d-flex justify-content-center">
                <a href="index.php" class="btn btn-lg" style="background: rgb(248, 179, 2)">Let's go Home</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>