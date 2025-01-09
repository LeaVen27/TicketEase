<?php

include('../../../inc/includes.php');

Session::checkRight("config", UPDATE);

$config = new PluginTicketeaseConfig();

// Suppression d'une règle
if (isset($_GET['delete'])) {
   $config->delete(['id' => $_GET['delete']]);
   Html::back();

// Ajout classique
} elseif (isset($_POST['add'])) {
   $new_config = $config->prepareInputForAdd($_POST);
   if ($new_config) {
      $config->add($new_config);
   }
   Html::back();

// Ajout d'une nouvelle règle
} elseif (isset($_POST['add_rule'])) {
   if (!empty($_POST['status']) && !empty($_POST['priority']) && !empty($_POST['time_before_priority_change'])) {

      $time_before_priority_change = (int)$_POST['time_before_priority_change'];

      switch ($_POST['time_unit']) {
         case 'minutes':
            $time_before_priority_change *= 60;
            break;
         case 'hours':
            $time_before_priority_change *= 3600;
            break;
         case 'days':
            $time_before_priority_change *= 86400;
            break;
      }

      $new_rule = [
         'status'  => $_POST['status'],
         'priority'=> $_POST['priority'],
         'time_before_priority_change' => $time_before_priority_change
      ];

      $config->add($new_rule);
      Html::back();

   } else {
      Session::addMessageAfterRedirect(
         __('Veuillez remplir tous les champs.', 'ticketease'),
         false,
         ERROR
      );
      Html::back();
   }

// Mise à jour de la fréquence du cron
} elseif (isset($_POST['update_cron_freq'])) {
   if (isset($_POST['cron_frequency'])) {
      $freq = (int)$_POST['cron_frequency'];
      // borne 1..5
      if ($freq < 1) $freq = 1;
      if ($freq > 5) $freq = 5;

      // on enregistre
      PluginTicketeaseConfig::setCronFrequency($freq);
   }
   Html::back();

// Affichage principal + Graphiques
} else {
   Html::header(__('TicketEase', 'ticketease'), $_SERVER['PHP_SELF'], 'config', 'PluginTicketeaseConfig');

   // 1) Formulaire principal (règles)
   $config->showForm();

   // 2) Charger Chart.js
   echo "<script src='https://cdn.jsdelivr.net/npm/chart.js'></script>";

   // === Plugin pour afficher le pourcentage au centre des donuts ===
   echo "<script>
   // Définition d'un plugin pour dessiner du texte au centre
   const centerTextPlugin = {
     id: 'centerTextPlugin',
     afterDraw: (chart) => {
       const { ctx, chartArea: { top, bottom, left, right, width, height } } = chart;
       const data = chart.data.datasets[0].data;
       if (!data || data.length < 2) {
         return;
       }

       // Somme des 2 valeurs (ex: escaladés + non escaladés)
       const sum = data[0] + data[1];

       // Calcul du pourcentage pour data[0]
       const percent = sum === 0 ? '0%' : ((data[0] / sum) * 100).toFixed(0) + '%';

       // Style du texte
       ctx.save();
       ctx.font = 'bold 20px sans-serif';
       ctx.fillStyle = 'rgba(249, 47, 47, 0.6)';
       ctx.textAlign = 'center';
       ctx.textBaseline = 'middle';

       // Coordonnées du centre
       const centerX = left + width / 2;
       const centerY = top + height / 2;

       // Dessin du texte
       ctx.fillText(percent, centerX, centerY);
       ctx.restore();
     }
   };
   </script>";

   // --- Séparateur ---
   echo "<hr>";
   echo "<h3 style='text-align:center;'>"
        . __('Statistiques TicketEase', 'ticketease')
        . "</h3><br>";

   // === (A) Bar Chart : Nombre de tickets par priorité (ouverts)
   echo "<div style='width:50vw; height:50vh; margin:auto;'>";
   echo "<canvas id='myBarChart'></canvas>";
   echo "</div>";

   // Requête pour le bar chart
   $counts = [0, 0, 0, 0, 0];
   $qprio = "
      SELECT priority, COUNT(*) AS cnt
      FROM glpi_tickets
      WHERE status NOT IN (5,6)
        AND is_deleted=0
      GROUP BY priority
   ";
   $resprio = $DB->query($qprio);
   while ($rowp = $DB->fetchAssoc($resprio)) {
      $idx = ((int)$rowp['priority']) - 1;
      if ($idx >= 0 && $idx < 5) {
         $counts[$idx] = (int)$rowp['cnt'];
      }
   }

   echo "<script>
      const ctxBar = document.getElementById('myBarChart').getContext('2d');
      const myBarChart = new Chart(ctxBar, {
         type: 'bar',
         data: {
            labels: ['Très basse', 'Basse', 'Moyenne', 'Haute', 'Très haute'],
            datasets: [{
               label: 'Tickets par priorité (ouverts)',
               data: " . json_encode($counts) . ",
               backgroundColor: [
                  'rgba(116, 255, 92, 0.6)',
                  'rgba(175, 248, 176, 0.6)',
                  'rgba(0, 219, 15, 0.6)',
                  'rgba(251, 96, 96, 0.6)',
                  'rgba(249, 47, 47, 0.6)'
               ],
               borderColor: [
                  'rgba(116, 255, 92, 1)',
                  'rgba(175, 248, 176, 1)',
                  'rgba(0, 219, 15, 1)',
                  'rgba(251, 96, 96, 1)',
                  'rgba(249, 47, 47, 1)'
               ],
               borderWidth: 1
            }]
         },
         options: {
            scales: {
               y: {
                  beginAtZero: true
               }
            }
         }
      });
   </script>";

   // --- Deux camemberts (doughnuts) côte à côte ---
   echo "<br>";
   echo "<div style='display:flex; justify-content:center; align-items:center; gap:40px; margin-bottom:40px;'>";

   // (1) Doughnut : Taux de succès
   echo "<div style='width:300px; height:300px;'>";
   echo "<canvas id='myDoughnutSucc' width='300' height='300'></canvas>";
   echo "</div>";

   // (2) Doughnut : % tickets en priorité 5
   echo "<div style='width:300px; height:300px;'>";
   echo "<canvas id='myDoughnutMax' width='300' height='300'></canvas>";
   echo "</div>";

   echo "</div>"; // fin div flex

   // --- 1) Taux de succès (Escaladés vs Non escaladés) ---
   $sqlEsc = "
      SELECT COUNT(*) AS c
      FROM glpi_tickets
      WHERE is_deleted=0
        AND status NOT IN (5,6)
        AND plugin_ticketease_escalated=1
   ";
   $resEsc = $DB->query($sqlEsc);
   $rowEsc = $DB->fetchAssoc($resEsc);
   $countEscaladed = (int)$rowEsc['c'];

   $sqlOpened = "
      SELECT COUNT(*) AS c
      FROM glpi_tickets
      WHERE is_deleted=0
        AND status NOT IN (5,6)
   ";
   $resOpened = $DB->query($sqlOpened);
   $rowOpened = $DB->fetchAssoc($resOpened);
   $countOpened = (int)$rowOpened['c'];

   $countNotEsc = max(0, $countOpened - $countEscaladed);

   $labelsSucc = [ __('Escaladés', 'ticketease'), __('Non escaladés', 'ticketease') ];
   $dataSucc   = [ $countEscaladed, $countNotEsc ];
   $colorsSucc = [ 'rgba(249, 47, 47, 0.6)', 'rgba(54, 162, 235, 0.6)' ];

   // --- 2) % tickets en priorité 5
   $sqlMax = "
      SELECT COUNT(*) AS c
      FROM glpi_tickets
      WHERE is_deleted=0
        AND status NOT IN (5,6)
        AND priority=5
   ";
   $resMax = $DB->query($sqlMax);
   $rowMax = $DB->fetchAssoc($resMax);
   $countMaxPriority = (int)$rowMax['c'];

   $countOther = max(0, $countOpened - $countMaxPriority);

   $labelsMax = [ __('En priorité 5', 'ticketease'), __('Autres priorités', 'ticketease') ];
   $dataMax   = [ $countMaxPriority, $countOther ];
   $colorsMax = [ 'rgba(249, 47, 47, 0.6)', 'rgba(54, 162, 235, 0.6)' ];

   echo "<script>
      // Doughnut 1 : Taux de succès
      const ctxSucc = document.getElementById('myDoughnutSucc').getContext('2d');
      const myDoughnutSucc = new Chart(ctxSucc, {
         type: 'doughnut',
         data: {
            labels: " . json_encode($labelsSucc) . ",
            datasets: [{
              data: " . json_encode($dataSucc) . ",
              backgroundColor: " . json_encode($colorsSucc) . "
            }]
         },
         options: {
            responsive: true,
            cutout: '40%',
            plugins: {
               legend: {
                  position: 'bottom'
               },
               title: {
                  display: true,
                  text: '" . __('Taux de succès', 'ticketease') . "'
               }
            }
         },
         plugins: [centerTextPlugin]
      });

      // Doughnut 2 : Tickets prio 5
      const ctxMax = document.getElementById('myDoughnutMax').getContext('2d');
      const myDoughnutMax = new Chart(ctxMax, {
         type: 'doughnut',
         data: {
            labels: " . json_encode($labelsMax) . ",
            datasets: [{
              data: " . json_encode($dataMax) . ",
              backgroundColor: " . json_encode($colorsMax) . "
            }]
         },
         options: {
            responsive: true,
            cutout: '40%',
            plugins: {
               legend: {
                  position: 'bottom'
               },
               title: {
                  display: true,
                  text: '" . __('% Priorité Très Haute', 'ticketease') . "'
               }
            }
         },
         plugins: [centerTextPlugin]
      });
   </script>";

   Html::footer();
}
?>
