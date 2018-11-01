<?php
#  include 'kopf.html';

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
          $sql = 'SELECT titel, urkundenid from ERSETZEDBNAME.serien WHERE serienid=:serienid;';
          $sth = $dbh->prepare($sql);
          $sth->execute(array('serienid' => $serienid));
          $veranstaltg = $sth->fetch();

          echo '<h2>'.$veranstaltg['titel']."</h2>\n";

          $sql = 'SELECT htmlrow FROM ERSETZEDBNAME.serienwebergebnisse e
              WHERE serienid=:serienid ';
          $querypar = array('serienid' => $serienid);
          if (!empty($geschlecht)) {
              $sql = $sql.' AND geschlecht LIKE :geschlecht';
              $querypar['geschlecht'] = '%'.$geschlecht.'%';
          }
          if (!empty($lauf)) {
              $sql = $sql.' AND laufname LIKE :lauf';
              $querypar['lauf'] = '%'.$lauf.'%';
          }
          if (!empty($stichwort)) {
              $sql = $sql.'
                AND ( name LIKE :stichwort
                OR verein LIKE :stichwort
                OR startnummer LIKE :stichwort )';
              $querypar['stichwort'] = '%'.$stichwort.'%';
          }
          $sql = $sql." ORDER BY laufname,zeit";

          $sth = $dbh->prepare($sql);
          $sth->execute($querypar);

          echo '<table><tr class=\'tkopf\'>'
          .'<td>Start-<br>nummer</td>'
          .'<td>Lauf</td>'
          .'<td>Name</td>'
          .'<td>Verein</td>'
          .'<td>Zeit</td>'
        .'<td>Platz</td>'
        .'<td>AK-Platz</td>';
          if ($veranstaltg['urkunden']) {
              echo '<td>Urkunde</td>';
          }
          echo '</tr>';
          $roweven = 0;
          $rowcount = 1;
          foreach ($sth->fetchAll() as $row) {
              echo "<tr class='row_".$roweven."'>"
            .'<td>'.$row['startnummer'].'</td>'
              .'<td>'.$row['laufname'].'</td>'
              .'<td>'.$row['name'].'</td>'
              .'<td>'.$row['verein'].'</td>'
              .'<td>'.$row['zeit'].'</td>'
              .'<td>'.$row['platz'].'</td>'
              .'<td>'.$row['akplatz'].'</td>';
              if ($veranstaltg['urkunden']) {
                  echo '<td><a href="urkunden/urkunde.php?startnummer='.$row['startnummer']
            ."&veranstaltungsid=$veranstaltungsid&laufname=".$row['laufname'].'">Urkunde</a></td>';
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
include 'fuss.html';
?>
