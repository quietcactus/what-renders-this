<?php

if (! defined('ABSPATH')) {
  exit;
}

class WRT_Inspector_State {

  const PARAM_PANEL  = 'wrt-inspect';
  const PARAM_VALUES = 'wrt-inspect-values';

  private $route;
  private $trace;
  private $acf;
  private $panel;

  public function __construct() {
    $this->route = new WRT_Inspector_Route();
    $this->trace = new WRT_Inspector_Trace();
    $this->acf   = new WRT_Inspector_ACF();
    $this->panel = new WRT_Inspector_Panel();
  }

  public function snapshot(): void {
    $this->trace->snapshot();
  }

  public function capture_template(string $template): string {
    return $this->route->capture_template($template);
  }

  public function watch_hierarchy(): void {
    $this->route->watch_hierarchy();
  }

  /**
   * The panel is reachable without the admin bar, so a theme that suppresses
   * the bar (or a user who turned it off) still has a way in.
   */
  public function should_render(): bool {
    return is_admin_bar_showing() || self::param(self::PARAM_PANEL);
  }

  public function enqueue_assets(): void {
    if (! $this->should_render()) {
      return;
    }
    $this->panel->enqueue();
  }

  public function add_node(): void {
    global $wp_admin_bar;
    if (! $wp_admin_bar instanceof WP_Admin_Bar) {
      return;
    }
    $this->panel->add_node($wp_admin_bar, $this->route);
  }

  public function render_panel(): void {
    if (! $this->should_render()) {
      return;
    }
    $this->panel->render($this->context());
  }

  /**
   * Everything the panel and the markdown report need, built once at the end
   * of the footer — the partial diff is meaningless before that point.
   */
  public function context(): array {
    $post_id     = $this->route->post_id();
    $with_values = self::param(self::PARAM_VALUES);

    return [
      'url'               => home_url(add_query_arg([])),
      'gate_reason'       => WRT_Inspector_Gate::reason(),
      'conditionals'      => $this->route->conditionals(),
      'object'            => $this->route->object_label(),
      'object_id'         => $this->route->object_id(),
      'post_id'           => $post_id,
      'post_type'         => $this->route->post_type(),
      'template'          => $this->route->template_relative(),
      'template_abs'      => $this->route->template(),
      'assigned'          => $this->route->assigned_template($post_id),
      'assigned_missing'  => $this->route->assigned_template_missing($post_id),
      'candidates'        => $this->route->candidates(),
      'outside_hierarchy' => $this->route->resolved_outside_hierarchy(),
      'acf_available'     => WRT_Inspector_ACF::is_available(),
      'has_post_context'  => (bool) $post_id,
      'with_values'       => $with_values,
      'groups'            => $this->acf->groups($post_id, $with_values),
      'partials'          => $this->trace->partials(),
      'traced'            => $this->trace->has_baseline(),
    ];
  }

  public static function param(string $key): bool {
    if (! isset($_GET[$key])) {
      return false;
    }
    $value = sanitize_text_field(wp_unslash($_GET[$key]));
    return $value !== '' && $value !== '0';
  }
}
