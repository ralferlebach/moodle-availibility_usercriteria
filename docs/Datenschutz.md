# Datenschutz – availability_usercriteria (AUC)

**Stand:** 0.3.0 (25.10.2026). Technische Beschreibung, keine Rechtsberatung. Nachweis: `tests/privacy_test.php`,
`tests/permissions_test.php`.

## Datenverarbeitung

- AUC legt **keine Tabellen** an und speichert **keine Daten über die ausgewerteten Personen**.
- Bei jeder Zugriffsprüfung liest AUC vorhandene Moodle-Daten: Profil, Rollen, Einschreibungen,
  letzter Login, letzter Kurszugriff, Aktivitätsabschlüsse, Kursgesamtbewertung. Gelesene Werte liegen nur im
  Request-Cache (`db/caches.php`, Modus Request) und verfallen am Ende der Anfrage.
- Nach außen gibt AUC nur „erfüllt / nicht erfüllt“ an Moodle zurück.

## Regeln als Kurskonfiguration

- Eine Regel ist Kurskonfiguration. Moodle speichert sie im Feld `availability` der Aktivität
  (`course_modules`) oder des Abschnitts (`course_sections`), nicht AUC.
- Eine Regel **kann identifizierende Werte enthalten**, wenn Lehrende sie wählen (Nutzer-ID, Nutzername, Name,
  E-Mail, Telefon, Adresse). Solche Regeln werden in Kurssicherungen mitgesichert und beim Wiederherstellen
  übernommen.
- Wird eine darin genannte Person gelöscht, bleibt die Regel unverändert (Test
  `test_person_named_in_a_rule`). Das entspricht Moodles Profilbedingung (`availability_profile`), die
  dieselben Felder anbietet und ebenfalls keine Daten verwaltet.

## Privacy API

- `classes/privacy/provider.php` ist ein `null_provider`, wie bei allen Core-Availability-Bedingungen
  (`availability_completion`, `_date`, `_grade`, `_group`, `_grouping`, `_profile`).
- Geprüft (Test): `core_privacy\manager::component_is_compliant('availability_usercriteria')` ist wahr, und
  für eine in einer Regel genannte Person liefert AUC keinen Kontext für Export oder Löschung. Ein
  `null_provider` hat nichts zu exportieren oder zu löschen; die Regeltexte gehören zur Kurskonfiguration
  (Core).
- Die Begründung (`privacy:metadata`, DE/EN) benennt diese Grenze ausdrücklich.

## Keine Offenlegung

| Weg | Maßnahme | Nachweis |
|---|---|---|
| Hinweistext für Teilnehmende | Identitätsmerkmale ohne Wert, sonst nur Schwellenwerte | `test_restriction_text_shows_no_values_of_people`, Behat |
| Debug-Ausgabe | Identitätsoperanden maskiert | `test_operands_of_people_are_not_disclosed` |
| Fehlermeldungen fehlerhafter Regeln | enthalten keine Werte | `test_error_messages_contain_no_values` |
| Editor | nur Definitionen, keine Personendaten | `test_editor_reveals_no_data_of_people` |
| Bewertung | nur das Urteil, nie die Note der Person | `test_hidden_and_locked_grades` |

## Empfehlung für den Betrieb

Identitätsmerkmale sparsam einsetzen; wo möglich Rollen, Gruppen oder Profilfelder ohne Personenbezug
verwenden. Vor dem Produktiveinsatz sollte die Datenschutzstelle der Einrichtung diese Beschreibung bewerten,
das ist offen.
