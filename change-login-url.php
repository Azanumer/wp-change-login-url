<?php
/**
 * Plugin Name:       Change Login URL
 * Plugin URI:        https://github.com/Azanumer/wp-change-login-url
 * Description:       Renames wp-login.php to a custom slug (e.g. /panel-login/). Direct hits to wp-login.php return 404 unless a secret recovery key is used.
 * Version:           1.0.0
 * Author:            Azan Umer
 * License:           MIT
 * Text Domain:       wcl
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WCL_OPTION', 'wcl_settings');
define('WCL_DEFAULT_SLUG', 'panel-login');

/** Slugs that are never allowed (would break WP or collide with core). */
function wcl_reserved_slugs()
{
    return array(
        'wp-admin', 'wp-login', 'admin', 'login', 'dashboard',
        'wp-json', 'xmlrpc', 'feed', 'wp-content', 'wp-includes',
    );
}

/**
 * Get plugin settings merged with defaults. Auto-generates and persists
 * the recovery key on first run.
 *
 * @return array {slug, recovery_key}
 */
function wcl_get_settings()
{
    $defaults = array(
        'slug'         => WCL_DEFAULT_SLUG,
        'recovery_key' => '',
    );
    $saved = get_option(WCL_OPTION, array());
    if (!is_array($saved)) {
        $saved = array();
    }
    $settings = array_merge($defaults, $saved);

    if (empty($settings['recovery_key'])) {
        $settings['recovery_key'] = wp_generate_password(40, false);
        update_option(WCL_OPTION, $settings);
    }
    return $settings;
}

/**
 * Sanitize the settings form. Slug: lowercase letters, numbers, hyphens.
 * Reserved/invalid slugs are rejected with an admin notice and the old
 * value is kept.
 */
function wcl_sanitize_settings($input)
{
    $old = wcl_get_settings();
    $out = $old;

    $slug = isset($input['slug']) ? strtolower(trim((string) $input['slug'])) : '';
    $slug = preg_replace('/[^a-z0-9-]+/', '', $slug);
    $slug = trim($slug, '-');

    if ($slug === '') {
        add_settings_error('wcl_settings', 'wcl_slug_empty',
            __('Slug cannot be empty — keeping the previous one.', 'wcl'), 'error');
    } elseif (in_array($slug, wcl_reserved_slugs(), true)) {
        add_settings_error('wcl_settings', 'wcl_slug_reserved',
            sprintf(__('"%s" is a reserved slug — keeping the previous one.', 'wcl'), esc_html($slug)), 'error');
    } else {
        $out['slug'] = $slug;
    }

    if (!empty($input['regenerate_key'])) {
        $out['recovery_key'] = wp_generate_password(40, false);
        add_settings_error('wcl_settings', 'wcl_key_regen',
            __('Recovery key regenerated. Save it somewhere safe.', 'wcl'), 'updated');
    }

    return $out;
}

/** Flush rewrite rules (used on activation, deactivation, settings save). */
function wcl_flush_rules()
{
    flush_rewrite_rules();
}

// ---------------------------------------------------------------------------
// Settings page: Settings → Change Login URL
// ---------------------------------------------------------------------------

add_action('admin_menu', 'wcl_admin_menu');
function wcl_admin_menu()
{
    add_options_page(
        __('Change Login URL', 'wcl'),
        __('Change Login URL', 'wcl'),
        'manage_options',
        'wcl-settings',
        'wcl_settings_page'
    );
}

add_action('admin_init', 'wcl_admin_init');
function wcl_admin_init()
{
    register_setting('wcl_settings_group', WCL_OPTION, array(
        'sanitize_callback' => 'wcl_sanitize_settings',
    ));
}

