<?php
require 'db.php';
require 'header.php';

// Only Acquisition Specialists (or Owners with this role) can add vehicles
if (!$isAcq) {
    die("<div class='container'><h2 class='error'>Unauthorized Access. Acquisition Specialists only.</h2></div></body></html>");
}

$message = '';
$searchMessage = '';
$foundCustomer = null;

// Customer Lookup
if (isset($_POST['search_customer'])) {
    $searchValue = trim($_POST['search_value']);
    
    if (!empty($searchValue)) {
        // Search for Individual
        $stmt = $pdo->prepare("
            SELECT c.*, i.FirstName, i.LastName, i.SSN, 'individual' as CustomerType
            FROM Customer c
            INNER JOIN Individual i ON c.CustomerID = i.CustomerID
            WHERE i.SSN = ?
        ");
        $stmt->execute([$searchValue]);
        $foundCustomer = $stmt->fetch();
        
        // If not found, search for Business
        if (!$foundCustomer) {
            $stmt = $pdo->prepare("
                SELECT c.*, b.BusinessName, b.TaxID, b.ContactFirstName, b.ContactLastName, b.ContactTitle, 'business' as CustomerType
                FROM Customer c
                INNER JOIN Business b ON c.CustomerID = b.CustomerID
                WHERE b.TaxID = ?
            ");
            $stmt->execute([$searchValue]);
            $foundCustomer = $stmt->fetch();
        }
        
        if ($foundCustomer) {
            // Updated style to 'color: green;'
            $searchMessage = "<p class='success' style='color: green;'>Customer Found! Details populated below.</p>";
        } else {
            $searchMessage = "<p class='error'>Customer Not Found. Please enter new customer details below.</p>";
        }
    } else {
        $searchMessage = "<p class='error'>Please enter an SSN or Tax ID to search.</p>";
    }
}

// Vehicle Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['search_customer'])) {
    $vin = $_POST['vin'];
    $type = $_POST['type'];
    $mfg = $_POST['mfg'];
    $model = $_POST['model'];
    $year = $_POST['year'];
    $fuel = $_POST['fuel'];
    $cond = $_POST['condition'];
    $hp = $_POST['hp'];
    $drive = $_POST['drivetrain'];
    $price = $_POST['price'];
    $purchdate = $_POST['purchdate'];
    $notes = $_POST['notes'] ?: null;
    $colors = $_POST['colors'] ?? [];
    
    $sellerType = $_POST['seller_type'];
    $taxOrSsn = $_POST['tax_or_ssn'];

    $currentYear = (int)date('Y');
    if ($year > ($currentYear + 1)) {
        $message = "<p class='error'>Error: Model year cannot exceed " . ($currentYear + 1) . ".</p>";
    } elseif ($purchdate > date('Y-m-d')) {
        $message = "<p class='error'>Error: Purchase date cannot be a future date.</p>";
    } else {
        try {
            $pdo->beginTransaction();

            $customerID = null;

            // 1. Check if Customer Exists
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

            // 4. Insert Vehicle
            $sqlVehicle = "INSERT INTO Vehicle (VIN, ModelName, ModelYear, FuelType, `Condition`, Horsepower, Drivetrain, Notes, PurchPrice, PurchDate, TypeName, MfgName, Purchaser_CustomerID, AcqSpecialist_Username) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sqlVehicle);
            $stmt->execute([$vin, $model, $year, $fuel, $cond, $hp, $drive, $notes, $price, $purchdate, $type, $mfg, $customerID, $_SESSION['username']]);

            // 5. Insert Colors
            $stmtColor = $pdo->prepare("INSERT INTO VehicleColor (VIN, Color) VALUES (?, ?)");
            foreach ($colors as $color) {
                $stmtColor->execute([$vin, trim($color)]);
            }

            $pdo->commit();
            
            header("Location: vehicle_detail.php?vin=" . urlencode($vin));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<p class='error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}
?>

<h2>Add New Vehicle</h2>

<div class="lookup-section" style="margin-bottom: 30px; padding: 15px; border: 1px solid #ccc;">
    <h3>Step 1: Customer Lookup</h3>
    <p>Search by SSN or Tax ID to see if the seller is already in the system.</p>
    <form method="POST">
        <input type="text" name="search_value" placeholder="SSN or Tax ID" required>
        <button type="submit" name="search_customer">Search</button>
    </form>
    <?= $searchMessage ?>
</div>

<hr>

<?= $message ?>

<form method="POST">
    <h3>Step 2: Vehicle Details</h3>
    <input type="text" name="vin" placeholder="17-char VIN" maxlength="17" required>
    <input type="text" name="type" placeholder="Type (e.g. SUV)" required>
    <input type="text" name="mfg" placeholder="Manufacturer (e.g. Ford)" required>
    <input type="text" name="model" placeholder="Model Name" required>
    <input type="number" name="year" placeholder="Model Year" required>
    <input type="text" name="fuel" placeholder="Fuel Type" required>
    <select name="condition" required>
        <option value="">Select Condition</option>
        <option value="Excellent">Excellent</option>
        <option value="Very Good">Very Good</option>
        <option value="Good">Good</option>
        <option value="Fair">Fair</option>
        <option value="Rough">Rough</option>
    </select>
    <input type="number" name="hp" placeholder="Horsepower" required>
    <input type="text" name="drivetrain" placeholder="Drivetrain" required>
    <input type="number" step="0.01" name="price" placeholder="Purchase Price" required>
    <input type="date" name="purchdate" max="<?= date('Y-m-d') ?>" required>
    <input type="text" name="notes" placeholder="Notes (Optional)">
    <br><br>
    <label>Colors (Select multiple):</label><br>
    <select name="colors[]" multiple required style="height: 150px;">
        <option value="Aluminum">Aluminum</option>
        <option value="Beige">Beige</option>
        <option value="Black">Black</option>
        <option value="Blue">Blue</option>
        <option value="Brown">Brown</option>
        <option value="Bronze">Bronze</option>
        <option value="Claret">Claret</option>
        <option value="Copper">Copper</option>
        <option value="Cream">Cream</option>
        <option value="Gold">Gold</option>
        <option value="Gray">Gray</option>
        <option value="Green">Green</option>
        <option value="Maroon">Maroon</option>
        <option value="Metallic">Metallic</option>
        <option value="Navy">Navy</option>
        <option value="Orange">Orange</option>
        <option value="Pink">Pink</option>
        <option value="Purple">Purple</option>
        <option value="Red">Red</option>
        <option value="Rose">Rose</option>
        <option value="Rust">Rust</option>
        <option value="Silver">Silver</option>
        <option value="Tan">Tan</option>
        <option value="Turquoise">Turquoise</option>
        <option value="White">White</option>
        <option value="Yellow">Yellow</option>
    </select>

    <hr>
    <h3>Step 3: Seller Details</h3>
    <label>Seller Type:</label>
    <select name="seller_type" id="sellerType" required onchange="toggleSellerFields()">
        <option value="individual" <?= ($foundCustomer && $foundCustomer['CustomerType'] == 'individual') ? 'selected' : '' ?>>Individual</option>
        <option value="business" <?= ($foundCustomer && $foundCustomer['CustomerType'] == 'business') ? 'selected' : '' ?>>Business</option>
    </select><br><br>

    <input type="text" name="tax_or_ssn" placeholder="SSN or Tax ID" required value="<?= $foundCustomer ? htmlspecialchars($foundCustomer['SSN'] ?? $foundCustomer['TaxID']) : '' ?>">
    <input type="text" name="phone" placeholder="Phone Number" required value="<?= $foundCustomer ? htmlspecialchars($foundCustomer['Phone']) : '' ?>">
    <input type="email" name="email" placeholder="Email (Optional)" value="<?= $foundCustomer ? htmlspecialchars($foundCustomer['Email']) : '' ?>">
    <input type="text" name="street" placeholder="Street Address" required value="<?= $foundCustomer ? htmlspecialchars($foundCustomer['StreetAddress']) : '' ?>">
    <input type="text" name="city" placeholder="City" required value="<?= $foundCustomer ? htmlspecialchars($foundCustomer['City']) : '' ?>">
    <input type="text" name="state" placeholder="State" required value="<?= $foundCustomer ? htmlspecialchars($foundCustomer['State']) : '' ?>">
    <input type="text" name="zip" placeholder="Postal Code" required value="<?= $foundCustomer ? htmlspecialchars($foundCustomer['PostalCode']) : '' ?>">

    <div id="individual_fields" <?= ($foundCustomer && $foundCustomer['CustomerType'] == 'business') ? 'style="display:none;"' : '' ?>>
        <h4>Individual Info</h4>
        <input type="text" name="fname" placeholder="First Name" value="<?= ($foundCustomer && $foundCustomer['CustomerType'] == 'individual') ? htmlspecialchars($foundCustomer['FirstName']) : '' ?>">
        <input type="text" name="lname" placeholder="Last Name" value="<?= ($foundCustomer && $foundCustomer['CustomerType'] == 'individual') ? htmlspecialchars($foundCustomer['LastName']) : '' ?>">
    </div>

    <div id="business_fields" <?= (!$foundCustomer || $foundCustomer['CustomerType'] == 'individual') ? 'style="display:none;"' : '' ?>>
        <h4>Business Info</h4>
        <input type="text" name="bus_name" placeholder="Business Name" value="<?= ($foundCustomer && $foundCustomer['CustomerType'] == 'business') ? htmlspecialchars($foundCustomer['BusinessName']) : '' ?>">
        <input type="text" name="bus_cfname" placeholder="Contact First Name" value="<?= ($foundCustomer && $foundCustomer['CustomerType'] == 'business') ? htmlspecialchars($foundCustomer['ContactFirstName']) : '' ?>">
        <input type="text" name="bus_clname" placeholder="Contact Last Name" value="<?= ($foundCustomer && $foundCustomer['CustomerType'] == 'business') ? htmlspecialchars($foundCustomer['ContactLastName']) : '' ?>">
        <input type="text" name="bus_title" placeholder="Contact Title" value="<?= ($foundCustomer && $foundCustomer['CustomerType'] == 'business') ? htmlspecialchars($foundCustomer['ContactTitle']) : '' ?>">
    </div>

    <br><br>
    <button type="submit" style="padding: 10px 20px; font-size: 16px;">Save Vehicle & Seller</button>
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