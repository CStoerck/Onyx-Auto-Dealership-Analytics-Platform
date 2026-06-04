<?php 
require 'db.php'; 
require 'header.php'; 

if(empty($_GET['vin'])) die("<div class='container'>VIN required.</div></body></html>");
$vin = $_GET['vin'];

// Query 1: Vehicle Details without SELECT *
$sql = "SELECT 
            v.VIN, v.TypeName, v.MfgName, v.ModelName, v.ModelYear, v.FuelType, 
            v.Condition, v.Horsepower, v.Drivetrain, v.Notes, v.PurchPrice, v.PurchDate,
            (v.PurchPrice * 1.25 + COALESCE(pc.TotalPartsCost, 0) * 1.1) AS SalePrice,
            COALESCE(pc.TotalPartsCost, 0) AS TotalPartsCost,
            GROUP_CONCAT(DISTINCT vc.Color ORDER BY vc.Color SEPARATOR ', ') AS Colors,
            MAX(c.Phone) AS Phone, MAX(c.Email) AS Email,
            MAX(c.StreetAddress) AS StreetAddress, MAX(c.City) AS City,
            MAX(c.State) AS State, MAX(c.PostalCode) AS PostalCode,
            MAX(i.FirstName) AS FirstName, MAX(i.LastName) AS LastName,
            MAX(b.BusinessName) AS BusinessName, MAX(b.ContactFirstName) AS ContactFirstName,
            MAX(b.ContactLastName) AS ContactLastName, MAX(b.ContactTitle) AS ContactTitle,
            MAX(acq_u.FirstName) AS AcqFirstName, MAX(acq_u.LastName) AS AcqLastName,
            MAX(bc.Phone) AS BuyerPhone, MAX(bc.Email) AS BuyerEmail,
            MAX(bc.StreetAddress) AS BuyerStreet, MAX(bc.City) AS BuyerCity,
            MAX(bc.State) AS BuyerState, MAX(bc.PostalCode) AS BuyerPostal,
            MAX(bi.FirstName) AS BuyerFirstName, MAX(bi.LastName) AS BuyerLastName,
            MAX(bb.BusinessName) AS BuyerBizName, MAX(bb.ContactFirstName) AS BuyerContactFirst,
            MAX(bb.ContactLastName) AS BuyerContactLast, MAX(bb.ContactTitle) AS BuyerContactTitle
        FROM Vehicle v
        LEFT JOIN Customer c ON v.Purchaser_CustomerID = c.CustomerID
        LEFT JOIN Individual i ON c.CustomerID = i.CustomerID
        LEFT JOIN Business b ON c.CustomerID = b.CustomerID
        LEFT JOIN VehicleColor vc ON v.VIN = vc.VIN
        INNER JOIN `User` acq_u ON v.AcqSpecialist_Username = acq_u.Username
        LEFT JOIN Sale s2 ON v.VIN = s2.VIN
        LEFT JOIN Customer bc ON s2.Buyer_CustomerID = bc.CustomerID
        LEFT JOIN Individual bi ON bc.CustomerID = bi.CustomerID
        LEFT JOIN Business bb ON bc.CustomerID = bb.CustomerID
        LEFT JOIN (
            SELECT po.VIN, SUM(p.UnitPrice * p.Quantity) AS TotalPartsCost
            FROM PartsOrder po 
            JOIN Part p ON po.VIN = p.VIN AND po.OrderNum = p.OrderNum
            WHERE p.Status = 'Installed'
            GROUP BY po.VIN
        ) pc ON v.VIN = pc.VIN
        WHERE v.VIN = ?
        GROUP BY v.VIN";
$stmt = $pdo->prepare($sql);
$stmt->execute([$vin]);
$veh = $stmt->fetch();

if (!$veh) die("<div class='container'>Vehicle not found.</div></body></html>");

// Query 2: Pending Parts
$stmt = $pdo->prepare("SELECT COUNT(*) AS pending FROM Part WHERE VIN = ? AND Status IN ('Ordered', 'Received')");
$stmt->execute([$vin]);
$pendingCount = $stmt->fetch()['pending'];

// Query 3: Sale Data (If sold)
$sale = null;
$stmt = $pdo->prepare("SELECT s.SaleDate, s.SalesAgent_Username FROM Sale s WHERE s.VIN = ?");
$stmt->execute([$vin]);
$sale = $stmt->fetch();

