<?php
/**
 * PA Editorial Engine uninstall handler.
 *
 * Cleans up all plugin data when the plugin is deleted via the admin UI.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove plugin options.
delete_option( 'pa_engine_settings' );
delete_option( 'pa_taxonomy_map' );

// Flush object cache group.
wp_cache_delete( 'pa_engine_settings', 'pa_editorial_engine' );
wp_cache_delete( 'pa_taxonomy_map', 'pa_editorial_engine' );

// Clean up post meta across all posts.
delete_post_meta_by_key( '_pa_parent_story_id' );
delete_post_meta_by_key( '_pa_editorial_stop' );
delete_post_meta_by_key( '_pa_editorial_stop_by' );
delete_post_meta_by_key( '_pa_is_correction' );
delete_post_meta_by_key( '_pa_correction_note' );
delete_post_meta_by_key( '_pa_auto_mapped_rules' );
