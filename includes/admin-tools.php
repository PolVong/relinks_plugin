<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Admin sub-page ────────────────────────────────────────────────────────────
add_action( 'admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'Relinks — Інструменти',
        'Relinks',
        'manage_options',
        'relinks-tools',
        'relinks_admin_page'
    );
} );

// ── Handle Google Sheets sync ─────────────────────────────────────────────────
add_action( 'admin_post_relinks_sync_gsheets', function() {
    check_admin_referer( 'relinks_sync_gsheets' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Недостатньо прав.' );

    $result = relinks_sync_gsheets();
    $status = $result['success'] ? 'success' : 'error';
    $msg    = urlencode( $result['message'] );

    wp_redirect( admin_url( 'options-general.php?page=relinks-tools&status=' . $status . '&msg=' . $msg ) );
    exit;
} );

// ── Handle import from anchors.txt ────────────────────────────────────────────
add_action( 'admin_post_relinks_import', function() {
    check_admin_referer( 'relinks_import' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Недостатньо прав.' );

    $result = relinks_import_txt();
    $status = $result['success'] ? 'success' : 'error';
    $msg    = urlencode( $result['message'] );

    wp_redirect( admin_url( 'options-general.php?page=relinks-tools&status=' . $status . '&msg=' . $msg ) );
    exit;
} );

// ── Admin page HTML ───────────────────────────────────────────────────────────
function relinks_admin_page() {
    $json_file   = RELINKS_DIR . 'data/relinking.json';
    $json_exists = file_exists( $json_file );
    $json_count  = 0;
    $last_mod    = '';

    if ( $json_exists ) {
        $data       = json_decode( file_get_contents( $json_file ), true );
        $json_count = is_array( $data ) ? count( $data ) : 0;
        $last_mod   = date_i18n( 'd.m.Y H:i', filemtime( $json_file ) );
    }

    $gsheets_url = get_field( 'relinks_gsheets_url', 'option' );
    $status      = $_GET['status'] ?? '';
    $msg         = isset( $_GET['msg'] ) ? urldecode( sanitize_text_field( $_GET['msg'] ) ) : '';
    ?>
    <div class="wrap">
        <h1>Relinks — Інструменти</h1>

        <?php if ( $status === 'success' ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
        <?php elseif ( $status === 'error' ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $msg ); ?></p></div>
        <?php endif; ?>

        <h2>Стан системи</h2>
        <table class="form-table">
            <tr>
                <th>JSON файл анкорів</th>
                <td>
                    <?php if ( $json_exists ) : ?>
                        <span style="color:green">✅ Файл існує</span> —
                        <strong><?php echo (int) $json_count; ?> сторінок</strong> з анкорами.<br>
                        <small>Оновлено: <?php echo esc_html( $last_mod ); ?></small>
                    <?php else : ?>
                        <span style="color:red">❌ Файл не знайдено.</span> Синхронізуйте з Google Sheets або імпортуйте anchors.txt.
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Google Sheets</th>
                <td>
                    <?php if ( $gsheets_url ) : ?>
                        <span style="color:green">✅ URL вказано</span><br>
                        <small><a href="<?php echo esc_url( $gsheets_url ); ?>" target="_blank"><?php echo esc_html( $gsheets_url ); ?></a></small>
                    <?php else : ?>
                        <span style="color:#999">— не вказано</span>
                        (<a href="<?php echo esc_url( admin_url( 'options-general.php?page=relinks-options' ) ); ?>">додати в налаштуваннях</a>)
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <h2>Синхронізація з Google Sheets</h2>
        <p>Завантажує дані з таблиці та оновлює локальний файл анкорів. При недоступності таблиці плагін продовжує працювати з локальним файлом.</p>
        <?php if ( $gsheets_url ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="relinks_sync_gsheets">
                <?php wp_nonce_field( 'relinks_sync_gsheets' ); ?>
                <?php submit_button( 'Синхронізувати з Google Sheets', 'primary', 'submit', false ); ?>
            </form>
        <?php else : ?>
            <p style="color:#999;">Вкажіть URL Google Sheets у <a href="<?php echo esc_url( admin_url( 'options-general.php?page=relinks-options' ) ); ?>">налаштуваннях</a>.</p>
        <?php endif; ?>

        <hr>

        <h2>Імпорт з anchors.txt</h2>
        <p>Ручний метод: покладіть файл <code>anchors.txt</code> у папку плагіна і натисніть кнопку.</p>
        <?php
        $txt_exists = file_exists( RELINKS_DIR . 'anchors.txt' );
        if ( $txt_exists ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="relinks_import">
                <?php wp_nonce_field( 'relinks_import' ); ?>
                <?php submit_button( 'Імпортувати anchors.txt → relinking.json', 'secondary', 'submit', false ); ?>
            </form>
        <?php else : ?>
            <p style="color:#999;"><span style="color:red">❌</span> anchors.txt не знайдено в папці плагіна.</p>
        <?php endif; ?>

        <hr>

        <h2>Статистика анкорів</h2>
        <?php
        $stats = relinks_get_anchor_stats();
        if ( empty( $stats ) ) : ?>
            <p style="color:#999;">Посилань ще не згенеровано. Статистика з'явиться після першої генерації.</p>
        <?php else : ?>
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

        <hr>

        <p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=relinks-options' ) ); ?>" class="button">
            ⚙️ Налаштування (обов'язкові сторінки, Google Sheets, CSS)
        </a></p>
    </div>
    <?php
}
