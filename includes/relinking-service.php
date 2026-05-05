<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Write a line to data/relinks.log with timestamp.
 */
function relinks_log( $message ) {
    $file = RELINKS_DIR . 'data/relinks.log';
    $line = '[' . date( 'd.m.Y H:i:s' ) . '] ' . $message . PHP_EOL;
    file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
}

/**
 * Load and cache relinking.json.
 * Returns: array [ 'url' => ['anchor1', ...], ... ]
 */
function relinks_load_json() {
    static $cache = null;
    if ( $cache !== null ) return $cache;

    $file = RELINKS_DIR . 'data/relinking.json';
    if ( ! file_exists( $file ) ) {
        return $cache = [];
    }

    $data  = json_decode( file_get_contents( $file ), true );
    $cache = is_array( $data ) ? $data : [];
    return $cache;
}

/**
 * Normalize URL to path with trailing slash.
 */
function relinks_normalize_url( $url ) {
    $url    = rtrim( trim( $url ), '. ' );
    $parsed = parse_url( $url );
    $path   = isset( $parsed['path'] ) ? $parsed['path'] : $url;
    return rtrim( $path, '/' ) . '/';
}

/**
 * Get a random anchor for a URL that is not already used.
 */
function relinks_get_random_anchor( $json, $url, $used_anchors ) {
    $path = relinks_normalize_url( $url );

    foreach ( $json as $json_url => $anchors ) {
        if ( relinks_normalize_url( $json_url ) !== $path ) continue;

        $available = array_values( array_diff( $anchors, $used_anchors ) );
        if ( empty( $available ) ) return false;

        return $available[ array_rand( $available ) ];
    }

    return false;
}

/**
 * Get anchor usage counts across all published pages.
 * Returns: [ normalized_url_path => count ]
 *
 * @param int|null $exclude_post_id Post to exclude from counting (current post being regenerated)
 */
function relinks_get_usage_counts( $exclude_post_id = null ) {
    $query = new WP_Query( [
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'relinks_list',
                'compare' => 'EXISTS',
            ],
        ],
    ] );

    $counts = [];

    foreach ( $query->posts as $pid ) {
        if ( (int) $pid === (int) $exclude_post_id ) continue;

        $links = get_field( 'relinks_list', $pid );
        if ( ! is_array( $links ) ) continue;

        foreach ( $links as $link ) {
            $url = isset( $link['url'] ) ? relinks_normalize_url( $link['url'] ) : '';
            if ( ! $url ) continue;
            $counts[ $url ] = ( $counts[ $url ] ?? 0 ) + 1;
        }
    }

    return $counts;
}

/**
 * Generate relinking items for a post with uniform distribution.
 *
 * @param int $post_id Current post ID
 * @param int $count   Total number of links to generate
 * @return array [ ['anchor' => '...', 'url' => '...'], ... ]
 */
