<?php
/**
 * Static files. Fonts and icons are served from this site, so no visitor
 * address goes to a third party and the CSP can stay strict.
 */

/** URL of a static file with a version, so browsers load the new file after a change. */
function asset_url(string $path): string
{
    $file = dirname(__DIR__) . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** Print the stylesheet links for every page. */
function head_assets(): void
{
    foreach (['/assets/vendor/fonts/fonts.css', '/assets/vendor/fontawesome/css/fa.min.css', '/assets/css/style.css'] as $css) {
        echo '<link rel="stylesheet" href="' . e(asset_url($css)) . '">' . "\n    ";
    }
    echo '<link rel="preload" href="/assets/vendor/fonts/inter-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>' . "\n";
}
