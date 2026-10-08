=== Acronyms ===
Contributors: mkbekkelund
Tags: acronyms, abbreviations, abbr, tooltip, accessibility
Requires at least: 6.7
Tested up to: 7.1
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically wraps the first occurrence of defined acronyms in abbr tags with their full meaning.

== Description ==

Acronyms is a lightweight WordPress plugin that scans your post and page content for defined acronyms and wraps the first occurrence of each in an `<abbr>` HTML element with the full meaning as the `title` attribute.

**Key features:**

* Only the first occurrence of each acronym per post is wrapped
* Per-acronym case sensitivity setting
* Word boundary matching prevents replacement inside normal words
* Skips content inside links, code blocks, and existing abbreviation elements
* Respects manually-placed `<abbr>` elements by the post author
* Works with the block editor, classic editor, and plain text editor
* Includes abbreviation tags in RSS feed output
* Touch-friendly tooltip on mobile devices
* Configurable post type support
* Comes with a central list of common acronyms, used together with your own
* Simple admin interface under Settings > Acronyms

== Installation ==

1. Upload the `acronym-tooltips` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > Acronyms to add your acronyms

== Frequently Asked Questions ==

= How does the plugin handle case sensitivity? =

Each acronym can be individually configured as case-sensitive or case-insensitive. By default, matching is case-sensitive. For example, if you add "HTML" as case-sensitive, it will only match "HTML" and not "html" or "Html".

= Will the plugin replace acronyms inside links or code blocks? =

No. The plugin skips content inside `<a>`, `<code>`, `<pre>`, `<script>`, `<style>`, and existing `<abbr>` elements.

= What happens if I manually add an abbr element in my post? =

If you manually wrap an acronym in an `<abbr>` element anywhere in your post, the plugin will skip that acronym entirely for that post. This lets you control how specific acronyms are presented.

= Does the plugin work with page caching? =

Yes. However, cached pages will not immediately reflect changes to your acronym list. Updated acronyms will appear once the cache expires or is cleared.

= How do abbreviation tooltips work on mobile? =

The plugin includes a lightweight JavaScript that shows a tap-to-reveal tooltip on touch devices. Tapping an abbreviated term shows the full meaning; tapping elsewhere dismisses it.

= What is the central list? =

The plugin comes with a list of common acronyms that is used together with your own. If you add an acronym with the same text, yours is used. You can turn off single central acronyms in the acronym list under Settings > Acronyms.

= Does the plugin contact external servers? =

Not by default. The central list is bundled with the plugin. If you turn on "Fetch updates" under Settings > Acronyms > Settings, see the External services section below.

= Which post types does the plugin support? =

By default, the plugin processes Posts and Pages. You can configure which post types it applies to in Settings > Acronyms > Settings.

== External services ==

This plugin can fetch an updated central acronym list from the internet. This is off by default and only happens if an administrator turns on "Fetch updates" under Settings > Acronyms > Settings.

When turned on, the site downloads a JSON file once a day, and when an administrator clicks "Fetch now". By default the file is fetched from GitHub: https://raw.githubusercontent.com/MartinBekkelund/Acronyms/main/data/central-acronyms.json. An administrator can enter another URL instead.

The plugin sends no data about your site, posts or visitors. The request is a normal web request, so the server receives your site's IP address and the WordPress user agent, like any other web request.

GitHub terms of service: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
GitHub privacy statement: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

== Changelog ==

= 1.1.0 =
* Central acronym list bundled with the plugin, used together with your own acronyms
* Local acronyms win over central ones with the same text
* Turn single central acronyms off or on for your site
* Optional daily fetch of the central list from GitHub or your own URL (off by default)
* Plugin slug and text domain changed to acronym-tooltips
* Front-end script loads with defer

= 1.0.0 =
* Initial release
* Acronym management (add, edit, delete, search)
* Content filtering with first-occurrence-only replacement
* Per-acronym case sensitivity
* Word boundary matching
* Protected element detection
* Manual abbr element detection
* RSS feed support
* Mobile touch tooltip
* Configurable post type support
