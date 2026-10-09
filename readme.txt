=== Ruvelo Feedback ===
Contributors: francoisbultez
Tags: feedback, roadmap, changelog, feature requests, voting
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A free feedback board, public roadmap and changelog. People suggest and vote, you ship, and everyone who asked is told.

== Description ==

Your users ask for things and vote. You plan, build and ship. When it ships, everyone who asked hears about it, by email and in a "What's new" bell on your site. It's the loop that Canny, Featurebase and Beamer sell as a subscription, as one free plugin that keeps everything in your own WordPress.

**A board people actually use.** Requests with one-click votes, filtered by status, category and tag, searchable, sorted by top, new or trending. Pin what matters. Anyone can read; posting and voting need an account, or an email address if you allow guests.

**Fewer duplicates.** While someone types a title, similar requests appear with their votes, so they vote instead of posting the same idea again. Without JavaScript, the form shows them before posting. When duplicates slip through, merge them: votes and comments carry over, nobody is counted twice, and the old address redirects.

**Every request tells its story.** Status history, the team's public notes, core WordPress comments with a "Team" badge for your staff, and a link to the changelog entry it shipped in.

**A public roadmap** of what's planned, in progress and shipped, straight from the board.

**A changelog with an RSS feed.** Entries are ordinary posts with labels (New, Improved, Fixed), featured images and the requests they answer. Publish one and its requests are marked Shipped.

**"What's new" on your site.** A block for your header that shows each visitor how many updates they haven't seen, with the latest in a dropdown.

**Close the loop.** Change a status with a public note, or publish a changelog entry: the author and every voter get an email with an unsubscribe link. Emails go out in the background, a batch at a time through WP-Cron, never while someone waits for a page.

**Made for WordPress.** Requests and entries are post types, so you get the admin screens, revisions, search and the REST API for free. Votes live in their own table with a unique index. Five blocks, rendered on the server and themable with CSS variables and block styles, plus shortcodes for classic themes. One click creates the pages, using a wide "Feedback portal" template on block themes.

**For developers.** A REST API (`ruvelo-feedback/v1`), WP-CLI commands for every admin action, Abilities API registrations so AI agents can read the board and roadmap (and, with the right capability, change statuses), and actions and filters for everything that happens. Settings can be defined in code.

= Our promises =

* **No nags, no upsells, no "pro" version.** No admin notices outside the plugin's own screens, no review requests, no ads, no redirect after activation. It's complete and free, and it will stay that way.
* **No tracking and no remote requests.** The plugin never phones home and loads nothing from anywhere else: no fonts, scripts, images or banners. The only emails it sends are the ones you turn on.
* **Updates come only from WordPress.org and Composer**, through WordPress's own updater. Ruvelo will never sell this plugin, its WordPress.org account or its users.
* **Your data stays yours.** Deleting the plugin keeps your requests and changelog unless you tick "Also delete every request, vote, changelog entry and setting". Scheduled emails are always cleared.
* **Accessible and light.** Labels, focus states, keyboard support and live announcements, aiming at WCAG 2.2 AA. Forms work without JavaScript; it only makes votes and suggestions happen in place. Respects reduced motion and offers light, dark or system colour schemes.

Ruvelo Feedback is built by [Valtry](https://valtry.co.uk/?utm_source=ruvelo&utm_medium=wordpress&utm_campaign=wp-feedback), the website security scanner, as part of Ruvelo, its free and open-source extensions for WordPress and Laravel. The code, issues and roadmap are on [GitHub](https://github.com/Ruvelo/wp-feedback).

== Installation ==

1. Install and activate the plugin from Plugins → Add New, or with `wp plugin install ruvelo-feedback --activate`.
2. Go to Feedback → Settings and click **Create the feedback pages**. You get a Feedback page with the board, and Roadmap and Suggest a feature pages under it. The changelog lives at `/feedback/changelog/`.
3. Add the **What's New** block to your header (Appearance → Editor → Patterns → Header), next to a link to your Feedback page.

Prefer to place things yourself? Add the Feedback Board, Roadmap, Changelog, Submit Request and What's New blocks anywhere, or use the shortcodes: `[ruvelo_feedback_board]`, `[ruvelo_feedback_roadmap]`, `[ruvelo_feedback_changelog]`, `[ruvelo_feedback_submit]` and `[ruvelo_feedback_whats_new]`.

With Composer: `composer require ruvelo/wp-feedback`.

== Frequently Asked Questions ==

= Who can post and vote? =

By default, anyone can read and signed-in users can post and vote, one vote per account. In Settings you can let guests post with a name and email (held for moderation by default), and let guests vote by confirming an email address. Administrators and editors manage feedback; the capability is `ruvelo_manage_feedback`, so you can give it to any role.

= How do people hear when something ships? =

When you change a request's status (in the editor, the list's bulk actions, the request page, WP-CLI or the REST API) the change is queued. WP-Cron then emails the author and every voter in batches of 50, about a minute apart. Publishing a changelog entry that lists requests marks them Shipped and links the email to the entry. Every email has an unsubscribe link for that request or for everything. Choose which statuses send email in Settings.

= Does it send email right away? =

No. Emails are never sent during a page load. If your site has little traffic, set up a real cron job for WP-Cron, or run `wp ruvelo-feedback notifications run`.

= Can I rename the statuses or add my own? =

Rename them and change their colours in Settings. Developers can add statuses with the `ruvelo_feedback_statuses` filter.

= Will it look right with my theme? =

The blocks follow the Ruvelo house style by default and pick up your theme's fonts and colours with the "Match theme" block style. Every colour is a CSS variable (`--ruvelo-feedback-accent` and friends) you can override. Themes can also replace any template by copying it to `your-theme/ruvelo-feedback/`.

= Does it work with classic themes? =

Yes. Use the shortcodes on any page. Requests and changelog entries are shown inside your theme's header and footer, and a theme can provide `single-ruvelo_request.php` to take over.

= Is it accessible? =

That's the aim: WCAG 2.2 AA. Every control has a label and a visible focus state, the vote buttons say what they do and how many votes there are, results are announced to screen readers, and everything works with a keyboard and without JavaScript. If you find a barrier, please open an issue.

= What about privacy? =

The plugin stores who voted for what, and the email address of guests who post or vote, to confirm their vote and tell them when the request changes. It adds a paragraph to your privacy policy guide, and works with Tools → Export Personal Data and Erase Personal Data. Nothing leaves your site except the emails you turn on.

= Can AI agents use it? =

Yes, through the WordPress Abilities API: they can list, search and read requests, read the roadmap and changelog, submit a request as the signed-in user, and change a status if the user has the `ruvelo_manage_feedback` capability. With the MCP adapter installed, the abilities are available to MCP clients too.

== Screenshots ==

1. The feedback board: requests with votes, statuses, filters, search and sorting.
2. A request: votes, the team's latest update, history, and comments with a Team badge.
3. The public roadmap: planned, in progress and shipped.
4. The changelog, with labels and the requests each entry answers.
5. "What's new" in the site header, with the number of unseen updates.
6. Suggest a feature: similar requests appear while you type.
7. Requests in the admin, with votes, statuses and a moderation queue.
8. The Feedback panel in the editor: change the status with a public note, pin or merge.
9. Settings: who can post and vote, moderation, emails, statuses, and one-click pages.
10. Simple stats: votes per week, top open requests and requests by status.
11. On a phone.
12. In dark mode.

== Changelog ==

= 1.0.0 - 2026-10-09 =

* First release: feedback board, roadmap, changelog, What's New, and emails when requests ship.

== Upgrade Notice ==

= 1.0.0 =

First release.
