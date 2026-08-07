# What Renders This

A dev-only WordPress plugin that answers, on any frontend page: **which template file rendered this**, what the route and post context are, which ACF field groups are attached, and the ordered chain of theme partials that fired.

- **Version:** 1.0.0
- **Author:** Ian Garcia
- **License:** GPL-2.0-or-later
- **Requires PHP:** 7.4+
- **Requires WordPress:** 6.0+
- **Requires:** nothing. ACF is optional — the ACF section hides itself when ACF is not active.

## What it does

Adds an admin bar node whose label is the resolved template filename. Click it and a panel opens with the full picture:

| Section      | What it shows                                                                                     |
| ------------ | ------------------------------------------------------------------------------------------------- |
| **Route**    | Which `is_*` conditionals matched, the queried object class, the object ID, the post type.        |
| **Template** | The resolved template (theme-relative), the `_wp_page_template` assignment, and every candidate the hierarchy considered with the winner flagged. |
| **ACF**      | Field groups whose location rules match this post, each with its local-JSON file and field list.  |
| **Partials** | Every theme file included during the render, in first-inclusion order.                            |

**Copy context** puts the whole report on the clipboard as markdown. A BugHerd ticket carries a URL; this turns that URL into a file list before you open an editor, and the blob pastes straight into Claude Code as starting context.

```
## Page context
URL       https://staging.example.test/team/jane-smith/
Route     is_single · is_singular · post_type=team · ID 2044
Template  mytheme/single-team.php
Assigned  (none — resolved by hierarchy)

## ACF groups
- Team Bio  mytheme/acf-json/group_5f2a.json  (14 fields)
    bio_headline (text)
    bio_body (wysiwyg)

## Partials, first-inclusion order
1. mytheme/includes/include-tracker.php
2. mytheme/includes/include-banner.php
3. mytheme/includes/include-bio-tabs.php
```

## Installation

Either:

- **Upload the zip** — **Plugins → Add New → Upload Plugin**, pick `dist/wrt-inspector.zip`, activate.
- **Copy the folder** — drop `dist/wrt-inspector/` into `wp-content/plugins/`, activate **What Renders This** in **Plugins**.

There is nothing to configure. The plugin writes no options, creates no tables, and makes no database writes at all.

## The gate

The inspector only runs when **both** are true:

1. The environment gate opens, and
2. the current user has `manage_options`.

The environment gate is:

```php
if (defined('WRT_INSPECTOR')) {
  return (bool) WRT_INSPECTOR;   // explicit constant always wins, both ways
}
return wp_get_environment_type() !== 'production';
```

> **Set `WRT_INSPECTOR` explicitly on managed hosts.** WP Engine staging and dev installs commonly report `production` from `wp_get_environment_type()` unless `WP_ENVIRONMENT_TYPE` is set — the plugin would then do nothing on exactly the environments it exists for. Put this in `wp-config.php` on any non-production install:

```php
define('WRT_INSPECTOR', true);
```

The same constant set to `false` is a hard off switch, which is the safe way to leave the plugin installed on a box you are not sure about.

It also skips admin screens, AJAX, REST, cron and WP-CLI. v1 is frontend only.

## Usage

- **Admin bar node** — the label is the resolved template filename; the tooltip carries the object ID. Click to toggle the panel, `Esc` to close.
- **`?wrt-inspect=1`** — renders the panel already open, independent of the admin bar. Use this when a theme suppresses the bar or a user has turned it off.
- **`?wrt-inspect-values=1`** — adds ACF field *values* to the panel and to the copied report. Off by default, and the values are not fetched server-side at all unless the parameter is present. The **show values** link in the ACF section toggles it.

> Field values are live client content. Turning them on can put names, addresses or unpublished copy into anything you paste the report into. That is why it is opt-in per request rather than a saved preference.

## How it works

| File                                        | Responsibility                                                                                 |
| ------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `wrt-inspector.php`                          | Bootstrap — constants, `wrt_inspector_enabled()`, hook wiring on `init`.                        |
| `includes/class-wrt-inspector-gate.php`      | Environment + capability + request-type gate.                                                  |
| `includes/class-wrt-inspector-route.php`     | Captures `template_include` and every `{$type}_template_hierarchy` filter; route conditionals. |
| `includes/class-wrt-inspector-trace.php`     | Snapshots `get_included_files()` and diffs it to produce the partial chain.                    |
| `includes/class-wrt-inspector-acf.php`       | Resolves attached field groups and locates their local-JSON files.                             |
| `includes/class-wrt-inspector-panel.php`     | Admin bar node, panel markup, markdown report.                                                 |
| `includes/class-wrt-inspector-state.php`     | Composes the above and builds the context array once, at the end of the footer.                |
| `assets/inspector.css` / `inspector.js`     | Namespaced panel styling, toggle and clipboard behaviour.                                      |

### Why the partial tracer works the way it does

Themes pull partials with raw `include(locate_template('includes/include-banner.php'))`. WordPress fires no action for that, and `locate_template()` has no filter. There is nothing to hook.

So the tracer works one level below WordPress, at the PHP language level:

1. Snapshot `get_included_files()` at `template_redirect` priority `0`.
2. Snapshot it again at `wp_footer` priority `PHP_INT_MAX`.
3. Subtract, keep only files under the parent or child theme directory, normalise to theme-relative paths.

That catches `include`, `require`, `get_template_part()` and `include_module()` uniformly, because all four end up in the same PHP include table.

### Why the node is added on `wp_before_admin_bar_render`

`admin_bar_menu` fires from `_wp_admin_bar_init()` on `template_redirect` priority `0` — **before** `template_include` has resolved anything. A node built there would have an empty label. `wp_before_admin_bar_render` runs inside `wp_admin_bar_render()`, which is well after the template is known.

## Known limits

- **First-inclusion order, not render order.** `get_included_files()` lists each file once. A partial pulled in by six different modules appears a single time, at its first inclusion point. Exact ordering with repeat counts would need a logging wrapper around every `include` in the theme, which is a theme change and out of scope.
- **The footer is the boundary.** Files included after `wp_footer` are missed. The second snapshot hooks at `PHP_INT_MAX` so it runs last within the footer; `shutdown` would be more complete but fires after `</html>`, where the panel cannot be rendered.
- **Full-page caching.** If a staging environment serves a cached page, PHP never runs and the inline panel is whatever was cached. A stale panel is possible, and self-evidently stale.
- **Non-post contexts.** Archives, search and 404 have no post ID, so ACF location rules do not apply. The panel says so explicitly rather than rendering an empty section.

## Test matrix

Verify against the route types that actually differ:

- A page with a custom template assigned in the editor.
- A custom post type single whose template nests partials two or more levels deep.
- A second custom post type single, to confirm the hierarchy candidate list changes with it.
- The blog archive.
- Search results.
- A 404.
- **The gate on a real WP Engine staging install** — the assumption most likely to be wrong.

## Development

There is no build step — the source in `includes/` and `assets/` is what runs. `dist/` is a copy of the installable files plus a zip, regenerated by hand after a version bump:

```bash
rm -rf dist && mkdir -p dist/wrt-inspector
cp wrt-inspector.php README.md LICENCE dist/wrt-inspector/
cp -r includes assets dist/wrt-inspector/
pwsh -NoProfile -Command "Compress-Archive -Path 'dist/wrt-inspector' -DestinationPath 'dist/wrt-inspector.zip' -Force"
```

The zip must contain a single top-level `wrt-inspector/` directory. A flat zip unpacks loose into `wp-content/plugins/` and WordPress will not recognise it as a plugin.

## License

GPL-2.0-or-later. See [LICENCE](LICENCE).
