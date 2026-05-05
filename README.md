# Relinks — плагін внутрішньої перелінковки

**Версія:** 2.0.0  
**Залежності:** WordPress, ACF PRO

Плагін автоматично генерує блоки внутрішніх посилань для сторінок сайту. Анкори і URL беруться з Google Sheets (або файлу `anchors.txt`), розподіляються рівномірно між сторінками. Обов'язкові сторінки завжди включаються в блок.

---

## Архітектура

```
todo-relinks/
├── todo-relinks.php              # Точка входу
├── includes/
│   ├── acf-setup.php             # ACF поля + реєстрація блоку
│   ├── relinking-service.php     # Бізнес-логіка
│   └── admin-tools.php           # Адмін-інструменти
├── templates/
│   └── block-relinking.php       # Рендер блоку на фронті
├── data/
│   └── relinking.json            # Локальний кеш анкорів (генерується)
├── anchors.txt                   # Ручний fallback (необов'язково)
└── README.md
```

---

## Файли та їх відповідальність

### `todo-relinks.php`
Точка входу плагіну.
- Визначає константи `RELINKS_DIR`, `RELINKS_URL`, `RELINKS_VERSION`
- Створює папку `data/` при активації
- Перевіряє наявність ACF PRO
- Підключає includes через `plugins_loaded`

---

### `includes/acf-setup.php`
Реєстрація всіх ACF-полів та Gutenberg-блоку.

**Options page** (`Settings → Relinks Options`):

| Поле | Тип | Призначення |
|---|---|---|
| `relinks_mandatory_urls` | Repeater (sub: `url`) | Обов'язкові сторінки — завжди в блоці першими |
| `relinks_gsheets_url` | Text | URL Google Sheets з анкорами |
| `relinks_custom_css` | Textarea | Кастомний CSS — додається після базових стилів |
| `relinks_enabled` | True/False | Глобальний вмикач системи |

**Post-level поля** (для кожної сторінки в редакторі):

| Поле | Тип | Призначення |
|---|---|---|
| `relinks_generate` | True/False | Тригер автогенерації при збереженні |
| `relinks_generate_count` | Number | Кількість посилань (3–20, default 6) |
| `relinks_list` | Repeater (url, anchor) | Список посилань блоку — редагується вручну |

**ACF block** `acf/internal-relinking` — реєструє Gutenberg-блок, вказує на шаблон.

**Хук `acf/save_post`** → `relinks_on_acf_save()`:  
Якщо `relinks_generate = true`, запускає генерацію, зберігає результат у `relinks_list`, скидає прапор на `false`.

---

### `includes/relinking-service.php`
Вся бізнес-логіка. Чисті функції без side-effects.

#### `relinks_load_json()`
Завантажує та кешує `data/relinking.json` у статичну змінну.  
Повертає: `[ 'https://example.com/page/' => ['Анкор 1', 'Анкор 2'], ... ]`

#### `relinks_normalize_url(string $url) → string`
Нормалізує URL до шляху зі слешем: `https://example.com/page/` → `/page/`  
Використовується для порівняння URL незалежно від домену.

#### `relinks_get_random_anchor(array $json, string $url, array $used_anchors) → string|false`
Повертає випадковий анкор для URL, якого ще немає у `$used_anchors`.

#### `relinks_get_usage_counts(?int $exclude_post_id) → array`
Сканує всі опубліковані сторінки з `relinks_list`, рахує скільки разів кожен URL вже використовується.  
Поточний пост виключається (`$exclude_post_id`) — щоб при перегенерації старі дані не впливали.  
Повертає: `[ '/page/' => 3, '/other/' => 1, ... ]`

#### `relinks_generate(int $post_id, int $count = 6) → array`
Головна функція генерації. Алгоритм:
1. Зчитує обов'язкові URL з `relinks_mandatory_urls` (option)
2. Отримує usage counts (виключаючи поточний пост)
3. Додає обов'язкові першими
4. Пул залишкових URL сортує: спочатку shuffle (рандом у тай-брейках), потім `usort` за usage count ASC
5. Заповнює решту слотів найрідше використовуваними URL

Забезпечує: жодного дублікату URL, жодного дублікату анкора, поточна сторінка не лінкується сама на себе.

