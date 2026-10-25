# Technischer Blueprint – availability_usercriteria (AUC)

**Stand:** 24.10.2026 · **Status:** Architekturvorschlag, kein Implementierungscode

## 1. Architekturentscheidung
**Ein einziges Availability-Condition-Plugin**, keine neue Statistikplattform, kein Moodle-Core-Fork. Moodle verwaltet bestehende Restrict-access-Gruppen und ruft die Prüfung im jeweiligen Nutzer- und Kurskontext auf. AUC liefert die konfigurierbare atomare Bedingung.

## 2. Vorgesehene Komponenten
- **Availability-Condition-Klasse:** interpretiert gespeicherte Konfiguration und ermittelt erfüllt/nicht erfüllt im Moodle-Availability-Lifecycle.
- **Frontend-Editor (AMD/JS):** Kategorie/Merkmal/Operator/Wert, Feldtypvalidierung, Serialisierung; barrierearme Beschriftung.
- **Merkmalskatalog:** zentrale Definition zulässiger Merkmale, Datentypen, Operatoren, Kurs-/Site-Bezug und Einheiten.
- **Wertbeschaffung:** kleine, nach Datenquelle getrennte Reader für User/Profile, Roles, Enrolments, Completion, Gradebook und Logs.
- **Vergleichslogik:** typisierte, deterministische Vergleiche; NULL separat; keine freie SQL-Expression.
- **Optionale Metrikadapter:** Dedication-Zeit und spätere relative Aggregationen; bei fehlender Abhängigkeit definierter „nicht verfügbar“-Status.
- **Tests:** PHPUnit und Behat, Last- und Versionsmatrix.

## 3. Datenfluss
1. Lehrperson wählt AUC im vorhandenen Restrict-access-Dialog.
2. Editor speichert ein **versioniertes Konfigurationsobjekt**: Schema-Version, Merkmalsschlüssel, Operator, typisierte Operanden, optionale Einheiten und Scope.
3. Moodle lädt die Bedingung im Kontext einer Aktivität oder eines Abschnitts.
4. AUC validiert Konfiguration, löst den Nutzer-/Kurskontext auf, fragt die benötigte Quelle ab und vergleicht den Wert.
5. AUC liefert das Ergebnis an die Core-Availability-Engine; Core kombiniert es mit anderen Bedingungen.

## 4. Integrationspunkte
- Plugin-Typ: `availability`; Komponente: `availability_usercriteria`.
- Moodle Availability API: Condition-Auswertung, Editor-Integration, sprachabhängige Beschreibung, Speicherung/Wiederherstellung; genaue Signaturen gegen Zielversion prüfen.
- Core-APIs: User/Profile, Role/Context, Enrolment, Completion, Gradebook, Logstore (sofern vorhanden).
- Moodle Cache API nur, wenn Messungen Bedarf und korrekte Invalidierung belegen.
- Privacy API: nach tatsächlicher Datenhaltung.

## 5. Fachliche Sonderfälle (verbindliche Entwurfsentscheidungen für MVP vorgeschlagen)
**Mehrfacheinschreibung:** „Es existiert mindestens eine aktive Einschreibung, die das Kriterium erfüllt.“ Optional später Auswahl `any/all`.

**Rollen:** getrennte Merkmale „direkt im Kurs zugewiesene Rolle“ und „im Kurs wirksame Rolle“; Systemrolle gesondert. Effektive Berechtigungen sind nicht mit Rollennamen gleichzusetzen.

**Kursnote:** aktuelle aggregierte Kursgesamtbewertung auf 0–100 % normalisiert. Unbewertet ist NULL, nicht 0. Bei versteckten Bewertungen keine unbeabsichtigte Offenlegung über Fehltexte.

**Completion:** Anzahl abgeschlossener abschließbarer Kursaktivitäten; exakte Einbeziehung von „bestanden“ und „nicht bestanden“ spezifizieren. Unzugängliche und gelöschte Aktivitäten dürfen nicht unreflektiert zählen.

**Login/Aktivität:** `lastlogin` als Site-Merkmal, letztes Kursereignis als getrenntes Kursmerkmal; Logretention und fehlende Ereignisse berücksichtigen.

**Bearbeitungsdauer:** Beginn und Ende explizit; Vorschlag Beginn = gültiger Einschreibebeginn, Ende = Kursabschlusszeit. Andere Varianten erst nach Nutzerentscheidung.

**Relative Kriterien:** Phase 2; vorab definierte Referenzpopulation, Gleichstandsregel, Stichtag und Aktualität. Keine kursweiten Aggregationen pro Einzelprüfung.