function wcl_settings_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'wcl'));
    }
    $settings  = wcl_get_settings();
    $login_url = home_url('/' . $settings['slug'] . '/');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Change Login URL', 'wcl'); ?></h1>
        <p>
            <?php esc_html_e('Your login page is now:', 'wcl'); ?>
            <code><?php echo esc_html($login_url); ?></code>
        </p>
        <p class="description">
            <?php esc_html_e('Direct access to wp-login.php now returns 404. If you ever forget the slug, open wp-login.php with ?wcl_key= plus the recovery key below.', 'wcl'); ?>
        </p>
        <form method="post" action="options.php">
            <?php
            settings_fields('wcl_settings_group'); // nonce + option fields
            ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="wcl_slug"><?php esc_html_e('Login slug', 'wcl'); ?></label></th>
                    <td>
                        <code><?php echo esc_html(trailingslashit(home_url())); ?></code>
                        <input type="text" id="wcl_slug" name="<?php echo esc_attr(WCL_OPTION); ?>[slug]"
                               value="<?php echo esc_attr($settings['slug']); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('Lowercase letters, numbers and hyphens only.', 'wcl'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Recovery key', 'wcl'); ?></th>
                    <td>
                        <code><?php echo esc_html($settings['recovery_key']); ?></code>
                        <p class="description">
                            <?php esc_html_e('Emergency access:', 'wcl'); ?>
                            <code><?php echo esc_html(site_url('wp-login.php') . '?wcl_key=' . $settings['recovery_key']); ?></code>
                        </p>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(WCL_OPTION); ?>[regenerate_key]" value="1" />
                            <?php esc_html_e('Regenerate recovery key', 'wcl'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Rewrite: /<slug>/ → wp-login.php
// ---------------------------------------------------------------------------

add_action('init', 'wcl_add_rewrite_rule');
function wcl_add_rewrite_rule()
{
    $settings = wcl_get_settings();
    add_rewrite_rule(
        '^' . preg_quote($settings['slug'], '/') . '/?$',
        'index.php?wcl_action=login',
        'top'
    );
}

add_filter('query_vars', 'wcl_query_vars');
function wcl_query_vars($vars)
{
    $vars[] = 'wcl_action';
    return $vars;
}

add_action('template_redirect', 'wcl_template_redirect');
function wcl_template_redirect()
{
    if (get_query_var('wcl_action') !== 'login') {
        return;
    }
    $login_php = ABSPATH . 'wp-login.php';
    if (!file_exists($login_php)) {
        wp_die(esc_html__('Login handler not found.', 'wcl'), 500);
    }
    require $login_php; // wp-load.php is already loaded; its ABSPATH guard no-ops.
    exit;
}

// ---------------------------------------------------------------------------
// Block direct wp-login.php access (404) unless the recovery key matches
// ---------------------------------------------------------------------------

add_action('login_init', 'wcl_maybe_block_wp_login');
function wcl_maybe_block_wp_login()
{
    // Reached through the slug rewrite — allowed.
    if (get_query_var('wcl_action') === 'login') {
        return;
    }
    $settings = wcl_get_settings();
    $key = isset($_GET['wcl_key']) ? (string) $_GET['wcl_key'] : '';
    if ($key !== ''
        && strlen($key) === strlen($settings['recovery_key'])
        && hash_equals($settings['recovery_key'], $key)
    ) {
        return; // emergency recovery access
    }
    status_header(404);
    nocache_headers();
    wp_die(esc_html__('Page not found.', 'wcl'), '', array('response' => 404));
}

// ---------------------------------------------------------------------------
// Rewrite the URLs WordPress generates so everything points at the slug
// ---------------------------------------------------------------------------

add_filter('login_url', 'wcl_filter_login_url', 10, 3);
function wcl_filter_login_url($url, $redirect, $force_reauth)
{
    $settings = wcl_get_settings();
    $new = home_url('/' . $settings['slug'] . '/');
    if (!empty($redirect)) {
        $new = add_query_arg('redirect_to', $redirect, $new); // add_query_arg encodes
    }
    if ($force_reauth) {
        $new = add_query_arg('reauth', '1', $new);
    }
    return $new;
}

add_filter('lostpassword_url', 'wcl_filter_lostpassword_url', 10, 2);
function wcl_filter_lostpassword_url($url, $redirect)
{
    $settings = wcl_get_settings();
    $new = add_query_arg('action', 'lostpassword', home_url('/' . $settings['slug'] . '/'));
    if (!empty($redirect)) {
        $new = add_query_arg('redirect_to', $redirect, $new); // add_query_arg encodes
    }
    return $new;
}

add_filter('register_url', 'wcl_filter_register_url');
function wcl_filter_register_url($url)
{
    $settings = wcl_get_settings();
    return add_query_arg('action', 'register', home_url('/' . $settings['slug'] . '/'));
}

// logout_url must also go through the slug — wp-login.php is blocked.
add_filter('logout_url', 'wcl_filter_logout_url', 10, 2);
function wcl_filter_logout_url($url, $redirect)
{
    $settings = wcl_get_settings();
    $new = add_query_arg('action', 'logout', home_url('/' . $settings['slug'] . '/'));
    if (!empty($redirect)) {
        $new = add_query_arg('redirect_to', $redirect, $new); // add_query_arg encodes
    }
    return wp_nonce_url($new, 'log-out');
}

// ---------------------------------------------------------------------------
// Lifecycle
// ---------------------------------------------------------------------------

register_activation_hook(__FILE__, 'wcl_activate');
function wcl_activate()
{
    wcl_get_settings();      // ensure defaults + recovery key exist
    wcl_add_rewrite_rule();  // register the rule before flushing
    wcl_flush_rules();
}

register_deactivation_hook(__FILE__, 'wcl_flush_rules');

// Re-flush when the slug changes on the settings page.
add_action('update_option_' . WCL_OPTION, 'wcl_flush_rules');

add_action('plugins_loaded', 'wcl_load_textdomain');
function wcl_load_textdomain()
{
    load_plugin_textdomain('wcl', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
