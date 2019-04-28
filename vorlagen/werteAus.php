<?php
 require_once('serienwertung.php');
 $auswahl = new SerienWertung();
 try { # SQL fehler fangen
   $auswahl->HTMLAUswertung();
     } catch (Exception $e) {
   echo "Failed: " . $e->getMessage();
   die();
   }

 ?>
