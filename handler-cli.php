#!/usr/bin/php
<?php
/**
 * بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيم
 *
 * Created by Jim Yaghi
 * Date: 2021-02-28
 * Time: 15:22
 *
 */

namespace JY\BounceHandlerPlugin {

	use Exception;

	// turn on debugging and showing of errors so we can see on the frontend everything that is going on
	define( 'WP_DEBUG', true );
	define( 'WP_DEBUG_DISPLAY', true );


	if ( ! function_exists( 'locate_root' ) ) {
		/**
		 * @param null $dir
		 *
		 * @return string
		 * @throws Exception
		 */
		function locate_root($dir = null): string {
			if ( !$dir || !is_dir($dir) ) {
				if ( array_key_exists( 'DOCUMENT_ROOT', $_SERVER ) && $_SERVER['DOCUMENT_ROOT'] ) {
					$dir = $_SERVER['DOCUMENT_ROOT'];
				} else {
					$dir = __DIR__;
				}
			}
			$dir = rtrim( $dir, '/' );
			$levels  = 0;
			$found   = false;

			do {
				if ( file_exists( $dir . '/wp-load.php' ) ) {
					$found = true;
				}
			} while ( ! $found && 10 > $levels ++ && ( $dir = dirname( $dir ) ) );

			if ( ! $found ) {
				throw new Exception( "Application could not locate wp" );
			}

			return rtrim( $dir, '/' );
		}
	}
	$docRoot = locate_root( ( $argv[1] ?? '' ) ? $argv[1] : __DIR__ );


	require_once __DIR__ . '/vendor/autoload.php';
	require_once $docRoot . '/wp-load.php';
	$taskWorker = new SQSNotificationQueueWorker();
	$taskWorker->work();
}