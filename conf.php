<?php
    $host = "localhost";
    $dbname = "my_micheleferrari";
    $username = "micheleferrari";
    $password = "";

    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Connection Error: " . $e->getMessage());
    }
?>