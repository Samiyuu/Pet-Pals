<?php
define('APP_RUN', true);
require_once '../includes/auth.php';
require_once '../includes/config.php';

requireLogin();

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $pet_id      = intval($_POST['pet_id'] ?? 0);
    $rental_days = intval($_POST['rental_days'] ?? 0);

    if (!$customer_id || !$pet_id || $rental_days < 1) {
        $error = 'Please fill in all required fields.';
    } else {
        $checkPet = $pdo->prepare("SELECT status, rental_price FROM pets WHERE pet_id = ?");
        $checkPet->execute([$pet_id]);
        $petData = $checkPet->fetch();

        if (!$petData || $petData['status'] !== 'Available') {
            $error = 'Sorry, that pet is no longer available.';
        } else {
            $rentalDate     = date('Y-m-d');
            $expectedReturn = date('Y-m-d', strtotime("+$rental_days days"));
            $fee            = $petData['rental_price'] * $rental_days;

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO rentals (customer_id, pet_id, rental_date, expected_return, rental_days, rental_fee, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, 'Active', ?)
                ");
                $stmt->execute([$customer_id, $pet_id, $rentalDate, $expectedReturn, $rental_days, $fee, $_SESSION['user_id']]);
                $rentalId = $pdo->lastInsertId();

                $payStmt = $pdo->prepare("INSERT INTO payments (rental_id, amount, payment_method) VALUES (?, ?, 'Cash')");
                $payStmt->execute([$rentalId, $fee]);

                $pdo->prepare("UPDATE pets SET status = 'Rented' WHERE pet_id = ?")->execute([$pet_id]);

                $pdo->commit();

                header("Location: receipt.php?rental_id=$rentalId");
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Transaction failed. Please try again.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $rental_id = intval($_POST['rental_id']);
    $stmt = $pdo->prepare("SELECT pet_id, status FROM rentals WHERE rental_id = ?");
    $stmt->execute([$rental_id]);
    $rental = $stmt->fetch();

    if ($rental && $rental['status'] === 'Active') {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE rentals SET status = 'Cancelled' WHERE rental_id = ?")->execute([$rental_id]);
            $pdo->prepare("UPDATE pets SET status = 'Available' WHERE pet_id = ?")->execute([$rental['pet_id']]);
            $pdo->commit();
            $message = 'Rental cancelled.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Could not cancel. Please try again.';
        }
    }
}

$customers = $pdo->query("SELECT customer_id, customer_name FROM customers ORDER BY customer_name")->fetchAll();

