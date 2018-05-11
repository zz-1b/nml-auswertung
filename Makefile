
include dblogin_details.mk

ifndef DEPLOYTO
$(error DEPLOYTO ist nicht gesetzt, dblogin_details.mk prüfen!)
endif

GENERATED = createdb.sql adm/ergebnissehochladen.php adm/ergebnis.php \
 adm/csvupload.php adm/serienwertung.php adm/werteAus.php adm/.htaccess .htpasswd

all:	3rdparty $(GENERATED)

3rdparty:
	mkdir -p 3rdparty/ajaxcrud
	cd 3rdparty; wget -O ajaxcrud.zip http://www.ajaxcrud.com/getFile.php; cd ajaxcrud; unzip -x ../ajaxcrud.zip ; rm -rf examples
	cd 3rdparty; wget http://fpdf.de/downloads/fpdf181.tgz; tar xzvf fpdf181.tgz

createdb.sql:	config.json generator.pl
	perl generator.pl --dbuser $(DBUSER) --gen db

adm:
	mkdir adm
	perl generator.pl --gen htuser

adm/ergebnissehochladen.php:	adm config.json generator.pl vorlagen/ergebnissehochladen.php
	perl generator.pl --in vorlagen/ergebnissehochladen.php --out adm/ergebnissehochladen.php
#	perl generator.pl -eingabe

adm/csvupload.php:	adm config.json generator.pl vorlagen/csvimport.php
	perl generator.pl --in vorlagen/csvimport.php --out adm/csvimport.php

adm/ergebnis.php:	adm config.json generator.pl vorlagen/ergebnis.php
		perl generator.pl --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) --in vorlagen/ergebnis.php --out adm/ergebnis.php

adm/serienwertung.php: adm config.json generator.pl vorlagen/serienwertung.php
	perl generator.pl --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) --in vorlagen/serienwertung.php --out adm/serienwertung.php

adm/werteAus.php: adm config.json generator.pl vorlagen/werteAus.php
		perl generator.pl --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) --in vorlagen/werteAus.php --out adm/werteAus.php

adm/.htaccess: adm config.json generator.pl vorlagen/.htaccess
	perl generator.pl --in vorlagen/.htaccess --out adm/.htaccess

wertung.html:
	perl generator.pl -ausgabe

install:	3rdparty $(GENERATED)
	rsync --info NAME1 --delete --recursive --links --verbose --include=".htaccess" --include ".htpasswd" \
	--exclude=".*" --exclude="*.tmpl" --exclude="*~" --exclude="vorlagen" --exclude "dblogin_details.mk" \
	 -av . $(DEPLOYTO)/.


clean:
	rm -rf adm

veryclean:
	rm -rf 3rdparty
