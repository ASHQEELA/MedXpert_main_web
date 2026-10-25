<?php
require_once '../config/header.php';
?>

<h1>Online Pharmacy</h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> Home</span>
    <span>/</span>
    <span>Online Pharmacy</span>
</div>
</div>
</div>

<div class="content-wrapper">
    <!-- Search Box -->
    <div class="search-box">
        <i class="fas fa-search search-icon"></i>
        <input type="text" class="search-input" data-search="medicines" placeholder="Search medicines by name or category...">
    </div>
    
    <!-- Category Filters -->
    <div class="filter-buttons">
        <button class="filter-btn active" data-type="medicine" data-filter="all">All Categories</button>
        <button class="filter-btn" data-type="medicine" data-filter="Pain Relief">Pain Relief</button>
        <button class="filter-btn" data-type="medicine" data-filter="Antibiotic">Antibiotic</button>
        <button class="filter-btn" data-type="medicine" data-filter="Gastric">Gastric</button>
        <button class="filter-btn" data-type="medicine" data-filter="Allergy">Allergy</button>
        <button class="filter-btn" data-type="medicine" data-filter="Diabetes">Diabetes</button>
        <button class="filter-btn" data-type="medicine" data-filter="Blood Pressure">Blood Pressure</button>
        <button class="filter-btn" data-type="medicine" data-filter="Vitamin">Vitamin</button>
    </div>
    
    <!-- Medicines Grid -->
    <div class="items-grid" id="medicinesContainer">
        <div class="spinner" role="status" aria-label="Loading"></div>
    </div>
</div>

<!-- Cart button -->
<button type="button" class="cart-fab" id="cartButton" onclick="openCart()" aria-label="Open your cart">
    <i class="fas fa-shopping-cart"></i> <span>Cart</span> <span class="cart-count" id="cartCount">0</span>
</button>

<!-- Cart and checkout modal -->
<div class="modal" id="orderModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Your Cart</h3>
            <button class="modal-close" onclick="closeModal('orderModal')" aria-label="Close">&times;</button>
        </div>
        <form id="orderForm" onsubmit="event.preventDefault(); submitOrder();" enctype="multipart/form-data">
            <div class="modal-body">
                <div id="cartLines" class="cart-lines"></div>

                <div class="cart-total">
                    <strong>Total Amount:</strong>
                    <strong>৳<span id="orderTotal">0.00</span></strong>
                </div>

                <div class="form-group" id="rxGroup" hidden>
                    <label class="form-label" for="prescriptionFile">Prescription photo</label>
                    <input type="file" class="form-input" id="prescriptionFile" name="prescription" accept="image/jpeg,image/png,image/webp">
                    <small class="slot-note">Needed for: <span id="rxNames"></span>. JPG, PNG or WebP, up to 2 MB.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Delivery Address</label>
                    <textarea class="form-input" name="delivery_address" rows="3" placeholder="Enter your complete delivery address" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-input" name="phone" placeholder="Enter your phone number" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select class="form-input" name="payment_method" required>
                        <option value="cash">Cash on Delivery</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="mobile_banking">Mobile Banking (bKash/Nagad)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('orderModal')">Keep shopping</button>
                <button type="submit" class="btn btn-primary" id="placeOrderBtn">Place Order</button>
            </div>
        </form>
    </div>
</div>


<?php require_once '../config/footer.php'; ?>