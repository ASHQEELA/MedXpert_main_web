<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $stmt = $conn->prepare("DELETE FROM blood_donors WHERE id = ?");
        $id = (int)$_POST['delete_id'];
        $stmt->bind_param("i", $id);
        $stmt->execute();
        audit_log($conn, 'donor.delete', 'donor', $id);
        flash_set('success', 'Donor deleted.');
        redirect('/admin/donors.php');
    }

    $action = pick($_POST['action'] ?? '', ['add', 'edit']);
    if ($action !== null) {
        $name = clean($_POST['name'] ?? '', 100);
        $blood_type = pick($_POST['blood_type'] ?? '', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);
        $age = (int)($_POST['age'] ?? 0);
        $phone = clean($_POST['phone'] ?? '', 20);
        $email = strtolower(clean($_POST['email'] ?? '', 100));
        $location = clean($_POST['location'] ?? '', 150);
        $available = isset($_POST['available']) ? 1 : 0;
        $medical_conditions = clean($_POST['medical_conditions'] ?? '', 2000);

        $problem = null;
        if ($name === '' || $location === '') {
            $problem = 'Fill in every required field.';
        } elseif ($blood_type === null) {
            $problem = 'Choose a valid blood type.';
        } elseif ($age < 18 || $age > 65) {
            $problem = 'Donor age must be between 18 and 65.';
        } elseif (!valid_phone($phone)) {
            $problem = 'Enter a valid phone number.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $problem = 'Enter a valid email address.';
        }

        $uploadError = null;
        $newImage = $problem === null ? save_image_upload('image', 'donors', 'donor', $uploadError, 800) : null;
        if ($problem === null && $uploadError !== null) {
            $problem = $uploadError;
        }

        if ($problem !== null) {
            flash_set('error', $problem);
            redirect('/admin/donors.php');
        }

        if ($action === 'add') {
            $image = $newImage ?? 'default-donor.png';
            $stmt = $conn->prepare("INSERT INTO blood_donors (name, blood_type, age, phone, email, location, available, medical_conditions, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssisssiss", $name, $blood_type, $age, $phone, $email, $location, $available, $medical_conditions, $image);
            $stmt->execute();
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $image = $newImage;
            if ($image === null) {
                $current = db_query("SELECT image FROM blood_donors WHERE id = ?", 'i', [$id])->fetch_assoc();
                $image = $current['image'] ?? 'default-donor.png';
            }
            $stmt = $conn->prepare("UPDATE blood_donors SET name=?, blood_type=?, age=?, phone=?, email=?, location=?, available=?, medical_conditions=?, image=? WHERE id=?");
            $stmt->bind_param("ssisssissi", $name, $blood_type, $age, $phone, $email, $location, $available, $medical_conditions, $image, $id);
            $stmt->execute();
        }

        audit_log($conn, $action === 'add' ? 'donor.add' : 'donor.edit', 'donor', $action === 'add' ? (int)$conn->insert_id : $id, $name);
        flash_set('success', $action === 'add' ? 'Donor added.' : 'Donor updated.');
        redirect('/admin/donors.php');
    }
}

$donors = $conn->query("SELECT * FROM blood_donors ORDER BY created_at DESC");

require_once __DIR__ . '/../config/header.php';
?>

<h1>Manage Donors</h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> Home</span>
    <span>/</span>
    <span>Admin</span>
    <span>/</span>
    <span>Manage Donors</span>
</div>
</div>
<div class="top-actions">
    <button class="btn btn-primary" onclick="openModal('donorModal'); setDonorAction('add')">
        <i class="fas fa-plus"></i> Add New Donor
    </button>
</div>
</div>

