<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Handle Google Sheets sync ─────────────────────────────────────────────────
add_action( 'admin_post_relinks_sync_gsheets', function() {
    check_admin_referer( 'relinks_sync_gsheets' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Недостатньо прав.' );

    $result = relinks_sync_gsheets();
    $status = $result['success'] ? 'success' : 'error';
    $msg    = urlencode( $result['message'] );

    wp_redirect( admin_url( 'options-general.php?page=relinks-options&status=' . $status . '&msg=' . $msg ) );
    exit;
} );

// ── Handle import from anchors.txt ────────────────────────────────────────────
add_action( 'admin_post_relinks_import', function() {
    check_admin_referer( 'relinks_import' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Недостатньо прав.' );

    $result = relinks_import_txt();
    $status = $result['success'] ? 'success' : 'error';
    $msg    = urlencode( $result['message'] );

    wp_redirect( admin_url( 'options-general.php?page=relinks-options&status=' . $status . '&msg=' . $msg ) );
    exit;
} );

// ── Status notices on options page ────────────────────────────────────────────
add_action( 'admin_notices', function() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'settings_page_relinks-options' ) return;

    $status = $_GET['status'] ?? '';
    $msg    = isset( $_GET['msg'] ) ? urldecode( sanitize_text_field( $_GET['msg'] ) ) : '';

    if ( $status === 'success' && $msg ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
    } elseif ( $status === 'error' && $msg ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $msg ) . '</p></div>';
    }
} );

// ── Sync button after GSheets URL field ───────────────────────────────────────
add_action( 'acf/render_field/key=field_relinks_gsheets_url', function() {
    $gsheets_url = get_field( 'relinks_gsheets_url', 'option' );
    if ( ! $gsheets_url ) return;
    ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
        <input type="hidden" name="action" value="relinks_sync_gsheets">
        <?php wp_nonce_field( 'relinks_sync_gsheets' ); ?>
        <button type="submit" class="button button-secondary">↻ Синхронізувати</button>
    </form>
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
    <div class="wrap" style="margin-top:0;">
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
    <?php
} );
