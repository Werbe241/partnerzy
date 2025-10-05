<?php
/*
Plugin Name: KK – Test View
Description: Minimalny MU-test. Na /kk/testview zwraca prosty tekst, by potwierdzić działanie MU-pluginów i bardzo wczesne przechwycenie żądania.
Author: Copilot
Version: 1.0.0
*/
if (!defined('ABSPATH')) exit;
add_action('init', function(){
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  if (preg_match('#/kk/testview#', $uri)) {
    if (!headers_sent()) {
      nocache_headers();
      status_header(200);
      header('Content-Type: text/plain; charset=UTF-8');
    }
    echo 'KK Test View OK';
    exit;
  }
}, 0);