# environment-setup.md

## Setup der Test- & Laufzeitumgebung für availability_usercriteria (AUC)

Anleitung, um die Verifikationsumgebung von Grund auf aufzusetzen: Code-Style (phpcs/moodle-cs),
Moodle 4.5 und 5.3, PHP, PostgreSQL, PHPUnit und ein Browser-Test des Editors. Geschrieben für die
Arbeit im Container. Übernommen aus dem ADELE-Dokument (`environment-setup.md` im plugintemplate) und
für AUC angepasst.

> **Konvention:** QUELLE = `/home/claude/work/moodle-availability_usercriteria` (Arbeitskopie, wird
> ausgeliefert). SPIEGEL = Wegwerf-Kopien im Moodle-Baum, gegen die getestet wird
> (`/home/claude/moodle405/availability/condition/usercriteria`,
> `/home/claude/moodle503/public/availability/condition/usercriteria`). **Nie im Spiegel
> entwickeln** – er wird bei jedem Testlauf überschrieben. `tools/mirror_and_test.sh` macht das.

> **Verifikationsstand:** Alles in diesem Dokument wurde am 2026-10-24/25 in Session 001 im Container
> ausgeführt; die genannten Ergebnisse sind die tatsächlichen. Abweichungen gegenüber dem
> ADELE-Dokument sind mit **(neu)** markiert.

---

## 0. Zielzustand (verifiziert)

| Komponente | Moodle 4.5 | Moodle 5.3 |
|---|---|---|
| Moodle | `MOODLE_405_STABLE` (4.5.15+) | `MOODLE_503_STABLE` (5.3+, Build 20261021) |
| Pfad | `/home/claude/moodle405` | `/home/claude/moodle503` (Code unter `public/`) |
| PHP | 8.3.6 | 8.3.6 (Moodle 5.3 verlangt ≥ 8.3) |
| Datenbank | PostgreSQL 16.15, Port 5432 | **PostgreSQL 17.11**, Port 5433 (5.3 verlangt PG ≥ 17 bzw. MariaDB ≥ 11.4) |
| DB / Rolle | `moodle405` / `moodle` | `moodle503` / `moodle` |
| PHPUnit | 9.6.34 | 11.5.56 |
| Plugin-Pfad | `availability/condition/usercriteria` | `public/availability/condition/usercriteria` |
| Testsuite | `availability_usercriteria_testsuite` | `availability_usercriteria_testsuite` |
| phpcs / moodle-cs | `/tmp/moodlecs`, `moodlehq/moodle-cs ^3.7` | dasselbe |

MariaDB: im Container nur 10.11.14 verfügbar, damit läuft Moodle 4.5, aber **nicht** 5.3. Der
MariaDB-Test auf 5.3 fehlt daher hier (die CI-Matrix in `.github/workflows/moodle-plugin-ci-main.yml` sieht ihn vor, er ist noch nicht gelaufen).

---

## 1. Systempakete

```bash
apt-get update -qq
apt-get install -y -qq php8.3-cli php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip composer \
    php8.3-pgsql php8.3-gd php8.3-intl php8.3-soap postgresql postgresql-client git unzip locales
```

**(neu) Zwei Einstellungen, ohne die `phpunit init` abbricht:**

```bash
# 1. Moodle verlangt max_input_vars >= 5000. "php -d max_input_vars=5000" reicht NICHT: init.php startet
#    Unterprozesse ohne das Flag. Also dauerhaft in die CLI-Konfiguration:
echo "max_input_vars=5000" > /etc/php/8.3/cli/conf.d/99-moodle.ini

# 2. PHPUnit verlangt die Locale en_AU.UTF-8:
echo "en_AU.UTF-8 UTF-8" >> /etc/locale.gen && locale-gen
```

---

## 2. Code-Style: phpcs / moodle-cs

```bash
mkdir -p /tmp/moodlecs && cd /tmp/moodlecs
composer require --dev --no-interaction "moodlehq/moodle-cs:^3.7"
P="$(pwd)/vendor/moodlehq/moodle-cs/moodle"
for d in Universal Modernize NormalizedArrays; do P="$P,$(pwd)/vendor/phpcsstandards/phpcsextra/$d"; done
./vendor/bin/phpcs --config-set installed_paths "$P"
./vendor/bin/phpcs -i     # erwartet: ... moodle, Universal, Modernize and NormalizedArrays
```

`Referenced sniff "Universal..." does not exist` ist **kein Codeproblem**, sondern fehlende
`installed_paths`.

