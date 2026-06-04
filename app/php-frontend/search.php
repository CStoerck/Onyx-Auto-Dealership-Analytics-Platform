<?php 
require 'db.php'; require 'header.php'; 

// Dashboards Metrics (Auth Only)
if ($isLoggedIn) {
    $availStmt = $pdo->query("SELECT COUNT(v.VIN) AS cnt FROM Vehicle v LEFT JOIN Sale s ON v.VIN = s.VIN WHERE s.VIN IS NULL AND v.VIN NOT IN (SELECT VIN FROM Part WHERE Status IN ('Ordered', 'Received'))");
    $pendingStmt = $pdo->query("SELECT COUNT(v.VIN) AS cnt FROM Vehicle v LEFT JOIN Sale s ON v.VIN = s.VIN WHERE s.VIN IS NULL AND v.VIN IN (SELECT VIN FROM Part WHERE Status IN ('Ordered', 'Received'))");
    
    echo "<h3>Metrics: Available for Sale: " . $availStmt->fetch()['cnt'] . " | Pending Parts: " . $pendingStmt->fetch()['cnt'] . "</h3><hr>";
}

// Build Dynamic SQL
$sql = "SELECT 
            v.VIN, v.ModelYear, v.MfgName, v.ModelName, v.TypeName, v.Condition, v.PurchPrice,
            (v.PurchPrice * 1.25 + COALESCE(pc.TotalPartsCost, 0) * 1.1) AS SalePrice,
            CASE WHEN MAX(s.VIN) IS NOT NULL THEN 'Sold' ELSE 'Unsold' END AS SaleStatus,
            GROUP_CONCAT(DISTINCT vc.Color ORDER BY vc.Color SEPARATOR ', ') AS Colors
        FROM Vehicle v
        LEFT JOIN Sale s ON v.VIN = s.VIN
        LEFT JOIN VehicleColor vc ON v.VIN = vc.VIN
        LEFT JOIN (
            SELECT po.VIN, SUM(p.UnitPrice * p.Quantity) AS TotalPartsCost
            FROM PartsOrder po 
            JOIN Part p ON po.VIN = p.VIN AND po.OrderNum = p.OrderNum
            WHERE p.Status = 'Installed'
            GROUP BY po.VIN
        ) pc ON v.VIN = pc.VIN WHERE 1=1 ";
$params = [];

// Role Based Logic — check highest privilege first so owner (all roles) hits manager branch
if ($isManager) {
    if (!empty($_GET['status_filter'])) {
        if ($_GET['status_filter'] == 'Sold')   $sql .= " AND s.VIN IS NOT NULL ";
        if ($_GET['status_filter'] == 'Unsold') $sql .= " AND s.VIN IS NULL ";
        // 'All' or no filter — no WHERE restriction, show everything
    }
} elseif ($isAcq) {
    $sql .= " AND s.VIN IS NULL ";
} elseif ($isSales || !$isLoggedIn) {
    $sql .= " AND s.VIN IS NULL AND v.VIN NOT IN (SELECT VIN FROM Part WHERE Status IN ('Ordered', 'Received')) ";
}

// Form Inputs
if (!empty($_GET['type']))      { $sql .= " AND v.TypeName = ?";  $params[] = $_GET['type']; }
if (!empty($_GET['mfg']))       { $sql .= " AND v.MfgName = ?";   $params[] = $_GET['mfg']; }
if (!empty($_GET['year']))      { $sql .= " AND v.ModelYear = ?";  $params[] = $_GET['year']; }
if (!empty($_GET['fuel']))      { $sql .= " AND v.FuelType = ?";   $params[] = $_GET['fuel']; }
if (!empty($_GET['drivetrain'])){ $sql .= " AND v.Drivetrain = ?"; $params[] = $_GET['drivetrain']; }
if (!empty($_GET['color']))     { $sql .= " AND v.VIN IN (SELECT VIN FROM VehicleColor WHERE Color = ?)"; $params[] = $_GET['color']; }
if ($isLoggedIn && !empty($_GET['vin'])) { $sql .= " AND v.VIN = ?"; $params[] = $_GET['vin']; }
if (!empty($_GET['keyword'])) {
    $sql .= " AND (v.MfgName LIKE ? OR v.ModelName LIKE ? OR CAST(v.ModelYear AS CHAR) LIKE ? OR v.Notes LIKE ?)";
    $kw = '%' . $_GET['keyword'] . '%';
    array_push($params, $kw, $kw, $kw, $kw);
}

