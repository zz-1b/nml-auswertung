#!/usr/bin/perl
use utf8;
use Getopt::Long;
use File::Basename;
use Path::Tiny;
use JSON::PP;
use Apache::Htpasswd;

sub gen_db
{
    my ($config) = @_;
    my $dbname = ${$config}{'db-name'};
    $sql="
drop database ${dbname};
CREATE DATABASE ${dbname} CHARACTER SET utf8;
/*
 Datenbank/Datenmodell für Laufergebnisse und Laufserien

 Serien bestehen aus vorgegebenen Veranstaltungen.
 Pro Veranstaltung gibt es Strecken mit (einzelnen) Ergebnisdatensätzen.

 Die Tabellen beschreiben das Datenmodell entlang der Beziehungen zwischen
 den modellierten Daten, ausgedrückt durch Fremdschlüsselbeziehungen.
*/
USE ${dbname};

CREATE TABLE serien
(
 serienid INT NOT NULL AUTO_INCREMENT,
 urkundenid INT,
 titel CHAR(255) NOT NULL,
 PRIMARY KEY (serienid)
);

/*
  Laufveranstaltungen, die Teil einer Laufserie sein können
*/
CREATE TABLE veranstaltungen
(
  veranstaltungsid INT NOT NULL AUTO_INCREMENT,
  name CHAR(40) NOT NULL,
  titel CHAR(255) NOT NULL,
  zeit TIMESTAMP NOT NULL,
  serienid INT,
  urkundenid INT,
  PRIMARY KEY (veranstaltungsid)
);

/*
  Hochgeladene Vorlagen
*/
CREATE TABLE urkundenvorlagen
(
  urkundenid INT AUTO_INCREMENT PRIMARY KEY,
  pfad VARCHAR(512)
);

/*
 Hier sind verschiedene Distanzen in den Einzelveranstaltungen abgebildet.
 Diese Tabelle erlaubt weitere Ansichten (z. B. Ergebnisse der Einzelveranstaltungen) der Datenbasis.
 Für die Laufserienauswertung ist sie nicht zwingend nötig.
*/
CREATE TABLE strecken
(
  streckenid INT AUTO_INCREMENT PRIMARY KEY,
  veranstaltungsid INT NOT NULL,
  meter INT NOT NULL,
  name CHAR(40) NOT NULL,
  FOREIGN KEY (veranstaltungsid) REFERENCES veranstaltungen(veranstaltungsid) ON DELETE CASCADE
);

/*
  In dieser Tabelle wird ein Eintrag mit Daten zum Hochladevorgang abgelegt, wenn
  neue Ergebnislisten eingegeben werden.
  Jede hochgeladene Liste gehört zu einer Distanz in einer Veranstaltung.
*/
CREATE TABLE datensaetze
(
  datensatzid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  veranstaltungsid INT NOT NULL,
  streckenid INT NOT NULL,
  erzeugungszeitpunkt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  quelldatei VARCHAR(512),
  FOREIGN KEY (veranstaltungsid) REFERENCES veranstaltungen(veranstaltungsid) ON DELETE CASCADE,
  FOREIGN KEY (streckenid) REFERENCES strecken(streckenid) ON DELETE CASCADE
);

/*
   Rohdaten der Ergebnislisten von den teilnehmenden Vereinen
   Personen in den hochzuladenden Tabellen müssen in den vorgegebenen Attributen (incl. Verein)
   eindeutig sein, siehe Primärschlüsseldefinition.
*/
CREATE TABLE ergebnisse
(
  datensatzid int NOT NULL,
  nachname CHAR(40) NOT NULL,
  vorname CHAR(40) NOT NULL,
  jahrgang YEAR(4) NOT NULL,
  geschlecht ENUM('m','w') NOT NULL,
  verein CHAR(60) NOT NULL,
  zeit TIME NOT NULL,
  ordnungsnr INT NOT NULL DEFAULT 1,
  CONSTRAINT PK_eindeutige_Person PRIMARY KEY ( datensatzid, nachname, vorname, jahrgang, geschlecht ),
  FOREIGN KEY (datensatzid) REFERENCES datensaetze(datensatzid) ON DELETE CASCADE
);

/*
  Die folgenden Tabellen enthalten durch das Auswertungssystem aus den Eingaben berechnete Werte.

  Die Tabelle 'serienteilnehmer' dient der Identifikation der Serienteilnehmer über alle Läufe.
*/
CREATE TABLE serienteilnehmer
(
  serienid INT NOT NULL,
  tnid INT AUTO_INCREMENT PRIMARY KEY,
  nachname CHAR(40) NOT NULL,
  vorname CHAR(40) NOT NULL,
  jahrgang YEAR(4) NOT NULL,
  geschlecht ENUM('m','w') NOT NULL,
  -- notnagel zur identifikation bei namens/jahrgangsgleichheit
  ordnungsnr INT NOT NULL,
  verein CHAR(60),
  teilnahmen INT,
  bonusteilnahmen INT,
  altersklasse CHAR(8),
  FOREIGN KEY(serienid) REFERENCES serien(serienid) ON DELETE CASCADE
);

/*
  Zur Vereinfachung der Abfragen werden hier die aktuellen Datensätze der Roh-Ergebnislisten gesammelt.
*/
CREATE TABLE letztedatensaetze
(
  veranstaltungsid INT NOT NULL PRIMARY KEY,
  datensatzid INT NOT NULL,
  FOREIGN KEY(datensatzid) REFERENCES datensaetze(datensatzid) ON DELETE CASCADE
);

/*
  In dieser Tabelle werden die Ergebnisse der Einzelläufe aufbereitet.
  Das 'inwertung'-Attribut ist 1 für die maximal 4 schnellsten Läufe, sonst 0.
*/
CREATE TABLE serieneinzelergebnisse
(
 serienid INT NOT NULL,
 tnid INT NOT NULL,
 datensatzid int NOT NULL,
 zeit TIME NOT NULL,
 rang INT NOT NULL,
 inwertung BOOL NOT NULL,
 CONSTRAINT PK_serieneinzelergebnisse PRIMARY KEY (serienid, tnid, datensatzid),
 FOREIGN KEY (tnid) REFERENCES serienteilnehmer(tnid) ON DELETE CASCADE,
 FOREIGN KEY (datensatzid) REFERENCES letztedatensaetze(datensatzid) ON DELETE CASCADE
);

/*
In dieser Tabelle wird die Serienwertung abgelegt.
*/
CREATE TABLE serienrangliste
(
 serienid INT NOT NULL,
 tnid INT NOT NULL,
 serienzeit TIME NOT NULL,
 bonuszeit TIME NOT NULL,
 gesamtplatz INT,  -- wg sortierung
 mwplatz INT,
 altersklassenplatz INT,
 CONSTRAINT PK_serienrangliste PRIMARY KEY (serienid, tnid),
 FOREIGN KEY (tnid) REFERENCES serienteilnehmer(tnid) ON DELETE CASCADE
);

/*
  Für das Suchformular pro Laufserie
*/
CREATE TABLE serienauswertungen
(
  serienid INT NOT NULL,
  format INT NOT NULL,
  htmlhead TEXT NOT NULL,
  CONSTRAINT PK_serienauswertungen PRIMARY KEY (serienid, format),
  FOREIGN KEY (serienid) REFERENCES serien(serienid) ON DELETE CASCADE
);

/*
  Für das Suchformular pro Laufserie
*/
CREATE TABLE serienwebergebnisse
(
  serienid INT NOT NULL,
  tnid INT NOT NULL,
  format INT NOT NULL,
  htmlrow TEXT NOT NULL,
  CONSTRAINT PK_serienwebergebnisse PRIMARY KEY (serienid, tnid, format),
  FOREIGN KEY (serienid,format) REFERENCES serienauswertungen(serienid,format) ON DELETE CASCADE,
  FOREIGN KEY (tnid) REFERENCES serienteilnehmer(tnid) ON DELETE CASCADE
);

/*
  Für das Suchformular pro Veranstaltung
*/
CREATE TABLE webergebnisse
(
  datensatzid INT NOT NULL,
  gesamtplatz INT NOT NULL,
  tnid INT NOT NULL,
  htmlrow TEXT NOT NULL,
  CONSTRAINT PK_webergebnisse PRIMARY KEY (datensatzid, tnid)
);

";
# Evtl. Rechte vergebnen
  if(${$config}{'db-grants'} )
  {
    $dbuser = ${$config}{'db-user'};
    $sql.="
-- nur leserechte auf der Tabelle mit vorkonfigurierten Laufdaten, sonst vollzugriff
GRANT SELECT ON ${dbname}.veranstaltungen TO '${dbuser}';
GRANT ALL ON  ${dbname}.datensaetze TO '${dbuser}';
GRANT ALL ON  ${dbname}.ergebnisse TO '${dbuser}';
GRANT ALL ON  ${dbname}.* TO '${dbuser}';
";
  }

# vordefinierte Serie/Läufe in die Datenbank
my $serientitel=${$config}{'titel'};
$sql.="INSERT INTO serien (titel) VALUES ('$serientitel');\n";

  my @laeufe=@{${$config}{'laeufe'}};
  foreach $lauf (@laeufe)
  {
    my $laufname = ${$lauf}{'name'};
    my $lauftitel = ${$lauf}{'titel'};
    my $insert="INSERT INTO veranstaltungen (name,titel,serienid)
                SELECT '$laufname','$lauftitel',max(serienid) as serienid
                FROM serien;";
    $sql.=$insert."\n";
    my @strecken=@{${$lauf}{'strecken'}};
    foreach $strecke (@strecken)
    {
      my $streckenname = ${$strecke}{'name'};
      my $distanz = ${$strecke}{'distanz'};
      $sql.="INSERT INTO strecken (veranstaltungsid, name, meter)
      SELECT veranstaltungsid,'$streckenname','$distanz'
      FROM veranstaltungen
      WHERE name='$laufname' AND titel='$lauftitel';\n";
    }
  }

  path("createdb.sql")->spew_utf8($sql);
};

