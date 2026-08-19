<?php
/*
	Plugin Name: Export Themes
	Plugin URI: https://wordpress.org/plugins/wp-clone-template/
	Description: A simple plugin to export templates in a .zip file and then install them from the same package in other servers.
	Version: 3.0
	Requires at least: 6.0
	Requires PHP: 7.4
	Author: Sergio Milardovich
	Author URI: https://milardovich.com.ar/
	Text Domain: wp-clone-template
	License: GPLv2 or later
	License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPCT_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Capability required to export a theme.
 *
 * An export is the theme's full PHP source, so this is deliberately stricter
 * than the manage_options check used up to 2.12.
 */
function wpct_capability() {
	return apply_filters( 'wpct_capability', 'install_themes' );
}

/*
 * ---------------------------------------------------------------------------
 * Admin screen
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_menu', 'wpct_admin_menu' );
function wpct_admin_menu() {
	$hook = add_theme_page(
		__( 'Export Themes', 'wp-clone-template' ),
		__( 'Export', 'wp-clone-template' ),
		wpct_capability(),
		'clone_template',
		'wpct_render_page'
	);

	if ( $hook ) {
		// Runs before any admin markup, so the download can send its own headers.
		add_action( 'load-' . $hook, 'wpct_maybe_export' );
	}
}

function wpct_render_page() {
	include_once WPCT_PATH . 'views/export.php';
}

/**
 * List of exportable themes, keyed by stylesheet directory.
 */
function wpct_get_themes() {
	$themes = array();

	foreach ( wp_get_themes() as $stylesheet => $theme ) {
		$themes[ $stylesheet ] = $theme->display( 'Name' );
	}

	natcasesort( $themes );

	return $themes;
}

function wpct_show_error( $message ) {
	add_settings_error( 'wpct', 'wpct_error', $message, 'error' );
}

/*
 * ---------------------------------------------------------------------------
 * Export
 * ---------------------------------------------------------------------------
 */

/**
 * Handle the export request, if this page load is one.
 */
function wpct_maybe_export() {
	if ( ! isset( $_POST['export_template'] ) ) {
		return;
	}

	if ( ! current_user_can( wpct_capability() ) ) {
		wp_die( esc_html__( 'You are not allowed to export themes.', 'wp-clone-template' ), 403 );
	}

	// 2.12 accepted this POST from anywhere, with no nonce at all.
	check_admin_referer( 'wpct_export' );

	$requested = isset( $_POST['Templates'] ) ? sanitize_text_field( wp_unslash( $_POST['Templates'] ) ) : '';

	// Never trust the value as a path: it must be one of the installed themes.
	// 2.12 pasted it straight into a filesystem path, so "../../.." walked out
	// of the themes directory.
	$themes = wpct_get_themes();
	if ( '' === $requested || ! isset( $themes[ $requested ] ) ) {
		wpct_show_error( __( 'That theme does not exist.', 'wp-clone-template' ) );
		return;
	}

	$theme = wp_get_theme( $requested );
	if ( ! $theme->exists() ) {
		wpct_show_error( __( 'That theme does not exist.', 'wp-clone-template' ) );
		return;
	}

	$archive = wpct_build_archive( $theme );

	if ( is_wp_error( $archive ) ) {
		wpct_show_error( $archive->get_error_message() );
		return;
	}

	wpct_send_archive( $archive, $theme->get_stylesheet() );
}

/**
 * Files that never belong in a distributable theme package.
 */
function wpct_should_skip( $relative_path ) {
	$skip = false;

	foreach ( array( '.git', '.svn', '.hg', 'node_modules', '.DS_Store' ) as $needle ) {
		if ( $relative_path === $needle
			|| 0 === strpos( $relative_path, $needle . '/' )
			|| false !== strpos( $relative_path, '/' . $needle . '/' )
			|| substr( $relative_path, - ( strlen( $needle ) + 1 ) ) === '/' . $needle ) {
			$skip = true;
			break;
		}
	}

	/**
	 * Filters whether a file is left out of the export.
	 *
	 * @param bool   $skip
	 * @param string $relative_path Path relative to the theme directory.
	 */
	return apply_filters( 'wpct_should_skip_file', $skip, $relative_path );
}

