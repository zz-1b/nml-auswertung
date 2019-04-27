<?php
    require('adm/urkunde.php');

    function cleanint($str)
    {
        if( filter_var($str, FILTER_VALIDATE_INT) )
        {
            return (int)$str;
        }
        else
            return 0;
    }

    $tnid = cleanint($_GET['tnid']);
    $serienid = cleanint($_GET['serienid']);

    try {  # Verbindungsfehler fangen
        $dbh = new PDO('mysql:host=localhost;dbname=ERSETZEDBNAME', 'ERSETZEDBUSER', 'ERSETZEDBPASSWD');
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try { # SQL fehler fangen

            $sql = "SELECT vorname, nachname, verein, altersklasse FROM ERSETZEDBNAME.serienteilnehmer
                    WHERE tnid=$tnid
                    AND serienid=$serienid";

            $tnstmt = $dbh->prepare($sql);
            $tnstmt->execute();
            $tnres = $tnstmt->fetch();
 
            $sql = "SELECT serienzeit, bonuszeit, mwplatz, altersklassenplatz
                    FROM ERSETZEDBNAME.serienrangliste
                    WHERE tnid=$tnid
                    AND serienid=$serienid";

            $elstmt = $dbh->prepare($sql);
            $elstmt->execute();
            $elres = $elstmt->fetch();

            $sql = "SELECT v.titel as titel, s.zeit as zeit
                    FROM ERSETZEDBNAME.serieneinzelergebnisse s, ERSETZEDBNAME.veranstaltungen v, ERSETZEDBNAME.datensaetze d
                    WHERE s.tnid=$tnid AND s.serienid = $serienid
                    AND s.datensatzid = d.datensatzid
                    AND v.serienid = $serienid AND v.veranstaltungsid = d.veranstaltungsid
                    AND s.inwertung = TRUE
                    ORDER BY v.zeit";
                    
            $sthe = $dbh->prepare($sql);
            $sthe->execute();
            $ergebnisse=$sthe->fetchAll(PDO::FETCH_ASSOC);
            foreach ($ergebnisse as $row)
            {
                $titelnb = preg_replace("/<br>/"," ",preg_replace("/-<br>/","-",$row["titel"]));
                $laufergebnisse[$titelnb] = $row['zeit'];
            }
 
            erzeugeUrkunde($tnres['vorname']." ".$tnres['nachname'],
                $tnres['verein'], $elres['serienzeit'], $elres['mwplatz'],
                $tnres['altersklasse'], $elres['altersklassenplatz'],
                substr($elres['bonuszeit'],3), $laufergebnisse, "");

            } catch (Exception $e) {
            echo "Failed: " . $e->getMessage();
            die();
        }

        $dbh = null;
    } catch (PDOException $e) {
        print "Error!: " . $e->getMessage() . "<br/>";
        die();
    }
?>
