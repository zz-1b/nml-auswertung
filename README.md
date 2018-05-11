## Laufserien-Wertung ##

Dies Repository enthält den Quellcode des Auswertungssystems für die Laufserie Nord-Münsterland.

Der Zweck ist das Sammeln der Einzelergebnisse, die automatische Berechnung der Serienwertung und das Veröffentlichen der
Ergebnislisten.

Für jede teilnehmende Laufveranstaltung gibt es einen per [htaccess](https://wiki.selfhtml.org/wiki/Webserver/htaccess) gesicherten Zugang
zur Eingabe der Ergebnisse. Die Serienwertung bzw. Zwischenstände vor Abschluß des letzten Laufes werden direkt nach dem Hochladen neuer
Ergebnisse berechnet und über ein öffentlich zugängliches Suchformular nach Geschlecht, Altersklassen und per Stichwortsuche aufgeschlüsselt
bereitgestellt.

### Voraussetzungen, Konfiguration und Aufsetzen des Systems ###
Das System wird mit Hilfe eines Generatorskripts aus Konfigurationsdaten und Vorlagen aufgebaut. Dazu wird 'GNU Make' und Perl eingesetzt.
Diese Werkzeuge sind in praktisch allen Linuxdistributionen von Haus aus verfügbar, Alternativen sind Cygwin für Windows oder das neue "Linux Subsystem for Windows".

Das generierte System besteht aus einem SQL-Skript ('createdb.sql') zum initialen Aufsetzen der MySQL- oder MariaDB-Datenbank, verschiedenen PHP-Skripten und HTML-Seiten sowie Webserver-Konfigurationsdateien (.htpasswd/.htaccess) für die Zugangskontrolle.
Das 'install'-Ziel im Makefile enthält die Kommandos (im Beispiel einen rsync-Aufruf) der das generierte System auf einen entfernten Webserver überträgt. Die Installation auf dem Server unterscheidet sich je nach Provider und dort verwendetem Webserver. Daher muss das 'install'-Kommando entsprechend angepasst werden. Das Beispiel geht von einem SSH-Zugang beim Provider und einem Apache-Webserver aus.

Die Serie und die einzelnen Veranstaltungen sind in der Datei 'config.json' beschrieben. Pro Lauf wird ein Kurzname, der angezeigte
Titel und eine Strecke definiert. Das Repository enthält eine Beispiel-Konfigurationsdatei.
Die Passwörter für die Hochladezugänge werden in der Datei 'passworte' zeilenweise im Format 'kurzname:klartextpasswort' abgelegt.
Die Konfiguration des Datenbankzugangs erfolgt in der Datei 'dblogin_details.mk', die vom Makefile direkt eingebunden wird und folgende
Variablen setzen muss:

```
# der Name der anzulegenden Datenbank
DBNAME=laufserienwertung
# der Name des Datenbanknutzers, der in den generierten PHP-Skripten verwendet wird
DBUSER=serienwertungsbenutzer
# das Passwort dazu
DBPASSWD="geheim"
# ein rsync-Ziel für die Installation - ggf. mit Anpassung des Makefiles auch eine FTP-Url o. ä.
DEPLOYTO=www-data@webserver.provider.net:/server/html/nml-auswertung
```

### Eingabeformat ###
Die Ergebnislisten müssen mindestens die Spalten Vorname, Nachname, Jahrgang(vierstellig), Geschlecht(als m/w)und die Zeit(hh:mm:ss) in beliebiger Reihenfolge enthalten. Die Dateien müssen im CSV-Format mit Komma als Feldtrenner und Anführungszeichen als Feldbegrenzer hochgeladen werden (Trennzeichen sind diskutabel bzw. könnten einstellbar werden). Umlaute und Sonderzeichen müssen UTF-8-kodiert sein. Excel-Dateien lassen sich mit "Speichern Unter" und Dateiformatauswahl 'CSV' konvertieren. (TODO: genauer/mit screenshots )

Eine Beispieldatei:
```
"Nachname","Vorname","Jahrgang","Geschlecht","Verein","Zeit"
"Friedrich","Wanda","1996","w","SuS Neunkirchen","0:42:25"
"Krüger","Günther","1991","m","Bergziegen","0:39:51"
"Geuker","Kurt","1996","m","TuS Altenberge","0:47:28"
```

Das Formular zum Hochladen der Ergebnisse wird unter "adm/ergebnissehochladen.php" abgelegt.

## Abhängigkeiten ##

Die Generatorskripte brauchen zwei Pakete, die auf Ubuntu-Systemen in der Regel
nachinstalliert werden müssen:

sudo apt-get install apache2-utils
sudo apt-get install libtypes-path-tiny-perl

Zufallstestdatengenerator:
sudo apt-get install libmath-random-perl

Die generierten Skripte setzen für die Zeitberechnung MySQL- bzw. MariaDB-spezifische Funktionen ein. Soll ein anderes Datenbanksystem verwendet werden, muss die Wertungsberechnung in SerienWertung.php angepasst werden.

## Internes ##
"generator.pl" instantiiert die Vorlagen und ersetzt dazu Vorlagen-Schlüsselwörter mit Konfigurationsinhalten.
Der Zusammenhang von Vorlagen und generierten Dateien ist im Makefile beschrieben.
Das Datenmodell/das Datenbankschema ist in den Kommentaren in generator.pl beschrieben.
Das Hochladeformular "ergebnissehochladen.php" stößt das "csvimport.php" für das Prüfen und Importieren der CSV-Textdateien an.
Die Klasse ErgebnisTabelle in "ergebnis.php" erledigt das Einfügen der Werte in die Datenbank.
Die Klasse SerienWertung in serienwertung.php rechnet die eindeutige Zuordnung der Teilnehmer aus den hochgeladenen Rohdaten und die Wertung
aus und legt vorformatierte Zeilen der Ergebnisausgabe in der Datenbank ab.
Das PHP-Skript serienergebnisse.php gibt die vorformatierten Ergebnisse gefiltert aus.
