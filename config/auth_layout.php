<?php
/** Shared frame for the pages people see before they log in. */
function auth_page_start(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="<?php echo e(lang_code()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?php echo e($title); ?> - MedXpert</title>
    <?php head_assets(); ?>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="auth-header">
                <div class="auth-logo">
                    <h1><i class="fas fa-heartbeat"></i> MedXpert</h1>
                    <p><?php echo e($title); ?></p>
                </div>
            </div>
<?php
}

function auth_page_end(bool $backLink = true): void
{
    if ($backLink) {
        echo '<p class="auth-footer-link"><a href="/index.php">Back to login</a></p>';
    }
    ?>
        </div>
    </div>
</body>
</html>
<?php
}

function auth_message(?array $flash): void
{
    if ($flash) {
        echo '<div class="alert alert-' . ($flash['type'] === 'success' ? 'success' : 'error') . '" role="status">' . e($flash['message']) . '</div>';
    }
}