<div class="content-wrapper">
    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Donor</th>
                        <th>Blood Type</th>
                        <th>Age</th>
                        <th>Location</th>
                        <th>Available</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($donor = $donors->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; background: #f0f0f0;">
                                        <?php if ($donor['image'] && $donor['image'] !== 'default-donor.png'): ?>
                                            <img src="/uploads/donors/<?php echo e(safe_image_name($donor['image'])); ?>" alt="Photo of <?php echo htmlspecialchars($donor['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;"><i class="fas fa-user"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($donor['name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($donor['email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-danger"><?php echo htmlspecialchars($donor['blood_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($donor['age']); ?></td>
                            <td><?php echo htmlspecialchars($donor['location']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $donor['available'] ? 'success' : 'danger'; ?>">
                                    <?php echo $donor['available'] ? 'Available' : 'Unavailable'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-secondary btn-sm" onclick="editDonor(<?php echo json_attr($donor); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this donor?')">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo (int)$donor['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete donor"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Donor Modal -->
<div class="modal" id="donorModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Donor</h3>
            <button class="modal-close" onclick="closeModal('donorModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="modal-body" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" id="donorAction" value="add">
            <input type="hidden" name="id" id="donorId">
            
            <div class="form-group">
                <label class="form-label">Donor Image</label>
                <div style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; background: #f0f0f0; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center;">
                    <img id="donorImagePreview" src="" alt="Selected donor photo preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                    <i id="donorImagePlaceholder" class="fas fa-user" style="font-size: 2rem; color: #ccc;"></i>
                </div>
                <input type="file" name="image" id="donorImage" class="form-input" accept="image/*" onchange="previewDonorImage(event)">
            </div>
            
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" id="donorName" class="form-input" required>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Blood Type</label>
                    <select name="blood_type" id="donorBloodType" class="form-input" required>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Age</label>
                    <input type="number" name="age" id="donorAge" class="form-input" required>
                </div>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="donorPhone" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="donorEmail" class="form-input" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Location</label>
                <input type="text" name="location" id="donorLocation" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Medical Conditions (Optional)</label>
                <textarea name="medical_conditions" id="donorMedical" class="form-input" rows="2"></textarea>
            </div>
            
            <div class="form-group d-flex align-center gap-1">
                <input type="checkbox" name="available" id="donorAvailable" value="1" checked>
                <label class="form-label" style="margin-bottom: 0;">Currently Available for Donation</label>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Donor</button>
        </form>
    </div>
</div>

<script>
function previewDonorImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('donorImagePreview');
    const placeholder = document.getElementById('donorImagePlaceholder');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
        placeholder.style.display = 'block';
    }
}

function setDonorAction(action) {
    document.getElementById('donorAction').value = action;
    document.getElementById('modalTitle').textContent = action === 'add' ? 'Add New Donor' : 'Edit Donor';
    if (action === 'add') {
        document.getElementById('donorId').value = '';
        document.querySelectorAll('#donorModal .form-input').forEach(input => input.value = '');
        document.getElementById('donorAvailable').checked = true;
        document.getElementById('donorImagePreview').style.display = 'none';
        document.getElementById('donorImagePlaceholder').style.display = 'block';
    }
}

function editDonor(donor) {
    setDonorAction('edit');
    document.getElementById('donorId').value = donor.id;
    document.getElementById('donorName').value = donor.name;
    document.getElementById('donorBloodType').value = donor.blood_type;
    document.getElementById('donorAge').value = donor.age;
    document.getElementById('donorPhone').value = donor.phone;
    document.getElementById('donorEmail').value = donor.email;
    document.getElementById('donorLocation').value = donor.location;
    document.getElementById('donorMedical').value = donor.medical_conditions;
    document.getElementById('donorAvailable').checked = donor.available == 1;
    
    // Show donor image if exists
    if (donor.image && donor.image !== 'default-donor.png') {
        document.getElementById('donorImagePreview').src = '../uploads/donors/' + donor.image;
        document.getElementById('donorImagePreview').style.display = 'block';
        document.getElementById('donorImagePlaceholder').style.display = 'none';
    } else {
        document.getElementById('donorImagePreview').style.display = 'none';
        document.getElementById('donorImagePlaceholder').style.display = 'block';
    }
    
    openModal('donorModal');
}
</script>

<?php require_once '../config/footer.php'; ?>
