<?php

if (! defined('ABSPATH')) {
  exit;
}

class WRT_Inspector_Route {

  /**
   * Every hierarchy filter WordPress can fire, in get_query_template() naming.
   */
  const TYPES = [
    '404', 'archive', 'attachment', 'author', 'category', 'date', 'embed',
    'frontpage', 'home', 'index', 'page', 'paged', 'privacypolicy',
    'search', 'single', 'singular', 'tag', 'taxonomy',
  ];

  /**
   * Conditionals worth reporting, most specific first.
   */
  const CONDITIONALS = [
    'is_front_page', 'is_home', 'is_privacy_policy', 'is_page', 'is_single',
    'is_attachment', 'is_singular', 'is_post_type_archive', 'is_category',
    'is_tag', 'is_tax', 'is_author', 'is_date', 'is_archive', 'is_search',
    'is_404', 'is_paged', 'is_embed',
  ];

  private $template = '';
  private $hierarchy = [];

  public function capture_template(string $template): string {
    $this->template = $template;
    return $template; // never mutate
  }

  /**
   * Several hierarchy filters can fire for one request (page then singular,
   * etc.), so key by type instead of letting the last one win.
   */
  public function watch_hierarchy(): void {
    foreach (self::TYPES as $type) {
      add_filter("{$type}_template_hierarchy", function ($templates) use ($type) {
        $this->hierarchy[$type] = $templates;
        return $templates;
      });
    }
  }

  public function template(): string {
    return $this->template;
  }

  public function template_relative(): string {
    if (! $this->template) {
      return '';
    }
    return WRT_Inspector_Trace::theme_relative($this->template);
  }

  public function hierarchy(): array {
    return $this->hierarchy;
  }

  /**
   * Every candidate WordPress considered, in fire order, deduped.
   * The winner is flagged so the panel can mark it.
   */
  public function candidates(): array {
    $winner = $this->template ? basename($this->template) : '';
    $seen   = [];
    $out    = [];

    foreach ($this->hierarchy as $type => $templates) {
      foreach ((array) $templates as $candidate) {
        if (isset($seen[$candidate])) {
          continue;
        }
        $seen[$candidate] = true;
        $out[] = [
          'name'   => $candidate,
          'type'   => $type,
          'winner' => $candidate === $winner,
        ];
      }
    }

    return $out;
  }

  /**
   * True when the rendered template is not one the hierarchy proposed —
   * a plugin or a template_include filter took over.
   */
  public function resolved_outside_hierarchy(): bool {
    if (! $this->template) {
      return false;
    }
    foreach ($this->candidates() as $candidate) {
      if ($candidate['winner']) {
        return false;
      }
    }
    return true;
  }

  public function conditionals(): array {
    $out = [];
    foreach (self::CONDITIONALS as $conditional) {
      if (function_exists($conditional) && call_user_func($conditional)) {
        $out[] = $conditional;
      }
    }
    return $out;
  }

  public function object_id(): ?int {
    $id = get_queried_object_id();
    return $id ? (int) $id : null;
  }

  /**
   * The post ID, only when there genuinely is a post context.
   * A category archive has a queried object ID, but it is a term.
   */
  public function post_id(): ?int {
    if (! is_singular()) {
      return null;
    }
    $id = get_queried_object_id();
    return $id ? (int) $id : null;
  }

  public function post_type(): string {
    $object = get_queried_object();
    if ($object instanceof WP_Post) {
      return $object->post_type;
    }
    if ($object instanceof WP_Post_Type) {
      return $object->name;
    }
    return '';
  }

  public function object_label(): string {
    $object = get_queried_object();
    if ($object instanceof WP_Post) {
      return 'WP_Post · ' . $object->post_type;
    }
    if ($object instanceof WP_Term) {
      return 'WP_Term · ' . $object->taxonomy;
    }
    if ($object instanceof WP_Post_Type) {
      return 'WP_Post_Type · ' . $object->name;
    }
    if ($object instanceof WP_User) {
      return 'WP_User';
    }
    return $object ? get_class($object) : 'none';
  }

  /**
   * Catches "editor picked a template file that no longer exists in the theme".
   */
  public function assigned_template(?int $post_id): string {
    return $post_id
      ? (string) get_post_meta($post_id, '_wp_page_template', true)
      : '';
  }

  /**
   * True when a template is assigned in the editor but absent from the theme.
   */
  public function assigned_template_missing(?int $post_id): bool {
    $assigned = $this->assigned_template($post_id);
    if (! $assigned || $assigned === 'default') {
      return false;
    }
    return locate_template($assigned) === '';
  }
}
