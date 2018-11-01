
#DEPLOY_CFG = dblogin_details_lokal.mk
#DEPLOY_CFG = dblogin_details_test.mk
DEPLOY_CFG = dblogin_details.mk

include $(DEPLOY_CFG)

ifndef DEPLOYTO
$(error DEPLOYTO ist nicht gesetzt, dblogin_details.mk prüfen!)
endif

GENERATED = createdb.sql adm/ergebnissehochladen.php adm/ergebnis.php \
 adm/csvimport.php adm/serienwertung.php adm/werteAus.php adm/uebersicht.php \
 adm/.htaccess .htaccess .htpasswd lnm-style.css lnm-style-kurz.css serienergebnisse.html serienergebnisausgabe.php \
 images/logo.svg

all:	3rdparty $(GENERATED)

3rdparty:
	mkdir -p 3rdparty/ajaxcrud
	cd 3rdparty; wget -O ajaxcrud.zip http://www.ajaxcrud.com/getFile.php; cd ajaxcrud; unzip -x ../ajaxcrud.zip ; rm -rf examples
	cd 3rdparty; wget http://fpdf.de/downloads/fpdf181.tgz; tar xzvf fpdf181.tgz
	cd 3rdparty; wget https://code.jquery.com/jquery-3.3.1.min.js

createdb.sql:	config.json generator.pl $(DEPLOY_CFG) Makefile
	perl generator.pl  --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) $(DBGRANTS) --gen db

adm:
	mkdir adm

.htpasswd: passworte config.json generator.pl
	perl generator.pl --gen htuser

# generische Regel für die PHP-Skripte im adm-Ordner
adm/%.php: vorlagen/%.php adm config.json generator.pl $(DEPLOY_CFG)
	perl generator.pl --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) --uploadfolder $(UPLOADFOLDER) --in $< --out $@

%.php: vorlagen/%.php config.json generator.pl $(DEPLOY_CFG)
	perl generator.pl --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) --uploadfolder $(UPLOADFOLDER) --in $< --out $@

adm/.htaccess: adm config.json generator.pl vorlagen/.htaccess-adm
	perl generator.pl --htpasswd $(HTPASSWD) --in vorlagen/.htaccess-adm --out adm/.htaccess

.htaccess: adm config.json generator.pl vorlagen/.htaccess-main
	perl generator.pl --in vorlagen/.htaccess-main --out .htaccess

%.css: vorlagen/%.css
	cp $< $@

images/logo.svg: vorlagen/LNM_Logo_2018_trace.svg
	mkdir -p images
	cp $< $@

serienergebnisse.html: vorlagen/serienergebnisse.html config.json $(DEPLOY_CFG)
	perl generator.pl --dbname $(DBNAME) --dbuser $(DBUSER) --dbpasswd $(DBPASSWD) --in $< --out $@

install:	3rdparty $(GENERATED)
	rsync --delete --recursive --links --verbose --include=".htaccess" --include ".htpasswd" \
	--exclude=".*" --exclude="*~" --exclude="vorlagen" --exclude "dblogin_*.mk" \
	 -av . $(DEPLOYTO)/.

pinstall: 3rdparty $(GENERATED)
	rm -rf nml-auswertung; mkdir -p nml-auswertung/adm; mkdir -p nml-auswertung/images
	for i in $(GENERATED); do cp $$i nml-auswertung/$$i ; done
	cp 3rdparty/jquery* nml-auswertung/jquery.min.js
	cp -rp adm nml-auswertung/
	cp .htaccess nml-auswertung/
	rsync --delete --recursive --links --verbose --include=".htaccess" --include ".htpasswd" \
	--exclude=".*" --exclude="*~" --exclude="vorlagen" --exclude "dblogin*.mk" --exclude "passworte" \
	 -av nml-auswertung/ $(DEPLOYTO)/

clean:
	rm -rf $(GENERATED) adm image

veryclean:
	rm -rf 3rdparty
