<?php

/**
 * Plugin Name: Pagelock
 * Plugin URI: https://mattruetz.com/
 * Description: A WordPress plugin to password-protect specific pages with custom password locks.
 * Version: 1.2.1
 * Author: Matt Ruetz
 * License: GPL v2 or later
 * Text Domain: pagelock
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PAGELOCK_VERSION', '1.1.0');
define('PAGELOCK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PAGELOCK_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once PAGELOCK_PLUGIN_DIR . 'includes/class-pagelock.php';
require_once PAGELOCK_PLUGIN_DIR . 'includes/class-pagelock-admin.php';
require_once PAGELOCK_PLUGIN_DIR . 'includes/class-pagelock-frontend.php';
require_once PAGELOCK_PLUGIN_DIR . 'includes/class-pagelock-database.php';

// Initialize the plugin
function pagelock_init()
{
    $pagelock = new Pagelock();
    $pagelock->run();
}
add_action('plugins_loaded', 'pagelock_init');

// Activation hook
register_activation_hook(__FILE__, 'pagelock_activate');
function pagelock_activate()
{
    Pagelock_Database::create_tables();

    // Set default options
    add_option('pagelock_version', PAGELOCK_VERSION);

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'pagelock_deactivate');
function pagelock_deactivate()
{
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'pagelock_uninstall');
function pagelock_uninstall()
{
    // Remove database tables
    Pagelock_Database::drop_tables();

    // Remove options
    delete_option('pagelock_version');
}
