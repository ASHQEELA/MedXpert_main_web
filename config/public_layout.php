<?php
/**
 * Shared frame for the public pages: home, services, about, FAQ, contact, terms.
 * Use public_page_start() at the top and public_page_end() at the bottom.
 */
require_once __DIR__ . '/site.php';

const PUBLIC_NAV = [
    'services' => ['/services.php', 'Services'],
    'how' => ['/index.php#how', 'How it works'],
    'about' => ['/about.php', 'About'],
    'faq' => ['/faq.php', 'FAQ'],
    'contact' => ['/contact.php', 'Contact'],
];

function public_page_start(string $title, string $description, string $active = ''): void
{
    $fullTitle = $active === 'home' ? $title : $title . ' - MedXpert';
    $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $langNext = $path !== '' && $path[0] === '/' ? $path : '/index.php';
    $other = lang_code() === 'bn' ? 'en' : 'bn';
    ?>
<!DOCTYPE html>
<html lang="<?php echo e(lang_code()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($fullTitle); ?></title>
    <meta name="description" content="<?php echo e($description); ?>">
    <meta property="og:title" content="<?php echo e($fullTitle); ?>">
    <meta property="og:description" content="<?php echo e($description); ?>">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="#0b2e26">
    <?php
    foreach (['/assets/vendor/fonts/fonts.css', '/assets/vendor/fontawesome/css/fa.min.css', '/assets/css/public.css'] as $css) {
        echo '<link rel="stylesheet" href="' . e(asset_url($css)) . '">' . "\n    ";
    }
    ?>
    <link rel="preload" href="/assets/vendor/fonts/bricolage-grotesque-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
</head>
<body class="pub">
    <a class="pub-skip" href="#main"><?php te('Skip to main content'); ?></a>
    <div class="pub-sos">
        <div class="pub-wrap">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <span><strong><?php te('In an emergency, call'); ?> <a href="tel:<?php echo e(SITE_EMERGENCY_NUMBER); ?>"><?php echo e(SITE_EMERGENCY_NUMBER); ?></a> <?php te('first.'); ?></strong> <?php te('MedXpert is not an emergency line.'); ?></span>
        </div>
    </div>
    <header class="pub-nav" id="pubNav">
        <div class="pub-wrap pub-nav-row">
            <a class="pub-brand" href="/index.php"><i class="fas fa-heartbeat" aria-hidden="true"></i> MedXpert</a>
            <nav class="pub-links" aria-label="<?php te('Main'); ?>">
                <?php foreach (PUBLIC_NAV as $key => [$href, $label]): ?>
                    <a href="<?php echo e($href); ?>"<?php echo $active === $key ? ' aria-current="page"' : ''; ?>><?php te($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="pub-nav-actions">
                <form method="post" action="/api/set_language.php" class="pub-lang">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="next" value="<?php echo e($langNext); ?>">
                    <input type="hidden" name="lang" value="<?php echo e($other); ?>">
                    <button type="submit" lang="<?php echo e($other); ?>"><?php echo $other === 'bn' ? 'বাংলা' : 'English'; ?></button>
                </form>
                <?php if (isLoggedIn()): ?>
                    <a class="pub-btn pub-btn-primary pub-btn-small" href="/dashboard.php"><?php te('Open dashboard'); ?></a>
                <?php else: ?>
                    <a class="pub-btn pub-btn-ghost pub-btn-small" href="/login.php"><?php te('Log in'); ?></a>
                    <a class="pub-btn pub-btn-primary pub-btn-small" href="/login.php#register"><?php te('Create account'); ?></a>
                <?php endif; ?>
            </div>
            <button type="button" class="pub-menu-btn" id="pubMenuBtn" aria-expanded="false" aria-controls="pubNav" aria-label="<?php te('Menu'); ?>"><i class="fas fa-bars" aria-hidden="true"></i></button>
        </div>
    </header>
    <main id="main">
<?php
}

function public_page_end(): void
{
    $email = public_support_email();
    ?>
    </main>
    <footer class="pub-footer">
        <div class="pub-wrap">
            <div class="pub-footer-grid">
                <div class="pub-footer-brand">
                    <a class="pub-brand" href="/index.php"><i class="fas fa-heartbeat" aria-hidden="true"></i> MedXpert</a>
                    <p><?php te('Doctors, blood donors, medicine and ambulance requests for Bangladesh, in one account.'); ?></p>
                    <a class="pub-footer-sos" href="tel:<?php echo e(SITE_EMERGENCY_NUMBER); ?>"><i class="fas fa-phone" aria-hidden="true"></i> <?php te('Emergency'); ?>: <?php echo e(SITE_EMERGENCY_NUMBER); ?></a>
                </div>
                <div>
                    <h2><?php te('Services'); ?></h2>
                    <ul>
                        <li><a href="/services.php#doctors"><?php te('Doctor consultation'); ?></a></li>
                        <li><a href="/services.php#blood"><?php te('Blood bank'); ?></a></li>
                        <li><a href="/services.php#pharmacy"><?php te('Online pharmacy'); ?></a></li>
                        <li><a href="/services.php#ambulance"><?php te('Ambulance service'); ?></a></li>
                    </ul>
                </div>
                <div>
                    <h2><?php te('Company'); ?></h2>
                    <ul>
                        <li><a href="/about.php"><?php te('About'); ?></a></li>
                        <li><a href="/faq.php"><?php te('FAQ'); ?></a></li>
                        <li><a href="/contact.php"><?php te('Contact'); ?></a></li>
                    </ul>
                </div>
                <div>
                    <h2><?php te('Legal'); ?></h2>
                    <ul>
                        <li><a href="/privacy.php"><?php te('Privacy policy'); ?></a></li>
                        <li><a href="/terms.php"><?php te('Terms of use'); ?></a></li>
                        <?php if ($email !== ''): ?>
                            <li><a href="mailto:<?php echo e($email); ?>"><?php echo e($email); ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="pub-fine">
                <span>&copy; <?php echo date('Y'); ?> MedXpert</span>
                <span><?php te('MedXpert does not replace professional medical advice, diagnosis or treatment.'); ?></span>
            </div>
        </div>
    </footer>
    <script src="<?php echo e(asset_url('/assets/js/public.js')); ?>"></script>
</body>
</html>
<?php
}

/** Where messages from the contact page go. */
function public_support_email(): string
{
    return SITE_SUPPORT_EMAIL !== '' ? SITE_SUPPORT_EMAIL : '';
}

/** Live numbers and top doctors for the home page. Failures give empty values, never an error. */
function public_home_data(mysqli $conn): array
{
    $data = ['doctors' => 0, 'donors' => 0, 'medicines' => 0, 'top' => []];
    try {
        $data['doctors'] = (int)$conn->query("SELECT COUNT(*) c FROM doctors WHERE status = 'active'")->fetch_assoc()['c'];
        $data['donors'] = (int)$conn->query("SELECT COUNT(*) c FROM blood_donors WHERE available = 1")->fetch_assoc()['c'];
        $data['medicines'] = (int)$conn->query("SELECT COUNT(*) c FROM medicines WHERE status = 'available'")->fetch_assoc()['c'];
        $res = $conn->query("SELECT name, specialization, hospital, location, rating, image FROM doctors WHERE status = 'active' ORDER BY rating DESC, name ASC LIMIT 3");
        $data['top'] = $res->fetch_all(MYSQLI_ASSOC);
    } catch (Throwable $e) {
        error_log('Home page data skipped: ' . $e->getMessage());
    }
    return $data;
}
