<?php
require 'db.php'; 
require 'header.php';
if (!$isManager) die("Unauthorized access.");

// Parts Statistics Report
$sql = "WITH deduped_parts_order AS (
    SELECT DISTINCT
        VIN,
        OrderNum,
        VendorName
    FROM PartsOrder
),

deduped_part AS (
    SELECT DISTINCT
        VIN,
        OrderNum,
        PartNumber,
        Description,
        UnitPrice,
        Quantity,
        Status
    FROM Part
),

part_order_totals AS (
    SELECT
        VIN,
        OrderNum,
        SUM(Quantity) AS TotalPartsSupplied,
        SUM(UnitPrice * Quantity) AS TotalDollarAmount
    FROM deduped_part
    GROUP BY
        VIN,
        OrderNum
)

SELECT
    v.VendorName,
    COALESCE(SUM(pot.TotalPartsSupplied), 0) AS TotalPartsSupplied,
    COALESCE(SUM(pot.TotalDollarAmount), 0) AS TotalDollarAmount
FROM Vendor v
LEFT JOIN deduped_parts_order po
    ON v.VendorName = po.VendorName
LEFT JOIN part_order_totals pot
    ON po.VIN = pot.VIN
   AND po.OrderNum = pot.OrderNum
GROUP BY
    v.VendorName
ORDER BY
    v.VendorName ASC;";

$stmt = $pdo->query($sql);
$partsStats = $stmt->fetchAll();

// Monthly Sales Summary Report
$monthlySalesSQL = "
WITH deduped_part AS (
    SELECT DISTINCT
        VIN,
        OrderNum,
        PartNumber,
        Description,
        UnitPrice,
        Quantity,
        Status
    FROM Part
),

parts_costs AS (
    SELECT 
        VIN, 
        SUM(UnitPrice * Quantity) AS TotalCost
    FROM deduped_part
    WHERE LOWER(Status) = 'installed'
    GROUP BY VIN
),

deduped_sale AS (
    SELECT DISTINCT
        VIN,
        SaleDate
    FROM Sale
),

deduped_vehicle AS (
    SELECT DISTINCT
        VIN,
        PurchPrice
    FROM Vehicle
)

SELECT
    YEAR(s.SaleDate) AS SaleYear,
    MONTH(s.SaleDate) AS SaleMonth,

    COUNT(DISTINCT s.VIN) AS TotalVehiclesSold,

    ROUND(
        SUM(v.PurchPrice * 1.25 + COALESCE(pc.TotalCost, 0) * 1.10),
        2
    ) AS GrossSalesIncome,

    ROUND(
        SUM(
            (v.PurchPrice * 1.25 + COALESCE(pc.TotalCost, 0) * 1.10)
            - v.PurchPrice
            - COALESCE(pc.TotalCost, 0)
        ),
        2
    ) AS TotalNetIncome

FROM deduped_sale s
INNER JOIN deduped_vehicle v
    ON s.VIN = v.VIN
LEFT JOIN parts_costs pc
    ON v.VIN = pc.VIN
GROUP BY
    YEAR(s.SaleDate),
    MONTH(s.SaleDate)
ORDER BY
    SaleYear DESC,
    SaleMonth DESC;";

$monthlyStmt = $pdo->query($monthlySalesSQL);
$monthlySales = $monthlyStmt->fetchAll();

