=== Seedcast Bulletin Library ===
Contributors: seedcast
Tags: church, bulletin, ministry, announcements, sermons
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A dated bulletin page for every Sunday: programs, announcements, handouts and sermons, gathered in one place and kept forever.

== Description ==

**Seedcast explains Sunday.**

Every week your church gathers and something happens that is worth keeping. Seedcast Bulletin Library turns that gathering into a page: the date, the message, the programs that met, the announcements that mattered that week, and the handouts people took home. Publish it once and it stays put. The week you are living now quietly becomes the archive a visitor reads next year.

It answers the question every visitor is already asking before they ever walk through your doors: what is it actually like to be part of this church?

= Built for whoever runs the bulletin =

Everything happens in the WordPress admin, in plain language. Create a service, choose the date, and the plugin offers you the programs and announcements that belong to that week. Click Add and they attach. There is no page builder to learn, no template to edit and no code to write, and the Services list tells you at a glance which Sundays are still missing something.

= What lives on a service page =

* **A dated heading**, so every Sunday has its own permanent URL and its own place in the archive.
* **A featured image or video.** Drop in a YouTube, Vimeo or direct MP4 URL and the video plays in place of the image. You choose whether the image sits above the overview, below it, or stays hidden.
* **A service overview** written in the normal WordPress editor. Prefer blocks? Turn the block editor on for services with one checkbox.
* **Today's Programs**, each with its time, description, image and optional link.
* **This Week's Announcements**, laid out as cards saying when each one happens, where, who to contact, and an event link.
* **Today's Handouts**, printable materials that render as download buttons beside the program they belong to.
* **A sermon**, when Sermon Library is installed, and any other section a companion Seedcast plugin contributes.
* **Share buttons** for Facebook, X, LinkedIn, email and copy link.

= Programs =

Programs are the parallel experiences happening at your gathering: Sunday School, Youth Program, Main Service, Kids Church. Write each one once, with a title, time, description, image and an optional link to the ministry page on your site. From then on it is offered to you every week, ready to attach with a single click.

= Announcements scheduled the way your church talks =

Every announcement starts with a frequency, and the editor asks only for what that frequency needs:

* **No set schedule** for notices that are not events: a sign-up, a call for volunteers.
* **One Time** for a single date.
* **Consecutive** for a run of days, like ten days of prayer.
* **Multiday** for one ministry that meets on several days, each with its own time. The men's group that meets on Wednesday at noon and again on Monday afternoon is one announcement, not two.
* **Staggered** for a handful of specific dates, like a quarterly worship night.
* **Recurring** for a regular rhythm: weekly on a chosen day, monthly, every other month, or the first, second, third or last weekday of the month.

The card says it in plain words, "Every Thursday · 7pm" or "Third Saturday of the month · 8am", and a Staggered announcement quietly drops the dates that have already passed.

An announcement is offered to every Sunday service from the week it is published through the week of its last date. A notice that runs for six weeks is waiting on six service pages without anyone remembering to add it, and you still decide, one click at a time, which Sundays it actually appears on. Each announcement can also carry a location, contact name, email and phone, an event link and its own image, and empty fields simply disappear from the card.

Contact names autocomplete from a shared contacts list, so the same ministry leader is spelled and reached the same way everywhere.

= History that stays true =

When you save a service, its announcements, programs and handouts are copied and frozen into that page. Change a program's description next spring and last autumn's services keep the wording that was actually true that day. When a source has moved on from a saved copy, the editor shows an "Update" prompt on the affected item so you can pull the current fields in deliberately, with one click, on the services where it matters.

= Structured data for search engines and AI assistants =

Every service page emits full JSON-LD: an Event with startDate, datePublished, dateModified, location and organizer, a nested VideoObject when a service video is set, a subEvent list built from that day's Programs, and BreadcrumbList markup. Search engines can index each Sunday as a distinct event, and AI assistants can answer "what is happening at this church on Sunday?" from structured, first-party data on your own domain instead of guessing from a PDF.

