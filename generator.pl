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
 Datenbank/Modell für Laufergebnisse und Laufserien

 Serien bestehen aus vorgegebenen Veranstaltungen.
 Pro Veranstaltung gibt es Strecken mit Ergebnis-Ergebnisdatensätzen.
*/
USE ${dbname};

CREATE TABLE veranstaltungen
(
  veranstaltungsid INT NOT NULL AUTO_INCREMENT,
  name CHAR(40) NOT NULL,
  titel CHAR(255) NOT NULL,
  PRIMARY KEY (veranstaltungsid)
);


CREATE TABLE strecken
(
  streckenid INT AUTO_INCREMENT PRIMARY KEY,
  veranstaltungsid INT NOT NULL,
  meter INT NOT NULL,
  name CHAR(40) NOT NULL,
  FOREIGN KEY (veranstaltungsid) REFERENCES veranstaltungen(veranstaltungsid) ON DELETE CASCADE
);

-- Hier ein Eintrag je hochgeladener Datei
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
   Rohdaten von den teilnehmenden Vereinen
   Personen in den hochzuladenden Tabellen müssen (incl. Verein)
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
 zeit TIME(1) NOT NULL,
 INDEX vid_idx (datensatzid),
 CONSTRAINT PK_eindeutige_Person PRIMARY KEY ( datensatzid, nachname, vorname, jahrgang, verein, geschlecht ),
 FOREIGN KEY (datensatzid) REFERENCES datensaetze(datensatzid) ON DELETE CASCADE
);

-- Generierte tabellen
CREATE TABLE serienteilnehmer
(
 tnid INT AUTO_INCREMENT PRIMARY KEY,
 nachname CHAR(40) NOT NULL,
 vorname CHAR(40) NOT NULL,
 jahrgang YEAR(4) NOT NULL,
 geschlecht ENUM('m','w') NOT NULL,
 -- notnagel zur identifikation bei namens/jahrgangsgleichheit
 verein CHAR(60),
 teilnahmen INT,
 bonusteilnahmen INT,
 altersklasse CHAR(8)
);
CREATE TABLE letztedatensaetze
(
  veranstaltungsid INT NOT NULL PRIMARY KEY,
  datensatzid INT NOT NULL,
  FOREIGN KEY(datensatzid) REFERENCES datensaetze(datensatzid) ON DELETE CASCADE
);

CREATE TABLE serieneinzelergebnisse
(
 tnid INT NOT NULL,
 datensatzid int NOT NULL,
 zeit TIME(1) NOT NULL,
 rang INT NOT NULL,
 inwertung BOOL NOT NULL,
 CONSTRAINT PK_Wertung PRIMARY KEY (tnid, datensatzid),
 FOREIGN KEY (tnid) REFERENCES serienteilnehmer(tnid) ON DELETE CASCADE,
 FOREIGN KEY (datensatzid) REFERENCES letztedatensaetze(datensatzid) ON DELETE CASCADE
);

CREATE TABLE serienrangliste
(
 tnid INT NOT NULL PRIMARY KEY,
 serienzeit TIME(1) NOT NULL,
 FOREIGN KEY (tnid) REFERENCES serienteilnehmer(tnid) ON DELETE CASCADE
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

# vordefinierte Läufe in die Datenbank
  my @laeufe=@{${$config}{'laeufe'}};
  foreach $lauf (@laeufe)
  {
    my $laufname = ${$lauf}{'name'};
    my $lauftitel = ${$lauf}{'titel'};
    my $insert="INSERT INTO veranstaltungen (name,titel) VALUES ('$laufname','$lauftitel');";
    $sql.=$insert."\n";
    my @strecken=@{${$lauf}{'strecken'}};
    foreach $strecke (@strecken)
    {
      my $streckenname = ${$strecke}{'name'};
      my $distanz = ${$strecke}{'distanz'};
      my $insert="INSERT INTO strecken (veranstaltungsid, name, meter)
      SELECT veranstaltungsid,'$streckenname','$distanz'
      FROM veranstaltungen
      WHERE name='$laufname' AND titel='$lauftitel';";
      $sql.=$insert."\n";
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
            system("htpasswd -cb .htpasswd $laufname $admpasswort") == 0 or die("htpasswd bzw apache2-utils versagt/fehlt");
            $cr=1;
        }
        else {
            system("htpasswd -b .htpasswd $laufname $admpasswort") == 0 or die("htpasswd bzw apache2-utils versagt/fehlt");
        }
    }
}

sub keyword_replace
{
  my ($config,$krwin, $kwrout) = @_;
  open(KWRIN, "<", $kwrin) or die("Cannot open $kwrin for reading.");
  open(KWROUT, ">", $kwrout) or die("Cannot open $kwrout for writing.");
  while(<KWRIN>)
  {
    s/ERSETZEDBNAME/${$config}{'db-name'}/g;
    s/ERSETZEDBUSER/${$config}{'db-user'}/g;
    s/ERSETZEDBPASSWD/${$config}{'db-passwd'}/g;
    s/ERSETZEUPLOADFOLDER/${$config}{'upload-folder'}/g;
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
my $result = GetOptions (
      "in:s" =>\$kwrin,
      "out:s" =>\$kwrout,
      "dbuser=s" => \$dbuser,
      "dbpasswd=s" => \$dbpasswd,
      "dbname=s" => \$dbname,
      "gen=s" => \$gen);

# json::pp experiment
#$perl_hash_or_arrayref = {bla => ["blub", "plip"]};
#$perl_hash_or_arrayref = {t=>"goldig",bla => {t => "blub", u => "plip"}};
#$perl_hash_or_arrayref = {bla => [{t => "blub", u => "plip"}, {t => "fisch", u => "fusch"}]};

#$utf8_encoded_json_text = encode_json $perl_hash_or_arrayref;
#print "Test: ".$utf8_encoded_json_text."\n";

#my $fc = path($configfile)->slurp;
#print "Datei:".$fc."\n";
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

#${$config}{'db-grants'} = true;
#my @laeufe=@{${$config}{'laeufe'}};
#foreach $lauf (@laeufe)
#    print "Lauf:".${$lauf}{'name'}."\n";

if( $kwrin ne "")
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
