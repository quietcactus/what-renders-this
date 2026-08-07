<?php

if (! defined('ABSPATH')) {
  exit;
}

class WRT_Inspector_Gate {

  const CAPABILITY = 'manage_options';

  /**
   * Environment gate only. Says nothing about the current user.
   */
  public static function enabled(): bool {
    if (defined('WRT_INSPECTOR')) {
      return (bool) WRT_INSPECTOR;
    }
    if (! function_exists('wp_get_environment_type')) {
      return false;
    }
    return wp_get_environment_type() !== 'production';
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
    if (defined('WRT_INSPECTOR')) {
      return 'WRT_INSPECTOR constant = ' . (WRT_INSPECTOR ? 'true' : 'false');
    }
    if (! function_exists('wp_get_environment_type')) {
      return 'wp_get_environment_type() unavailable';
    }
    return 'wp_get_environment_type() = ' . wp_get_environment_type();
  }
}