Prüfen – **zweimal**: standalone und im Moodle-Baum. `moodle.Files.LangFilesOrdering` und
`moodle.PHPUnit.TestCaseCovers` schweigen, wenn ein Verzeichnis außerhalb eines Moodle-Baums geprüft
wird; „lokal grün“ bedeutet dann nichts.

```bash
cd /home/claude/work/moodle-availability_usercriteria
/tmp/moodlecs/vendor/bin/phpcs --standard=moodle --severity=1 --extensions=php \
    --ignore=*/yui/build/*,*/tools/* --no-cache --report=summary .
# im Baum, nach dem Spiegeln:
cd /home/claude/moodle405 && /tmp/moodlecs/vendor/bin/phpcs --standard=moodle --severity=1 \
    --extensions=php --ignore=*/yui/build/*,*/tools/* --no-cache --report=summary \
    availability/condition/usercriteria
```

Erwartung: leere Ausgabe, Exit 0. **JS nicht mit phpcs prüfen** (`--extensions=php`), dafür ist
ESLint zuständig. Auto-Fix: `phpcbf` mit denselben Optionen.

---

## 3. PostgreSQL

```bash
pg_ctlcluster 16 main start          # nach jedem Container-Neustart nötig
su postgres -c "psql -c \"CREATE ROLE moodle LOGIN PASSWORD 'moodle' CREATEDB;\""
su postgres -c "psql -c \"CREATE DATABASE moodle405 OWNER moodle;\""
```

**(neu) PostgreSQL 17 für Moodle 5.3** (Ubuntu 24.04 liefert nur 16; das offizielle PGDG-Repository ist
erreichbar):

```bash
curl -fsS https://www.postgresql.org/media/keys/ACCC4CF8.asc -o /tmp/pgdg.asc
mkdir -p /usr/share/postgresql-common/pgdg && cp /tmp/pgdg.asc /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc
echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt noble-pgdg main" \
    > /etc/apt/sources.list.d/pgdg.list
apt-get update -qq && apt-get install -y -qq postgresql-17
pg_ctlcluster 17 main start          # Port 5433 (16 belegt 5432)
su postgres -c "psql -p 5433 -c \"CREATE ROLE moodle LOGIN PASSWORD 'moodle' CREATEDB;\""
su postgres -c "psql -p 5433 -c \"CREATE DATABASE moodle503 OWNER moodle;\""
```

---

## 4. Moodle beziehen und konfigurieren

```bash
cd /home/claude
git clone --branch MOODLE_405_STABLE --depth 1 https://github.com/moodle/moodle.git moodle405
git clone --branch MOODLE_503_STABLE --depth 1 https://github.com/moodle/moodle.git moodle503
mkdir -p /home/claude/moodledata405 /home/claude/moodledata405_phpu \
         /home/claude/moodledata503 /home/claude/moodledata503_phpu
(cd moodle405 && composer install --no-interaction --prefer-dist)
(cd moodle503 && composer install --no-interaction --prefer-dist)
```

**(neu) Seit Moodle 5.1 liegt der Code unter `public/`.** `config.php` bleibt im Wurzelverzeichnis,
Plugins und CLI-Skripte liegen unter `public/` (`public/admin/tool/phpunit/cli/init.php`).

`config.php` (4.5; für 5.3 `dbname=moodle503`, `dbport=5433`, `dataroot`/`phpunit_dataroot` auf `503`):

```php
<?php
unset($CFG); global $CFG; $CFG = new stdClass();
$CFG->dbtype='pgsql'; $CFG->dblibrary='native';
$CFG->dbhost='localhost'; $CFG->dbname='moodle405';
$CFG->dbuser='moodle'; $CFG->dbpass='moodle'; $CFG->prefix='mdl_';
$CFG->dboptions=['dbpersist'=>0,'dbport'=>5432,'dbsocket'=>''];
$CFG->wwwroot='http://localhost:8080';
$CFG->dataroot='/home/claude/moodledata405';
$CFG->admin='admin';
$CFG->directorypermissions=0777;
$CFG->phpunit_prefix='phpu_';
$CFG->phpunit_dataroot='/home/claude/moodledata405_phpu';
require_once(__DIR__.'/lib/setup.php');
```

---

## 5. Plugin spiegeln und PHPUnit

```bash
tools/mirror_and_test.sh          # beide Bäume
tools/mirror_and_test.sh 405      # nur 4.5
```

Das Skript spiegelt, initialisiert PHPUnit neu (`init.php`) und führt die Testsuite aus. Von Hand:

```bash
cd /home/claude/moodle405
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite availability_usercriteria_testsuite
# 5.3:
cd /home/claude/moodle503
php public/admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite availability_usercriteria_testsuite
```

