# KK Lite - Instrukcja wdrożenia

## Przegląd

KK Lite to lekka wtyczka WordPress do zarządzania certyfikatami koordynatorów. Zapewnia pełne API REST, panel zarządzania certyfikatami oraz publiczną weryfikację.

## Wymagania

- WordPress 5.0 lub nowszy
- PHP 7.0 lub nowszy
- MySQL 5.6 lub nowszy
- Permalinki włączone (nie "Proste")

## Instalacja

### Krok 1: Upload plików

1. Sklonuj lub pobierz repozytorium
2. Skopiuj folder `wp-content/plugins/kk-lite/` do swojej instalacji WordPress w `wp-content/plugins/`
3. (Opcjonalnie) Skopiuj pliki z `wp-content/mu-plugins/` do `wp-content/mu-plugins/` w WordPress

### Krok 2: Aktywacja wtyczki

1. Zaloguj się do panelu administracyjnego WordPress
2. Przejdź do **Wtyczki** → **Zainstalowane wtyczki**
3. Znajdź **KK Lite** i kliknij **Aktywuj**
4. Wtyczka automatycznie utworzy wymagane tabele w bazie danych:
   - `{prefix}kk_course_results` - wyniki testów
   - `{prefix}kk_certificates` - certyfikaty

### Krok 3: Konfiguracja permalinków

1. Przejdź do **Ustawienia** → **Permalinki**
2. Kliknij **Zapisz zmiany** (nie musisz nic zmieniać, wystarczy zapisać)
3. To spowoduje odświeżenie reguł przepisywania URL

### Krok 4: Weryfikacja instalacji

Sprawdź czy następujące URL działają:

1. **Panel certyfikatów** (wymaga logowania):
   ```
   https://werbekoordinator.pl/kk/certyfikaty/
   ```

2. **Strona weryfikacji** (publiczna):
   ```
   https://werbekoordinator.pl/kk/weryfikacja/
   ```

3. **API endpoint** (wymaga logowania):
   ```
   https://werbekoordinator.pl/wp-json/kk/v1/certificate/my
   ```
   Powinien zwrócić JSON `[]` lub listę certyfikatów

## Użytkowanie

### Wydawanie certyfikatów

1. Zaloguj się do WordPress
2. Otwórz `https://werbekoordinator.pl/kk/certyfikaty/`
3. Kliknij przycisk "Wydaj certyfikat KR/MR/RT"
4. Certyfikat zostanie automatycznie wygenerowany z numerem w formacie: `KR-YYYYMMDD-####`

### Weryfikacja certyfikatów

1. Otwórz `https://werbekoordinator.pl/kk/weryfikacja/`
2. Wprowadź numer certyfikatu (np. `KR-20240101-0001`)
3. Kliknij "Weryfikuj"
4. System wyświetli informacje o certyfikacie lub komunikat o braku certyfikatu

### Podgląd certyfikatu

W panelu certyfikatów kliknij "Podgląd" przy wybranym certyfikacie. Wyświetli się elegancki certyfikat z:
- Numerem certyfikatu
- Datą wydania i ważności
- Kodem QR do weryfikacji
- Możliwością wydruku lub pobrania jako PNG

## REST API

### Endpointy (wymagają logowania)

#### POST `/wp-json/kk/v1/test-result`
Zapisz wynik testu.

**Body:**
```json
{
  "module_id": 1,
  "score": 85,
  "passed": true
}
```

#### POST `/wp-json/kk/v1/certificate/issue`
Wydaj nowy certyfikat.

**Body:**
```json
{
  "role": "KR",
  "user_id": null
}
```
Parametr `user_id` jest opcjonalny. Tylko administratorzy mogą wydawać certyfikaty dla innych użytkowników.

**Response:**
```json
{
  "success": true,
  "cert_no": "KR-20240101-0001",
  "issued_at": "2024-01-01 12:00:00",
  "valid_until": "2026-01-01 12:00:00"
}
```

#### GET `/wp-json/kk/v1/certificate/my`
Pobierz certyfikaty zalogowanego użytkownika.

