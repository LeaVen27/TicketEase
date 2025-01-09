<?php

define('PLUGIN_TICKETEASE_VERSION', '2.0.0');
define("PLUGIN_TICKETEASE_MIN_GLPI", "10.0.5");
define("PLUGIN_TICKETEASE_MAX_GLPI", "10.9.9");

function plugin_init_ticketease() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['ticketease'] = true;

   if (Plugin::isPluginActive('ticketease')) {

      if (Session::haveRight('config', UPDATE)) {
         $PLUGIN_HOOKS['config_page']['ticketease'] = 'front/config.form.php';
      }

      // On lit la fréquence cron (en minutes) depuis la BDD
      $cron_minutes = PluginTicketeaseConfig::getCronFrequency();

      // On l'affecte en secondes au hook cron
      $PLUGIN_HOOKS['cron']['ticketease'] = $cron_minutes * 60;

      // Optionnel : Post_init si tu veux exécuter update_ticket_priority sur chaque page
      // (mais c'est gourmand). On peut le laisser ou le retirer.
      $PLUGIN_HOOKS['post_init']['ticketease'] = 'plugin_ticketease_update_ticket_priority';
   }
}

function plugin_version_ticketease() {
   return [
      'name'        => 'TicketEase',
      'version'     => PLUGIN_TICKETEASE_VERSION,
      'author'      => 'Evan Navega',
      'homepage'    => 'https://github.com/evannvg/TicketEase',
      'license'     => 'GPL v2+',
      'requirements'=> [
         'glpi' => [
            'min' => PLUGIN_TICKETEASE_MIN_GLPI,
            'max' => PLUGIN_TICKETEASE_MAX_GLPI,
         ]
      ]
   ];
}

function plugin_ticketease_check_prerequisites() {
   if (version_compare(GLPI_VERSION, PLUGIN_TICKETEASE_MIN_GLPI, 'lt')
    || version_compare(GLPI_VERSION, PLUGIN_TICKETEASE_MAX_GLPI, 'gt')) {
      echo "Ce plugin nécessite GLPI version 10.0.5 à 10.9.9";
      return false;
   }
   return true;
}

function plugin_ticketease_check_config() {
   return true;
}
