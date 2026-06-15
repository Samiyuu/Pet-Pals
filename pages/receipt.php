<?php
define('APP_RUN', true);
require_once '../includes/auth.php';
require_once '../includes/config.php';
requireLogin();

if (!isset($_GET['rental_id'])) {
    header("Location: rentals.php");
    exit;
}

$rental_id = intval($_GET['rental_id']);

$stmt = $pdo->prepare("
    SELECT r.*, c.customer_name, c.contact_number, c.address,
           p.pet_name, p.species, p.rental_price,
           u.username as processed_by
    FROM rentals r
    JOIN customers c ON r.customer_id = c.customer_id
    JOIN pets p ON r.pet_id = p.pet_id
    JOIN users u ON r.created_by = u.user_id
    WHERE r.rental_id = ?
");
$stmt->execute([$rental_id]);
$rental = $stmt->fetch();

if (!$rental) {
    die("Rental not found.");
}

// Calculate correct amount with deduction
if ($rental['status'] === 'Cancelled' || $rental['status'] === 'Returned') {
    $rentalDate = new DateTime($rental['rental_date']);
    $actualReturnDate = !empty($rental['actual_return']) ? new DateTime($rental['actual_return']) : new DateTime();
    $daysUsed = $actualReturnDate->diff($rentalDate)->days + 1;
    if ($daysUsed > $rental['rental_days']) $daysUsed = $rental['rental_days'];
    
    $originalAmount = $rental['rental_fee']; // Original full price
    $correctAmount  = $rental['rental_price'] * $daysUsed; // Deducted price
    $deduction      = $originalAmount - $correctAmount; // Amount saved
} else {
    $daysUsed       = $rental['rental_days'];
    $originalAmount = $rental['rental_fee'];
    $correctAmount  = $rental['rental_fee'];
    $deduction      = 0;
}

$receiptNo = str_pad($rental_id, 6, '0', STR_PAD_LEFT);
$dateIssued = date('F d, Y - h:i A', strtotime($rental['created_at'] ?? 'now'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $receiptNo ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            padding: 20px;
        }
        .receipt {
            background: #fff;
            max-width: 450px;
            margin: 0 auto;
            padding: 25px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #2c3e50;
        }
        .header p {
            margin: 2px 0;
            font-size: 13px;
            color: #666;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            font-size: 14px;
        }
        .label {
            color: #555;
        }
        .value {
            font-weight: 500;
        }
        .total {
            font-weight: bold;
            font-size: 15px;
        }
        .deduction-text {
            color: #e74c3c;
        }
        .adjusted-text {
            color: #27ae60;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #777;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt { border: none; box-shadow: none; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="receipt">
    <div class="header">
        <h1>🐾 PetPal Rental Receipt</h1>
        <p>PetPal Rental Management System</p>
        <p>CvSU CCAT Campus, Rosario, Cavite</p>
    </div>

    <div class="info-row">
        <span class="label">Receipt No:</span>
        <span class="value">#<?= $receiptNo ?></span>
    </div>
    <div class="info-row">
        <span class="label">Date Issued:</span>
        <span class="value"><?= $dateIssued ?></span>
    </div>
    <div class="info-row">
        <span class="label">Processed by:</span>
        <span class="value"><?= htmlspecialchars($rental['processed_by']) ?></span>
    </div>

    <div style="margin: 10px 0;">
        <strong>Customer Information</strong>
        <div class="info-row">
            <span class="label">Name:</span>
            <span class="value"><?= htmlspecialchars($rental['customer_name']) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Contact:</span>
            <span class="value"><?= htmlspecialchars($rental['contact_number']) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Address:</span>
            <span class="value"><?= htmlspecialchars($rental['address']) ?></span>
        </div>
    </div>

    <div style="margin: 10px 0;">
        <strong>Rental Details</strong>
        <div class="info-row">
            <span class="label">Pet:</span>
            <span class="value"><?= htmlspecialchars($rental['pet_name']) ?> (<?= $rental['species'] ?>)</span>
        </div>
        <div class="info-row">
            <span class="label">Rental Date:</span>
            <span class="value"><?= date('F d, Y', strtotime($rental['rental_date'])) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Expected Return:</span>
            <span class="value"><?= date('F d, Y', strtotime($rental['expected_return'])) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Actual Return:</span>
            <span class="value"><?= !empty($rental['actual_return']) ? date('F d, Y', strtotime($rental['actual_return'])) : '—' ?></span>
        </div>
        <div class="info-row">
            <span class="label">Rental Days:</span>
            <span class="value"><?= $daysUsed ?> day(s)</span>
        </div>
        <div class="info-row">
            <span class="label">Rate per Day:</span>
            <span class="value">₱<?= number_format($rental['rental_price'], 2) ?></span>
        </div>
    </div>

    <div style="margin: 10px 0;">
        <strong>Payment Summary</strong>
        <?php if ($deduction > 0): ?>
        <div class="info-row">
            <span class="label">Original Rental Fee:</span>
            <span class="value">₱<?= number_format($originalAmount, 2) ?></span>
        </div>
        <div class="info-row deduction-text">
            <span class="label">Deduction (Early Return):</span>
            <span class="value">- ₱<?= number_format($deduction, 2) ?></span>
        </div>
        <div class="info-row adjusted-text">
            <span class="label">Adjusted Rental Fee:</span>
            <span class="value">₱<?= number_format($correctAmount, 2) ?></span>
        </div>
        <?php else: ?>
        <div class="info-row">
            <span class="label">Rental Fee:</span>
            <span class="value">₱<?= number_format($correctAmount, 2) ?></span>
        </div>
        <?php endif; ?>
        
        <!-- ✅ NOW SHOWS THE DEDUCTED AMOUNT HERE -->
        <div class="info-row total">
            <span>TOTAL PAID:</span>
            <span>₱<?= number_format($correctAmount, 2) ?></span>
        </div>
        
        <div class="info-row">
            <span class="label">Payment Method:</span>
            <span class="value">Cash</span>
        </div>
        <div class="info-row">
            <span class="label">Status:</span>
            <span class="value"><?= $rental['status'] ?></span>
        </div>
    </div>

    <div class="footer">
        Thank you for choosing PetPal!<br>
        Please return the pet on time to avoid late penalties.
    </div>

    <div class="no-print" style="text-align:center; margin-top:15px;">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Receipt</button>
        <a href="payments.php" class="btn btn-secondary">Back</a>
    </div>
</div>

</body>
</html>