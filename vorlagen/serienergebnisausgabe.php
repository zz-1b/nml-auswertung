<?php


  function cleanint($str)
  {
      if (filter_var($str, FILTER_VALIDATE_INT)) {
          return (int) $str;
      } else {
          return 0;
      }
  }

  # Anti-Hack
  function cleanstr($strs)
  {
      return filter_var($strs, FILTER_SANITIZE_STRING);
  }

  function cleanstiwo($strs)
  {
    $ielig = array();
    $ielig[0] = '/\xc4/i';
    $ielig[1] = '/\xd6/i';
    $ielig[2] = '/\xdc/i';
    $ielig[3] = '/\xdf/i';
    $ielig[4] = '/\xe4/i';
    $ielig[5] = '/\xf6/i';
    $ielig[6] = '/\xfc/i';

    $utf8lig = array();
    $utf8lig[0] = 'Ä';
    $utf8lig[1] = 'Ö';
    $utf8lig[2] = 'Ü';
    $utf8lig[3] = 'ß';
    $utf8lig[4] = 'ä';
    $utf8lig[5] = 'ö';
    $utf8lig[6] = 'ü';

    return filter_var(preg_replace($ielig, $utf8lig, $strs), FILTER_SANITIZE_STRING);
  }

  $serienid = cleanint($_GET['serienid']);
  $stichwort = cleanstiwo($_GET['stichwort']);
  $geschlecht = cleanstr($_GET['geschlecht']);
  $anzahl = cleanint($_GET['anzahl']);
  $format = 1;
  if( strlen($_GET['format'])>0 )
  {
    $format = cleanint($_GET['format']);
  }

#echo 'Par: '.$veranstaltungsid.':'.$lauf.':'.$stichwort.':'.$geschlecht.':<p>';
  try
  {  # Verbindungsfehler fangen
      $dbh = new PDO('mysql:host=localhost;dbname=ERSETZEDBNAME', 'ERSETZEDBUSER', 'ERSETZEDBPASSWD');
      $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      try
       { # SQL fehler fangen

          $sql = 'SELECT htmlhead from serienauswertungen WHERE serienid=:serienid AND format=:format;';
          $sth = $dbh->prepare($sql);
          $sth->execute(array('serienid' => $serienid, 'format' => $format));
          $htmlhead = $sth->fetch()['htmlhead'];

          $sql = 'SELECT titel, urkundenid from serien WHERE serienid=:serienid;';
          $sth = $dbh->prepare($sql);
          $sth->execute(array('serienid' => $serienid));
          $veranstaltg = $sth->fetch();

#          echo '<h2>'.$veranstaltg['titel']."</h2>\n";

          $sql = 'SELECT e.htmlrow, e.tnid FROM serienwebergebnisse e, serienrangliste r, serienteilnehmer t
              WHERE e.serienid=:serienid
              AND r.serienid=e.serienid
              AND t.serienid=e.serienid
              AND e.tnid=t.tnid AND e.tnid=r.tnid
              AND e.format=:format';
          if (!empty($geschlecht)) {
              $sql = $sql.' AND t.geschlecht LIKE :geschlecht';
          }
          if (!empty($stichwort)) {
              $sql = $sql.' AND htmlrow LIKE :stichwort';
          }
          $sql = $sql." ORDER BY r.gesamtplatz";
          if (!empty($anzahl)) {
              $sql = $sql.' LIMIT :anzahl';
          }
          $sth = $dbh->prepare($sql);
          $sth->bindValue(':serienid',(int)$serienid, PDO::PARAM_INT);
          $sth->bindValue(':format',(int)$format, PDO::PARAM_INT);
          if (!empty($geschlecht)) {
            $sth->bindValue(':geschlecht', $geschlecht, PDO::PARAM_STR);
          }
          if (!empty($stichwort)) {
            $sth->bindValue(':stichwort', '%'.$stichwort.'%', PDO::PARAM_STR);
          }
          if (!empty($anzahl)) {
            $sth->bindValue(':anzahl',(int)$anzahl, PDO::PARAM_INT);
          }
          $sth->execute();

          if( $format >= 1)
          {
            print '<!DOCTYPE HTML>
            <!-- HTML5 -->
            <html lang="de">
            <head>
                <meta charset="utf-8">
                <link rel="stylesheet" type="text/css" href="lnm-style-kurz.css">
                <title>Ergebnisse der Laufserie Nord-Münsterland</title>
            </head>
            <body>
            <small><table class=\'kurztabelle\'><tr class=\'ergebnistabellenkopf\'>'.$htmlhead;
          } else {
            print '<!DOCTYPE HTML>
            <!-- HTML5 -->
            <html lang="de">
            <head>
                <meta charset="utf-8">
                <link rel="stylesheet" type="text/css" href="lnm-style.css">
                <title>Ergebnisse der Laufserie Nord-Münsterland</title>
            </head>
            <body><table class=\'ergebnistabelle\'><tr class=\'ergebnistabellenkopf\'>'.$htmlhead;
          }
          if ($veranstaltg['urkundenid']) {
              echo '<td>Urkunde</td>';
          }
          echo '</tr>';
          $roweven = 0;
          $rowcount = 1;
          foreach ($sth->fetchAll() as $row) {
              echo "<tr class='row_".$roweven."'>"
              .$row['htmlrow'];
              if ($veranstaltg['urkundenid']) {
                  echo '<td><a href="urkunden/urkunde.php?tnid='.$row['tnid'].'">Urkunde</a></td>';
              }
              echo '</tr>';
              $roweven = 1 - $roweven;
              ++$rowcount;
          }
          echo '</table>';
          if( $format >= 1)
          {
            echo '</small>';
          }

      } catch (Exception $e) {
          echo 'Failed: '.$e->getMessage();
          die();
      }

      $dbh = null;
  } catch (PDOException $e) {
      echo 'Error!: '.$e->getMessage().'<br/>';
      die();
  }
print '</body></html>';
?>
