<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Support;

use Ruvelo\WP\Feedback\Settings;

/**
 * Small markup helpers shared by the templates. Everything returned here is
 * already escaped.
 */
final class Html
{
    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice(array_values(array_filter($parts)), 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }

    public static function avatar(string $name, string $class = ''): string
    {
        return sprintf('<span class="ruvelo-fb-avatar %s" aria-hidden="true">%s</span>', esc_attr($class), esc_html(self::initials($name)));
    }

    /**
     * The class and inline style for a tone: a named tone or a hex colour.
     *
     * @return array{class: string, style: string}
     */
    public static function tone(string $color): array
    {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
            return ['class' => 'ruvelo-fb-tone-custom', 'style' => '--ruvelo-fb-tone: '.$color.';'];
        }
        $color = in_array($color, ['accent', 'lilac', 'success', 'warning', 'danger', 'neutral'], true) ? $color : 'neutral';

        return ['class' => 'ruvelo-fb-tone-'.$color, 'style' => ''];
    }

    public static function toneAttrs(string $color, string $extra = ''): string
    {
        $tone = self::tone($color);
        $html = 'class="'.esc_attr(trim($extra.' '.$tone['class'])).'"';
        if ($tone['style'] !== '') {
            $html .= ' style="'.esc_attr($tone['style']).'"';
        }

        return $html;
    }

    public static function status(string $key): string
    {
        $color = Settings::statuses()[$key]['color'] ?? 'neutral';

        return '<span '.self::toneAttrs($color, 'ruvelo-fb-chip ruvelo-fb-chip--status').'>'.esc_html(Settings::statusLabel($key)).'</span>';
    }

    public static function dot(string $color): string
    {
        return '<span '.self::toneAttrs($color, 'ruvelo-fb-dot').' aria-hidden="true"></span>';
    }

    public static function icon(string $name): string
    {
        $paths = [
            'up' => '<path d="m6 15 6-6 6 6"/>',
            'comment' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12Z"/>',
            'pin' => '<path d="M12 17v5"/><path d="M9 10.8V4h6v6.8l3 3.2v2H6v-2l3-3.2Z"/>',
            'bulb' => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2Z"/>',
            'map' => '<rect x="3" y="4" width="5" height="16" rx="1.5"/><rect x="10" y="4" width="5" height="11" rx="1.5"/><rect x="17" y="4" width="4" height="7" rx="1.5"/>',
            'sparkle' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><path d="M12 8.5 13.2 11l2.3 1-2.3 1L12 15.5 10.8 13l-2.3-1 2.3-1Z"/>',
            'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'rss' => '<path d="M5 11a8 8 0 0 1 8 8"/><path d="M5 4a15 15 0 0 1 15 15"/><circle cx="6" cy="18" r="1.25" fill="currentColor"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'bell' => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
            'arrow' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        ];
        $width = in_array($name, ['up', 'plus', 'check'], true) ? '2.25' : '1.75';

        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.$width.'" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.($paths[$name] ?? '').'</svg>';
    }

    public static function relative(int $timestamp): string
    {
        /* translators: %s: a human time difference such as "3 days" */
        return sprintf(__('%s ago', 'ruvelo-feedback'), human_time_diff($timestamp, time()));
    }

    public static function date(int $timestamp): string
    {
        return wp_date((string) get_option('date_format', 'F j, Y'), $timestamp) ?: '';
    }

    /**
     * Restricted HTML for request bodies and notes written by visitors.
     */
    public static function userHtml(string $html): string
    {
        return wp_kses($html, [
            'p' => [], 'br' => [], 'strong' => [], 'em' => [], 'code' => [], 'ul' => ['class' => []], 'ol' => [], 'li' => [],
            'a' => ['href' => [], 'rel' => []], 'blockquote' => [], 'pre' => [],
        ]);
    }
}
