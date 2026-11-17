<?php
/**
 * PSR-4 autoloader.
 *
 * Maps one or more namespace prefixes onto source directories. The plugin's
 * own namespace maps to src/; the merged Pro code keeps its historical
 * ClandevsSmartCatalogFiltersPro namespace and maps onto src/Pro.
 *
 * @package ClandevsSmartCatalogFilters
 */

namespace ClandevsSmartCatalogFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads plugin classes using PSR-4 conventions.
 */
final class Autoloader {
	/**
	 * Default namespace prefix.
	 *
	 * @var string
	 */
	private const PREFIX = __NAMESPACE__ . '\\';

	/**
	 * Registered namespace prefixes mapped to base directories.
	 *
	 * @var array<string, string>
	 */
	private static $base_dirs = array();

	/**
	 * Whether the spl autoloader callback has been registered.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Register a namespace prefix.
	 *
	 * Without a prefix, the plugin's own namespace maps onto the given
	 * directory, matching the historical single-mapping behaviour.
	 *
	 * @param string      $base_dir Base directory for namespaced classes.
	 * @param string|null $prefix   Namespace prefix ending with a backslash.
	 * @return void
	 */
	public static function register( string $base_dir, ?string $prefix = null ): void {
		if ( null === $prefix || '' === $prefix ) {
			$prefix = self::PREFIX;
		}

		self::$base_dirs[ $prefix ] = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;

		if ( ! self::$registered ) {
			spl_autoload_register( array( __CLASS__, 'load' ) );
			self::$registered = true;
		}
	}

	/**
	 * Load class file.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function load( string $class_name ): void {
		foreach ( self::$base_dirs as $prefix => $base_dir ) {
			$prefix_length = strlen( $prefix );

			if ( 0 !== strncmp( $prefix, $class_name, $prefix_length ) ) {
				continue;
			}

			$relative_class = substr( $class_name, $prefix_length );
			$file           = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
}
