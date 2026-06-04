<?php
require 'db.php';
require 'header.php';

if (!$isSales) {
    die("<div class='container'><h2 class='error'>Unauthorized Access. Sales Agents only.</h2></div></body></html>");
}

if (empty($_GET['vin'])) die("<div class='container'>VIN required.</div>");
$vin = $_GET['vin'];

// Security & Business Logic Check: Ensure vehicle is unsold and has no pending parts
$checkStmt = $pdo->prepare("
    SELECT v.VIN, 
           (SELECT COUNT(*) FROM Sale WHERE VIN = v.VIN) AS IsSold,
           (SELECT COUNT(*) FROM Part WHERE VIN = v.VIN AND Status IN ('Ordered','Received')) AS PendingParts
    FROM Vehicle v WHERE v.VIN = ?");
$checkStmt->execute([$vin]);
$check = $checkStmt->fetch();

if (!$check || $check['IsSold'] > 0 || $check['PendingParts'] > 0) {
    die("<div class='container'><h2 class='error'>Vehicle cannot be sold. It is either already sold, has pending parts, or does not exist.</h2></div></body></html>");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sellerType = $_POST['seller_type']; // 'individual' or 'business'
    $taxOrSsn = $_POST['tax_or_ssn'];

    try {
        $pdo->beginTransaction();
        $customerID = null;

        // 1. Check if Buyer Exists
        if ($sellerType === 'individual') {
            $stmt = $pdo->prepare("SELECT CustomerID FROM Individual WHERE SSN = ?");
        } else {
            $stmt = $pdo->prepare("SELECT CustomerID FROM Business WHERE TaxID = ?");
        }
        $stmt->execute([$taxOrSsn]);
        $existingCustomer = $stmt->fetch();

        if ($existingCustomer) {
            $customerID = $existingCustomer['CustomerID'];
        } else {
            // 2. Insert Base Customer
            $stmt = $pdo->prepare("INSERT INTO Customer (Phone, Email, StreetAddress, City, State, PostalCode) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['phone'], $_POST['email'] ?: null, $_POST['street'], $_POST['city'], $_POST['state'], $_POST['zip']]);
            $customerID = $pdo->lastInsertId();

            // 3. Insert Subclass
            if ($sellerType === 'individual') {
                $stmt = $pdo->prepare("INSERT INTO Individual (SSN, FirstName, LastName, CustomerID) VALUES (?, ?, ?, ?)");
                $stmt->execute([$taxOrSsn, $_POST['fname'], $_POST['lname'], $customerID]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO Business (TaxID, BusinessName, ContactFirstName, ContactLastName, ContactTitle, CustomerID) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$taxOrSsn, $_POST['bus_name'], $_POST['bus_cfname'], $_POST['bus_clname'], $_POST['bus_title'], $customerID]);
            }
        }

        // 4. Insert Sale Record
        $stmt = $pdo->prepare("INSERT INTO Sale (VIN, Buyer_CustomerID, SalesAgent_Username, SaleDate) VALUES (?, ?, ?, CURDATE())");
        $stmt->execute([$vin, $customerID, $_SESSION['username']]);

        $pdo->commit();
        header("Location: vehicle_detail.php?vin=" . urlencode($vin));
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<p class='error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>

<h2>Sell Vehicle: <?= htmlspecialchars($vin) ?></h2>
<?= $message ?>

<form method="POST">
    <h3>Buyer Details</h3>
    <label>Buyer Type:</label>
    <select name="seller_type" id="sellerType" required onchange="toggleSellerFields()">
        <option value="individual">Individual</option>
        <option value="business">Business</option>
    </select><br><br>

    <input type="text" name="tax_or_ssn" placeholder="SSN or Tax ID" required>
    <input type="text" name="phone" placeholder="Phone Number" required>
    <input type="email" name="email" placeholder="Email (Optional)">
    <input type="text" name="street" placeholder="Street Address" required>
    <input type="text" name="city" placeholder="City" required>
    <input type="text" name="state" placeholder="State" required>
    <input type="text" name="zip" placeholder="Postal Code" required>

    <div id="individual_fields">
        <h4>Individual Info</h4>
        <input type="text" name="fname" placeholder="First Name">
        <input type="text" name="lname" placeholder="Last Name">
    </div>

    <div id="business_fields" style="display:none;">
        <h4>Business Info</h4>
        <input type="text" name="bus_name" placeholder="Business Name">
        <input type="text" name="bus_cfname" placeholder="Contact First Name">
        <input type="text" name="bus_clname" placeholder="Contact Last Name">
        <input type="text" name="bus_title" placeholder="Contact Title">
    </div>

    <br><br>
    <button type="submit" style="padding: 10px 20px; font-size: 16px; background-color: #28a745; color: white;">Confirm Sale</button>
</form>

<script>
function toggleSellerFields() {
    var type = document.getElementById('sellerType').value;
    if (type === 'business') {
        document.getElementById('business_fields').style.display = 'block';
        document.getElementById('individual_fields').style.display = 'none';
    } else {
        document.getElementById('business_fields').style.display = 'none';
        document.getElementById('individual_fields').style.display = 'block';
    }
}
</script>

</div></body></html>