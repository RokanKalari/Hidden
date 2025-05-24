<?php
/**
 * LANGUAGE CONFIGURATION
 * File: config/languages.php
 * Purpose: Multi-language configuration and settings
 */

// Available languages
$available_languages = [
    'en' => [
        'name' => 'English',
        'native_name' => 'English',
        'flag' => 'us',
        'direction' => 'ltr',
        'date_format' => 'Y-m-d',
        'currency_format' => 'left'
    ],
    'ku' => [
        'name' => 'Kurdish',
        'native_name' => 'کوردی',
        'flag' => 'krd',
        'direction' => 'rtl',
        'date_format' => 'Y/m/d',
        'currency_format' => 'right'
    ],
    'ar' => [
        'name' => 'Arabic',
        'native_name' => 'العربية',
        'flag' => 'ar',
        'direction' => 'rtl',
        'date_format' => 'd/m/Y',
        'currency_format' => 'right'
    ]
];

/**
 * Get language configuration
 */
function getLanguageConfig($lang_code) {
    global $available_languages;
    return $available_languages[$lang_code] ?? $available_languages['en'];
}

/**
 * Get all available languages
 */
function getAvailableLanguages() {
    global $available_languages;
    return $available_languages;
}

/**
 * Check if language is RTL
 */
function isRTL($lang_code) {
    $config = getLanguageConfig($lang_code);
    return $config['direction'] === 'rtl';
}
?>