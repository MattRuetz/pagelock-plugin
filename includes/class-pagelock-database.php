<?php

/**
 * Pagelock Database Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Pagelock_Database
{

    /**
     * Create database tables
     */
    public static function create_tables()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pagelock_locks';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            password varchar(255) NOT NULL,
            pages text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Set default style settings
        self::set_default_settings();
    }

    /**
     * Drop database tables
     */
    public static function drop_tables()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pagelock_locks';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");

        // Remove style settings
        delete_option('pagelock_settings');
    }

    /**
     * Set default style settings
     */
    public static function set_default_settings()
    {
        $default_settings = array(
            'icon_image' => '',
            'icon_image_id' => '',
            'icon_background_color1' => '#A5AB52',
            'icon_background_color2' => '#566246',
            'button_color' => '#ED9A25',
            'form_background_color' => 'rgba(255, 255, 255, 0.95)',
            'heading_text_color' => '#6C0E23',
            'body_text_color' => '#46351D',
            'field_design' => 'default',
            'background_type' => 'curve',
            'background_solid_color' => '#F8E7CE',
            'background_curve_color1' => '#F8E7CE',
            'background_curve_color2' => '#F8E7CE',
            'background_image' => '',
            'background_image_id' => '',
            'background_image_overlay' => true,
            'background_image_overlay_color' => 'rgba(248, 231, 206, 0.8)',
            'background_image_blur' => 0,
            'form_border_radius' => 24,
            'field_border_radius' => 16,
            'button_border_radius' => 16
        );

        if (!get_option('pagelock_settings')) {
            add_option('pagelock_settings', $default_settings);
        }
    }

    /**
     * Get style settings
     */
    public static function get_settings()
    {
        $settings = get_option('pagelock_settings');
        if (!$settings) {
            self::set_default_settings();
            $settings = get_option('pagelock_settings');
        }
        return $settings;
    }

    /**
     * Update style settings
     */
    public static function update_settings($new_settings)
    {
        $current_settings = self::get_settings();
        $updated_settings = array_merge($current_settings, $new_settings);
        return update_option('pagelock_settings', $updated_settings);
    }

    /**
     * Get all page locks
     */
    public static function get_locks()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pagelock_locks';
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    }

    /**
     * Get a specific lock by ID
     */
    public static function get_lock($id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pagelock_locks';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }

    /**
     * Get lock for a specific page
     */
    public static function get_lock_for_page($page_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pagelock_locks';
        $locks = $wpdb->get_results("SELECT * FROM $table_name");

        foreach ($locks as $lock) {
            $pages = maybe_unserialize($lock->pages);
            if (is_array($pages) && in_array($page_id, $pages)) {
                return $lock;
            }
        }

        return null;
    }

    /**
     * Insert a new lock
     */
    public static function insert_lock($data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pagelock_locks';

        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => sanitize_text_field($data['name']),
                'password' => wp_hash_password($data['password']),
                'pages' => maybe_serialize($data['pages'])
            ),
            array('%s', '%s', '%s')
        );

        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Update an existing lock
     */
    public static function update_lock($id, $data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pagelock_locks';

        $update_data = array(
            'name' => sanitize_text_field($data['name']),
            'pages' => maybe_serialize($data['pages'])
        );

        // Only update password if provided
        if (!empty($data['password'])) {
            $update_data['password'] = wp_hash_password($data['password']);
        }

        return $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Delete a lock
     */
    public static function delete_lock($id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pagelock_locks';
        return $wpdb->delete($table_name, array('id' => $id), array('%d'));
    }

    /**
     * Verify password for a lock
     */
    public static function verify_password($lock_id, $password)
    {
        $lock = self::get_lock($lock_id);
        if (!$lock) {
            return false;
        }

        return wp_check_password($password, $lock->password);
    }
}
