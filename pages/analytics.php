<?php
define('APP_RUN', true);
require_once '../includes/auth.php';
require_once '../includes/config.php';

requireAdmin();

$dailyRevenue = $pdo->query("
    SELECT DATE(r.created_at) as day, 
           COALESCE(SUM(pay.amount), 0) as total
    FROM rentals r
    LEFT JOIN payments pay ON r.rental_id = pay.rental_id
    GROUP BY DATE(r.created_at)
    ORDER BY day ASC
")->fetchAll();

$monthlyRevenue = $pdo->query("
    SELECT DATE_FORMAT(r.created_at, '%b %Y') as month_label,
           YEAR(r.created_at) as yr, MONTH(r.created_at) as mo,
           COALESCE(SUM(pay.amount), 0) as total
    FROM rentals r
    LEFT JOIN payments pay ON r.rental_id = pay.rental_id
    GROUP BY yr, mo
    ORDER BY yr, mo
")->fetchAll();

$annualRevenue = $pdo->query("
    SELECT YEAR(r.created_at) as year, 
           COALESCE(SUM(pay.amount), 0) as total
    FROM rentals r
    LEFT JOIN payments pay ON r.rental_id = pay.rental_id
    GROUP BY YEAR(r.created_at)
    ORDER BY year DESC
")->fetchAll();

$mostRented = $pdo->query("
    SELECT p.pet_name, p.species, 
           COUNT(r.rental_id) as times_rented,
           COALESCE(SUM(pay.amount), 0) as revenue_generated
    FROM pets p
    LEFT JOIN rentals r ON p.pet_id = r.pet_id AND r.status != 'Cancelled'
    LEFT JOIN payments pay ON r.rental_id = pay.rental_id
    GROUP BY p.pet_id
    ORDER BY times_rented DESC
    LIMIT 10
")->fetchAll();

$totalCompletedRentals = $pdo->query("SELECT COUNT(*) FROM rentals WHERE status != 'Cancelled'")->fetchColumn() ?: 0;

$rentalsBySpecies = $pdo->query("
    SELECT p.species, COUNT(r.rental_id) as count
    FROM pets p
    LEFT JOIN rentals r ON p.pet_id = r.pet_id AND r.status != 'Cancelled'
    GROUP BY p.species
    HAVING count > 0
    ORDER BY count DESC
")->fetchAll();

$topCustomers = $pdo->query("
    SELECT c.customer_name, 
           COUNT(r.rental_id) as total_rentals,
           COALESCE(SUM(pay.amount), 0) as total_spent
    FROM customers c
    LEFT JOIN rentals r ON c.customer_id = r.customer_id AND r.status != 'Cancelled'
    LEFT JOIN payments pay ON r.rental_id = pay.rental_id
    GROUP BY c.customer_id
    ORDER BY total_rentals DESC, total_spent DESC
    LIMIT 10
")->fetchAll();

$repeatCustomers = $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT customer_id FROM rentals WHERE status != 'Cancelled'
        GROUP BY customer_id HAVING COUNT(*) > 1
    ) as sub
")->fetchColumn() ?: 0;

$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() ?: 0;

$rentalTrend = $pdo->query("
    SELECT DATE_FORMAT(r.created_at, '%b %Y') as month_label,
           YEAR(r.created_at) as yr, MONTH(r.created_at) as mo,
           COUNT(*) as count
    FROM rentals r
    GROUP BY yr, mo
    ORDER BY yr, mo
    LIMIT 6
")->fetchAll();

$rentalStatusCount = $pdo->query("SELECT status, COUNT(*) as count FROM rentals GROUP BY status")->fetchAll();

$dailyLabels   = !empty($dailyRevenue) ? array_column($dailyRevenue, 'day') : ['No Data'];
$dailyValues   = !empty($dailyRevenue) ? array_map('floatval', array_column($dailyRevenue, 'total')) : [0];

$monthlyLabels = !empty($monthlyRevenue) ? array_column($monthlyRevenue, 'month_label') : ['No Data'];
$monthlyValues = !empty($monthlyRevenue) ? array_map('floatval', array_column($monthlyRevenue, 'total')) : [0];

$speciesLabels = !empty($rentalsBySpecies) ? array_column($rentalsBySpecies, 'species') : ['No Data'];
$speciesValues = !empty($rentalsBySpecies) ? array_map('intval', array_column($rentalsBySpecies, 'count')) : [1];

$trendLabels   = !empty($rentalTrend) ? array_column($rentalTrend, 'month_label') : ['No Data'];
$trendValues   = !empty($rentalTrend) ? array_map('intval', array_column($rentalTrend, 'count')) : [0];

$top5Names  = array_column(array_slice($mostRented, 0, 5), 'pet_name') ?: ['No Data'];
$top5Values = array_map('intval', array_column(array_slice($mostRented, 0, 5), 'times_rented')) ?: [0];

$statusCounts = ['Active' => 0, 'Returned' => 0, 'Cancelled' => 0];
foreach ($rentalStatusCount as $row) {
    if (isset($statusCounts[$row['status']])) $statusCounts[$row['status']] = (int)$row['count'];
}

include '../includes/header.php';
?>

<h2 class="page-title">Analytics Dashboard (OLAP)</h2>

<div class="card">
    <div class="card-header">💰 Revenue Analytics</div>
    <div class="card-body">
        <div class="charts-grid">
            <div>
                <h3 style="margin-bottom:12px;">Monthly Revenue</h3>
                <div class="chart-container" style="position: relative; height:250px;">
                    <canvas id="monthlyRevenueChart"></canvas>
                </div>
            </div>
            <div>
                <h3 style="margin-bottom:12px;">Daily Revenue</h3>
                <div class="chart-container" style="position: relative; height:250px;">
                    <canvas id="dailyRevenueChart"></canvas>
                </div>
            </div>
        </div>

        <h3 class="mt-3">Annual Revenue Summary</h3>
        <div class="table-wrapper mt-1">
            <table class="table">
                <thead><tr><th>Year</th><th>Total Revenue</th></tr></thead>
                <tbody>
                    <?php if (!empty($annualRevenue)): ?>
                        <?php foreach ($annualRevenue as $row): ?>
                        <tr>
                            <td><?= $row['year'] ?></td>
                            <td><strong>₱<?= number_format($row['total'], 2) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="text-center">No revenue data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">🐾 Pet Performance Analytics</div>
    <div class="card-body">
        <div class="charts-grid">
            <div>
                <h3 style="margin-bottom:12px;">Rentals by Species</h3>
                <div class="chart-container" style="position: relative; height:250px;">
                    <canvas id="speciesChart"></canvas>
                </div>
            </div>
            <div>
                <h3 style="margin-bottom:12px;">Top 5 Most Rented Pets</h3>
                <div class="chart-container" style="position: relative; height:250px;">
                    <canvas id="topPetsChart"></canvas>
                </div>
            </div>
        </div>

        <h3 class="mt-3">Pet Rental Performance Table</h3>
        <div class="table-wrapper mt-1">
            <table class="table">
                <thead>
                    <tr><th>Rank</th><th>Pet Name</th><th>Species</th><th>Times Rented</th><th>Revenue Generated</th><th>Utilization Rate</th></tr>
                </thead>
                <tbody>
                    <?php if (!empty($mostRented)): ?>
                        <?php foreach ($mostRented as $i => $p): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($p['pet_name']) ?></strong></td>
                            <td><?= htmlspecialchars($p['species']) ?></td>
                            <td><?= $p['times_rented'] ?></td>
                            <td>₱<?= number_format($p['revenue_generated'], 2) ?></td>
                            <td>
                                <?php
                                $util = $totalCompletedRentals > 0 ? round(($p['times_rented'] / $totalCompletedRentals) * 100, 1) : 0;
                                ?>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="flex:1; height:8px; background:#E0E0E0; border-radius:4px;">
                                        <div style="width:<?= min($util, 100) ?>%; height:100%; background:#4CAF50; border-radius:4px;"></div>
                                    </div>
                                    <span><?= $util ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">No pet rental data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">👥 Customer Analytics</div>
    <div class="card-body">
        <div class="stats-grid" style="margin-bottom:var(--sp-lg);">
            <div class="stat-card">
                <div class="stat-number"><?= $totalCustomers ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-number"><?= $repeatCustomers ?></div>
                <div class="stat-label">Repeat Customers</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-number"><?= $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100) : 0 ?>%</div>
                <div class="stat-label">Repeat Rate</div>
            </div>
        </div>

        <h3>Top Customers by Rental Frequency</h3>
        <div class="table-wrapper mt-1">
            <table class="table">
                <thead><tr><th>Rank</th><th>Customer Name</th><th>Total Rentals</th><th>Total Spent</th></tr></thead>
                <tbody>
                    <?php if (!empty($topCustomers)): ?>
                        <?php foreach ($topCustomers as $i => $c): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($c['customer_name']) ?></td>
                            <td><?= $c['total_rentals'] ?></td>
                            <td>₱<?= number_format($c['total_spent'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">No customer data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">📈 Rental Trend Analytics</div>
    <div class="card-body">
        <div class="charts-grid">
            <div>
                <h3 style="margin-bottom:12px;">Monthly Rental Volume</h3>
                <div class="chart-container" style="position: relative; height:250px;">
                    <canvas id="rentalTrendChart"></canvas>
                </div>
            </div>
            <div>
                <h3 style="margin-bottom:12px;">Rental Status Breakdown</h3>
                <div class="chart-container" style="position: relative; height:250px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
<script>
const monthlyLabels = <?= json_encode($monthlyLabels) ?>;
const monthlyValues = <?= json_encode($monthlyValues) ?>;
const dailyLabels = <?= json_encode($dailyLabels) ?>;
const dailyValues = <?= json_encode($dailyValues) ?>;
const speciesLabels = <?= json_encode($speciesLabels) ?>;
const speciesValues = <?= json_encode($speciesValues) ?>;
const top5Names = <?= json_encode($top5Names) ?>;
const top5Values = <?= json_encode($top5Values) ?>;
const trendLabels = <?= json_encode($trendLabels) ?>;
const trendValues = <?= json_encode($trendValues) ?>;
const statusData = [<?= $statusCounts['Active'] ?>,<?= $statusCounts['Returned'] ?>,<?= $statusCounts['Cancelled'] ?>];

new Chart(document.getElementById('monthlyRevenueChart'), {
    type: 'bar',
    data: {
        labels: monthlyLabels,
        datasets: [{ label: 'Revenue (PHP)', data: monthlyValues,
            backgroundColor: '#4CAF50', borderColor: '#2E7D32', borderWidth: 1, borderRadius: 4 }]
    },
    options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() } } }
    }
});

new Chart(document.getElementById('dailyRevenueChart'), {
    type: 'line',
    data: {
        labels: dailyLabels,
        datasets: [{ label: 'Daily Revenue', data: dailyValues,
            borderColor: '#FF8F00', backgroundColor: 'rgba(255,143,0,0.1)',
            fill: true, tension: 0.4, pointRadius: 3 }]
    },
    options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() } } }
    }
});

new Chart(document.getElementById('speciesChart'), {
    type: 'doughnut',
    data: {
        labels: speciesLabels,
        datasets: [{ data: speciesValues,
            backgroundColor: ['#4CAF50','#FF8F00','#01579B','#C62828','#7B1FA2','#00838F'] }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('topPetsChart'), {
    type: 'bar',
    data: {
        labels: top5Names,
        datasets: [{ label: 'Times Rented', data: top5Values,
            backgroundColor: ['#4CAF50','#FF8F00','#01579B','#C62828','#7B1FA2'] }]
    },
    options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y',
        plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } }
    }
});

new Chart(document.getElementById('rentalTrendChart'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{ label: 'Rentals', data: trendValues,
            borderColor: '#2E7D32', backgroundColor: 'rgba(46,125,50,0.1)',
            fill: true, tension: 0.4 }]
    },
    options: { responsive: true, maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Active', 'Returned', 'Cancelled'],
        datasets: [{ data: statusData,
            backgroundColor: ['#FF8F00','#4CAF50','#90A4AE'] }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php include '../includes/footer.php'; ?>