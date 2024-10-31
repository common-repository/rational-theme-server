<?php
// Make sure user came via redirect/rewrite
if ( empty( $_SERVER['REDIRECT_STATUS'] ) || intval( $_SERVER['REDIRECT_STATUS'] ) !== 200 ) exit;

// Make sure there's a referrer or no direct access:
if ( empty( $_SERVER['HTTP_REFERER'] ) ) exit;

$theme = $_REQUEST['theme'];
$preg_theme = preg_quote( $theme );

// Make sure there's a redirect URL and it contains the requested theme name
if ( empty( $_SERVER['REDIRECT_URL'] ) || !preg_match( "/{$preg_theme}/", $_SERVER['REDIRECT_URL']) ) exit;

$wp_themes_folder = rts_decode_path( $_REQUEST['folder'] );
if ( !is_dir( $wp_themes_folder ) ) exit;

$wp_uploads_folder = rts_decode_path( $_REQUEST['uploads'] );
if ( !is_dir( $wp_uploads_folder ) ) exit;

$theme_zip_filename = "{$theme}.zip";
$theme_source_path = $wp_themes_folder . $theme;
if ( !is_dir( $theme_source_path ) ) exit;

$rts_upload_path = $wp_uploads_folder . 'rts-themes/';
if ( !file_exists( $rts_upload_path ) ) {
	mkdir( $rts_upload_path );
}

$theme_destination_path = $rts_upload_path . $theme_zip_filename;
if ( !file_exists( $theme_destination_path ) ) {
	rts_zip_folder( $theme_source_path, $theme_destination_path );
}

header( 'Pragma: public' );
header( 'Expires: 0' );
header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
header( 'Cache-Control: public' );
header( 'Content-Description: File Transfer' );
header( 'Content-type: application/octet-stream' );
header( 'Content-Disposition: attachment; filename="' . $theme_zip_filename . '"' );
header( 'Content-Transfer-Encoding: binary' );
header( 'Content-Length: ' . filesize( $theme_destination_path ) );
ob_end_flush();
@readfile( $theme_destination_path );

/**
 * Builds a zip file from a directory (recursive)
 *
 * @param	string	$source			Source folder path
 * @param	string	$destination	Destination file path
 *
 * @return	boolean					True on success, false on failure
 */
function rts_zip_folder( $source, $destination ) {
	if ( !extension_loaded( 'zip' ) || !file_exists( $source ) ) {
		return false;
	}
	
	$zip = new ZipArchive();
	if ( !$zip->open( $destination, ZIPARCHIVE::CREATE ) ) {
		return false;
	}
	
	$source = str_replace( '\\', '/', realpath( $source ) );
	
	if ( is_dir( $source ) === true ) {
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source ), RecursiveIteratorIterator::SELF_FIRST );

		foreach ( $files as $file ) {
			$file = str_replace( '\\', '/', $file );

			// Ignore "." and ".." folders
			if ( in_array( substr( $file, strrpos( $file, '/' ) + 1 ), array( '.', '..' ) ) ) {
				continue;
			}

			$file = realpath( $file );

			if ( is_dir( $file ) === true ) {
				$zip->addEmptyDir( str_replace( $source . '/', '', $file . '/' ) );
			} else if ( is_file($file) === true ) {
				$zip->addFromString( str_replace( $source . '/', '', $file ), file_get_contents( $file ) );
			}
		}
	} else if ( is_file( $source ) === true ) {
		$zip->addFromString( basename( $source ), file_get_contents( $source ) );
	}

	return $zip->close();
}

function rts_decode_path( $path ) {
	return urldecode( strtr( $path, "'", '%' ) );
}