$sql .= " GROUP BY v.VIN ORDER BY v.VIN ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();
?>

<h2>Search Vehicles</h2>
<form method="GET" style="margin-bottom: 20px;">
    <select name="type">
        <option value="">All Types</option>
        <?php foreach (['Convertible','Coupe','Minivan','Sedan','SUV','Truck','Other'] as $t): ?>
            <option value="<?= $t ?>"<?= ($_GET['type'] ?? '') == $t ? ' selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="mfg" placeholder="Manufacturer" value="<?= htmlspecialchars($_GET['mfg'] ?? '') ?>">
    <input type="number" name="year" placeholder="Model Year" value="<?= htmlspecialchars($_GET['year'] ?? '') ?>">
    <select name="fuel">
        <option value="">All Fuel Types</option>
        <?php foreach (['Gas','Diesel','Hybrid','Plugin Hybrid','Battery','Natural Gas'] as $f): ?>
            <option value="<?= $f ?>"<?= ($_GET['fuel'] ?? '') == $f ? ' selected' : '' ?>><?= $f ?></option>
        <?php endforeach; ?>
    </select>
    <select name="drivetrain">
        <option value="">All Drivetrains</option>
        <?php foreach (['FWD','RWD','AWD','4WD'] as $d): ?>
            <option value="<?= $d ?>"<?= ($_GET['drivetrain'] ?? '') == $d ? ' selected' : '' ?>><?= $d ?></option>
        <?php endforeach; ?>
    </select>
    <select name="color">
        <option value="">All Colors</option>
        <?php foreach ($pdo->query("SELECT DISTINCT Color FROM VehicleColor ORDER BY Color") as $c): ?>
            <option value="<?= htmlspecialchars($c['Color']) ?>"<?= ($_GET['color'] ?? '') == $c['Color'] ? ' selected' : '' ?>><?= htmlspecialchars($c['Color']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if($isLoggedIn): ?> <input type="text" name="vin" placeholder="Search by VIN" value="<?= htmlspecialchars($_GET['vin'] ?? '') ?>"> <?php endif; ?>
    <input type="text" name="keyword" placeholder="Keyword" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>">
    <?php if($isManager): ?>
        <select name="status_filter">
            <option value="">All Statuses</option>
            <option value="Sold"<?= ($_GET['status_filter'] ?? '') == 'Sold' ? ' selected' : '' ?>>Sold</option>
            <option value="Unsold"<?= ($_GET['status_filter'] ?? '') == 'Unsold' ? ' selected' : '' ?>>Unsold</option>
        </select>
    <?php endif; ?>
    <button type="submit">Search</button>
</form>

<table>
    <tr><th>VIN</th><th>Vehicle</th><th>Condition</th><th>Colors</th><th>Price</th><th>Status</th></tr>
    <?php foreach($vehicles as $v): ?>
    <tr>
        <td><a href="vehicle_detail.php?vin=<?= $v['VIN'] ?>"><?= htmlspecialchars($v['VIN']) ?></a></td>
        <td><?= $v['ModelYear'] ?> <?= htmlspecialchars($v['MfgName']) ?> <?= htmlspecialchars($v['ModelName']) ?></td>
        <td><?= htmlspecialchars($v['Condition']) ?></td>
        <td><?= htmlspecialchars($v['Colors'] ?? 'N/A') ?></td>
        <td>$<?= number_format($v['SalePrice'], 2) ?></td>
        <td><?= $v['SaleStatus'] ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($vehicles)) echo "<tr><td colspan='6'>Sorry, it looks like we don't have that in stock!</td></tr>"; ?>
</table>
</div></body></html>