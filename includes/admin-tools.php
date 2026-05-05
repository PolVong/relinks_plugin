<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Handle Google Sheets sync ─────────────────────────────────────────────────
add_action( 'admin_post_relinks_sync_gsheets', function() {
    check_admin_referer( 'relinks_sync_gsheets', '_wpnonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Недостатньо прав.' );

    $result = relinks_sync_gsheets();
    set_transient( 'relinks_notice_' . get_current_user_id(), $result, 60 );

    wp_redirect( admin_url( 'options-general.php?page=relinks-options' ) );
    exit;
} );

// ── Handle import from anchors.txt ────────────────────────────────────────────
add_action( 'admin_post_relinks_import', function() {
    check_admin_referer( 'relinks_import' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Недостатньо прав.' );

    $result = relinks_import_txt();
    set_transient( 'relinks_notice_' . get_current_user_id(), $result, 60 );

    wp_redirect( admin_url( 'options-general.php?page=relinks-options' ) );
    exit;
} );

// ── Status notices on options page ────────────────────────────────────────────
add_action( 'admin_notices', function() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'settings_page_relinks-options' ) return;

    $result = get_transient( 'relinks_notice_' . get_current_user_id() );
    if ( ! $result ) return;

    delete_transient( 'relinks_notice_' . get_current_user_id() );

    $type = $result['success'] ? 'success' : 'error';
    echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . esc_html( $result['message'] ) . '</p></div>';
} );

// ── Sync button after GSheets URL field ───────────────────────────────────────
add_action( 'acf/render_field/key=field_relinks_gsheets_url', function() {
    $gsheets_url = get_field( 'relinks_gsheets_url', 'option' );
    if ( ! $gsheets_url ) return;

    $url = wp_nonce_url(
        admin_url( 'admin-post.php?action=relinks_sync_gsheets' ),
        'relinks_sync_gsheets'
    );
    ?>
    <div style="margin-top:10px;">
        <a href="<?php echo esc_url( $url ); ?>" class="button button-secondary">↻ Синхронізувати</a>
    </div>
    <?php
} );

// ── Stats + Import section at bottom of options page ─────────────────────────
add_action( 'admin_footer', function() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'settings_page_relinks-options' ) return;

    $json_file   = RELINKS_DIR . 'data/relinking.json';
    $json_exists = file_exists( $json_file );
    $json_count  = 0;
    $last_mod    = '';

    if ( $json_exists ) {
        $data       = json_decode( file_get_contents( $json_file ), true );
        $json_count = is_array( $data ) ? count( $data ) : 0;
        $last_mod   = date_i18n( 'd.m.Y H:i', filemtime( $json_file ) );
    }
    ?>
    <div id="relinks-tools-section" style="display:none;">
        <hr>

        <h2>Стан бази анкорів</h2>
        <p>
            <?php if ( $json_exists ) : ?>
                <span style="color:green">✅ Файл існує</span> —
                <strong><?php echo (int) $json_count; ?> сторінок</strong> з анкорами.
                Оновлено: <?php echo esc_html( $last_mod ); ?>
            <?php else : ?>
                <span style="color:red">❌ Файл не знайдено.</span>
                Синхронізуйте з Google Sheets або імпортуйте anchors.txt.
            <?php endif; ?>
        </p>

        <?php if ( file_exists( RELINKS_DIR . 'anchors.txt' ) ) : ?>
            <h2>Імпорт з anchors.txt</h2>
            <p>Ручний метод: покладіть файл <code>anchors.txt</code> у папку плагіна і натисніть кнопку.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="relinks_import">
                <?php wp_nonce_field( 'relinks_import' ); ?>
                <?php submit_button( 'Імпортувати anchors.txt → relinking.json', 'secondary', 'submit', false ); ?>
            </form>
        <?php endif; ?>

        <?php $stats = relinks_get_anchor_stats(); ?>
        <?php if ( ! empty( $stats ) ) : ?>
            <h2 style="margin-top:20px;">Статистика анкорів</h2>
            <p>Анкори відсортовані від найчастіше до найрідше використовуваних.</p>
            <table class="widefat striped" style="max-width:600px;">
                <thead>
                    <tr>
                        <th>Анкор</th>
                        <th style="width:120px;text-align:center;">Використань</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $stats as $anchor => $count ) : ?>
                        <tr>
                            <td><?php echo esc_html( $anchor ); ?></td>
                            <td style="text-align:center;"><?php echo (int) $count; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <script>
    jQuery(function($) {
        $('#relinks-tools-section').appendTo('.wrap:first').show();
    });
    </script>
    <?php
} );
