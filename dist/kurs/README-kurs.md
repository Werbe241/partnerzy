# Kurs Werbekoordinator - Dokumentacja

## Wprowadzenie

Witaj w kursie online dla Werbekoordinatorów! To kompleksowe szkolenie przygotowuje do pracy jako koordynator współpracy z partnerami biznesowymi fundacji.

## 🚀 Jak otworzyć kurs?

### Metoda 1: Otwieranie lokalne (najproste)

1. Pobierz lub sklonuj repozytorium
2. Przejdź do folderu `dist/kurs/`
3. **Kliknij dwukrotnie na plik `index.html`**
4. Kurs otworzy się w Twojej domyślnej przeglądarce

**To wszystko!** Kurs działa całkowicie lokalnie, bez potrzeby serwera.

### Metoda 2: Lokalny serwer (opcjonalnie)

Jeśli wolisz używać lokalnego serwera (nie jest to wymagane):

```bash
cd dist/kurs/
python -m http.server 8000
# lub
npx serve
```

Następnie otwórz: `http://localhost:8000`

## 📚 Struktura kursu

Kurs składa się z:

### Moduł 1: Sens Werbekoordinatora (10 lekcji + zakończenie)
- 01: Wprowadzenie - cel kursu i rola
- 02: Misja i sens działania
- 03: Odpowiedzialność i standardy etyczne (RODO)
- 04: Granice roli
- 05: Kompetencje miękkie
- 06: Przykładowy dzień pracy
- 07: Najczęstsze błędy
- 08: Checklista jakości
- 09: Mini-case: od kontaktu do wdrożenia
- 10: Podsumowanie i zadanie domowe
- end: Ekran zakończenia modułu

### Moduł 2: Werbekoordinator w praktyce (5 lekcji + zakończenie)
- 01: Oferta i korzyści
- 02: Rola koordynatora - zadania i standardy
- 03: Proces od kontaktu do wdrożenia
- 04: Narzędzia i materiały
- 05: Komunikacja i RODO
- end: Ekran zakończenia modułu

### Test końcowy
- 20 pytań jednokrotnego wyboru
- Próg zaliczenia: 80% (16/20 poprawnych odpowiedzi)
- Możliwość powtórzenia testu

## 🎧 Lektor TTS (Text-to-Speech)

Kurs posiada wbudowany lektor tekstów wykorzystujący Web Speech API przeglądarki.

### Obsługiwane przeglądarki
- ✅ Google Chrome / Chromium
- ✅ Microsoft Edge
- ✅ Safari (macOS/iOS)
- ⚠️ Firefox (ograniczone wsparcie)

### Funkcje audio:
- **Odtwórz** - odczytuje treść aktualnej strony
- **Pauza** - zatrzymuje odczyt
- **Stop** - kończy odczyt
- **Odsłuchaj ponownie** - odtwarza od początku
- **Prędkość** - regulacja 0.75x - 1.5x
- **Wybór głosu** - dostępne głosy polskie (zależne od systemu)

### Skróty klawiszowe:
- `Space` - Odtwórz/Pauza
- `N` - Następna strona
- `P` - Poprzednia strona

## 🔊 Własne pliki MP3 (opcjonalnie)

Możesz dodać własne nagrania audio zamiast TTS:

1. Nagraj lub wygeneruj pliki MP3 dla poszczególnych stron
2. Umieść je w folderze `dist/kurs/audio/`
3. Nazwij według schematu: `{moduł}-{strona}.mp3`

**Przykłady:**
```
audio/01-01.mp3  → Moduł 1, strona 1
audio/01-02.mp3  → Moduł 1, strona 2
audio/02-01.mp3  → Moduł 2, strona 1
audio/TEST-index.mp3 → Test końcowy
```

**Aplikacja automatycznie wykryje pliki MP3 i użyje ich zamiast TTS.**

## 📱 Responsywność

Kurs jest w pełni responsywny i działa na:
- 💻 Komputerach (Windows, macOS, Linux)
- 📱 Tabletach (iPad, Android tablets)
- 📱 Smartfonach (iOS, Android)

Na urządzeniach mobilnych sidebar jest zwijany - kliknij przycisk ☰ aby go otworzyć.

## ♿ Dostępność (A11y)

Kurs został zaprojektowany z myślą o dostępności:

- **Kontrast kolorów** - spełnia standardy WCAG AA
- **Nawigacja klawiaturą** - pełna obsługa bez myszy
- **Screen readers** - kompatybilność z czytnikami ekranu
- **ARIA labels** - odpowiednie etykiety dla technologii asystujących
- **Focus states** - wyraźne wskaźniki fokusa
- **Skróty klawiszowe** - szybka nawigacja

## 💾 Zapisywanie postępu

Postęp w kursie jest **automatycznie zapisywany** w pamięci przeglądarki (localStorage):

- Ostatnio odwiedzona strona
- Lista ukończonych lekcji
- Ogólny postęp (procent ukończenia)

**Uwaga:** Jeśli wyczyścisz dane przeglądarki (cache, cookies), postęp zostanie usunięty.

## 🔧 Jak dodać lub zmienić treść?

### Dodawanie nowej strony do modułu:

1. Utwórz nowy plik HTML w odpowiednim folderze modułu, np:
   ```
   dist/kurs/modules/01/11.html
   ```

2. Dodaj treść w formacie HTML (możesz skopiować strukturę z istniejącej strony)

3. Zaktualizuj `index.html` - dodaj link w sekcji sidebar:
   ```html
   <li><a href="#" data-module="01" data-page="11">11. Tytuł nowej lekcji</a></li>
   ```

