<?php

/**
 * Main Pagelock Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Pagelock
{

    private $admin;
    private $frontend;

    public function __construct()
    {
        // Initialize components
        $this->admin = new Pagelock_Admin();
        $this->frontend = new Pagelock_Frontend();
    }

    public function run()
    {
        // Add hooks
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Initialize admin and frontend
        $this->admin->init();
        $this->frontend->init();
    }

    public function init()
    {
        // Load text domain for internationalization
        load_plugin_textdomain('pagelock', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function enqueue_frontend_scripts()
    {
        wp_enqueue_style(
            'pagelock-frontend',
            PAGELOCK_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            PAGELOCK_VERSION
        );

        wp_enqueue_script(
            'pagelock-frontend',
            PAGELOCK_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            PAGELOCK_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('pagelock-frontend', 'pagelock_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pagelock_nonce')
        ));
    }

    public function enqueue_admin_scripts($hook)
    {
        // Only load on our plugin pages
        if (strpos($hook, 'pagelock') !== false) {
            wp_enqueue_style(
                'pagelock-admin',
                PAGELOCK_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                PAGELOCK_VERSION
            );

            wp_enqueue_script(
                'pagelock-admin',
                PAGELOCK_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                PAGELOCK_VERSION,
                true
            );
        }
    }
}
