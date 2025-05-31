<?php

/**
 * Pagelock Admin Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Pagelock_Admin
{

    public function init()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_pagelock_save_lock', array($this, 'handle_save_lock'));
        add_action('wp_ajax_pagelock_delete_lock', array($this, 'handle_delete_lock'));
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('Pagelock', 'pagelock'),
            __('Pagelock', 'pagelock'),
            'manage_options',
            'pagelock',
            array($this, 'admin_page'),
            'dashicons-lock',
            30
        );

        add_submenu_page(
            'pagelock',
            __('Page Locks', 'pagelock'),
            __('Page Locks', 'pagelock'),
            'manage_options',
            'pagelock',
            array($this, 'admin_page')
        );

        add_submenu_page(
            'pagelock',
            __('Add New Lock', 'pagelock'),
            __('Add New Lock', 'pagelock'),
            'manage_options',
            'pagelock-add',
            array($this, 'add_lock_page')
        );
    }

    public function admin_page()
    {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $lock_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        switch ($action) {
            case 'edit':
                $this->edit_lock_page($lock_id);
                break;
            case 'delete':
                $this->delete_lock($lock_id);
                break;
            default:
                $this->list_locks_page();
                break;
        }
    }

    public function list_locks_page()
    {
        $locks = Pagelock_Database::get_locks();
?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Page Locks', 'pagelock'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=pagelock-add'); ?>" class="page-title-action"><?php _e('Add New', 'pagelock'); ?></a>
            <hr class="wp-header-end">

            <?php if (empty($locks)): ?>
                <div class="notice notice-info">
                    <p><?php _e('No page locks found. Create your first lock to get started!', 'pagelock'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('Name', 'pagelock'); ?></th>
                            <th scope="col"><?php _e('Protected Pages', 'pagelock'); ?></th>
                            <th scope="col"><?php _e('Created', 'pagelock'); ?></th>
                            <th scope="col"><?php _e('Actions', 'pagelock'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locks as $lock): ?>
                            <tr>
                                <td><strong><?php echo esc_html($lock->name); ?></strong></td>
                                <td>
                                    <?php
                                    $pages = maybe_unserialize($lock->pages);
                                    $page_titles = array();
                                    if (is_array($pages)) {
                                        foreach ($pages as $page_id) {
                                            $page = get_post($page_id);
                                            if ($page) {
                                                $page_titles[] = $page->post_title;
                                            }
                                        }
                                    }
                                    echo esc_html(implode(', ', $page_titles));
                                    ?>
                                </td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($lock->created_at))); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=pagelock&action=edit&id=' . $lock->id); ?>" class="button"><?php _e('Edit', 'pagelock'); ?></a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=pagelock&action=delete&id=' . $lock->id), 'delete_lock_' . $lock->id); ?>" class="button button-secondary" onclick="return confirm('<?php _e('Are you sure you want to delete this lock?', 'pagelock'); ?>')"><?php _e('Delete', 'pagelock'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php
    }

    public function add_lock_page()
    {
        $this->render_lock_form();
    }

    public function edit_lock_page($lock_id)
    {
        $lock = Pagelock_Database::get_lock($lock_id);
        if (!$lock) {
            wp_die(__('Lock not found.', 'pagelock'));
        }
        $this->render_lock_form($lock);
    }

    private function render_lock_form($lock = null)
    {
        $is_edit = !empty($lock);
        $pages = get_pages();
        $selected_pages = $is_edit ? maybe_unserialize($lock->pages) : array();
    ?>
        <div class="wrap">
            <h1><?php echo $is_edit ? __('Edit Lock', 'pagelock') : __('Add New Lock', 'pagelock'); ?></h1>

            <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" id="pagelock-form">
                <?php wp_nonce_field('pagelock_save_lock'); ?>
                <input type="hidden" name="action" value="pagelock_save_lock">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="lock_id" value="<?php echo esc_attr($lock->id); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="lock_name"><?php _e('Lock Name', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input name="lock_name" type="text" id="lock_name" value="<?php echo $is_edit ? esc_attr($lock->name) : ''; ?>" class="regular-text" required>
                            <p class="description"><?php _e('A descriptive name for this lock.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="lock_password"><?php _e('Password', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input name="lock_password" type="password" id="lock_password" class="regular-text" <?php echo !$is_edit ? 'required' : ''; ?>>
                            <p class="description"><?php echo $is_edit ? __('Leave blank to keep current password.', 'pagelock') : __('The password required to access protected pages.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="lock_pages"><?php _e('Protected Pages', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <select name="lock_pages[]" id="lock_pages" multiple class="regular-text" size="10" required>
                                <?php foreach ($pages as $page): ?>
                                    <option value="<?php echo esc_attr($page->ID); ?>" <?php echo (is_array($selected_pages) && in_array($page->ID, $selected_pages)) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($page->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select the pages to protect with this lock. Hold Ctrl/Cmd to select multiple pages.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $is_edit ? __('Update Lock', 'pagelock') : __('Create Lock', 'pagelock'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=pagelock'); ?>" class="button"><?php _e('Cancel', 'pagelock'); ?></a>
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#pagelock-form').on('submit', function(e) {
                    e.preventDefault();
                    var form = $(this);
                    var formData = form.serialize();

                    $.post(form.attr('action'), formData, function(response) {
                        if (response.success) {
                            window.location.href = '<?php echo admin_url('admin.php?page=pagelock'); ?>';
                        } else {
                            alert(response.data || 'An error occurred.');
                        }
                    }).fail(function() {
                        alert('An error occurred while saving the lock.');
                    });
                });
            });
        </script>
<?php
    }

    public function handle_save_lock()
    {
        check_ajax_referer('pagelock_save_lock');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'pagelock'));
        }

        $lock_id = isset($_POST['lock_id']) ? intval($_POST['lock_id']) : 0;
        $name = sanitize_text_field($_POST['lock_name']);
        $password = $_POST['lock_password'];
        $pages = isset($_POST['lock_pages']) ? array_map('intval', $_POST['lock_pages']) : array();

        if (empty($name) || empty($pages)) {
            wp_send_json_error(__('Name and pages are required.', 'pagelock'));
        }

        if ($lock_id > 0 && empty($password)) {
            // Update without password change
            $data = array(
                'name' => $name,
                'pages' => $pages
            );
        } else {
            if (empty($password)) {
                wp_send_json_error(__('Password is required.', 'pagelock'));
            }
            $data = array(
                'name' => $name,
                'password' => $password,
                'pages' => $pages
            );
        }

        if ($lock_id > 0) {
            $result = Pagelock_Database::update_lock($lock_id, $data);
        } else {
            $result = Pagelock_Database::insert_lock($data);
        }

        if ($result !== false) {
            wp_send_json_success(__('Lock saved successfully.', 'pagelock'));
        } else {
            wp_send_json_error(__('Failed to save lock.', 'pagelock'));
        }
    }

    public function handle_delete_lock()
    {
        $lock_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_lock_' . $lock_id)) {
            wp_die(__('Security check failed.', 'pagelock'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'pagelock'));
        }

        $result = Pagelock_Database::delete_lock($lock_id);

        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=pagelock&message=deleted'));
        } else {
            wp_redirect(admin_url('admin.php?page=pagelock&message=error'));
        }
        exit;
    }

    private function delete_lock($lock_id)
    {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_lock_' . $lock_id)) {
            wp_die(__('Security check failed.', 'pagelock'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'pagelock'));
        }

        $result = Pagelock_Database::delete_lock($lock_id);

        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=pagelock&message=deleted'));
        } else {
            wp_redirect(admin_url('admin.php?page=pagelock&message=error'));
        }
        exit;
    }
}
