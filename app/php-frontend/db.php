<?php
session_start(); // Start session for user routing/auth

$host = '127.0.0.1';
$db   = 'cs6400_sp26_team040'; // Your MySQL database name
$user = 'root';
$pass = 'Dylan&Jasmin92868'; // Your MySQL password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper variables for routing logic
$isLoggedIn = isset($_SESSION['username']);
$isAcq = $isLoggedIn && $_SESSION['is_acq'] == 1;
$isSales = $isLoggedIn && $_SESSION['is_sales'] == 1;
$isManager = $isLoggedIn && $_SESSION['is_manager'] == 1;
?>