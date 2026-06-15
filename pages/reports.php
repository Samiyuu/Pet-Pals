<?php
define('APP_RUN', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$page_title = 'Reports & Export';
$current_date = date('Y-m-d');
$active_tab = $_GET['tab'] ?? 'rental';

if ($active_tab === 'rental') {
    $print_filename = "PetPal_Rental_Report_{$current_date}";
} elseif ($active_tab === 'revenue') {
    $print_filename = "PetPal_Revenue_Report_{$current_date}";
} elseif ($active_tab === 'customer') {
    $print_filename = "PetPal_Customer_Report_{$current_date}";
} elseif ($active_tab === 'pet') {
    $print_filename = "PetPal_Pet_Report_{$current_date}";
} else {
    $print_filename = "PetPal_Report_{$current_date}";
}

if (isset($_GET['export'])) {
    $type = $_GET['export'];
    
    if ($type === 'rentals') {
        $filename = "PetPal_Rental_Report_{$current_date}.csv";
    } elseif ($type === 'revenue') {
        $filename = "PetPal_Revenue_Report_{$current_date}.csv";
    } elseif ($type === 'customers') {
        $filename = "PetPal_Customer_Report_{$current_date}.csv";
    } elseif ($type === 'pets') {
        $filename = "PetPal_Pet_Report_{$current_date}.csv";
    } else {
        $filename = "PetPal_Report_{$current_date}.csv";
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    if ($type === 'rentals') {
        fputcsv($output, ['Rental ID', 'Customer', 'Pet', 'Species', 'Rental Date', 'Expected Return', 'Actual Return', 'Days', 'Total Fee', 'Status']);
        $rows = $pdo->query("
            SELECT r.rental_id, c.customer_name, p.pet_name, p.species,
                   r.rental_date, r.expected_return, r.actual_return,
                   r.rental_days, r.rental_fee, r.status
            FROM rentals r
            JOIN customers c ON r.customer_id = c.customer_id
            JOIN pets p ON r.pet_id = p.pet_id
            ORDER BY r.created_at DESC
        ")->fetchAll();
        foreach ($rows as $row) fputcsv($output, $row);
    } elseif ($type === 'revenue') {
        fputcsv($output, ['Year', 'Month', 'Total Revenue', 'Total Rentals']);
        $rows = $pdo->query("
            SELECT YEAR(pay.payment_date), MONTHNAME(pay.payment_date),
                   SUM(pay.amount) AS revenue, COUNT(DISTINCT pay.rental_id) AS rentals
            FROM payments pay
            GROUP BY YEAR(pay.payment_date), MONTH(pay.payment_date)
            ORDER BY YEAR(pay.payment_date), MONTH(pay.payment_date)
        ")->fetchAll();
        foreach ($rows as $row) fputcsv($output, $row);
    } elseif ($type === 'customers') {
        fputcsv($output, ['Customer ID', 'Name', 'Contact', 'Total Rentals', 'Last Rental', 'Total Spent']);
        $rows = $pdo->query("
            SELECT c.customer_id, c.customer_name, c.contact_number,
                   COUNT(r.rental_id) AS rentals,
                   MAX(r.rental_date) AS last_rental,
                   COALESCE(SUM(pay.amount), 0) AS total_spent
            FROM customers c
            LEFT JOIN rentals r ON c.customer_id = r.customer_id AND r.status != 'Cancelled'
            LEFT JOIN payments pay ON r.rental_id = pay.rental_id
            GROUP BY c.customer_id
            ORDER BY rentals DESC
        ")->fetchAll();
        foreach ($rows as $row) fputcsv($output, $row);
    } elseif ($type === 'pets') {
        fputcsv($output, ['Pet ID', 'Name', 'Species', 'Breed', 'Rental Price/Day', 'Times Rented', 'Total Revenue', 'Status']);
        $rows = $pdo->query("
            SELECT p.pet_id, p.pet_name, p.species, p.breed, p.rental_price,
                   COUNT(r.rental_id) AS times_rented,
                   COALESCE(SUM(pay.amount), 0) AS revenue,
                   p.status
            FROM pets p
            LEFT JOIN rentals r ON p.pet_id = r.pet_id AND r.status = 'Returned'
            LEFT JOIN payments pay ON r.rental_id = pay.rental_id
            GROUP BY p.pet_id
            ORDER BY times_rented DESC
        ")->fetchAll();
        foreach ($rows as $row) fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

$rental_report = $pdo->query("
    SELECT r.rental_id, c.customer_name, p.pet_name, p.species,
           r.rental_date, r.expected_return, r.actual_return,
           r.rental_days, r.rental_fee, r.status
    FROM rentals r
    JOIN customers c ON r.customer_id = c.customer_id
    JOIN pets p ON r.pet_id = p.pet_id
    ORDER BY r.rental_date DESC
    LIMIT 50
")->fetchAll();

$revenue_report = $pdo->query("
    SELECT YEAR(pay.payment_date) AS year,
           MONTHNAME(pay.payment_date) AS month,
           SUM(pay.amount) AS revenue,
           COUNT(DISTINCT pay.rental_id) AS rentals
    FROM payments pay
    GROUP BY YEAR(pay.payment_date), MONTH(pay.payment_date)
    ORDER BY YEAR(pay.payment_date), MONTH(pay.payment_date)
")->fetchAll();

$customer_report = $pdo->query("
    SELECT c.customer_name, c.contact_number,
           COUNT(r.rental_id) AS rentals,
           MAX(r.rental_date) AS last_rental,
           COALESCE(SUM(pay.amount), 0) AS total_spent
    FROM customers c
    LEFT JOIN rentals r ON c.customer_id = r.customer_id AND r.status != 'Cancelled'
    LEFT JOIN payments pay ON r.rental_id = pay.rental_id
    GROUP BY c.customer_id
    ORDER BY rentals DESC
")->fetchAll();

$pet_report = $pdo->query("
    SELECT p.pet_name, p.species, p.breed, p.rental_price, p.status,
           COUNT(r.rental_id) AS times_rented,
           COALESCE(SUM(pay.amount), 0) AS revenue
    FROM pets p
    LEFT JOIN rentals r ON p.pet_id = r.pet_id AND r.status = 'Returned'
    LEFT JOIN payments pay ON r.rental_id = pay.rental_id
    GROUP BY p.pet_id
    ORDER BY times_rented DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<script>
document.title = "<?= $print_filename ?>";
function printPage() {
    const originalTitle = document.title;
    document.title = "<?= $print_filename ?>";
    window.print();
    document.title = originalTitle;
}
</script>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
body {
    font-family: Arial, sans-serif;
    line-height: 1.4;
    background: #f5f7fa;
}
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start !important;
    }
    .tab-buttons {
        flex-direction: column;
        gap: 8px;
    }
    .table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin: 0 -15px;
        padding: 0 15px;
    }
    .btn {
        width: 100%;
    }
    .card-body {
        padding: 12px !important;
    }
    table th, table td {
        padding: 6px 8px !important;
        font-size: 0.85rem;
        white-space: nowrap;
    }
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 15px;
    flex-wrap: wrap;
}
.tab-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    padding: 12px 15px;
}
.card {
    margin: 15px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    background: #fff;
    overflow: hidden;
}
.card-header {
    padding: 12px 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    flex-wrap: wrap;
    gap: 8px;
}
.card-body {
    padding: 15px;
}
.table-wrapper {
    width: 100%;
}
table {
    width: 100%;
    border-collapse: collapse;
    table-layout: auto;
}
table th, table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}
table th {
    background: #f8f9fa;
    font-weight: 600;
}
.badge {
    padding: 3px 7px;
    border-radius: 3px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #fff;
    display: inline-block;
}
.badge-success { background: #28a745; }
.badge-warning { background: #ffc107; color: #212529; }
.badge-gray { background: #6c757d; }
.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    font-size: 0.9rem;
    line-height: 1.2;
}
.btn-sm {
    padding: 4px 8px;
    font-size: 0.85rem;
}
.btn-primary {
    background: #007bff;
    color: #fff;
}
.btn-light {
    background: #f8f9fa;
    color: #333;
    border: 1px solid #ddd;
}
.no-print {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
@media print {
    @page {
        size: A4;
        margin: 1cm;
    }
    body {
        background: #fff !important;
        font-size: 12pt;
    }
    .no-print, .no-print * {
        display: none !important;
    }
    .card {
        box-shadow: none !important;
        border: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .card-header {
        background: #fff !important;
        border-bottom: 2px solid #000 !important;
        font-size: 14pt;
        padding: 5px 0 !important;
        margin-bottom: 10px !important;
    }
    table {
        width: 100% !important;
        border: 1px solid #000 !important;
    }
    table th {
        background: #eee !important;
        border: 1px solid #000 !important;
        color: #000 !important;
    }
    table td {
        border: 1px solid #000 !important;
    }
    .page-header {
        margin: 0 0 15px 0 !important;
        font-size: 16pt;
        border-bottom: 2px solid #000;
        padding-bottom: 5px;
    }
    .table-wrapper {
        overflow: visible !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    tr {
        page-break-inside: avoid;
    }
}
</style>

<div class="page-header">
    <h2>📄 Reports & Export</h2>
    <button class="btn btn-primary no-print" onclick="printPage()">🖨️ Print This Page</button>
</div>

<div class="card no-print">
    <div class="tab-buttons">
        <a href="?tab=rental"   class="btn <?= $active_tab==='rental'   ? 'btn-primary' : 'btn-light' ?>">📋 Rental Report</a>
        <a href="?tab=revenue"  class="btn <?= $active_tab==='revenue'  ? 'btn-primary' : 'btn-light' ?>">💰 Revenue Report</a>
        <a href="?tab=customer" class="btn <?= $active_tab==='customer' ? 'btn-primary' : 'btn-light' ?>">👥 Customer Report</a>
        <a href="?tab=pet"      class="btn <?= $active_tab==='pet'      ? 'btn-primary' : 'btn-light' ?>">🐾 Pet Performance</a>
    </div>
</div>

<?php if ($active_tab === 'rental'): ?>
<div class="card">
    <div class="card-header">
        <span>📋 Rental Report</span>
        <a href="?export=rentals" class="btn btn-primary btn-sm no-print">⬇️ Export CSV</a>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#ID</th><th>Customer</th><th>Pet</th>
                        <th>Rental Date</th><th>Expected Return</th>
                        <th>Days</th><th>Fee</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rental_report as $r): ?>
                    <tr>
                        <td>#<?= $r['rental_id'] ?></td>
                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                        <td><?= htmlspecialchars($r['pet_name']) ?> (<?= htmlspecialchars($r['species']) ?>)</td>
                        <td><?= date('M j, Y', strtotime($r['rental_date'])) ?></td>
                        <td><?= date('M j, Y', strtotime($r['expected_return'])) ?></td>
                        <td><?= $r['rental_days'] ?></td>
                        <td>₱<?= number_format($r['rental_fee'], 2) ?></td>
                        <td>
                            <span class="badge <?= $r['status'] === 'Active' ? 'badge-warning' : ($r['status'] === 'Returned' ? 'badge-success' : 'badge-gray') ?>">
                                <?= $r['status'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($active_tab === 'revenue'): ?>
<div class="card">
    <div class="card-header">
        <span>💰 Revenue Report</span>
        <a href="?export=revenue" class="btn btn-primary btn-sm no-print">⬇️ Export CSV</a>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Year</th><th>Month</th><th>Rentals</th><th>Revenue</th></tr>
                </thead>
                <tbody>
                    <?php
                    $grand_total = 0;
                    foreach ($revenue_report as $r):
                        $grand_total += $r['revenue'];
                    ?>
                    <tr>
                        <td><?= $r['year'] ?></td>
                        <td><?= $r['month'] ?></td>
                        <td><?= $r['rentals'] ?></td>
                        <td>₱<?= number_format($r['revenue'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background:#f8f9fa;">
                        <td colspan="3"><strong>Grand Total</strong></td>
                        <td><strong>₱<?= number_format($grand_total, 2) ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($active_tab === 'customer'): ?>
<div class="card">
    <div class="card-header">
        <span>👥 Customer Report</span>
        <a href="?export=customers" class="btn btn-primary btn-sm no-print">⬇️ Export CSV</a>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Name</th><th>Contact</th><th>Total Rentals</th><th>Last Rental</th><th>Total Spent</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($customer_report as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['customer_name']) ?></td>
                        <td><?= htmlspecialchars($c['contact_number']) ?: '—' ?></td>
                        <td><?= $c['rentals'] ?></td>
                        <td><?= $c['last_rental'] ? date('M j, Y', strtotime($c['last_rental'])) : '—' ?></td>
                        <td>₱<?= number_format($c['total_spent'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($active_tab === 'pet'): ?>
<div class="card">
    <div class="card-header">
        <span>🐾 Pet Performance Report</span>
        <a href="?export=pets" class="btn btn-primary btn-sm no-print">⬇️ Export CSV</a>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Name</th><th>Species</th><th>Price/Day</th><th>Times Rented</th><th>Revenue</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pet_report as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['pet_name']) ?></td>
                        <td><?= htmlspecialchars($p['species']) ?></td>
                        <td>₱<?= number_format($p['rental_price'], 2) ?></td>
                        <td><?= $p['times_rented'] ?></td>
                        <td>₱<?= number_format($p['revenue'], 2) ?></td>
                        <td>
                            <span class="badge <?= $p['status'] === 'Available' ? 'badge-success' : ($p['status'] === 'Rented' ? 'badge-warning' : 'badge-gray') ?>">
                                <?= $p['status'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>