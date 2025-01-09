<?php

class PluginTicketeaseConfig extends CommonDBTM {

   static function getTypeName($nb = 0) {
      return __('Configuration', 'ticketease');
   }

   public static function getAllTicketPriorities() {
      return [
         1 => __('Très basse', 'ticketease'),
         2 => __('Basse', 'ticketease'),
         3 => __('Moyenne', 'ticketease'),
         4 => __('Haute', 'ticketease'),
         5 => __('Très haute', 'ticketease'),
      ];
   }

   public static function getConfigs() {
      $obj = new self();
      $results = $obj->find([]);
      return is_countable($results) ? $results : [];
   }

   /**
    * Lit la valeur du cron (en minutes) depuis la table glpi_plugin_ticketease_settings
    */
   public static function getCronFrequency() {
      global $DB;

      $sql = "SELECT cron_frequency
              FROM glpi_plugin_ticketease_settings
              LIMIT 1";
      $res = $DB->query($sql);
      if ($row = $DB->fetchAssoc($res)) {
         return (int)$row['cron_frequency'];
      }
      return 5; // Valeur par défaut si rien n'est trouvé
   }

   /**
    * Met à jour / insère la fréquence du cron (1..5) dans la table glpi_plugin_ticketease_settings
    */
   public static function setCronFrequency($freq) {
      global $DB;

      $sqlCheck = "SELECT id FROM glpi_plugin_ticketease_settings LIMIT 1";
      $resCheck = $DB->query($sqlCheck);
      if ($resCheck && $DB->numrows($resCheck) > 0) {
         // On update la ligne existante
         $row = $DB->fetchAssoc($resCheck);
         $DB->update('glpi_plugin_ticketease_settings',
                     ['cron_frequency' => $freq],
                     ['id' => $row['id']]);
      } else {
         // On insère une nouvelle ligne
         $DB->insert('glpi_plugin_ticketease_settings',
                     ['cron_frequency' => $freq]);
      }
   }

