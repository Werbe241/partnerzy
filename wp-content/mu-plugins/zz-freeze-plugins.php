<?php
/**
 * Plugin Name: ZZ Freeze Plugins
 * Description: Utilitka pozwalająca tymczasowo wyłączyć wybrane pluginy bez zmiany nazwy katalogu - dla celów testowych i debugowania
 * Version: 1.0.0
 * Author: Werbekoordinator.pl
 */

defined('ABSPATH') || exit;

/**
 * Aby wyłączyć plugin, dodaj jego ścieżkę do tablicy $plugins_to_freeze
 * Przykład: 'kk-lite/kk-lite.php', 'koordynator-kurs/koordynator-kurs.php'
 */

class ZZ_Freeze_Plugins {
    private static $instance = null;
    
    // LISTA PLUGINÓW DO WYŁĄCZENIA (zmień według potrzeb)
    private $plugins_to_freeze = array(
        // 'kk-lite/kk-lite.php',
        // 'koordynator-kurs/koordynator-kurs.php',
        // 'akismet/akismet.php',
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Filtruj aktywne pluginy dla zwykłych instalacji
        add_filter('option_active_plugins', array($this, 'filter_active_plugins'));
        
        // Filtruj aktywne pluginy dla multisite
        add_filter('site_option_active_sitewide_plugins', array($this, 'filter_sitewide_plugins'));
    }

    public function filter_active_plugins($plugins) {
        if (empty($this->plugins_to_freeze)) {
            return $plugins;
        }

        // Usuń zamrożone pluginy z listy aktywnych
        return array_diff($plugins, $this->plugins_to_freeze);
    }

    public function filter_sitewide_plugins($plugins) {
        if (empty($this->plugins_to_freeze) || !is_array($plugins)) {
            return $plugins;
        }

        // Dla multisite pluginy są w formacie array('plugin/file.php' => timestamp)
        foreach ($this->plugins_to_freeze as $plugin) {
            if (isset($plugins[$plugin])) {
                unset($plugins[$plugin]);
            }
        }

        return $plugins;
    }

    /**
     * Dodaj plugin do listy zamrożonych
     * 
     * @param string $plugin_path Ścieżka do pluginu (np. 'plugin-name/plugin-file.php')
     */
    public function freeze_plugin($plugin_path) {
        if (!in_array($plugin_path, $this->plugins_to_freeze)) {
            $this->plugins_to_freeze[] = $plugin_path;
        }
    }

    /**
     * Usuń plugin z listy zamrożonych
     * 
     * @param string $plugin_path Ścieżka do pluginu
     */
    public function unfreeze_plugin($plugin_path) {
        $key = array_search($plugin_path, $this->plugins_to_freeze);
        if ($key !== false) {
            unset($this->plugins_to_freeze[$key]);
            $this->plugins_to_freeze = array_values($this->plugins_to_freeze);
        }
    }

    /**
     * Pobierz listę zamrożonych pluginów
     * 
     * @return array
     */
    public function get_frozen_plugins() {
        return $this->plugins_to_freeze;
    }
}

// Inicjalizuj
ZZ_Freeze_Plugins::get_instance();

/**
 * INSTRUKCJA UŻYCIA:
 * 
 * 1. Aby wyłączyć plugin, odkomentuj linię w tablicy $plugins_to_freeze i dodaj ścieżkę pluginu
 * 2. Zapisz plik
 * 3. Plugin zostanie "zamrożony" i nie będzie ładowany przez WordPress
 * 4. Aby ponownie włączyć, zakomentuj lub usuń linię z tablicy
 * 
 * Przykłady:
 * - 'kk-lite/kk-lite.php' - wyłącza plugin KK Lite
 * - 'koordynator-kurs/koordynator-kurs.php' - wyłącza plugin Koordynator Kurs
 * 
 * UWAGA: To narzędzie działa tylko dla pluginów w wp-content/plugins/
 * MU-plugins są zawsze ładowane i nie mogą być wyłączone w ten sposób.
 */
