<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/public_layout.php';

public_page_start('About', 'What MedXpert is, what it does and what it does not do.', 'about');
?>
<section class="pub-page-head">
    <div class="pub-wrap">
        <h1><?php te('About MedXpert'); ?></h1>
        <p><?php te('MedXpert puts the health services people look for most in one place.'); ?></p>
    </div>
</section>

<section class="pub-wrap pub-prose" style="padding-bottom: 4rem;">
    <h2><?php te('Why it exists'); ?></h2>
    <p><?php te('Finding a doctor, a blood donor, a pharmacy and an ambulance usually means four phone calls and four different people. MedXpert brings them into one account so you can search, book and follow each request in one place.'); ?></p>

    <h2><?php te('What it does'); ?></h2>
    <ul>
        <li><?php te('Books appointments with doctors, using the times each doctor has open.'); ?></li>
        <li><?php te('Lists blood donors by blood type and place.'); ?></li>
        <li><?php te('Takes medicine orders, with a prescription photo when the medicine needs one.'); ?></li>
        <li><?php te('Takes ambulance requests and keeps the details together.'); ?></li>
        <li><?php te('Shows you the status of every appointment and order, and sends email updates.'); ?></li>
    </ul>

    <h2><?php te('What it does not do'); ?></h2>
    <ul>
        <li><?php te('It does not give medical advice, a diagnosis or treatment. Talk to a doctor.'); ?></li>
        <li><?php te('It is not an emergency line. In an emergency, call 999.'); ?></li>
        <li><?php te('It does not check the health of blood donors.'); ?></li>
        <li><?php te('It does not take online payments yet.'); ?></li>
    </ul>

    <h2><?php te('How it handles your data'); ?></h2>
    <p><?php te('You agree to the privacy policy before you register. Prescription photos are private. You can download your data or delete your account from your profile. Every admin action is logged.'); ?> <a href="/privacy.php"><?php te('Read the privacy policy'); ?></a>.</p>

    <h2><?php te('Talk to us'); ?></h2>
    <p><?php te('Questions, problems and ideas are welcome.'); ?> <a href="/contact.php"><?php te('Contact MedXpert'); ?></a>.</p>
</section>
<?php public_page_end(); ?>
