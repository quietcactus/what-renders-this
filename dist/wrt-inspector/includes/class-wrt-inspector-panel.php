<?php

if (! defined('ABSPATH')) {
  exit;
}

class WRT_Inspector_Panel {

  const NODE_ID = 'wrt-inspector';

  public function enqueue(): void {
    wp_enqueue_style(
      'wrt-inspector',
      WRT_INSPECTOR_URL . 'assets/inspector.css',
      [],
      WRT_INSPECTOR_VERSION
    );

    wp_enqueue_script(
      'wrt-inspector',
      WRT_INSPECTOR_URL . 'assets/inspector.js',
      [],
      WRT_INSPECTOR_VERSION,
      true
    );
  }

  /**
   * @param WP_Admin_Bar        $wp_admin_bar
   * @param WRT_Inspector_Route  $route
   */
  public function add_node($wp_admin_bar, WRT_Inspector_Route $route): void {
    $template = $route->template_relative();
    $label    = $template ? basename($template) : 'no template';
    $id       = $route->object_id();

    $wp_admin_bar->add_node([
      'id'    => self::NODE_ID,
      'title' => '<span class="wrt-insp-bar-glyph">&#9639;</span> ' . esc_html($label),
      'href'  => '#wrt-inspector-panel',
      'meta'  => [
        'class' => 'wrt-insp-bar-node',
        'title' => $id
          ? sprintf('What Renders This — %s · ID %d', $template, $id)
          : sprintf('What Renders This — %s', $template),
      ],
    ]);
  }

  public function render(array $context): void {
    $open   = WRT_Inspector_State::param(WRT_Inspector_State::PARAM_PANEL);
    $values = $context['with_values'];

    $values_url = $values
      ? remove_query_arg(WRT_Inspector_State::PARAM_VALUES)
      : add_query_arg(WRT_Inspector_State::PARAM_VALUES, '1');

    ?>
    <div id="wrt-inspector-panel" class="wrt-insp<?php echo $open ? ' is-open' : ''; ?>" role="dialog" aria-label="What Renders This">
      <div class="wrt-insp-head">
        <span class="wrt-insp-title"><?php echo esc_html($context['template'] ? basename($context['template']) : 'no template'); ?></span>
        <span class="wrt-insp-actions">
          <button type="button" class="wrt-insp-btn wrt-insp-copy-context">Copy context</button>
          <button type="button" class="wrt-insp-btn wrt-insp-close" aria-label="Close inspector">&times;</button>
        </span>
      </div>

      <div class="wrt-insp-body">
        <?php
        $this->section_route($context);
        $this->section_template($context);
        $this->section_acf($context, $values, $values_url);
        $this->section_partials($context);
        ?>
        <div class="wrt-insp-foot">
          <span class="wrt-insp-dim">Gate:</span> <?php echo esc_html($context['gate_reason']); ?><br>
          <span class="wrt-insp-dim">Snapshot boundary:</span> files included after <code>wp_footer</code> are not listed.
        </div>
      </div>

      <textarea class="wrt-insp-report" readonly aria-hidden="true" tabindex="-1"><?php echo esc_textarea($this->report($context)); ?></textarea>
    </div>
    <?php
  }

  private function section_route(array $context): void {
    ?>
    <div class="wrt-insp-sec">Route</div>
    <?php
    $conditionals = $context['conditionals']
      ? implode(' · ', $context['conditionals'])
      : 'none matched';

    $this->row('Matches', esc_html($conditionals));
    $this->row('Object', esc_html($context['object']));
    $this->row('Object ID', $context['object_id'] ? esc_html((string) $context['object_id']) : '<span class="wrt-insp-dim">none</span>');

    if ($context['post_type']) {
      $this->row('Post type', esc_html($context['post_type']));
    }
  }

  private function section_template(array $context): void {
    ?>
    <div class="wrt-insp-sec">Template</div>
    <?php
    if ($context['template']) {
      $this->row(
        'Resolved',
        '<span class="wrt-insp-win">' . esc_html($context['template']) . '</span>'
        . $this->copy_button($context['template'])
      );
    } else {
      $this->row('Resolved', '<span class="wrt-insp-dim">not captured — template_include did not fire</span>');
    }

    if ($context['post_id']) {
      if ($context['assigned'] && $context['assigned'] !== 'default') {
        $assigned = esc_html($context['assigned']);
        if ($context['assigned_missing']) {
          $assigned .= ' <span class="wrt-insp-warn">missing from theme</span>';
        }
        $this->row('Assigned', $assigned);
      } else {
        $this->row('Assigned', '<span class="wrt-insp-dim">none — resolved by hierarchy</span>');
      }
    }

    if ($context['candidates']) {
      $list = '<div class="wrt-insp-list">';
      foreach ($context['candidates'] as $candidate) {
        $class = $candidate['winner'] ? 'wrt-insp-win' : 'wrt-insp-dim';
        $list .= '<div class="' . $class . '">'
          . esc_html($candidate['name'])
          . ($candidate['winner'] ? ' &larr;' : '')
          . '</div>';
      }
      $list .= '</div>';
      $this->row('Considered', $list);
    }

    if ($context['outside_hierarchy']) {
      $this->row('Note', '<span class="wrt-insp-warn">resolved outside the hierarchy — a plugin or template_include filter took over</span>');
    }
  }

