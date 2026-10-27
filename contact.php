<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/public_layout.php';

const CONTACT_TOPICS = ['General question', 'Account help', 'Appointments', 'Medicine orders', 'Report a problem'];

// Messages go to the support address, or to the sender address when none is set
$recipient = public_support_email();
if ($recipient === '') {
    $recipient = (string)mx_config('mail_from', '');
}
$canReceive = filter_var($recipient, FILTER_VALIDATE_EMAIL) && mail_mode() !== 'off';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '', 100);
    $email = strtolower(clean($_POST['email'] ?? '', 100));
    $topic = pick($_POST['topic'] ?? '', CONTACT_TOPICS, CONTACT_TOPICS[0]);
    $message = trim((string)($_POST['message'] ?? ''));
    $message = mb_substr($message, 0, 3000, 'UTF-8');

    if (!empty($_POST['website'])) {
        // A hidden field only a bot fills in. Say thanks and drop it.
        flash_set('success', 'Thank you. Your message was sent.');
    } elseif (!$canReceive) {
        flash_set('error', 'Messages are not set up yet. Please try again later.');
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message, 'UTF-8') < 10) {
        flash_set('error', 'Enter your name, a valid email and a message of at least 10 characters.');
    } elseif (rate_limited('contact', $email, 3, 6, 60)) {
        http_response_code(429);
        flash_set('error', 'Too many messages. Please wait an hour and try again.');
    } else {
        rate_limit_hit('contact', $email);
        $body = "From: $name <$email>\nTopic: $topic\n\n$message\n\nReply to the sender at $email.";
        if (send_mail($recipient, 'MedXpert contact: ' . $topic, $body)) {
            flash_set('success', 'Thank you. Your message was sent. We reply by email.');
        } else {
            error_log('Contact message not sent: ' . mail_last_error());
            flash_set('error', 'The message could not be sent. Please try again later.');
        }
    }
    redirect('/contact.php');
}

$flash = flash_take();
public_page_start('Contact', 'Send a message to the MedXpert team about your account, an appointment, an order or a problem.', 'contact');
?>
<section class="pub-page-head">
    <div class="pub-wrap">
        <h1><?php te('Contact us'); ?></h1>
        <p><?php te('Ask a question or report a problem. We reply by email.'); ?></p>
    </div>
</section>

<section class="pub-wrap" style="padding-bottom: 4.5rem;">
    <div class="pub-contact-grid">
        <div>
            <?php if ($flash): ?>
                <div class="pub-alert <?php echo $flash['type'] === 'success' ? 'pub-alert-ok' : 'pub-alert-bad'; ?>" role="status"><?php te($flash['message']); ?></div>
            <?php endif; ?>
            <?php if ($canReceive): ?>
            <form method="post" class="pub-form" novalidate>
                <?php echo csrf_field(); ?>
                <div class="pub-field">
                    <label for="c-name"><?php te('Your name'); ?></label>
                    <input id="c-name" name="name" type="text" maxlength="100" autocomplete="name" required>
                </div>
                <div class="pub-field">
                    <label for="c-email"><?php te('Your email'); ?></label>
                    <input id="c-email" name="email" type="email" maxlength="100" autocomplete="email" required>
                </div>
                <div class="pub-field">
                    <label for="c-topic"><?php te('Topic'); ?></label>
                    <select id="c-topic" name="topic">
                        <?php foreach (CONTACT_TOPICS as $topic): ?>
                            <option value="<?php echo e($topic); ?>"><?php te($topic); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pub-field">
                    <label for="c-message"><?php te('Message'); ?></label>
                    <textarea id="c-message" name="message" maxlength="3000" required></textarea>
                </div>
                <div class="pub-trap" aria-hidden="true">
                    <label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>
                <div><button type="submit" class="pub-btn pub-btn-primary"><?php te('Send message'); ?></button></div>
            </form>
            <?php else: ?>
                <p class="pub-note"><?php te('The message form is not set up yet. Please check back soon.'); ?></p>
            <?php endif; ?>
        </div>

        <aside class="pub-side">
            <h2><?php te('In an emergency'); ?></h2>
            <p><?php te('Call'); ?> <a href="tel:<?php echo e(SITE_EMERGENCY_NUMBER); ?>"><strong><?php echo e(SITE_EMERGENCY_NUMBER); ?></strong></a>. <?php te('Do not use this form for an emergency.'); ?></p>
            <?php if (SITE_SUPPORT_EMAIL !== ''): ?>
                <h2><?php te('Email'); ?></h2>
                <p><a href="mailto:<?php echo e(SITE_SUPPORT_EMAIL); ?>"><?php echo e(SITE_SUPPORT_EMAIL); ?></a></p>
            <?php endif; ?>
            <?php if (SITE_SUPPORT_PHONE !== ''): ?>
                <h2><?php te('Phone'); ?></h2>
                <p><a href="tel:<?php echo e(preg_replace('/[^\d+]/', '', SITE_SUPPORT_PHONE)); ?>"><?php echo e(SITE_SUPPORT_PHONE); ?></a></p>
            <?php endif; ?>
            <h2><?php te('Quick answers'); ?></h2>
            <p><a href="/faq.php"><?php te('Read the questions and answers'); ?></a></p>
        </aside>
    </div>
</section>
<?php public_page_end(); ?>
