<?php
require_once '../config/header.php';
?>

<h1>Doctor Consultation</h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> Home</span>
    <span>/</span>
    <span>Doctor Consultation</span>
</div>
</div>
</div>

<div class="content-wrapper">
    <!-- Search Box -->
    <div class="search-box">
        <i class="fas fa-search search-icon"></i>
        <input type="text" class="search-input" data-search="doctors" placeholder="Search by name, specialization, or hospital...">
    </div>
    
    <!-- Doctors Grid -->
    <div class="items-grid" id="doctorsContainer">
        <div class="spinner" role="status" aria-label="Loading"></div>
    </div>
</div>

<!-- Appointment Modal -->
<div class="modal" id="appointmentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Book Appointment</h3>
            <button class="modal-close" onclick="closeModal('appointmentModal')">&times;</button>
        </div>
        <form id="appointmentForm" onsubmit="event.preventDefault(); submitAppointment();">
            <div class="modal-body">
                <input type="hidden" id="appointmentDoctorId" name="doctor_id">
                
                <div class="form-group">
                    <label class="form-label" for="appointmentDate">Appointment Date</label>
                    <input type="date" class="form-input" id="appointmentDate" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>" onchange="loadSlots()">
                </div>

                <div class="form-group">
                    <span class="form-label" id="slotLabel">Available Times</span>
                    <input type="hidden" name="appointment_time" id="appointmentTime">
                    <div id="slotPicker" class="slot-grid" role="group" aria-labelledby="slotLabel">
                        <p class="slot-note">Pick a date to see the times.</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Consultation Type</label>
                    <select class="form-input" name="consultation_type" required>
                        <option value="in-person">In-Person Visit</option>
                        <option value="video">Video Consultation</option>
                        <option value="phone">Phone Consultation</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Symptoms / Reason for Visit</label>
                    <textarea class="form-input" name="symptoms" rows="4" placeholder="Describe your symptoms or reason for consultation..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('appointmentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Booking</button>
            </div>
        </form>
    </div>
</div>


<?php require_once '../config/footer.php'; ?>