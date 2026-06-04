<?php 
require 'db.php'; 
if ($isLoggedIn) { header("Location: search.php"); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $sql = "SELECT 
                u.Username, u.FirstName, u.LastName,
                CASE WHEN a.Username IS NOT NULL THEN 1 ELSE 0 END AS IsAcq,
                CASE WHEN s.Username IS NOT NULL THEN 1 ELSE 0 END AS IsSales,
                CASE WHEN m.Username IS NOT NULL THEN 1 ELSE 0 END AS IsMgr
            FROM `User` u
            LEFT JOIN AcquisitionSpecialist a ON u.Username = a.Username
            LEFT JOIN SalesAgent s ON u.Username = s.Username
            LEFT JOIN OperatingManager m ON u.Username = m.Username
            WHERE u.Username = ? AND u.Password = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user, $pass]);
    $result = $stmt->fetch();

    if ($result) {
        $_SESSION['username'] = $result['Username'];
        $_SESSION['fname'] = $result['FirstName'];
        $_SESSION['is_acq'] = $result['IsAcq'];
        $_SESSION['is_sales'] = $result['IsSales'];
        $_SESSION['is_manager'] = $result['IsMgr'];
        header("Location: search.php");
        exit;
    } else {
        $error = "Invalid Username or Password.";
    }
}
require 'header.php'; 
?>

<h2>Employee Login</h2>
<?php if($error) echo "<p class='error'>$error</p>"; ?>
<form method="POST">
    <input type="text" name="username" placeholder="Username" required><br><br>
    <input type="password" name="password" placeholder="Password" required><br><br>
    <button type="submit">Login</button>
</form>
</div></body></html>