// KONFIG: działa z plikami lokalnymi (na Twoim serwerze)
window.KURS_CONFIG = {
  title: 'Kurs Koordynatora Reklamy',
  organization: 'Fundacja Werbekoordynator',
  modules: [
    {
      id: 'modul-01',
      title: 'Moduł 1: Podstawy roli Koordynatora Reklamy',
      // Ten plik już jest w tym samym folderze „kurs/"
      localPath: 'Modul-01_Podstawy-Koordynatora-Reklamy.txt'
    },
    {
      id: 'modul-02',
      title: 'Moduł 2: Praktyka Koordynatora Reklamy',
      localPath: 'Modul-02_Praktyka-KR.txt'
    },
    {
      id: 'modul-03',
      title: 'Moduł 3: Narzędzia i technologie',
      localPath: 'Modul-03_Narzedzia-Technologie.txt'
    },
    {
      id: 'modul-04',
      title: 'Moduł 4: Komunikacja i relacje',
      localPath: 'Modul-04_Komunikacja-Relacje.txt'
    },
    {
      id: 'modul-05',
      title: 'Moduł 5: Wdrożenia i case studies',
      localPath: 'Modul-05_Wdrozenia-Case-Studies.txt'
    },
    {
      id: 'modul-06',
      title: 'Moduł 6: Analityka i monitoring',
      localPath: 'Modul-06_Analityka-Monitoring.txt'
    },
    {
      id: 'modul-07',
      title: 'Moduł 7: Zarządzanie projektami',
      localPath: 'Modul-07_Zarzadzanie-Projektami.txt'
    },
    {
      id: 'modul-08',
      title: 'Moduł 8: Marketing i promocja',
      localPath: 'Modul-08_Marketing-Promocja.txt'
    },
    {
      id: 'modul-09',
      title: 'Moduł 9: Finanse i rozliczenia',
      localPath: 'Modul-09_Finanse-Rozliczenia.txt'
    },
    {
      id: 'modul-10',
      title: 'Moduł 10: Rozwój zawodowy',
      localPath: 'Modul-10_Rozwoj-Zawodowy-KR.txt'
    },
    {
      id: 'modul-test',
      title: 'Test końcowy',
      localPath: 'Modul-TEST.txt'
    }
  ],
  defaultModuleId: 'modul-01',
  preferredSource: 'local' // czytamy z lokalnego pliku .md
};