## 6. Risiken und Gegenmaßnahmen
| Risiko | Gegenmaßnahme |
|---|---|
| Availability-Prüfung läuft sehr häufig | Keine Logvollscans; nur gezielte Abfragen und optionale Aggregation |
| Kursdaten werden ohne Berechtigung sichtbar | Nur boolesche Freigabe, keine fremden Rohdaten im Frontend |
| Logdaten fehlen oder sind veraltet | Datenverfügbarkeit erkennen, fail-closed, dokumentierte Semantik |
| Einschreibungen mehrfach/ausgesetzt | Explizite `any-active`-Regel und Tests |
| Moodle 5.x API-Änderungen | Versionierte Tests und Core-API-Prüfung |
| Rangvergleiche teuer | Separater Phase-2-Adapter, vorberechnete Kohortenwerte |
| Bestehende Plugins unterscheiden sich | Kein ungeprüftes Kopieren; Codeaudit als Voraussetzung für Übernahme |

## 7. Nicht-Ziele
Keine eigenen Dashboards, kein Tracking-JavaScript, keine frei formulierten SQL-Bedingungen, keine parallele Nutzerdatenbank, keine Änderung an Moodle-Core oder an anderen Plugins.

## 8. Prüf- und Umsetzungsreihenfolge
1. Referenzplugins und Core Availability API für Zielversionen read-only auditieren.
2. Fachliche Grenzfälle entscheiden und Merkmals-/Operatorenkatalog freigeben.
3. MVP durch beauftragte Entwickler umsetzen.
4. Automatisierte Unit-/Integrations-/Behat-Tests und Performanceprüfungen durchführen.
5. Erst danach optionale Zeit- und relative Metriken ergänzen.

## 9. Abnahme-Gates
- **G1:** Bedienoberfläche speichert/lädt alle zulässigen atomaren Bedingungen.
- **G2:** Alle Muss-Merkmale liefern korrekte Ergebnisse einschließlich NULL-/Grenzfällen.
- **G3:** Kurs- und Systemkontexte sowie Mehrfacheinschreibungen sind korrekt.
- **G4:** Keine N+1- oder ungebremsten Logscans; nachvollziehbare Performance.
- **G5:** Tests nachweislich auf freigegebenen Moodle-/DB-Versionen ausgeführt.

## 10. Offene Entscheidungen
Zählbasis für Completion, Startpunkt der Bearbeitungsdauer, Berechnung der Nutzungsdauer, Berechtigung zur Auswahl sensibler Identitätsmerkmale, Verfügbarkeit externer Logstores, Zeitstempel- und Cache-Aktualität, relative Vergleichspopulation und Gleichstände.

## 11. Umgesetzt in 0.3.0: Gültigkeitsmodell des Caches

Request-Cache `values` (Modus Request) hält je Person, Kurs und Merkmal einen gelesenen Wert; `fields` hält die
Definition der benutzerdefinierten Profilfelder. Nichts überdauert die Anfrage.

| Merkmal | Gelesen | Gültig bis | Frisch innerhalb derselben Anfrage durch |
|---|---|---|---|
| Identität, Standardprofilfelder | einmal je Anfrage | Anfrageende | `user_updated` |
| Benutzerdefinierte Profilfelder (Werte) | einmal je Anfrage | Anfrageende | `user_updated` (vom Profilformular ausgelöst) |
| Profilfeld-Definitionen | einmal je Anfrage | Anfrageende | `user_info_field_created/updated/deleted` |
| Rollen | einmal je Anfrage | Anfrageende | `role_assigned`, `role_unassigned` |
| Einschreibungen (Rohdaten) | einmal je Anfrage | Anfrageende | `user_enrolment_created/updated/deleted`, `enrol_instance_updated/deleted` |
| Einschreibung aktiv? / Beginn | **bei jeder Prüfung** aus den Rohdaten und der Uhr | – | Uhr (`\core\clock`): Beginn/Ende innerhalb der Anfrage wirken sofort |
| Letzter Site-Login | einmal je Anfrage | Anfrageende | `user_loggedin` |
| Letzter Kurszugriff | einmal je Anfrage | Anfrageende | – (Core schreibt ohne Ereignis; der gespeicherte Wert kann den laufenden Besuch schon enthalten) |
| Abschlusszähler | einmal je Anfrage | Anfrageende | `course_module_completion_updated`, `course_module_updated` |
| Kursbewertung | einmal je Anfrage | Anfrageende | `user_graded`, `grade_deleted`, `grade_item_updated/deleted` |

Der Observer (`classes/local/cache_observer.php`, `db/events.php`) verwirft bei jedem dieser Ereignisse den
ganzen Request-Cache. Das ist billig, weil der Cache nur Werte der laufenden Anfrage enthält. Direkte
Datenbankschreibzugriffe ohne Ereignis sieht AUC erst in der nächsten Anfrage; keine Moodle-API schreibt die
gelesenen Daten so. Nachweis: `tests/cache_test.php`, `tests/clock_source_test.php`.

Zeitquelle: ausschließlich `\core\di::get(\core\clock::class)`; erzwungen durch `tests/clock_usage_test.php`
(PHP und Editor-JavaScript, inkl. dynamischer Aufrufe und Negativproben).
