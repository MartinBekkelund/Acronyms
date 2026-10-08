# Acronyms

A WordPress plugin that automatically wraps the first occurrence of defined acronyms in `<abbr>` tags with their full meaning as the `title` attribute.

## Features

- Automatically adds `<abbr title="Full Meaning">ACRONYM</abbr>` to post and page content
- Only the first occurrence of each acronym per post/page is wrapped
- Per-acronym case sensitivity setting
- Word boundary matching prevents replacement inside normal words (e.g., "NASA" won't match in "NASALLY")
- Skips content inside `<a>`, `<code>`, `<pre>`, `<script>`, `<style>`, and existing `<abbr>` elements
- Respects manually-placed `<abbr>` elements by the post author
- Works with the block editor (Gutenberg), classic editor, and plain text editor
- Includes `<abbr>` tags in RSS feed output
- Touch-friendly tooltip on mobile devices
- Configurable post type support
- Central list of common acronyms, used together with your own
- Simple admin interface under Settings > Acronyms

## Requirements

- WordPress 6.7 or later
- PHP 7.4 or later

## Installation

1. Download or clone this repository into your `wp-content/plugins/` directory
2. Activate the plugin through the WordPress admin Plugins screen
3. Go to **Settings > Acronyms** to add your acronyms

## Usage

### Managing Acronyms

Navigate to **Settings > Acronyms** in the WordPress admin. The "Manage Acronyms" tab lets you:

- **Add** an acronym with its full meaning and case sensitivity preference
- **Edit** existing acronyms
- **Delete** acronyms you no longer need
- **Search** through your acronym list

### Settings

The "Settings" tab lets you choose which post types the plugin applies to. By default, it processes Posts and Pages.

### Central List

The plugin comes with a central list of common acronyms ([`data/central-acronyms.json`](data/central-acronyms.json)), shown together with your own in the acronym list:

- If you add an acronym with the same text, yours is used, and the central one is marked "Overridden by local".
- Central acronyms can't be edited or deleted, but you can turn each one off or on for your site.

Under **Settings**, you can turn on a daily fetch of the latest central list. It is off by default. The default source is the file in this repository, but you can enter your own URL, for example to share one list across several sites. If a fetch fails, the last good copy is used.

The file format:

```json
{
	"version": 1,
	"acronyms": [
		{ "acronym": "HTML", "title": "HyperText Markup Language", "case_sensitive": true }
	]
}
```

The URL must use https, the file can be at most 1 MB, and it can hold at most 5000 acronyms.

### Manual `<abbr>` Elements

If you manually add an `<abbr>` element for an acronym in your post content, the plugin will skip that acronym entirely for that post. This lets you take full control of specific acronyms when needed.

## Caching Note

If you use a page caching plugin (WP Super Cache, WP Rocket, etc.), cached pages will not immediately reflect changes to your acronym list. The updated acronyms will appear once the cache expires or is cleared.

## License

This plugin is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Author

[Martin Koksrud Bekkelund](https://www.nivlheim.no/)
