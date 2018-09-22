<?php
require_once('ergebnis.php');

$benutzer = $_SERVER['PHP_AUTH_USER'];
$uploaddir = 'ERSETZEUPLOADFOLDER';
$uploadfile = $uploaddir.$benutzer."-".uniqid()."-".basename($_FILES['userfile']['name']);
$streckenid = intval($_POST['STRECKENID']);

echo '<pre>';
if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile))
{
  echo 'Streckenid:'.$streckenid.'<p>';

  echo "Datei erfolgreich empfangen.\n";

  $ergebnis_csv_text=file_get_contents($uploadfile, FALSE, NULL, 0, 1000000);
  $csv_encoding = mb_detect_encoding($ergebnis_csv_text, "UTF-8, ISO-8859-1, ISO-8859-2");
  print "Zeichenkodierung der Datei: ".$csv_encoding."<br>\n";

  $zeile = 0;
  if (($handle = fopen($uploadfile, "r")) !== FALSE)
  {
    try
    {  # Verbindungsfehler fangen
      try
      { # SQL fehler fangen
        if( ($zeile = fgets($handle, 1000)) !== false)
        {
          $kopf = str_getcsv(mb_convert_encoding($zeile, "UTF-8", $csv_encoding), ";");
          $kopfspalten=count($kopf);
          $ergebnis = new ErgebnisTabelle($benutzer, $uploadfile, $streckenid);
          $zeile=1;
          $ergebnis->erkenneSpalten($kopf);
          while (($zeile = fgets($handle, 1000)) !== false)
          {
            $daten = str_getcsv(mb_convert_encoding($zeile, "UTF-8", $csv_encoding), ";");
            $num = count($daten);
            if( $num != $kopfspalten )
              throw new Exception('Falsche Spaltenzahl '.$num.' statt '.$kopfspalten.' in Zeile '.$zeile);
          $ergebnis->parseDaten($daten);
          $zeile++;
          }
          $ergebnis->schreibeErgebnisse();
          echo "Die Ergebnisse sind erfolgreich gespeichert.<p>\n";
          echo ' <a href="./werteAus.php">Auswertung berechnen</a><p>';
          echo ' <a href="./uebersicht.php">Zur Übersicht</a><p>';
        }
        else
          throw new \Exception("Datei leer ?", 1);
      }
      catch (Exception $e)
      {
        echo "Fehler beim Import in Zeile ".$zeile.": ". $e->getMessage();
        die();
        $dbh = null;
      }
    }
    catch (PDOException $e)
    {
      print "Error!: " . $e->getMessage() . "<br/>";
      die();
    }
    fclose($handle);
  }
  else
  {
    echo "Das Öffnen der Datei ist schiefgegangen.\n";
  }
#  unlink($uploadfile);
}
else
{
  echo "Das Hochladen der Datei ist schiefgegangen.\n";
}
#echo 'Here is some more debugging info:';
#print_r($_FILES);

print "</pre>";

?>
