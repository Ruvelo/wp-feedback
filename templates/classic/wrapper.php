<?php
/**
 * Classic themes: requests and the changelog inside the theme's header and footer.
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Templates;

defined('ABSPATH') || exit;

get_header();
?>
<main id="primary" class="site-main ruvelo-fb-classic" style="max-width: 72rem; margin: 0 auto; padding: 2rem 16px 4rem">
    <?php echo Templates::classicContent(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the views.?>
</main>
<?php
get_footer();
