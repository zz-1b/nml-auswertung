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

  $zeile = 0;
  if (($handle = fopen($uploadfile, "r")) !== FALSE)
  {
    try
    {  # Verbindungsfehler fangen
      try
      { # SQL fehler fangen
        if(($kopf = fgetcsv($handle, 1000, ",")) !== FALSE)
        {
          $kopfspalten=count($kopf);
          $ergebnis = new ErgebnisTabelle($benutzer, $uploadfile, $streckenid);
          $zeile=1;
          $ergebnis->erkenneSpalten($kopf);
          while (($daten = fgetcsv($handle, 1000, ",")) !== FALSE)
          {
            foreach( $daten as $c)
            if(!mb_check_encoding($c, 'UTF-8'))
              throw new Exception('Falsche Zeichenkodierung in Zeile '.$zeile.' - bitte UTF-8 verwenden.');
            $num = count($daten);
            if( $num != $kopfspalten )
              throw new Exception('Falsche Spaltenzahl '.$num.' statt '.$kopfspalten.' in Zeile '.$zeile);
          $ergebnis->parseDaten($daten);
          $zeile++;
          }
          $ergebnis->schreibeErgebnisse();
          echo "Die Ergebnisse sind erfolgreich gespeichert.<p>\n";
          echo ' <a href="ergebnislisten.php">Alle Ergebnisdatensätze</a><p>';
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
