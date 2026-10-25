<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
        $id = (int)$_POST['delete_id'];
        $stmt->bind_param("i", $id);
        $stmt->execute();
        audit_log($conn, 'medicine.delete', 'medicine', $id);
        flash_set('success', 'Medicine deleted.');
        redirect('/admin/medicines.php');
    }

    $action = pick($_POST['action'] ?? '', ['add', 'edit']);
    if ($action !== null) {
        $name = clean($_POST['name'] ?? '', 150);
        $category = clean($_POST['category'] ?? '', 100);
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $description = clean($_POST['description'] ?? '', 2000);
        $manufacturer = clean($_POST['manufacturer'] ?? '', 150);
        $dosage = clean($_POST['dosage'] ?? '', 100);
        $prescription_required = isset($_POST['prescription_required']) ? 1 : 0;
        $status = pick($_POST['status'] ?? '', ['available', 'out_of_stock'], 'available');

        $problem = null;
        if ($name === '' || $category === '') {
            $problem = 'Fill in every required field.';
        } elseif ($price < 0 || $price > 1000000) {
            $problem = 'Enter a price between 0 and 1,000,000.';
        } elseif ($stock < 0 || $stock > 100000) {
            $problem = 'Enter a stock between 0 and 100,000.';
        }

        $uploadError = null;
        $newImage = $problem === null ? save_image_upload('image', 'medicines', 'medicine', $uploadError, 800) : null;
        if ($problem === null && $uploadError !== null) {
            $problem = $uploadError;
        }

        if ($problem !== null) {
            flash_set('error', $problem);
            redirect('/admin/medicines.php');
        }

        if ($action === 'add') {
            $image = $newImage ?? 'default-medicine.png';
            $stmt = $conn->prepare("INSERT INTO medicines (name, category, price, stock, description, manufacturer, dosage, prescription_required, status, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdssssiss", $name, $category, $price, $stock, $description, $manufacturer, $dosage, $prescription_required, $status, $image);
            $stmt->execute();
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $image = $newImage;
            if ($image === null) {
                $current = db_query("SELECT image FROM medicines WHERE id = ?", 'i', [$id])->fetch_assoc();
                $image = $current['image'] ?? 'default-medicine.png';
            }
            $stmt = $conn->prepare("UPDATE medicines SET name=?, category=?, price=?, stock=?, description=?, manufacturer=?, dosage=?, prescription_required=?, status=?, image=? WHERE id=?");
            $stmt->bind_param("ssdssssissi", $name, $category, $price, $stock, $description, $manufacturer, $dosage, $prescription_required, $status, $image, $id);
            $stmt->execute();
        }

        audit_log($conn, $action === 'add' ? 'medicine.add' : 'medicine.edit', 'medicine', $action === 'add' ? (int)$conn->insert_id : $id, $name);
        flash_set('success', $action === 'add' ? 'Medicine added.' : 'Medicine updated.');
        redirect('/admin/medicines.php');
    }
}

$medicines = $conn->query("SELECT * FROM medicines ORDER BY created_at DESC");

require_once __DIR__ . '/../config/header.php';
?>

<h1>Manage Medicines</h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> Home</span>
    <span>/</span>
    <span>Admin</span>
    <span>/</span>
    <span>Manage Medicines</span>
</div>
</div>
<div class="top-actions">
    <button class="btn btn-primary" onclick="openModal('medicineModal'); setMedicineAction('add')">
        <i class="fas fa-plus"></i> Add New Medicine
    </button>
</div>
</div>

