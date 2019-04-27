<?php
  header( 'Content-type: text/html; charset=utf-8' );
  require_once('ergebnis.php');
  $benutzer = $_SERVER['PHP_AUTH_USER'];
  $auswahl = new DistanzAuswahl($benutzer);

  $distanzen = $auswahl->Strecken();

#    echo "<p>Sie gaben {$_SERVER['PHP_AUTH_PW']} als Passwort ein.</p>";
  echo '<form enctype="multipart/form-data" action="csvimport.php" method="POST">
     <!-- MAX_FILE_SIZE must precede the file input field -->
     <input type="hidden" name="MAX_FILE_SIZE" value="300000" />';
     if( count($distanzen)>1)
     {
#       foreach ($distanzen as $key => $value) {
#         print $value."<br>";
#       }
       echo '<input type="hidden" name="STRECKENID" value="'.array_keys($distanzen)[0].'" />';
     }
     else if( count($distanzen) == 1)
     {
        echo '<input type="hidden" name="STRECKENID" value="'.array_keys($distanzen)[0].'" />';
     }
#<!-- Name of input element determines name in $_FILES array -->
      echo '
      <h1>CSV-Datei mit Ergebnissen für den '.$benutzer.' hochladen </h1>
      <p><label for="tnbedingungen">
                    <input type="checkbox" required name="tnbedingungen"> Ich bestätige, dass alle in der Tabelle aufgeführten Personen die Teilnahmebedingungen
                    der ERSETZETITEL akzeptiert haben und die Daten mit den endgültigen Ergebnissen des jeweiligen Laufes übereinstimmen.</label></p>
      <p>      <input name="userfile" type="file" /> <input type="submit" value="Datei abschicken" /></p>
   </form>';

?>
