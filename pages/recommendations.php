<?php
define('APP_RUN', true);
require_once '../includes/auth.php';
require_once '../includes/config.php';
requireAdmin();

$page_title = 'Recommendations';

if (!function_exists('clean')) {
    function clean($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('formatMoney')) {
    function formatMoney($amt) {
        return '₱' . number_format((float)$amt, 2);
    }
}

$recommendations = [];

$low_demand = $pdo->query("
    SELECT p.pet_name, p.species, COUNT(r.rental_id) AS times_rented
    FROM pets p
    LEFT JOIN rentals r ON p.pet_id = r.pet_id
        AND MONTH(r.rental_date) = MONTH(CURDATE())
        AND YEAR(r.rental_date) = YEAR(CURDATE())
        AND r.status != 'Cancelled'
    WHERE p.status != 'Archived'
    GROUP BY p.pet_id
    HAVING times_rented < 2
    ORDER BY times_rented ASC
    LIMIT 5
")->fetchAll();

foreach ($low_demand as $pet) {
    $recommendations[] = [
        'type'  => 'warning',
        'icon'  => '📉',
        'title' => "Low Demand: {$pet['pet_name']}",
        'text'  => "{$pet['pet_name']} ({$pet['species']}) was only rented {$pet['times_rented']} time(s) this month. Consider promoting this pet to increase rentals."
    ];
}
$high_demand = $pdo->query("
    SELECT p.pet_name, p.species, COUNT(r.rental_id) AS times_rented
    FROM pets p
    JOIN rentals r ON p.pet_id = r.pet_id
        AND MONTH(r.rental_date) = MONTH(CURDATE())
        AND YEAR(r.rental_date) = YEAR(CURDATE())
        AND r.status != 'Cancelled'
    WHERE p.status != 'Archived'
    GROUP BY p.pet_id
    HAVING times_rented > 5
    ORDER BY times_rented DESC
")->fetchAll();

foreach ($high_demand as $pet) {
    $recommendations[] = [
        'type'  => 'success',
        'icon'  => '🔥',
        'title' => "High Demand: {$pet['pet_name']}",
        'text'  => "{$pet['pet_name']} has been rented {$pet['times_rented']} times this month! Consider acquiring more pets of this type to meet demand."
    ];
}

$current_rev = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM payments
    WHERE MONTH(payment_date) = MONTH(CURDATE())
    AND YEAR(payment_date) = YEAR(CURDATE())
")->fetchColumn();

$prev_rev = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM payments
    WHERE MONTH(payment_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(payment_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
")->fetchColumn();

if ($prev_rev > 0 && $current_rev < $prev_rev) {
    $drop_pct = round((($prev_rev - $current_rev) / $prev_rev) * 100, 1);
    $recommendations[] = [
        'type'  => 'critical',
        'icon'  => '⚠️',
        'title' => 'Declining Revenue',
        'text'  => "This month's revenue (" . formatMoney($current_rev) . ") is down {$drop_pct}% from last month (" . formatMoney($prev_rev) . "). Consider running promotional discounts to boost rentals."
    ];
}

$risky_customers = $pdo->query("
    SELECT c.customer_name, COUNT(pen.penalty_id) AS late_count
    FROM customers c
    JOIN rentals r ON c.customer_id = r.customer_id
    JOIN penalties pen ON r.rental_id = pen.rental_id
    GROUP BY c.customer_id
    HAVING late_count >= 3
")->fetchAll();

foreach ($risky_customers as $cust) {
    $recommendations[] = [
        'type'  => 'critical',
        'icon'  => '🚨',
        'title' => "High-Risk Customer: {$cust['customer_name']}",
        'text'  => "{$cust['customer_name']} has returned pets late {$cust['late_count']} times. Require advance payment for future rentals."
    ];
}

$total_pets_count = $pdo->query("SELECT COUNT(*) FROM pets WHERE status != 'Archived'")->fetchColumn();
$avail_pets_count = $pdo->query("SELECT COUNT(*) FROM pets WHERE status = 'Available'")->fetchColumn();

if ($total_pets_count > 0) {
    $availability_pct = ($avail_pets_count / $total_pets_count) * 100;
    if ($availability_pct < 20) {
        $recommendations[] = [
            'type'  => 'critical',
            'icon'  => '📦',
            'title' => 'Low Pet Availability',
            'text'  => "Only {$avail_pets_count} out of {$total_pets_count} pets are available (" . round($availability_pct, 1) . "%). Inventory is running low. Prepare additional pets or process pending returns."
        ];
    }
}

$most_profitable = $pdo->query("
    SELECT p.pet_name, p.species, COALESCE(SUM(pay.amount), 0) AS revenue
    FROM pets p
    JOIN rentals r ON p.pet_id = r.pet_id AND r.status = 'Returned'
    JOIN payments pay ON r.rental_id = pay.rental_id
    GROUP BY p.pet_id
    ORDER BY revenue DESC
    LIMIT 1
")->fetch();

if ($most_profitable && $most_profitable['revenue'] > 0) {
    $recommendations[] = [
        'type'  => 'success',
        'icon'  => '💰',
        'title' => "Most Profitable: {$most_profitable['pet_name']}",
        'text'  => "{$most_profitable['pet_name']} ({$most_profitable['species']}) has generated " . formatMoney($most_profitable['revenue']) . " in total revenue. Focus marketing efforts on this pet category."
    ];
}

$inactive_customers = $pdo->query("
    SELECT c.customer_name,
           MAX(r.rental_date) AS last_rental
    FROM customers c
    LEFT JOIN rentals r ON c.customer_id = r.customer_id
    GROUP BY c.customer_id
    HAVING last_rental < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
       OR last_rental IS NULL
    LIMIT 5
")->fetchAll();

if (!empty($inactive_customers)) {
    $names = implode(', ', array_map(fn($c) => $c['customer_name'], array_slice($inactive_customers, 0, 3)));
    $recommendations[] = [
        'type'  => 'info',
        'icon'  => '💤',
        'title' => 'Inactive Customers',
        'text'  => count($inactive_customers) . " customer(s) have not rented in over 6 months (e.g., {$names}). Consider sending promotional offers to re-engage them."
    ];
}

$peak_month = $pdo->query("
    SELECT MONTHNAME(rental_date) AS month_name, COUNT(*) AS count
    FROM rentals
    WHERE status != 'Cancelled'
    GROUP BY MONTH(rental_date)
    ORDER BY count DESC
    LIMIT 1
")->fetch();

if ($peak_month) {
    $recommendations[] = [
        'type'  => 'info',
        'icon'  => '📅',
        'title' => "Peak Month: {$peak_month['month_name']}",
        'text'  => "{$peak_month['month_name']} is historically your busiest month with {$peak_month['count']} rentals. Prepare additional staff and pets during this period."
    ];
}

$total_completed = $pdo->query("SELECT COUNT(*) FROM rentals WHERE status = 'Returned'")->fetchColumn();
$late_returns    = $pdo->query("SELECT COUNT(DISTINCT rental_id) FROM penalties")->fetchColumn();

if ($total_completed > 0) {
    $late_rate = ($late_returns / $total_completed) * 100;
    if ($late_rate > 5) {
        $recommendations[] = [
            'type'  => 'warning',
            'icon'  => '⏰',
            'title' => 'High Late Return Rate',
            'text'  => round($late_rate, 1) . "% of completed rentals had late returns. This is above the 5% threshold. Review rental policies and consider adding reminders before due dates."
        ];
    }
}

$species_counts = $pdo->query("
    SELECT p.species, COUNT(r.rental_id) AS count
    FROM rentals r
    JOIN pets p ON r.pet_id = p.pet_id
    WHERE r.status != 'Cancelled'
    GROUP BY p.species
")->fetchAll();

$grand_total = array_sum(array_column($species_counts, 'count'));
foreach ($species_counts as $s) {
    if ($grand_total > 0 && ($s['count'] / $grand_total) * 100 < 10) {
        $pct = round(($s['count'] / $grand_total) * 100, 1);
        $recommendations[] = [
            'type'  => 'warning',
            'icon'  => '📊',
            'title' => "Underperforming: {$s['species']}",
            'text'  => "{$s['species']} pets contribute only {$pct}% of total rentals. Reassess your investment in this category."
        ];
    }
}

if (empty($recommendations)) {
    $recommendations[] = [
        'type'  => 'success',
        'icon'  => '🎉',
        'title' => 'Everything Looks Good!',
        'text'  => 'No issues detected at this time. Keep up the great work!'
    ];
}

include '../includes/header.php';
?>

<div class="page-header">
    <h2>💡 Business Recommendations</h2>
    <span class="badge badge-info" style="font-size:14px; padding:6px 12px;">
        <?= count($recommendations) ?> Recommendation(s)
    </span>
</div>
<div class="rec-grid">
    <?php foreach ($recommendations as $rec): ?>
    <div class="rec-card <?= $rec['type'] ?>">
        <div class="rec-icon"><?= clean($rec['icon']) ?></div>
        <div class="rec-body">
            <div class="rec-title"><?= clean($rec['title']) ?></div>
            <div class="rec-text"><?= clean($rec['text']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php include '../includes/footer.php'; ?>