<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

// The verified flag needs migrations/001_add_doctor_verified.sql on older databases
$has_verified = $conn->query("SHOW COLUMNS FROM doctors LIKE 'verified'")->num_rows > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $stmt = $conn->prepare("DELETE FROM doctors WHERE id = ?");
        $id = (int)$_POST['delete_id'];
        $stmt->bind_param("i", $id);
        $stmt->execute();
        audit_log($conn, 'doctor.delete', 'doctor', $id);
        flash_set('success', 'Doctor deleted.');
        redirect('/admin/doctors.php');
    }

    $action = pick($_POST['action'] ?? '', ['add', 'edit']);
    if ($action !== null) {
        $name = clean($_POST['name'] ?? '', 100);
        $specialization = clean($_POST['specialization'] ?? '', 100);
        $experience = (int)($_POST['experience'] ?? 0);
        $qualification = clean($_POST['qualification'] ?? '', 255);
        $hospital = clean($_POST['hospital'] ?? '', 150);
        $location = clean($_POST['location'] ?? '', 150);
        $fee = (float)($_POST['fee'] ?? 0);
        $phone = clean($_POST['phone'] ?? '', 20);
        $email = strtolower(clean($_POST['email'] ?? '', 100));
        $sched_days = array_map('intval', (array)($_POST['schedule_days'] ?? []));
        $sched_start = clean($_POST['schedule_start'] ?? '', 5);
        $sched_end = clean($_POST['schedule_end'] ?? '', 5);
        $sched_slot = (int)($_POST['schedule_slot'] ?? 30);
        $bio = clean($_POST['bio'] ?? '', 2000);
        $status = pick($_POST['status'] ?? '', ['active', 'inactive'], 'active');

        $problem = null;
        if ($name === '' || $specialization === '' || $qualification === '' || $hospital === '' || $location === '') {
            $problem = 'Fill in every required field.';
        } elseif ($experience < 0 || $experience > 70) {
            $problem = 'Experience must be between 0 and 70 years.';
        } elseif ($fee < 0 || $fee > 1000000) {
            $problem = 'Enter a fee between 0 and 1,000,000.';
        } elseif (!valid_phone($phone)) {
            $problem = 'Enter a valid phone number.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $problem = 'Enter a valid email address.';
        }

        if ($problem === null) {
            try {
                $sched_days = check_schedule($sched_days, $sched_start, $sched_end, $sched_slot);
                [$available_days, $available_time] = schedule_summary($sched_days, $sched_start, $sched_end);
            } catch (RuntimeException $e) {
                $problem = $e->getMessage();
            }
        }

        $uploadError = null;
        $newImage = $problem === null ? save_image_upload('image', 'doctors', 'doctor', $uploadError, 800) : null;
        if ($problem === null && $uploadError !== null) {
            $problem = $uploadError;
        }

        if ($problem !== null) {
            flash_set('error', $problem);
            redirect('/admin/doctors.php');
        }

        if ($action === 'add') {
            $image = $newImage ?? 'default-doctor.png';
            $stmt = $conn->prepare("INSERT INTO doctors (name, specialization, experience, qualification, hospital, location, fee, phone, email, available_days, available_time, bio, status, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssisssdsssssss", $name, $specialization, $experience, $qualification, $hospital, $location, $fee, $phone, $email, $available_days, $available_time, $bio, $status, $image);
            $stmt->execute();
            $saved_id = (int)$conn->insert_id;
        } else {
            $saved_id = (int)($_POST['id'] ?? 0);
            $image = $newImage;
            if ($image === null) {
                $current = db_query("SELECT image FROM doctors WHERE id = ?", 'i', [$saved_id])->fetch_assoc();
                $image = $current['image'] ?? 'default-doctor.png';
            }
            $stmt = $conn->prepare("UPDATE doctors SET name=?, specialization=?, experience=?, qualification=?, hospital=?, location=?, fee=?, phone=?, email=?, available_days=?, available_time=?, bio=?, status=?, image=? WHERE id=?");
            $stmt->bind_param("ssisssdsssssssi", $name, $specialization, $experience, $qualification, $hospital, $location, $fee, $phone, $email, $available_days, $available_time, $bio, $status, $image, $saved_id);
            $stmt->execute();
        }

        save_doctor_schedule($conn, $saved_id, $sched_days, $sched_start, $sched_end, $sched_slot);

        if ($has_verified && $saved_id > 0) {
            $verified = isset($_POST['verified']) ? 1 : 0;
            $stmt = $conn->prepare("UPDATE doctors SET verified = ? WHERE id = ?");
            $stmt->bind_param("ii", $verified, $saved_id);
            $stmt->execute();
        }

        audit_log($conn, $action === 'add' ? 'doctor.add' : 'doctor.edit', 'doctor', $saved_id, $name);
        flash_set('success', $action === 'add' ? 'Doctor added.' : 'Doctor updated.');
        redirect('/admin/doctors.php');
    }
}

