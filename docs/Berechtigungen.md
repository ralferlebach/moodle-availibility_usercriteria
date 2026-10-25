# Berechtigungen – availability_usercriteria (AUC)

**Stand:** 0.3.0 (25.10.2026) · Nachweis: `tests/permissions_test.php`, `tests/behat/usercriteria.feature`

## Entscheidung

AUC führt **keine eigene Capability** ein. Wer eine Regel anlegen oder ändern darf, entscheidet Moodle über die
vorhandenen Rechte zum Bearbeiten der Aktivität bzw. des Abschnitts. Das gilt auch für Identitäts- und
Kontaktmerkmale (Nutzer-ID, Nutzername, Namen, E-Mail, Telefon, Adresse).

Begründung:

- Moodles eigene Profilbedingung (`availability_profile`) bietet dieselben Standardfelder (E-Mail, Telefon,
  Adresse, ID-Nummer …) mit genau diesem Rechtemodell an. AUC erweitert die Angriffsfläche damit nicht.
- Eine Regel gibt nur „erfüllt / nicht erfüllt“ zurück. Der Editor zeigt keine Werte von Personen
  (Test `test_editor_reveals_no_data_of_people`), Beschreibungen nennen Identitätsmerkmale nie im Klartext.
- Vom Auftraggeber nach 0.1.0 bestätigt: „Ja, so belassen (vermutlich Admin)“.

Restrisiko, bewusst akzeptiert: Wer Regeln bearbeiten darf, kann mit einer gezielten Regel prüfen, ob eine
bestimmte Person einen Wert hat (z. B. „E-Mail endet mit @firma.de“), indem sie beobachtet, ob die Person
die Aktivität öffnen kann. Das gilt gleichermaßen für die Core-Profilbedingung. Soll das für sensible
Merkmale enger werden, wäre eine eigene Capability (etwa `availability/usercriteria:useidentity`) der Weg.
Diese Option ist nicht umgesetzt und bräuchte eine fachliche Entscheidung.

## Matrix (Standardrollen, Moodle-Standardrechte)

| Rolle | Regeln an Aktivitäten bearbeiten (`moodle/course:manageactivities`) | Regeln an Abschnitten bearbeiten (`moodle/course:update`) | Wird durch eine nicht erfüllte Regel ausgesperrt |
|---|---|---|---|
| Trainer/in (editingteacher) | ja | ja | nein (`moodle/course:ignoreavailabilityrestrictions`) |
| Trainer/in ohne Bearbeitungsrecht (teacher) | nein | nein | nein (`ignoreavailabilityrestrictions`) |
| Manager/in (im Kurs zugewiesen) | ja | ja | nein |
| Administrator/in | ja | ja | nein |
| Teilnehmer/in (student) | nein | nein | **ja** |

Die Regel selbst wird für alle ausgewertet (`cm_info::available` ist auch für Lehrende „nicht erfüllt“); nur
das Öffnen ist für Rollen mit `ignoreavailabilityrestrictions` trotzdem erlaubt. Abweichende Rechte einer
Einrichtung gelten unverändert, weil AUC nur die Core-Prüfungen nutzt.

## Was der Editor preisgibt

| Angeboten | Nicht angeboten |
|---|---|
| Namen der Merkmale und Operatoren | Werte von Personen (Namen, E-Mail, Ort, Feldinhalte, IDs) |
| Benutzerdefinierte Profilfelder samt Menü-Optionen, die die Administration definiert hat, auch verborgene Felder (wie Core) | Liste der Teilnehmenden |
| Rollen, die auf der Kurs- bzw. Systemebene vorkommen, mit Kursnamen | Rollenzuweisungen von Personen |
| Aktivierte Einschreibemethoden | Einschreibungen von Personen |
| Zeitzone der bearbeitenden Person | – |

## Was Teilnehmende sehen

- Bei Merkmalen ohne Personenbezug: Merkmal, Operator und Schwellenwert („Kursbewertung (%) ist mindestens 50“).
- Bei Identitäts- und Kontaktmerkmalen nur: „Ihre Nutzerdaten erfüllen ein von der Lehrperson festgelegtes
  Kriterium“, ohne Wert (Behat-Szenario „A restriction naming a person does not show the value to students“).
- Fehlermeldungen zu fehlerhaften Regeln enthalten keine Werte (`test_error_messages_contain_no_values`).
