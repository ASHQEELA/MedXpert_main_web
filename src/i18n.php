<?php
/**
 * Language support. The page text is English in the code.
 * t('English text') returns the Bangla text from lang/bn.php when the person chose Bangla.
 * A text with no entry shows in English.
 */

const SUPPORTED_LANGUAGES = ['en', 'bn'];

function lang_code(): string
{
    $code = $_COOKIE['mx_lang'] ?? 'en';
    return (is_string($code) && in_array($code, SUPPORTED_LANGUAGES, true)) ? $code : 'en';
}

function t(string $text): string
{
    static $bn = null;
    if (lang_code() !== 'bn') {
        return $text;
    }
    if ($bn === null) {
        $bn = require dirname(__DIR__) . '/lang/bn.php';
    }
    return $bn[$text] ?? $text;
}

/** Print translated text, escaped. */
function te(string $text): void
{
    echo e(t($text));
}
