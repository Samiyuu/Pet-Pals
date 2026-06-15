<?php
define('APP_RUN', true);
require_once '../includes/auth.php';
require_once '../includes/config.php';

requireLogin();

$totalPets      = $pdo->query("SELECT COUNT(*) FROM pets WHERE status != 'Archived'")->fetchColumn();
$availablePets  = $pdo->query("SELECT COUNT(*) FROM pets WHERE status = 'Available'")->fetchColumn();
$rentedPets     = $pdo->query("SELECT COUNT(*) FROM pets WHERE status = 'Rented'")->fetchColumn();
$activeRentals  = $pdo->query("SELECT COUNT(*) FROM rentals WHERE status = 'Active'")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$totalRevenue   = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn();
$monthlyRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())")->fetchColumn();

$recentRentals = $pdo->query("
    SELECT r.rental_id, c.customer_name, p.pet_name, p.species, r.rental_date, r.expected_return, r.rental_fee, r.status
    FROM rentals r
    JOIN customers c ON r.customer_id = c.customer_id
    JOIN pets p ON r.pet_id = p.pet_id
    ORDER BY r.created_at DESC LIMIT 5
")->fetchAll();

$monthlyData = $pdo->query("
    SELECT DATE_FORMAT(payment_date, '%b %Y') AS month_label, SUM(amount) AS total
    FROM payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(payment_date), MONTH(payment_date) ORDER BY payment_date ASC
")->fetchAll();
$chartLabels = array_column($monthlyData, 'month_label');
$chartValues = array_column($monthlyData, 'total');

$speciesData = $pdo->query("
    SELECT p.species, COUNT(r.rental_id) as total
    FROM rentals r
    JOIN pets p ON r.pet_id = p.pet_id
    GROUP BY p.species
")->fetchAll();
$speciesLabels = array_column($speciesData, 'species');
$speciesCounts = array_column($speciesData, 'total');

include '../includes/header.php';
?>

<div class="page-header">
    <h2>Dashboard Overview</h2>
    <div>
        <span class="text-muted">Last updated: <?= date('F j, Y') ?></span>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card green">
        <div class="stat-icon">🐾</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalPets ?></div>
            <div class="stat-label">Total Pets</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-info">
            <div class="stat-value"><?= $availablePets ?></div>
            <div class="stat-label">Available</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">🚚</div>
        <div class="stat-info">
            <div class="stat-value"><?= $rentedPets ?></div>
            <div class="stat-label">Rented Out</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">📝</div>
        <div class="stat-info">
            <div class="stat-value"><?= $activeRentals ?></div>
            <div class="stat-label">Active Rentals</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">👥</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalCustomers ?></div>
            <div class="stat-label">Customers</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">💰</div>
        <div class="stat-info">
            <div class="stat-value">₱<?= number_format($monthlyRevenue, 0) ?></div>
            <div class="stat-label">This Month</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">💵</div>
        <div class="stat-info">
            <div class="stat-value">₱<?= number_format($totalRevenue, 0) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<div class="form-row mb-2">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📊 Monthly Revenue (Last 6 Months)</div>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-title">🐾 Rentals by Pet Type</div>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="speciesChart"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title">📋 Recent Rentals</div>
        <a href="rentals.php" class="btn btn-sm btn-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Pet</th>
                        <th>Date</th>
                        <th>Return</th>
                        <th>Fee</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentRentals)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">No transactions yet.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recentRentals as $r): ?>
                    <tr>
                        <td>#<?= $r['rental_id'] ?></td>
                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                        <td><?= htmlspecialchars($r['pet_name']) ?> (<?= $r['species'] ?>)</td>
                        <td><?= $r['rental_date'] ?></td>
                        <td><?= $r['expected_return'] ?></td>
                        <td style="white-space: nowrap;">₱<?= number_format($r['rental_fee'], 2) ?></td>
                        <td>
                            <?php
                            $badge = match($r['status']) {
                                'Active'    => 'badge-warning',
                                'Returned'  => 'badge-success',
                                'Cancelled' => 'badge-gray',
                                default     => 'badge-gray'
                            };
                            ?>
                            <span class="badge <?= $badge ?>"><?= $r['status'] ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Revenue (PHP)',
                data: <?= json_encode($chartValues) ?>,
                backgroundColor: '#2D7D46',
                borderRadius: 4,
                barThickness: 30
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    const speciesCtx = document.getElementById('speciesChart').getContext('2d');
    new Chart(speciesCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($speciesLabels) ?>,
            datasets: [{
                data: <?= json_encode($speciesCounts) ?>,
                backgroundColor: ['#2D7D46', '#3182ce', '#F4A62A', '#e53e3e', '#dd6b20'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>