**(neu) `vendor/bin/phpunit <Verzeichnis>` führt nichts aus** („No tests executed!“). Immer
`--testsuite availability_usercriteria_testsuite` verwenden.

Verifiziertes Ergebnis (Stand Session 001): 4.5 `OK (65 tests, 219 assertions)`; 5.3 65 Tests, 219
Assertions, grün, dazu 4 PHPUnit-11-Deprecations (`@dataProvider`-Annotationen; PHPUnit 9 auf 4.5
kennt noch keine Attribute, daher bleiben die Annotationen).

---

## 6. Editor im Browser testen (neu)

Der YUI-Editor lässt sich nicht per PHPUnit prüfen. Verifiziert ist ein Lauf mit dem PHP-Entwicklungsserver
und Playwright/Chromium:

```bash
cd /home/claude/moodle405
php admin/cli/install_database.php --agree-license --fullname="AUC Dev" --shortname=aucdev \
    --adminpass='Admin123!' --adminemail=admin@example.com
php admin/cli/cfg.php --name=enableavailability --set=1
php -S 127.0.0.1:8080 -t /home/claude/moodle405 &     # in dem Schritt starten, der ihn braucht

mkdir -p /tmp/pw && cd /tmp/pw && npm init -y && npm i playwright
PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers npx playwright install chromium
```

Stolpersteine: Der Bereich „Restrict access“ ist eingeklappt –
`page.getByRole('button', { name: /Restrict access/ })` klicken. Der Dialog ist kein `.modal-dialog`;
`button:text-is("User criteria")` verwenden. Der erste Seitenaufruf ist langsam (Cache-Aufbau, Single-Thread-Server),
großzügige Timeouts setzen. Nach Änderungen an `yui/build/` einmal `php admin/cli/purge_caches.php`.

---

## 7. YUI-Build (neu)

Offiziell: `npx grunt yui` im Moodle-Wurzelverzeichnis (Shifter). **In diesem Container scheitert
`npm ci` mit HTTP 403**, weil Moodle Shifter als GitHub-Tarball einbindet, den der npm-Zugang sperrt.
Ersatz: `tools/build_yui.py` erzeugt dieselben drei Dateien (`.js`, `-debug.js`, `-min.js`) mit
demselben `YUI.add(...)`-Wrapper; Minifizierung mit `terser` (`npm i -g terser`), sonst ist `-min`
unminifiziert (gültig, nur größer). Vor einem Release einmal mit echtem Grunt nachbauen und diffen,
`moodle-plugin-ci grunt` prüft die Konsistenz in der CI.

---

## 8. Arbeitsstände holen und das Template

- Template: `git clone https://github.com/ralferlebach/moodle-plugintemplate`. `init_plugin.py` kannte
  den Typ `availability` nicht; er wurde ergänzt (`availability/condition/{name}`). Dieser Eintrag gehört
  ins Template zurück.
- Nach `init_plugin.py` heißen Sprachdateien `lang/<code>/<name>.php`; Moodle erwartet für Nicht-`mod`-Plugins
  `lang/<code>/<component>.php` (hier `availability_usercriteria.php`). Umbenennen.
- Das Template legt `db/access.php`, ein Beispiel-Capability und Load-/Playwright-Gerüst an. AUC braucht
  keine eigene Capability; entfernt wurden `db/access.php`, `tests/load/`, `tests/playwright/` und die
  zugehörigen Workflows.
- `.gitattributes` setzt `export-ignore` für `.github/`, `docs/sessions/`, `docs/prompt-templates/`, `tools/`,
  `makefile` (seit 0.3.0 wird `docs/` sonst mitgeliefert, weil die README darauf verlinkt). Ein GitHub-Archiv
  enthält diese Pfade nicht; im Repository sind sie vorhanden. Aus ihrem Fehlen im entpackten Archiv nie auf ein
  Fehlen im Repo schließen.
- Der Container hat nur lesenden Zugriff auf `ralferlebach/moodle-plugintemplate`; Pushen ist von hier
  nicht vorgesehen (Ablage: nur lokal).

---

## 8a. Testmatrix, MariaDB und Behat (neu, 0.3.0)

**Weitere Moodle-Bäume** (5.0, 5.1, 5.2) wie 4.5/5.3 klonen (`git clone --depth 1 -b MOODLE_50x_STABLE`) und
`composer install`. Composer installiert hier aus Quellen (Git-Klone im `vendor/`); danach
`find vendor -mindepth 3 -maxdepth 3 -name .git -prune -exec rm -rf {} +` und den Composer-Cache
(`~/.cache/composer`) löschen, sonst laufen 8 GB voll. Ab 5.1 liegt der Code unter `public/`.