= Settings that keep it yours =

* Rename "Service" to Gathering, Meeting or whatever your church actually calls it, singular and plural.
* Set the archive heading, the intro paragraph and how many services show before paginating.
* Choose the URL slug before you publish.
* Set a fallback image for services without one of their own.
* Switch off the plugin's front-end CSS entirely if you would rather style everything in your theme. The markup and class names stay exactly the same.
* Show or hide the Visitor Card sidebar on service pages.

= Shortcodes =

Every one of these can be built in **Bulletin Library > Shortcodes** by picking options, with a button to copy the result.

* `[scbl_services limit="6" columns="3" start_date="2026-01-01"]` a grid of service cards. Attributes: limit (default 10), columns (1 to 4, default 3), start_date (YYYY-MM-DD, hides services before that date).
* `[scbl_next_service]` a featured card for the next upcoming service.
* `[scbl_announcements]` current and upcoming announcements as a grid.
* `[scbl_announcements grouped="true"]` the same cards under Upcoming and Ongoing headings, for a page that can replace an events calendar. Upcoming lists dated events soonest first and drops each one the day after it ends. Use `first="upcoming"` (the default) or `first="ongoing"` to choose which comes first. Both forms accept columns (1 to 4, default 3).
* `[seedcast_church_details]` your church name, address, service times, phone, email and a directions link, all read from one place so a move or a time change is a single edit.

= Part of the Seedcast suite =

Bulletin Library carries the shared foundation the rest of the Seedcast plugins build on: one set of church details, one theme token system so everything matches, a submission engine with spam protection, and a sections filter that lets any Seedcast plugin add its own content to a service page.

Sermon Library adds sermons that appear on the matching service automatically. Praise Report, Sing, Help, Hospitality and Visitor Card each contribute their own section. Install one or install them all: Bulletin Library works on its own, and the service page grows as you add companions.

= Privacy =

