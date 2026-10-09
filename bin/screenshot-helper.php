<?php
/**
 * Local dev only, never shipped: bin/dev-site.sh copies it into .wp/ as a
 * must-use plugin for bin/screenshots.sh. ?shot_as=<login> renders a page as
 * that user, ?shot_login=<login>&to=<path> signs in for real (for the block
 * editor), ?shot_scheme=dark forces a colour scheme, ?shot_open opens What's New.
 */
add_filter('determine_current_user', function ($id) {
    if (isset($_GET['shot_as'])) {
        $user = get_user_by('login', sanitize_user(wp_unslash($_GET['shot_as'])));
        return $user ? $user->ID : $id;
    }
    return $id;
}, 99);
add_filter('ruvelo_feedback_settings', function ($s) {
    if (isset($_GET['shot_scheme'])) {
        $s['color_scheme'] = sanitize_key($_GET['shot_scheme']);
    }
    return $s;
});
add_filter('show_admin_bar', function ($show) { return isset($_GET['shot_bar']) ? $show : false; });
add_filter('render_block_ruvelo/whats-new', function ($html) {
    return isset($_GET['shot_open']) ? str_replace('<details class="ruvelo-fb-wn"', '<details open class="ruvelo-fb-wn"', $html) : $html;
});
add_action('wp_head', function () {
    if (isset($_GET['shot_scheme']) && $_GET['shot_scheme'] === 'dark') {
        echo '<style>body,.wp-site-blocks{background:#11111c!important;color:#f1f1f8!important}.wp-site-blocks a:not(.ruvelo-fb *){color:#f1f1f8!important}.ruvelo-fb[data-theme="dark"]:not(.ruvelo-fb-inline){padding:0!important}</style>';
    }
});
add_action('plugins_loaded', function () {
    if (! isset($_GET['shot_as'])) {
        return;
    }
    $user = get_user_by('login', sanitize_user(wp_unslash($_GET['shot_as'])));
    if ($user) {
        $exp = time() + HOUR_IN_SECONDS;
        $_COOKIE[AUTH_COOKIE] = wp_generate_auth_cookie($user->ID, $exp, 'auth');
        $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($user->ID, $exp, 'logged_in');
    }
}, 0);
add_action('admin_init', function () {
    if (isset($_GET['shot_as'])) {
        remove_action('admin_enqueue_scripts', 'wp_auth_check_load');
        add_action('admin_enqueue_scripts', function () { wp_deregister_script('heartbeat'); wp_register_script('heartbeat', false); }, 0);
    }
});
add_action('init', function () {
    if (isset($_GET['shot_login'], $_GET['to'])) {
        $user = get_user_by('login', sanitize_user(wp_unslash($_GET['shot_login'])));
        if ($user) {
            wp_set_auth_cookie($user->ID, false);
            wp_safe_redirect(home_url(wp_unslash($_GET['to'])));
            exit;
        }
    }
}, 1);
