=== Seedcast Bulletin Library ===
Contributors: seedcast
Tags: church, bulletin, ministry, announcements, sermons
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Seedcast explains Sunday. Dated service pages that gather programs, announcements, handouts, and more, and grow through the week.

== Description ==

Seedcast Bulletin Library builds a dated service page for every Sunday your church gathers. Each service page pulls together the programs meeting that day, the announcements that apply that week, and any printable handouts your congregation needs to take home. Every Sunday tells a story - Seedcast helps you preserve and share it, so a first-time visitor can understand your church before they ever walk through the door.

The plugin answers the question every visitor is already asking: "What is it actually like to be part of this church?" One dated page at a time, week after week, it captures the complete story of your church's ministry and makes it discoverable.

**Features**

* **Service pages** with a configurable heading, featured image or video, a service overview (the standard WordPress editor), and structured sections for the day.
* **Programs**: the parallel experiences at your gathering (Sunday School, Youth Program, Main Service, Kids Church). Title, time, description, optional link, optional image.
* **Announcements**: durational notices with time, location, contact info, and optional images. Announcements attach to services by week automatically.
* **Handouts**: printable materials attached to a Program on a specific service, with a link that renders alongside that Program on the service page.
* **Contacts**: a shared name/email/phone list with autocomplete for announcement contacts.

Announcement, program, and handout copies freeze when a service is saved, so historical services stay stable even as you update the sources. When a source diverges from its saved copy, the editor shows an "Update" prompt on the affected copy so you can pull the source's current fields in with one click.

**Structured data for search and AI**

Every service page emits full JSON-LD - Event schema with startDate, datePublished, dateModified, location, organizer, and (when set) a nested VideoObject and subEvent list built from that day's Programs. Search engines can index each Sunday as a distinct event, and AI assistants can answer questions like "what's happening at [church] this Sunday?" with structured, first-party data straight from your site.

**Shared infrastructure**

Bulletin Library provides shared foundation used by companion Seedcast plugins (Sermon Library, Praise Report, Sing, Help, Hospitality, and others):

* Theme tokens for consistent styling across the suite
* Submission engine used by Praise Report and future plugins
* A sections filter that lets any Seedcast plugin contribute content to service pages

**Shortcodes**

* `[sunday_services limit="6" columns="3" start_date="2026-01-01"]` grid of service cards
* `[sunday_next_service]` featured card for the next upcoming service
* `[sunday_announcements]` currently-active announcements as a grid

**Companion plugins**