  private function section_acf(array $context, bool $values, string $values_url): void {
    ?>
    <div class="wrt-insp-sec">
      ACF groups
      <?php if ($context['acf_available'] && $context['has_post_context']) : ?>
        <a class="wrt-insp-toggle" href="<?php echo esc_url($values_url); ?>"><?php echo $values ? 'hide values' : 'show values'; ?></a>
      <?php endif; ?>
    </div>
    <?php

    if (! $context['acf_available']) {
      $this->row('', '<span class="wrt-insp-dim">ACF is not active</span>');
      return;
    }

    if (! $context['has_post_context']) {
      $this->row('', '<span class="wrt-insp-dim">no post context &mdash; ACF location rules do not apply</span>');
      return;
    }

    if (! $context['groups']) {
      $this->row('', '<span class="wrt-insp-dim">no field groups match this post</span>');
      return;
    }

    foreach ($context['groups'] as $group) {
      $meta = $group['json']
        ? esc_html($group['json']) . $this->copy_button($group['json'])
        : '<span class="wrt-insp-dim">no local JSON file</span>';

      $meta .= ' <span class="wrt-insp-dim">' . count($group['fields']) . ' fields</span>';

      $body = $meta;

      if ($values) {
        $body .= '<div class="wrt-insp-list">';
        foreach ($group['fields'] as $field) {
          $body .= '<div><span class="wrt-insp-dim">' . esc_html($field['name']) . '</span> '
            . esc_html((string) $field['value']) . '</div>';
        }
        $body .= '</div>';
      } else {
        $names = wp_list_pluck($group['fields'], 'name');
        if ($names) {
          $body .= '<div class="wrt-insp-list wrt-insp-dim">' . esc_html(implode(', ', $names)) . '</div>';
        }
      }

      $this->row($group['title'], $body);
    }
  }

  private function section_partials(array $context): void {
    $count = count($context['partials']);
    ?>
    <div class="wrt-insp-sec">Partials &middot; <?php echo (int) $count; ?> <span class="wrt-insp-dim">first-inclusion order</span></div>
    <?php

    if (! $context['traced']) {
      $this->row('', '<span class="wrt-insp-dim">no baseline snapshot — template_redirect did not fire</span>');
      return;
    }

    if (! $count) {
      $this->row('', '<span class="wrt-insp-dim">no theme files included after template_redirect</span>');
      return;
    }

    $list = '<div class="wrt-insp-list">';
    foreach ($context['partials'] as $index => $partial) {
      $list .= '<div><span class="wrt-insp-dim">' . ((int) $index + 1) . '.</span> '
        . esc_html($partial)
        . $this->copy_button($partial)
        . '</div>';
    }
    $list .= '</div>';

    $this->row('Chain', $list);
  }

  private function row(string $key, string $value): void {
    ?>
    <div class="wrt-insp-row">
      <span class="wrt-insp-k"><?php echo esc_html($key); ?></span>
      <span class="wrt-insp-v"><?php echo $value; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
    </div>
    <?php
  }

  private function copy_button(string $value): string {
    return ' <button type="button" class="wrt-insp-mini" data-wrt-copy="' . esc_attr($value) . '" aria-label="Copy path">copy</button>';
  }

  /**
   * The whole report as markdown. One button, one paste — this is what turns a
   * BugHerd URL into a file list before an editor is even opened.
   */
  private function report(array $context): string {
    $lines = [];

    $lines[] = '## Page context';
    $lines[] = 'URL       ' . $context['url'];
    $lines[] = 'Route     ' . ($context['conditionals'] ? implode(' · ', $context['conditionals']) : 'none matched')
      . ($context['post_type'] ? ' · post_type=' . $context['post_type'] : '')
      . ($context['object_id'] ? ' · ID ' . $context['object_id'] : '');
    $lines[] = 'Template  ' . ($context['template'] ?: '(not captured)');

    if ($context['post_id']) {
      $assigned = $context['assigned'] && $context['assigned'] !== 'default'
        ? $context['assigned'] . ($context['assigned_missing'] ? '  (MISSING FROM THEME)' : '')
        : '(none — resolved by hierarchy)';
      $lines[] = 'Assigned  ' . $assigned;
    }

    if ($context['outside_hierarchy']) {
      $lines[] = 'Note      resolved outside the template hierarchy';
    }

    $lines[] = '';
    $lines[] = '## ACF groups';

    if (! $context['acf_available']) {
      $lines[] = 'ACF is not active.';
    } elseif (! $context['has_post_context']) {
      $lines[] = 'No post context — ACF location rules do not apply.';
    } elseif (! $context['groups']) {
      $lines[] = 'No field groups match this post.';
    } else {
      foreach ($context['groups'] as $group) {
        $lines[] = sprintf(
          '- %s  %s  (%d fields)',
          $group['title'],
          $group['json'] ?: '(no local JSON)',
          count($group['fields'])
        );
        foreach ($group['fields'] as $field) {
          $lines[] = $context['with_values']
            ? sprintf('    %s: %s', $field['name'], $field['value'])
            : sprintf('    %s (%s)', $field['name'], $field['type']);
        }
      }
    }

    $lines[] = '';
    $lines[] = '## Partials, first-inclusion order';

    if (! $context['partials']) {
      $lines[] = 'None.';
    } else {
      foreach ($context['partials'] as $index => $partial) {
        $lines[] = ((int) $index + 1) . '. ' . $partial;
      }
    }

    return implode("\n", $lines) . "\n";
  }
}
