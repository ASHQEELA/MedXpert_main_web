<?php
require_once '../config/header.php';
?>

<h1>Ambulance Service</h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> Home</span>
    <span>/</span>
    <span>Ambulance Service</span>
</div>
</div>
</div>

<div class="content-wrapper">
    <div class="card hero-card">
        <h2><i class="fas fa-exclamation-triangle"></i> Emergency Ambulance Service</h2>
        <p>Available 24/7 for immediate medical emergencies</p>
        <a class="hotline" href="tel:<?php echo SITE_EMERGENCY_NUMBER; ?>">
            <i class="fas fa-phone-volume"></i>
            <span><small>24/7 emergency hotline</small><strong><?php echo SITE_EMERGENCY_NUMBER; ?></strong></span>
        </a>
    </div>
    
    <div class="modules-grid">
        <div class="module-card emergency-card" onclick="requestAmbulance('emergency')">
            <div class="module-icon">
                <i class="fas fa-phone-volume"></i>
            </div>
            <h3>Emergency Call</h3>
            <p>Immediate ambulance dispatch for critical emergencies</p>
            <button class="btn" style="background: white; color: #b91c1c; font-weight: 600;">
                <i class="fas fa-phone"></i> Call 999 Now
            </button>
        </div>
        
        <div class="module-card" onclick="requestAmbulance('scheduled')">
            <div class="module-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <h3>Scheduled Transport</h3>
            <p>Book ambulance for scheduled hospital visits or transfers</p>
            <button class="btn btn-primary">
                <i class="fas fa-calendar-plus"></i> Schedule Request
            </button>
        </div>
    </div>
    
    <!-- Info Cards -->
    <div class="grid-auto mt-3">
        <div class="info-card">
            <h3><i class="fas fa-clock"></i> Response Time</h3>
            <span class="stat-value" style="color: var(--success);">10-15 min</span>
            <p>Average response time in urban areas</p>
        </div>
        
        <div class="info-card">
            <h3><i class="fas fa-ambulance"></i> Fleet Size</h3>
            <span class="stat-value" style="color: var(--info);">50+ Units</span>
            <p>Advanced life support ambulances</p>
        </div>
        
        <div class="info-card">
            <h3><i class="fas fa-user-md"></i> Medical Staff</h3>
            <span class="stat-value" style="color: var(--warning);">100%</span>
            <p>Trained paramedics on every ambulance</p>
        </div>
    </div>
    
    <!-- Features List -->
    <div class="card mt-3">
        <h3>Our Ambulance Features</h3>
        <div class="grid-auto">
            <div class="feature-item">
                <i class="fas fa-check-circle"></i>
                <div>
                    <h4>Advanced Life Support</h4>
                    <p>Equipped with modern medical equipment</p>
                </div>
            </div>
            
            <div class="feature-item">
                <i class="fas fa-check-circle"></i>
                <div>
                    <h4>Trained Paramedics</h4>
                    <p>Certified medical professionals</p>
                </div>
            </div>
            
            <div class="feature-item">
                <i class="fas fa-check-circle"></i>
                <div>
                    <h4>GPS Tracking</h4>
                    <p>Real-time location monitoring</p>
                </div>
            </div>
            
            <div class="feature-item">
                <i class="fas fa-check-circle"></i>
                <div>
                    <h4>24/7 Availability</h4>
                    <p>Round-the-clock emergency service</p>
                </div>
            </div>
            
            <div class="feature-item">
                <i class="fas fa-check-circle"></i>
                <div>
                    <h4>Oxygen Support</h4>
                    <p>Medical oxygen and ventilators</p>
                </div>
            </div>
            
            <div class="feature-item">
                <i class="fas fa-check-circle"></i>
                <div>
                    <h4>Rapid Response</h4>
                    <p>Quick dispatch and navigation</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ambulance Request Modal -->
<div class="modal" id="ambulanceModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Request Ambulance</h3>
            <button class="modal-close" onclick="closeModal('ambulanceModal')">&times;</button>
        </div>
        <form id="ambulanceForm" onsubmit="event.preventDefault(); submitAmbulanceRequest();">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Patient Name</label>
                    <input type="text" class="form-input" name="name" placeholder="Enter patient name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="tel" class="form-input" name="phone" placeholder="Enter contact number" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Pickup Location</label>
                    <textarea class="form-input" name="pickup_location" rows="2" placeholder="Enter complete pickup address" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Destination Hospital</label>
                    <textarea class="form-input" name="destination" rows="2" placeholder="Enter destination hospital or address" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Emergency Type</label>
                    <select class="form-input" name="emergency_type" required>
                        <option value="">Select emergency type</option>
                        <option value="Heart Emergency">Heart Emergency</option>
                        <option value="Accident">Accident</option>
                        <option value="Breathing Problem">Breathing Problem</option>
                        <option value="Stroke">Stroke</option>
                        <option value="Pregnancy">Pregnancy/Delivery</option>
                        <option value="Other">Other Emergency</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Patient Condition</label>
                    <textarea class="form-input" name="patient_condition" rows="3" placeholder="Briefly describe the patient's condition" required></textarea>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i>
                    <span>For immediate life-threatening emergencies, please call 999 directly</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('ambulanceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../config/footer.php'; ?>
