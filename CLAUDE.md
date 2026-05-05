# CLAUDE.md — Relinks Plugin

WordPress плагін внутрішньої перелінковки v2.0.0. Залежність: **ACF PRO**.

## Структура файлів

```
relinks/
├── relinks.php                   # Точка входу, константи RELINKS_DIR / RELINKS_URL / RELINKS_VERSION
├── includes/
│   ├── acf-setup.php             # ACF поля, реєстрація блоку, хук acf/save_post
│   ├── relinking-service.php     # Вся бізнес-логіка (чисті функції)
│   └── admin-tools.php           # Адмін UI, обробники форм
├── templates/
│   └── block-relinking.php       # Рендер ACF-блоку на фронтенді
└── data/
    └── relinking.json            # Auto-generated. Не редагувати вручну.
```

## Конвенції найменування

| Тип | Префікс | Приклад |
|---|---|---|
| PHP функції | `relinks_` | `relinks_generate()` |
| PHP константи | `RELINKS_` | `RELINKS_DIR` |
| CSS класи | `relinks__` | `.relinks__list` |
| ACF field keys | `field_relinks_` | `field_relinks_list` |
| ACF group keys | `group_relinks_` | `group_relinks_post` |
| Адмін slugs | `relinks-` | `relinks-options` |

**Заборонено:** `todo_relinks_`, `TODO_RELINKS_`, `todo-relinks-` — це старі префікси.

## Критичні правила

**CSS — тільки через адмін.**
Ніколи не додавати inline `<style>` у `templates/block-relinking.php`. Всі стилі — виключно через поле `relinks_custom_css` (Settings → Relinks Options). Шаблон виводить тільки HTML + CSS з цього поля.

**Без hardcoded URL і кольорів.**
Жодних конкретних доменів, шляхів або кольорів (`#fe5000` тощо) у PHP-коді чи шаблонах.

**`data/relinking.json` — auto-generated.**
Файл перезаписується при GSheets sync або імпорті anchors.txt. Не редагувати вручну, не комітити.

## Ключові функції (`includes/relinking-service.php`)

- `relinks_generate($post_id, $count)` — генерує посилання з рівномірним розподілом: обов'язкові URL першими, решта — найрідше використовувані з пулу
- `relinks_get_usage_counts($exclude_post_id)` — рахує скільки разів кожен URL вже використовується на сайті (виключає поточний пост)
- `relinks_sync_gsheets()` — завантажує CSV з Google Sheets, зберігає в `relinking.json`
- `relinks_get_anchor_stats()` — повертає `[anchor => count]` для таблиці статистики в адміні
- `relinks_import_txt()` — ручний fallback: парсить `anchors.txt` → `relinking.json`

## ACF поля

**Options (Settings → Relinks Options):**
- `relinks_mandatory_urls` — repeater обов'язкових URL (sub-field: `url`)
- `relinks_gsheets_url` — URL Google Sheets
- `relinks_custom_css` — CSS блоку
- `relinks_enabled` — глобальний вмикач

**Post-level:**
- `relinks_generate` — тригер автогенерації
- `relinks_generate_count` — кількість посилань (3–20)
- `relinks_list` — repeater посилань (sub-fields: `url`, `anchor`)

## Типові задачі

**Додати нове поле налаштувань** → `includes/acf-setup.php`, масив `fields` у `group_relinks_options`

**Змінити логіку генерації** → `includes/relinking-service.php`, функція `relinks_generate()`

**Змінити вигляд блоку** → CSS через адмін-панель (`relinks_custom_css`), не в шаблоні

**Додати адмін-інструмент** → `includes/admin-tools.php`, функція `relinks_admin_page()`
