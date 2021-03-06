
#DEPLOY_CFG = dblogin_details_lokal.mk
DEPLOY_CFG = dblogin_details_test.mk
#DEPLOY_CFG = dblogin_details.mk  Achtung! noch Uberspace 6

include $(DEPLOY_CFG)

ifndef DEPLOYTO
$(error DEPLOYTO ist nicht gesetzt, dblogin_details.mk prüfen!)
endif

GENERATED = createdb.sql adm/ergebnissehochladen.php adm/ergebnis.php \
 adm/csvimport.php adm/serienwertung.php adm/werteAus.php adm/uebersicht.php \
 adm/urkunde.php adm/fpdf.php \
 adm/.htaccess .htaccess .htpasswd lnm-style.css lnm-style-kurz.css serienergebnisse.html serienergebnisausgabe.php \
 images/logo.svg onlineurkunde.php

all:	3rdparty $(GENERATED) adm/ci $(URKUNDE) adm/fpdf.php

3rdparty:
	mkdir -p 3rdparty
	cd 3rdparty; wget http://fpdf.de/downloads/fpdf181.tgz; tar xzvf fpdf181.tgz
	cd 3rdparty; wget https://code.jquery.com/jquery-3.3.1.min.js


adm/ci: vendor/CodeIgniter-3.1.9 vendor/grocery-crud-1.6.3 $(shell find vorlagen/adm/ci.tmpl) $(DEPLOY_CFG)
	mkdir -p adm/ci
	rsync --recursive vendor/CodeIgniter-3.1.9/* adm/ci/
	rsync --recursive vendor/grocery-crud-1.6.3/* adm/ci/
	rsync --recursive vorlagen/adm/ci.tmpl/* adm/ci/
	perl generator.pl --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) \
	--uploadfolder $(UPLOADFOLDER) --deployfolder $(DEPLOYFOLDER) \
	--in vorlagen/adm/ci.tmpl/application/config/database.php \
	--out adm/ci/application/config/database.php
	perl generator.pl --cibaseurl $(CIBASEURL) \
	--in vorlagen/adm/ci.tmpl/application/config/config.php \
	--out adm/ci/application/config/config.php
	touch adm/ci

#Die zip-Dateien in vendor/ fallen vom Himmel

vendor/CodeIgniter-3.1.9: vendor/CodeIgniter-3.1.9.zip
	cd vendor; unzip -x CodeIgniter-3.1.9.zip
	touch $@
vendor/grocery-crud-1.6.3: vendor/grocery-crud-1.6.3.zip
	cd vendor; unzip -x grocery-crud-1.6.3.zip
	touch $@

createdb.sql:	config.json generator.pl $(DEPLOY_CFG) Makefile
	perl generator.pl  --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) $(DBGRANTS) --gen db

adm:
	mkdir adm

.htpasswd: passworte config.json generator.pl
	perl generator.pl --gen htuser

# generische Regel für die PHP-Skripte im adm-Ordner
adm/%.php: vorlagen/%.php adm config.json generator.pl $(DEPLOY_CFG)
	perl generator.pl --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) \
	--uploadfolder $(UPLOADFOLDER) --deployfolder $(DEPLOYFOLDER) --in $< --out $@

%.php: vorlagen/%.php config.json generator.pl $(DEPLOY_CFG)
	perl generator.pl --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) \
	 --uploadfolder $(UPLOADFOLDER) --deployfolder $(DEPLOYFOLDER) --in $< --out $@

adm/.htaccess: adm config.json generator.pl vorlagen/.htaccess-adm
	perl generator.pl --htpasswd $(HTPASSWD) --in vorlagen/.htaccess-adm --out adm/.htaccess

adm/fpdf.php: 3rdparty
	cd adm; ln -fs ../3rdparty/fpdf.php .

.htaccess: adm config.json generator.pl vorlagen/.htaccess-main
	perl generator.pl --in vorlagen/.htaccess-main --out .htaccess

%.css: vorlagen/%.css generator.pl
	perl generator.pl --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) \
	--uploadfolder $(UPLOADFOLDER) --deployfolder $(DEPLOYFOLDER) --in $< --out $@

images/logo.svg: vorlagen/LNM_Logo_2018_trace.svg
	mkdir -p images
	cp $< $@

serienergebnisse.html: vorlagen/serienergebnisse.html config.json $(DEPLOY_CFG)
	perl generator.pl --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) --in $< --out $@

install:	3rdparty $(GENERATED)
	rsync --delete --recursive --links --verbose --include=".htaccess" --include ".htpasswd" \
	--exclude=".*" --exclude="*~" --exclude="vorlagen" --exclude "dblogin_*.mk" \
	 -av . $(DEPLOYTO)/.

pinstall: all
	rm -rf nml-auswertung; mkdir -p nml-auswertung/adm; mkdir -p nml-auswertung/images
	for i in $(GENERATED); do cp $$i nml-auswertung/$$i ; done
	cp 3rdparty/jquery* nml-auswertung/jquery.min.js
	mkdir -p nml-auswertung/3rdparty/font
	cp -p 3rdparty/fpdf181/fpdf* nml-auswertung/3rdparty/
	cp -rp 3rdparty/fpdf181/font nml-auswertung/3rdparty/
	cp $(URKUNDE) adm/Urkunde.jpg
	cp -rp adm nml-auswertung/
	cp .htaccess nml-auswertung/
	cp images/background.png nml-auswertung/images
	mkdir nml-auswertung/nml-urkunden
	rsync --delete --recursive --links --verbose --include=".htaccess" --include ".htpasswd" \
	--exclude=".*" --exclude="*~" --exclude="vorlagen" --exclude "dblogin*.mk" --exclude "passworte" \
	 -av nml-auswertung/ $(DEPLOYTO)/

clean:
	rm -rf $(GENERATED) adm image

veryclean:
	rm -rf 3rdparty