$allPets = $pdo->query("
    SELECT p.pet_id, p.pet_name, p.species, p.breed, p.rental_price, p.status,
           MAX(r.expected_return) AS available_from
    FROM pets p
    LEFT JOIN rentals r ON p.pet_id = r.pet_id AND r.status = 'Active'
    GROUP BY p.pet_id
    ORDER BY p.pet_name
")->fetchAll();

$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');
$where  = [];
$params = [];

if ($filterStatus) { $where[] = "r.status = ?"; $params[] = $filterStatus; }
if ($search) {
    $where[] = "(c.customer_name LIKE ? OR p.pet_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("
    SELECT r.*, c.customer_name, p.pet_name, p.species
    FROM rentals r
    JOIN customers c ON r.customer_id = c.customer_id
    JOIN pets p ON r.pet_id = p.pet_id
    $whereSQL
    ORDER BY r.created_at DESC
");
$stmt->execute($params);
$rentals = $stmt->fetchAll();

include '../includes/header.php';
?>

<style>
@media (max-width: 768px) {
    .page-header-wrap {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 1rem !important;
    }
    .form-grid {
        grid-template-columns: 1fr !important;
    }
    .form-inline {
        flex-direction: column;
        gap: 0.75rem !important;
        align-items: stretch !important;
    }
    .form-group {
        width: 100%;
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
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.form-full {
    grid-column: 1 / -1;
}
.page-header-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.unavailable {
    color: #999;
    font-style: italic;
}
.form-inline {
    display: flex;
    align-items: flex-end;
    gap: 0.75rem;
    flex-wrap: wrap;
}
</style>

<div class="page-header-wrap">
    <h2 class="page-title">Rental Management</h2>
    <a href="?show_form=1" class="btn btn-primary">+ Create Rental</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (isset($_GET['show_form'])): ?>
<div class="card">
    <div class="card-header">📝 Create New Rental</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="create">

            <div class="form-grid">
                <div class="form-group">
                    <label>Select Customer *</label>
                    <select name="customer_id" class="form-control" required>
                        <option value="">-- Choose Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['customer_id'] ?>"><?= htmlspecialchars($c['customer_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Pet *</label>
                    <select name="pet_id" id="pet_id" class="form-control"
                            onchange="calculateRentalFee(); calculateReturnDate();" required>
                        <option value="">-- Choose Pet --</option>
                        <?php foreach ($allPets as $p): ?>
                            <option value="<?= $p['pet_id'] ?>"
                                    data-price="<?= $p['rental_price'] ?>"
                                    <?= $p['status'] !== 'Available' ? 'disabled class="unavailable"' : '' ?>>
                                <?= htmlspecialchars($p['pet_name']) ?> (<?= $p['species'] ?>) - ₱<?= number_format($p['rental_price'],2) ?>/day
                                <?php if ($p['status'] === 'Rented'): ?>
                                    — Rented until: <?= $p['available_from'] ?>
                                <?php elseif ($p['status'] === 'Archived'): ?>
                                    — Archived
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Rental Days *</label>
                    <input type="number" name="rental_days" id="rental_days" class="form-control"
                           min="1" max="30" placeholder="Number of days"
                           oninput="calculateRentalFee(); calculateReturnDate();" required>
                </div>

                <div class="form-group">
                    <label>Expected Return Date</label>
                    <input type="text" id="expected_return_display" class="form-control"
                           readonly placeholder="Will auto-calculate" style="background:#f5f5f5;">
                </div>

                <div class="form-group form-full">
                    <label>Total Rental Fee (Fixed)</label>
                    <div id="calculated_fee" style="font-size:1.5rem; font-weight:700; color:#2E7D32; padding:8px; border:1px solid #e8f5e9; border-radius:8px; background:#f8fdf9;">
                        — Select pet and days —
                    </div>
                    <p class="form-hint text-muted">✅ System calculated — cannot be changed manually</p>
                    <p class="form-hint">Payment method: Cash only</p>
                </div>
            </div>

            <div class="btn-group mt-2">
                <button type="submit" class="btn btn-primary btn-lg">✅ Confirm Rental & Record Payment</button>
                <a href="rentals.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group">
                <label>Search</label>
                <input type="text" name="search" class="form-control" placeholder="Customer or pet name..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="Active"    <?= $filterStatus==='Active'    ? 'selected':'' ?>>Active</option>
                    <option value="Returned"  <?= $filterStatus==='Returned'  ? 'selected':'' ?>>Returned</option>
                    <option value="Cancelled" <?= $filterStatus==='Cancelled' ? 'selected':'' ?>>Cancelled</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">🔍 Filter</button>
            </div>
            <div class="form-group">
                <a href="rentals.php" class="btn btn-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">📋 All Rentals (<?= count($rentals) ?>)</div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Customer</th>
                        <th>Pet</th>
                        <th>Rental Date</th>
                        <th>Expected Return</th>
                        <th>Days</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rentals)): ?>
                        <tr><td colspan="9" class="table-empty">No rentals found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rentals as $r): ?>
                        <?php
                        $isOverdue = $r['status'] === 'Active' && strtotime($r['expected_return']) < strtotime('today');
                        ?>
                        <tr <?= $isOverdue ? 'style="background:#FFF8E1;"' : '' ?>>
                            <td>#<?= $r['rental_id'] ?></td>
                            <td><?= htmlspecialchars($r['customer_name']) ?></td>
                            <td><?= htmlspecialchars($r['pet_name']) ?> (<?= $r['species'] ?>)</td>
                            <td><?= $r['rental_date'] ?></td>
                            <td>
                                <?= $r['expected_return'] ?>
                                <?php if ($isOverdue): ?>
                                    <span class="badge badge-danger">OVERDUE</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $r['rental_days'] ?>d</td>
                            <td>₱<?= number_format($r['rental_fee'], 2) ?></td>
                            <td>
                                <span class="badge <?= $r['status']==='Active'?'badge-warning':($r['status']==='Returned'?'badge-success':'badge-secondary') ?>">
                                    <?= $r['status'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="receipt.php?rental_id=<?= $r['rental_id'] ?>" class="btn btn-info btn-sm">Receipt</a>
                                    <?php if ($r['status'] === 'Active'): ?>
                                        <a href="returns.php?rental_id=<?= $r['rental_id'] ?>" class="btn btn-success btn-sm">Return</a>
                                        <form method="POST" style="display:inline;"
                                              onsubmit="return confirm('Cancel this rental?')">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="rental_id" value="<?= $r['rental_id'] ?>">
                                            <button class="btn btn-danger btn-sm">Cancel</button>
                                        </form>
                                    <?php endif; ?>
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

<script>
function calculateRentalFee() {
    const petSelect = document.getElementById('pet_id');
    const daysInput = document.getElementById('rental_days');
    const feeDisplay = document.getElementById('calculated_fee');

    const selectedOption = petSelect.options[petSelect.selectedIndex];
    const pricePerDay = selectedOption.dataset.price ? parseFloat(selectedOption.dataset.price) : 0;
    const days = parseInt(daysInput.value) || 0;

    if (pricePerDay > 0 && days > 0) {
        const total = pricePerDay * days;
        feeDisplay.textContent = '₱' + total.toFixed(2);
    } else {
        feeDisplay.textContent = '— Select pet and days —';
    }
}

function calculateReturnDate() {
    const daysInput = document.getElementById('rental_days');
    const display = document.getElementById('expected_return_display');
    const days = parseInt(daysInput.value) || 0;

    if (days > 0) {
        const d = new Date();
        d.setDate(d.getDate() + days);
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const dt = String(d.getDate()).padStart(2, '0');
        display.value = `${y}-${m}-${dt}`;
    } else {
        display.value = '';
    }
}
</script>

<?php include '../includes/footer.php'; ?>