// Monthly Sales Drilldown
$drilldownData = [];
if (isset($_GET['month']) && isset($_GET['year'])) {
    $selectedMonth = intval($_GET['month']);
    $selectedYear = intval($_GET['year']);
    
    $drilldownSQL = "
    WITH deduped_part AS (
        SELECT DISTINCT
            VIN,
            OrderNum,
            PartNumber,
            Description,
            UnitPrice,
            Quantity,
            Status
        FROM Part
    ),

    parts_costs AS (
        SELECT 
            VIN,
            SUM(UnitPrice * Quantity) AS TotalCost
        FROM deduped_part
        WHERE LOWER(Status) = 'installed'
        GROUP BY VIN
    ),
    deduped_sale AS (
        SELECT DISTINCT
            VIN,
            SaleDate,
            SalesAgent_Username
        FROM Sale
    ),
    deduped_vehicle AS (
        SELECT DISTINCT
            VIN,
            PurchPrice
        FROM Vehicle
    ),
    deduped_sales_agent AS (
        SELECT DISTINCT
            Username
        FROM SalesAgent
    ),
    deduped_user AS (
        SELECT DISTINCT
            Username,
            FirstName,
            LastName
        FROM User
    )
    SELECT 
        u.FirstName, 
        u.LastName,

        COUNT(DISTINCT s.VIN) AS TotalVehiclesSold,

        ROUND(
            SUM(v.PurchPrice * 1.25 + COALESCE(pc.TotalCost, 0) * 1.10),
            2
        ) AS TotalSales

    FROM deduped_sale s
    INNER JOIN deduped_vehicle v
        ON s.VIN = v.VIN
    INNER JOIN deduped_sales_agent sa
        ON s.SalesAgent_Username = sa.Username
    INNER JOIN deduped_user u
        ON sa.Username = u.Username
    LEFT JOIN parts_costs pc
        ON v.VIN = pc.VIN
    WHERE YEAR(s.SaleDate) = :year 
    AND MONTH(s.SaleDate) = :month
    GROUP BY
        u.Username,
        u.FirstName,
        u.LastName
    ORDER BY
        TotalVehiclesSold DESC,
        TotalSales DESC;
    ";
    
    $drilldownStmt = $pdo->prepare($drilldownSQL);
    $drilldownStmt->execute(['year' => $selectedYear, 'month' => $selectedMonth]);
    $drilldownData = $drilldownStmt->fetchAll();
}

// Seller History Report
$sellerSQL = "
    SELECT
        COALESCE(CONCAT(i.FirstName, ' ', i.LastName), b.BusinessName) AS SellerName,
        COUNT(DISTINCT v.VIN) AS TotalVehicles,
        ROUND(AVG(v.PurchPrice), 2) AS AvgPurchasePrice,
        SUM(pv.TotalPartsQuantity) / COUNT(DISTINCT v.VIN) AS AvgPartsQuantity,
        ROUND(SUM(pv.TotalPartsCost) / COUNT(DISTINCT v.VIN), 2) AS AvgPartsCost

    FROM
    (
        SELECT DISTINCT
            VIN,
            Purchaser_CustomerID,
            PurchPrice
        FROM Vehicle
    ) v

    INNER JOIN
    (
        SELECT DISTINCT CustomerID
        FROM Customer
    ) c
        ON v.Purchaser_CustomerID = c.CustomerID

    LEFT JOIN
    (
        SELECT DISTINCT CustomerID, FirstName, LastName
        FROM Individual
    ) i
        ON c.CustomerID = i.CustomerID

    LEFT JOIN
    (
        SELECT DISTINCT CustomerID, BusinessName
        FROM Business
    ) b
        ON c.CustomerID = b.CustomerID

    LEFT JOIN
    (
        SELECT
            po.VIN,
            SUM(p.Quantity) AS TotalPartsQuantity,
            SUM(p.Quantity * p.UnitPrice) AS TotalPartsCost
        FROM
        (
            SELECT DISTINCT VIN, OrderNum
            FROM PartsOrder
        ) po
        LEFT JOIN
        (
            SELECT DISTINCT VIN, OrderNum, Quantity, UnitPrice
            FROM Part
        ) p
            ON po.VIN = p.VIN
            AND po.OrderNum = p.OrderNum
        GROUP BY po.VIN
    ) pv
        ON v.VIN = pv.VIN

    GROUP BY
        CASE 
            WHEN b.BusinessName IS NOT NULL THEN b.BusinessName
            ELSE CAST(c.CustomerID AS CHAR)
        END,
        i.FirstName,
        i.LastName,
        b.BusinessName

    ORDER BY
        TotalVehicles DESC,
        AvgPurchasePrice;";

$sellerStmt = $pdo->query($sellerSQL);
$sellerHistory = $sellerStmt->fetchAll();

