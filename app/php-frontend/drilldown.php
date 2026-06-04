<?php
require 'db.php'; require 'header.php';
if (!$isManager) die("<div class='container'><h2 class='error'>Unauthorized</h2></div></body></html>");

if (empty($_GET['year']) || empty($_GET['month'])) {
    die("<div class='container'><p class='error'>Year and Month are required.</p></div></body></html>");
}

$year = (int)$_GET['year'];
$month = (int)$_GET['month'];

$sql = "SELECT 
            u.FirstName, 
            u.LastName, 
            COUNT(s.VIN) AS TotalVehiclesSold,
            SUM(v.PurchPrice * 1.25 + COALESCE(PartsCost.Cost, 0) * 1.1) AS TotalSales
        FROM Sale s
        JOIN Vehicle v ON s.VIN = v.VIN
        JOIN `User` u ON s.SalesAgent_Username = u.Username
        LEFT JOIN (
            SELECT po.VIN, SUM(p.UnitPrice * p.Quantity) AS Cost
            FROM PartsOrder po
            JOIN Part p ON po.VIN = p.VIN AND po.OrderNum = p.OrderNum
            GROUP BY po.VIN
        ) PartsCost ON v.VIN = PartsCost.VIN
        WHERE YEAR(s.SaleDate) = ? AND MONTH(s.SaleDate) = ?
        GROUP BY s.SalesAgent_Username, u.FirstName, u.LastName
        ORDER BY TotalVehiclesSold DESC, TotalSales DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$year, $month]);
$agents = $stmt->fetchAll();
?>

<h2>Sales Drilldown for <?= $month ?>/<?= $year ?></h2>
<table>
    <tr><th>Sales Agent</th><th>Vehicles Sold</th><th>Total Sales</th></tr>
    <?php foreach($agents as $a): ?>
    <tr>
        <td><?= htmlspecialchars($a['FirstName'] . ' ' . $a['LastName']) ?></td>
        <td><?= $a['TotalVehiclesSold'] ?></td>
        <td>$<?= number_format($a['TotalSales'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($agents)) echo "<tr><td colspan='3'>No sales recorded for this month.</td></tr>"; ?>
</table>

<br>
<a href="reports.php?report=monthly"><button>Back to Monthly Summary</button></a>

</div></body></html>