#### `relinks_get_anchor_stats() → array`
Збирає всі анкори з усіх сторінок, рахує частоту.  
Повертає: `[ 'Анкор' => count, ... ]` відсортовано за спаданням.

#### `relinks_sync_gsheets() → array`
1. Зчитує URL з `relinks_gsheets_url` (option)
2. Витягує Sheet ID регексом: `/spreadsheets\/d\/([a-zA-Z0-9_-]+)/`
3. Будує CSV-запит: `https://docs.google.com/spreadsheets/d/{ID}/export?format=csv`
4. Завантажує через `wp_remote_get()` (timeout 15s)
5. Парсить CSV: стовпець 1 = анкор, стовпець 2 = URL
6. Зберігає в `data/relinking.json`

Повертає: `['success' => bool, 'count' => int, 'message' => string]`

#### `relinks_import_txt() → array`
Ручний fallback. Парсить `anchors.txt` (tab-separated) у той самий формат що і GSheets sync.  
Фільтрує рядки з "не створена" та невалідні URL.

---

### `includes/admin-tools.php`
Адмін-сторінка `Settings → Relinks`.

**Секції:**
- **Стан системи** — чи існує `relinking.json`, скільки URL, дата оновлення; статус GSheets URL
- **Синхронізація з Google Sheets** — кнопка запускає `relinks_sync_gsheets()` через `admin-post.php`
- **Імпорт з anchors.txt** — ручний fallback, кнопка активна тільки якщо файл існує
- **Статистика анкорів** — таблиця всіх анкорів з кількістю використань (через `relinks_get_anchor_stats()`)

**POST-хендлери** (nonce + `manage_options`):
- `admin_post_relinks_sync_gsheets`
- `admin_post_relinks_import`

---

### `templates/block-relinking.php`
Рендер блоку на фронтенді та в редакторі.

- Перевіряє `relinks_enabled` — якщо вимкнено, повертає без виводу
- Якщо `relinks_list` порожній і це редактор — виводить placeholder
- Виводить `relinks_custom_css` з налаштувань у тег `<style>` (один раз за запит, guard-константа `RELINKS_CSS_DONE`)
- Рендерить `<ul class="relinks__list">` зі списком посилань

**CSS класи:**
- `.relinks__list` — flex-контейнер, wrap, центрований
- `.relinks__item` — елемент списку, `flex: 0 0 auto` (не стискається)
- `.relinks__link` — посилання, `white-space: nowrap` (текст в один рядок)

---

## Джерело даних (Google Sheets)

Таблиця має два стовпці:

| A (Анкор) | B (URL) |
|---|---|
| Автоматизація виробництва | https://example.com/page/ |
| Автоматизація складу | https://example.com/page/ |
| CRM для бізнесу | https://example.com/crm/ |

Одна сторінка може мати кілька анкорів — генератор щоразу вибирає один випадковий.  
Рядки з "не створена" та невалідні URL ігноруються.  
Таблиця має бути відкрита: **Поділитися → Будь-хто з посиланням може переглядати**.

---

## Логіка рівномірного розподілу

При генерації посилань для нової сторінки:
1. Підраховується, скільки разів кожен URL вже зустрічається на **інших** сторінках
2. URL сортуються від найменш використовуваного до найбільш
3. Рівні значення — рандомізуються між собою
4. Нова сторінка отримує найменш використовувані URL

При **перегенерації** існуючої сторінки її старі посилання виключаються з підрахунку — і розподіл рахується заново без них.

---

## Швидкий старт на новому сайті

1. Завантажити та активувати плагін (потрібен ACF PRO)
2. **Settings → Relinks Options:**
   - Додати обов'язкові сторінки (або залишити порожнім)
   - Вставити URL Google Sheets
   - Опційно: додати Custom CSS
3. **Settings → Relinks → Синхронізувати з Google Sheets**
4. На будь-якій сторінці: вставити блок **"Блок перелінковки"** в Gutenberg
5. У бічній панелі редактора: увімкнути **🔄 Автогенерація** і зберегти сторінку
6. Перевірити результат у блоці або через **Settings → Relinks → Статистика анкорів**
