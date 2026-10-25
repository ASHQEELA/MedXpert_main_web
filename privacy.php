<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/auth_layout.php';
require_once __DIR__ . '/config/site.php';

$contact = defined('SITE_SUPPORT_EMAIL') && SITE_SUPPORT_EMAIL !== '' ? SITE_SUPPORT_EMAIL : '';
auth_page_start('Privacy Policy');
?>
            <article class="policy">
                <p class="policy-meta">Version <?php echo e(PRIVACY_VERSION); ?></p>

                <h2>What we collect</h2>
                <ul>
                    <li>Account details: your name, email address, phone number, address and profile photo.</li>
                    <li>Health information you enter: symptoms for appointments, the medicines you order, prescription photos, and ambulance request details.</li>
                    <li>Technical data: your IP address for security checks such as login limits, and a session cookie that keeps you logged in.</li>
                </ul>

                <h2>Why we use it</h2>
                <p>We use your information to book appointments, deliver medicine orders, send ambulance requests, contact you about them, and keep the service safe. We do not sell your information. We do not use it for advertising.</p>

                <h2>Who can see it</h2>
                <ul>
                    <li>You can see your own records.</li>
                    <li>MedXpert admins can see appointments, orders, prescription photos and requests so they can process them. Admin views of prescription photos are written to an audit log.</li>
                    <li>Other users cannot see your records. Blood donor listings show only the details a donor chose to publish.</li>
                </ul>

                <h2>How long we keep it</h2>
                <p>We keep your records while your account is open. You can delete your account at any time from your profile. Deleting it removes your profile, appointments, orders, prescription photos, ambulance requests and notifications.</p>

                <h2>Your choices</h2>
                <ul>
                    <li>Download a copy of your data from your profile.</li>
                    <li>Change your details on your profile.</li>
                    <li>Delete your account from your profile.</li>
                </ul>

                <h2>How we protect it</h2>
                <p>Passwords are stored as one-way hashes. Pages are served over HTTPS on a live site. Prescription photos are private files that only you and admins can open. Access to admin tools needs an admin account.</p>

                <h2>Cookies</h2>
                <p>We use one cookie to keep you logged in and one to remember your language. We use no advertising or tracking cookies.</p>

                <h2>Medical notice</h2>
                <p>MedXpert does not replace professional medical advice, diagnosis, or treatment. In an emergency call <?php echo e(SITE_EMERGENCY_NUMBER); ?>.</p>

                <h2>Contact</h2>
                <p><?php echo $contact !== '' ? 'Write to <a href="mailto:' . e($contact) . '">' . e($contact) . '</a> with any question about your data.' : 'Use the contact details on the site with any question about your data.'; ?></p>

                <h2>Changes</h2>
                <p>If this policy changes, we ask you to accept the new version the next time you log in.</p>
            </article>
<?php auth_page_end(); ?>
