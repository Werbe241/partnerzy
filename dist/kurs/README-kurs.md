# Kurs — instrukcja uruchomienia

Jak uruchomić lokalnie:
1. Pobierz ZIP gałęzi i rozpakuj.
2. Otwórz plik `dist/kurs/index.html` w przeglądarce (podwójne kliknięcie).

Audio (lektor):
- Domyślnie działa lektor TTS (Web Speech API, pl‑PL) po kliknięciu „Odsłuchaj”.
- Jeśli chcesz użyć nagrań, dodaj pliki MP3 do katalogu `dist/kurs/audio/` nazwane wg schematu `MODUL-STRONA.mp3`, np. `01-01.mp3`, `01-02.mp3`.
- Jeśli plik istnieje, kurs automatycznie użyje MP3 zamiast TTS.

Struktura:
```
dist/kurs/
  index.html
  style.css
  app.js
  assets/
    logo.png  (umieść tutaj swoje logo)
  modules/
    01/
      01.html … 10.html
      end.html
    02/
      01.html … 05.html
      end.html
    TEST/
      index.html (test 20 pytań, próg 80%)
```

Skróty klawiatury:
- Spacja — Odtwórz/Pauza (TTS lub MP3)
- N — Następna strona
- P — Poprzednia strona

Publikacja:
- Pliki są czysto statyczne; możesz wgrać do dowolnego hostingu lub osadzić w istniejącym serwisie (np. pod `/kk/kurs/`).
