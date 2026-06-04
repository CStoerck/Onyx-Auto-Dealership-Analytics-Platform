<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OnyxAuto Dealership</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f4f4f4; }
        header { background: #333; color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; }
        header a { color: #fff; text-decoration: none; margin-right: 15px; font-weight: bold; }
        .container { padding: 20px; max-width: 1200px; margin: auto; background: #fff; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #eee; }
        .error { color: red; }
    </style>
</head>
<body>
<header>
    <div>
        <a href="index.php">OnyxAuto    Home</a>
        <a href="search.php">Search Vehicles</a>
        <?php if($isAcq): ?> <a href="add_vehicle.php">Add Vehicle</a> <?php endif; ?>
        <?php if($isManager): ?> <a href="reports.php">Reports</a> <?php endif; ?>
    </div>
    <div>
        <?php if($isLoggedIn): ?>
            Welcome, <?= htmlspecialchars($_SESSION['fname']) ?>! | <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </div>
</header>
<div class="container">