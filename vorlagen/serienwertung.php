<!DOCTYPE HTML>
<!-- HTML5 -->
<html lang="de">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" type="text/css" href="lnm-style.css">
    <title>Ergebnisse der Laufserie Nord-Münsterland</title>
</head>
<body>
<?php
require_once('urkunde.php');

class SerienWertung
{
  private $dbh;
  private $serienid;
  function __construct( )
  {
    $this->dbh = new PDO('mysql:host=localhost;dbname=ERSETZEDBNAME', 'ERSETZEDBUSER', 'ERSETZEDBPASSWD');
    $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $res_row = $this->dbh->query("SELECT max(serienid) from serien;")->fetch();
    $this->serienid = $res_row['max(serienid)'];

    $this->dbh->exec('DELETE FROM letztedatensaetze');
    $ds = $this->dbh->exec(
    'INSERT INTO letztedatensaetze(veranstaltungsid, datensatzid)
     SELECT veranstaltungsid, MAX(datensatzid) as datensatzid
     FROM datensaetze
     GROUP BY veranstaltungsid');

    echo $ds." aktuelle Datensätze <p>\n";
    flush();
    ob_flush();

    echo "Ermittelung der Serienteilnehmer...<br>";
    flush();

    $this->dbh->exec('DELETE FROM serienteilnehmer WHERE serienid='.$this->serienid);

    $teilnehmer = $this->dbh->exec(
     'INSERT INTO serienteilnehmer(serienid, vorname, nachname, jahrgang, geschlecht, ordnungsnr, teilnahmen )
      SELECT DISTINCT '.$this->serienid.',e.vorname, e.nachname, e.jahrgang, e.geschlecht, e.ordnungsnr, 0
      FROM ergebnisse e, ergebnisse e2
      WHERE e.nachname=e2.nachname
            AND e.vorname=e2.vorname
            AND e.jahrgang=e2.jahrgang
            AND e.ordnungsnr=e2.ordnungsnr
            AND e.geschlecht=e2.geschlecht
            AND e.datensatzid in ( SELECT datensatzid FROM letztedatensaetze )
            AND e2.datensatzid in ( SELECT datensatzid FROM letztedatensaetze )');


    echo $teilnehmer." Serienteilnehmer <p>";
    flush();

    $this->dbh->exec('UPDATE serienteilnehmer t, ergebnisse e
                      SET t.verein=e.verein
                      WHERE t.vorname=e.vorname
                      AND t.nachname=e.nachname
                      AND e.ordnungsnr=t.ordnungsnr
                      AND t.nachname=e.nachname;');

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
       WHEN '.$jahr.'-jahrgang>=70 THEN \'70\'
       WHEN '.$jahr.'-jahrgang>=65 THEN \'65\'
       WHEN '.$jahr.'-jahrgang>=60 THEN \'60\'
       WHEN '.$jahr.'-jahrgang>=55 THEN \'55\'
       WHEN '.$jahr.'-jahrgang>=50 THEN \'50\'
       WHEN '.$jahr.'-jahrgang>=45 THEN \'45\'
       WHEN '.$jahr.'-jahrgang>=40 THEN \'40\'
       WHEN '.$jahr.'-jahrgang>=35 THEN \'35\'
       WHEN '.$jahr.'-jahrgang>=30 THEN \'30\'
       ELSE \'\'
       END )');


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
     ORDER BY t.tnid, zeit ASC');

     echo "Serienwertung:<br>\n";
     flush();
     ob_flush();

    $this->dbh->exec('DELETE FROM serieneinzelergebnisse');
    $this->dbh->exec(
    'INSERT INTO serieneinzelergebnisse(serienid,rang, tnid, datensatzid, zeit, inwertung)
     SELECT '.$this->serienid.',(case tnid
         WHEN @curId
         THEN @curRank:=@curRank+1
         ELSE @curRank:=1 AND @curId:=tnid END) AS rang,
          tnid, datensatzid, zeit, \'false\' as inwertung
      FROM serieneinzelraenge,
      (SELECT @curRank := 0, @curId := -1) r
      ORDER BY tnid, zeit ASC');

    $this->dbh->exec('UPDATE serieneinzelergebnisse SET inwertung=(rang<=4)');

    echo "Zusammenfassen der Zeiten...<br>\n";
    flush();
    ob_flush();

    try {
      $this->dbh->exec('DROP TABLE teilnahmezaehlung');
    } catch (Exception $e) { }

    $this->dbh->exec('CREATE TEMPORARY TABLE teilnahmezaehlung
    AS SELECT tnid, count(*) AS teilnahmen FROM serieneinzelergebnisse GROUP BY tnid');

    $this->dbh->exec('UPDATE serienteilnehmer, teilnahmezaehlung
    SET serienteilnehmer.teilnahmen=teilnahmezaehlung.teilnahmen, serienteilnehmer.bonusteilnahmen=0
    WHERE serienteilnehmer.tnid=teilnahmezaehlung.tnid');

    $this->dbh->exec('UPDATE serienteilnehmer  SET serienteilnehmer.bonusteilnahmen=0');

    $this->dbh->exec('UPDATE serienteilnehmer, teilnahmezaehlung
      SET serienteilnehmer.bonusteilnahmen=teilnahmezaehlung.teilnahmen-4
      WHERE serienteilnehmer.tnid=teilnahmezaehlung.tnid and teilnahmezaehlung.teilnahmen>4');

    # Berechnung der Serien-Zeit mit 45 Sekunden Bonus ab dem 5. Lauf - Achtung, SEC_TO_TIME und TIME_TO_SEC sind kein Standard-SQL
    $this->dbh->exec('DELETE FROM serienrangliste');
    $this->dbh->exec('INSERT INTO serienrangliste(serienid, tnid, serienzeit, bonuszeit)
     SELECT '.$this->serienid.', t.tnid, SEC_TO_TIME(sum(TIME_TO_SEC(e.zeit)*e.inwertung)-t.bonusteilnahmen*45) AS serienzeit, SEC_TO_TIME(t.bonusteilnahmen*45) AS bonuszeit
     FROM serienteilnehmer t, serieneinzelergebnisse e
     WHERE t.tnid=e.tnid GROUP BY t.tnid');

     print "Platzierungen...<br>\n";
     flush();
     ob_flush();

    try
    {

    $sthak=$this->dbh->prepare("SELECT DISTINCT altersklasse FROM serienteilnehmer;");
    $sthak->execute();
    foreach($sthak->fetchAll(PDO::FETCH_ASSOC) as $akrow)
    {
      $altersklasse=$akrow['altersklasse'];
      try {
        $this->dbh->exec('DROP TABLE akliste');
      } catch (Exception $e) { }

      $this->dbh->exec('CREATE TEMPORARY TABLE akliste
        AS SELECT r.tnid, r.serienzeit, t.bonusteilnahmen, t.teilnahmen, t.altersklasse, t.geschlecht
                        FROM serienrangliste r, serienteilnehmer t
                        WHERE r.tnid=t.tnid
                        AND t.altersklasse=\''.$altersklasse.'\';');
      try {
            $this->dbh->exec('DROP TABLE akrangliste');
          } catch (Exception $e) { }

      $this->dbh->exec('CREATE TEMPORARY TABLE akrangliste
                          AS SELECT a.tnid, (SELECT @curRank:=@curRank+1) as altersklassenplatz
                          FROM akliste a, (SELECT @curRank := 0) r
                          ORDER BY a.teilnahmen-a.bonusteilnahmen DESC, a.serienzeit ASC');

      $this->dbh->exec('UPDATE serienrangliste, akrangliste
                          SET serienrangliste.altersklassenplatz=akrangliste.altersklassenplatz
                          WHERE serienrangliste.tnid=akrangliste.tnid');
    }

    foreach ( array('m','w') as $geschlecht )
    {
      try { $this->dbh->exec('DROP TABLE mwliste'); } catch (Exception $e) { }

      $this->dbh->exec('CREATE TEMPORARY TABLE mwliste
        AS SELECT r.tnid, r.serienzeit, t.bonusteilnahmen, t.teilnahmen, t.altersklasse, t.geschlecht
                        FROM serienrangliste r, serienteilnehmer t
                        WHERE r.tnid=t.tnid
                        AND t.geschlecht=\''.$geschlecht.'\';');

      try { $this->dbh->exec('DROP TABLE mwrangliste'); } catch (Exception $e) { }

      $this->dbh->exec('CREATE TEMPORARY TABLE mwrangliste
                          AS SELECT a.tnid, (SELECT @curRank:=@curRank+1) as mwplatz
                          FROM mwliste a, (SELECT @curRank := 0) r
                          ORDER BY a.teilnahmen-a.bonusteilnahmen DESC, a.serienzeit ASC');

      $this->dbh->exec('UPDATE serienrangliste, mwrangliste
                          SET serienrangliste.mwplatz=mwrangliste.mwplatz
                          WHERE serienrangliste.tnid=mwrangliste.tnid');
    }

    try { $this->dbh->exec('DROP TABLE gliste'); } catch (Exception $e) { }

    $this->dbh->exec('CREATE TEMPORARY TABLE gliste
      AS SELECT r.tnid, r.serienzeit, t.bonusteilnahmen, t.teilnahmen
                      FROM serienrangliste r, serienteilnehmer t
                      WHERE r.tnid=t.tnid');

    try { $this->dbh->exec('DROP TABLE grangliste'); } catch (Exception $e) { }

    $this->dbh->exec('CREATE TEMPORARY TABLE grangliste
                        AS SELECT a.tnid, (SELECT @curRank:=@curRank+1) as gesamtplatz
                        FROM gliste a, (SELECT @curRank := 0) r
                        ORDER BY a.teilnahmen-a.bonusteilnahmen DESC, a.serienzeit ASC');

    $this->dbh->exec('UPDATE serienrangliste, grangliste
                        SET serienrangliste.gesamtplatz=grangliste.gesamtplatz
                        WHERE serienrangliste.tnid=grangliste.tnid');

  } catch (Exception $e) { print $e;}
  }

  function HTMLAUswertung()
  {
    echo "HTML-Formatierung<br>\n";
    flush();
    ob_flush();
    $awtabelle=array();
    $sth=$this->dbh->prepare('SELECT r.tnid, r.serienzeit, r.bonuszeit, t.teilnahmen,
                                     t.vorname, t.nachname, r.gesamtplatz, r.mwplatz,
                                     t.altersklasse, r.altersklassenplatz, t.geschlecht,
                                     t.jahrgang, t.verein
                       FROM serienrangliste r, serienteilnehmer t where r.tnid=t.tnid
                       ORDER BY t.teilnahmen-t.bonusteilnahmen DESC, r.serienzeit ASC');
    $sth->execute();
    $daten=$sth->fetchAll(PDO::FETCH_ASSOC);
    $zeile=0;
    foreach ($daten as $row)
    {
#      print "T ".$row['tnid']."   ".$row['serienzeit']."<br>\n";
      $awtabelle[$row['tnid']]=["serienzeit" => $row['serienzeit'],
                                "teilnahmen" => $row['teilnahmen'],
                                "bonuszeit" => $row['bonuszeit'],
                                "vorname" => $row['vorname'],
                                "nachname" => $row['nachname'],
                                "verein" => $row['verein'],
                                "gesamtplatz" => ($zeile++),
                                "mwplatz" => $row['mwplatz'],
                                "altersklasse" => $row['altersklasse'],
                                "altersklassenplatz" => $row['altersklassenplatz'],
                                "geschlecht" => $row['geschlecht'],
                                "jahrgang" => $row['jahrgang']
                               ];
    }
    $sth = $this->dbh->prepare("SELECT v.name,v.titel,datensatzid
                                FROM veranstaltungen v,letztedatensaetze d
                                WHERE v.veranstaltungsid=d.veranstaltungsid");
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

    $zeile='<th>Name</th><th>Verein</th>';
    foreach ($laufdaten as $row)
    {
        $zeile.="<th>".$row["titel"]."</th>";
    }
    $zeile.="<th>Gesamt-<br>platz</th>";
    $zeile.="<th>AK<br>Platz</th>";
    $zeile.="<th>AK</th>";
    $zeile.="<th>Bonuszeit</th>";
    $zeile.="<th>Serienwertung</th>\n";

    $zeilekurz='<th>Platz</th><th>Name</th><th>Verein</th><th>Serien<br>wertung</th>\n';
    $this->dbh->exec('DELETE FROM serienauswertungen WHERE serienid='.$this->serienid);

    $this->dbh->exec('INSERT INTO serienauswertungen (serienid, format, htmlhead)
                      VALUES ('.$this->serienid.',0 , \''.$zeile.'\');');
    $this->dbh->exec('INSERT INTO serienauswertungen (serienid, format, htmlhead)
                     VALUES ('.$this->serienid.',1 ,\''.$zeilekurz.'\');');


    $this->dbh->exec('DELETE FROM serienwebergebnisse WHERE serienid='.$this->serienid);

    $sthw=$this->dbh->prepare("INSERT INTO serienwebergebnisse(serienid,tnid,format,htmlrow)
                              VALUES (:serienid, :tnid, :format, :htmlrow);");

    foreach($awtabelle as $tnid => $teilnehmer)
    {
      $zeile="<td>".$teilnehmer["vorname"]." ".$teilnehmer["nachname"]."</td>"
            ."<td>".$teilnehmer["verein"]."</td>";

      $zeilekurz="<td>".$teilnehmer["mwplatz"].".</td><td>".$teilnehmer["vorname"]." ".$teilnehmer["nachname"]."</td>"
            ."<td>".$teilnehmer["verein"]."</td>";

      foreach ($laufdaten as $row)
      {
        if(isset($teilnehmer[$row['name']]['inwertung']))
        {
         if( $teilnehmer[$row['name']]['inwertung']>0)
         {
           $zeile.="<td><b>".$teilnehmer[$row['name']]["zeit"]."</b></td>";
         } else {
           $zeile.="<td>".$teilnehmer[$row['name']]["zeit"]."</td>";
         }
       } else {
         $zeile.="<td>--</td>";
       }
      }
      $zeile.="<td>".$teilnehmer["mwplatz"].".</td>";
      $zeile.="<td>".$teilnehmer["altersklassenplatz"].".</td>";
      $zeile.="<td>".$teilnehmer["altersklasse"]."</td>";
      $zeile.="<td>".substr($teilnehmer["bonuszeit"],3)."</td>";
      if( $teilnehmer['teilnahmen']>=4)
      {
        $szzeile="<td>".$teilnehmer["serienzeit"]."</td>";
      } else {
        $szzeile="<td><strike>".$teilnehmer["serienzeit"]."</strike>Zu wenig Teilnahmen</td>";
      }
      $zeile.=$szzeile;
      $zeilekurz.=$szzeile;
      $sthw->execute(array('tnid' => $tnid,
                           'serienid' => $this->serienid,
                           'format'=> 0,
                           'htmlrow' => $zeile));
      $sthw->execute(array('tnid' => $tnid,
                           'serienid' => $this->serienid,
                           'format'=> 1,
                           'htmlrow' => $zeilekurz));
      if(count($laufdaten)>1) {
        erzeugeUrkunde($tnid, $teilnehmer["vorname"]." ".$teilnehmer["nachname"],
          $teilnehmer["serienzeit"], $teilnehmer["mwplatz"],
          $teilnehmer["altersklasse"], $teilnehmer["altersklassenplatz"]);
       }
    }
  }
}

echo "Fertig!<br><b>Die Serienwertung ist neu berechnet worden und steht ab sofort online.</b>\n"
?>
<a href="./uebersicht.php">Zur&uuml;ck zur &Uuml;bersicht</a>
</body>
</html>
