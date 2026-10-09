# Contributing to Ruvelo Feedback

Thanks for helping! Bug reports, docs fixes and features are all welcome.

## Before you start

- **Bugs:** [open an issue](https://github.com/Ruvelo/wp-feedback/issues/new/choose) with the steps to reproduce, or go straight to a pull request with a failing test.
- **Features:** open an issue first if it's more than a small change, so we can agree on the shape before you spend time on it.
- **Security issues:** don't open an issue. See [SECURITY.md](https://github.com/Ruvelo/.github/blob/main/SECURITY.md).

## Setup

You need PHP 8.2+, Composer and WP-CLI. No database server: locally, WordPress runs on SQLite.

```bash
git clone https://github.com/<you>/wp-feedback && cd wp-feedback
composer install
bin/dev-site.sh            # WordPress on SQLite in .wp/, with the plugin and Halyard's demo
wp server --path=.wp --port=8990
composer check
```

Sign in at `http://localhost:8990/wp-admin` as `maya` / `password`. `bin/dev-site.sh --fresh` starts the demo over.

`composer check` runs exactly what CI runs:

| Command | What it does |
|---|---|
| `composer test` | PHPUnit integration tests (wp-phpunit; SQLite locally, MySQL in CI) |
| `composer lint` | Code style (Pint) and the WordPress security, database and i18n sniffs (PHPCS) |
| `composer format` | Fix code style |
| `composer analyse` | Static analysis (PHPStan level 8 with the WordPress extension) |
| `composer zip` | Build `build/ruvelo-feedback.zip` |
| `composer pot` | Regenerate `languages/ruvelo-feedback.pot` |

Before a release, also run Plugin Check against the built plugin (CI does it on every push):

```bash
composer zip
wp plugin install plugin-check --activate --path=.wp
# point .wp/wp-content/plugins/ruvelo-feedback at build/ruvelo-feedback, then:
wp plugin check ruvelo-feedback --path=.wp
```

`bin/screenshots.sh` regenerates `.wordpress-org/` (screenshots, banners and icons) from the running demo site.

## Making a change

1. Branch from `main`.
2. Write a test that fails without your change, in `tests/Feature`.
3. Keep the public surface stable: the `Requests` and `Changelog` write paths, hook names and arguments, REST routes and shapes, WP-CLI commands, block names and attributes, template file names and settings keys. If you must change one, say so in the PR.
4. Run `composer format` and `composer check`.
5. Add a line under **Unreleased** in [CHANGELOG.md](CHANGELOG.md).
6. Open the pull request. Screenshots help for anything visual.

## Where things live

```
ruvelo-feedback.php      Plugin header and a tiny PSR-4 loader
src/Requests.php         The write path for requests: create, vote, status, merge, pin; board and roadmap queries
src/Votes.php            The votes table
src/Changelog.php        Entries, shipping their requests, What's new
src/Notifications.php    The email queue, WP-Cron batches, unsubscribing
src/Frontend/            Views, no-JS form handlers, templates and comments
src/Admin/               List columns, editor panels, settings and stats
src/Rest/, src/Cli/      REST API and WP-CLI
src/Abilities.php        Abilities API registrations
blocks/                  block.json and render.php for each block
templates/               The markup (themes can override it)
assets/                  CSS and plain JavaScript, no build step
demo/                    Halyard's demo content
bin/                     Dev site, zip, screenshots
```

## Style

- `declare(strict_types=1)` everywhere, typed properties and return types.
- No `@phpstan-ignore` or baseline entries: fix the cause. `phpcs:ignore` only with a reason.
- Comments explain *why*, not *what*.
- Every page keeps working with JavaScript off: every action is a plain link or form first.
- No remote requests, no admin notices outside the plugin's own screens.
- UI follows the [Ruvelo house style](https://github.com/Ruvelo/.github/blob/main/BRAND.md): CSS variables, no build step, light and dark.

By contributing you agree that your work is released under the [GPL-2.0-or-later license](LICENSE) and that you'll follow the [code of conduct](https://github.com/Ruvelo/.github/blob/main/CODE_OF_CONDUCT.md).