function relinks_generate( $post_id, $count = 6 ) {
    relinks_log( "generate: start post_id={$post_id} count={$count}" );

    $json        = relinks_load_json();
    $current_url = get_permalink( $post_id );

    if ( empty( $json ) ) {
        relinks_log( "generate: ABORT — relinking.json порожній або не знайдено" );
        return [];
    }

    if ( ! $current_url ) {
        relinks_log( "generate: ABORT — не вдалося отримати permalink для post_id={$post_id}" );
        return [];
    }

    $current_path = relinks_normalize_url( $current_url );

    // Mandatory URLs from repeater
    $mandatory_rows = get_field( 'relinks_mandatory_urls', 'option' ) ?: [];
    $mandatory      = array_filter( array_column( $mandatory_rows ?: [], 'url' ) );

    // Usage counts excluding current post
    $usage = relinks_get_usage_counts( $post_id );

    $result       = [];
    $used_urls    = [ $current_path ];
    $used_anchors = [];

    $add = function( $url ) use ( $json, &$result, &$used_urls, &$used_anchors, $count ) {
        if ( count( $result ) >= $count ) return false;

        $path = relinks_normalize_url( $url );
        if ( in_array( $path, $used_urls, true ) ) return false;

        $anchor = relinks_get_random_anchor( $json, $url, $used_anchors );
        if ( ! $anchor ) return false;

        $result[]       = [ 'anchor' => $anchor, 'url' => $url ];
        $used_urls[]    = $path;
        $used_anchors[] = $anchor;
        return true;
    };

    // 1. Mandatory pages first
    foreach ( $mandatory as $url ) {
        $add( $url );
    }

    // 2. Fill remaining slots with least-used URLs from pool
    if ( count( $result ) < $count ) {
        $pool = array_keys( $json );

        // Shuffle first to randomize ties, then sort by usage count ascending
        shuffle( $pool );
        usort( $pool, function( $a, $b ) use ( $usage ) {
            $ca = $usage[ relinks_normalize_url( $a ) ] ?? 0;
            $cb = $usage[ relinks_normalize_url( $b ) ] ?? 0;
            return $ca - $cb;
        } );

        foreach ( $pool as $url ) {
            if ( count( $result ) >= $count ) break;
            $add( $url );
        }
    }

    relinks_log( "generate: done — згенеровано " . count( $result ) . " з {$count} посилань для post_id={$post_id}" );
    return $result;
}

/**
 * Get anchor text usage stats across all published pages.
 * Returns: [ 'anchor text' => count ] sorted by count descending.
 */
function relinks_get_anchor_stats() {
    $query = new WP_Query( [
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'relinks_list',
                'compare' => 'EXISTS',
            ],
        ],
    ] );

    $anchors = [];

    foreach ( $query->posts as $pid ) {
        $links = get_field( 'relinks_list', $pid );
        if ( ! is_array( $links ) ) continue;

        foreach ( $links as $link ) {
            $anchor = trim( $link['anchor'] ?? '' );
            if ( ! $anchor ) continue;
            $anchors[ $anchor ] = ( $anchors[ $anchor ] ?? 0 ) + 1;
        }
    }

    arsort( $anchors );
    return $anchors;
}

/**
 * Sync Google Sheets → relinking.json
 * Returns: ['success' => bool, 'count' => int, 'message' => string]
 */
