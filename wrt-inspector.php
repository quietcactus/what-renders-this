<?php

/**
 * Plugin Name: What Renders This
 * Description: Dev-only frontend inspector. Answers which template file rendered the page, what the route and post context are, which ACF field groups are attached, and the ordered chain of theme partials that fired.
 * Version:     1.1.0
 * Author:      Ian Garcia
 * License:     GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if (! defined('ABSPATH')) {
  exit;
}

define('WRT_INSPECTOR_DIR', plugin_dir_path(__FILE__));
define('WRT_INSPECTOR_URL', plugin_dir_url(__FILE__));
define('WRT_INSPECTOR_VERSION', '1.1.0');

require_once WRT_INSPECTOR_DIR . 'includes/class-wrt-inspector-gate.php';
require_once WRT_INSPECTOR_DIR . 'includes/class-wrt-inspector-route.php';
require_once WRT_INSPECTOR_DIR . 'includes/class-wrt-inspector-trace.php';
require_once WRT_INSPECTOR_DIR . 'includes/class-wrt-inspector-acf.php';
require_once WRT_INSPECTOR_DIR . 'includes/class-wrt-inspector-panel.php';
require_once WRT_INSPECTOR_DIR . 'includes/class-wrt-inspector-state.php';

/**
 * Whether the inspector is switched on. Filterable via wrt_inspector_enabled;
 * the WRT_INSPECTOR constant overrides the default in both directions.
 */
function wrt_inspector_enabled(): bool {
  return WRT_Inspector_Gate::enabled();
}

// init, not plugins_loaded — current_user_can() needs pluggable functions to be loaded.
add_action('init', function () {
  if (! WRT_Inspector_Gate::passes()) {
    return;
  }

  $state = new WRT_Inspector_State();

  // The panel renders into the page HTML. A misconfigured full-page cache
  // could otherwise store this admin's response and serve the panel to
  // anonymous visitors.
  add_action('send_headers', function () {
    nocache_headers();
  });

  add_action('template_redirect', [$state, 'snapshot'], 0);
  add_filter('template_include', [$state, 'capture_template'], PHP_INT_MAX);
  add_action('wp_enqueue_scripts', [$state, 'enqueue_assets']);
  // Runs after every admin_bar_menu callback, so the node lands last in the
  // bar. Both hooks fire inside wp_admin_bar_render(), well after
  // template_include, so either is safe for a template-derived label.
  add_action('wp_before_admin_bar_render', [$state, 'add_node']);
  add_action('wp_footer', [$state, 'render_panel'], PHP_INT_MAX);

  $state->watch_hierarchy();
});