// Average Time in Inventory Report
$inventorySQL = "
        WITH deduped_vehicle_type AS (
            SELECT DISTINCT
                TypeName
            FROM VehicleType
        ),
        deduped_vehicle AS (
            SELECT DISTINCT
                VIN,
                TypeName,
                PurchDate
            FROM Vehicle
        ),
        deduped_sale AS (
            SELECT DISTINCT
                VIN,
                SaleDate
            FROM Sale
        )
        SELECT 
            vt.TypeName, 

            CASE 
                WHEN COUNT(DISTINCT s.VIN) = 0 THEN 'N/A'
                ELSE ROUND(AVG(DATEDIFF(s.SaleDate, v.PurchDate) + 1), 2)
            END AS AvgDaysInInventory

        FROM deduped_vehicle_type vt
        LEFT JOIN deduped_vehicle v  
            ON vt.TypeName = v.TypeName 
        LEFT JOIN deduped_sale s  
            ON v.VIN = s.VIN 
        GROUP BY 
            vt.TypeName 
        ORDER BY 
            vt.TypeName;";

$inventoryStmt = $pdo->query($inventorySQL);
$inventoryStats = $inventoryStmt->fetchAll();

// Price Per Condition Report
$conditionSQL = "
        WITH cond AS (
            SELECT 'Excellent' AS `Condition`, 1 AS SortOrder
            UNION SELECT 'Very Good', 2
            UNION SELECT 'Good', 3
            UNION SELECT 'Fair', 4
            UNION SELECT 'Rough', 5
        ),

        deduped_vehicle AS (
            SELECT DISTINCT
                VIN,
                TypeName,
                `Condition`,
                PurchPrice
            FROM Vehicle
        )

        SELECT 
            cond.`Condition`,
            CONCAT('$', ROUND(IFNULL(AVG(CASE WHEN v.TypeName = 'Convertible' THEN v.PurchPrice END), 0), 2)) AS Convertible,
            CONCAT('$', ROUND(IFNULL(AVG(CASE WHEN v.TypeName = 'Coupe' THEN v.PurchPrice END), 0), 2)) AS Coupe,
            CONCAT('$', ROUND(IFNULL(AVG(CASE WHEN v.TypeName = 'Minivan' THEN v.PurchPrice END), 0), 2)) AS Minivan,
            CONCAT('$', ROUND(IFNULL(AVG(CASE WHEN v.TypeName = 'Sedan' THEN v.PurchPrice END), 0), 2)) AS Sedan,
            CONCAT('$', ROUND(IFNULL(AVG(CASE WHEN v.TypeName = 'SUV' THEN v.PurchPrice END), 0), 2)) AS SUV,
            CONCAT('$', ROUND(IFNULL(AVG(CASE WHEN v.TypeName = 'Truck' THEN v.PurchPrice END), 0), 2)) AS Truck,
            CONCAT('$', ROUND(IFNULL(AVG(CASE WHEN v.TypeName = 'Van' THEN v.PurchPrice END), 0), 2)) AS Van,
            CONCAT('$', ROUND(IFNULL(AVG(CASE WHEN v.TypeName = 'Other' THEN v.PurchPrice END), 0), 2)) AS Other
        FROM cond
        LEFT JOIN deduped_vehicle v
            ON cond.`Condition` = v.`Condition`
        GROUP BY
            cond.`Condition`,
            cond.SortOrder
        ORDER BY
            cond.SortOrder;";

$conditionStmt = $pdo->query($conditionSQL);
$conditionStats = $conditionStmt->fetchAll();

?>

<h2>Management Reports</h2>

<div style="margin-bottom: 20px;">
    <label for="reportDropdown"><strong>Select a Report: </strong></label>
    <select id="reportDropdown" onchange="showReport(this.value)">
        <option value="partsReport" <?php if(!isset($_GET['month'])) echo 'selected'; ?>>Parts Statistics</option>
        <option value="monthlyReport" <?php if(isset($_GET['month'])) echo 'selected'; ?>>Monthly Sales Summary</option>
        <option value="sellerReport">Seller History</option>
        <option value="inventoryReport">Average Time in Inventory</option>
        <option value="conditionReport">Price Per Condition</option>
    </select>
</div>