sub gen_htuser {
    open(PWD,"<","passworte") or die("Keine Passwortdatei vorhanden!");
    while(<PWD>)
    {
      chomp;
      ($u,$p) = split(/:/);
      $admpassworte{$u}=$p;
    }
    my $cr     = 0;
    my @laeufe = @{ ${$config}{'laeufe'} };
    foreach $lauf (@laeufe) {
        my $laufname  = ${$lauf}{'name'};
        my $admpasswort = $admpassworte{$laufname};
        if ( $cr == 0 ) {
            system("htpasswd -bcm .htpasswd $laufname $admpasswort") == 0 or die("htpasswd bzw apache2-utils versagt/fehlt");
            $cr=1;
        }
        else {
            system("htpasswd -bm .htpasswd $laufname $admpasswort") == 0 or die("htpasswd bzw apache2-utils versagt/fehlt");
        }
    }
    close PWD;
}

sub keyword_replace
{
  my ($config,$krwin, $kwrout) = @_;
  open(KWRIN, "<", $kwrin) or die("Cannot open $kwrin for reading.");
  open(KWROUT, ">", $kwrout) or die("Cannot open $kwrout for writing.");
  while(<KWRIN>)
  {
    s/ERSETZETITEL/${$config}{'titel'}/g;
    s/ERSETZEDBNAME/${$config}{'db-name'}/g;
    s/ERSETZEDBUSER/${$config}{'db-user'}/g;
    s/ERSETZEDBPASSWD/${$config}{'db-passwd'}/g;
    s/ERSETZEUPLOADFOLDER/${$config}{'upload-folder'}/g;
    s/ERSETZEDEPLOYFOLDER/${$config}{'deploy-folder'}/g;
    s/ERSETZEJAHR/${$config}{'jahr'}/g;
    s/ERSETZEHTPASSWD/${$config}{'htpasswd'}/g;
    if( ${$config}{'db-name'} =~ /test$/)
    {
      s/BACKGROUNDIMAGE/background-image: url\(\"images\/background.png\"\)/g;
    }
    else
    {
      s/BACKGROUNDIMAGE//g;
    }
     print KWROUT;
  }
  close KWRIN;
  close KWROUT;
}

my $configfile = "config.json";
our $ofolder = "";
my $dbname="";
my $dbuser="";
my $dbpasswd="";
my $ufolder="";
my $htpasswd="";
my $result = GetOptions (
      "in:s" =>\$kwrin,
      "out:s" =>\$kwrout,
      "dbuser=s" => \$dbuser,
      "dbpasswd=s" => \$dbpasswd,
      "dbgrants" => \$dbgrants,
      "dbname=s" => \$dbname,
      "uploadfolder=s" => \$ufolder,
      "deployfolder=s" => \$dfolder,
      "htpasswd=s" => \$htpasswd,
      "gen=s" => \$gen);

$config  = decode_json path($configfile)->slurp;

if( $dbname ne "" )
{
  ${$config}{'db-name'} = $dbname;
}
if( $dbuser ne "" )
{
  ${$config}{'db-user'} = $dbuser;
}
if( $dbpasswd ne "")
{
  ${$config}{'db-passwd'} = $dbpasswd;
}
if( $dbgrants )
{
  ${$config}{'db-grants'} = 1;
}
if( $ufolder ne "")
{
   ${$config}{'upload-folder'} = $ufolder;
}
if( $dfolder ne "")
{
  ${$config}{'deploy-folder'} = $dfolder;
}
if( $htpasswd ne "")
{
  ${$config}{'htpasswd'} = $htpasswd;
}

if( $kwrin ne "" && $kwrout ne "")
{
  keyword_replace($config,$krwin, $kwrout);
}
elsif( $gen eq "db")
{
  gen_db($config);
}
elsif( $gen eq "htuser")
{
  gen_htuser($config)
}
else
{
  die("Nichts zu tun!");
}
