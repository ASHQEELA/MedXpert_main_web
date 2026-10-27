<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/public_layout.php';

public_page_start('Terms of use', 'The rules for using MedXpert, in plain language.', '');
?>
<section class="pub-page-head">
    <div class="pub-wrap">
        <h1><?php te('Terms of use'); ?></h1>
        <p><?php te('The rules for using MedXpert, in plain language.'); ?></p>
    </div>
</section>

<section class="pub-wrap pub-prose" style="padding-bottom: 4rem;">
    <h2>1. Not medical advice</h2>
    <p>MedXpert helps you find and book services. It does not give medical advice, a diagnosis or treatment. Always ask a qualified doctor about your health.</p>

    <h2>2. Emergencies</h2>
    <p>MedXpert is not an emergency service. In an emergency, call <?php echo e(SITE_EMERGENCY_NUMBER); ?> first.</p>

    <h2>3. Your account</h2>
    <ul>
        <li>Give true details and keep them up to date.</li>
        <li>Keep your password private. You are responsible for what happens in your account.</li>
        <li>Tell us if you think someone else is using your account.</li>
    </ul>

    <h2>4. Using the service</h2>
    <ul>
        <li>Do not book appointments, place orders or send requests you do not mean to keep.</li>
        <li>Upload only your own prescriptions, or those of someone who has agreed.</li>
        <li>Do not try to break, overload or get around the site, or to reach other people's data.</li>
        <li>Do not use donor details for anything except a blood request.</li>
    </ul>

    <h2>5. Doctors, donors and medicines</h2>
    <p>Doctors, donors and medicine suppliers are separate from MedXpert. Their details, fees, prices and availability can change. MedXpert does not check a donor's health and does not promise that a doctor, a donor, a medicine or an ambulance will be available.</p>

    <h2>6. Orders and appointments</h2>
    <p>An order or an appointment is a request until it is confirmed. You can cancel a pending order and a pending or confirmed appointment from your dashboard.</p>

    <h2>7. Your data</h2>
    <p>How we use your information is in the <a href="/privacy.php">privacy policy</a>.</p>

    <h2>8. Closing an account</h2>
    <p>You can delete your account from your profile. We can suspend an account that breaks these terms.</p>

    <h2>9. Changes</h2>
    <p>We can change these terms. When we do, the new version appears on this page.</p>

    <h2>10. Contact</h2>
    <p>Questions about these terms? <a href="/contact.php">Contact us</a>.</p>
</section>
<?php public_page_end(); ?>
