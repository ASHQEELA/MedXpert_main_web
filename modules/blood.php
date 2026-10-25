<?php
require_once '../config/header.php';
?>

<h1>Blood Bank</h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> Home</span>
    <span>/</span>
    <span>Blood Bank</span>
</div>
</div>
</div>

<div class="content-wrapper">
    <!-- Search Box -->
    <div class="search-box">
        <i class="fas fa-search search-icon"></i>
        <input type="text" class="search-input" data-search="donors" placeholder="Search donors by name or location...">
    </div>
    
    <!-- Blood Type Filters -->
    <div class="filter-buttons">
        <button class="filter-btn active" data-type="blood" data-filter="all">All Types</button>
        <button class="filter-btn" data-type="blood" data-filter="A+">A+</button>
        <button class="filter-btn" data-type="blood" data-filter="A-">A-</button>
        <button class="filter-btn" data-type="blood" data-filter="B+">B+</button>
        <button class="filter-btn" data-type="blood" data-filter="B-">B-</button>
        <button class="filter-btn" data-type="blood" data-filter="AB+">AB+</button>
        <button class="filter-btn" data-type="blood" data-filter="AB-">AB-</button>
        <button class="filter-btn" data-type="blood" data-filter="O+">O+</button>
        <button class="filter-btn" data-type="blood" data-filter="O-">O-</button>
    </div>
    
    <!-- Donors Grid -->
    <div class="items-grid" id="donorsContainer">
        <div class="spinner" role="status" aria-label="Loading"></div>
    </div>
</div>


<?php require_once '../config/footer.php'; ?>