4. Zaktualizuj `app.js` - dodaj stronę do struktury kursu:
   ```javascript
   '01': { pages: ['01', '02', ... '11', 'end'], name: 'Sens Werbekoordinatora' }
   ```

### Edycja istniejącej treści:

1. Otwórz odpowiedni plik HTML w edytorze tekstu
2. Wprowadź zmiany
3. Zapisz i odśwież przeglądarkę

### Zmiana stylów (kolorów, czcionek):

Edytuj plik `dist/kurs/style.css`, zwłaszcza sekcję `:root` z zmiennymi CSS:

```css
:root {
    --bg-primary: #1a1a2e;        /* Główne tło */
    --accent-primary: #e94560;     /* Kolor akcentu */
    --text-primary: #eee;          /* Kolor tekstu */
    /* ... */
}
```

## 🌐 Integracja z WordPress

Aby osadzić kurs w WordPress pod adresem `/kk/kurs/`:

### Metoda 1: Bezpośrednie wgranie plików

1. Skopiuj cały folder `dist/kurs/` do:
   ```
   /public_html/kk/kurs/
   ```

2. Kurs będzie dostępny pod:
   ```
   https://twoja-domena.pl/kk/kurs/
   ```

### Metoda 2: Osadzenie przez iframe

1. Wgraj pliki jak w Metodzie 1

2. Utwórz stronę w WordPress

3. Dodaj kod HTML (blok "Custom HTML"):
   ```html
   <iframe 
       src="/kk/kurs/index.html" 
       style="width:100%; height:100vh; border:none;"
       title="Kurs Werbekoordinator">
   </iframe>
   ```

### Metoda 3: Integracja w motywie

Skonsultuj się z developerem WordPress - możliwe jest głębsze zintegrowanie kursu z tematem WP.

## 🛠️ Wymagania techniczne

### Minimalne wymagania:
- Przeglądarka: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- JavaScript musi być włączony
- LocalStorage włączony (do zapisywania postępu)

### Zalecane:
- Szybkie łącze internetowe (jeśli korzystasz z TTS online voices)
- Głośniki lub słuchawki (dla funkcji lektora)
- Ekran o rozdzielczości min. 1024x768 (dla komputerów)

## 📊 Struktura plików

```
dist/kurs/
├── index.html              # Główny plik aplikacji
├── style.css               # Wszystkie style CSS
├── app.js                  # Logika aplikacji (nawigacja, TTS, postęp)
├── README-kurs.md          # Ten plik
├── modules/
│   ├── 01/                 # Moduł 1
│   │   ├── 01.html
│   │   ├── 02.html
│   │   ├── ...
│   │   ├── 10.html
│   │   └── end.html
│   ├── 02/                 # Moduł 2
│   │   ├── 01.html
│   │   ├── ...
│   │   ├── 05.html
│   │   └── end.html
│   └── TEST/               # Test końcowy
│       └── index.html
└── audio/                  # (opcjonalnie) Pliki MP3
    ├── 01-01.mp3
    ├── 01-02.mp3
    └── ...
```

## 🐛 Rozwiązywanie problemów

### Kurs nie ładuje się lokalnie
- Sprawdź czy otworzyłeś plik `index.html` (nie inny plik)
- Sprawdź czy JavaScript jest włączony w przeglądarce
- Spróbuj innej przeglądarki (Chrome zalecany)

### Lektor nie działa
- Sprawdź czy przeglądarka obsługuje Web Speech API (Chrome/Edge/Safari)
- Sprawdź czy głośniki/słuchawki są podłączone
- Sprawdź czy dźwięk nie jest wyciszony w systemie
- Firefox ma ograniczone wsparcie - użyj Chrome

### Postęp nie zapisuje się
- Sprawdź czy nie przeglądasz w trybie incognito/prywatnym
- Sprawdź ustawienia przeglądarki - czy localStorage jest włączony
- Nie czyść danych przeglądarki po każdej sesji

### Sidebar nie działa na mobile
- Kliknij przycisk ☰ w górnym rogu
- Spróbuj odświeżyć stronę

### Treść nie wyświetla się poprawnie
- Sprawdź czy wszystkie pliki są w odpowiednich folderach
- Otwórz konsolę przeglądarki (F12) i sprawdź błędy
- Upewnij się że ścieżki do plików są poprawne

## 📝 Licencja i użytkowanie

Ten kurs został stworzony dla fundacji w ramach szkolenia Werbekoordinatorów.

- ✅ Możesz używać kursu wewnętrznie w organizacji
- ✅ Możesz modyfikować treści i dostosowywać do potrzeb
- ✅ Możesz dodawać własne moduły i materiały
- ❌ Nie sprzedawaj kursu jako produktu komercyjnego
- ❌ Zachowaj informacje o autorach (jeśli są)

## 🆘 Wsparcie

Jeśli masz pytania lub problemy:

1. Sprawdź sekcję "Rozwiązywanie problemów" powyżej
2. Otwórz konsolę przeglądarki (F12) i sprawdź komunikaty błędów
3. Skontaktuj się z administratorem systemu fundacji

## 📅 Aktualizacje

### Wersja 1.0 (Aktualna)
- Moduł 1: Sens Werbekoordinatora (10 lekcji)
- Moduł 2: Werbekoordinator w praktyce (5 lekcji)
- Test końcowy (20 pytań)
- Lektor TTS
- Zapisywanie postępu
- Pełna responsywność
- Dostępność (A11y)

### Planowane funkcje:
- Możliwość wydruku certyfikatu po zaliczeniu
- Dodatkowe moduły tematyczne
- System quizów po każdym module
- Tryb offline (PWA)

---

**Powodzenia w nauce!** 🎓

Jeśli masz sugestie ulepszeń, skontaktuj się z zespołem fundacji.
