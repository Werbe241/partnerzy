<?php
/*
Plugin Name: KK – Mail SMTP (MU)
Description: Ustawia nadawcę na admin@werbekoordinator.pl. Jeśli dostępne są stałe KK_SMTP_* w wp-config.php, włączy SMTP (np. s60.cyber-folks.pl:465 SSL). Brak zależności zewnętrznych.
Author: Werbekoordinator
Version: 1.1.0
*/
if (!defined('ABSPATH')) exit;

const KK_MAIL_FROM = 'admin@werbekoordinator.pl';

add_filter('wp_mail_from', fn($f) => KK_MAIL_FROM);
add_filter('wp_mail_from_name', function($name){
  $site = get_bloginfo('name');
  return $site ?: 'Werbekoordinator';
});

// Oczyść potencjalne From/Return-Path z nagłówków
add_filter('wp_mail', function($atts){
  $headers = array();
  if (!empty($atts['headers'])) {
    $lines = is_array($atts['headers']) ? $atts['headers'] : preg_split("/\r?\n/", (string)$atts['headers']);
    foreach ($lines as $h) {
      if (!$h) continue;
      if (preg_match('/^\s*(From|Return-Path|Sender)\s*:/i', $h)) continue;
      $headers[] = $h;
    }
  }
  if (!array_filter($headers, fn($h)=> stripos($h,'Reply-To:')===0)) {
    $headers[] = 'Reply-To: '.KK_MAIL_FROM;
  }
  $atts['headers'] = $headers;
  return $atts;
}, 10, 1);

// Opcjonalne SMTP (gdy zdefiniowano stałe w wp-config.php)
add_action('phpmailer_init', function($phpmailer){
  $phpmailer->Sender = KK_MAIL_FROM; // envelope sender
  if (strtolower($phpmailer->From) !== strtolower(KK_MAIL_FROM)) {
    $phpmailer->setFrom(KK_MAIL_FROM, wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), false);
  }

  if (defined('KK_SMTP_HOST') && KK_SMTP_HOST) {
    $phpmailer->isSMTP();
    $phpmailer->Host       = KK_SMTP_HOST;            // np. 's60.cyber-folks.pl'
    $phpmailer->SMTPAuth   = defined('KK_SMTP_AUTH') ? (bool)KK_SMTP_AUTH : true;
    if (defined('KK_SMTP_USER')) $phpmailer->Username = KK_SMTP_USER; // 'admin@werbekoordinator.pl'
    if (defined('KK_SMTP_PASS')) $phpmailer->Password = KK_SMTP_PASS; // hasło
    $phpmailer->Port       = defined('KK_SMTP_PORT') ? (int)KK_SMTP_PORT : 465;
    $phpmailer->SMTPSecure = defined('KK_SMTP_SEC') ? KK_SMTP_SEC : 'ssl';
  }
});
