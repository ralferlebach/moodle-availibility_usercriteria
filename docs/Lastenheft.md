# Lastenheft – availability_usercriteria (AUC)

**Stand:** 24.10.2026 · **Status:** Konzept / zur Abstimmung · **Auftraggeberperspektive**

## 1. Ausgangslage und Ziel
Moodle bietet Restrict-access-Bedingungen für einzelne Nutzer- und Kursmerkmale. Die gewünschten typisierten Vergleiche, aggregierten Lernmetriken und relativen Kriterien sind damit nicht durchgängig abgedeckt. Ziel ist **ein kleines Availability-Plugin** `availability_usercriteria` (AUC), das Lehrenden die Konfiguration nutzerbezogener Voraussetzungen für Moodle-Aktivitäten und gegebenenfalls Kursabschnitte ermöglicht. AUC ist **kein Reporting-, Statistik- oder Einschreibeplugin**.

## 2. Zielgruppen und Nutzung
Lehrende mit Berechtigung zur Bearbeitung von Voraussetzungen konfigurieren in der vorhandenen Moodle-Oberfläche eine Bedingung aus Merkmal, Operator und Wert. Moodle prüft für die jeweilige Person beim Zugriff, ob sie erfüllt ist. Bestehende Moodle-UND-/ODER-Gruppen und Sichtbarkeitseinstellungen bleiben maßgeblich.

## 3. Fachliche Anforderungen

| ID | Anforderung | Priorität |
|---|---|---|
| L-01 | Einzelner Nutzer: ID, Nutzername, Vorname, Nachname; gleich/ungleich, bei numerischer ID auch >, >=, <, <=; bei Text LIKE/enthält | Muss |
| L-02 | Standard- und benutzerdefinierte Profilfelder: feldtypgerechte Operatoren, einschließlich numerischer Vergleiche, sofern der Feldtyp das unterstützt | Muss |
| L-03 | Kurs- und Systemrolle als Bedingung | Muss |
| L-04 | Einschreibedatum vor/nach/zwischen; klar benannter Zeitbezug | Muss |
| L-05 | Einschreibemethode, auch bei mehreren Einschreibungen | Muss |
| L-06 | Zeit seit letztem Site-Login und seit letzter nachweisbarer Kursaktivität getrennt | Muss |
| L-07 | Anzahl abgeschlossener Kursaktivitäten | Muss |
| L-08 | Aktuelle Kursnote mit =, !=, >, >=, <, <=, zwischen; explizite Behandlung fehlender Noten | Muss |
| L-09 | Nutzungsdauer im Kurs mit kenntlich gemachter Schätzmethodik | Soll |
| L-10 | Dauer vom definierten Kursstart bis zum Kursabschluss | Soll |
| L-11 | Relative Kriterien (Maximum/Minimum, höchste/niedrigste N, Perzentile) | Kann, zweite Ausbaustufe |
| L-12 | Alle Bedingungen über die Moodle-Restrict-access-Oberfläche, kombinierbar mit Core-Bedingungen | Muss |
| L-13 | Mehrsprachigkeit DE/EN und barrierearme Bedienung | Muss |
| L-14 | Keine unnötige dauerhafte Kopie von Nutzerdaten | Muss |

## 4. Qualitätsanforderungen
- **Korrektheit:** Keine Freigabe bei unklaren oder fehlenden sicherheitsrelevanten Daten; gesonderter Operator „nicht vorhanden“ kann gezielt verwendet werden.
- **Performance:** Keine vollständigen Logscans und keine kursweiten Rangberechnungen pro Aktivität und Nutzer beim Rendern einer Kursseite.
- **Sicherheit:** Auswertung ausschließlich serverseitig; Konfiguration und Einsicht nach Moodle-Capabilities und Kontext.
- **Kompatibilität:** Moodle 4.5 LTS bis 5.3 als Prüfziel, keine ungeprüfte Kompatibilitätsbehauptung; MariaDB und PostgreSQL.
- **Datenschutz:** Datenminimierung, transparente Metrikdefinitionen, Privacy-API-Beurteilung.
- **Wartbarkeit:** Moodle Coding Standards, automatisierte Tests, keine unnötige eigene Infrastruktur.

## 5. Abgrenzung
Nicht enthalten: eigenständige Dashboards, Nutzerranglisten für Lehrende, automatisches Enrolment, Benachrichtigungen, prädiktive Lernanalyse, Live-Zeittracking oder Änderungen am Moodle-Core. Externe Plugins wie `block_dedication` können optional Daten liefern; keine verpflichtende Abhängigkeit in V1.

## 6. Fachlich zu entscheidende Punkte
1. Zählen bei Aktivitätsabschlüssen alle abschließbaren Aktivitäten oder nur sichtbare bzw. ausgewählte Typen?
2. Was gilt als Kursstart: Einschreibebeginn, erster Kurszugriff oder konfigurierter Zeitpunkt?
3. Welche Bewertung ist maßgeblich: normalisierte Kursgesamtbewertung (Vorschlag) oder Rohpunkte?
4. Welche Referenzpopulation gilt für relative Vergleiche, und wie werden Gleichstände behandelt?
5. Soll „letzter Login“ Site-Login und Kurszugriff als getrennte auswählbare Merkmale zeigen (Vorschlag: ja)?

## 7. Abnahmekriterien
AUC erscheint im Restrict-access-Dialog, speichert und lädt Regeln verlustfrei, prüft die Bedingungen im jeweiligen Kurskontext reproduzierbar, behandelt fehlende Daten und Mehrfacheinschreibungen definiert und erfüllt automatisierte Funktionstests sowie Berechtigungs-, Upgrade- und Performanceprüfungen für unterstützte Moodle-Versionen.
