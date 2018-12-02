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
          echo "Spalte ".$name." an Stelle ".$c."\n";
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
    if( strlen($nachname) == 0 || strlen($nachname)>40 )
      throw new \Exception("Der Nachname darf nicht leer und nicht länger als 40 Zeichen sein.");
    $vorname = $zeile[$this->spaltenindizes["Vorname"]];
    if( strlen($vorname) == 0 || strlen($vorname)>40 )
      throw new \Exception("Der Vorname darf nicht leer und nicht länger als 40 Zeichen sein.");
    $jahrgang = $zeile[$this->spaltenindizes["Jahrgang"]];
    if( intval($jahrgang<1900) )
      throw new \Exception("Das Geburtsjahr muss vierstellig sein.", 1);
    if( intval($jahrgang>2030) )
        throw new \Exception("Das Geburtsjahr ist nicht plausibel.", 1);
    $geschlecht = strtolower(substr($zeile[$this->spaltenindizes["Geschlecht"]],0,1)); # erstes Zeichen aus dem Zelleninhalt
    if( strcmp($geschlecht, "m")!=0 && strcmp($geschlecht, "w")!=0 )
      throw new \Exception("das Geschlecht muß als m/w angegeben sein.", 1);
    $verein = $zeile[$this->spaltenindizes["Verein"]];
    if( strlen($verein)>60 )
      throw new \Exception("Der Vereinsname darf nicht länger als 60 Zeichen sein.");
    $zeit = $zeile[$this->spaltenindizes["Zeit"]];
    if( !preg_match('/([0]?[\d]):([012345][\d]?):[012345][\d]?(,[\d])?/',$zeit))
      throw new \Exception("Formatfehler: Die Zeit muss in der Form hh:mm:ss angegeben werden.");
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