   function showForm($ID = 0, $options = []) {

      echo "<style>
         .tab_cadre_fixe,
         .tab_bg_1 {
            background-color: #ffffff !important;
         }
         .tab_cadre_fixe {
            margin-bottom: 0 !important;
            padding: 0 !important;
            border-collapse: collapse;
         }
         .table-rules {
            border-radius: 8px; /* tous les coins arrondis */
            overflow: hidden;
         }
         .ticketease-hr {
            margin: 0;
            border: 0;
            border-top: 1px solid #ccc;
         }

         /* --- Ajouts pour le modal --- */
         #cronBackdrop {
            display: none;
            position: fixed;
            top: 0; 
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.3);
            z-index: 9998;
         }
         #cronModal {
            display: none;
            position: fixed;
            top: 50%; 
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 20px;
            width: 280px;  /* Ajuste selon tes besoins */
            box-shadow: 0 2px 6px rgba(0,0,0,0.3); /* ombre */
         }

         #cronModal h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
         }

         .cronModal-desc {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.3;
         }

         .cronModal-body {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
         }
         .cronModal-body select {
            flex: 1;  /* le select prend la place dispo */
         }

         .cronModal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
         }

         /* Bouton annuler stylé */
         .btn-cancel {
            min-width: 80px;
            padding: 5px 12px;
            border: 1px solid #ccc;
            background: #f1f1f1;
            border-radius: 4px;
            cursor: pointer;
         }
         .btn-cancel:hover {
            background: #e2e2e2;
         }
      </style>";


      // Formulaire global, avec un id='form' pour rattacher le select
      echo "<form id='form' name='form' method='post' action='" . $this->getFormURL() . "'>";

      // Un seul tableau
      echo "<table class='tab_cadre_fixe table-rules' style='width:100%;'>";

         // === Ligne d'entête (titre global) avec engrenage Font Awesome
         echo "<tr>";
            echo "<th colspan='5' style='position: relative;'>";

               // Icône engrenage en haut/droite, clic => openCronModal()
               echo "<i class='fa fa-cog' style='
                  position:absolute;
                  right:10px;
                  top:50%;
                  transform:translateY(-50%);
                  font-size:20px;
                  cursor:pointer;'
                  aria-hidden='true'
                  onclick='openCronModal()'></i>";

               // Titre centré
               echo "<span style='display:block; text-align:center;'>";
               echo __('Configuration du plugin TicketEase', 'ticketease');
               echo "</span>";

            echo "</th>";
         echo "</tr>";

         // En-têtes
         echo "<tr>";
            echo "<th style='text-align:center;'>" . __('Règle', 'ticketease') . "</th>";
            echo "<th style='text-align:center;'>" . __('Statut du ticket', 'ticketease') . "</th>";
            echo "<th style='text-align:center;'>" . __('Priorité du ticket', 'ticketease') . "</th>";
            echo "<th style='text-align:center;'>" . __('Temps avant changement de priorité', 'ticketease') . "</th>";
            echo "<th style='text-align:center;'>" . __('Actions', 'ticketease') . "</th>";
         echo "</tr>";

         // Règles existantes
         $config_data = $this->getConfigs();
         $rule_number = 1;

         foreach ($config_data as $id => $data) {
            echo "<tr class='tab_bg_1'>";
               echo "<td class='center'>"
                    . __('Règle', 'ticketease')
                    . " " . $rule_number
                    . "</td>";

               // Statut
               echo "<td class='center'>";
               echo Ticket::getStatus($data['status']);
               echo "</td>";

               // Priorité
               echo "<td class='center'>";
               echo Ticket::getPriorityName($data['priority']);
               echo "</td>";

               // Temps
               $time = (int)$data['time_before_priority_change'];
               $value = $time;
               $unit  = __('secondes', 'ticketease');

               if ($time > 0 && $time % 86400 === 0) {
                  $value = $time / 86400;
                  $unit  = __('jours', 'ticketease');
               } else if ($time > 0 && $time % 3600 === 0) {
                  $value = $time / 3600;
                  $unit  = __('heures', 'ticketease');
               } else if ($time > 0 && $time % 60 === 0) {
                  $value = $time / 60;
                  $unit  = __('minutes', 'ticketease');
               }

               echo "<td class='center'>{$value} {$unit}</td>";

               // Actions
               echo "<td class='center'>";
               echo "<a href='" . $this->getFormURL() . "?delete={$id}'>"
                    . __('Supprimer', 'ticketease')
                    . "</a>";
               echo "</td>";

            echo "</tr>";
            $rule_number++;
         }

         // Ligne pour ajouter une nouvelle règle (5 colonnes)
         echo "<tr>";
            echo "<td class='center' style='padding:10px;'>";
            echo "<strong>" . __('Nouvelle règle', 'ticketease') . "</strong>";
            echo "</td>";

            // Statut
            echo "<td class='center' style='padding:10px;'>";
            Ticket::dropdownStatus([
                'name'  => 'status',
                'value' => '',
                'display_emptychoice' => true
            ]);
            echo "</td>";

            // Priorité
            echo "<td class='center' style='padding:10px;'>";
            Ticket::dropdownPriority([
               'name'  => 'priority',
               'value' => '',
               'display_emptychoice' => true
            ]);
            echo "</td>";

            // Temps + unité
            echo "<td class='center' style='padding:10px;'>";
            echo "<input type='text' name='time_before_priority_change' value='' size='10'> ";
            echo "<select name='time_unit'>";
               echo "<option value='seconds'>" . __('secondes', 'ticketease') . "</option>";
               echo "<option value='minutes'>" . __('minutes', 'ticketease') . "</option>";
               echo "<option value='hours'>" . __('heures', 'ticketease') . "</option>";
               echo "<option value='days'>" . __('jours', 'ticketease') . "</option>";
            echo "</select>";
            echo "</td>";

            // Bouton ajouter
            echo "<td class='center' style='padding:10px;'>";
            echo "<input type='submit' name='add_rule' value='"
                 . __('Ajouter', 'ticketease')
                 . "' class='submit'>";
            echo "</td>";
         echo "</tr>";

      echo "</table>";

      // Fermeture du formulaire principal
      Html::closeForm();

      // Backdrop (derrière le popup)
      echo "<div id='cronBackdrop'></div>";

      // Div modal (popup)
      echo "<div id='cronModal'>";
         echo "<h3>" . __('Fréquence du cron (minutes)', 'ticketease') . "</h3>";

         // Petit texte explicatif
         echo "<p class='cronModal-desc'>"
              . __('Définit l\'intervalle en minutes entre deux exécutions automatiques du plugin TicketEase.', 'ticketease')
              . "</p>";

         echo "<div class='cronModal-body'>";
            $current_cron = self::getCronFrequency();
            echo "<select name='cron_frequency' form='form'>";
            for ($i = 1; $i <= 5; $i++) {
               $selected = ($i == $current_cron) ? 'selected' : '';
               echo "<option value='$i' $selected>$i</option>";
            }
            echo "</select>";
         echo "</div>";

         // Boutons (Enregistrer / Annuler)
         echo "<div class='cronModal-buttons'>";
            echo "<input type='submit' name='update_cron_freq' value='" 
                 . __('Enregistrer', 'ticketease')
                 . "' class='submit' form='form'>";

            // Bouton Annuler stylé
            echo "<button type='button' onclick='closeCronModal()' class='btn-cancel'>"
                 . __('Annuler', 'ticketease')
                 . "</button>";
         echo "</div>";

      echo "</div>";


      // JS : fonctions open/close
      echo "<script>
      function openCronModal() {
         // afficher la modale
         document.getElementById('cronModal').style.display = 'block';
         // afficher le backdrop
         var bd = document.getElementById('cronBackdrop');
         if (bd) {
            bd.style.display = 'block';
         }
      }
      function closeCronModal() {
         document.getElementById('cronModal').style.display = 'none';
         var bd = document.getElementById('cronBackdrop');
         if (bd) {
            bd.style.display = 'none';
         }
      }
      </script>";
   }
}
