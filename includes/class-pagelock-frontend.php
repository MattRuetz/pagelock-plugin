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
        add_action('init', array($this, 'start_session'), 1);
        add_action('init', array($this, 'setup_cache_exclusions'), 1);
        add_action('template_redirect', array($this, 'check_page_lock'), 1);
        add_action('wp_ajax_pagelock_verify_password', array($this, 'handle_password_verification'));
        add_action('wp_ajax_nopriv_pagelock_verify_password', array($this, 'handle_password_verification'));
    }

    public function start_session()
    {
        // Don't start sessions for REST API requests, admin-ajax, or admin pages
        if ($this->should_skip_session()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }
    }

    private function should_skip_session()
    {
        // Skip if this is a REST API request
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        // Skip if this is an admin-ajax request
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return true;
        }

        // Skip if we're in the admin area
        if (is_admin()) {
            return true;
        }

        // Skip if this is a REST API URL pattern
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
            return true;
        }

        // Skip if this is a cron request
        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }

        return false;
    }

    public function setup_cache_exclusions()
    {
        // Set up early cache exclusions for cache plugins that need it
        
        // WP Rocket - exclude URLs with pagelock
        add_filter('rocket_cache_reject_uri', array($this, 'rocket_exclude_protected_pages'));
        
        // Cloudflare
        if (class_exists('CF\WordPress\Hooks')) {
            add_filter('cloudflare_purge_by_url', array($this, 'cloudflare_exclude_protected_pages'));
        }
        
        // Breeze (Cloudways)
        if (class_exists('Breeze_Admin')) {
            add_filter('breeze_exclude_url', array($this, 'breeze_exclude_protected_pages'));
        }
    }

    public function rocket_exclude_protected_pages($uri)
    {
        // Get all protected pages and exclude them from caching
        $locks = Pagelock_Database::get_locks();
        if ($locks) {
            foreach ($locks as $lock) {
                $pages = maybe_unserialize($lock->pages);
                if (is_array($pages)) {
                    foreach ($pages as $page_id) {
                        $page_url = get_permalink($page_id);
                        if ($page_url) {
                            $path = parse_url($page_url, PHP_URL_PATH);
                            $uri[] = $path;
                        }
                    }
                }
            }
        }
        return $uri;
    }

    public function cloudflare_exclude_protected_pages($urls)
    {
        // Similar exclusion for Cloudflare
        $locks = Pagelock_Database::get_locks();
        if ($locks) {
            foreach ($locks as $lock) {
                $pages = maybe_unserialize($lock->pages);
                if (is_array($pages)) {
                    foreach ($pages as $page_id) {
                        $page_url = get_permalink($page_id);
                        if ($page_url && !in_array($page_url, $urls)) {
                            $urls[] = $page_url;
                        }
                    }
                }
            }
        }
        return $urls;
    }

    public function breeze_exclude_protected_pages($urls)
    {
        // Similar exclusion for Breeze
        return $this->cloudflare_exclude_protected_pages($urls);
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

        // Add cache-busting for locked pages (prevents caching even when authenticated)
        $this->set_no_cache_headers();

        // Check if user is already authenticated for this lock
        if ($this->is_authenticated($lock->id)) {
            return;
        }

        // Show password form with additional cache prevention
        $this->show_password_form($lock);
        exit;
    }

    private function is_authenticated($lock_id)
    {
        // Session should already be started by init hook, but double-check
        if (!session_id()) {
            session_start();
        }

        return isset($_SESSION['pagelock_authenticated']) &&
            is_array($_SESSION['pagelock_authenticated']) &&
            in_array($lock_id, $_SESSION['pagelock_authenticated']);
    }

    private function authenticate_user($lock_id)
    {
        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['pagelock_authenticated'])) {
            $_SESSION['pagelock_authenticated'] = array();
        }

        if (!in_array($lock_id, $_SESSION['pagelock_authenticated'])) {
            $_SESSION['pagelock_authenticated'][] = $lock_id;
        }

        // Force session write to ensure data persists
        session_write_close();
        session_start();
    }

    private function set_no_cache_headers()
    {
        // Prevent caching with standard headers
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        }

        // WordPress specific no-cache
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        if (!defined('DONOTCACHEOBJECT')) {
            define('DONOTCACHEOBJECT', true);
        }
        if (!defined('DONOTCACHEDB')) {
            define('DONOTCACHEDB', true);
        }

        // Popular cache plugin exclusions
        $this->exclude_from_cache_plugins();
    }

    private function exclude_from_cache_plugins()
    {
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            add_filter('rocket_cache_reject_uri', array($this, 'rocket_exclude_uri'));
            add_filter('rocket_cache_query_strings', array($this, 'rocket_exclude_query_strings'));
        }

        // W3 Total Cache
        if (defined('W3TC')) {
            add_filter('w3tc_can_cache', '__return_false');
        }

        // WP Super Cache
        if (function_exists('wp_cache_serve_cache_file')) {
            global $cache_enabled;
            $cache_enabled = false;
        }

        // WP Fastest Cache
        if (class_exists('WpFastestCache')) {
            add_filter('wpfc_is_cacheable', '__return_false');
        }

        // LiteSpeed Cache
        if (defined('LSCWP_V')) {
            add_filter('litespeed_cache_is_cacheable', '__return_false');
        }

        // Autoptimize
        if (class_exists('autoptimizeCache')) {
            add_filter('autoptimize_filter_noptimize', '__return_true');
        }
    }

    public function rocket_exclude_uri($uri)
    {
        global $post;
        if ($post && Pagelock_Database::get_lock_for_page($post->ID)) {
            $uri[] = '.*';
        }
        return $uri;
    }

    public function rocket_exclude_query_strings($query_strings)
    {
        $query_strings[] = 'pagelock_auth';
        return $query_strings;
    }

    private function clear_page_cache($page_id)
    {
        $page_url = get_permalink($page_id);
        
        // WP Rocket
        if (function_exists('rocket_clean_files')) {
            rocket_clean_files(array($page_url));
        }
        
        // W3 Total Cache
        if (function_exists('w3tc_flush_url')) {
            w3tc_flush_url($page_url);
        }
        
        // WP Super Cache
        if (function_exists('wp_cache_post_change')) {
            wp_cache_post_change($page_id);
        }
        
        // LiteSpeed Cache
        if (class_exists('LiteSpeed_Cache_Purge')) {
            LiteSpeed_Cache_Purge::purge_post($page_id);
        }
        
        // Autoptimize
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
        }
        
        // WP Fastest Cache
        if (class_exists('WpFastestCache')) {
            $wpfc = new WpFastestCache();
            if (method_exists($wpfc, 'singleDeleteCache')) {
                $wpfc->singleDeleteCache(false, $page_id);
            }
        }
        
        // Generic WordPress cache clearing
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
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
                    line-height: 1.5;
                }

                /* Responsive Design */
                /* Large tablets and small desktops */
                @media (max-width: 1024px) {
                    .pagelock-container {
                        max-width: 420px;
                        width: 85%;
                    }
                }

                /* Tablets */
                @media (max-width: 768px) {
                    body {
                        padding: 1rem;
                        overflow-y: auto;
                        min-height: 100vh;
                    }

                    .pagelock-container {
                        padding: 2.5rem 2rem;
                        margin: 0;
                        width: 100%;
                        max-width: 100%;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                    }

                    .pagelock-title {
                        font-size: 1.75rem;
                        margin-bottom: 0.75rem;
                    }

                    .pagelock-subtitle {
                        font-size: 1rem;
                        margin-bottom: 1.5rem;
                    }

                    .pagelock-icon {
                        width: 70px;
                        height: 70px;
                        margin-bottom: 1.5rem;
                    }

                    .pagelock-button {
                        padding: 1rem 1.5rem;
                        font-size: 1rem;
                        min-height: 48px;
                        /* Minimum touch target size */
                    }

                    .pagelock-error {
                        padding: 0.875rem;
                        font-size: 0.9rem;
                    }

                    .pagelock-footer {
                        font-size: 0.85rem;
                    }
                }

                /* Tablet icon styling */
                <?php if (empty($settings['icon_image'])): ?>@media (max-width: 768px) {
                    .pagelock-icon::before {
                        font-size: 2rem;
                    }
                }

                <?php endif; ?>
                /* Tablet field styling - minimal */
                <?php if ($settings['field_design'] === 'minimal'): ?>@media (max-width: 768px) {
                    input[type="password"].pagelock-input {
                        padding: 1rem 0.5rem;
                        font-size: 1rem;
                    }
                }

                <?php else: ?>@media (max-width: 768px) {
                    input[type="password"].pagelock-input {
                        padding: 1rem;
                        font-size: 1rem;
                    }
                }

                <?php endif; ?>
                /* Mobile phones */
                @media (max-width: 480px) {
                    body {
                        padding: 0.5rem;
                    }

                    .pagelock-container {
                        padding: 2rem 1.5rem;
                        border-radius: <?php echo min(24, intval($settings['form_border_radius'])); ?>px;
                    }

                    .pagelock-title {
                        font-size: 1.5rem;
                        line-height: 1.3;
                    }

                    .pagelock-subtitle {
                        font-size: 0.95rem;
                        margin-bottom: 1.25rem;
                    }

                    .pagelock-icon {
                        width: 60px;
                        height: 60px;
                        margin-bottom: 1.25rem;
                    }

                    .pagelock-form {
                        margin-bottom: 1.5rem;
                    }

                    .pagelock-button {
                        padding: 0.875rem 1.25rem;
                        font-size: 0.95rem;
                        min-height: 44px;
                        border-radius: <?php echo min(16, intval($settings['button_border_radius'])); ?>px;
                    }

                    .pagelock-error {
                        padding: 0.75rem;
                        font-size: 0.85rem;
                        border-radius: 8px;
                    }

                    .pagelock-footer {
                        font-size: 0.8rem;
                        line-height: 1.4;
                    }
                }

                /* Mobile icon styling */
                <?php if (empty($settings['icon_image'])): ?>@media (max-width: 480px) {
                    .pagelock-icon::before {
                        font-size: 1.75rem;
                    }
                }

                <?php endif; ?>
                /* Mobile field styling - minimal */
                <?php if ($settings['field_design'] === 'minimal'): ?>@media (max-width: 480px) {
                    input[type="password"].pagelock-input {
                        padding: 0.875rem 0.25rem;
                        font-size: 0.95rem;
                        margin-bottom: 1.25rem;
                    }
                }

                <?php else: ?>@media (max-width: 480px) {
                    input[type="password"].pagelock-input {
                        padding: 0.875rem 1rem;
                        font-size: 0.95rem;
                        margin-bottom: 1.25rem;
                        border-radius: <?php echo min(16, intval($settings['field_border_radius'])); ?>px;
                    }
                }

                <?php endif; ?>
                /* Very small screens */
                @media (max-width: 320px) {
                    .pagelock-container {
                        padding: 1.5rem 1rem;
                    }

                    .pagelock-title {
                        font-size: 1.375rem;
                    }

                    .pagelock-subtitle {
                        font-size: 0.9rem;
                    }

                    .pagelock-icon {
                        width: 50px;
                        height: 50px;
                        margin-bottom: 1rem;
                    }
                }

                /* Very small screen icon styling */
                <?php if (empty($settings['icon_image'])): ?>@media (max-width: 320px) {
                    .pagelock-icon::before {
                        font-size: 1.5rem;
                    }
                }

                <?php endif; ?>
                /* Touch device optimizations */
                @media (hover: none) and (pointer: coarse) {
                    .pagelock-button:hover {
                        transform: none;
                        box-shadow: 0 4px 15px rgba(237, 154, 37, 0.2);
                    }

                    .pagelock-button:active {
                        transform: scale(0.98);
                        transition: transform 0.1s ease;
                    }
                }

                /* Touch device field focus - default style only */
                <?php if ($settings['field_design'] !== 'minimal'): ?>@media (hover: none) and (pointer: coarse) {
                    input[type="password"].pagelock-input:focus {
                        box-shadow: 0 0 0 2px rgba(165, 171, 82, 0.1);
                    }
                }

                <?php endif; ?>
                /* High DPI displays */
                @media (-webkit-min-device-pixel-ratio: 2),
                (min-resolution: 192dpi) {
                    .pagelock-container {
                        backdrop-filter: blur(8px);
                    }
                }

                /* Landscape orientation on phones */
                @media (max-width: 768px) and (orientation: landscape) and (max-height: 500px) {
                    body {
                        align-items: flex-start;
                        padding-top: 2rem;
                        overflow-y: auto;
                    }

                    .pagelock-container {
                        margin: 0 auto;
                        min-height: auto;
                    }

                    .pagelock-icon {
                        width: 50px;
                        height: 50px;
                        margin-bottom: 1rem;
                    }

                    .pagelock-title {
                        font-size: 1.375rem;
                        margin-bottom: 0.5rem;
                    }

                    .pagelock-subtitle {
                        font-size: 0.9rem;
                        margin-bottom: 1rem;
                    }

                    .pagelock-form {
                        margin-bottom: 1rem;
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
        // Start session for password verification (we need this even though it's AJAX)
        if (!session_id()) {
            session_start();
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'pagelock_verify')) {
            wp_send_json_error(__('Security check failed. Please refresh and try again.', 'pagelock'));
        }

        // Validate required fields
        if (!isset($_POST['lock_id']) || !isset($_POST['page_id']) || !isset($_POST['password'])) {
            wp_send_json_error(__('Missing required fields.', 'pagelock'));
        }

        $lock_id = intval($_POST['lock_id']);
        $page_id = intval($_POST['page_id']);
        $password = sanitize_text_field($_POST['password']);

        if (empty($password)) {
            wp_send_json_error(__('Password is required.', 'pagelock'));
        }

        // Verify lock exists and is valid for the page
        $lock = Pagelock_Database::get_lock_for_page($page_id);
        if (!$lock || $lock->id != $lock_id) {
            wp_send_json_error(__('Invalid lock configuration.', 'pagelock'));
        }

        // Verify password
        if (Pagelock_Database::verify_password($lock_id, $password)) {
            $this->authenticate_user($lock_id);
            
            // Clear cache for this page after successful authentication
            $this->clear_page_cache($page_id);
            
            wp_send_json_success(__('Access granted.', 'pagelock'));
        } else {
            wp_send_json_error(__('Invalid password.', 'pagelock'));
        }
    }
}
