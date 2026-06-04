<?php
require 'db.php'; require 'header.php';
if (!$isAcq) die("<div class='container'><h2 class='error'>Unauthorized</h2></div></body></html>");

if (empty($_GET['vin']) || empty($_GET['order']) || empty($_GET['part'])) {
    die("<div class='container'>Missing required parameters.</div></body></html>");
}

$vin = $_GET['vin'];
$orderNum = $_GET['order'];
$partNum = $_GET['part'];
$message = '';

// Fetch current status
$stmt = $pdo->prepare("SELECT Status, Description FROM Part WHERE VIN = ? AND OrderNum = ? AND PartNumber = ?");
$stmt->execute([$vin, $orderNum, $partNum]);
$part = $stmt->fetch();

if (!$part) die("<div class='container'>Part not found.</div></body></html>");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newStatus = $_POST['status'];
    $currentStatus = $part['Status'];
    
    // BUSINESS LOGIC: Status can only move forward
    $valid = false;
    if ($currentStatus == 'ordered' && in_array($newStatus, ['received', 'installed'])) $valid = true;
    if ($currentStatus == 'received' && $newStatus == 'installed') $valid = true;

    if ($valid) {
        $updateStmt = $pdo->prepare("UPDATE Part SET Status = ? WHERE VIN = ? AND OrderNum = ? AND PartNumber = ?");
        $updateStmt->execute([$newStatus, $vin, $orderNum, $partNum]);
        header("Location: vehicle_detail.php?vin=" . urlencode($vin));
        exit;
    } else {
        $message = "<p class='error'>Invalid status progression. Cannot revert to a previous status.</p>";
    }
}
?>

<h2>Update Status: <?= htmlspecialchars($part['Description']) ?></h2>
<?= $message ?>
<p><strong>Current Status:</strong> <?= htmlspecialchars($part['Status']) ?></p>

<?php if ($part['Status'] != 'installed'): ?>
<form method="POST">
    <label>Select New Status:</label>
    <select name="status" required>
        <?php if ($part['Status'] == 'ordered'): ?>
            <option value="received">Received</option>
            <option value="installed">Installed</option>
        <?php elseif ($part['Status'] == 'received'): ?>
            <option value="installed">Installed</option>
        <?php endif; ?>
    </select>
    <button type="submit">Update Status</button>
</form>
<?php else: ?>
    <p style="color: green;">Part is fully installed. No further updates permitted.</p>
<?php endif; ?>

<br>
<a href="vehicle_detail.php?vin=<?= urlencode($vin) ?>"><button>Back to Vehicle</button></a>

</div></body></html>