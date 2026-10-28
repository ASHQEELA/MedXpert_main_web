<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/public_layout.php';

if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$home = public_home_data($conn);

public_page_start(
    'MedXpert - Doctors, blood donors, medicine and ambulance in Bangladesh',
    'Book a doctor, find a blood donor, order medicine and request an ambulance from one MedXpert account.',
    'home'
);
?>
<section class="pub-hero">
    <div class="pub-wrap pub-hero-grid">
        <div>
            <h1><?php te('Care in Bangladesh, in one place.'); ?></h1>
            <p class="pub-lead"><?php te('Book a doctor, find a blood donor, order medicine and request an ambulance. One account, one dashboard.'); ?></p>
            <div class="pub-cta-row">
                <a class="pub-btn pub-btn-primary" href="/login.php#register"><?php te('Create free account'); ?></a>
                <a class="pub-btn pub-btn-ghost" href="/login.php"><?php te('Log in'); ?></a>
            </div>
        </div>

        <div class="pub-signs" aria-label="<?php te('Services'); ?>">
            <p class="pub-board-title"><?php te('Where do you need to go?'); ?></p>
            <a class="pub-sign pub-sign-doctors" href="/services.php#doctors">
                <span class="pub-sign-icon"><i class="fas fa-user-doctor" aria-hidden="true"></i></span>
                <span>
                    <span class="pub-sign-name"><?php te('Doctor consultation'); ?></span>
                    <span class="pub-sign-note"><?php echo $home['doctors'] > 0 ? e($home['doctors'] . ' ' . t($home['doctors'] === 1 ? 'doctor to book' : 'doctors to book')) : e(t('Book a time slot')); ?></span>
                </span>
                <i class="fas fa-arrow-right pub-sign-go" aria-hidden="true"></i>
            </a>
            <a class="pub-sign pub-sign-blood" href="/services.php#blood">
                <span class="pub-sign-icon"><i class="fas fa-droplet" aria-hidden="true"></i></span>
                <span>
                    <span class="pub-sign-name"><?php te('Blood bank'); ?></span>
                    <span class="pub-sign-note"><?php echo $home['donors'] > 0 ? e($home['donors'] . ' ' . t($home['donors'] === 1 ? 'donor ready to help' : 'donors ready to help')) : e(t('Search donors by blood type')); ?></span>
                </span>
                <i class="fas fa-arrow-right pub-sign-go" aria-hidden="true"></i>
            </a>
            <a class="pub-sign pub-sign-pharmacy" href="/services.php#pharmacy">
                <span class="pub-sign-icon"><i class="fas fa-pills" aria-hidden="true"></i></span>
                <span>
                    <span class="pub-sign-name"><?php te('Online pharmacy'); ?></span>
                    <span class="pub-sign-note"><?php echo $home['medicines'] > 0 ? e($home['medicines'] . ' ' . t($home['medicines'] === 1 ? 'medicine in stock' : 'medicines in stock')) : e(t('Order with a prescription when needed')); ?></span>
                </span>
                <i class="fas fa-arrow-right pub-sign-go" aria-hidden="true"></i>
            </a>
            <a class="pub-sign pub-sign-ambulance" href="/services.php#ambulance">
                <span class="pub-sign-icon"><i class="fas fa-truck-medical" aria-hidden="true"></i></span>
                <span>
                    <span class="pub-sign-name"><?php te('Ambulance service'); ?></span>
                    <span class="pub-sign-note"><?php te('Request a ride to hospital'); ?></span>
                </span>
                <i class="fas fa-arrow-right pub-sign-go" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</section>

<section class="pub-section pub-section-white" id="how">
    <div class="pub-wrap">
        <div class="pub-head">
            <h2><?php te('Start in three steps'); ?></h2>
        </div>
        <ol class="pub-steps">
            <li>
                <h3><?php te('Create your account'); ?></h3>
                <p><?php te('Register with your email. We send a link to confirm it.'); ?></p>
            </li>
            <li>
                <h3><?php te('Pick a service'); ?></h3>
                <p><?php te('Search doctors and book a free time slot. Find donors by blood type. Order medicine. Request an ambulance.'); ?></p>
            </li>
            <li>
                <h3><?php te('Follow it from your dashboard'); ?></h3>
                <p><?php te('See the status of each appointment and order. Get email updates. Cancel while a request is still pending.'); ?></p>
            </li>
        </ol>
    </div>
</section>