Bulletin Library works alongside other Seedcast plugins. Sermon Library adds sermon posts that render on the service page automatically. Praise Report, Sing, Help, Hospitality, and Visitor Card each extend the service page with their own section. The companion plugins are distributed separately and integrate through the shared sections filter.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/seedcast-bulletin-library/`, or install through the WordPress plugin screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Visit **Bulletin Library** in the admin sidebar to add your first service.

== Frequently Asked Questions ==

= Do I need to add code to make this work? =

No. Everything is manageable from the WordPress admin. Three shortcodes are available for embedding content on other pages (see Description).

= Does this replace my existing content? =

No. Bulletin Library adds new post types (Services, Announcements, Programs) alongside your existing content. Regular pages and posts are untouched.

= What if I only want the shared infrastructure and not the service pages? =

The service pages feature can be turned off from the Bulletin Library dashboard. Shared infrastructure remains available for other Seedcast plugins.

= Does the plugin send data to any external service? =

No.

== Changelog ==

= 3.7.0 =
* Every setting the shared Seedcast library stores was renamed to a longer, more distinctive prefix, so it cannot collide with another plugin that happened to pick the same short one. Your church details, service times, theme and spam settings are carried across automatically the first time the plugin loads; nothing needs re-entering.
* Service page sections and the sidebar are now escaped against an allow list on the way out, instead of being printed as-is. Visitor Card forms and share icons are unaffected: the list admits the form fields and icons the suite actually uses, and removes scripts and event handlers.
* Submitted form values are cleaned once, in a single place, before any plugin sees them. A value containing a backslash is no longer damaged on its way into the database.
* The church details published for search engines can no longer be broken by a stray tag in a setting.

= 3.6.4 =
* Program handouts strip no longer stretches with empty tan space. Reworked so the `.scbl-program__inner` area grows (`flex: 1`), pushing the handouts strip down to the bottom of the card as a compact block that hugs its content.

= 3.6.3 =
* Program handouts strip now anchors to the bottom of taller cards in a row (`flex: auto` on `.scbl-program__handouts`).

= 3.6.2 =
* Program body preserves HTML. Save switched from `sanitize_textarea_field` (which stripped tags) to `wp_kses_post`.
* Persistent "The program has changed" notice fixed. `source_has_changed` now normalises both sides through `wp_kses_post` so legacy stripped copies re-sync on the first Update click.
* Program body renders in a `<div>` wrapper with `wpautop` and `do_shortcode`, so paragraphs and shortcodes both work.

= 3.6.1 =
* Handouts styling: bold "Handouts:" label on its own line, pill-shaped download buttons with accent hover, light-background footer (`--sc-color-bg-subtle`) with a top border, wrap for multiple handouts.

= 3.6.0 =
* Switched service description back to the standard WordPress `post_content` field with its normal editor. Restored `'editor'` support on the Service CPT and removed the custom Service Update metabox.
* Description renders below the featured image/video via `the_content()`. Legacy services stored to `_scbl_service_description` meta fall back automatically.
* Orphan handouts (no linked Program on this service) now render in a standalone "Handouts" section under Today's Programs.

= 3.5.9 =
* Fixed persistent "The announcement has changed" notice. Save was storing the submitted (duplicate) image ID as `image_source_id`; now stores the correctly computed value so `source_has_changed` reports true only when the source has actually diverged.
* Restored metabox handle-actions (Move up / Move down / Show or hide panel) on Bulletin Library CPT screens.

= 3.5.8 =
* Added `scbl_service_description` to the default metabox order so Today's Handouts renders in its intended position.
* Stronger defensive CSS for wp_editor when sites strip parts of wp-admin.css.

= 3.5.7 =
* Reverted Handouts save handler to the pre-3.5.4 behaviour that was known to work in the field.

= 3.5.4 - 3.5.6 =
* JS enqueue improvements (media-editor dependency, robust row builder), plain-textarea test for description rendering, various visual and copy fixes.

= 3.5.3 =
* Fixed dead "Choose image" button on Bulletin Settings (settings-page JS enqueue guard was too tight).
* "Browse Services" restyled as a pill button matching Copy Link; renamed to "Browse Weekly Services" in 3.5.6.
* Defensive admin CSS scoped to Bulletin Library post types.

= 3.5.0 - 3.5.2 =
* Plugin Check pass: prefixed all template globals with `scbl_`. Rebrand to Seedcast Bulletin Library.
* SEO/AEO schema enrichment: `datePublished`, `dateModified`, nested `VideoObject`, `subEvent` list built from Programs.
* Accessibility pass: removed redundant `role="main"`, full ARIA combobox for contact autocomplete.
* Migrated 93 inline `style=` attributes to `assets/css/scbl-admin.css`. Removed duplicate/unused CSS rules.
* Query efficiency: cache priming (`update_meta_cache`, `_prime_post_caches`) on program and announcement suggestion queries.
* Security scan clean: all save handlers verified (nonce + capability + per-field sanitisation).
* Assorted bug fixes: announcement featured image save, image change detection on copies, JS notice selector after inline-style migration.

= 3.4.0 - 3.4.3 =
* Description meta field separated from `post_content` (later reverted in 3.6.0 - see above).
* Visitor Card sidebar integration via the `scbl_service_sidebar_end` action.
* Sidebar column width tuning.

= 3.3.x =
* Announcement, Program, and Handout copy system introduced. Copies freeze when a service is saved; source-diverged copies show an Update prompt.

= 3.0.0 =
* Initial rebrand from Living Bulletin to Seedcast Bulletin Library. Namespace, constants, post types, meta keys, options, admin menu, filters, CSS classes, JS filenames, template folder, and text domain all renamed.

== Upgrade Notice ==

= 3.7.0 =
Settings stored by the shared Seedcast library move to a new, longer prefix. They are carried across for you on the first page load after upgrading, so nothing needs re-entering. Includes output escaping and input handling improvements.
