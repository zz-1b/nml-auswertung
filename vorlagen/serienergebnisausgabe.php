<?php

print '<!DOCTYPE HTML>
<!-- HTML5 -->
<html lang="de">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" type="text/css" href="lnm-style.css">
    <title>Ergebnisse der Laufserie Nord-Münsterland</title>
</head>
<body>
<table>';

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

  $serienid = cleanint($_GET['serienid']);
  $stichwort = cleanstr($_GET['stichwort']);
  $geschlecht = cleanstr($_GET['geschlecht']);

#echo 'Par: '.$veranstaltungsid.':'.$lauf.':'.$stichwort.':'.$geschlecht.':<p>';
  try
  {  # Verbindungsfehler fangen
      $dbh = new PDO('mysql:host=localhost;dbname=ERSETZEDBNAME', 'ERSETZEDBUSER', 'ERSETZEDBPASSWD');
      $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      try
       { # SQL fehler fangen

          $sql = 'SELECT htmlhead from serienauswertungen WHERE serienid=:serienid;';
          $sth = $dbh->prepare($sql);
          $sth->execute(array('serienid' => $serienid));
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
              AND e.tnid=t.tnid AND e.tnid=r.tnid';
          $querypar = array('serienid' => $serienid);
          if (!empty($geschlecht)) {
              $sql = $sql.' AND t.geschlecht LIKE :geschlecht';
              $querypar['geschlecht'] = '%'.$geschlecht.'%';
          }
          if (!empty($stichwort)) {
              $sql = $sql.'
                AND htmlrow LIKE :stichwort';
              $querypar['stichwort'] = '%'.$stichwort.'%';
          }
          $sql = $sql." ORDER BY r.gesamtplatz";

          $sth = $dbh->prepare($sql);
          $sth->execute($querypar);

          echo '<table class=\'ergebnistabelle\'><tr class=\'ergebnistabellenkopf\'>'.$htmlhead;
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
