# Timeminator Community v1.0.1

**Release-Datum:** 2026-11-27

Patch-Release. Behebt die Docker-Persistenz, damit das Versprechen „ein
Container" wirklich hält.

## Fix: config.php bleibt im Daten-Volume erhalten

Der Installer schrieb `config.php` bisher ins Web-Wurzelverzeichnis – also
**außerhalb** des Volumes, das Docker unter `/var/www/html/data` einhängt. Wurde
der Container neu erstellt (oder zum Aktualisieren ein neues Image-Tag gezogen),
ging `config.php` verloren, während die SQLite-Datenbank und die Einstellungen im
Volume erhalten blieben. Der Installer erschien dann erneut, als wäre nichts
installiert.

Ab 1.0.1 schreibt der Installer `config.php` nach `data/`, sobald dieses
Verzeichnis beschreibbar ist (durch `data/.htaccess` vor Web-Zugriff geschützt).
Damit übersteht die Konfiguration das Neuerstellen des Containers und
Image-Updates. Ist `data/` nicht beschreibbar, wird wie bisher ins
Web-Wurzelverzeichnis geschrieben – klassische Shared-Hosting-Installationen
ändern sich also nicht. **Bestehende Installationen bleiben unberührt**: eine
vorhandene `config.php` im Web-Wurzelverzeichnis wird weiterhin zuerst gelesen.

## Update

- **Docker:** ein neues Image-Tag ziehen und den Container neu erstellen –
  Datenbank, Einstellungen und ab jetzt auch `config.php` bleiben im Volume
  `tm_data` erhalten.

  ```
  docker pull freshnfunky/timeminator-community:latest
  ```

- **Shared Hosting / PHP:** Die mitgelieferte Update-Funktion unter
  *Administration → System* ersetzt die Anwendungsdateien und lässt `config.php`
  und `data/` unangetastet.

Quellcode & Releases: https://github.com/freshNfunky/Timeminator-Community
Website: https://timeminator.com
Lizenz: MIT (Community Edition)
