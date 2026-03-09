<?php
/**
 * PSR-4 autoloader.
 *
 * @package WooFilters
 */

namespace WooFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads plugin classes using PSR-4 conventions.
 */
final class Autoloader {
	/**
	 * Namespace prefix.
	 *
	 * @var string
	 */
	private const PREFIX = __NAMESPACE__ . '\\';

	/**
	 * Base directory.
	 *
	 * @var string
	 */
	private static $base_dir = '';

	/**
	 * Register autoloader callback.
	 *
	 * @param string $base_dir Base directory for namespaced classes.
	 * @return void
	 */
	public static function register( string $base_dir ): void {
		self::$base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load class file.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function load( string $class_name ): void {
		$prefix_length = strlen( self::PREFIX );
		if ( 0 !== strncmp( self::PREFIX, $class_name, $prefix_length ) ) {
			return;
		}

		$relative_class = substr( $class_name, $prefix_length );
		$file           = self::$base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