// Query 4: Parts List (For Acq Specs, Managers, and Owners)
$parts = [];
if ($isAcq || $isManager || $isOwner) {
    $stmt = $pdo->prepare("SELECT p.OrderNum, p.PartNumber, p.Description, po.VendorName,
            p.UnitPrice, p.Quantity, p.Status
        FROM Part p
        INNER JOIN PartsOrder po ON p.VIN = po.VIN AND p.OrderNum = po.OrderNum
        WHERE p.VIN = ?");
    $stmt->execute([$vin]);
    $parts = $stmt->fetchAll();
}
?>

<h2><?= htmlspecialchars($veh['ModelYear'] . ' ' . $veh['MfgName'] . ' ' . $veh['ModelName']) ?></h2>
<p><strong>VIN:</strong> <?= htmlspecialchars($veh['VIN']) ?></p>
<p><strong>Type:</strong> <?= htmlspecialchars($veh['TypeName']) ?></p>
<p><strong>Condition:</strong> <?= htmlspecialchars($veh['Condition']) ?></p>
<p><strong>Manufacturer:</strong> <?= htmlspecialchars($veh['MfgName']) ?></p>
<p><strong>Model:</strong> <?= htmlspecialchars($veh['ModelName']) ?></p>
<p><strong>Year:</strong> <?= htmlspecialchars($veh['ModelYear']) ?></p>
<p><strong>Fuel Type:</strong> <?= htmlspecialchars($veh['FuelType']) ?></p>
<p><strong>Drivetrain:</strong> <?= htmlspecialchars($veh['Drivetrain']) ?></p>
<p><strong>Color(s):</strong> <?= htmlspecialchars($veh['Colors'] ?? 'N/A') ?></p>
<p><strong>Horsepower:</strong> <?= htmlspecialchars($veh['Horsepower']) ?></p>
<p><strong>Sale Price:</strong> $<?= number_format($veh['SalePrice'], 2) ?></p>
<?php if (!empty($veh['Notes'])): ?><p><strong>Notes:</strong> <?= htmlspecialchars($veh['Notes']) ?></p><?php endif; ?>

<?php if (($isSales || $isOwner) && !$sale && $pendingCount == 0): ?>
    <button onclick="window.location.href='sell_vehicle.php?vin=<?= urlencode($vin) ?>'" style="background-color: #28a745; color: white; padding: 10px;">Sell Vehicle</button>
<?php endif; ?>

<?php if ($isAcq || $isManager || $isOwner): ?>
    <hr>
    <h3>Parts Inventory</h3>
    <p><strong>Total Cost of Parts:</strong> $<?= number_format($veh['TotalPartsCost'], 2) ?></p>
    <?php if ($isAcq || $isOwner): ?>
        <p><strong>Original Purchase Price:</strong> $<?= number_format($veh['PurchPrice'], 2) ?></p>
        <p><strong>Purchase Date:</strong> <?= htmlspecialchars($veh['PurchDate']) ?></p>
        <button onclick="window.location.href='add_part.php?vin=<?= urlencode($vin) ?>'" style="margin-bottom: 15px;">+ Add Parts Order</button>
    <?php endif; ?>

    <?php if (count($parts) > 0): ?>
        <table>
            <tr>
                <th>Order Num</th>
                <th>Part Num</th>
                <th>Description</th>
                <th>Vendor</th>
                <th>Unit Price</th>
                <th>Quantity</th>
                <th>Status</th>
                <?php if ($isAcq || $isOwner): ?><th>Action</th><?php endif; ?>
            </tr>
            <?php foreach ($parts as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['OrderNum']) ?></td>
                <td><?= htmlspecialchars($p['PartNumber']) ?></td>
                <td><?= htmlspecialchars($p['Description']) ?></td>
                <td><?= htmlspecialchars($p['VendorName']) ?></td>
                <td>$<?= number_format($p['UnitPrice'], 2) ?></td>
                <td><?= htmlspecialchars($p['Quantity']) ?></td>
                <td><?= htmlspecialchars($p['Status']) ?></td>
                <?php if ($isAcq || $isOwner): ?>
                    <td>
                        <?php if ($p['Status'] != 'Installed'): ?>
                            <a href="update_part.php?vin=<?= urlencode($vin) ?>&order=<?= urlencode($p['OrderNum']) ?>&part=<?= urlencode($p['PartNumber']) ?>">Update</a>
                        <?php else: ?>
                            <span style="color: gray;">Done</span>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No parts ordered for this vehicle.</p>
    <?php endif; ?>
<?php endif; ?>

<?php if ($isManager || $isOwner): ?>
    <hr>
    <h3>Manager View</h3>
    <p><strong>Purchased On:</strong> <?= htmlspecialchars($veh['PurchDate']) ?></p>
    <p><strong>Acquisition Specialist:</strong> <?= htmlspecialchars($veh['AcqFirstName'] . ' ' . $veh['AcqLastName']) ?></p>
    <p><strong>Seller:</strong> <?= htmlspecialchars($veh['BusinessName'] ?? $veh['FirstName'] . ' ' . $veh['LastName']) ?></p>
    <p><strong>Seller Contact:</strong> <?= htmlspecialchars($veh['Phone']) ?> | <?= htmlspecialchars($veh['Email'] ?? 'N/A') ?></p>
    <p><strong>Seller Address:</strong> <?= htmlspecialchars($veh['StreetAddress'] . ', ' . $veh['City'] . ', ' . $veh['State'] . ' ' . $veh['PostalCode']) ?></p>

    <?php if ($sale): ?>
        <p style="color: green; font-size: 1.2em;"><strong>SOLD</strong> on <?= htmlspecialchars($sale['SaleDate']) ?> by <?= htmlspecialchars($sale['SalesAgent_Username']) ?></p>
        <h4>Buyer Info</h4>
        <p><strong>Buyer:</strong> <?= htmlspecialchars($veh['BuyerBizName'] ?? $veh['BuyerFirstName'] . ' ' . $veh['BuyerLastName']) ?></p>
        <p><strong>Buyer Contact:</strong> <?= htmlspecialchars($veh['BuyerPhone']) ?> | <?= htmlspecialchars($veh['BuyerEmail'] ?? 'N/A') ?></p>
        <p><strong>Buyer Address:</strong> <?= htmlspecialchars($veh['BuyerStreet'] . ', ' . $veh['BuyerCity'] . ', ' . $veh['BuyerState'] . ' ' . $veh['BuyerPostal']) ?></p>
    <?php else: ?>
        <p style="color: #d9534f;"><strong>Status: Unsold</strong></p>
    <?php endif; ?>
<?php endif; ?>

</div></body></html>