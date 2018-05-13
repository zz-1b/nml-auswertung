<?php

class SerienWertung
{
  private $dbh;
  function __construct( )
  {
    $this->dbh = new PDO('mysql:host=localhost;dbname=ERSETZEDBNAME', 'ERSETZEDBUSER', 'ERSETZEDBPASSWD');
    $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $this->dbh->exec('DELETE FROM serienteilnehmer');
    $teilnehmer = $this->dbh->exec(
     'INSERT INTO serienteilnehmer(vorname, nachname, jahrgang, geschlecht, verein, teilnahmen )
      SELECT DISTINCT e.vorname, e.nachname, e.jahrgang, e.geschlecht, e.verein, 0
      FROM ergebnisse e, ergebnisse e2
      WHERE e.nachname=e2.nachname
            AND e.vorname=e2.vorname
            AND e.jahrgang=e2.jahrgang
            AND e.geschlecht=e2.geschlecht');
//            AND (e.verein="" or e2.verein="" or e.verein=e2.verein )');

    echo $teilnehmer." Serienteilnehmer <p>";

    $jahr=ERSETZEJAHR;
    $this->dbh->exec(
      'UPDATE serienteilnehmer
       SET altersklasse=CONCAT(UPPER(geschlecht), CASE
       WHEN '.$jahr.'-jahrgang<8 THEN \'KU8\'
       WHEN '.$jahr.'-jahrgang<10 THEN \'KU10\'
       WHEN '.$jahr.'-jahrgang<12 THEN \'KU12\'
       WHEN '.$jahr.'-jahrgang<14 THEN \'JU14\'
       WHEN '.$jahr.'-jahrgang<16 THEN \'JU16\'
       WHEN '.$jahr.'-jahrgang<18 THEN \'JU18\'
       WHEN '.$jahr.'-jahrgang<20 THEN \'JU20\'
       WHEN '.$jahr.'-jahrgang<23 THEN \'U23\'
       WHEN '.$jahr.'-jahrgang>=85 THEN \'85\'
       WHEN '.$jahr.'-jahrgang>=80 THEN \'80\'
       WHEN '.$jahr.'-jahrgang>=75 THEN \'75\'
       WHEN '.$jahr.'-jahrgang>=70 THEN \'60\'
       WHEN '.$jahr.'-jahrgang>=65 THEN \'55\'
       WHEN '.$jahr.'-jahrgang>=60 THEN \'60\'
       WHEN '.$jahr.'-jahrgang>=55 THEN \'55\'
       WHEN '.$jahr.'-jahrgang>=50 THEN \'50\'
       WHEN '.$jahr.'-jahrgang>=45 THEN \'45\'
       WHEN '.$jahr.'-jahrgang>=40 THEN \'40\'
       WHEN '.$jahr.'-jahrgang>=35 THEN \'35\'
       WHEN '.$jahr.'-jahrgang>=30 THEN \'30\'
       ELSE \'\'
       END )');


    $this->dbh->exec('DELETE FROM letztedatensaetze');
    $ds = $this->dbh->exec(
    'INSERT INTO letztedatensaetze(veranstaltungsid, datensatzid)
     SELECT veranstaltungsid, MAX(datensatzid) as datensatzid
     FROM datensaetze
     GROUP BY veranstaltungsid');

     echo $ds." Datensätze <p>";

    try {
     $this->dbh->exec('DROP TABLE serieneinzelraenge');
    } catch (Exception $e) { }

    $this->dbh->exec(
    'CREATE TEMPORARY TABLE serieneinzelraenge AS
     SELECT t.tnid, d.datensatzid, e.zeit
     FROM serienteilnehmer t, letztedatensaetze d, ergebnisse e
     WHERE t.nachname=e.nachname
      AND t.vorname=e.vorname
      AND t.jahrgang=e.jahrgang
      AND d.datensatzid=e.datensatzid
      AND t.verein=e.verein  # TBD!!
     ORDER BY t.tnid, zeit ASC');


    $this->dbh->exec('DELETE FROM serieneinzelergebnisse');
    print "serieneinzelergebnisse".$this->dbh->exec(
    'INSERT INTO serieneinzelergebnisse(rang, tnid, datensatzid, zeit, inwertung)
     SELECT (case tnid
         WHEN @curId
         THEN @curRank:=@curRank+1
         ELSE @curRank:=1 AND @curId:=tnid END) AS rang,
          tnid, datensatzid, zeit, \'false\' as inwertung
      FROM serieneinzelraenge,
      (SELECT @curRank := 0, @curId := -1) r
      ORDER BY tnid, zeit ASC');

    $this->dbh->exec('UPDATE serieneinzelergebnisse SET inwertung=(rang<=4)');

    try {
      $this->dbh->exec('DROP TABLE teilnahmezaehlung');
    } catch (Exception $e) { }

    $this->dbh->exec('CREATE TEMPORARY TABLE teilnahmezaehlung
    AS SELECT tnid, count(*) AS teilnahmen FROM serieneinzelergebnisse GROUP BY tnid');

    $this->dbh->exec('UPDATE serienteilnehmer, teilnahmezaehlung
    SET serienteilnehmer.teilnahmen=teilnahmezaehlung.teilnahmen, serienteilnehmer.bonusteilnahmen=0
    WHERE serienteilnehmer.tnid=teilnahmezaehlung.tnid');

    $this->dbh->exec('UPDATE serienteilnehmer, teilnahmezaehlung
      SET serienteilnehmer.bonusteilnahmen=teilnahmezaehlung.teilnahmen-4
      WHERE serienteilnehmer.tnid=teilnahmezaehlung.tnid and teilnahmezaehlung.teilnahmen>4');

    # Berechnung der Serien-Zeit mit 45 Sekunden Bonus ab dem 5. Lauf - Achtung, SEC_TO_TIME und TIME_TO_SEC sind kein Standard-SQL
    $this->dbh->exec('DELETE FROM serienrangliste');
    $this->dbh->exec('INSERT INTO serienrangliste(tnid, serienzeit)
     SELECT t.tnid, SEC_TO_TIME(sum(TIME_TO_SEC(e.zeit)*e.inwertung)-t.teilnahmen*45) AS serienzeit
     FROM serienteilnehmer t, serieneinzelergebnisse e
     WHERE t.tnid=e.tnid GROUP BY t.tnid');
  }

  function HTMLAUswertung()
  {
    $awtabelle=array();
    $sth=$this->dbh->prepare('SELECT r.tnid, r.serienzeit, t.bonusteilnahmen, t.teilnahmen, t.vorname, t.nachname, t.altersklasse, t.geschlecht, t.jahrgang
                       FROM serienrangliste r, serienteilnehmer t where r.tnid=t.tnid
                       ORDER BY t.teilnahmen-t.bonusteilnahmen DESC, r.serienzeit ASC');
    $sth->execute();
    $daten=$sth->fetchAll(PDO::FETCH_ASSOC);
    foreach ($daten as $row)
    {
#      print "T ".$row['tnid']."   ".$row['serienzeit']."<br>\n";
      $awtabelle[$row['tnid']]=["serienzeit" => $row['serienzeit'],
                                "teilnahmen" => $row['teilnahmen'],
                                "bonusteilnahmen" => $row['bonusteilnahmen'],
                                "vorname" => $row['vorname'],
                                "nachname" => $row['nachname'],
                                "altersklasse" => $row['altersklasse'],
                                "geschlecht" => $row['geschlecht'],
                                "jahrgang" => $row['jahrgang']
                               ];
    }
    $sth = $this->dbh->prepare("SELECT v.name,v.titel,datensatzid FROM veranstaltungen v,letztedatensaetze d WHERE v.veranstaltungsid=d.veranstaltungsid");
    $sth->execute();
    $laufdaten=$sth->fetchAll(PDO::FETCH_ASSOC);

    foreach ($laufdaten as $row)
    {
      $name=$row['name'];
      $datensatzid=$row['datensatzid'];
#      print "<p>$datensatzid $name <br>\n";
      $sth = $this->dbh->prepare('SELECT t.tnid, e.zeit, e.inwertung as inwertung
                         FROM serienteilnehmer t, serieneinzelergebnisse e
                         WHERE t.tnid=e.tnid AND e.datensatzid='.$datensatzid);
      $sth->execute();
      $daten=$sth->fetchAll(PDO::FETCH_ASSOC);
      foreach ($daten as $row)
      {
        $awtabelle[$row["tnid"]][$name] = [ "zeit" => $row['zeit'], "inwertung" => $row['inwertung']];
      }
    }

    print "<table>\n";
    print "<tr>";
    print "<th>Teilnehmer</th>";
    foreach ($laufdaten as $row)
    {
        print "<th>".$row["titel"]."</th>";
    }
    print "<th>AK</th>";
    print "<th>Serienwertung</th>";
    print "</tr>\n";

    foreach($awtabelle as $teilnehmer)
    {
      print "<tr>";
      print "<td>".$teilnehmer["vorname"]." ".$teilnehmer["nachname"]."</td>";
      foreach ($laufdaten as $row)
      {
        if(isset($teilnehmer[$row['name']]['inwertung']))
        {
         if( $teilnehmer[$row['name']]['inwertung']>0)
         {
           print "<td><b>".$teilnehmer[$row['name']]["zeit"]."</b></td>";
         } else {
           print "<td>".$teilnehmer[$row['name']]["zeit"]."</td>";
         }
       } else {
         print "<td>--</td>";
       }
      }
      print "<td>".$teilnehmer["altersklasse"]."</td>";
      if( $teilnehmer['teilnahmen']>=4)
      {
        print "<td>".$teilnehmer["serienzeit"]."</td>";
      } else {
        print "<td><strike>".$teilnehmer["serienzeit"]."</strike>Zu wenig Teilnahmen</td>";
      }
      print "</tr>\n";
    }
    print "</table>\n";
  }
}
?>
