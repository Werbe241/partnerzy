# Statyczna strona kursu (site/)

To jest gotowa do wdrożenia, statyczna strona kursu. Działa na dowolnym serwerze HTTP(S), bez backendu.

## Szybki start
1. Skopiuj folder `site/` na swój serwer (np. `/public_html/site/` lub `https://twojadomena.pl/site/`).
2. Wejdź w przeglądarce na adres `https://twojadomena.pl/site/`.
3. Strona wczytuje moduły z lokalnego katalogu `site/kurs/` (ustawione w assets/config.js → preferredSource: 'local').

## Dodawanie/edycja treści
- Edytuj plik Markdown w `site/kurs/`.
- Odśwież stronę (Ctrl+F5), zmiany pojawią się od razu.
- Każdy nowy moduł dodaj jako kolejny wpis w `assets/config.js` i dodaj odpowiedni `.md` do `site/kurs/`.

## Drukowanie / PDF
Kliknij „🖨️ PDF" i wybierz „Zapisz jako PDF".

## Wymagania
- Serwer HTTP(S) z możliwością serwowania plików statycznych.
- Przeglądarka z włączonym JavaScript.

## Uwaga
- Linki zewnętrzne otwierają się w nowej karcie.
- Tryb jasny/ciemny zapisywany w localStorage.
