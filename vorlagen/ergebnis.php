<?php

class DistanzAuswahl
{
  private $dbh;
  function __construct( $veranstaltung )
  {
    $this->dbh = new PDO('mysql:host=localhost;dbname=ERSETZEDBNAME', 'ERSETZEDBUSER', 'ERSETZEDBPASSWD');
    $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->veranstaltung = $veranstaltung;
  }

  function __destruct() {
  }
  public function Strecken()
  {
    $sth = $this->dbh->prepare("SELECT s.streckenid,s.name,s.meter FROM strecken s, veranstaltungen v
                                WHERE s.veranstaltungsid=v.veranstaltungsid
                                AND v.name=:veranstaltungsname;");
    $sth->execute(array('veranstaltungsname' => $this->veranstaltung ));
    $strecken=array();
    foreach ($sth->fetchAll() as $v)
      $strecken[$v['streckenid']]=$v['name'];
    return $strecken;
  }


}
class ErgebnisTabelle
{
  private $dbh;
  private $vid;
  function __construct( $veranstaltung, $quelldatei, $streckenid )
  {
    $this->dbh = new PDO('mysql:host=localhost;dbname=ERSETZEDBNAME', 'ERSETZEDBUSER', 'ERSETZEDBPASSWD');
    $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->dsid=$this->erzeugeDatensatz($veranstaltung, $quelldatei, $streckenid);
    $this->spaltennamen=array("Nachname","Vorname","Jahrgang","Geschlecht","Verein","Zeit");
  }

  function __destruct() {
  }

  # Einen neuen Datensatzeintrag anhand des Veranstaltungsnamens aus dem login erzeugen
  private function erzeugeDatensatz( $veranstaltung, $quelldatei, $streckenid )
  {
    $this->dbh->beginTransaction();
    $sql = $this->dbh->prepare("INSERT INTO datensaetze(veranstaltungsid, quelldatei, streckenid)
                                SELECT veranstaltungsid,:quelldatei,:streckenid FROM veranstaltungen v
                                WHERE v.name=:veranstaltungsname;");
    $sql->execute(array('veranstaltungsname' => $veranstaltung,
                        'quelldatei' => $quelldatei,
                        'streckenid' => $streckenid ) );

    $res_row = $this->dbh->query("SELECT max(datensatzid) from datensaetze;")->fetch();
    return $res_row['max(datensatzid)'];
  }

  public function erkenneSpalten( $kopfzeile )
  {
    $this->spaltenindizes=array();
    foreach($this->spaltennamen as $column)
    {
      foreach($kopfzeile as $c => $name)
      {
        if( strcmp($name, $column) == 0)
        {
          if(isset($this->spaltenindizes[$name] ))
            throw new \Exception("Ein Spaltenname kommt doppelt vor.", 1);
          $this->spaltenindizes[$name] = $c;
          print "Spalte ".$name." an Stelle ".$c."\n";
        }
      }
      if(!isset($this->spaltenindizes[$column] ))
        throw new \Exception("Die Spalte \"".$column."\" fehlt!", 1);
    }
  }
  public function parseDaten( $zeile )
  {
    $sql = $this->dbh->prepare("INSERT INTO ergebnisse (datensatzid, nachname, vorname, jahrgang, geschlecht, verein, zeit)
                                    VALUES (:datensatzid, :nachname, :vorname, :jahrgang, :geschlecht, :verein, :zeit)");
    $nachname = $zeile[$this->spaltenindizes["Nachname"]];
    $vorname = $zeile[$this->spaltenindizes["Vorname"]];
    $jahrgang = $zeile[$this->spaltenindizes["Jahrgang"]];
    $geschlecht = strtolower(substr($zeile[$this->spaltenindizes["Geschlecht"]],0,1)); # erstes Zeichen aus dem Zelleninhalt
    if( strcmp($geschlecht, "m")!=0 && strcmp($geschlecht, "w")!=0 )
      throw new \Exception("das Geschlecht muß als m/w angegeben sein.", 1);
    $verein = $zeile[$this->spaltenindizes["Verein"]];
    $zeit = $zeile[$this->spaltenindizes["Zeit"]];
    $sql->execute(array('datensatzid' => $this->dsid,
          'nachname' => htmlspecialchars($nachname),
          'vorname' => htmlspecialchars($vorname),
          'jahrgang' => intval($jahrgang),
          'geschlecht' => htmlspecialchars($geschlecht),
          'verein' => htmlspecialchars($verein),
          'zeit' => $zeit)
      );
  }

  public function schreibeErgebnisse()
  {
    $this->dsid=-1;
    $this->dbh->commit();
  }
}

?>
