// KONFIG: działa z plikami lokalnymi (na Twoim serwerze)
window.KURS_CONFIG = {
  title: 'Kurs Koordynatora Reklamy',
  organization: 'Fundacja Werbekoordynator',
  modules: [
    {
      id: 'modul-01',
      title: 'Moduł 1: Podstawy roli Koordynatora Reklamy',
      // Ten plik już jest w tym samym folderze „kurs/”
      localPath: 'Modul-01_Podstawy-Koordynatora-Reklamy.md'
    }
  ],
  defaultModuleId: 'modul-01',
  preferredSource: 'local' // czytamy z lokalnego pliku .md
};