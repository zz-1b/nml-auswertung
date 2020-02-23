<?php
header( 'Content-type: text/html; charset=utf-8' );
require_once('ergebnis.php');
require_once('serienwertung.php');

$benutzer = $_SERVER['PHP_AUTH_USER'];
$uploaddir = 'ERSETZEUPLOADFOLDER';
$uploadfile = $uploaddir.$benutzer."-".uniqid()."-".basename($_FILES['userfile']['name']);
$streckenid = intval($_POST['STRECKENID']);

if(!isset($_POST['tnbedingungen']) || $_POST['tnbedingungen'] != 'on')
{
    echo '<h1>Abgebrochen!</h1><b>Ohne die Zusicherung der Zustimmung zu den Teilnahmebedingungen und der Richtigkeit der Daten kann die Tabelle
  nicht entgegengenommen werden.</b><p>Bitte das Ankreuzfeld auf der vorhergehenden Seite beachten.</p>';
}
else
{
    echo '<pre>';
    if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile))
    {
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
			$trenner=";";
			if( strpos($zeile, $trenner) == FALSE )
			{
			    $trenner=",";
			    if( strpos($zeile, $trenner) == FALSE )
				throw new \Exception('Die Felder müssen mit ; oder , getrennt sein!');
			}
			$kopf = str_getcsv(mb_convert_encoding($zeile, "UTF-8", $csv_encoding), $trenner);
			$kopfspalten=count($kopf);
			$ergebnis = new ErgebnisTabelle($benutzer, $uploadfile, $streckenid);
			$zeilenzahl=1;
			$ergebnis->erkenneSpalten($kopf);
			$parserfehler=NULL;
			while (($zeile = fgets($handle, 1000)) !== false)
			{
			    try
			    {
				$daten = str_getcsv(mb_convert_encoding($zeile, "UTF-8", $csv_encoding),$trenner);
				$num = count($daten);
				if( $num != $kopfspalten )
				    throw new \Exception('Falsche Spaltenzahl '.$num.' statt '.$kopfspalten.' in Zeile '.$zeile);
				$ergebnis->parseDaten($daten);
				$zeilenzahl++;
			    }
		            catch (Exception $e)
			    {
				echo "Fehler beim Import in der Zeile: ".$zeile." ". $e->getMessage()."\n";
				$parserfehler=$e;
			    }

			}
			if(isset($parserfehler))
			{
			   throw $parserfehler;
			}
			$ergebnis->schreibeErgebnisse();
			echo "Die Ergebnisse ( ".$zeilenzahl." Zeilen ) sind erfolgreich gespeichert.<p>\n";
			flush();
			ob_flush();
			$wertung = new SerienWertung();
			$wertung->HTMLAUswertung();

			#echo ' <a href="./werteAus.php">Auswertung berechnen</a><p>';
			echo ' <a href="./uebersicht.php">Zur Übersicht</a><p>';
		    }
		    else
			throw new \Exception("Datei leer ?", 1);
		}
		catch (Exception $e)
		{
		    echo "\n<b>Der Ergebnisimport hat nicht funktioniert!</b>\n";
		    echo "Fehler beim Import in der Zeile: ".$zeile." ". $e->getMessage()."\n";
		    echo "Bitte die Daten bzw das Dateiformat prüfen, korrigieren und neu hochladen.\n";
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
    // code...
}

?>
