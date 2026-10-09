# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-10-09

First release.

### Added

- Feature requests as a custom post type, with categories, tags, core comments and revisions, and five statuses (Under review, Planned, In progress, Shipped, Closed) with editable labels and colours and a filter for more.
- Votes in their own table with a unique `(request, voter)` index; optional guest votes confirmed by email.
- A front-end "Suggest a feature" form for signed-in users, or guests with a name and email held for moderation; similar requests while the title is typed (REST), or before posting without JavaScript.
- Merging duplicates: votes carry over without double counting, comments move, the duplicate's author becomes a voter, and its page redirects.
- Pinning, a moderation queue with a menu count, bulk status changes, and a Feedback panel in the editor.
- A roadmap of planned, in-progress and shipped requests.
- A changelog post type with labels (New, Improved, Fixed), linked requests that ship when it's published, list and single templates and the core RSS feed.
- "What's new": an unread count per visitor and a dropdown of the latest entries.
- Emails to authors and voters when a request changes status or ships, queued and sent by WP-Cron in batches, with unsubscribe links, and the `ruvelo_feedback_notification` action for other channels.
- Blocks: Feedback Board, Roadmap, Changelog, What's New and Submit Request, with block styles and CSS variables; shortcodes; block templates, a "Feedback portal" page template and patterns; "Create the feedback pages" in Settings.
- REST API (`ruvelo-feedback/v1`), WP-CLI (`wp ruvelo-feedback`), Abilities API registrations, actions and filters.
- Settings, simple stats, personal data export and erasure, and an uninstall that deletes content only when asked.
- Halyard's demo portal (`wp ruvelo-feedback demo`) and a WordPress Playground blueprint.

[Unreleased]: https://github.com/Ruvelo/wp-feedback/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/Ruvelo/wp-feedback/releases/tag/v1.0.0