<div id="partsReport" class="report-section" style="display: <?php echo isset($_GET['month']) ? 'none' : 'block'; ?>;">
    <h3>Parts Statistics</h3>
    <table>
        <tr><th>Vendor Name</th><th>Total Parts Supplied</th><th>Total Dollar Amount</th></tr>
        <?php foreach($partsStats as $stat): ?>
        <tr>
            <td><?= htmlspecialchars($stat['VendorName']) ?></td>
            <td><?= $stat['TotalPartsSupplied'] ?></td>
            <td>$<?= number_format($stat['TotalDollarAmount'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div id="monthlyReport" class="report-section" style="display: <?php echo isset($_GET['month']) ? 'block' : 'none'; ?>;">
    <h3>Monthly Sales Summary</h3>
    <table>
        <tr>
            <th>Year</th>
            <th>Month</th>
            <th>Total Vehicles Sold</th>
            <th>Gross Sales Income</th>
            <th>Total Net Income</th>
        </tr>
        <?php foreach($monthlySales as $sale): ?>
        <tr>
            <td><?= $sale['SaleYear'] ?></td>
            <td>
                <a href="reports.php?month=<?= $sale['SaleMonth'] ?>&year=<?= $sale['SaleYear'] ?>">
                    <?= date('F', mktime(0, 0, 0, $sale['SaleMonth'], 1)) ?>
                </a>
            </td>
            <td><?= $sale['TotalVehiclesSold'] ?></td>
            <td>$<?= number_format($sale['GrossSalesIncome'], 2) ?></td>
            <td>$<?= number_format($sale['TotalNetIncome'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php if (!empty($drilldownData)): ?>
    <h3>Sales Details for <?= date('F', mktime(0, 0, 0, $selectedMonth, 1)) ?> <?= $selectedYear ?></h3>
    <table>
        <tr>
            <th>Salesperson Name</th>
            <th>Total Vehicles Sold</th>
            <th>Total Sales</th>
        </tr>
        <?php foreach($drilldownData as $agent): ?>
        <tr>
            <td><?= htmlspecialchars($agent['FirstName'] . ' ' . $agent['LastName']) ?></td>
            <td><?= $agent['TotalVehiclesSold'] ?></td>
            <td>$<?= number_format($agent['TotalSales'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div id="sellerReport" class="report-section" style="display: none;">
    <h3>Seller History</h3>
    <table>
        <tr>
            <th>Seller Name</th>
            <th>Total Vehicles</th>
            <th>Avg Purchase Price</th>
            <th>Avg Parts Quantity</th>
            <th>Avg Parts Cost</th>
        </tr>
        <?php foreach($sellerHistory as $seller): ?>
        <?php 
            $highlight = ($seller['AvgPartsQuantity'] >= 5 || $seller['AvgPartsCost'] > 500) ? 'style="background-color: #ffcccc;"' : '';
        ?>
        <tr <?= $highlight ?>>
            <td><?= htmlspecialchars($seller['SellerName']) ?></td>
            <td><?= $seller['TotalVehicles'] ?></td>
            <td>$<?= number_format($seller['AvgPurchasePrice'], 2) ?></td>
            <td><?= number_format($seller['AvgPartsQuantity'], 2) ?></td>
            <td>$<?= number_format($seller['AvgPartsCost'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div id="inventoryReport" class="report-section" style="display: none;">
    <h3>Average Time in Inventory</h3>
    <table>
        <tr>
            <th>Vehicle Type</th>
            <th>Average Days in Inventory</th>
        </tr>
        <?php foreach($inventoryStats as $stat): ?>
        <tr>
            <td><?= htmlspecialchars($stat['TypeName']) ?></td>
            <td><?= htmlspecialchars($stat['AvgDaysInInventory']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div id="conditionReport" class="report-section" style="display: none;">
    <h3>Price Per Condition</h3>
    <table>
        <tr>
            <th>Condition</th>
            <th>Convertible</th>
            <th>Coupe</th>
            <th>Minivan</th>
            <th>Sedan</th>
            <th>SUV</th>
            <th>Truck</th>
            <th>Van</th>
            <th>Other</th>
        </tr>
        <?php foreach($conditionStats as $row): ?>
        <tr>
            <td><strong><?= htmlspecialchars($row['Condition']) ?></strong></td>
            <td><?= $row['Convertible'] ?></td>
            <td><?= $row['Coupe'] ?></td>
            <td><?= $row['Minivan'] ?></td>
            <td><?= $row['Sedan'] ?></td>
            <td><?= $row['SUV'] ?></td>
            <td><?= $row['Truck'] ?></td>
            <td><?= $row['Van'] ?></td>
            <td><?= $row['Other'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<script>
    function showReport(reportId) {
        var reports = document.querySelectorAll('.report-section');
        reports.forEach(function(report) {
            report.style.display = 'none';
        });
        document.getElementById(reportId).style.display = 'block';
    }
</script>

</div></body></html>