**MariaDB 10.11** (Ubuntu-Paket, `service mariadb start`), Nutzer `moodle`/`moodle`, je Baum eine
Testdatenbank `mphpu<version>` (utf8mb4_unicode_ci). In jedem `config.php` schaltet `AUC_DB=mariadb` PHPUnit
über `$CFG->phpunit_dbtype` usw. auf MariaDB um. Moodle 5.3 verlangt **MariaDB 11.4**: die Pakete
`mariadb-server-core`, `mariadb-client-core`, `mariadb-common` aus dem MariaDB-Repository
(`https://dlm.mariadb.com/repo/mariadb-server/11.4/repo/ubuntu noble main`, Schlüssel
`https://mariadb.org/mariadb_release_signing_key.pgp`) mit einer eigenen apt-Konfiguration herunterladen, mit
`dpkg -x` nach `/opt/mdb114/root` entpacken (neben 10.11, nicht installiert), `libaio1t64` nachinstallieren,
`mariadb-install-db --no-defaults --datadir=/opt/mdb114/data` und `mariadbd --no-defaults --port=3307
--socket=/opt/mdb114/run/mysqld.sock` starten. Der 5.3-Baum nutzt diese Instanz (Port 3307).

**Matrix:** `tools/run_matrix.sh [Berichtsordner]` führt 4.5–5.3 auf PostgreSQL und 4.5–5.2 auf MariaDB aus,
schreibt JUnit-XML, Logs und `report.md` (inkl. Performance aus `tests/performance_test.php`). Ein
abgebrochener PHPUnit-Init hinterlässt halbe `phpu_`-Tabellen („Can not use database for testing“): Datenbank
neu anlegen.

**Behat:** Apache mit mod_php (`apt-get install apache2 libapache2-mod-php8.3`, Nutzer `www-data`,
`max_input_vars` auch in `/etc/php/8.3/apache2/conf.d`), je Baum ein Port (8405 → `moodle405`,
8503 → `moodle503/public`). ChromeDriver passend zur vorhandenen Chromium-Version aus dem PyPI-Paket
`chromedriver-py` (hier 156, `pip install --target /opt/cdpy chromedriver-py==156.0.8078.47`), gestartet mit
`--port=4444`. In `config.php`: `behat_wwwroot`, `behat_prefix = 'bht_'`, `behat_dataroot` und ein Profil
`headlesschrome` mit `binary` auf das Playwright-Chromium und `--headless=new --no-sandbox`. Dann
`php admin/tool/behat/cli/init.php` (5.x: `public/admin/...`), `chmod -R a+rwX` auf das Behat-Datenverzeichnis und
`vendor/bin/behat --config <behat_dataroot>/behatrun/behat/behat.yml --profile=headlesschrome --tags=@availability_usercriteria`.

**Weitere Gates lokal:** `node tests/js/form_tz_test.js`, `python3 tools/build_yui.py --check`,
`python3 tools/check_links.py <entpacktes Release>`.

---

## 9. Typische Stolpersteine

- **`No tests executed!`** → `--testsuite availability_usercriteria_testsuite` statt Verzeichnis.
- **`max_input_vars`** / **`en_AU.UTF-8`** → §1.
- **DB down** nach Container-Neustart → `pg_ctlcluster 16 main start` und `17 main start`.
- **Moodle 5.3: `version 17 is required`** → PostgreSQL 17 (§3). MariaDB 10.11 genügt nicht (5.3 verlangt 11.4).
- **PHPUnit nicht reinitialisiert** nach dem Spiegeln → veraltete Registrierung.
- **Request-Cache in Tests:** Änderungen über Moodle-APIs leeren den Cache über Ereignisse. Schreibt ein Test
  direkt in die Datenbank, vorher `reader::reset()` (bzw. `catalog::reset()` für Profilfelder) aufrufen;
  `cache_helper::purge_all()` leert den statischen Request-Cache nicht.
- **Kursgesamtbewertung `needsupdate=1`**: direkt gesetzte Kursnoten (`update_final_grade` auf dem Kurs-Item)
  lassen das Item auf „Regrade nötig“ stehen; AUC wertet das bewusst als nicht entscheidbar. Tests erzeugen
  eine manuelle Bewertungsaufgabe und rufen `grade_regrade_final_grades()` auf.
- **Test-Klassen:** `grade_item` braucht `require_once($CFG->libdir . '/gradelib.php')`; ohne lief der Test
  auf 4.5, brach aber auf 5.3 mit „Class grade_item not found“ ab.
- **Docblock-Falle**: neue Methode vor einer privaten Methode eingefügt → verwaister Docblock → phpcs
  „Missing docblock“. Nach Einfügungen phpcs laufen lassen.
