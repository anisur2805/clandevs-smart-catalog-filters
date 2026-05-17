<?php
/**
 * PSR-4 autoloader for Pro add-on.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Autoloader {
	private const PREFIX = __NAMESPACE__ . '\\';

	private static $base_dir = '';

	public static function register( string $base_dir ): void {
		self::$base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

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
