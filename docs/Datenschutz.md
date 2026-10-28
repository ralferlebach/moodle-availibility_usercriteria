# Datenschutz – availability_usercriteria (AUC)

**Stand:** 0.4.0 (28.10.2026). Technische Beschreibung, keine Rechtsberatung. Nachweis: `tests/privacy_test.php`,
`tests/permissions_test.php`, `tests/usage_test.php`, `tests/relative_test.php`.

## Was gespeichert wird (neu in 0.4.0)

| Tabelle | Inhalt | Personenbezug | Zweck |
|---|---|---|---|
| `availability_usercriteria_use` | je Person und Kurs: geschätzte Nutzungsdauer (Sekunden), Anzahl Sitzungen, erster und letzter verarbeiteter Log-Zeitpunkt | **ja** | Merkmale „Nutzungsdauer“ (L-09) und „Dauer bis Kursabschluss“ (L-10) ohne Logscans beim Seitenaufruf |
| `availability_usercriteria_dist` | je Kurs und Merkmal: sortierte Liste der Werte der Teilnehmenden, Anzahl, Berechnungszeitpunkt | nein (keine Nutzer-IDs) | relative Kriterien (L-11) |

- Die Nutzungsdaten sind ein Aggregat dessen, was das Standard-Log ohnehin enthält; Rohereignisse werden nicht
  kopiert. Gelöschte Personen und Kurse werden über Ereignisse sofort entfernt; Ereignisse gelöschter Kurse oder
  Personen werden bei der Verarbeitung übersprungen.
- Die Vergleichslisten enthalten keine Nutzer-IDs. In sehr kleinen Kursen kann ein einzelner Wert dennoch einer
  Person zuordenbar sein; die Liste wird aber nie angezeigt, nur zur Ja/Nein-Entscheidung verwendet.
- Was beim Seitenaufruf gelesen wird, liegt nur im Request-Cache und verfällt mit der Anfrage.
- Nach außen gibt AUC nur „erfüllt / nicht erfüllt“ an Moodle zurück.

## Privacy API

- `classes/privacy/provider.php` ist ein vollständiger Provider (Metadaten, `core_userlist_provider`,
  Plugin-Provider) für die Tabelle `availability_usercriteria_use` im Kurskontext:
  Export (Nutzungsdauer in Stunden, Sitzungen, Zeitpunkte), Löschung je Person, je Personenliste und für den
  ganzen Kurs. Geprüft in `privacy_test::test_export_and_delete`; `component_is_compliant` ist wahr.
- Die Vergleichslisten sind ohne Personenbezug und erscheinen deshalb nicht in den Metadaten.

## Regeln als Kurskonfiguration

- Eine Regel ist Kurskonfiguration (Feld `availability` von Aktivität oder Abschnitt), nicht Teil der AUC-Daten.
- Eine Regel **kann identifizierende Werte enthalten**, wenn Lehrende sie wählen (Nutzer-ID, Nutzername, Name,
  E-Mail, Telefon, Adresse). Sie wird in Kurssicherungen mitgesichert; beim Wiederherstellen werden Nutzer-IDs
  (bei „gleich/ungleich“) und Rollen-IDs auf die Zielseite umgeschrieben (`restore_test`).
- Wird eine darin genannte Person gelöscht, bleibt die Regel unverändert (`test_person_named_in_a_rule`), wie
  bei Moodles Profilbedingung (`availability_profile`).

## Keine Offenlegung

| Weg | Maßnahme | Nachweis |
|---|---|---|
| Hinweistext für Teilnehmende | Identitätsmerkmale ohne Wert, sonst nur Schwellenwerte; nie der eigene oder fremde Werte, nie Ränge | `test_restriction_text_shows_no_values_of_people`, Behat |
| Diagnosehinweis für Lehrende | nennt nur Gründe der Seite/des Kurses (z. B. Log aus), nie Personen | `relative_test::test_population`, `usage_test` |
| Debug-Ausgabe | Identitätsoperanden maskiert | `test_operands_of_people_are_not_disclosed` |
| Fehlermeldungen fehlerhafter Regeln | enthalten keine Werte | `test_error_messages_contain_no_values` |
| Editor | nur Definitionen, keine Personendaten | `test_editor_reveals_no_data_of_people` |

## Empfehlung für den Betrieb

Die Nutzungsdauer ist eine Schätzung und für Lehrende nicht einsehbar; sie wirkt nur als Zugangsbedingung.
Vor dem Produktiveinsatz sollte die Datenschutzstelle der Einrichtung diese Beschreibung und die Speicherung der
Nutzungsdaten bewerten; das ist offen.
