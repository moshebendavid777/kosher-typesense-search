=== Kosher Typesense Search ===
Contributors: kosher
Tags: search, typesense, ajax, analytics
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 3.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Typesense powered search, filtering, autocomplete, indexing, synonym sync, and search analytics for Kosher.com content.

== Description ==

Kosher Typesense Search provides a WordPress search interface backed by Typesense. It includes frontend shortcodes, an authenticated AJAX proxy, indexing helpers, WP-CLI commands, synonym syncing from ACF options, and admin search analytics.

The plugin supports the recipes, articles, episodes, shows, and menus post types. Menus are indexed into the live_menus collection from the kosher-theme menu builder data.

== Configuration ==

Define Typesense credentials in wp-config.php or the environment:

* KOSHER_TYPESENSE_HOST
* KOSHER_TYPESENSE_PROTOCOL
* KOSHER_TYPESENSE_PORT
* KOSHER_TYPESENSE_COLLECTION_PREFIX
* KOSHER_TYPESENSE_SEARCH_API_KEY
* KOSHER_TYPESENSE_ADMIN_API_KEY

Generic TYPESENSE_* constants are also supported.

Remote vendor assets are not loaded by this plugin. Bundle or register Tom Select, Swiper, Font Awesome, and Bootstrap Icons locally in the theme/site integration when those UI features are enabled.

== Shortcodes ==

Use [kosher_typesense_search] for the default search form.

Attributes:

* template: default, header, or filter
* placeholder: custom input placeholder
* target: optional search target

== WP-CLI ==

Use wp kosher-typesense init_collections to recreate Typesense collections.
Use wp kosher-typesense reindex to rebuild all supported collections.
Use wp kosher-typesense reindex --c=menus to rebuild live_menus only.
Use wp kosher-typesense update_schema to patch schemas without deleting documents.
Use wp kosher-typesense synonym_update --collection=recipes to sync synonyms for one collection.

The legacy wp typesense command is kept as an alias.

== Changelog ==

= 3.1.0 =
* Renamed plugin namespace and assets to kosher.
* Added live_menus schema and indexing support for the menus post type.
* Cleaned old AP Typesense and backup files from the distributable plugin.
* Added safer config handling for WordPress.org submission.