**Response:**
```json
[
  {
    "cert_no": "KR-20240101-0001",
    "role": "KR",
    "issued_at": "2024-01-01 12:00:00",
    "valid_until": "2026-01-01 12:00:00",
    "status": "active"
  }
]
```

### Endpointy publiczne

#### GET `/wp-json/kk/v1/certificate/verify?cert_no=KR-20240101-0001`
Weryfikuj autentyczność certyfikatu.

**Response:**
```json
{
  "found": true,
  "data": {
    "cert_no": "KR-20240101-0001",
    "role": "KR",
    "owner": "Jan Kowalski",
    "issued_at": "2024-01-01 12:00:00",
    "valid_until": "2026-01-01 12:00:00",
    "status": "active"
  }
}
```

## Must-Use Plugins (opcjonalne)

### kk-safe-view.php

Hotfix, który zapewnia działanie `/kk/certyfikaty/` i `/kk/weryfikacja/` nawet jeśli:
- Rewrite rules nie zostały odświeżone
- Główny plugin KK Lite jest wyłączony
- Inny plugin/motyw powoduje konflikty

**Instalacja:**
```bash
cp wp-content/mu-plugins/kk-safe-view.php [wordpress]/wp-content/mu-plugins/
```

### zz-freeze-plugins.php

Narzędzie do tymczasowego wyłączania wybranych pluginów bez zmiany nazwy folderów.

**Użycie:**
1. Edytuj plik i odkomentuj/dodaj ścieżkę pluginu w tablicy `$plugins_to_freeze`
2. Zapisz plik
3. Plugin zostanie wyłączony bez konieczności zmiany nazwy folderu

**Przykład:**
```php
private $plugins_to_freeze = array(
    'kk-lite/kk-lite.php',  // wyłącz KK Lite
);
```

## Uwagi dotyczące bezpieczeństwa

### Omijanie nonce dla zalogowanych użytkowników

Plugin implementuje filtr `rest_authentication_errors`, który pozwala zalogowanym użytkownikom na dostęp do API bez nagłówka `X-WP-Nonce`. Jest to bezpieczne, ponieważ:

1. Wymaga aktywnej sesji WordPress (cookie)
2. Dotyczy tylko zalogowanych użytkowników
3. Upraszcza integrację z CDN/cache
4. Publiczne endpointy (/certificate/verify) są dostępne dla wszystkich

### Zalecenia

- Regularnie aktualizuj WordPress i wtyczki
- Używaj silnych haseł dla kont administratorów
- Rozważ włączenie SSL (HTTPS) dla całej witryny
- Monitoruj logi dostępu do API

## Troubleshooting

### Problem: 404 na /kk/certyfikaty/

**Rozwiązanie:**
1. Przejdź do Ustawienia → Permalinki → Zapisz zmiany
2. Jeśli nie pomaga, sprawdź czy wtyczka jest aktywna
3. Zainstaluj MU plugin `kk-safe-view.php` jako awaryjne rozwiązanie

### Problem: API zwraca błąd nonce

**Rozwiązanie:**
1. Sprawdź czy użytkownik jest zalogowany
2. Wyczyść cache przeglądarki i WordPress
3. Sprawdź czy nie ma konfliktów z innymi pluginami bezpieczeństwa

### Problem: Certyfikat nie wyświetla się poprawnie

**Rozwiązanie:**
1. Sprawdź czy wszystkie pliki są prawidłowo załadowane (Console w DevTools)
2. Upewnij się, że ścieżki do plików CSS/JS są poprawne
3. Wyczyść cache przeglądarki

### Problem: Tabele bazy danych nie zostały utworzone

**Rozwiązanie:**
1. Dezaktywuj i ponownie aktywuj wtyczkę
2. Sprawdź uprawnienia użytkownika bazy danych
3. Sprawdź logi błędów PHP

## Kontakt i wsparcie

W razie problemów lub pytań:
- Email: support@werbekoordinator.pl
- Repozytorium: https://github.com/Werbe241/partnerzy

## Changelog

### 1.0.4 (2024-01-01)
- Pierwsza publiczna wersja
- Pełne REST API dla certyfikatów
- Panel zarządzania certyfikatami
- Publiczna weryfikacja
- MU plugins dla awaryjnej pracy
- Omijanie nonce dla zalogowanych użytkowników
