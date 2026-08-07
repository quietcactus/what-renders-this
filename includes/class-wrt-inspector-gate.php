<?php

if (! defined('ABSPATH')) {
  exit;
}

class WRT_Inspector_Gate {

  const CAPABILITY = 'manage_options';

  /**
   * Whether the inspector is switched on. Says nothing about the current user
   * — capability is checked separately, and that is the real boundary.
   *
   * On by default, everywhere. The panel only ever renders for users who hold
   * manage_options, and everything it shows is already available to them in
   * the dashboard, so gating on environment bought nothing while breaking the
   * plugin on managed hosts that report 'production' for every install.
   */
  public static function enabled(): bool {
    $enabled = defined('WRT_INSPECTOR') ? (bool) WRT_INSPECTOR : true;
    return (bool) apply_filters('wrt_inspector_enabled', $enabled);
  }

  /**
   * Full gate: environment, capability, and request type.
   */
  public static function passes(): bool {
    if (! self::enabled()) {
      return false;
    }
    if (! current_user_can(self::CAPABILITY)) {
      return false;
    }
    if (is_admin()) {
      return false; // v1 is frontend only
    }
    if (wp_doing_ajax() || wp_doing_cron()) {
      return false;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
      return false;
    }
    if (defined('WP_CLI') && WP_CLI) {
      return false;
    }
    return true;
  }

  /**
   * Human-readable reason the gate opened, for the panel footer.
   */
  public static function reason(): string {
    $source = defined('WRT_INSPECTOR')
      ? 'WRT_INSPECTOR constant = ' . (WRT_INSPECTOR ? 'true' : 'false')
      : 'on by default';

    $environment = function_exists('wp_get_environment_type')
      ? wp_get_environment_type()
      : 'unknown';

    return sprintf('%s · environment %s · %s', $source, $environment, self::CAPABILITY);
  }
}
