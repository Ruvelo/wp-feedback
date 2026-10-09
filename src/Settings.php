<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

/**
 * Every setting in one place: defaults, the saved option, and the
 * `ruvelo_feedback_settings` filter so a site can define them in code.
 */
final class Settings
{
    public const OPTION = 'ruvelo_feedback_settings';

    public const CAP = 'ruvelo_manage_feedback';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'name' => '',
            'post' => 'users',          // users | anyone
            'vote' => 'users',          // users | anyone (guests confirm by email)
            'moderation' => 'guests',   // none | guests | all
            'emails' => true,
            'notify_statuses' => ['planned', 'in_progress', 'shipped', 'closed'],
            'status_labels' => [],      // key => label overrides
            'status_colors' => [],      // key => tone or #hex overrides
            'color_scheme' => 'light',  // light | auto | dark
            'per_page' => 20,
            'similar' => 5,
            'batch_size' => 50,
            'delete_data' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            $saved = get_option(self::OPTION, []);
            $settings = array_merge(self::defaults(), is_array($saved) ? $saved : []);

            /**
             * Filters the settings. Use it to define settings in code.
             *
             * @param array<string, mixed> $settings
             */
            $filtered = apply_filters('ruvelo_feedback_settings', $settings);
            self::$cache = is_array($filtered) ? $filtered : $settings;
        }

        return self::$cache;
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? self::defaults()[$key] ?? null;
    }

    public static function string(string $key): string
    {
        $value = self::get($key);

        return is_scalar($value) ? (string) $value : '';
    }

    public static function int(string $key): int
    {
        $value = self::get($key);

        return is_numeric($value) ? (int) $value : 0;
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key);
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function update(array $values): void
    {
        $saved = get_option(self::OPTION, []);
        update_option(self::OPTION, array_merge(is_array($saved) ? $saved : [], $values));
        self::flush();
    }

    public static function flush(): void
    {
        self::$cache = null;
        self::$statuses = null;
    }

    public static function name(): string
    {
        $name = trim(self::string('name'));

        return $name !== '' ? $name : (string) get_bloginfo('name');
    }

    /** @var array<string, array{label: string, color: string}>|null */
    private static ?array $statuses = null;

    /**
     * The statuses, in order, with their labels and colours. Colours are
     * a tone (accent, lilac, success, warning, danger, neutral) or a hex colour.
     *
     * @return array<string, array{label: string, color: string}>
     */
    public static function statuses(): array
    {
        if (self::$statuses !== null) {
            return self::$statuses;
        }

        $statuses = [
            'under_review' => ['label' => __('Under review', 'ruvelo-feedback'), 'color' => 'neutral'],
            'planned' => ['label' => __('Planned', 'ruvelo-feedback'), 'color' => 'lilac'],
            'in_progress' => ['label' => __('In progress', 'ruvelo-feedback'), 'color' => 'warning'],
            'shipped' => ['label' => __('Shipped', 'ruvelo-feedback'), 'color' => 'success'],
            'closed' => ['label' => __('Closed', 'ruvelo-feedback'), 'color' => 'danger'],
        ];

        $labels = self::get('status_labels');
        $colors = self::get('status_colors');
        foreach ($statuses as $key => $status) {
            if (is_array($labels) && isset($labels[$key]) && is_string($labels[$key]) && trim($labels[$key]) !== '') {
                $statuses[$key]['label'] = trim($labels[$key]);
            }
            if (is_array($colors) && isset($colors[$key]) && is_string($colors[$key]) && self::validColor($colors[$key])) {
                $statuses[$key]['color'] = $colors[$key];
            }
        }

        /**
         * Filters the request statuses: key => [label, color]. Keep the five
         * built-in keys; add your own after them.
         *
         * @param array<string, array{label: string, color: string}> $statuses
         */
        $filtered = apply_filters('ruvelo_feedback_statuses', $statuses);
        $clean = [];
        if (is_array($filtered)) {
            foreach ($filtered as $key => $status) {
                if (is_string($key) && is_array($status) && isset($status['label'])) {
                    $color = isset($status['color']) && is_string($status['color']) && self::validColor($status['color']) ? $status['color'] : 'neutral';
                    $clean[sanitize_key($key)] = ['label' => (string) $status['label'], 'color' => $color];
                }
            }
        }

        return self::$statuses = $clean !== [] ? $clean : $statuses;
    }

    public static function statusLabel(string $key): string
    {
        return self::statuses()[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function isStatus(string $key): bool
    {
        return isset(self::statuses()[$key]);
    }

    /**
     * Statuses shown on the board by default.
     *
     * @return list<string>
     */
    public static function openStatuses(): array
    {
        $open = apply_filters('ruvelo_feedback_open_statuses', ['under_review', 'planned', 'in_progress']);

        return self::keys($open);
    }

    /**
     * Statuses that stop taking votes.
     *
     * @return list<string>
     */
    public static function lockedStatuses(): array
    {
        return self::keys(apply_filters('ruvelo_feedback_locked_statuses', ['shipped', 'closed']));
    }

    /**
     * The roadmap's columns.
     *
     * @return list<string>
     */
    public static function roadmapStatuses(): array
    {
        return self::keys(apply_filters('ruvelo_feedback_roadmap_statuses', ['planned', 'in_progress', 'shipped']));
    }

    /**
     * Changelog labels: slug => [label, color].
     *
     * @return array<string, array{label: string, color: string}>
     */
    public static function labels(): array
    {
        $labels = [
            'new' => ['label' => __('New', 'ruvelo-feedback'), 'color' => 'accent'],
            'improved' => ['label' => __('Improved', 'ruvelo-feedback'), 'color' => 'lilac'],
            'fixed' => ['label' => __('Fixed', 'ruvelo-feedback'), 'color' => 'success'],
        ];

        /**
         * Filters the changelog labels' default names and colours.
         *
         * @param array<string, array{label: string, color: string}> $labels
         */
        $filtered = apply_filters('ruvelo_feedback_labels', $labels);

        return is_array($filtered) ? $filtered : $labels;
    }

    public static function validColor(string $color): bool
    {
        return in_array($color, ['accent', 'lilac', 'success', 'warning', 'danger', 'neutral'], true)
            || preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1;
    }

    /**
     * @return list<string>
     */
    private static function keys(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($key): string => is_string($key) ? $key : '', $value), static fn (string $key): bool => self::isStatus($key)));
    }
}