$doctors = $conn->query("SELECT d.*,
        (SELECT GROUP_CONCAT(weekday ORDER BY weekday) FROM doctor_schedules s WHERE s.doctor_id = d.id) AS schedule_days,
        (SELECT TIME_FORMAT(MIN(start_time), '%H:%i') FROM doctor_schedules s WHERE s.doctor_id = d.id) AS schedule_start,
        (SELECT TIME_FORMAT(MIN(end_time), '%H:%i') FROM doctor_schedules s WHERE s.doctor_id = d.id) AS schedule_end,
        (SELECT MIN(slot_minutes) FROM doctor_schedules s WHERE s.doctor_id = d.id) AS schedule_slot
    FROM doctors d ORDER BY d.created_at DESC");

require_once __DIR__ . '/../config/header.php';
?>

<h1>Manage Doctors</h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> Home</span>
    <span>/</span>
    <span>Admin</span>
    <span>/</span>
    <span>Manage Doctors</span>
</div>
</div>
<div class="top-actions">
    <button class="btn btn-primary" onclick="openModal('doctorModal'); setDoctorAction('add')">
        <i class="fas fa-plus"></i> Add New Doctor
    </button>
</div>
</div>

<div class="content-wrapper">
    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Hospital</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($doctor = $doctors->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; background: #f0f0f0;">
                                        <?php if ($doctor['image'] && $doctor['image'] !== 'default-doctor.png'): ?>
                                            <img src="/uploads/doctors/<?php echo e(safe_image_name($doctor['image'])); ?>" alt="Photo of <?php echo htmlspecialchars($doctor['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;"><i class="fas fa-user-md"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($doctor['name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($doctor['email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                            <td><?php echo htmlspecialchars($doctor['hospital']); ?></td>
                            <td>৳<?php echo number_format($doctor['fee'], 2); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $doctor['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($doctor['status']); ?>
                                </span>
                                <?php if (!empty($doctor['verified'])): ?>
                                    <span class="verified-badge"><i class="fas fa-circle-check"></i> Verified</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-secondary btn-sm" onclick="editDoctor(<?php echo json_attr($doctor); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this doctor?')">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo (int)$doctor['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete doctor"><i class="fas fa-trash"></i></button>
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

<!-- Doctor Modal -->
<div class="modal" id="doctorModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Doctor</h3>
            <button class="modal-close" onclick="closeModal('doctorModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="modal-body" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" id="doctorAction" value="add">
            <input type="hidden" name="id" id="doctorId">
            
            <div class="form-group">
                <label class="form-label">Doctor Image</label>
                <div style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; background: #f0f0f0; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center;">
                    <img id="doctorImagePreview" src="" alt="Selected doctor photo preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                    <i id="doctorImagePlaceholder" class="fas fa-user-md" style="font-size: 2rem; color: #ccc;"></i>
                </div>
                <input type="file" name="image" id="doctorImage" class="form-input" accept="image/*" onchange="previewDoctorImage(event)">
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" id="doctorName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Specialization</label>
                    <input type="text" name="specialization" id="doctorSpecialization" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Experience (Years)</label>
                    <input type="number" name="experience" id="doctorExperience" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Qualification</label>
                    <input type="text" name="qualification" id="doctorQualification" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Hospital</label>
                    <input type="text" name="hospital" id="doctorHospital" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" id="doctorLocation" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Consultation Fee</label>
                    <input type="number" step="0.01" name="fee" id="doctorFee" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="doctorPhone" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="doctorEmail" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="doctorStatus" class="form-input">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <?php if ($has_verified): ?>
                <div class="form-group">
                    <label class="form-label" for="doctorVerified">Verification</label>
                    <label class="check-row">
                        <input type="checkbox" name="verified" id="doctorVerified" value="1">
                        <span>Verified by MedXpert (license and identity checked)</span>
                    </label>
                </div>
                <?php endif; ?>
            </div>
            
            <fieldset class="schedule-box">
                <legend>Weekly schedule</legend>
                <div class="day-row">
                    <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $i => $dayName): ?>
                        <label class="day-chip"><input type="checkbox" name="schedule_days[]" value="<?php echo $i; ?>" class="schedule-day"><span><?php echo $dayName; ?></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="scheduleStart">Starts</label>
                        <input type="time" name="schedule_start" id="scheduleStart" class="form-input" value="09:00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="scheduleEnd">Ends</label>
                        <input type="time" name="schedule_end" id="scheduleEnd" class="form-input" value="17:00" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="scheduleSlot">Minutes per appointment</label>
                    <select name="schedule_slot" id="scheduleSlot" class="form-input">
                        <?php foreach (SCHEDULE_SLOT_CHOICES as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === 30 ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>
            <div class="form-group">
                <label class="form-label">Bio</label>
                <textarea name="bio" id="doctorBio" class="form-input" rows="3"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Doctor</button>
        </form>
    </div>
</div>

<script>
function previewDoctorImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('doctorImagePreview');
    const placeholder = document.getElementById('doctorImagePlaceholder');
    
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

function setDoctorAction(action) {
    document.getElementById('doctorAction').value = action;
    document.getElementById('modalTitle').textContent = action === 'add' ? 'Add New Doctor' : 'Edit Doctor';
    if (action === 'add') {
        document.getElementById('doctorId').value = '';
        document.querySelectorAll('#doctorModal .form-input').forEach(input => input.value = '');
        document.getElementById('doctorStatus').value = 'active';
        document.querySelectorAll('.schedule-day').forEach(box => box.checked = [1, 2, 3, 4, 5].includes(Number(box.value)));
        document.getElementById('scheduleStart').value = '09:00';
        document.getElementById('scheduleEnd').value = '17:00';
        document.getElementById('scheduleSlot').value = '30';
        const vBox = document.getElementById('doctorVerified');
        if (vBox) vBox.checked = false;
        document.getElementById('doctorImagePreview').style.display = 'none';
        document.getElementById('doctorImagePlaceholder').style.display = 'block';
    }
}

function editDoctor(doctor) {
    setDoctorAction('edit');
    document.getElementById('doctorId').value = doctor.id;
    document.getElementById('doctorName').value = doctor.name;
    document.getElementById('doctorSpecialization').value = doctor.specialization;
    document.getElementById('doctorExperience').value = doctor.experience;
    document.getElementById('doctorQualification').value = doctor.qualification;
    document.getElementById('doctorHospital').value = doctor.hospital;
    document.getElementById('doctorLocation').value = doctor.location;
    document.getElementById('doctorFee').value = doctor.fee;
    document.getElementById('doctorPhone').value = doctor.phone;
    document.getElementById('doctorEmail').value = doctor.email;
    const days = String(doctor.schedule_days || '').split(',');
    document.querySelectorAll('.schedule-day').forEach(box => box.checked = days.includes(box.value));
    document.getElementById('scheduleStart').value = doctor.schedule_start || '09:00';
    document.getElementById('scheduleEnd').value = doctor.schedule_end || '17:00';
    document.getElementById('scheduleSlot').value = doctor.schedule_slot || '30';
    document.getElementById('doctorBio').value = doctor.bio;
    document.getElementById('doctorStatus').value = doctor.status;
    const verifiedBox = document.getElementById('doctorVerified');
    if (verifiedBox) verifiedBox.checked = Number(doctor.verified) === 1;
    
    // Show doctor image if exists
    if (doctor.image && doctor.image !== 'default-doctor.png') {
        document.getElementById('doctorImagePreview').src = '../uploads/doctors/' + doctor.image;
        document.getElementById('doctorImagePreview').style.display = 'block';
        document.getElementById('doctorImagePlaceholder').style.display = 'none';
    } else {
        document.getElementById('doctorImagePreview').style.display = 'none';
        document.getElementById('doctorImagePlaceholder').style.display = 'block';
    }
    
    openModal('doctorModal');
}
</script>

<?php require_once '../config/footer.php'; ?>
