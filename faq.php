<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/public_layout.php';

$groups = [
    'Account' => [
        ['Is MedXpert free to use?', 'Creating an account is free. Doctors and pharmacies set their own fees and prices, and you see them before you book or order.'],
        ['Why do I need to confirm my email?', 'The link proves the address is yours, so we can send you appointment and order updates. The link works once and lasts 48 hours.'],
        ['I did not get the confirmation email. What now?', 'Check your Spam folder. Then ask for a new link on the resend page. It sends a fresh link to any account that is still waiting.'],
        ['I forgot my password.', 'Use the forgot password link on the login page. We email you a link that works once and lasts 60 minutes.'],
    ],
    'Doctors' => [
        ['How do I book a doctor?', 'Log in, open Doctor Consultation, choose a doctor, pick a date and a free time, and describe the reason for the visit.'],
        ['Why can I not see a time I want?', 'Times appear only when the doctor works that day and nobody has taken the slot.'],
        ['Can I cancel?', 'Yes. Open My Appointments and cancel a pending or confirmed appointment.'],
    ],
    'Medicine' => [
        ['Do I need a prescription?', 'Only for medicines marked as prescription medicines. You upload a photo of the prescription when you order.'],
        ['How do I pay?', 'You choose cash, card or mobile banking when you order. MedXpert records your choice. It does not take online payments yet.'],
        ['Can I cancel an order?', 'Yes, while it is pending. After the pharmacy starts preparing it, you cannot cancel it in the app.'],
    ],
    'Blood bank and ambulance' => [
        ['Who sees donor phone numbers?', 'Only people who are logged in.'],
        ['Does MedXpert check donors?', 'No. Ask the donor about recent illness and the last donation, and confirm the match at the hospital.'],
        ['What if it is an emergency?', 'Call 999 first. MedXpert is not an emergency line.'],
    ],
    'Privacy' => [
        ['What do you do with my health information?', 'We use it only to provide the services you ask for. The privacy policy explains this in full.'],
        ['Can I get my data or delete my account?', 'Yes. Both options are on your profile page.'],
        ['Who can open my prescription photo?', 'You and MedXpert admins. Admin views are logged.'],
    ],
];

public_page_start('Questions and answers', 'Answers about accounts, doctors, medicine, blood donors, ambulance requests and privacy on MedXpert.', 'faq');
?>
<section class="pub-page-head">
    <div class="pub-wrap">
        <h1><?php te('Questions and answers'); ?></h1>
        <p><?php te('Not here? Send us a message.'); ?> <a href="/contact.php"><?php te('Contact us'); ?></a>.</p>
    </div>
</section>

<section class="pub-wrap" style="padding-bottom: 4rem;">
    <div class="pub-faq">
        <?php foreach ($groups as $group => $items): ?>
            <h2 class="pub-faq-group"><?php te($group); ?></h2>
            <?php foreach ($items as [$q, $a]): ?>
                <details>
                    <summary><?php te($q); ?></summary>
                    <p><?php te($a); ?><?php echo $q === 'I did not get the confirmation email. What now?' ? ' <a href="/resend_verification.php">' . e(t('Send a new link')) . '</a>.' : ''; ?></p>
                </details>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php public_page_end(); ?>
