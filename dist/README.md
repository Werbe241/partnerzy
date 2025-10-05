# KK Lite - Dokumentacja

## Spis treści
1. [Wprowadzenie](#wprowadzenie)
2. [Konfiguracja ID systemowego](#konfiguracja-id-systemowego)
3. [Ustawienie kursu KR](#ustawienie-kursu-kr)
4. [Wystawianie certyfikatów](#wystawianie-certyfikatów)
5. [Weryfikacja certyfikatów](#weryfikacja-certyfikatów)
6. [Szybkie testy](#szybkie-testy)

## Wprowadzenie

KK Lite to wtyczka WordPress do zarządzania kursem Koordynatora Reklamy i certyfikatami (KR, MR, RT).

### Główne funkcje:
- Kurs online z testami dla Koordynatorów Reklamy (KR)
- System certyfikatów oparty na zewnętrznym ID systemowym
- Weryfikacja certyfikatów
- Panel administracyjny do wystawiania certyfikatów MR i RT
- Integracja z WooCommerce (opcjonalna)

## Konfiguracja ID systemowego

### Dodawanie pola "ID systemowe" do profilu użytkownika

Wtyczka używa zewnętrznego ID systemowego zamiast WordPress user_id dla certyfikatów.

#### Kolejność wyszukiwania ID:
1. `kk_system_id` (preferowane)
2. `promoter_id` (fallback)
3. `werbeko_id` (fallback)

### Jak dodać pole w profilu użytkownika:

Dodaj ten kod do `functions.php` w swoim motywie lub do wtyczki:

```php
// Dodaj pole ID systemowe do profilu użytkownika
add_action('show_user_profile', 'kk_add_system_id_field');
add_action('edit_user_profile', 'kk_add_system_id_field');

function kk_add_system_id_field($user) {
  ?>
  <h3>ID Systemowe Werbekoordinator</h3>
  <table class="form-table">
    <tr>
      <th><label for="kk_system_id">ID systemowe</label></th>
      <td>
        <input type="text" name="kk_system_id" id="kk_system_id" 
               value="<?php echo esc_attr(get_user_meta($user->ID, 'kk_system_id', true)); ?>" 
               class="regular-text" />
        <p class="description">Twój unikalny identyfikator w systemie (np. 00000011005)</p>
      </td>
    </tr>
  </table>
  <?php
}

// Zapisz pole ID systemowe
add_action('personal_options_update', 'kk_save_system_id_field');
add_action('edit_user_profile_update', 'kk_save_system_id_field');

function kk_save_system_id_field($user_id) {
  if (!current_user_can('edit_user', $user_id)) {
    return false;
  }
  
  if (isset($_POST['kk_system_id'])) {
    update_user_meta($user_id, 'kk_system_id', sanitize_text_field($_POST['kk_system_id']));
  }
}
```

### Filtr niestandardowy

Możesz nadpisać sposób pobierania ID systemowego za pomocą filtra:

```php
add_filter('kk_get_user_system_id', function($system_id, $user_id) {
  // Twoja własna logika
  return $system_id;
}, 10, 2);
```

## Ustawienie kursu KR

### Podstawowa konfiguracja

1. **Utwórz nową stronę WordPress**
   - W panelu admin przejdź do: Strony → Dodaj nową
   - Tytuł: "Zostań Koordynatorem Reklamy"
   
2. **Dodaj shortcode do treści strony:**
   ```
   [kk_course]
   ```

3. **Opublikuj stronę**

### Integracja z WooCommerce

Jeśli masz zainstalowany WooCommerce, wtyczka automatycznie:
- Doda zakładkę "Zostań Koordynatorem Reklamy" w menu "Moje konto"
- Wyświetli kurs w tej zakładce

**Nie musisz niczego dodawać - wszystko dzieje się automatycznie!**

### Dostępne adresy URL:

- `/kk/certyfikaty/` - Panel użytkownika z certyfikatami
- `/kk/weryfikacja/` - Publiczna weryfikacja certyfikatów
- `/kk-safe/` - Bezpieczny widok certyfikatów (MU plugin)

## Wystawianie certyfikatów

### Certyfikat KR (Koordynator Reklamy)

**Wystawiany automatycznie** po zdaniu kursu:
1. Użytkownik przechodzi wszystkie moduły
2. Zdaje test końcowy (minimum 70%)
3. System automatycznie wydaje certyfikat KR

Format numeru: `KR-{EXTERNAL_ID}`
Przykład: `KR-00000011005`

### Certyfikaty MR i RT (tylko admin)

Administratorzy mogą wystawiać certyfikaty MR (Menadżer Regionalny) i RT (Regional Trainer):

#### Opcja 1: Panel certyfikatów `/kk/certyfikaty/`
1. Zaloguj się jako administrator
2. Przejdź do `/kk/certyfikaty/`
3. W sekcji "Panel administratora" wpisz EXTERNAL_ID
4. Kliknij "Wydaj MR po ID" lub "Wydaj RT po ID"

#### Opcja 2: Safe View `/kk-safe/`
1. Przejdź do `/kk-safe/`
2. W sekcji administratora wpisz EXTERNAL_ID
3. Kliknij "Wydaj MR" lub "Wydaj RT"

#### Opcja 3: REST API

```bash
curl -X POST "https://twoja-domena.pl/wp-json/kk/v1/certificate/issue" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{
    "role": "MR",
    "external_id": "00000011005"
  }'
```

## Weryfikacja certyfikatów

### Przez stronę weryfikacji

Przejdź do: `/kk/weryfikacja/?cert_no=KR-00000011005`

### Wyszukiwanie po ID

Możesz szukać używając:
- **Pełnego numeru** (z prefiksem): `KR-00000011005`
- **Samego ID** (bez prefiksu): `00000011005`

Przy wyszukiwaniu po samym ID, system pokaże **wszystkie certyfikaty** przypisane do tego ID.

### REST API

```bash
# Po pełnym numerze
curl "https://twoja-domena.pl/wp-json/kk/v1/certificate/verify?cert_no=KR-00000011005"

# Po samym ID (zwróci wszystkie certyfikaty)
curl "https://twoja-domena.pl/wp-json/kk/v1/certificate/verify?cert_no=00000011005"
```

## Szybkie testy

### Test 1: Wydanie certyfikatu KR z kursu

1. Zaloguj się jako użytkownik z ustawionym `kk_system_id`
2. Przejdź na stronę kursu (gdzie jest `[kk_course]`)
3. Przejrzyj wszystkie 6 modułów
4. Rozpocznij test końcowy
5. Zdobądź minimum 70% punktów
6. Sprawdź czy otrzymałeś certyfikat w formacie `KR-{TWOJE_ID}`

### Test 2: Wydanie MR/RT przez admina

1. Zaloguj się jako administrator
2. Przejdź do `/kk/certyfikaty/` lub `/kk-safe/`
3. W panelu administratora wpisz EXTERNAL_ID (np. `00000011005`)
4. Kliknij "Wydaj MR po ID"
5. Sprawdź komunikat o powodzeniu i numer certyfikatu

### Test 3: Weryfikacja z prefiksem

1. Przejdź do `/kk/weryfikacja/?cert_no=KR-00000011005`
2. Powinieneś zobaczyć szczegóły certyfikatu:
   - Status
   - Numer
   - Właściciel
   - Rola
   - ID systemowe
   - Data wystawienia

### Test 4: Weryfikacja samym ID

1. Przejdź do `/kk/weryfikacja/?cert_no=00000011005`
2. Powinieneś zobaczyć **listę wszystkich certyfikatów** dla tego ID:
   - KR-00000011005
   - MR-00000011005 (jeśli został wystawiony)
   - RT-00000011005 (jeśli został wystawiony)

## Struktura bazy danych

### Tabela: `wp_kk_certificates`

```sql
CREATE TABLE wp_kk_certificates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cert_no VARCHAR(64) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NOT NULL,
  external_id VARCHAR(64) NULL,
  role VARCHAR(8) NOT NULL,
  issued_at DATETIME NOT NULL,
  valid_until DATETIME NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'valid',
  meta LONGTEXT NULL,
  PRIMARY KEY (id),
  KEY user_idx (user_id),
  KEY external_id_idx (external_id),
  KEY role_idx (role)
)
```

### Tabela: `wp_kk_course_results`

```sql
CREATE TABLE wp_kk_course_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  module_id INT UNSIGNED NOT NULL,
  score INT UNSIGNED NOT NULL,
  passed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY user_idx (user_id),
  KEY module_idx (module_id)
)
```

## REST API Endpoints

### GET `/kk/v1/certificate/my`
Pobiera certyfikaty zalogowanego użytkownika.

### POST `/kk/v1/certificate/issue`
Wystawia nowy certyfikat.
```json
{
  "role": "KR|MR|RT",
  "external_id": "00000011005"  // opcjonalne, tylko dla adminów
}
```

### GET `/kk/v1/certificate/verify?cert_no={number}`
Weryfikuje certyfikat po numerze lub external_id.

### GET `/kk/v1/certificate/check-kr`
Sprawdza czy użytkownik ma już certyfikat KR.

### POST `/kk/v1/test-result`
Zapisuje wynik testu.
```json
{
  "module_id": 999,
  "score": 85,
  "passed": 1
}
```

## Rozwiązywanie problemów

### Certyfikaty nie ładują się
- Sprawdź czy użytkownik jest zalogowany
- Otwórz konsolę przeglądarki (F12) i sprawdź błędy
- Zweryfikuj czy REST API działa: `/wp-json/kk/v1/certificate/my`

### Nie można wydać certyfikatu MR/RT
- Upewnij się że jesteś zalogowany jako administrator
- Sprawdź czy EXTERNAL_ID jest poprawny
- Sprawdź czy certyfikat nie został już wydany dla tego ID

### Kurs nie wyświetla się w WooCommerce
- Sprawdź czy WooCommerce jest aktywny
- Przejdź do Ustawienia → Linki stałe i kliknij "Zapisz zmiany"
- Wyczyść cache WordPress i przeglądarki

### Test końcowy nie działa
- Upewnij się że plik `/kk-lite/data/questions.json` istnieje
- Sprawdź czy przejrzałeś wszystkie 6 modułów
- Sprawdź konsolę przeglądarki pod kątem błędów JavaScript

## Wsparcie

W razie problemów:
1. Sprawdź logi błędów WordPress
2. Włącz tryb debugowania w `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
3. Sprawdź plik `/wp-content/debug.log`

## Changelog

### Wersja 1.1.0
- Dodano obsługę external_id dla certyfikatów
- Nowy format numeru certyfikatu: `{ROLE}-{EXTERNAL_ID}`
- Shortcode `[kk_course]` dla kursu KR
- Integracja z WooCommerce My Account
- Admin może wystawiać MR/RT po external_id
- Weryfikacja obsługuje wyszukiwanie po ID bez prefiksu
- Poprawione komunikaty błędów
- MU plugin kk-safe-view.php
- Strona ustawień administratora "KK Lite → Kurs"

## Struktura katalogów

```
wp-content/
├── plugins/
│   ├── kk-lite/              # Główna wtyczka
│   │   ├── kk-lite.php       # Plik główny
│   │   ├── templates/        # Szablony HTML
│   │   │   ├── app.html      # Panel certyfikatów
│   │   │   ├── course.html   # Kurs KR
│   │   │   └── verify.html   # Weryfikacja
│   │   └── data/
│   │       └── questions.json # Pytania testowe
│   └── koordynator-kurs/     # Treści kursów
│       └── templates/        # Moduły kursu
└── mu-plugins/
    └── kk-safe-view.php      # Safe View (hotfix)
dist/
└── README.md                  # Ta dokumentacja
```
