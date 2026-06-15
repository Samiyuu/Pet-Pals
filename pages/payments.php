<?php
define('APP_RUN', true);
require_once '../includes/auth.php';
require_once '../includes/config.php';
requireLogin();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $rental_id      = $_POST['rental_id'] ?? '';
    $amount         = floatval($_POST['amount'] ?? 0);
    $payment_date   = $_POST['payment_date'] ?? date('Y-m-d H:i:s');
    $payment_method = $_POST['payment_method'] ?? 'Cash';

    if (!empty($rental_id) && $amount > 0) {
        $checkRental = $pdo->prepare("
            SELECT r.rental_fee, r.rental_date, r.actual_return, r.rental_days, r.status, p.rental_price 
            FROM rentals r
            JOIN pets p ON r.pet_id = p.pet_id
            WHERE r.rental_id = ?
        ");
        $checkRental->execute([$rental_id]);
        $rentalData = $checkRental->fetch();

        if (!$rentalData) {
            $error = "Rental record not found.";
        } else {
            if ($rentalData['status'] === 'Cancelled' || $rentalData['status'] === 'Returned') {
                $rentalDate = new DateTime($rentalData['rental_date']);
                $returnDate = !empty($rentalData['actual_return']) ? new DateTime($rentalData['actual_return']) : new DateTime();
                $daysUsed = $returnDate->diff($rentalDate)->days + 1;
                if ($daysUsed > $rentalData['rental_days']) $daysUsed = $rentalData['rental_days'];
                $correctFee = $rentalData['rental_price'] * $daysUsed;
                $messageNote = " (Charged only for $daysUsed day(s) used)";
            } else {
                $correctFee = floatval($rentalData['rental_fee']);
                $messageNote = "";
            }

            if ($amount != $correctFee) {
                $error = "❌ Invalid amount! Correct payment is ₱" . number_format($correctFee, 2) . $messageNote;
            } else {
                $checkPayment = $pdo->prepare("SELECT payment_id FROM payments WHERE rental_id = ?");
                $checkPayment->execute([$rental_id]);
                if ($checkPayment->rowCount() > 0) {
                    $error = "⚠️ Payment already recorded for this rental.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO payments (rental_id, amount, payment_method, payment_date) 
                                           VALUES (?, ?, ?, ?)");
                    $stmt->execute([$rental_id, $correctFee, $payment_method, $payment_date]);
                    header("Location: payments.php?success=1");
                    exit;
                }
            }
        }
    } else {
        $error = "Please fill all required fields correctly.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payment'])) {
    $del_id = intval($_POST['delete_payment']);
    $pdo->prepare("DELETE FROM payments WHERE payment_id = ?")->execute([$del_id]);
    header("Location: payments.php?deleted=1");
    exit;
}

$payments = $pdo->query("
    SELECT 
        p.payment_id,
        p.rental_id,
        p.amount,
        p.payment_method,
        p.payment_date,
        c.customer_name,
        pet.pet_name,
        pet.species,
        r.rental_fee,
        r.status AS rental_status
    FROM payments p
    JOIN rentals r ON p.rental_id = r.rental_id
    JOIN customers c ON r.customer_id = c.customer_id
    JOIN pets pet ON r.pet_id = pet.pet_id
    ORDER BY p.payment_date DESC
")->fetchAll();

$unpaid_rentals = $pdo->query("
    SELECT 
        r.rental_id, 
        c.customer_name, 
        pet.pet_name,
        pet.species,
        r.rental_fee,
        r.status,
        r.rental_date,
        r.actual_return,
        r.rental_days,
        pet.rental_price
    FROM rentals r
    JOIN customers c ON r.customer_id = c.customer_id
    JOIN pets pet ON r.pet_id = pet.pet_id
    WHERE (r.status IN ('Active', 'Returned', 'Cancelled'))
      AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.rental_id = r.rental_id)
    ORDER BY r.rental_date DESC
")->fetchAll();

include '../includes/header.php';
?>

<style>
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 1rem !important;
    }
    .table-wrapper {
        overflow-x: auto;
        width: 100%;
    }
    .btn-group {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }
    .btn-group .btn {
        width: 100%;
        text-align: center;
    }
    .form-row {
        flex-direction: column;
        gap: 1rem;
    }
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
</style>

<div class="page-header">
    <h2>💳 Payment Management</h2>

</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">✅ Payment recorded successfully!</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success">🗑️ Payment deleted — rental is now unpaid again!</div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (empty($unpaid_rentals)): ?>
<div class="alert alert-info">ℹ️ All rentals are fully paid — you can still view or delete old payments below.</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title">All Payments</div>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Pet</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Date</th>
                        <th>Rental Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">No payments recorded yet.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($payments as $pay): ?>
                    <tr>
                        <td>#<?= $pay['payment_id'] ?></td>
                        <td><?= htmlspecialchars($pay['customer_name']) ?></td>
                        <td><?= htmlspecialchars($pay['pet_name']) ?> (<?= $pay['species'] ?>)</td>
                        <td>₱<?= number_format($pay['amount'], 2) ?></td>
                        <td><span class="badge badge-info"><?= $pay['payment_method'] ?></span></td>
                        <td><?= date('M j, Y g:i A', strtotime($pay['payment_date'])) ?></td>
                        <td><span class="badge <?= $pay['rental_status'] == 'Returned' ? 'badge-success' : ($pay['rental_status'] == 'Cancelled' ? 'badge-secondary' : 'badge-warning') ?>"><?= $pay['rental_status'] ?></span></td>
                        <td>
                            <div class="btn-group">
                                <a href="receipt.php?rental_id=<?= $pay['rental_id'] ?>" target="_blank" class="btn btn-sm btn-info">🧾 Receipt</a>
                                <form method="POST" onsubmit="return confirm('Delete this payment? This rental will become unpaid again!')" style="display:inline;">
                                    <input type="hidden" name="delete_payment" value="<?= $pay['payment_id'] ?>">
                                    <?php if($_SESSION['role']=='admisn') : ?><button class="btn btn-danger btn-sm">Delete</button> <?php endif; ?>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="paymentModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Record New Payment</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <?php if (empty($unpaid_rentals)): ?>
            <div class="alert alert-info">✅ No unpaid rentals available.<br>All rentals are already paid.</div>
        <?php else: ?>
        <form method="POST" action="payments.php">
            <div class="form-group">
                <label>Select Rental Transaction</label>
                <select name="rental_id" class="form-control" required onchange="updateAmount(this)">
                    <option value="">-- Choose Rental --</option>
                    <?php foreach ($unpaid_rentals as $r): ?>
                        <?php
                        if ($r['status'] === 'Cancelled' || $r['status'] === 'Returned') {
                            $rentalDate = new DateTime($r['rental_date']);
                            $returnDate = !empty($r['actual_return']) ? new DateTime($r['actual_return']) : new DateTime();
                            $daysUsed = $returnDate->diff($rentalDate)->days + 1;
                            if ($daysUsed > $r['rental_days']) $daysUsed = $r['rental_days'];
                            $showFee = $r['rental_price'] * $daysUsed;
                            $label = "#{$r['rental_id']} - " . htmlspecialchars($r['customer_name']) . " (" . htmlspecialchars($r['pet_name']) . ") — " . strtoupper($r['status']) . ": ₱" . number_format($showFee,2) . " ($daysUsed day(s) used)";
                        } else {
                            $showFee = $r['rental_fee'];
                            $label = "#{$r['rental_id']} - " . htmlspecialchars($r['customer_name']) . " (" . htmlspecialchars($r['pet_name']) . ") — ₱" . number_format($showFee,2);
                        }
                        ?>
                        <option value="<?= $r['rental_id'] ?>" data-fee="<?= $showFee ?>">
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Payment Amount (₱) <small class="text-muted">(Fixed — cannot change)</small></label>
                <input type="number" step="0.01" name="amount" id="pay_amount" class="form-control" required placeholder="0.00" readonly style="background:#f5f5f5; cursor:not-allowed;">
            </div>

            <div class="form-row" style="display:flex; gap:1rem;">
                <div class="form-group" style="flex:1;">
                    <label>Payment Date & Time</label>
                    <input type="datetime-local" name="payment_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="Cash">Cash</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="add_payment" class="btn btn-primary w-100 mt-2">Save Payment Record</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('paymentModal').classList.add('active');
}
function closeModal() {
    document.getElementById('paymentModal').classList.remove('active');
}
function updateAmount(select) {
    let fee = select.options[select.selectedIndex].getAttribute('data-fee');
    if (fee) {
        document.getElementById('pay_amount').value = fee;
    } else {
        document.getElementById('pay_amount').value = '';
    }
}
</script>

<?php include '../includes/footer.php'; ?>