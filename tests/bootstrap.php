<?php
/**
 * PHPUnit bootstrap for lightweight unit tests.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        $key = strtolower($key);
        return (string) preg_replace('/[^a-z0-9_]/', '', $key);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string {
        return trim(strip_tags($value));
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
        return $default;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string {
        return $text;
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists(string $taxonomy): bool {
        return in_array($taxonomy, array('pa_brand', 'pa_color', 'pa_size'), true);
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

require_once dirname(__DIR__) . '/src/FilterSettings.php';
require_once dirname(__DIR__) . '/src/ShopFilters.php';
require_once dirname(__DIR__) . '/src/Analytics.php';
