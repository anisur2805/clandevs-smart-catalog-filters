<?php
/**
 * PHPUnit bootstrap for lightweight unit tests.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

// The merged Pro classes keep their historical constants; the tests for
// Presets, SEO URLs and Advanced Analytics exercise those classes.
if (!defined('CSCFP_VERSION')) {
    define('CSCFP_VERSION', 'test');
}

if (!defined('CSCFP_PLUGIN_URL')) {
    define('CSCFP_PLUGIN_URL', 'https://example.test/wp-content/plugins/pro/');
}

// Simple in-memory option store so tests can read and write options.
if (!isset($GLOBALS['__cscf_test_options'])) {
    $GLOBALS['__cscf_test_options'] = array();
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        $key = strtolower($key);
        return (string) preg_replace('/[^a-z0-9_]/', '', $key);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string {
        return trim(wp_strip_all_tags($value));
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title(string $value): string {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9_-]+/', '-', $value);
        return trim($value, '-');
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return stripslashes((string) $value);
    }
}

if (!function_exists('wc_format_decimal')) {
    function wc_format_decimal(string $value): string {
        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            return '';
        }
        return (string) $normalized;
    }
}

if (!function_exists('absint')) {
    function absint(string $value): int {
        return abs((int) $value);
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        if (array_key_exists($option, $GLOBALS['__cscf_test_options'])) {
            return $GLOBALS['__cscf_test_options'][$option];
        }
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value, $autoload = null): bool {
        $GLOBALS['__cscf_test_options'][$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool {
        unset($GLOBALS['__cscf_test_options'][$option]);
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $transient) {
        return get_option('_transient_' . $transient, false);
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $transient, $value, int $expiration = 0): bool {
        return update_option('_transient_' . $transient, $value);
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $transient): bool {
        return delete_option('_transient_' . $transient);
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string {
        return $text;
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists(string $taxonomy): bool {
        return in_array($taxonomy, array('pa_brand', 'pa_color', 'pa_size', 'product_cat'), true);
    }
}

if (!function_exists('wc_get_attribute_taxonomies')) {
    function wc_get_attribute_taxonomies(): array {
        return array(
            (object) array('attribute_name' => 'brand'),
            (object) array('attribute_name' => 'color'),
            (object) array('attribute_name' => 'size'),
        );
    }
}

if (!function_exists('wc_attribute_taxonomy_name')) {
    function wc_attribute_taxonomy_name(string $attribute): string {
        return 'pa_' . sanitize_key($attribute);
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit(string $value): string {
        return rtrim($value, "/\\");
    }
}

if (!function_exists('wc_get_product_ids_on_sale')) {
    function wc_get_product_ids_on_sale(): array {
        return array(10, 20);
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $value, bool $remove_breaks = false): string {
        $value = preg_replace('/<[^>]*>/', '', $value);
        if ($remove_breaks) {
            $value = preg_replace('/[\r\n\t ]+/', ' ', $value);
        }
        return trim($value);
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color(string $color): ?string {
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            return $color;
        }
        return null;
    }
}

if (!function_exists('wp_parse_str')) {
    function wp_parse_str(string $input, &$result): void {
        parse_str($input, $result);
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- this stub IS the wp_parse_url implementation.
        return parse_url($url, $component);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $value): string {
        return rtrim($value, "/\\") . '/';
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = '/'): string {
        return 'https://example.test' . $path;
    }
}

if (!function_exists('wc_get_page_permalink')) {
    function wc_get_page_permalink(string $page): string {
        return 'https://example.test/shop/';
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value) {
        return $value;
    }
}

require_once dirname(__DIR__) . '/src/License.php';
require_once dirname(__DIR__) . '/src/FilterSettings.php';
require_once dirname(__DIR__) . '/src/StyleSettings.php';
require_once dirname(__DIR__) . '/src/ShopFilters.php';
require_once dirname(__DIR__) . '/src/Analytics.php';
require_once dirname(__DIR__) . '/src/ReviewPrompt.php';

// The merged Pro classes under test (they keep the Pro namespace).
require_once dirname(__DIR__) . '/src/Pro/DateRangeFilter.php';
require_once dirname(__DIR__) . '/src/Pro/AdvancedAnalytics.php';
require_once dirname(__DIR__) . '/src/Pro/SEOFriendlyURLs.php';
require_once dirname(__DIR__) . '/src/Pro/Presets.php';