/**
 * Zip the theme into a temporary file outside the web root.
 *
 * Up to 2.12 the archive was written into the plugin's own directory and the
 * browser was redirected to it, which left every exported theme downloadable by
 * anyone who knew the URL.
 *
 * @return string|WP_Error Absolute path to the archive.
 */
function wpct_build_archive( $theme ) {
	$source = untrailingslashit( $theme->get_stylesheet_directory() );

	if ( ! is_dir( $source ) || ! is_readable( $source ) ) {
		return new WP_Error( 'wpct_unreadable', __( 'The theme directory cannot be read.', 'wp-clone-template' ) );
	}

	$stylesheet  = $theme->get_stylesheet();
	$destination = trailingslashit( get_temp_dir() ) . wp_unique_filename( get_temp_dir(), $stylesheet . '.zip' );

	$files = wpct_collect_files( $source );
	if ( empty( $files ) ) {
		return new WP_Error( 'wpct_empty', __( 'The theme directory is empty.', 'wp-clone-template' ) );
	}

	if ( class_exists( 'ZipArchive' ) ) {
		$result = wpct_zip_with_ziparchive( $destination, $stylesheet, $files );
	} else {
		$result = wpct_zip_with_pclzip( $destination, $stylesheet, $source, $files );
	}

	if ( is_wp_error( $result ) ) {
		if ( file_exists( $destination ) ) {
			unlink( $destination );
		}
		return $result;
	}

	return $destination;
}

/**
 * Absolute paths of every file to include, keyed by their path inside the zip.
 */
function wpct_collect_files( $source ) {
	$files = array();

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		$absolute = $item->getPathname();
		$relative = ltrim( str_replace( $source, '', $absolute ), '/\\' );
		$relative = str_replace( '\\', '/', $relative );

		if ( '' === $relative || wpct_should_skip( $relative ) ) {
			continue;
		}

		if ( $item->isFile() && $item->isReadable() ) {
			$files[ $relative ] = $absolute;
		}
	}

	return $files;
}

function wpct_zip_with_ziparchive( $destination, $stylesheet, $files ) {
	$zip = new ZipArchive();

	if ( true !== $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		return new WP_Error( 'wpct_zip_open', __( 'The zip file could not be created.', 'wp-clone-template' ) );
	}

	$zip->addEmptyDir( $stylesheet );

	foreach ( $files as $relative => $absolute ) {
		$zip->addFile( $absolute, $stylesheet . '/' . $relative );
	}

	if ( ! $zip->close() ) {
		return new WP_Error( 'wpct_zip_write', __( 'The zip file could not be written.', 'wp-clone-template' ) );
	}

	return true;
}

/**
 * Fallback for the rare install without the zip extension.
 *
 * WordPress ships its own maintained copy of PclZip; the one bundled with this
 * plugin until 2.12 still used a PHP 4 constructor and died under PHP 8.
 */
function wpct_zip_with_pclzip( $destination, $stylesheet, $source, $files ) {
	require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

	$zip = new PclZip( $destination );

	$added = $zip->add(
		array_values( $files ),
		PCLZIP_OPT_REMOVE_PATH,
		dirname( $source ),
		PCLZIP_OPT_ADD_PATH,
		''
	);

	if ( 0 === $added ) {
		return new WP_Error( 'wpct_zip_write', $zip->errorInfo( true ) );
	}

	return true;
}

/**
 * Stream the archive to the browser and delete it.
 */
function wpct_send_archive( $path, $stylesheet ) {
	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $stylesheet . '.zip"' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );

	// Nothing else may end up inside the zip.
	while ( ob_get_level() ) {
		ob_end_clean();
	}

	readfile( $path );
	unlink( $path );

	exit;
}

/*
 * ---------------------------------------------------------------------------
 * Activation
 * ---------------------------------------------------------------------------
 */

register_activation_hook( __FILE__, 'wpct_activate' );
function wpct_activate() {
	// Versions up to 2.12 left exported themes in a world-readable directory
	// inside the plugin. Clean it up on upgrade.
	$legacy = WPCT_PATH . 'templates';

	if ( ! is_dir( $legacy ) ) {
		return;
	}

	foreach ( (array) glob( $legacy . '/*.zip' ) as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}

	@rmdir( $legacy );
}
