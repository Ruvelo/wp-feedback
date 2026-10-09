<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Support;

/**
 * Plain-text handling for what visitors type. Requests are stored as core
 * paragraph and list blocks, so they edit cleanly in the block editor.
 */
final class Text
{
    public static function line(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', sanitize_text_field($text)) ?? '');

        return mb_substr($text, 0, $max);
    }

    public static function plain(string $text, int $max): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = sanitize_textarea_field($text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim(mb_substr($text, 0, $max));
    }

    /**
     * Turns plain text into paragraph and list blocks. Lines starting with
     * "- " or "* " become a list; **bold** becomes <strong>; URLs become links.
     */
    public static function toBlocks(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $blocks = [];
        foreach (preg_split("/\n{2,}/", $text) ?: [] as $chunk) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $chunk)), static fn (string $line): bool => $line !== ''));
            if ($lines === []) {
                continue;
            }
            $isList = count(array_filter($lines, static fn (string $line): bool => preg_match('/^[-*•]\s+/u', $line) === 1)) === count($lines);
            if ($isList) {
                $items = array_map(static fn (string $line): string => "<!-- wp:list-item -->\n<li>".self::inline((string) preg_replace('/^[-*•]\s+/u', '', $line))."</li>\n<!-- /wp:list-item -->", $lines);
                $blocks[] = "<!-- wp:list -->\n<ul class=\"wp-block-list\">".implode("\n", $items)."</ul>\n<!-- /wp:list -->";
            } else {
                $blocks[] = "<!-- wp:paragraph -->\n<p>".implode('<br>', array_map([self::class, 'inline'], $lines))."</p>\n<!-- /wp:paragraph -->";
            }
        }

        return implode("\n\n", $blocks);
    }

    private static function inline(string $line): string
    {
        $html = esc_html($line);
        $html = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $html) ?? $html;

        return make_clickable($html);
    }

    public static function stripBlocks(string $content): string
    {
        return (string) preg_replace('/<!--\s*\/?wp:[^>]*-->/', '', $content);
    }

    public static function excerpt(string $content, int $length): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags(self::stripBlocks(str_replace(['</p>', '</li>', '<br>'], ' ', $content))), ENT_QUOTES)));
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length - 1), ' ,.;:').'…';
    }

    /**
     * Meaningful lowercase words, for similarity.
     *
     * @return list<string>
     */
    public static function keywords(string $text): array
    {
        $stop = ['the', 'and', 'for', 'with', 'from', 'that', 'this', 'into', 'please', 'add', 'able', 'can', 'could', 'would', 'should', 'want', 'need', 'let', 'make', 'have', 'our', 'your', 'you', 'are', 'not', 'all', 'more', 'support', 'option', 'feature', 'allow', 'way', 'when', 'what'];
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];
        $words = array_filter($words, static fn (string $word): bool => mb_strlen($word) >= 3 && ! in_array($word, $stop, true));

        return array_values(array_unique(array_slice($words, 0, 8)));
    }
}
