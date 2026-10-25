<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * German strings for availability_usercriteria.
 *
 * @package    availability_usercriteria
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Keep the keys in alphabetical order: moodle.Files.LangFilesOrdering enforces it.
$string['asnumber'] = '{$a} (als Zahl)';
$string['bounds_both'] = 'beide Grenzen eingeschlossen';
$string['bounds_lower'] = 'untere Grenze eingeschlossen, obere ausgeschlossen';
$string['bounds_none'] = 'beide Grenzen ausgeschlossen';
$string['bounds_upper'] = 'untere Grenze ausgeschlossen, obere eingeschlossen';
$string['cachedef_values'] = 'Für eine Person während einer Seitenanfrage gelesene Werte';
$string['cat_activity'] = 'Login und Zugriff';
$string['cat_enrolment'] = 'Einschreibung';
$string['cat_identity'] = 'Identität';
$string['cat_profile'] = 'Profilfelder';
$string['cat_progress'] = 'Fortschritt und Bewertung';
$string['cat_role'] = 'Rollen';
$string['desc'] = '{$a->feature} {$a->operator} {$a->operand}';
$string['desc_days'] = '{$a} Tage';
$string['desc_identity'] = 'Ihre Nutzerdaten erfüllen ein von der Lehrperson festgelegtes Kriterium';
$string['desc_none'] = '{$a->feature} {$a->operator}';
$string['desc_not'] = 'NICHT ({$a})';
$string['desc_pair'] = '{$a->first} und {$a->second} ({$a->bounds})';
$string['description'] = 'Zugriff nach Merkmalen der Person einschränken: Identität, Profilfelder, Rollen, Einschreibung, Aktivität, Fortschritt und Bewertung.';
$string['editor_bounds'] = 'Grenzen';
$string['editor_choose'] = 'Auswählen ...';
$string['editor_courseinfo'] = 'Wird in diesem Kurs gelesen.';
$string['editor_days'] = 'Tage';
$string['editor_feature'] = 'Merkmal';
$string['editor_operator'] = 'Operator';
$string['editor_siteinfo'] = 'Wird für die gesamte Site gelesen.';
$string['editor_value'] = 'Wert';
$string['editor_value2'] = 'Oberer Wert';
$string['error_backwardrange'] = 'Der untere Wert darf nicht größer als der obere Wert sein.';
$string['error_invaliddays'] = 'Geben Sie eine ganze Zahl von mindestens 1 Tag ein.';
$string['error_invalidvalue'] = 'Geben Sie einen gültigen Wert für die Nutzerkriterien-Bedingung ein.';
$string['error_selectfeature'] = 'Wählen Sie ein Merkmal für die Nutzerkriterien-Bedingung.';
$string['error_selectoperator'] = 'Wählen Sie einen Operator für die Nutzerkriterien-Bedingung.';
$string['error_selectvalue'] = 'Wählen Sie mindestens einen Wert für die Nutzerkriterien-Bedingung.';
$string['feature_completed_count'] = 'Anzahl erfolgreich abgeschlossener Aktivitäten';
$string['feature_course_grade'] = 'Kursbewertung (%)';
$string['feature_course_lastaccess'] = 'Letzter Kurszugriff';
$string['feature_enrol_method'] = 'Einschreibemethode';
$string['feature_enrol_start'] = 'Beginn der Einschreibung';
$string['feature_finished_count'] = 'Anzahl beendeter Aktivitäten (einschließlich nicht bestanden)';
$string['feature_role_course_direct'] = 'Direkt im Kurs zugewiesene Rolle';
$string['feature_role_course_effective'] = 'Im Kurs wirksame Rolle (einschließlich geerbter)';
$string['feature_role_system'] = 'Systemrolle';
$string['feature_site_lastlogin'] = 'Letzter Site-Login';
$string['feature_user_address'] = 'Adresse';
$string['feature_user_alternatename'] = 'Alternativer Name';
$string['feature_user_city'] = 'Ort';
$string['feature_user_country'] = 'Ländercode';
$string['feature_user_department'] = 'Abteilung';
$string['feature_user_email'] = 'E-Mail-Adresse';
$string['feature_user_firstname'] = 'Vorname';
$string['feature_user_id'] = 'Nutzer-ID';
$string['feature_user_idnumber'] = 'ID-Nummer';
$string['feature_user_institution'] = 'Institution';
$string['feature_user_lang'] = 'Bevorzugte Sprache';
$string['feature_user_lastname'] = 'Nachname';
$string['feature_user_middlename'] = 'Zweiter Vorname';
$string['feature_user_phone1'] = 'Telefon';
$string['feature_user_phone2'] = 'Mobiltelefon';
$string['feature_user_username'] = 'Nutzername';
$string['hint_completed_count'] = 'Anzahl der Aktivitäten dieses Kurses, die die Person abgeschlossen oder bestanden hat. Gezählt werden Aktivitäten mit Abschlussverfolgung, die von Lehrenden sichtbar geschaltet sind, auch solche, die verfügbar, aber nicht auf der Kursseite angezeigt sind. Nicht gezählt: verborgene Aktivitäten, Aktivitäten in verborgenen Abschnitten, Aktivitäten, die gerade gelöscht werden. Zugriffsbeschränkungen der Aktivitäten werden nicht geprüft: Eine Aktivität, die die Person noch nicht öffnen kann, zählt trotzdem, und eine beschränkte Aktivität zählt sich selbst mit.';
$string['hint_course_grade'] = 'Kursgesamtbewertung in Prozent ihres Bereichs (0-100), nicht gerundet; eine Skala zählt vom ersten (0) bis zum letzten Eintrag (100). Überschreibungen zählen, verborgene und gesperrte Bewertungen ebenso. Ohne Bewertung oder solange Moodle die Bewertungen noch neu berechnen muss, ist die Bedingung nicht erfüllt; eine fehlende Bewertung fragt man mit „ist nicht vorhanden“ ab.';
$string['hint_course_lastaccess'] = 'Der Zeitpunkt, zu dem Moodle die Person zuletzt im Kurs erfasst hat (jede Seite des Kurses). Kein Maß für Lernaktivität und nicht aus den Logs entnommen.';
$string['hint_enrol_start'] = 'Beginn einer aktiven Einschreibung, ersatzweise ihre Erstellungszeit. Bei mehreren Einschreibungen genügt eine aktive. Ohne aktive Einschreibung ist der Wert nicht vorhanden. Zeit in Ihrer Zeitzone.';
$string['hint_finished_count'] = 'Wie die Anzahl abgeschlossener Aktivitäten, aber „nicht bestanden“ zählt auch: abgeschlossen, bestanden oder nicht bestanden. Gleiche Aktivitäten: verfolgt und sichtbar, Zugriffsbeschränkungen werden nicht geprüft.';
$string['hint_site_lastlogin'] = 'Letzter Login. „Ist nicht vorhanden“ bedeutet: nie eingeloggt.';
$string['op_after'] = 'liegt nach';
$string['op_before'] = 'liegt vor';
$string['op_between'] = 'liegt zwischen';
$string['op_contains'] = 'enthält';
$string['op_endswith'] = 'endet mit';
$string['op_eq'] = 'ist gleich';
$string['op_gt'] = 'ist größer als';
$string['op_gte'] = 'ist mindestens';
$string['op_in'] = 'ist eines von';
$string['op_inlast'] = 'liegt innerhalb der letzten';
$string['op_lt'] = 'ist kleiner als';
$string['op_lte'] = 'ist höchstens';
$string['op_missing'] = 'ist nicht vorhanden';
$string['op_ne'] = 'ist ungleich';
$string['op_notcontains'] = 'enthält nicht';
$string['op_notin'] = 'ist keines von';
$string['op_olderthan'] = 'liegt länger zurück als';
$string['op_present'] = 'ist vorhanden';
$string['op_startswith'] = 'beginnt mit';
$string['pluginname'] = 'Nutzerkriterien';
$string['privacy:metadata'] = 'Das Plugin Nutzerkriterien hat keine eigenen Tabellen und speichert keine Daten über die Personen, die es auswertet; es liest vorhandene Moodle-Daten nur im Moment der Zugriffsprüfung. Eine Zugriffsregel ist jedoch Kurskonfiguration und kann von Lehrenden gewählte identifizierende Werte enthalten (Nutzer-ID, Nutzername, Name, E-Mail, Telefon, Adresse). Moodle speichert solche Regeln in den Einstellungen der Aktivität oder des Abschnitts und sichert sie in Kurssicherungen mit.';
$string['title'] = 'Nutzerkriterien';
