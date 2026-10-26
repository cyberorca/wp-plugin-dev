=== Firefox Throbber Suggestion ===
Contributors: yoursite
Tags: firefox, userchrome, suggestion
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 2.0.0
License: GPLv2 or later

A small, dismissible bottom-right chip suggesting a custom Firefox
tab-loading icon (userChrome.css tweak). Nothing is installed
automatically, visitors always download and run the bundled script
themselves.

== Description ==

Shows a small chip in the bottom-right corner of every frontend page:

> Want a cleaner tab-loading icon in Firefox?
> [Yes] [No]

- **No** (or the × in the corner) dismisses it. Remembered in the
  visitor's browser so it won't nag them again on future page loads.
- **Yes** downloads the bundled `install.sh` and reveals a one-line
  Terminal command to paste, plus a link to view the script's source
  before running it. Also remembered, so it won't ask again once done.

No shortcode or page editing needed, it just appears site-wide once
the plugin is active and enabled.

The script itself (bundled in `assets/install.sh`) only ever touches
the visitor's own Firefox profile, requires them to run it explicitly
in their own Terminal, and prints exactly what it changes before
changing it (supports `--dry-run`). It's never executed from the
page, the browser cannot do that, by design.

== Settings ==

Under **Settings → Throbber Suggestion** in WP Admin:

- **Show the chip** — on/off toggle for the whole feature
- **Question text** — the main line shown
- **Sub text** — the smaller line under it
- **"Yes" button label** / **"No" button label**

== Installation ==

1. In WordPress Admin, go to Plugins → Add New → Upload Plugin.
2. Upload this plugin's zip file.
3. Activate it.
4. Visit Settings → Throbber Suggestion to adjust wording or turn it
   off, if needed. It's on by default once activated.

== Changelog ==

= 2.0.0 =
Switched from a manually-placed shortcode to an automatic, site-wide
bottom-right chip. Added a Settings page for wording and an on/off
toggle. Added dismissal persistence (localStorage) so the chip doesn't
reappear on every page once a visitor has responded to it.

= 1.0.0 =
Initial release (shortcode-based).
