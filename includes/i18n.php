<?php
declare(strict_types=1);

/** @return array<string,string> */
function sodium_supported_languages(): array
{
    return [
        'fr' => 'Français',
        'en' => 'English',
        'de' => 'Deutsch',
        'it' => 'Italiano',
        'es' => 'Español',
        'pt' => 'Português',
    ];
}

function sodium_normalize_locale(?string $locale): string
{
    $locale = strtolower(substr(trim((string) $locale), 0, 2));
    return array_key_exists($locale, sodium_supported_languages()) ? $locale : 'fr';
}

function sodium_locale(): string
{
    $user = function_exists('current_user') ? current_user() : null;
    if (is_array($user) && !empty($user['language'])) {
        return sodium_normalize_locale((string) $user['language']);
    }
    if (!empty($_SESSION['install_language'])) {
        return sodium_normalize_locale((string) $_SESSION['install_language']);
    }
    return sodium_normalize_locale(defined('SODIUM_DEFAULT_LOCALE') ? (string) SODIUM_DEFAULT_LOCALE : 'fr');
}

/** @return array<string,string> */
function sodium_translations(?string $locale = null): array
{
    static $catalogues = [];
    $locale = sodium_normalize_locale($locale ?? sodium_locale());
    if (!isset($catalogues[$locale])) {
        $fallback = require dirname(__DIR__) . '/languages/fr.php';
        $translated = $locale === 'fr' ? [] : require dirname(__DIR__) . '/languages/' . $locale . '.php';
        $catalogues[$locale] = array_replace($fallback, $translated);
    }
    return $catalogues[$locale];
}

function sodium_t(string $key, array $replace = [], ?string $locale = null): string
{
    $catalogue = sodium_translations($locale);
    $text = (string) ($catalogue[$key] ?? $key);
    foreach ($replace as $name => $value) {
        $text = str_replace(':' . $name, (string) $value, $text);
    }
    return $text;
}

/** @return array<string,string> French UI text mapped to the active language. */
function sodium_browser_translation_map(): array
{
    if (sodium_locale() === 'fr') return [];
    $source = sodium_translations('fr');
    $target = sodium_translations();
    $map = [];
    foreach ($source as $key => $french) {
        if ($french !== '' && isset($target[$key]) && $target[$key] !== $french && !str_contains($french, ':')) {
            $map[$french] = $target[$key];
        }
    }
    return $map;
}
