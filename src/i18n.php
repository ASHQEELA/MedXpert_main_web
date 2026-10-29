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

/** All Bangla text: lang/bn.php plus lang/bn_ui.php. */
function bn_dictionary(): array
{
    static $dict = null;
    if ($dict === null) {
        $dict = array_merge(require dirname(__DIR__) . '/lang/bn.php', require dirname(__DIR__) . '/lang/bn_ui.php');
    }
    return $dict;
}

function t(string $text): string
{
    if (lang_code() !== 'bn') {
        return $text;
    }
    return bn_dictionary()[$text] ?? $text;
}

/** Print translated text, escaped. */
function te(string $text): void
{
    echo e(t($text));
}

/* ------------------------------------------------------------------
 * Whole-page translation for Bangla.
 * Text that has no te() call still gets translated here: every page, form hint,
 * server message and status label that matches an entry in the Bangla list.
 * ------------------------------------------------------------------ */
function i18n_digits(string $s): string
{
    return strtr($s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
}

const I18N_MONTHS = ['Jan' => 'জানুয়ারি', 'Feb' => 'ফেব্রুয়ারি', 'Mar' => 'মার্চ', 'Apr' => 'এপ্রিল', 'May' => 'মে', 'Jun' => 'জুন',
    'Jul' => 'জুলাই', 'Aug' => 'আগস্ট', 'Sep' => 'সেপ্টেম্বর', 'Oct' => 'অক্টোবর', 'Nov' => 'নভেম্বর', 'Dec' => 'ডিসেম্বর'];
const I18N_DAYS = ['Sun' => 'রবি', 'Mon' => 'সোম', 'Tue' => 'মঙ্গল', 'Wed' => 'বুধ', 'Thu' => 'বৃহস্পতি', 'Fri' => 'শুক্র', 'Sat' => 'শনি'];

/** Bangla for one piece of text, or null when there is no translation. */
function i18n_text(string $text): ?string
{
    $dict = bn_dictionary();
    if (isset($dict[$text])) {
        return $dict[$text];
    }
    $m = [];
    if (preg_match('/^(\d+) years? experience$/', $text, $m)) {
        return i18n_digits($m[1]) . ' বছরের অভিজ্ঞতা';
    }
    if (preg_match('/^(\d+) years old$/', $text, $m)) {
        return 'বয়স ' . i18n_digits($m[1]) . ' বছর';
    }
    if (preg_match('/^Last donated (\d{4}-\d{2}-\d{2})$/', $text, $m)) {
        return 'সর্বশেষ রক্তদান: ' . i18n_digits($m[1]);
    }
    if (preg_match('/^(\d+) recorded actions?$/', $text, $m)) {
        return i18n_digits($m[1]) . 'টি নথিভুক্ত কাজ';
    }
    if (preg_match('/^([A-Z][a-z]{2}) (\d{1,2}), (\d{4})(?: (\d{1,2}:\d{2})(?: ?(AM|PM))?)?$/', $text, $m) && isset(I18N_MONTHS[$m[1]])) {
        $out = i18n_digits($m[2]) . ' ' . I18N_MONTHS[$m[1]] . ', ' . i18n_digits($m[3]);
        if (!empty($m[4])) {
            $out .= ' ' . i18n_digits($m[4]) . (empty($m[5]) ? '' : ($m[5] === 'AM' ? ' এএম' : ' পিএম'));
        }
        return $out;
    }
    if (preg_match('/^(\d{1,2}) ([A-Z][a-z]{2})(?:: (\d+) (orders|appointments))?$/', $text, $m) && isset(I18N_MONTHS[$m[2]])) {
        $out = i18n_digits($m[1]) . ' ' . I18N_MONTHS[$m[2]];
        if (isset($m[4])) {
            $out .= ': ' . i18n_digits($m[3]) . 'টি ' . ($m[4] === 'orders' ? 'অর্ডার' : 'অ্যাপয়েন্টমেন্ট');
        }
        return $out;
    }
    if (preg_match('/^(?:Sun|Mon|Tue|Wed|Thu|Fri|Sat)(?:-(?:Sun|Mon|Tue|Wed|Thu|Fri|Sat))?, \d/', $text)) {
        $out = strtr($text, I18N_DAYS);
        $out = str_replace([' AM', ' PM'], [' এএম', ' পিএম'], $out);
        return i18n_digits($out);
    }
    if (preg_match('/^(.+) added to your cart$/', $text, $m)) {
        return $m[1] . ' কার্টে যোগ হয়েছে';
    }
    return null;
}

/** Translate a page or a JSON reply. Anything else passes through untouched. */
function i18n_output_filter(string $buffer): string
{
    $head = ltrim(substr($buffer, 0, 64));
    if ($head === '') {
        return $buffer;
    }
    if ($head[0] === '{') {
        if (strlen($buffer) > 20000) {
            return $buffer;
        }
        $data = json_decode($buffer, true);
        if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
            $data['message'] = i18n_text($data['message']) ?? $data['message'];
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $buffer;
        }
        return $buffer;
    }
    if ($head[0] !== '<' || strlen($buffer) > 4000000) {
        return $buffer;
    }
    $parts = preg_split('~(<script\b.*?</script>|<style\b.*?</style>|<textarea\b.*?</textarea>|<!--.*?-->)~is', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $buffer;
    }
    $translate = static function (string $raw): string {
        $plain = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $bn = i18n_text($plain);
        return $bn === null ? $raw : htmlspecialchars($bn, ENT_QUOTES, 'UTF-8');
    };
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1 && stripos($part, '<textarea') === 0) {
            $parts[$i] = preg_replace_callback('~\\b(placeholder|title|aria-label)="([^"]+)"~', static function (array $m) use ($translate): string {
                return $m[1] . '="' . $translate($m[2]) . '"';
            }, $part) ?? $part;
            continue;
        }
        if ($i % 2 === 1 || $part === '') {
            continue;
        }
        $part = preg_replace_callback('~>([^<>]+)(?=<)~', static function (array $m) use ($translate): string {
            $text = $m[1];
            $trim = trim($text);
            if ($trim === '' || !preg_match('/[A-Za-z]{2}/', $trim)) {
                return '>' . $text;
            }
            $lead = substr($text, 0, strlen($text) - strlen(ltrim($text)));
            $tail = substr($text, strlen(rtrim($text)));
            return '>' . $lead . $translate($trim) . $tail;
        }, $part) ?? $part;
        $part = preg_replace_callback('~\b(placeholder|title|aria-label|alt)="([^"]+)"~', static function (array $m) use ($translate): string {
            return $m[1] . '="' . $translate($m[2]) . '"';
        }, $part) ?? $part;
        $part = preg_replace_callback('~\bon(click|submit)="([^"]*)"~', static function (array $m): string {
            $js = preg_replace_callback("~'([^'\\\\]{4,})'~", static function (array $q): string {
                $bn = i18n_text(html_entity_decode($q[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                return $bn === null ? $q[0] : "'" . str_replace("'", "\\'", htmlspecialchars($bn, ENT_QUOTES, 'UTF-8')) . "'";
            }, $m[2]) ?? $m[2];
            return 'on' . $m[1] . '="' . $js . '"';
        }, $part) ?? $part;
        $parts[$i] = $part;
    }
    return implode('', $parts);
}
