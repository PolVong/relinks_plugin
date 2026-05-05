<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Options Page ──────────────────────────────────────────────────────────────
add_action( 'acf/init', function() {
    if ( ! function_exists( 'acf_add_options_page' ) ) return;

    acf_add_options_page( [
        'page_title'  => 'Relinks — Налаштування',
        'menu_title'  => 'Relinks Options',
        'menu_slug'   => 'relinks-options',
        'capability'  => 'manage_options',
        'parent_slug' => 'options-general.php',
        'redirect'    => false,
    ] );
} );

// ── Field Group: Options Page ─────────────────────────────────────────────────
add_action( 'acf/init', function() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_relinks_options',
        'title'  => 'Налаштування перелінковки',
        'fields' => [
            [
                'key'          => 'field_relinks_mandatory_urls',
                'label'        => 'Обов\'язкові сторінки',
                'name'         => 'relinks_mandatory_urls',
                'type'         => 'repeater',
                'instructions' => 'Ці сторінки завжди включаються першими при автогенерації.',
                'button_label' => '+ Додати сторінку',
                'layout'       => 'table',
                'sub_fields'   => [
                    [
                        'key'   => 'field_relinks_mandatory_url_item',
                        'label' => 'URL',
                        'name'  => 'url',
                        'type'  => 'url',
                    ],
                ],
            ],
            [
                'key'          => 'field_relinks_gsheets_url',
                'label'        => 'Google Sheets URL',
                'name'         => 'relinks_gsheets_url',
                'type'         => 'text',
                'instructions' => 'Посилання на таблицю з анкорами. Таблиця має бути відкрита для перегляду за посиланням.',
                'placeholder'  => 'https://docs.google.com/spreadsheets/d/...',
            ],
            [
                'key'          => 'field_relinks_custom_css',
                'label'        => 'Custom CSS',
                'name'         => 'relinks_custom_css',
                'type'         => 'textarea',
                'instructions' => 'CSS для блоку перелінковки. Виводиться в тезі <style> на фронтенді. Доступні класи: .relinks__list, .relinks__item, .relinks__link',
                'rows'         => 8,
            ],
            [
                'key'           => 'field_relinks_enabled',
                'label'         => 'Увімкнути систему перелінковки',
                'name'          => 'relinks_enabled',
                'type'          => 'true_false',
                'default_value' => 1,
                'ui'            => 1,
            ],
        ],
        'location' => [
            [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'relinks-options' ] ],
        ],
    ] );
} );

// ── ACF Block ─────────────────────────────────────────────────────────────────
add_action( 'acf/init', function() {
    if ( ! function_exists( 'acf_register_block_type' ) ) return;

    acf_register_block_type( [
        'name'            => 'internal-relinking',
        'title'           => 'Блок перелінковки',
        'description'     => 'Автоматичний блок внутрішньої перелінковки для SEO.',
        'render_template' => RELINKS_DIR . 'templates/block-relinking.php',
        'category'        => 'formatting',
        'icon'            => 'admin-links',
        'keywords'        => [ 'relinks', 'seo', 'перелінковка' ],
        'mode'            => 'preview',
        'supports'        => [ 'align' => false, 'jsx' => false ],
    ] );
} );

// ── Field Group: Post-level relinking ─────────────────────────────────────────
add_action( 'acf/init', function() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'      => 'group_relinks_post',
        'title'    => 'Перелінковка',
        'position' => 'normal',
        'fields'   => [
            [
                'key'           => 'field_relinks_generate',
                'label'         => '🔄 Автогенерація',
                'name'          => 'relinks_generate',
                'type'          => 'true_false',
                'instructions'  => 'Увімкніть і збережіть — список нижче заповниться автоматично (існуючі посилання будуть замінені).',
                'default_value' => 0,
                'ui'            => 1,
            ],
            [
                'key'           => 'field_relinks_generate_count',
                'label'         => 'Кількість посилань для генерації',
                'name'          => 'relinks_generate_count',
                'type'          => 'number',
                'instructions'  => 'Включаючи обов\'язкові. Мінімум 3.',
                'default_value' => 6,
                'min'           => 3,
                'max'           => 20,
            ],
            [
                'key'          => 'field_relinks_list',
                'label'        => 'Посилання',
                'name'         => 'relinks_list',
                'type'         => 'repeater',
                'instructions' => 'Можна редагувати вручну, додавати нові рядки або видаляти. Порядок рядків = порядок відображення.',
                'button_label' => '+ Додати посилання',
                'layout'       => 'table',
                'sub_fields'   => [
                    [
                        'key'   => 'field_relinks_item_url',
                        'label' => 'URL',
                        'name'  => 'url',
                        'type'  => 'text',
                    ],
                    [
                        'key'         => 'field_relinks_item_anchor',
                        'label'       => 'Анкор',
                        'name'        => 'anchor',
                        'type'        => 'text',
                        'placeholder' => 'Текст посилання',
                    ],
                ],
            ],
        ],
        'location' => [
            [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ] ],
        ],
    ] );
} );

// ── Link to tools page after last options field ───────────────────────────────
add_action( 'acf/render_field/key=field_relinks_enabled', function() {
    $url = admin_url( 'admin.php?page=relinks-tools' );
    echo '<p style="margin-top:12px;"><a href="' . esc_url( $url ) . '" class="button button-secondary">→ Синхронізація та інструменти</a></p>';
} );

// ── ACF Save: тригер автогенерації ────────────────────────────────────────────
add_action( 'acf/save_post', 'relinks_on_acf_save', 20 );

function relinks_on_acf_save( $post_id ) {
    if ( ! is_numeric( $post_id ) ) return;
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
    if ( ! get_field( 'relinks_generate', $post_id ) ) return;

    $count = (int) get_field( 'relinks_generate_count', $post_id );
    if ( $count < 3 ) $count = 6;

    $links = relinks_generate( $post_id, $count );

    $rows = array_map( function( $l ) {
        return [ 'url' => $l['url'], 'anchor' => $l['anchor'] ];
    }, $links );

    update_field( 'field_relinks_list', $rows, $post_id );
    update_field( 'field_relinks_generate', 0, $post_id );
}
