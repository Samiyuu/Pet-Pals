<?php
define('APP_RUN', true);    
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

requireLogin();

$message = '';
$error   = '';
$rental  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'return') {
    $rental_id      = intval($_POST['rental_id']);
    $actual_return  = $_POST['actual_return'] ?? date('Y-m-d');
    $penalty_amount = floatval($_POST['penalty_amount'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT r.*, p.pet_id 
        FROM rentals r 
        JOIN pets p ON r.pet_id = p.pet_id 
        WHERE r.rental_id = ? AND r.status = 'Active'
    ");
    $stmt->execute([$rental_id]);
    $rentalData = $stmt->fetch();

    if (!$rentalData) {
        $error = 'Rental not found or already processed.';
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE rentals SET status='Returned', actual_return=? WHERE rental_id=?")
                ->execute([$actual_return, $rental_id]);

            $pdo->prepare("UPDATE pets SET status='Available' WHERE pet_id=?")
                ->execute([$rentalData['pet_id']]);

            if ($penalty_amount > 0) {
                $pdo->prepare("INSERT INTO penalties (rental_id, amount, reason) VALUES (?, ?, 'Late Return')")
                    ->execute([$rental_id, $penalty_amount]);

                $pdo->prepare("INSERT INTO payments (rental_id, amount, payment_method) VALUES (?, ?, 'Cash')")
                    ->execute([$rental_id, $penalty_amount]);
            }

            $pdo->commit();

            $message = 'Return processed successfully!';
            header("Location: receipt.php?rental_id=$rental_id&returned=1");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to process return. Please try again.';
        }
    }
}

$preloadId = intval($_GET['rental_id'] ?? 0);
if ($preloadId) {
    $stmt = $pdo->prepare("
        SELECT r.*, c.customer_name, p.pet_name, p.species, p.rental_price
        FROM rentals r
        JOIN customers c ON r.customer_id = c.customer_id
        JOIN pets p ON r.pet_id = p.pet_id
        WHERE r.rental_id = ? AND r.status = 'Active'
    ");
    $stmt->execute([$preloadId]);
    $rental = $stmt->fetch();
}

$activeRentals = $pdo->query("
    SELECT r.rental_id, c.customer_name, p.pet_name, r.rental_date, r.expected_return, r.rental_fee
    FROM rentals r
    JOIN customers c ON r.customer_id = c.customer_id
    JOIN pets p ON r.pet_id = p.pet_id
    WHERE r.status = 'Active'
    ORDER BY r.expected_return ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<h2 class="page-title">Process Return</h2>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($rental): ?>
<div class="card">
    <div class="card-header">🔄 Return Pet: <?= htmlspecialchars($rental['pet_name']) ?></div>
    <div class="card-body">
        <div class="form-grid mb-2">
            <div>
                <strong>Customer:</strong> <?= htmlspecialchars($rental['customer_name']) ?><br>
                <strong>Pet:</strong> <?= htmlspecialchars($rental['pet_name']) ?> (<?= $rental['species'] ?>)<br>
                <strong>Rental Fee:</strong> ₱<?= number_format($rental['rental_fee'], 2) ?>
            </div>
            <div>
                <strong>Rental Date:</strong> <?= $rental['rental_date'] ?><br>
                <strong>Expected Return:</strong> <?= $rental['expected_return'] ?><br>
                <strong>Daily Rate:</strong> ₱<?= number_format($rental['rental_price'], 2) ?>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="return">
            <input type="hidden" name="rental_id" value="<?= $rental['rental_id'] ?>">
            <input type="hidden" id="expected_return" value="<?= $rental['expected_return'] ?>">
            <input type="hidden" id="price_per_day" value="<?= $rental['rental_price'] ?>">
            <input type="hidden" name="penalty_amount" id="penalty_amount" value="0">

            <div class="form-grid">
                <div class="form-group">
                    <label>Actual Return Date *</label>
                    <input type="date" name="actual_return" id="actual_return" class="form-control"
                           value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"
                           onchange="calculatePenalty()" required>
                </div>
                <div class="form-group">
                    <label>Penalty Calculation</label>
                    <div id="penalty_display" style="padding:8px; font-weight:600; color:#2E7D32;">
                        Select return date to calculate
                    </div>
                    <p class="form-hint">Penalty = 50% of daily rate per late day</p>
                </div>
            </div>

            <div class="btn-group mt-2">
                <button type="submit" class="btn btn-success btn-lg">✅ Confirm Return</button>
                <a href="returns.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function calculatePenalty() {
    const expected = new Date(document.getElementById('expected_return').value);
    const actual = new Date(document.getElementById('actual_return').value);
    const price = parseFloat(document.getElementById('price_per_day').value);
    let penalty = 0;
    let displayText = 'On time — no penalty';

    if (actual > expected) {
        const diffTime = Math.abs(actual - expected);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        penalty = diffDays * (price * 0.5);
        displayText = diffDays + ' day(s) late — Penalty: ₱' + penalty.toFixed(2);
    }

    document.getElementById('penalty_amount').value = penalty.toFixed(2);
    document.getElementById('penalty_display').textContent = displayText;
}

document.addEventListener('DOMContentLoaded', calculatePenalty);
</script>
<?php endif; ?>

<div class="card">
    <div class="card-header">📋 Active Rentals - Select to Process Return</div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Customer</th><th>Pet</th><th>Rented</th><th>Expected Return</th><th>Fee</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($activeRentals)): ?>
                        <tr><td colspan="7" class="table-empty">No active rentals.</td></tr>
                    <?php else: ?>
                        <?php foreach ($activeRentals as $r): ?>
                        <?php $overdue = strtotime($r['expected_return']) < strtotime('today'); ?>
                        <tr <?= $overdue ? 'style="background:#FFF8E1;"' : '' ?>>
                            <td>#<?= $r['rental_id'] ?></td>
                            <td><?= htmlspecialchars($r['customer_name']) ?></td>
                            <td><?= htmlspecialchars($r['pet_name']) ?></td>
                            <td><?= $r['rental_date'] ?></td>
                            <td>
                                <?= $r['expected_return'] ?>
                                <?php if ($overdue): ?><span class="badge badge-danger">OVERDUE</span><?php endif; ?>
                            </td>
                            <td>₱<?= number_format($r['rental_fee'], 2) ?></td>
                            <td>
                                <a href="?rental_id=<?= $r['rental_id'] ?>" class="btn btn-success btn-sm">Process Return</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>