<div class="content-wrapper">
    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($med = $medicines->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 50px; height: 50px; border-radius: 4px; overflow: hidden; background: #f0f0f0;">
                                        <?php if ($med['image'] && $med['image'] !== 'default-medicine.png'): ?>
                                            <img src="/uploads/medicines/<?php echo e(safe_image_name($med['image'])); ?>" alt="<?php echo htmlspecialchars($med['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;"><i class="fas fa-pills"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($med['name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($med['manufacturer']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($med['category']); ?></td>
                            <td>৳<?php echo number_format($med['price'], 2); ?></td>
                            <td><?php echo $med['stock']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $med['status'] === 'available' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($med['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-secondary btn-sm" onclick="editMedicine(<?php echo json_attr($med); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this medicine?')">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo (int)$med['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete medicine"><i class="fas fa-trash"></i></button>
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

<!-- Medicine Modal -->
<div class="modal" id="medicineModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Medicine</h3>
            <button class="modal-close" onclick="closeModal('medicineModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="modal-body" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" id="medicineAction" value="add">
            <input type="hidden" name="id" id="medicineId">
            
            <div class="form-group">
                <label class="form-label">Medicine Image</label>
                <div style="width: 100px; height: 100px; border-radius: 4px; overflow: hidden; background: #f0f0f0; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center;">
                    <img id="medicineImagePreview" src="" alt="Selected medicine image preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                    <i id="medicineImagePlaceholder" class="fas fa-pills" style="font-size: 2rem; color: #ccc;"></i>
                </div>
                <input type="file" name="image" id="medicineImage" class="form-input" accept="image/*" onchange="previewMedicineImage(event)">
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Medicine Name</label>
                    <input type="text" name="name" id="medicineName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" id="medicineCategory" class="form-input" required>
                </div>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Price (৳)</label>
                    <input type="number" step="0.01" name="price" id="medicinePrice" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Stock Quantity</label>
                    <input type="number" name="stock" id="medicineStock" class="form-input" required>
                </div>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Manufacturer</label>
                    <input type="text" name="manufacturer" id="medicineManufacturer" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Dosage (e.g., 500mg)</label>
                    <input type="text" name="dosage" id="medicineDosage" class="form-input" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="medicineDescription" class="form-input" rows="3"></textarea>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="medicineStatus" class="form-input">
                        <option value="available">Available</option>
                        <option value="out_of_stock">Out of Stock</option>
                    </select>
                </div>
                <div class="form-group d-flex align-center gap-1" style="margin-top: 1.5rem;">
                    <input type="checkbox" name="prescription_required" id="medicinePrescription" value="1">
                    <label class="form-label" style="margin-bottom: 0;">Prescription Required</label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Medicine</button>
        </form>
    </div>
</div>

<script>
function previewMedicineImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('medicineImagePreview');
    const placeholder = document.getElementById('medicineImagePlaceholder');
    
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

function setMedicineAction(action) {
    document.getElementById('medicineAction').value = action;
    document.getElementById('modalTitle').textContent = action === 'add' ? 'Add New Medicine' : 'Edit Medicine';
    if (action === 'add') {
        document.getElementById('medicineId').value = '';
        document.querySelectorAll('#medicineModal .form-input').forEach(input => input.value = '');
        document.getElementById('medicineStatus').value = 'available';
        document.getElementById('medicinePrescription').checked = false;
        document.getElementById('medicineImagePreview').style.display = 'none';
        document.getElementById('medicineImagePlaceholder').style.display = 'block';
    }
}

function editMedicine(med) {
    setMedicineAction('edit');
    document.getElementById('medicineId').value = med.id;
    document.getElementById('medicineName').value = med.name;
    document.getElementById('medicineCategory').value = med.category;
    document.getElementById('medicinePrice').value = med.price;
    document.getElementById('medicineStock').value = med.stock;
    document.getElementById('medicineManufacturer').value = med.manufacturer;
    document.getElementById('medicineDosage').value = med.dosage;
    document.getElementById('medicineDescription').value = med.description;
    document.getElementById('medicineStatus').value = med.status;
    document.getElementById('medicinePrescription').checked = med.prescription_required == 1;
    
    // Show medicine image if exists
    if (med.image && med.image !== 'default-medicine.png') {
        document.getElementById('medicineImagePreview').src = '../uploads/medicines/' + med.image;
        document.getElementById('medicineImagePreview').style.display = 'block';
        document.getElementById('medicineImagePlaceholder').style.display = 'none';
    } else {
        document.getElementById('medicineImagePreview').style.display = 'none';
        document.getElementById('medicineImagePlaceholder').style.display = 'block';
    }
    
    openModal('medicineModal');
}
</script>

<?php require_once '../config/footer.php'; ?>
