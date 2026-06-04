<?php
require 'db.php'; require 'header.php';
if (!$isAcq) die("<div class='container'><h2 class='error'>Unauthorized</h2></div></body></html>");
if (empty($_GET['vin'])) die("<div class='container'>VIN required.</div>");
$vin = $_GET['vin'];

$message = '';
$searchMessage = '';
$foundVendor = null;

// Vendor Lookup
if (isset($_POST['search_vendor'])) {
    $vendorNameSearch = trim($_POST['search_name']);
    
    if (!empty($vendorNameSearch)) {
        $stmt = $pdo->prepare("SELECT * FROM Vendor WHERE VendorName = ?");
        $stmt->execute([$vendorNameSearch]);
        $foundVendor = $stmt->fetch();
        
        if ($foundVendor) {
            $searchMessage = "<p style='color:green;'>Vendor Found! Details populated below.</p>";
        } else {
            $searchMessage = "<p class='error'>Vendor Not Found. Please enter new vendor details below.</p>";
        }
    } else {
        $searchMessage = "<p class='error'>Please enter a Vendor Name to search.</p>";
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['search_vendor'])) {
    $vendorName = trim($_POST['vendor_name']);
    
    try {
        $pdo->beginTransaction();

        // 1. Search for Vendor
        $stmt = $pdo->prepare("SELECT VendorName FROM Vendor WHERE VendorName = ?");
        $stmt->execute([$vendorName]);
        if (!$stmt->fetch()) {
            // Add Vendor if new
            $stmt = $pdo->prepare("INSERT INTO Vendor (VendorName, Phone, StreetAddress, City, State, PostalCode) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$vendorName, $_POST['v_phone'], $_POST['v_street'], $_POST['v_city'], $_POST['v_state'], $_POST['v_zip']]);
        }

        // 2. Generate OrderNum (VIN + 3-digit ordinal)
        $stmt = $pdo->prepare("SELECT COUNT(*) AS orderCount FROM PartsOrder WHERE VIN = ?");
        $stmt->execute([$vin]);
        $count = $stmt->fetch()['orderCount'] + 1;
        $orderNum = $vin . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        // 3. Create Parts Order
        $stmt = $pdo->prepare("INSERT INTO PartsOrder (VIN, OrderNum, VendorName) VALUES (?, ?, ?)");
        $stmt->execute([$vin, $orderNum, $vendorName]);

        // 4. Add Part
        $stmt = $pdo->prepare("INSERT INTO Part (VIN, OrderNum, PartNumber, Description, UnitPrice, Quantity, Status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$vin, $orderNum, $_POST['part_num'], $_POST['description'], $_POST['price'], $_POST['quantity'], 'ordered']);

        $pdo->commit();
        $message = "<p style='color:green;'>Part Ordered Successfully!</p>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<p class='error'>Error: " . $e->getMessage() . "</p>";
    }
}
?>

<h2>Add Parts for VIN: <?= htmlspecialchars($vin) ?></h2>

<div class="lookup-section" style="margin-bottom: 30px; padding: 15px; border: 1px solid #ccc;">
    <h3>Step 1: Vendor Lookup</h3>
    <p>Search by Vendor Name to see if they are already in the system.</p>
    <form method="POST">
        <input type="text" name="search_name" placeholder="Vendor Name" required>
        <button type="submit" name="search_vendor">Search</button>
    </form>
    <?= $searchMessage ?>
</div>

<hr>

<?= $message ?>

<form method="POST">
    <h3>Step 2: Vendor Details</h3>
    <p><em>If vendor exists, address fields will be ignored.</em></p>
    <input type="text" name="vendor_name" placeholder="Vendor Name" required value="<?= $foundVendor ? htmlspecialchars($foundVendor['VendorName']) : '' ?>">
    <input type="text" name="v_phone" placeholder="Vendor Phone" value="<?= $foundVendor ? htmlspecialchars($foundVendor['Phone']) : '' ?>">
    <input type="text" name="v_street" placeholder="Street Address" value="<?= $foundVendor ? htmlspecialchars($foundVendor['StreetAddress']) : '' ?>">
    <input type="text" name="v_city" placeholder="City" value="<?= $foundVendor ? htmlspecialchars($foundVendor['City']) : '' ?>">
    <input type="text" name="v_state" placeholder="State" value="<?= $foundVendor ? htmlspecialchars($foundVendor['State']) : '' ?>">
    <input type="text" name="v_zip" placeholder="Postal Code" value="<?= $foundVendor ? htmlspecialchars($foundVendor['PostalCode']) : '' ?>">

    <h3>Step 3: Part Details</h3>
    <input type="text" name="part_num" placeholder="Part Number" required>
    <input type="text" name="description" placeholder="Description" required>
    <input type="number" step="0.01" name="price" placeholder="Unit Price" required>
    <input type="number" name="quantity" placeholder="Quantity" required>
    <br><br>
    <button type="submit" style="padding: 10px 20px; font-size: 16px;">Place Order</button>
</form>
<br>
<a href="vehicle_detail.php?vin=<?= $vin ?>">Back to Vehicle</a>
</div></body></html>