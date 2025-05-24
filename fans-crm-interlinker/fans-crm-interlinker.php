<?php
/**
 * Plugin Name: InterLinker
 * Description: Fans-CRM Analyzing the internal linking of the site
 * Version: 1.0
 * Author: Oleg Medinskiy
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'INTERLINKER_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'INTERLINKER_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'INTERLINKER_UPLOADS_DIR_PATH', wp_get_upload_dir()['basedir'] );
define( 'INTERLINKER_UPLOADS_DIR_URL', wp_get_upload_dir()['baseurl'] );

define( 'INTERLINKER_GLOBAL_COUNTER_OPTION', 'interlinker_global_count_anchors' );
define( 'INTERLINKER_TEMPLATES_COUNTER_OPTION', 'interlinker_templates_counter' );

require_once INTERLINKER_PLUGIN_DIR_PATH . 'inc/interlinker_helper.php';
require_once INTERLINKER_PLUGIN_DIR_PATH . 'inc/interlinker_core.php';

if( !function_exists("interlinker_create_table") ){
	/**
	 * Creates the interlinker_keys table if it does not exist.
	 */
	function interlinker_create_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'interlinker_keys';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "
		CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`key` TEXT NOT NULL,
			id_post BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			FULLTEXT KEY key_ft (`key`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
		";

		dbDelta($sql);
	}
	register_activation_hook(__FILE__, 'interlinker_create_table');
}

/**
 * Begins execution of the plugin.
 */
if( class_exists("InterLinker_Core") ){
	new InterLinker_Core();
}





