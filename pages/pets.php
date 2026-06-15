<?php
define('APP_RUN', true);
require_once '../includes/auth.php';
require_once '../includes/config.php';
requireLogin();

$page_title = 'Pet Management';

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$upload_dir = __DIR__ . '/../assets/uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pet'])) {
    $pet_id       = (int)$_POST['pet_id'];
    $pet_name     = trim($_POST['pet_name']);
    $species      = trim($_POST['species']);
    $breed        = trim($_POST['breed']);
    $age          = (int)$_POST['age'];
    $gender       = $_POST['gender'];
    $rental_price = (float)$_POST['rental_price'];
    $status       = $_POST['status'];
    $photo_path   = trim($_POST['current_photo']);

    // ✅ ADDED AVIF TO ALLOWED FILE TYPES
    if (!empty($_FILES['photo']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'avif'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        
        if ($_FILES['photo']['error'] !== 0) {
            echo "<script>alert('❌ Error uploading file — please try again');</script>";
        } elseif (!in_array($ext, $allowed)) {
            echo "<script>alert('❌ Invalid file type! Only JPG, JPEG, PNG, GIF, AVIF allowed');</script>";
        } else {
            // Delete old photo if exists
            if (!empty($photo_path) && file_exists(__DIR__ . '/../' . $photo_path)) {
                unlink(__DIR__ . '/../' . $photo_path);
            }
            $new_filename = 'pet_' . time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                $photo_path = 'assets/uploads/' . $new_filename;
            } else {
                echo "<script>alert('❌ Failed to save uploaded file');</script>";
            }
        }
    }

    $sql = "UPDATE pets 
            SET pet_name = :name, 
                species = :species, 
                breed = :breed, 
                age = :age, 
                gender = :gender, 
                rental_price = :price, 
                status = :status, 
                photo = :photo 
            WHERE pet_id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':name', $pet_name, PDO::PARAM_STR);
    $stmt->bindValue(':species', $species, PDO::PARAM_STR);
    $stmt->bindValue(':breed', $breed, PDO::PARAM_STR);
    $stmt->bindValue(':age', $age, PDO::PARAM_INT);
    $stmt->bindValue(':gender', $gender, PDO::PARAM_STR);
    $stmt->bindValue(':price', $rental_price, PDO::PARAM_STR);
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->bindValue(':photo', $photo_path, PDO::PARAM_STR);
    $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        header("Location: pets.php?success=updated");
        exit;
    } else {
        echo "<script>alert('❌ Update failed');</script>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pet'])) {
    $pet_name     = trim($_POST['pet_name']);
    $species      = trim($_POST['species']);
    $breed        = trim($_POST['breed']);
    $age          = (int)$_POST['age'];
    $gender       = $_POST['gender'];
    $rental_price = (float)$_POST['rental_price'];
    $status       = $_POST['status'];
    $photo_path   = '';

    // ✅ ADDED AVIF HERE TOO
    if (!empty($_FILES['photo']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'avif'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        
        if ($_FILES['photo']['error'] !== 0) {
            echo "<script>alert('❌ Error uploading file');</script>";
        } elseif (!in_array($ext, $allowed)) {
            echo "<script>alert('❌ Invalid file type! Only JPG, JPEG, PNG, GIF, AVIF allowed');</script>";
        } else {
            $new_filename = 'pet_' . time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                $photo_path = 'assets/uploads/' . $new_filename;
            } else {
                echo "<script>alert('❌ Failed to save file');</script>";
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO pets (pet_name, species, breed, age, gender, rental_price, status, photo) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$pet_name, $species, $breed, $age, $gender, $rental_price, $status, $photo_path]);

    header("Location: pets.php?success=added");
    exit;
}

if (isset($_GET['delete'])) {
    $pet_id = (int)$_GET['delete'];

    // ✅ Check if rented — cannot delete
    $check = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE pet_id = ? AND status IN ('Ongoing','Pending')");
    $check->execute([$pet_id]);
    $has_rent = $check->fetchColumn();

    if ($has_rent > 0) {
        echo "<script>alert('❌ Cannot delete: This pet is currently rented or has an ongoing reservation!');</script>";
    } else {
        $stmt = $pdo->prepare("SELECT photo FROM pets WHERE pet_id = ?");
        $stmt->execute([$pet_id]);
        $pic = $stmt->fetchColumn();
        if (!empty($pic) && file_exists(__DIR__ . '/../' . $pic)) {
            unlink(__DIR__ . '/../' . $pic);
        }
        $pdo->prepare("DELETE FROM pets WHERE pet_id = ?")->execute([$pet_id]);
        header("Location: pets.php?success=deleted");
        exit;
    }
}

$pets = $pdo->query("
    SELECT * FROM pets 
    ORDER BY FIELD(status, 'Available','Rented','Archived'), pet_name ASC
")->fetchAll();

include '../includes/header.php';
?>

<div class="page-header">
    <h2>🐾 Pet Management</h2>
    <button class="btn btn-primary" onclick="openAddModal()">+ Add New Pet</button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
    <?= $_GET['success'] === 'added' ? '✅ Pet added successfully!' : ($_GET['success'] === 'updated' ? '✅ Pet updated successfully!' : '✅ Pet deleted!') ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><div class="card-title">All Pets</div></div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Species</th>
                        <th>Breed</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Price/Day</th>
                        <th>Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pets as $p): ?>
                    <tr>
                        <td>
                            <?php 
                            if (!empty($p['photo'])): 
                                $image_url = '../' . $p['photo'];
                            ?>
                                <img 
                                    src="<?= clean($image_url) ?>" 
                                    width="60" 
                                    height="60" 
                                    style="object-fit:cover; border-radius:4px; border:1px solid #ddd;" 
                                    alt="Pet photo"
                                    onerror="this.onerror=null; this.src='https://via.placeholder.com/60?text=No+Img';">
                            <?php else: ?>
                                <div style="width:60px;height:60px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;border-radius:4px;">📷</div>
                            <?php endif; ?>
                        </td>
                        <td><?= clean($p['pet_name']) ?></td>
                        <td><?= clean($p['species']) ?></td>
                        <td><?= clean($p['breed']) ?></td>
                        <td><?= (int)$p['age'] ?> yrs</td>
                        <td><?= $p['gender'] ?></td>
                        <td><?= formatMoney($p['rental_price']) ?></td>
                        <td>
                            <span class="badge <?= $p['status']==='Available'?'badge-success':($p['status']==='Rented'?'badge-warning':'badge-secondary') ?>">
                                <?= $p['status'] ?>
                            </span>
                        </td>
                        <td class="no-print">
                            <button class="btn btn-sm btn-info" onclick="openEditModal(
                                <?= (int)$p['pet_id'] ?>,
                                '<?= clean($p['pet_name']) ?>',
                                '<?= clean($p['species']) ?>',
                                '<?= clean($p['breed']) ?>',
                                <?= (int)$p['age'] ?>,
                                '<?= $p['gender'] ?>',
                                <?= (float)$p['rental_price'] ?>,
                                '<?= $p['status'] ?>',
                                '<?= clean($p['photo']) ?>'
                            )" >✏️ Edit</button>
                            <?php if($_SESSION['role'] == 'admin'): ?><a href="pets.php?delete=<?= $p['pet_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this pet?')">🗑️ Delete</a><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <h3 class="modal-title">Add New Pet</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label>Pet Name *</label>
                    <input type="text" name="pet_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Species *</label>
                    <input type="text" name="species" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Breed</label>
                    <input type="text" name="breed" class="form-control">
                </div>
                <div class="form-group">
                    <label>Age (years)</label>
                    <input type="number" name="age" class="form-control" min="0" max="30">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Gender *</label>
                    <select name="gender" class="form-control" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rental Price ₱ *</label>
                    <input type="number" step="0.01" name="rental_price" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" class="form-control" required>
                        <option value="Available">Available</option>
                        <option value="Rented">Rented</option>
                        <option value="Archived">Archived</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Photo</label>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                </div>
            </div>
            <button type="submit" name="add_pet" class="btn btn-primary w-100 mt-2">Save Pet</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Pet</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="pet_id" id="edit_id">
            <input type="hidden" name="current_photo" id="edit_current_photo">

            <div class="form-row">
                <div class="form-group">
                    <label>Pet Name *</label>
                    <input type="text" name="pet_name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Species *</label>
                    <input type="text" name="species" id="edit_species" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Breed</label>
                    <input type="text" name="breed" id="edit_breed" class="form-control">
                </div>
                <div class="form-group">
                    <label>Age (years)</label>
                    <input type="number" name="age" id="edit_age" class="form-control" min="0" max="30">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Gender *</label>
                    <select name="gender" id="edit_gender" class="form-control" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rental Price ₱ *</label>
                    <input type="number" step="0.01" name="rental_price" id="edit_price" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" id="edit_status" class="form-control" required>
                        <option value="Available">Available</option>
                        <option value="Rented">Rented</option>
                        <option value="Archived">Archived</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Change Photo</label>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                    <small class="text-muted">Leave empty to keep current</small>
                    <div id="edit_photo_preview" style="margin-top:5px;"></div>
                </div>
            </div>

            <button type="submit" name="update_pet" class="btn btn-primary w-100 mt-2">Update Pet</button>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}
function openEditModal(id, name, species, breed, age, gender, price, status, photo) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_species').value = species;
    document.getElementById('edit_breed').value = breed;
    document.getElementById('edit_age').value = age;
    document.getElementById('edit_gender').value = gender;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_current_photo').value = photo;

    let preview = document.getElementById('edit_photo_preview');
    if (photo) {
        preview.innerHTML = '<img src="../' + photo + '" width="70" style="border-radius:4px;" alt="Current photo">';
    } else {
        preview.innerHTML = '<span class="text-muted">No photo uploaded</span>';
    }

    document.getElementById('editModal').classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}
</script>

<?php include '../includes/footer.php'; ?>