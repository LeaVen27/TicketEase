<?php

function plugin_ticketease_install() {
   global $DB;

   // 1) Crée la table de config si pas déjà existante
   $DB->query("
       CREATE TABLE IF NOT EXISTS glpi_plugin_ticketease_configs (
         id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         time_before_priority_change INT(11) UNSIGNED NOT NULL,
         status INT(11) UNSIGNED NOT NULL,
         priority INT(11) UNSIGNED NOT NULL,
         PRIMARY KEY (id)
       ) ENGINE = InnoDB
         DEFAULT CHARSET = utf8mb4
         COLLATE = utf8mb4_unicode_ci;
   ");

   // 2) Ajoute la colonne plugin_ticketease_escalated dans glpi_tickets si elle n'existe pas
   $checkColumn = "SHOW COLUMNS FROM glpi_tickets LIKE 'plugin_ticketease_escalated'";
   $resCheck = $DB->query($checkColumn);
   if ($resCheck && $resCheck->num_rows === 0) {
      $DB->query("
         ALTER TABLE glpi_tickets
         ADD COLUMN plugin_ticketease_escalated TINYINT(1) NOT NULL DEFAULT 0
      ");
   }

   // 3) Crée la table pour stocker la fréquence du cron (1..5 min)
   $DB->query("
      CREATE TABLE IF NOT EXISTS glpi_plugin_ticketease_settings (
         id INT NOT NULL AUTO_INCREMENT,
         cron_frequency INT NOT NULL DEFAULT 5,
         PRIMARY KEY (id)
      ) ENGINE=InnoDB
        DEFAULT CHARSET = utf8mb4
        COLLATE = utf8mb4_unicode_ci;
   ");

   // Vérifie si la table est vide, sinon insère une ligne par défaut
   $sqlCheck = "SELECT id FROM glpi_plugin_ticketease_settings LIMIT 1";
   $resCheck = $DB->query($sqlCheck);
   if ($resCheck && $resCheck->num_rows === 0) {
      // insère la valeur par défaut (5 minutes)
      $DB->query("
         INSERT INTO glpi_plugin_ticketease_settings (cron_frequency)
         VALUES (5)
      ");
   }

   return true;
}

function plugin_ticketease_uninstall() {
   global $DB;

   // 1) Supprime la table config
   $DB->query("DROP TABLE IF EXISTS glpi_plugin_ticketease_configs");

   // 2) Retire la colonne plugin_ticketease_escalated
   $checkColumn = "SHOW COLUMNS FROM glpi_tickets LIKE 'plugin_ticketease_escalated'";
   $resCheck = $DB->query($checkColumn);
   if ($resCheck && $resCheck->num_rows > 0) {
      $DB->query("
         ALTER TABLE glpi_tickets
         DROP COLUMN plugin_ticketease_escalated
      ");
   }

   // 3) Supprime aussi la table glpi_plugin_ticketease_settings
   $DB->query("DROP TABLE IF EXISTS glpi_plugin_ticketease_settings");

   return true;
}

function plugin_ticketease_update_ticket_priority() {
   global $DB;

   $plugin = new Plugin();
   if ($plugin->isActivated('ticketease')) {
      $configs = PluginTicketeaseConfig::getConfigs();

      foreach ($configs as $config) {
         $time_before_priority_change = $config['time_before_priority_change'];
         $status  = $config['status'];
         $newprio = $config['priority'];

         // On cible les tickets ayant ce statut + non supprimés
         $query = "
            SELECT id, date, priority
            FROM glpi_tickets
            WHERE status = $status
              AND is_deleted = 0
         ";

         $result = $DB->query($query);

         while ($data = $result->fetch_assoc()) {
            $ticket_age = time() - strtotime($data['date']);
            // Escalade si
            // (1) l'âge du ticket dépasse time_before_priority_change
            // (2) la priorité actuelle est < la newprio (autorise multi-escalade)
            if ($ticket_age > $time_before_priority_change
             && $data['priority'] < $newprio) {

               $DB->update(
                  'glpi_tickets',
                  [
                     'priority'                   => $newprio,
                     'plugin_ticketease_escalated'=> 1
                  ],
                  ['id' => $data['id']]
               );
            }
         }
      }
   }
}

function plugin_ticketease_cron_task() {
   $cron_status = false;

   if (plugin_ticketease_update_ticket_priority()) {
      $cron_status = true;
   }

   return $cron_status;
}
