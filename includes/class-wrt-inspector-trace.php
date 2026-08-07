<?php

if (! defined('ABSPATH')) {
  exit;
}

class WRT_Inspector_Trace {

  private $baseline = [];
  private $taken    = false;

  public function snapshot(): void {
    $this->baseline = get_included_files();
    $this->taken    = true;
  }

  public function has_baseline(): bool {
    return $this->taken;
  }

  /**
   * Theme files included between the baseline snapshot and now, in
   * first-inclusion order.
   *
   * The theme pulls partials with raw include(locate_template(...)), which
   * fires no action and has no filter. Working one level below WordPress —
   * diffing PHP's own include table — catches include, require,
   * get_template_part() and include_module() uniformly.
   */
  public function partials(): array {
    if (! $this->taken) {
      return [];
    }

    $roots = self::roots();

    // array_diff preserves first-include order, which is the render order.
    $new = array_diff(get_included_files(), $this->baseline);

    $out = [];
    foreach ($new as $file) {
      $file = wp_normalize_path($file);
      foreach ($roots as $root) {
        if (strpos($file, $root . '/') !== 0) {
          continue;
        }
        $out[] = substr($file, strlen(dirname($root)) + 1);
        break;
      }
    }

    // NOTE: get_included_files() is unique-per-file. include-social.php pulled
    // by both the header and the footer appears once, at its first position.
    return $out;
  }

  /**
   * Absolute path to a theme-relative path (mytheme/includes/x.php).
   * Returns the path unchanged when it lives outside the theme directories.
   */
  public static function theme_relative(string $file): string {
    $file = wp_normalize_path($file);
    foreach (self::roots() as $root) {
      if (strpos($file, $root . '/') === 0) {
        return substr($file, strlen(dirname($root)) + 1);
      }
    }
    return $file;
  }

  private static function roots(): array {
    return array_unique([
      wp_normalize_path(get_stylesheet_directory()),
      wp_normalize_path(get_template_directory()),
    ]);
  }
}