function relinks_sync_gsheets() {
    relinks_log( "sync_gsheets: start" );

    $url = get_field( 'relinks_gsheets_url', 'option' );

    if ( ! $url ) {
        relinks_log( "sync_gsheets: ABORT — URL не вказано" );
        return [ 'success' => false, 'count' => 0, 'message' => 'URL Google Sheets не вказано в налаштуваннях.' ];
    }

    if ( ! preg_match( '/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $url, $matches ) ) {
        relinks_log( "sync_gsheets: ABORT — не вдалося визначити ID таблиці з URL: {$url}" );
        return [ 'success' => false, 'count' => 0, 'message' => 'Не вдалося визначити ID таблиці з URL.' ];
    }

    $sheet_id = $matches[1];
    $gid      = null;
    if ( preg_match( '/[?&#]gid=(\d+)/', $url, $gid_matches ) ) {
        $gid = $gid_matches[1];
    }

    $csv_url = "https://docs.google.com/spreadsheets/d/{$sheet_id}/export?format=csv";
    if ( $gid ) {
        $csv_url .= "&gid={$gid}";
    }
    relinks_log( "sync_gsheets: fetching {$csv_url}" );

    $response = wp_remote_get( $csv_url, [ 'timeout' => 15 ] );

    if ( is_wp_error( $response ) ) {
        relinks_log( "sync_gsheets: ERROR — " . $response->get_error_message() );
        return [ 'success' => false, 'count' => 0, 'message' => 'Помилка з\'єднання: ' . $response->get_error_message() ];
    }

    $code = wp_remote_retrieve_response_code( $response );
    relinks_log( "sync_gsheets: HTTP {$code}" );
    if ( $code !== 200 ) {
        return [ 'success' => false, 'count' => 0, 'message' => "HTTP помилка: {$code}. Перевірте доступ до таблиці (має бути відкрита за посиланням)." ];
    }

    $body  = wp_remote_retrieve_body( $response );
    $lines = explode( "\n", str_replace( [ "\r\n", "\r" ], "\n", $body ) );
    $data  = [];

    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( ! $line ) continue;

        $parts = str_getcsv( $line );
        if ( count( $parts ) < 2 ) continue;

        $anchor  = trim( $parts[0] );
        $url_val = rtrim( trim( $parts[1] ), '. ' );

        if ( ! $anchor || ! $url_val ) continue;
        if ( stripos( $anchor, 'не створена' ) !== false ) continue;

        if ( str_starts_with( $url_val, '/' ) ) {
            $url_val = home_url( $url_val );
        }

        if ( ! filter_var( $url_val, FILTER_VALIDATE_URL ) ) continue;

        if ( ! isset( $data[ $url_val ] ) ) $data[ $url_val ] = [];
        if ( ! in_array( $anchor, $data[ $url_val ], true ) ) {
            $data[ $url_val ][] = $anchor;
        }
    }

    if ( empty( $data ) ) {
        relinks_log( "sync_gsheets: ABORT — жодного валідного рядка не розпарсено" );
        return [ 'success' => false, 'count' => 0, 'message' => 'Таблиця порожня або невірний формат. Очікується: Анкор | URL.' ];
    }

    $dir = RELINKS_DIR . 'data/';
    if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );

    $json_file = $dir . 'relinking.json';
    $json      = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    $result    = file_put_contents( $json_file, $json );

    if ( $result === false ) {
        return [ 'success' => false, 'count' => 0, 'message' => 'Помилка запису файлу. Перевірте права доступу до /data/.' ];
    }

    $count = count( $data );
    relinks_log( "sync_gsheets: OK — збережено {$count} URL у relinking.json" );
    return [
        'success' => true,
        'count'   => $count,
        'message' => sprintf( 'Синхронізовано %d сторінок з анкорами.', $count ),
    ];
}

/**
 * Parse anchors.txt → relinking.json (manual fallback)
 * Returns: ['success' => bool, 'count' => int, 'message' => string]
 */
function relinks_import_txt() {
    $txt_file  = RELINKS_DIR . 'anchors.txt';
    $json_file = RELINKS_DIR . 'data/relinking.json';

    if ( ! file_exists( $txt_file ) ) {
        return [ 'success' => false, 'count' => 0, 'message' => 'Файл anchors.txt не знайдено.' ];
    }

    $lines = file( $txt_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    $data  = [];

    foreach ( $lines as $line ) {
        $parts = preg_split( '/\t{2,}/', trim( $line ) );
        if ( count( $parts ) < 2 ) continue;

        $anchor = trim( $parts[0] );
        $url    = rtrim( trim( $parts[1] ), '. ' );

        if ( empty( $anchor ) || empty( $url ) ) continue;
        if ( stripos( $anchor, 'не створена' ) !== false ) continue;
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) continue;

        if ( ! isset( $data[ $url ] ) ) $data[ $url ] = [];
        if ( ! in_array( $anchor, $data[ $url ], true ) ) {
            $data[ $url ][] = $anchor;
        }
    }

    $dir = RELINKS_DIR . 'data/';
    if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );

    $json   = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    $result = file_put_contents( $json_file, $json );

    if ( $result === false ) {
        return [ 'success' => false, 'count' => 0, 'message' => 'Помилка запису файлу. Перевірте права доступу до /data/.' ];
    }

    return [
        'success' => true,
        'count'   => count( $data ),
        'message' => sprintf( 'Успішно імпортовано %d сторінок з анкорами.', count( $data ) ),
    ];
}
