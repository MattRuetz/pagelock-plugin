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
        add_action('wp_ajax_pagelock_save_settings', array($this, 'handle_save_settings'));
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

        add_submenu_page(
            'pagelock',
            __('Settings', 'pagelock'),
            __('Settings', 'pagelock'),
            'manage_options',
            'pagelock-settings',
            array($this, 'settings_page')
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

    public function settings_page()
    {
        wp_enqueue_media();
        $settings = Pagelock_Database::get_settings();
    ?>
        <div class="wrap">
            <h1><?php _e('Pagelock Settings', 'pagelock'); ?></h1>

            <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" id="pagelock-settings-form">
                <?php wp_nonce_field('pagelock_save_settings'); ?>
                <input type="hidden" name="action" value="pagelock_save_settings">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="icon_image"><?php _e('Icon Image', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <div class="pagelock-media-upload">
                                <input type="hidden" name="icon_image_id" id="icon_image_id" value="<?php echo esc_attr($settings['icon_image_id'] ?? ''); ?>">
                                <div id="icon_image_preview">
                                    <?php if (!empty($settings['icon_image'])): ?>
                                        <img src="<?php echo esc_url($settings['icon_image']); ?>" style="max-width: 80px; max-height: 80px; display: block; margin-bottom: 10px;">
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button" id="select_icon_image"><?php _e('Select Image', 'pagelock'); ?></button>
                                <button type="button" class="button" id="remove_icon_image" style="margin-left: 5px;"><?php _e('Remove Image', 'pagelock'); ?></button>
                            </div>
                            <p class="description"><?php _e('Select an image from the media library to replace the plant emoji in the password form.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="icon_background_color1"><?php _e('Icon Background Gradient Color 1', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="icon_background_color1" id="icon_background_color1" value="<?php echo esc_attr($settings['icon_background_color1']); ?>">
                            <p class="description"><?php _e('First color of the icon background gradient.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="icon_background_color2"><?php _e('Icon Background Gradient Color 2', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="icon_background_color2" id="icon_background_color2" value="<?php echo esc_attr($settings['icon_background_color2']); ?>">
                            <p class="description"><?php _e('Second color of the icon background gradient.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="button_color"><?php _e('Button Color', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="button_color" id="button_color" value="<?php echo esc_attr($settings['button_color']); ?>">
                            <p class="description"><?php _e('Color of the access button on the password form.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="form_background_color"><?php _e('Form Background Color', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="form_background_color" id="form_background_color" value="<?php echo esc_attr($settings['form_background_color']); ?>" class="regular-text">
                            <p class="description"><?php _e('Background color of the password form (supports rgba values).', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="heading_text_color"><?php _e('Heading Text Color', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="heading_text_color" id="heading_text_color" value="<?php echo esc_attr($settings['heading_text_color']); ?>">
                            <p class="description"><?php _e('Color of the main heading text.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="body_text_color"><?php _e('Body Text Color', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="body_text_color" id="body_text_color" value="<?php echo esc_attr($settings['body_text_color']); ?>">
                            <p class="description"><?php _e('Color of the body text and descriptions.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="field_design"><?php _e('Field Design', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <select name="field_design" id="field_design">
                                <option value="default" <?php selected($settings['field_design'], 'default'); ?>><?php _e('Default', 'pagelock'); ?></option>
                                <option value="minimal" <?php selected($settings['field_design'], 'minimal'); ?>><?php _e('Minimal', 'pagelock'); ?></option>
                            </select>
                            <p class="description"><?php _e('Choose the design style for the password input field.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="form_border_radius"><?php _e('Form Border Radius', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="range" name="form_border_radius" id="form_border_radius" min="0" max="50" value="<?php echo esc_attr($settings['form_border_radius']); ?>">
                            <span id="form_radius_value"><?php echo esc_attr($settings['form_border_radius']); ?>px</span>
                            <p class="description"><?php _e('Border radius of the password form container.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="field_border_radius"><?php _e('Field Border Radius', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="range" name="field_border_radius" id="field_border_radius" min="0" max="50" value="<?php echo esc_attr($settings['field_border_radius']); ?>">
                            <span id="field_radius_value"><?php echo esc_attr($settings['field_border_radius']); ?>px</span>
                            <p class="description"><?php _e('Border radius of the password input field.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="button_border_radius"><?php _e('Button Border Radius', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="range" name="button_border_radius" id="button_border_radius" min="0" max="50" value="<?php echo esc_attr($settings['button_border_radius']); ?>">
                            <span id="button_radius_value"><?php echo esc_attr($settings['button_border_radius']); ?>px</span>
                            <p class="description"><?php _e('Border radius of the access button.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="background_type"><?php _e('Background Type', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <select name="background_type" id="background_type">
                                <option value="solid" <?php selected($settings['background_type'], 'solid'); ?>><?php _e('Solid Color', 'pagelock'); ?></option>
                                <option value="curve" <?php selected($settings['background_type'], 'curve'); ?>><?php _e('Gradient Curve', 'pagelock'); ?></option>
                                <option value="image" <?php selected($settings['background_type'], 'image'); ?>><?php _e('Image', 'pagelock'); ?></option>
                            </select>
                            <p class="description"><?php _e('Choose the type of background for the password form.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr class="background-solid-row">
                        <th scope="row">
                            <label for="background_solid_color"><?php _e('Background Color', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="background_solid_color" id="background_solid_color" value="<?php echo esc_attr($settings['background_solid_color']); ?>">
                            <p class="description"><?php _e('Solid background color.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr class="background-curve-row">
                        <th scope="row">
                            <label for="background_curve_color1"><?php _e('Gradient Color 1', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="background_curve_color1" id="background_curve_color1" value="<?php echo esc_attr($settings['background_curve_color1']); ?>">
                            <p class="description"><?php _e('First color of the gradient.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr class="background-curve-row">
                        <th scope="row">
                            <label for="background_curve_color2"><?php _e('Gradient Color 2', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="background_curve_color2" id="background_curve_color2" value="<?php echo esc_attr($settings['background_curve_color2']); ?>">
                            <p class="description"><?php _e('Second color of the gradient.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr class="background-image-row">
                        <th scope="row">
                            <label for="background_image"><?php _e('Background Image', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <div class="pagelock-media-upload">
                                <input type="hidden" name="background_image_id" id="background_image_id" value="<?php echo esc_attr($settings['background_image_id'] ?? ''); ?>">
                                <div id="background_image_preview">
                                    <?php if (!empty($settings['background_image'])): ?>
                                        <img src="<?php echo esc_url($settings['background_image']); ?>" style="max-width: 200px; max-height: 150px; display: block; margin-bottom: 10px;">
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button" id="select_background_image"><?php _e('Select Image', 'pagelock'); ?></button>
                                <button type="button" class="button" id="remove_background_image" style="margin-left: 5px;"><?php _e('Remove Image', 'pagelock'); ?></button>
                            </div>
                            <p class="description"><?php _e('Select a background image from the media library.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr class="background-image-row">
                        <th scope="row">
                            <label for="background_image_overlay"><?php _e('Image Overlay', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <label><input type="checkbox" name="background_image_overlay" id="background_image_overlay" value="1" <?php checked($settings['background_image_overlay']); ?>> <?php _e('Enable overlay', 'pagelock'); ?></label>
                            <p class="description"><?php _e('Add a colored overlay over the background image.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr class="background-image-row">
                        <th scope="row">
                            <label for="background_image_overlay_color"><?php _e('Overlay Color', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="background_image_overlay_color" id="background_image_overlay_color" value="<?php echo esc_attr($settings['background_image_overlay_color']); ?>" class="regular-text">
                            <p class="description"><?php _e('Color of the overlay (supports rgba values).', 'pagelock'); ?></p>
                        </td>
                    </tr>
                    <tr class="background-image-row">
                        <th scope="row">
                            <label for="background_image_blur"><?php _e('Background Blur', 'pagelock'); ?></label>
                        </th>
                        <td>
                            <input type="range" name="background_image_blur" id="background_image_blur" min="0" max="20" value="<?php echo esc_attr($settings['background_image_blur']); ?>">
                            <span id="blur_value"><?php echo esc_attr($settings['background_image_blur']); ?>px</span>
                            <p class="description"><?php _e('Amount of blur to apply to the background image.', 'pagelock'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Settings', 'pagelock'); ?>">
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Enqueue WordPress media scripts
                if (typeof wp !== 'undefined' && wp.media) {

                    // Icon Image Media Library
                    var iconFrame;
                    $('#select_icon_image').on('click', function(e) {
                        e.preventDefault();

                        if (iconFrame) {
                            iconFrame.open();
                            return;
                        }

                        iconFrame = wp.media({
                            title: '<?php _e('Select Icon Image', 'pagelock'); ?>',
                            button: {
                                text: '<?php _e('Use this image', 'pagelock'); ?>'
                            },
                            multiple: false,
                            library: {
                                type: 'image'
                            }
                        });

                        iconFrame.on('select', function() {
                            var attachment = iconFrame.state().get('selection').first().toJSON();
                            $('#icon_image_id').val(attachment.id);
                            $('#icon_image_preview').html('<img src="' + attachment.url + '" style="max-width: 80px; max-height: 80px; display: block; margin-bottom: 10px;">');
                        });

                        iconFrame.open();
                    });

                    $('#remove_icon_image').on('click', function(e) {
                        e.preventDefault();
                        $('#icon_image_id').val('');
                        $('#icon_image_preview').html('');
                    });

                    // Background Image Media Library
                    var backgroundFrame;
                    $('#select_background_image').on('click', function(e) {
                        e.preventDefault();

                        if (backgroundFrame) {
                            backgroundFrame.open();
                            return;
                        }

                        backgroundFrame = wp.media({
                            title: '<?php _e('Select Background Image', 'pagelock'); ?>',
                            button: {
                                text: '<?php _e('Use this image', 'pagelock'); ?>'
                            },
                            multiple: false,
                            library: {
                                type: 'image'
                            }
                        });

                        backgroundFrame.on('select', function() {
                            var attachment = backgroundFrame.state().get('selection').first().toJSON();
                            $('#background_image_id').val(attachment.id);
                            $('#background_image_preview').html('<img src="' + attachment.url + '" style="max-width: 200px; max-height: 150px; display: block; margin-bottom: 10px;">');
                        });

                        backgroundFrame.open();
                    });

                    $('#remove_background_image').on('click', function(e) {
                        e.preventDefault();
                        $('#background_image_id').val('');
                        $('#background_image_preview').html('');
                    });
                }

                function toggleBackgroundOptions() {
                    var selectedType = $('#background_type').val();
                    $('.background-solid-row, .background-curve-row, .background-image-row').hide();
                    $('.background-' + selectedType + '-row').show();
                }

                $('#background_type').on('change', toggleBackgroundOptions);
                toggleBackgroundOptions();

                $('#background_image_blur').on('input', function() {
                    $('#blur_value').text($(this).val() + 'px');
                });

                // Handle border radius sliders
                $('#form_border_radius').on('input', function() {
                    $('#form_radius_value').text($(this).val() + 'px');
                });

                $('#field_border_radius').on('input', function() {
                    $('#field_radius_value').text($(this).val() + 'px');
                });

                $('#button_border_radius').on('input', function() {
                    $('#button_radius_value').text($(this).val() + 'px');
                });

                $('#pagelock-settings-form').on('submit', function(e) {
                    e.preventDefault();
                    var form = $(this);
                    var formData = form.serialize();

                    $.post(form.attr('action'), formData, function(response) {
                        if (response.success) {
                            $('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>').insertAfter('.wrap h1');
                        } else {
                            $('<div class="notice notice-error is-dismissible"><p>' + (response.data || 'An error occurred.') + '</p></div>').insertAfter('.wrap h1');
                        }
                    }).fail(function() {
                        $('<div class="notice notice-error is-dismissible"><p>An error occurred while saving settings.</p></div>').insertAfter('.wrap h1');
                    });
                });
            });
        </script>
    <?php
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

    public function handle_save_settings()
    {
        check_ajax_referer('pagelock_save_settings');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'pagelock'));
        }

        $settings = array();

        // Handle icon image from media library
        if (isset($_POST['icon_image_id']) && !empty($_POST['icon_image_id'])) {
            $icon_id = intval($_POST['icon_image_id']);
            $icon_url = wp_get_attachment_url($icon_id);
            if ($icon_url) {
                $settings['icon_image_id'] = $icon_id;
                $settings['icon_image'] = $icon_url;
            }
        } else {
            $settings['icon_image_id'] = '';
            $settings['icon_image'] = '';
        }

        // Handle background image from media library
        if (isset($_POST['background_image_id']) && !empty($_POST['background_image_id'])) {
            $bg_id = intval($_POST['background_image_id']);
            $bg_url = wp_get_attachment_url($bg_id);
            if ($bg_url) {
                $settings['background_image_id'] = $bg_id;
                $settings['background_image'] = $bg_url;
            }
        } else {
            $settings['background_image_id'] = '';
            $settings['background_image'] = '';
        }

        // Handle other settings
        $fields = array(
            'button_color',
            'form_background_color',
            'heading_text_color',
            'body_text_color',
            'icon_background_color1',
            'icon_background_color2',
            'field_design',
            'background_type',
            'background_solid_color',
            'background_curve_color1',
            'background_curve_color2',
            'background_image_overlay_color',
            'background_image_blur',
            'form_border_radius',
            'field_border_radius',
            'button_border_radius'
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        // Handle checkbox
        $settings['background_image_overlay'] = isset($_POST['background_image_overlay']) ? true : false;

        $result = Pagelock_Database::update_settings($settings);

        if ($result !== false) {
            wp_send_json_success(__('Settings saved successfully.', 'pagelock'));
        } else {
            wp_send_json_error(__('Failed to save settings.', 'pagelock'));
        }
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
