<?php
/**
 * ACF Block render template: Internal Relinking
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Global toggle
$enabled = get_field( 'relinks_enabled', 'option' );
if ( $enabled === false || $enabled === 0 || $enabled === '0' ) return;

$post_id = get_the_ID();
$links   = get_field( 'relinks_list', $post_id );

// Editor preview placeholder
$is_preview = ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'is_admin' ) && is_admin() );

if ( empty( $links ) ) {
    if ( $is_preview ) {
        echo '<div style="padding:20px;background:#f8f8f8;border:2px dashed #ccc;text-align:center;color:#888;font-family:sans-serif;">';
        echo '<strong style="display:block;margin-bottom:8px;">📎 Блок перелінковки</strong>';
        echo 'Посилання ще не згенеровано.<br>Увімкніть <em>🔄 Автогенерацію</em> і збережіть сторінку.';
        echo '</div>';
    }
    return;
}

// Custom CSS — виводиться лише один раз за запит
if ( ! defined( 'RELINKS_CSS_DONE' ) ) {
    define( 'RELINKS_CSS_DONE', true );
    $custom_css = get_field( 'relinks_custom_css', 'option' );
    if ( $custom_css ) {
        echo '<style>' . str_replace( '</style>', '', $custom_css ) . '</style>';
    }
}
?>

<ul class="relinks__list">
    <?php foreach ( $links as $link ) :
        $anchor = isset( $link['anchor'] ) ? trim( $link['anchor'] ) : '';
        $url    = isset( $link['url'] )    ? trim( $link['url'] )    : '';
        if ( ! $anchor || ! $url ) continue;
    ?>
    <li class="relinks__item">
        <a href="<?php echo esc_url( $url ); ?>" class="relinks__link">
            <?php echo esc_html( $anchor ); ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
