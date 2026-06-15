<?php
define('APP_RUN', true);
require_once '../includes/auth.php';
require_once '../includes/config.php';

requireLogin();

$message = '';
$error   = '';
$editCustomer = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $customer_name   = trim($_POST['customer_name'] ?? '');
        $contact_number  = trim($_POST['contact_number'] ?? '');
        $address         = trim($_POST['address'] ?? '');

        if (empty($customer_name) || empty($contact_number) || empty($address)) {
            $error = 'All fields are required.';
        } else {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO customers (customer_name, contact_number, address) VALUES (?, ?, ?)");
                $stmt->execute([$customer_name, $contact_number, $address]);
                $message = 'Customer added successfully!';
            } else {
                $customer_id = intval($_POST['customer_id']);
                $stmt = $pdo->prepare("UPDATE customers SET customer_name=?, contact_number=?, address=? WHERE customer_id=?");
                $stmt->execute([$customer_name, $contact_number, $address, $customer_id]);
                $message = 'Customer updated successfully!';
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $editCustomer = $stmt->fetch();
}

$viewHistory = null;
if (isset($_GET['history'])) {
    $cid = intval($_GET['history']);
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$cid]);
    $viewHistory = $stmt->fetch();

    if ($viewHistory) {
        $stmt = $pdo->prepare("
            SELECT r.*, p.pet_name, p.species, pay.amount as paid_amount
            FROM rentals r
            JOIN pets p ON r.pet_id = p.pet_id
            LEFT JOIN payments pay ON r.rental_id = pay.rental_id
            WHERE r.customer_id = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$cid]);
        $customerRentals = $stmt->fetchAll();
    }
}

$search = trim($_GET['search'] ?? '');
$where  = [];
$params = [];

if ($search) {
    $where[] = "(customer_name LIKE ? OR contact_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("
    SELECT c.*, COUNT(r.rental_id) as total_rentals
    FROM customers c
    LEFT JOIN rentals r ON c.customer_id = r.customer_id
    $whereSQL
    GROUP BY c.customer_id
    ORDER BY c.customer_name ASC
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

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
        margin: 0;
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
    .d-flex {
        flex-wrap: wrap;
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
</style>

<div class="page-header-wrap">
    <h2 class="page-title">Customer Management</h2>
    <a href="?show_form=1" class="btn btn-primary">+ Add Customer</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (isset($_GET['show_form']) || $editCustomer): ?>
<div class="card">
    <div class="card-header"><?= $editCustomer ? '✏️ Edit Customer' : '➕ Add New Customer' ?></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $editCustomer ? 'edit' : 'add' ?>">
            <?php if ($editCustomer): ?>
                <input type="hidden" name="customer_id" value="<?= $editCustomer['customer_id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="customer_name" class="form-control"
                           value="<?= htmlspecialchars($editCustomer['customer_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Contact Number *</label>
                    <input type="text" name="contact_number" class="form-control"
                           placeholder="e.g. 09171234567"
                           value="<?= htmlspecialchars($editCustomer['contact_number'] ?? '') ?>" required>
                </div>
                <div class="form-group form-full">
                    <label>Address *</label>
                    <textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($editCustomer['address'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="btn-group mt-2">
                <button type="submit" class="btn btn-primary">💾 Save</button>
                <a href="customers.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($viewHistory): ?>
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>📋 Rental History: <?= htmlspecialchars($viewHistory['customer_name']) ?></span>
        <a href="customers.php" class="btn btn-sm" style="background:rgba(255,255,255,0.2);color:white;">Close</a>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Pet</th><th>Rented</th><th>Expected Return</th><th>Actual Return</th><th>Fee</th><th>Paid</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($customerRentals)): ?>
                        <tr><td colspan="8" class="table-empty">No rentals yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($customerRentals as $r): ?>
                        <tr>
                            <td>#<?= $r['rental_id'] ?></td>
                            <td><?= htmlspecialchars($r['pet_name']) ?> (<?= $r['species'] ?>)</td>
                            <td><?= $r['rental_date'] ?></td>
                            <td><?= $r['expected_return'] ?></td>
                            <td><?= $r['actual_return'] ?? '—' ?></td>
                            <td>₱<?= number_format($r['rental_fee'], 2) ?></td>
                            <td><?= $r['paid_amount'] ? '₱' . number_format($r['paid_amount'], 2) : '—' ?></td>
                            <td><span class="badge <?= $r['status']==='Active'?'badge-warning':($r['status']==='Returned'?'badge-success':'badge-secondary') ?>"><?= $r['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="GET" class="form-inline d-flex align-center">
            <div class="form-group">
                <label>Search Customer</label>
                <input type="text" name="search" class="form-control" placeholder="Name or phone..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">🔍 Search</button>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <a href="customers.php" class="btn btn-secondary w-100">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">👥 Customers (<?= count($customers) ?>)</div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Name</th><th>Contact</th><th>Address</th><th>Total Rentals</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr><td colspan="6" class="table-empty">No customers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                        <tr>
                            <td><?= $c['customer_id'] ?></td>
                            <td><strong><?= htmlspecialchars($c['customer_name']) ?></strong></td>
                            <td><?= htmlspecialchars($c['contact_number']) ?></td>
                            <td><?= htmlspecialchars($c['address']) ?></td>
                            <td><span class="badge badge-info"><?= $c['total_rentals'] ?> rental(s)</span></td>
                            <td>
                                <div class="btn-group">
                                    <a href="?history=<?= $c['customer_id'] ?>" class="btn btn-info btn-sm">History</a>
                                    <a href="?edit=<?= $c['customer_id'] ?>" class="btn btn-warning btn-sm">Edit</a>
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

<?php include '../includes/footer.php'; ?>