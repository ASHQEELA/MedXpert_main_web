<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/public_layout.php';

public_page_start('Services', 'Doctor consultation, blood bank, online pharmacy and ambulance requests on MedXpert. See what each service does.', 'services');
$cta = isLoggedIn() ? ['/dashboard.php', 'Open dashboard'] : ['/login.php#register', 'Create free account'];
?>
<section class="pub-page-head">
    <div class="pub-wrap">
        <h1><?php te('Four services, one account'); ?></h1>
        <p><?php te('Each service works the same way: search, choose, then follow it from your dashboard.'); ?></p>
    </div>
</section>

<section class="pub-wrap" style="padding-bottom: 3rem;">
    <article class="pub-service" id="doctors">
        <div class="pub-service-icon" style="background: var(--pub-blue);"><i class="fas fa-user-doctor" aria-hidden="true"></i></div>
        <div>
            <h2><?php te('Doctor consultation'); ?></h2>
            <p><?php te('Find a doctor and book a time that is free.'); ?></p>
            <ul>
                <li><?php te('Search by name, specialty, hospital or place.'); ?></li>
                <li><?php te('Pick a date. You only see times the doctor works and nobody has taken.'); ?></li>
                <li><?php te('Choose an in-person, video or phone visit.'); ?></li>
                <li><?php te('Cancel a pending or confirmed appointment from My Appointments.'); ?></li>
            </ul>
            <a class="pub-btn pub-btn-ghost" href="<?php echo e($cta[0]); ?>"><?php te($cta[1]); ?></a>
        </div>
    </article>

    <article class="pub-service" id="blood">
        <div class="pub-service-icon" style="background: var(--pub-rose);"><i class="fas fa-droplet" aria-hidden="true"></i></div>
        <div>
            <h2><?php te('Blood bank'); ?></h2>
            <p><?php te('Find donors near you and call them directly.'); ?></p>
            <ul>
                <li><?php te('Filter by blood type and search by place.'); ?></li>
                <li><?php te('Donor phone numbers show only to people who are logged in.'); ?></li>
                <li><?php te('MedXpert does not check a donor\'s health. Ask about recent illness and the last donation, and confirm the match at the hospital.'); ?></li>
            </ul>
            <a class="pub-btn pub-btn-ghost" href="<?php echo e($cta[0]); ?>"><?php te($cta[1]); ?></a>
        </div>
    </article>

    <article class="pub-service" id="pharmacy">
        <div class="pub-service-icon" style="background: var(--pub-teal);"><i class="fas fa-pills" aria-hidden="true"></i></div>
        <div>
            <h2><?php te('Online pharmacy'); ?></h2>
            <p><?php te('Order medicine and follow the delivery.'); ?></p>
            <ul>
                <li><?php te('Search by name, category or maker. Add up to 20 medicines to one order.'); ?></li>
                <li><?php te('For prescription medicines, upload a photo of the prescription. Only you and MedXpert admins can open it.'); ?></li>
                <li><?php te('Choose cash, card or mobile banking. MedXpert records your choice. It does not take online payments yet.'); ?></li>
                <li><?php te('Follow the order from received to delivered, and cancel while it is pending.'); ?></li>
            </ul>
            <a class="pub-btn pub-btn-ghost" href="<?php echo e($cta[0]); ?>"><?php te($cta[1]); ?></a>
        </div>
    </article>

    <article class="pub-service" id="ambulance">
        <div class="pub-service-icon" style="background: var(--pub-orange);"><i class="fas fa-truck-medical" aria-hidden="true"></i></div>
        <div>
            <h2><?php te('Ambulance service'); ?></h2>
            <p><?php te('Send a request with the pickup place, the destination and the patient\'s condition.'); ?></p>
            <ul>
                <li><?php te('Mark it as an emergency or a scheduled trip.'); ?></li>
                <li><?php te('Your phone number goes with the request, so the team can call you back.'); ?></li>
            </ul>
            <p class="pub-note"><strong><?php te('In an emergency, call'); ?> <a href="tel:<?php echo e(SITE_EMERGENCY_NUMBER); ?>"><?php echo e(SITE_EMERGENCY_NUMBER); ?></a> <?php te('first.'); ?></strong> <?php te('A web request is not a replacement for the emergency line.'); ?></p>
            <a class="pub-btn pub-btn-ghost" style="margin-top: 1.2rem;" href="<?php echo e($cta[0]); ?>"><?php te($cta[1]); ?></a>
        </div>
    </article>
</section>
<?php public_page_end(); ?>
