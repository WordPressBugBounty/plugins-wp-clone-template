=== Export Themes ===
Contributors: milardovich
Tags: themes, export, exporter, backup, clone
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export any installed theme as a .zip file you can upload to another WordPress site.

== Description ==

With this plugin you can export any installed theme as a `.zip` file and then install that
same theme on another server through the normal "Upload Theme" screen.

== Installation ==

1. Install and activate the plugin through the 'Plugins' menu in WordPress.
2. A new "Export" item appears under the "Appearance" menu.

== Usage ==

Go to Appearance > Export, pick the theme you want and press "Export". Your browser downloads
a `.zip` file. On the other site, go to Appearance > Themes > Add New > Upload Theme, choose
that file and press "Install Now".

== Frequently Asked Questions ==

= Who can export a theme? =

Users who can install themes (administrators on a single site, super admins on multisite).
An export contains the theme's full PHP source, so the plugin no longer settles for the
weaker `manage_options` check it used up to 2.12. You can change this with the
`wpct_capability` filter.

= Are development files included? =

`.git`, `.svn`, `.hg`, `node_modules` and `.DS_Store` are left out. Use the
`wpct_should_skip_file` filter to change that.

== Changelog ==

= 3.0 =
* Compatible with WordPress 7.0 and PHP 8.
* Fixed: the plugin could not be activated at all on PHP 8. The activation hook pointed at
  `install_wpct`, but the function is called `wpct_install_wpct`; an invalid callback is a
  fatal TypeError since PHP 8.
* Fixed: exporting died with "Path cannot be empty". The bundled PclZip library still used a
  PHP 4 style constructor, which PHP 8 no longer treats as a constructor, so the archive name
  was never set. Exports now use ZipArchive, and fall back to the maintained copy of PclZip
  that ships with WordPress.
* Fixed: removed the third argument of `define()`, dropped in PHP 8.
* Security: the export form had no nonce and no capability check on submit, so any logged-in
  visitor could be made to trigger an export through a crafted request. Both are enforced now.
* Security: the requested theme name was pasted straight into a filesystem path. It is now
  validated against the list of installed themes.
* Security: archives were written into the plugin's own folder and served by redirect, which
  left every export downloadable by anyone who guessed the URL. They are now built outside the
  web root, streamed to the browser and deleted immediately. Activating this version also
  deletes any archive left behind by an older one.
* Removed: `session_start()` and `ob_start()` on every request, which broke page caching and
  emitted notices.
* Removed: the bundled 5,700 line copy of PclZip.
* Fixed: the theme list no longer comes from a hand-rolled `readdir()` on a path guessed with
  `substr($_SERVER['SCRIPT_FILENAME'], 0, -19)`. It uses `wp_get_themes()`, so themes in a
  custom theme root are listed too, with their real names.
* Fixed: all output is escaped, and the form no longer relies on PHP short open tags.
* Added: proper internationalisation with the `wp-clone-template` text domain.
* Added: `wpct_capability` and `wpct_should_skip_file` filters.

= 2.12 =
* Typo fixed

= 2.1 =
* Warning fixed
* Language functions added

= 2.0 =
* Security bug fixed
* Old zip files are automatically deleted
* Better session management
* 100% WP 4.0 Compatible

= 1.5 =
* Permisson problems fixed.
* Warnings added.
* Directory structure changed.

== Screenshots ==

1. "Export" option in the Appearance menu.
2. The export screen.

== Upgrade Notice ==

= 3.0 =
Required update: 2.12 cannot be activated on PHP 8 and its export was broken. Also fixes a
missing CSRF check and stops leaving exported themes publicly downloadable.
