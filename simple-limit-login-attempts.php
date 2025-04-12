<?php
/**
 * Plugin Name: Simple Limit Login Attempts
 * Plugin URI: https://exarth.com
 * Plugin Description: A simple plugin to limit login attempts and protect against brute force attacks.
 * Version: 1.0.0
 * Author: Exarth
 * Author URI: https://exarth.com
 * License: MIT
 * License URI: https://choosealicense.com/licenses/mit/
 * Text Domain: simple-limit-login-attempts
 */

// SECURITY CHECK
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// DEFINE CONSTANT
define( 'SLLA_VERSION', '1.0.0' );
define( 'SLLA_PLUGIN_ID', 'simple-limit-login-attempts' );
define( 'SLLA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SLLA_PLUGIN_URL', plugins_url( '', __FILE__ ) );

// INCLUDING FILES
require_once SLLA_PLUGIN_DIR . 'includes/class-slla-core.php';

// INITIALIZE THE PLUGIN
function slla_init() {
    $slla_core = new SLLA_Core();
}

// DEACTIVATION HOOK
register_deactivation_hook( __FILE__, 'slla_remove_activation_notice_flag' );

function slla_remove_activation_notice_flag() {
    delete_option( 'slla_plugin_activated_notice' );
}

// CREATE CUSTOM DATABASE TABLE ON PLUGIN ACTIVATION
register_activation_hook( __FILE__, 'slla_create_logs_table' );

function slla_create_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'slla_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        type VARCHAR(20) NOT NULL,
        ip VARCHAR(100) NOT NULL,
        username VARCHAR(255) DEFAULT '',
        time DATETIME NOT NULL,
        reason TEXT DEFAULT '',
        PRIMARY KEY (id),
        INDEX idx_type (type),
        INDEX idx_ip (ip)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Migrate existing successful logins
    $successful_logs = get_option( 'slla_successful_logins', array() );
    if ( ! empty( $successful_logs ) ) {
        foreach ( $successful_logs as $log ) {
            $wpdb->insert(
                $table_name,
                array(
                    'type' => 'successful_login',
                    'ip' => sanitize_text_field( $log['ip'] ),
                    'username' => sanitize_text_field( $log['username'] ),
                    'time' => $log['time'],
                ),
                array( '%s', '%s', '%s', '%s' )
            );
        }
        delete_option( 'slla_successful_logins' );
    }

    // Migrate existing lockout logs
    $lockout_logs = get_option( 'slla_lockout_logs', array() );
    if ( ! empty( $lockout_logs ) ) {
        foreach ( $lockout_logs as $log ) {
            $wpdb->insert(
                $table_name,
                array(
                    'type' => 'lockout',
                    'ip' => sanitize_text_field( $log['ip'] ),
                    'time' => $log['time'],
                    'reason' => sanitize_text_field( $log['reason'] ),
                ),
                array( '%s', '%s', '%s', '%s' )
            );
        }
        delete_option( 'slla_lockout_logs' );
    }
}

add_action( 'plugins_loaded', 'slla_init' );