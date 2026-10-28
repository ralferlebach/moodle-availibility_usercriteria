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
$string['desc_count'] = '{$a} Teilnehmenden';
$string['desc_days'] = '{$a} Tage';
$string['desc_identity'] = 'Ihre Nutzerdaten erfüllen ein von der Lehrperson festgelegtes Kriterium';
$string['desc_none'] = '{$a->feature} {$a->operator}';
$string['desc_not'] = 'NICHT ({$a})';
$string['desc_pair'] = '{$a->first} und {$a->second} ({$a->bounds})';
$string['desc_percent'] = '{$a} % der Teilnehmenden';
$string['description'] = 'Zugriff nach Merkmalen der Person einschränken: Identität, Profilfelder, Rollen, Einschreibung, Aktivität, Fortschritt und Bewertung.';
$string['diag'] = '(Hier nicht auswertbar: {$a})';
$string['diag_nocompletion'] = 'die Abschlussverfolgung ist in diesem Kurs ausgeschaltet.';
$string['diag_nocoursecompletion'] = 'dieser Kurs hat keine Kursabschlusskriterien.';
$string['diag_nolist'] = 'die Vergleichswerte der Teilnehmenden werden gerade berechnet (geplante Aufgabe).';
$string['diag_nolog'] = 'der Standard-Logspeicher ist nicht aktiviert.';
$string['diag_noparticipantroles'] = 'in den Plugin-Einstellungen sind keine Teilnehmerrollen gesetzt.';
$string['diag_notprocessed'] = 'das Log wurde noch nicht verarbeitet (geplante Aufgabe).';
$string['diag_unknownfeature'] = 'das Merkmal gibt es auf dieser Seite nicht, zum Beispiel ein gelöschtes Profilfeld.';
$string['diag_unknownoperator'] = 'der Operator passt nicht zum Merkmal.';
$string['editor_bounds'] = 'Grenzen';
$string['editor_choose'] = 'Auswählen ...';
$string['editor_count'] = 'Teilnehmende';
$string['editor_courseinfo'] = 'Wird in diesem Kurs gelesen.';
$string['editor_days'] = 'Tage';
$string['editor_feature'] = 'Merkmal';
$string['editor_operator'] = 'Operator';
$string['editor_percent'] = '% der Teilnehmenden';
$string['editor_relativeinfo'] = 'Verglichen mit den Teilnehmenden des Kurses (Rollen legt die Administration fest), Stand der letzten stündlichen Berechnung; bei Gleichstand gilt die bessere Position. Der eigene Wert wird aktuell gelesen; eine Änderung seit der letzten Berechnung kann eine Freigabe nur verzögern.';
$string['editor_siteinfo'] = 'Wird für die gesamte Site gelesen.';
$string['editor_value'] = 'Wert';
$string['editor_value2'] = 'Oberer Wert';
$string['error_backwardrange'] = 'Der untere Wert darf nicht größer als der obere Wert sein.';
$string['error_invalidcount'] = 'Geben Sie eine ganze Zahl von mindestens 1 Teilnehmenden ein.';
$string['error_invaliddays'] = 'Geben Sie eine ganze Zahl von mindestens 1 Tag ein.';
$string['error_invalidpercent'] = 'Geben Sie einen Prozentsatz über 0 und höchstens 100 ein.';
$string['error_invalidvalue'] = 'Geben Sie einen gültigen Wert für die Nutzerkriterien-Bedingung ein.';
$string['error_selectfeature'] = 'Wählen Sie ein Merkmal für die Nutzerkriterien-Bedingung.';
$string['error_selectoperator'] = 'Wählen Sie einen Operator für die Nutzerkriterien-Bedingung.';
$string['error_selectvalue'] = 'Wählen Sie mindestens einen Wert für die Nutzerkriterien-Bedingung.';
$string['feature_completed_count'] = 'Anzahl erfolgreich abgeschlossener Aktivitäten';
$string['feature_completion_duration'] = 'Dauer vom ersten Zugriff bis zum Kursabschluss (Tage)';
$string['feature_course_grade'] = 'Kursbewertung (%)';
$string['feature_course_lastaccess'] = 'Letzter Kurszugriff';
$string['feature_enrol_method'] = 'Einschreibemethode';
$string['feature_enrol_start'] = 'Beginn der Einschreibung';
$string['feature_finished_count'] = 'Anzahl beendeter Aktivitäten (einschließlich nicht bestanden)';
$string['feature_role_course_direct'] = 'Direkt im Kurs zugewiesene Rolle';
$string['feature_role_course_effective'] = 'Im Kurs wirksame Rolle (einschließlich geerbter)';
$string['feature_role_system'] = 'Systemrolle';
$string['feature_site_lastlogin'] = 'Letzter Site-Login';
$string['feature_usage_time'] = 'Nutzungsdauer im Kurs (Stunden, geschätzt)';
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
$string['hint_completion_duration'] = 'Tage vom ersten im Standard-Log gefundenen Zugriff auf den Kurs bis zum Kursabschluss. Noch nicht abgeschlossen: Wert nicht vorhanden. Setzt einen Kursabschluss mit Kriterien voraus. Zugriffe, die vor der Verarbeitung aus dem Log gelöscht wurden, sind nicht bekannt.';
$string['hint_course_grade'] = 'Kursgesamtbewertung in Prozent ihres Bereichs (0-100), nicht gerundet; eine Skala zählt vom ersten (0) bis zum letzten Eintrag (100). Überschreibungen zählen, verborgene und gesperrte Bewertungen ebenso. Ohne Bewertung oder solange Moodle die Bewertungen noch neu berechnen muss, ist die Bedingung nicht erfüllt; eine fehlende Bewertung fragt man mit „ist nicht vorhanden“ ab.';
$string['hint_course_lastaccess'] = 'Der Zeitpunkt, zu dem Moodle die Person zuletzt im Kurs erfasst hat (jede Seite des Kurses). Kein Maß für Lernaktivität und nicht aus den Logs entnommen.';
$string['hint_enrol_start'] = 'Beginn einer aktiven Einschreibung, ersatzweise ihre Erstellungszeit. Bei mehreren Einschreibungen genügt eine aktive. Ohne aktive Einschreibung ist der Wert nicht vorhanden. Zeit in Ihrer Zeitzone.';
$string['hint_finished_count'] = 'Wie die Anzahl abgeschlossener Aktivitäten, aber „nicht bestanden“ zählt auch: abgeschlossen, bestanden oder nicht bestanden. Gleiche Aktivitäten: verfolgt und sichtbar, Zugriffsbeschränkungen werden nicht geprüft.';
$string['hint_site_lastlogin'] = 'Letzter Login. „Ist nicht vorhanden“ bedeutet: nie eingeloggt.';
$string['hint_usage_time'] = 'Geschätzt aus dem Standard-Log: Ereignisse im Abstand von höchstens 30 Minuten bilden eine Sitzung, die Zeit dazwischen zählt; die Zeit nach dem letzten Klick einer Sitzung nicht. Eine Untergrenze, alle 10 Minuten aktualisiert. Ohne Log-Ereignisse: 0 Stunden.';
$string['op_after'] = 'liegt nach';
$string['op_before'] = 'liegt vor';
$string['op_best'] = 'gehört zu den besten';
$string['op_between'] = 'liegt zwischen';
$string['op_bottompct'] = 'gehört zu den unteren';
$string['op_contains'] = 'enthält';
$string['op_endswith'] = 'endet mit';
$string['op_eq'] = 'ist gleich';
$string['op_gt'] = 'ist größer als';
$string['op_gte'] = 'ist mindestens';
$string['op_in'] = 'ist eines von';
$string['op_inlast'] = 'liegt innerhalb der letzten';
$string['op_lt'] = 'ist kleiner als';
$string['op_lte'] = 'ist höchstens';
$string['op_max'] = 'ist der höchste Wert der Teilnehmenden';
$string['op_min'] = 'ist der niedrigste Wert der Teilnehmenden';
$string['op_missing'] = 'ist nicht vorhanden';
$string['op_ne'] = 'ist ungleich';
$string['op_notcontains'] = 'enthält nicht';
$string['op_notin'] = 'ist keines von';
$string['op_olderthan'] = 'liegt länger zurück als';
$string['op_present'] = 'ist vorhanden';
$string['op_startswith'] = 'beginnt mit';
$string['op_toppct'] = 'gehört zu den oberen';
$string['op_worst'] = 'gehört zu den niedrigsten';
$string['participantroles'] = 'Teilnehmerrollen';
$string['participantroles_desc'] = 'Relative Kriterien vergleichen eine Person mit den Teilnehmenden des Kurses: Personen mit aktiver Einschreibung und einer dieser Rollen, im Kurs zugewiesen.';
$string['pluginname'] = 'Nutzerkriterien';
$string['privacy:metadata:usage'] = 'Geschätzte Nutzungsdauer und erster protokollierter Zugriff je Person und Kurs, aus dem Standard-Log für Zugriffsregeln berechnet.';
$string['privacy:metadata:usage:courseid'] = 'Der Kurs.';
$string['privacy:metadata:usage:firstaccess'] = 'Zeitpunkt des ersten protokollierten Zugriffs auf den Kurs.';
$string['privacy:metadata:usage:lastevent'] = 'Zeitpunkt des letzten verarbeiteten Ereignisses im Kurs.';
$string['privacy:metadata:usage:seconds'] = 'Geschätzte Nutzungsdauer in Sekunden.';
$string['privacy:metadata:usage:sessions'] = 'Anzahl der Sitzungen.';
$string['privacy:metadata:usage:timemodified'] = 'Zeitpunkt der letzten Aktualisierung.';
$string['privacy:metadata:usage:userid'] = 'Die Person.';
$string['privacy:path'] = 'Nutzungsdauer';
$string['task_update_distributions'] = 'Vergleichswerte für relative Nutzerkriterien';
$string['task_update_usage'] = 'Nutzungsdauer aus dem Standard-Log (Nutzerkriterien)';
$string['title'] = 'Nutzerkriterien';