The plugin sends nothing to any external service. Your services, programs, announcements, handouts and contacts stay in your own WordPress database.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/seedcast-bulletin-library/`, or install through the WordPress plugin screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Visit **Bulletin Library** in the admin sidebar to add your first service.
4. Add your church details under **Seedcast > Church** so the shortcode and the search engine markup have something to work with.

== Frequently Asked Questions ==

= Do I need to add code to make this work? =

No. Everything is manageable from the WordPress admin. Four shortcodes are available if you want to embed content on other pages, and the Shortcode Generator builds them for you, but nothing requires them.

= Does this replace my existing content? =

No. Bulletin Library adds new post types (Services, Programs, Announcements) alongside your existing content. Your pages and posts are untouched.

= Do I have to re-enter announcements every week? =

No. Every announcement is offered to each Sunday service from the week it is published through the week of its last date. Attach it with one click, or leave it off a week where it does not apply. One with no end date keeps being offered until you unpublish it.

= Can one announcement cover a group that meets on different days? =

Yes. Choose Multiday and add a row for each day, each with its own time. For specific dates rather than weekdays, such as a quarterly gathering, choose Staggered.

= What happens to old services when I edit a program or announcement? =

Nothing, until you say so. Saved services hold a frozen copy, so history stays accurate. Where a source has changed, the editor shows an "Update" prompt on that item and you decide whether to pull the new wording in.

= Can I use the block editor for services? =

Yes. Services use the classic editor by default so the service editor stays visually consistent, and a single checkbox in the settings switches them to the block editor when you want embeds, columns or richer content in the overview.

= Can I call it something other than "Service"? =

Yes. Set your own singular and plural labels in the settings, and the admin menus, list tables and archive follow along.

= Will it match my theme? =

The plugin ships with light, token-based styling that sits comfortably in most themes, and every colour is driven by a shared token you can override. If you would rather write the styles yourself, switch the front-end CSS off in the settings and keep the markup exactly as it is.

= What if I only want the shared infrastructure and not the service pages? =

The service pages feature can be turned off from the Bulletin Library dashboard. The shared infrastructure stays available to the other Seedcast plugins.

= Do I need the other Seedcast plugins? =

No. Bulletin Library is complete on its own. Companion plugins add their own sections to the service page when you install them.

= Does the plugin send data to any external service? =

No.

== Screenshots ==

1. A published service page: dated heading, featured image or video, the service overview, the sermon, today's programs with handouts, this week's announcements, and the visitor card in the sidebar.
2. The Sunday Services archive, with your own heading and intro text above a grid of every service you have published.
3. Bulletin Library settings: archive display, editor choice, custom labels, URL slug, fallback image, front-end CSS and the shortcode reference.
4. The Services list, with a "Still missing" column that tells you which Sundays are complete before a visitor finds out for you.
5. Editing a service: service date, overview, featured image position, service video, and one-click suggestions for the programs, announcements and handouts that belong to that week.
6. Programs are written once and reused: Main Service, Kids Church, Kids Huddle, Abidey Babies.
7. Editing a program: description, time, optional link to the ministry page, and its own image.
8. The Announcements list, showing each announcement's schedule and whether it is active or still upcoming.
9. Editing an announcement: its frequency and schedule, location, contact details that autocomplete from your contacts list, and an event link.

== Changelog ==

= 3.8.0 =
* Announcements get a Scheduling box in place of Display period. Choose a frequency (No set schedule, One Time, Consecutive, Multiday, Staggered, or Recurring: weekly, monthly, every other month, or the first, second, third or last weekday of the month) and fill in only the dates and times it needs. Time now lives in Scheduling.
* Multiday covers one ministry that meets on several days of the week, each with its own time. Staggered covers a set of specific dates, each with its own time.
* Announcement cards describe the schedule in plain words, such as "Every Thursday · 7pm" or "Third Saturday of the month · 8am". Staggered dates that have already passed for that service's week are left off.
* An announcement is now offered to services from the week it is published through the week of its last date, or until it is unpublished when it has none. The `[scbl_announcements]` shortcode follows the same rule, so upcoming announcements now appear there too.
* Existing announcements become "No set schedule" with their dates and time unchanged, so their cards look the same. Nothing needs migrating, and existing service copies are not flagged as changed.
* The Announcements list shows a Schedule column in place of Start and End.
* New: `[scbl_announcements grouped="true"]` lists announcements under Upcoming and Ongoing, so one page of it can replace an events calendar. One Time, Consecutive and Staggered announcements are Upcoming, soonest first; everything else is Ongoing. `first="upcoming"` (the default) or `first="ongoing"` sets which comes first, and the first heading adds no space above itself, so the page editor controls it.
* New: a Shortcode Generator under Bulletin Library > Shortcodes builds any of the plugin's shortcodes from a few choices, in the same shape as the Sermon Library and Visitor Card generators.
* Fixed: in a row of announcement cards, a card shorter than its neighbour stretched its image area, leaving a tinted gap between the image and the text. On Elementor pages, an image wider than 16:9 no longer falls short of its frame.
* Announcement card images use the large size and keep their srcset, which escaping used to strip, so they stay sharp on high-resolution screens instead of being stretched from the 300px medium size.
* Fixed: the Expired filter on the Announcements list also listed ongoing announcements.
* Fixed: announcement and program descriptions in the service editor showed raw HTML tags. They now read as plain text there, and the service page keeps its formatting.
* Programs on the service page have a little more room between them, and the archive intro paragraph uses the full content width.
* Fixed: `[scbl_next_service]` works out which service is next in the site's timezone, so this Sunday's service no longer gives way to next week's in the afternoon.

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

= 3.8.0 =
Announcements get a Scheduling box with frequencies such as One Time, Multiday and Recurring. Existing announcements carry over as "No set schedule" with their dates and time, so nothing changes until you edit them.

= 3.7.0 =
Settings stored by the shared Seedcast library move to a new, longer prefix. They are carried across for you on the first page load after upgrading, so nothing needs re-entering. Includes output escaping and input handling improvements.
