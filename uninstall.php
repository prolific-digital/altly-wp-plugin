<?php
/**
 * Uninstall cleanup for Altly - AI Text Generator.
 *
 * Removes only this plugin's own settings (the API license key and default
 * mode) and its pull-sync cron event. Image alt text and the `_altly_queued` /
 * `_altly_managed` attachment meta are kept: they are content, not settings.
 *
 * @package Altly
 */

defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete Altly's options and cron event on the current site.
 */
function altly_uninstall_site() {
	delete_option( 'altly_license_key' );
	delete_option( 'altly_default_mode' );
	wp_clear_scheduled_hook( 'altly_sync_results_cron' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $altly_site_id ) {
		switch_to_blog( $altly_site_id );
		altly_uninstall_site();
		restore_current_blog();
	}
} else {
	altly_uninstall_site();
}
