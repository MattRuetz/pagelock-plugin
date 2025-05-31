<?php

/**
 * Pagelock Frontend Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Pagelock_Frontend
{

    public function init()
    {
        add_action('template_redirect', array($this, 'check_page_lock'));
        add_action('wp_ajax_pagelock_verify_password', array($this, 'handle_password_verification'));
        add_action('wp_ajax_nopriv_pagelock_verify_password', array($this, 'handle_password_verification'));
    }

    public function check_page_lock()
    {
        if (!is_page() && !is_single()) {
            return;
        }

        global $post;
        $page_id = $post->ID;

        // Check if this page has a lock
        $lock = Pagelock_Database::get_lock_for_page($page_id);
        if (!$lock) {
            return;
        }

        // Check if user is already authenticated for this lock
        if ($this->is_authenticated($lock->id)) {
            return;
        }

        // Show password form
        $this->show_password_form($lock);
        exit;
    }

    private function is_authenticated($lock_id)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        return isset($_SESSION['pagelock_authenticated']) &&
            is_array($_SESSION['pagelock_authenticated']) &&
            in_array($lock_id, $_SESSION['pagelock_authenticated']);
    }

    private function authenticate_user($lock_id)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (!isset($_SESSION['pagelock_authenticated'])) {
            $_SESSION['pagelock_authenticated'] = array();
        }

        if (!in_array($lock_id, $_SESSION['pagelock_authenticated'])) {
            $_SESSION['pagelock_authenticated'][] = $lock_id;
        }
    }

    private function show_password_form($lock)
    {
        global $post;

        // Get site info for title
        $site_name = get_bloginfo('name');
        $page_title = $post->post_title;

        // Get style settings
        $settings = Pagelock_Database::get_settings();

        wp_head();

        // Generate background styles based on settings
        $background_style = '';
        $background_overlay = '';

        switch ($settings['background_type']) {
            case 'solid':
                $background_style = 'background: ' . esc_attr($settings['background_solid_color']) . ';';
                break;
            case 'curve':
                $background_style = 'background: linear-gradient(135deg, ' . esc_attr($settings['background_curve_color1']) . ' 0%, ' . esc_attr($settings['background_curve_color2']) . ' 100%);';
                // Add the curve element
                $curve_color = $settings['background_curve_color1']; // Use the first color for the curve
                $curve_hex = str_replace('#', '%23', $curve_color);
                $background_overlay = "
                body::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 1000 1000\"><path d=\"M0,500 Q250,200 500,500 T1000,500 L1000,1000 L0,1000 Z\" fill=\"{$curve_hex}\" opacity=\"0.1\"/></svg>') no-repeat center;
                    background-size: cover;
                    animation: float 20s ease-in-out infinite;
                }";
                break;
            case 'image':
                if (!empty($settings['background_image'])) {
                    $blur_style = $settings['background_image_blur'] > 0 ? 'filter: blur(' . $settings['background_image_blur'] . 'px);' : '';
                    $background_style = "background: url('" . esc_url($settings['background_image']) . "') center center / cover no-repeat;";

                    if ($settings['background_image_overlay']) {
                        $background_overlay = "
                        body::after {
                            content: '';
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: " . esc_attr($settings['background_image_overlay_color']) . ";
                            z-index: 1;
                        }";
                    }

                    if ($blur_style) {
                        $background_overlay .= "
                        body::before {
                            content: '';
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: url('" . esc_url($settings['background_image']) . "') center center / cover no-repeat;
                            {$blur_style}
                            z-index: 0;
                        }";
                    }
                }
                break;
        }
?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>

        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($page_title . ' - ' . $site_name); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: relative;
                    overflow: hidden;
                    <?php echo $background_style; ?>
                }

                <?php echo $background_overlay; ?>@keyframes float {

                    0%,
                    100% {
                        transform: rotate(0deg) scale(1);
                    }

                    50% {
                        transform: rotate(5deg) scale(1.05);
                    }
                }

                .pagelock-container {
                    background: <?php echo esc_attr($settings['form_background_color']); ?>;
                    backdrop-filter: blur(10px);
                    border-radius: <?php echo esc_attr($settings['form_border_radius']); ?>px;
                    padding: 3rem;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
                    max-width: 480px;
                    width: 90%;
                    text-align: center;
                    position: relative;
                    z-index: 10;
                }

                .pagelock-icon {
                    width: 80px;
                    height: 80px;
                    margin: 0 auto 2rem;
                    background: linear-gradient(135deg, <?php echo esc_attr($settings['icon_background_color1']); ?> 0%, <?php echo esc_attr($settings['icon_background_color2']); ?> 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: relative;
                    overflow: hidden;
                }

                <?php if (!empty($settings['icon_image'])): ?>.pagelock-icon img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    border-radius: 50%;
                }

                <?php else: ?>.pagelock-icon::before {
                    content: '🌿';
                    font-size: 2.5rem;
                }

                <?php endif; ?>.pagelock-title {
                    font-size: 2rem;
                    color: <?php echo esc_attr($settings['heading_text_color']); ?>;
                    margin-bottom: 1rem;
                    font-weight: 700;
                }

                .pagelock-subtitle {
                    color: <?php echo esc_attr($settings['body_text_color']); ?>;
                    margin-bottom: 2rem;
                    font-size: 1.1rem;
                    line-height: 1.6;
                }

                .pagelock-form {
                    margin-bottom: 2rem;
                }

                <?php if ($settings['field_design'] === 'minimal'): ?>input[type="password"].pagelock-input {
                    width: 100%;
                    padding: 1rem 0;
                    border: none;
                    border-bottom: 2px solid rgba(165, 171, 82, 0.3);
                    font-size: 1.1rem;
                    background: transparent;
                    transition: all 0.3s ease;
                    margin-bottom: 1.5rem;
                    outline: none;
                    color: <?php echo esc_attr($settings['body_text_color']); ?>;
                }

                input[type="password"].pagelock-input::placeholder {
                    color: rgba(<?php
                                $rgb = sscanf($settings['body_text_color'], "#%02x%02x%02x");
                                echo implode(', ', $rgb);
                                ?>, 0.6);
                }

                input[type="password"].pagelock-input:focus {
                    border-bottom-color: <?php echo esc_attr($settings['icon_background_color1']); ?>;
                    background: transparent;
                }

                <?php else: ?>input[type="password"].pagelock-input {
                    width: 100%;
                    padding: 1rem 1.5rem;
                    border: 2px solid rgba(165, 171, 82, 0.3);
                    border-radius: <?php echo esc_attr($settings['field_border_radius']); ?>px;
                    font-size: 1.1rem;
                    background: rgba(248, 231, 206, 0.5);
                    transition: all 0.3s ease;
                    margin-bottom: 1.5rem;
                    outline: none;
                }

                input[type="password"].pagelock-input:focus {
                    border-color: #A5AB52;
                    box-shadow: 0 0 0 4px rgba(165, 171, 82, 0.1);
                    background: #fff;
                }

                <?php endif; ?>.pagelock-button {
                    background: <?php echo esc_attr($settings['button_color']); ?>;
                    color: white;
                    border: none;
                    padding: 1rem 2rem;
                    border-radius: <?php echo esc_attr($settings['button_border_radius']); ?>px;
                    font-size: 1.1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    width: 100%;
                    position: relative;
                    overflow: hidden;
                }

                .pagelock-button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px rgba(237, 154, 37, 0.3);
                    filter: brightness(1.1);
                }

                .pagelock-button:active {
                    transform: translateY(0);
                }

                .pagelock-button:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                    transform: none;
                }

                .pagelock-error {
                    background: rgba(212, 88, 116, 0.1);
                    color: #6C0E23;
                    padding: 1rem;
                    border-radius: 12px;
                    margin-bottom: 1rem;
                    border-left: 4px solid #D45874;
                    display: none;
                }

                .pagelock-footer {
                    color: <?php echo esc_attr($settings['body_text_color']); ?>;
                    font-size: 0.9rem;
                }

                /* Responsive */
                @media (max-width: 768px) {
                    .pagelock-container {
                        padding: 2rem;
                        margin: 1rem;
                    }

                    .pagelock-title {
                        font-size: 1.75rem;
                    }
                }
            </style>
        </head>

        <body>
            <div class="pagelock-container">
                <div class="pagelock-icon">
                    <?php if (!empty($settings['icon_image'])): ?>
                        <img src="<?php echo esc_url($settings['icon_image']); ?>" alt="<?php _e('Lock Icon', 'pagelock'); ?>">
                    <?php endif; ?>
                </div>

                <h1 class="pagelock-title">Access Required</h1>
                <p class="pagelock-subtitle">
                    This page is password protected. Please enter the password to continue.
                </p>

                <div class="pagelock-error" id="pagelock-error"></div>

                <form class="pagelock-form" id="pagelock-form">
                    <input type="hidden" name="action" value="pagelock_verify_password">
                    <input type="hidden" name="lock_id" value="<?php echo esc_attr($lock->id); ?>">
                    <input type="hidden" name="page_id" value="<?php echo esc_attr($post->ID); ?>">
                    <?php wp_nonce_field('pagelock_verify'); ?>

                    <input
                        type="password"
                        name="password"
                        class="pagelock-input"
                        placeholder="Enter password..."
                        required
                        autofocus>

                    <button type="submit" class="pagelock-button" id="pagelock-submit">
                        Access Page
                    </button>
                </form>

                <p class="pagelock-footer">
                    Need access? Contact a member of the network for the password.
                </p>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const form = document.getElementById('pagelock-form');
                    const submitBtn = document.getElementById('pagelock-submit');
                    const errorDiv = document.getElementById('pagelock-error');

                    form.addEventListener('submit', function(e) {
                        e.preventDefault();

                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Verifying...';
                        errorDiv.style.display = 'none';

                        const formData = new FormData(form);

                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    submitBtn.textContent = 'Access Granted!';
                                    submitBtn.style.background = 'linear-gradient(135deg, #007C77, #566246)';
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 1000);
                                } else {
                                    errorDiv.textContent = data.data || 'Invalid password. Please try again.';
                                    errorDiv.style.display = 'block';
                                    submitBtn.disabled = false;
                                    submitBtn.textContent = 'Access Page';
                                    form.querySelector('input[name="password"]').value = '';
                                    form.querySelector('input[name="password"]').focus();
                                }
                            })
                            .catch(error => {
                                errorDiv.textContent = 'An error occurred. Please try again.';
                                errorDiv.style.display = 'block';
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Access Page';
                            });
                    });
                });
            </script>

            <?php wp_footer(); ?>
        </body>

        </html>
<?php
    }

    public function handle_password_verification()
    {
        check_ajax_referer('pagelock_verify');

        $lock_id = intval($_POST['lock_id']);
        $page_id = intval($_POST['page_id']);
        $password = $_POST['password'];

        if (empty($password)) {
            wp_send_json_error(__('Password is required.', 'pagelock'));
        }

        // Verify password
        if (Pagelock_Database::verify_password($lock_id, $password)) {
            $this->authenticate_user($lock_id);
            wp_send_json_success(__('Access granted.', 'pagelock'));
        } else {
            wp_send_json_error(__('Invalid password.', 'pagelock'));
        }
    }
}
