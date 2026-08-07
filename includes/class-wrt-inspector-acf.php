<?php

if (! defined('ABSPATH')) {
  exit;
}

class WRT_Inspector_ACF {

  const VALUE_MAX = 160;

  public static function is_available(): bool {
    return function_exists('acf_get_field_groups');
  }

  /**
   * Field groups whose location rules match this post.
   *
   * @param int|null $post_id
   * @param bool     $with_values Fetch raw field values as well as names.
   */
  public function groups(?int $post_id, bool $with_values = false): array {
    if (! self::is_available() || ! $post_id) {
      return [];
    }

    // Never hard-code acf-json/ — the path is filterable via
    // acf/settings/load_json and may include several directories.
    $paths = (array) acf_get_setting('load_json');
    $out   = [];

    foreach (acf_get_field_groups(['post_id' => $post_id]) as $group) {

      $json = '';
      foreach ($paths as $path) {
        $try = wp_normalize_path(trailingslashit($path) . $group['key'] . '.json');
        if (file_exists($try)) {
          $json = WRT_Inspector_Trace::theme_relative($try);
          break;
        }
      }

      $fields = function_exists('acf_get_fields') ? (acf_get_fields($group) ?: []) : [];
      $rows   = [];

      foreach ($fields as $field) {
        $row = [
          'name'  => isset($field['name']) ? $field['name'] : '',
          'type'  => isset($field['type']) ? $field['type'] : '',
          'value' => null,
        ];
        if ($with_values && $row['name'] !== '') {
          $row['value'] = self::stringify(get_field($row['name'], $post_id, false));
        }
        $rows[] = $row;
      }

      $out[] = [
        'title'  => isset($group['title']) ? $group['title'] : $group['key'],
        'key'    => $group['key'],
        'json'   => $json,
        'fields' => $rows,
      ];
    }

    return $out;
  }

  /**
   * Flatten a raw ACF value to one short, single-line string.
   */
  public static function stringify($value): string {
    if ($value === null || $value === '' || $value === []) {
      return '(empty)';
    }
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }
    if (is_scalar($value)) {
      $string = (string) $value;
    } else {
      $string = wp_json_encode($value);
      if ($string === false) {
        return '(unserializable)';
      }
    }

    $string = trim(preg_replace('/\s+/', ' ', $string));
    if (function_exists('mb_strlen') && mb_strlen($string) > self::VALUE_MAX) {
      return mb_substr($string, 0, self::VALUE_MAX) . '…';
    }
    if (strlen($string) > self::VALUE_MAX) {
      return substr($string, 0, self::VALUE_MAX) . '…';
    }
    return $string;
  }
}