<?php if ($home['top']): ?>
<section class="pub-section">
    <div class="pub-wrap">
        <div class="pub-head">
            <h2><?php te('Top rated doctors'); ?></h2>
            <p><?php te('Log in to see every doctor, their fees and open time slots.'); ?></p>
        </div>
        <div class="pub-doctors">
            <?php foreach ($home['top'] as $doc):
                $initials = '';
                foreach (array_slice(preg_split('/\s+/', preg_replace('/^Dr\.?\s*/i', '', (string)$doc['name'])), 0, 2) as $part) {
                    $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
                }
                $img = (string)($doc['image'] ?? '');
                $hasImg = $img !== '' && $img !== 'default-doctor.png' && is_file(__DIR__ . '/uploads/doctors/' . safe_image_name($img));
                ?>
                <article class="pub-doctor">
                    <div class="pub-avatar">
                        <?php if ($hasImg): ?>
                            <img src="/uploads/doctors/<?php echo e(safe_image_name($img)); ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <?php echo e($initials); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3><?php echo e($doc['name']); ?></h3>
                        <p><?php echo e($doc['specialization']); ?></p>
                        <p><?php echo e($doc['hospital']); ?>, <?php echo e($doc['location']); ?></p>
                        <span class="pub-rating"><i class="fas fa-star" aria-hidden="true"></i> <?php echo e(number_format((float)$doc['rating'], 1)); ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="pub-more"><a class="pub-btn pub-btn-ghost" href="/login.php"><?php te('Log in to book'); ?></a></p>
    </div>
</section>
<?php endif; ?>

<section class="pub-section pub-band">
    <div class="pub-wrap">
        <div class="pub-head">
            <h2><?php te('Your health information stays yours'); ?></h2>
        </div>
        <ul class="pub-promises">
            <li>
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <div>
                    <h3><?php te('You agree first'); ?></h3>
                    <p><?php te('You accept the privacy policy before you register. We record when you did.'); ?></p>
                </div>
            </li>
            <li>
                <i class="fas fa-file-medical" aria-hidden="true"></i>
                <div>
                    <h3><?php te('Prescriptions are private'); ?></h3>
                    <p><?php te('Only you and MedXpert admins can open a prescription photo.'); ?></p>
                </div>
            </li>
            <li>
                <i class="fas fa-download" aria-hidden="true"></i>
                <div>
                    <h3><?php te('Take your data with you'); ?></h3>
                    <p><?php te('Download what we hold about you, or delete your account, from your profile.'); ?></p>
                </div>
            </li>
            <li>
                <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                <div>
                    <h3><?php te('Admin actions are logged'); ?></h3>
                    <p><?php te('Every change an admin makes is recorded with the time and the admin who did it.'); ?> <a href="/privacy.php"><?php te('Read the privacy policy'); ?></a>.</p>
                </div>
            </li>
        </ul>
    </div>
</section>

<section class="pub-section">
    <div class="pub-wrap pub-split">
        <div class="pub-head">
            <h2><?php te('Common questions'); ?></h2>
            <p class="pub-more"><a href="/faq.php"><?php te('See all questions'); ?></a></p>
        </div>
        <div class="pub-faq">
            <details>
                <summary><?php te('Is MedXpert free to use?'); ?></summary>
                <p><?php te('Creating an account is free. Doctors and pharmacies set their own fees and prices, and you see them before you book or order.'); ?></p>
            </details>
            <details>
                <summary><?php te('Do I need a prescription to order medicine?'); ?></summary>
                <p><?php te('Only for medicines marked as prescription medicines. You upload a photo of the prescription when you order.'); ?></p>
            </details>
            <details>
                <summary><?php te('Can I cancel an appointment or an order?'); ?></summary>
                <p><?php te('Yes, from My Appointments and My Orders. You can cancel a pending order and any pending or confirmed appointment.'); ?></p>
            </details>
            <details>
                <summary><?php te('What if it is an emergency?'); ?></summary>
                <p><?php te('Call 999 first. MedXpert is not an emergency line.'); ?></p>
            </details>
        </div>
    </div>
</section>

<section class="pub-closing">
    <div class="pub-wrap pub-closing-row">
        <div>
            <h2><?php te('Create your account in a minute'); ?></h2>
            <p><?php te('You only need an email address and a phone number.'); ?></p>
        </div>
        <a class="pub-btn pub-btn-light" href="/login.php#register"><?php te('Create free account'); ?></a>
    </div>
</section>
<?php public_page_end(); ?>
