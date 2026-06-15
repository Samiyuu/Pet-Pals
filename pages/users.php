<?php
define('APP_RUN', true);
require_once '../includes/auth.php';
require_once '../includes/config.php';
requireAdmin();

$page_title = 'User Management';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username   = trim($_POST['username']);
    $password   = $_POST['password'];
    $full_name  = trim($_POST['full_name']);
    $role       = $_POST['role'];

    if (!empty($username) && !empty($password) && !empty($full_name)) {
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $hashed_pass, $full_name, $role]);
        header("Location: users.php?success=added");
        exit;
    } else {
        $error = "Please fill in all required fields.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id    = $_POST['user_id'];
    $username   = trim($_POST['username']);
    $full_name  = trim($_POST['full_name']);
    $role       = $_POST['role'];
    $new_pass   = $_POST['new_password'];

    if (!empty($username) && !empty($full_name)) {
        if (!empty($new_pass)) {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, full_name=?, role=? WHERE user_id=?");
            $stmt->execute([$username, $hashed_pass, $full_name, $role, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, role=? WHERE user_id=?");
            $stmt->execute([$username, $full_name, $role, $user_id]);
        }
        header("Location: users.php?success=updated");
        exit;
    } else {
        $error = "Username and Full Name are required.";
    }
}

if (isset($_GET['delete'])) {
    $user_id = $_GET['delete'];
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        header("Location: users.php?success=deleted");
        exit;
    } else {
        $error = "You cannot delete your own account.";
    }
}

if (isset($_GET['toggle'])) {
    $user_id = $_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE users SET status = IF(status='active', 'disabled', 'active') WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: users.php?success=toggled");
    exit;
}

$users = $pdo->query("
    SELECT user_id, username, full_name, role, status, created_at
    FROM users
    ORDER BY role DESC, full_name ASC
")->fetchAll();

include '../includes/header.php';
?>

<div class="page-header">
    <h2>👤 User Management</h2>
    <button class="btn btn-primary" onclick="openAddModal()">+ Add New User</button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
    <?php
    switch ($_GET['success']) {
        case 'added': echo "✅ User added successfully!"; break;
        case 'updated': echo "✅ User updated successfully!"; break;
        case 'deleted': echo "✅ User deleted successfully!"; break;
        case 'toggled': echo "✅ User status updated!"; break;
    }
    ?>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger">❌ <?= $error ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title">System Users</div>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>#<?= $u['user_id'] ?></td>
                        <td><?= clean($u['full_name']) ?></td>
                        <td><?= clean($u['username']) ?></td>
                        <td>
                            <span class="badge <?= $u['role'] === 'admin' ? 'badge-primary' : 'badge-secondary' ?>">
                                <?= ucfirst($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                <?= ucfirst($u['status']) ?>
                            </span>
                        </td>
                        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td class="no-print">
                            <button class="btn btn-sm btn-info" onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)">✏️ Edit</button>
                            <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                            <a href="users.php?toggle=<?= $u['user_id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Change status for this user?')">
                                <?= $u['status'] === 'active' ? '🔒 Disable' : '🔓 Enable' ?>
                            </a>
                            <a href="users.php?delete=<?= $u['user_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user? This cannot be undone!')">🗑️ Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Add New User</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form method="POST" action="users.php">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Role *</label>
                <select name="role" class="form-control" required>
                    <option value="staff">Staff</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <button type="submit" name="add_user" class="btn btn-primary w-100 mt-2">Save User</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Edit User</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST" action="users.php">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" id="edit_username" class="form-control" required>
            </div>
            <div class="form-group">
                <label>New Password <small class="text-muted">(leave blank to keep current)</small></label>
                <input type="password" name="new_password" class="form-control">
            </div>
            <div class="form-group">
                <label>Role *</label>
                <select name="role" id="edit_role" class="form-control" required>
                    <option value="staff">Staff</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <button type="submit" name="edit_user" class="btn btn-primary w-100 mt-2">Update User</button>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}
function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.user_id;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('editModal').classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}
</script>

<?php include '../includes/footer.php'; ?>