<?php
/**
 * The feedback board.
 *
 * @var array<string, mixed> $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Urls;
use Ruvelo\WP\Feedback\Frontend\Views;
use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Support\Html;

defined('ABSPATH') || exit;

/** @var array{status: string, category: string, tag: string, sort: string, search: string, page: int} $ruvelo_filters */
$ruvelo_filters = $view['filters'];
/** @var array<string, int> $ruvelo_counts */
$ruvelo_counts = $view['counts'];
$ruvelo_base = Urls::here();
$ruvelo_with = static function (array $changes) use ($ruvelo_filters, $ruvelo_base): string {
    $current = [
        'fb_status' => $ruvelo_filters['status'] !== 'open' ? $ruvelo_filters['status'] : null,
        'fb_category' => $ruvelo_filters['category'],
        'fb_tag' => $ruvelo_filters['tag'],
        'fb_sort' => $ruvelo_filters['sort'] !== 'top' ? $ruvelo_filters['sort'] : null,
        'fb_q' => $ruvelo_filters['search'],
    ];

    return Urls::boardWith(array_merge($current, $changes), $ruvelo_base);
};
$ruvelo_sorts = [
    'top' => __('Top', 'ruvelo-feedback'),
    'new' => __('New', 'ruvelo-feedback'),
    'trending' => __('Trending', 'ruvelo-feedback'),
];
$ruvelo_submit = Urls::submit();
?>
<div <?php echo $view['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped attributes.?>>
    <?php if ($view['nav']) {
        echo Views::nav('board'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the template.
    } ?>
    <?php echo Views::notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Views::notice().?>

    <?php if ($view['heading']) { ?>
        <div class="ruvelo-fb-head">
            <div>
                <h1><?php esc_html_e('Tell us what to build next', 'ruvelo-feedback'); ?></h1>
                <p class="ruvelo-fb-lede"><?php esc_html_e('Search for your idea and vote for it, or suggest something new. We read every request.', 'ruvelo-feedback'); ?></p>
            </div>
            <?php if ($ruvelo_submit !== '') { ?>
                <a class="ruvelo-fb-btn" href="<?php echo esc_url($ruvelo_submit); ?>"><?php echo Html::icon('plus'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.?><?php esc_html_e('Suggest a feature', 'ruvelo-feedback'); ?></a>
            <?php } ?>
        </div>
    <?php } ?>

    <div class="ruvelo-fb-cols ruvelo-fb-cols--left">
        <aside class="ruvelo-fb-side ruvelo-fb-side--filters" aria-label="<?php esc_attr_e('Filters', 'ruvelo-feedback'); ?>">
            <section>
                <h2 id="ruvelo-fb-filter-status"><?php esc_html_e('Status', 'ruvelo-feedback'); ?></h2>
                <ul class="ruvelo-fb-filter" aria-labelledby="ruvelo-fb-filter-status">
                    <li><a href="<?php echo esc_url($ruvelo_with(['fb_status' => null])); ?>" <?php echo $ruvelo_filters['status'] === 'open' ? 'aria-current="true"' : ''; ?>><?php echo Html::dot('accent'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?><?php esc_html_e('Open', 'ruvelo-feedback'); ?><span class="ruvelo-fb-count"><?php echo esc_html(number_format_i18n((int) $view['open'])); ?></span></a></li>
                    <?php foreach (Settings::statuses() as $ruvelo_key => $ruvelo_status) { ?>
                        <li><a href="<?php echo esc_url($ruvelo_with(['fb_status' => $ruvelo_key])); ?>" <?php echo $ruvelo_filters['status'] === $ruvelo_key ? 'aria-current="true"' : ''; ?>><?php echo Html::dot($ruvelo_status['color']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?><?php echo esc_html($ruvelo_status['label']); ?><span class="ruvelo-fb-count"><?php echo esc_html(number_format_i18n($ruvelo_counts[$ruvelo_key] ?? 0)); ?></span></a></li>
                    <?php } ?>
                    <li><a href="<?php echo esc_url($ruvelo_with(['fb_status' => 'all'])); ?>" <?php echo $ruvelo_filters['status'] === 'all' ? 'aria-current="true"' : ''; ?>><?php echo Html::dot('neutral'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?><?php esc_html_e('Everything', 'ruvelo-feedback'); ?><span class="ruvelo-fb-count"><?php echo esc_html(number_format_i18n((int) $view['all'])); ?></span></a></li>
                </ul>
            </section>

            <?php if ($view['categories'] !== []) { ?>
                <section>
                    <h2 id="ruvelo-fb-filter-category"><?php esc_html_e('Category', 'ruvelo-feedback'); ?></h2>
                    <ul class="ruvelo-fb-filter" aria-labelledby="ruvelo-fb-filter-category">
                        <li><a href="<?php echo esc_url($ruvelo_with(['fb_category' => null])); ?>" <?php echo $ruvelo_filters['category'] === '' ? 'aria-current="true"' : ''; ?>><?php esc_html_e('All categories', 'ruvelo-feedback'); ?></a></li>
                        <?php foreach ($view['categories'] as $ruvelo_term) { ?>
                            <li><a href="<?php echo esc_url($ruvelo_with(['fb_category' => $ruvelo_term->slug])); ?>" <?php echo $ruvelo_filters['category'] === $ruvelo_term->slug ? 'aria-current="true"' : ''; ?>><?php echo esc_html($ruvelo_term->name); ?></a></li>
                        <?php } ?>
                    </ul>
                </section>
            <?php } ?>

            <?php if ($view['tags'] !== []) { ?>
                <section>
                    <h2><?php esc_html_e('Tags', 'ruvelo-feedback'); ?></h2>
                    <div class="ruvelo-fb-tagcloud">
                        <?php foreach ($view['tags'] as $ruvelo_term) {
                            $ruvelo_on = $ruvelo_filters['tag'] === $ruvelo_term->slug; ?>
                            <a class="ruvelo-fb-chip<?php echo $ruvelo_on ? '' : ' ruvelo-fb-chip--outline'; ?>" href="<?php echo esc_url($ruvelo_with(['fb_tag' => $ruvelo_on ? null : $ruvelo_term->slug])); ?>" <?php echo $ruvelo_on ? 'aria-current="true"' : ''; ?>><?php echo esc_html($ruvelo_term->name); ?></a>
                        <?php } ?>
                    </div>
                </section>
            <?php } ?>
        </aside>

        <div>
            <div class="ruvelo-fb-toolbar">
                <form class="ruvelo-fb-search" action="<?php echo esc_url(remove_query_arg(['fb_q', 'fb_status', 'fb_category', 'fb_tag', 'fb_sort', 'fb_page'], $ruvelo_base)); ?>" method="get" role="search">
                    <label class="ruvelo-fb-sr" for="ruvelo-fb-q"><?php esc_html_e('Search requests', 'ruvelo-feedback'); ?></label>
                    <input id="ruvelo-fb-q" type="search" name="fb_q" value="<?php echo esc_attr($ruvelo_filters['search']); ?>" placeholder="<?php esc_attr_e('Search requests…', 'ruvelo-feedback'); ?>" maxlength="200" autocomplete="off">
                    <?php
                    foreach (['fb_status' => $ruvelo_filters['status'] !== 'open' ? $ruvelo_filters['status'] : '', 'fb_category' => $ruvelo_filters['category'], 'fb_tag' => $ruvelo_filters['tag'], 'fb_sort' => $ruvelo_filters['sort'] !== 'top' ? $ruvelo_filters['sort'] : ''] as $ruvelo_name => $ruvelo_value) {
                        if ($ruvelo_value !== '') {
                            printf('<input type="hidden" name="%s" value="%s">', esc_attr($ruvelo_name), esc_attr($ruvelo_value));
                        }
                    }
// Keep ?page_id= and friends with plain permalinks.
foreach (wp_parse_args((string) wp_parse_url($ruvelo_base, PHP_URL_QUERY)) as $ruvelo_name => $ruvelo_value) {
    if (is_string($ruvelo_value) && ! str_starts_with((string) $ruvelo_name, 'fb_')) {
        printf('<input type="hidden" name="%s" value="%s">', esc_attr((string) $ruvelo_name), esc_attr($ruvelo_value));
    }
}
?>
                </form>
                <nav class="ruvelo-fb-seg" aria-label="<?php esc_attr_e('Sort', 'ruvelo-feedback'); ?>">
                    <?php foreach ($ruvelo_sorts as $ruvelo_key => $ruvelo_label) { ?>
                        <a href="<?php echo esc_url($ruvelo_with(['fb_sort' => $ruvelo_key === 'top' ? null : $ruvelo_key])); ?>" <?php echo $ruvelo_filters['sort'] === $ruvelo_key ? 'aria-current="true"' : ''; ?>><?php echo esc_html($ruvelo_label); ?></a>
                    <?php } ?>
                </nav>
            </div>

            <?php if ($view['filtered']) { ?>
                <p class="ruvelo-fb-active">
                    <?php
$ruvelo_total = (int) $view['total'];
                if ($ruvelo_filters['search'] !== '') {
                    /* translators: 1: number of requests, 2: search text */
                    echo esc_html(sprintf(_n('%1$s request matching “%2$s”', '%1$s requests matching “%2$s”', $ruvelo_total, 'ruvelo-feedback'), number_format_i18n($ruvelo_total), $ruvelo_filters['search']));
                } else {
                    /* translators: %s: number of requests */
                    echo esc_html(sprintf(_n('%s request', '%s requests', $ruvelo_total, 'ruvelo-feedback'), number_format_i18n($ruvelo_total)));
                }
                ?>
                    · <a href="<?php echo esc_url(Urls::boardWith([], $ruvelo_base)); ?>"><?php esc_html_e('Clear filters', 'ruvelo-feedback'); ?></a>
                </p>
            <?php } ?>

            <?php if ($view['posts'] === []) { ?>
                <p class="ruvelo-fb-empty">
                    <?php if ($view['filtered']) { ?>
                        <strong><?php esc_html_e('No requests match.', 'ruvelo-feedback'); ?></strong>
                        <?php if ($ruvelo_submit !== '') { ?>
                            <?php esc_html_e('Can’t find it?', 'ruvelo-feedback'); ?> <a href="<?php echo esc_url(Urls::submit($ruvelo_filters['search'])); ?>"><?php esc_html_e('Suggest it', 'ruvelo-feedback'); ?></a>.
                        <?php } ?>
                    <?php } else { ?>
                        <strong><?php esc_html_e('No requests yet.', 'ruvelo-feedback'); ?></strong>
                        <?php if ($ruvelo_submit !== '') { ?>
                            <a href="<?php echo esc_url($ruvelo_submit); ?>"><?php esc_html_e('Suggest the first one', 'ruvelo-feedback'); ?></a>.
                        <?php } ?>
                    <?php } ?>
                </p>
            <?php } else { ?>
                <ol class="ruvelo-fb-list">
                    <?php foreach ($view['posts'] as $ruvelo_post) {
                        echo Views::card($ruvelo_post, isset($view['voted'][$ruvelo_post->ID])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the template.
                    } ?>
                </ol>
                <?php echo Views::pagination((int) $ruvelo_filters['page'], (int) $view['pages'], $ruvelo_base); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Views::pagination().?>
            <?php } ?>
        </div>
    </div>
    <div class="ruvelo-fb-live ruvelo-fb-sr" aria-live="polite" data-ruvelo-live></div>
</div>
