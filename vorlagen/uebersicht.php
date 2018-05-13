<?php

  $dbh = new PDO('mysql:host=localhost;dbname=ERSETZEDBNAME', 'ERSETZEDBUSER', 'ERSETZEDBPASSWD');
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $datensaetze = $dbh->prepare("SELECT v.name AS vname, v.titel, s.name AS sname,
     d.erzeugungszeitpunkt, d.quelldatei
   FROM veranstaltungen v, strecken s, datensaetze d
   WHERE v.veranstaltungsid=s.veranstaltungsid
   AND v.veranstaltungsid = d.veranstaltungsid
   AND s.streckenid = d.streckenid
   ORDER BY d.erzeugungszeitpunkt");

   $laufteilnehmer = $dbh->prepare("SELECT titel,count(tnid) AS anzahl
   FROM  serieneinzelergebnisse e, letztedatensaetze d, veranstaltungen v
   WHERE d.veranstaltungsid=v.veranstaltungsid
   AND e.datensatzid=d.datensatzid
   GROUP BY d.veranstaltungsid");

   $serienteilnehmer = $dbh->prepare("SELECT teilnahmen,count(teilnahmen) AS anzahl
    FROM serienteilnehmer
    GROUP BY teilnahmen");

   $gesamtteilnehmer = $dbh->prepare("SELECT count(*) AS anzahl FROM serienteilnehmer");

?>
<html>
<head>
  <title>Übersicht</title>
</head>
<body>

  <h2>Veranstaltungen</h2>
  <table class="data-table">
    <caption class="title">Veranstaltungen</caption>
    <thead>
      <tr>
        <th>Veranstaltung</th>
        <th>Teilnehmerzahl für die Laufserie</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $anzahl   = 0;
    $laufteilnehmer->execute();
    while ($row = $laufteilnehmer->fetch(PDO::FETCH_ASSOC))
    {
      echo '<tr>
          <td>'.$row['titel'].'</td>
          <td>'.$row['anzahl'].'</td>
        </tr>';
    }?>
    </tbody>
  </table>

  <?php
  $gesamtteilnehmer->execute();
  $row = $gesamtteilnehmer->fetch(PDO::FETCH_ASSOC);
  echo "Insgesamt ".$row['anzahl']." Teilnehmer";
  ?>

  <h2>Serienteilnahme</h2>
  <table class="data-table">
    <caption class="title">Serienteilnahme</caption>
    <thead>
      <tr>
        <th>Läufe pro Teilnehmer</th>
        <th>Anzahl</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $anzahl   = 0;
    $serienteilnehmer->execute();
    while ($row = $serienteilnehmer->fetch(PDO::FETCH_ASSOC))
    {
      echo '<tr>
          <td>'.$row['teilnahmen'].'</td>
          <td>'.$row['anzahl'].'</td>
        </tr>';
    }?>
    </tbody>
  </table>

  <h2>Ergebnisdatensätze</h2>
  <table class="data-table">
    <caption class="title">Ergebnisdatensätze</caption>
      <thead>
        <tr>
          <th>Lauf</th>
          <th>Strecke</th>
          <th>Benutzer</th>
          <th>Hochgeladen</th>
          <th>Datei</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $anzahl   = 0;
      $datensaetze->execute();
      while ($row = $datensaetze->fetch(PDO::FETCH_ASSOC))
      {
        echo '<tr>
            <td>'.$row['titel'].'</td>
            <td>'.$row['sname'].'</td>
            <td>'.$row['vname'].'</td>
            <td>'.$row['erzeugungszeitpunkt'].'</td>
            <td>'.$row['quelldatei'].'</td>
          </tr>';
      }?>
      </tbody>
    </table>
</body>
</html>
