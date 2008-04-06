<?php
/**
 * MODx language File
 *
 * @author davaeron, german by Marc Hinse
 * @package MODx
 * @version 1.0
 * 
 * Filename:       /install/lang/german/german.inc.php
 * Language:       German
 * Encoding:       UTF-8
 */




$_lang['license'] = '<p class="title">MODx Lizenzvereinbarung.</p>
  <hr style="WIDTH: 90%; HEIGHT: 1px; TEXT-ALIGN: left">

  <h4>Sie m&uuml;ssen der Lizenz zustimmen bevor Sie mit der Installation fortfahren.</h4>

  <p>Die Software ist unter der GPL lizenziert. Das Benutzen der Software unterliegt den bestimmungen der GPL. Die GPL betrifft diese Software sowie Ihre Benutzung in folgender Weise:</p>

  <h4>Die GNU General Public License ist eine freie Software Lizenz.</h4>

  <p>Wie bei jeder freien Softwarelizenz haben Sie folgende Freiheiten:</p>

  <ul>
    <li>Die Freiheit, das Programm zu jedem Zweck zu benutzen.</li>

    <li>Die Freiheit das Programm zu studieren und es Ihren Bed&uuml;rfnissen anzupassen.</li>

    <li>Die Freiheit, Kopien des Programms zu verbreiten und so dem N&auml;chsten zu helfen.</li>

    <li>Die Freiheit, das Programm zu verbessern, weiter zu verbreiten und so der gesamten Nutzergemeinde zu helfen.</li>
  </ul>

  <p>Sie k&ouml;nnen diese Freiheiten aus&uuml;ben wenn Sie mit den Bedingungen der GNU GPL einverstanden erkl&auml;ren. Die Bedingungen finden Sie <a href="http://www.gnu.de/documents/gpl.de.html" target="_blank">hier</a>.</p>

  <p>Das oben stehende ist eine Zusammenfassung der GNU GPL. Wenn Sie fortfahren, stimmen Sie den Bestimmungen der GNU General Public Licence zu, nicht dem hier beschriebenen. Oben stehendes stellt
  nur eine Zusammenfassng dar, seine Richtigkeit kann nicht garantiert werden.&nbsp;Es wird dringend empfohlen, die&nbsp;<a href="http://www.gnu.org/copyleft/gpl.html" target="_blank">GNU General
  Public License</a> im Original zu lesen bevor Sie fortfahren, diese k&ouml;nnen Sie auch im heruntergeladenen Paket als Textdatei finden.</p>';
$_lang["encoding"] = 'UTF-8';	//charset encoding for html header
$_lang["modx_install"] = 'MODx &raquo; Installation';
$_lang["loading"] = 'Laden...';
$_lang["Begin"] = 'Anfang';
$_lang["status_connecting"] = ' Verbindung zu Host: ';
$_lang["status_fehlgeschlagen"] = 'fehlgeschlagen!';
$_lang["status_passed"] = 'in Ordnung';
$_lang["status_checking_Datenbank"] = '...    Prufe Datenbank: ';
$_lang["status_fehlgeschlagen_could_not_select_Datenbank"] = 'fehlgeschlagen - konnte Datenbank nicht auswahlen';
$_lang["status_fehlgeschlagen_table_prefix_already_in_use"] = 'fehlgeschlagen - Tabellen Prefix bereits verwendet!';
$_lang["welcome_message_welcome"] = 'Willkommen beim MODx Installationsprogramm.';
$_lang["welcome_message_text"] = 'Dieses Programm wird Sie durch die Installation begleiten.';
$_lang["welcome_message_select_begin_button"] = 'Bitte klicken Sie auf `Begin` um zu beginnen:';
$_lang["installation_mode"] = 'Installationsmodus';
$_lang["installation_new_installation"] = 'Neue Installation';
$_lang["installation_install_new_copy"] = 'Neue Kopie installieren von ';
$_lang["installation_install_new_note"] = 'Beachten Sie, dass diese Option alle Daten in der Datenbank uberschreibt.';
$_lang["installation_upgrade_existing"] = 'Upgrade einer existierenden Installation';
$_lang["installation_upgrade_existing_note"] = 'Upgrade Ihrer Dateien und der Datenbank.';
$_lang["installation_upgrade_advanced"] = 'Upgrade Installation fur Fortgeschrittene<br /><small>(andere Datenbankkonfiguration)</small>';
$_lang["installation_upgrade_advanced_note"] = 'For fortgeschrittene Datenbank Administratoren oder bei Umzug auf Server mit einem anderen Datenbank Zeichensatz/Kollation. <b>Sie mussen Ihren vollstandigen Datenkbanknamen, -Benutzer, -Passwort und -Verbindung/Kollation wissen.</b>';
$_lang["connection_screen_connection_information"] = 'Verbindungsinformation';
$_lang["connection_screen_connection_and_login_information"] = 'Datenbank Verbindung- und Login-Information';
$_lang["connection_screen_connection_note"] = 'Bitte geben Sie den Namen der Datenbank an, die Sie fur MODX angelegt haben. Falls es noch keine Datenbank gibt, versucht das Programm sie fur Sie anzulegen. Dies kann je nach MySQL-Konfiguration oder Datenbankrechten fehlschlagen.';
$_lang["connection_screen_Datenbank_name"] = 'Datenbank Name:';
$_lang["connection_screen_table_prefix"] = 'Tabellen Prefix:';
$_lang["connection_screen_collation"] = 'Kollation:';
$_lang["connection_screen_character_set"] = 'Zeichensatz der Verbindung:';
$_lang["connection_screen_Datenbank_info"] = 'Nun geben Sie bitte die Verbindungsdetails Ihrer Datenbank ein.';
$_lang["connection_screen_Datenbank_host"] = 'Datenbank host:';
$_lang["connection_screen_Datenbank_login"] = 'Datenbank Login Name:';
$_lang["connection_screen_Datenbank_pass"] = 'Datenbank Passwort:';
$_lang["connection_screen_test_connection"] = 'Verbindung testen';
$_lang["connection_screen_default_admin_user"] = 'Standard Administrationskonto';
$_lang["connection_screen_default_admin_note"] = 'Bitte geben Sie weitere Details fur Ihren Administrator-Account an. Sie konnen Ihren Namen angeben und vergessen Sie bitte nicht das frei wahlbare Passwort.Diese Daten benotigen Sie fur den Login in die Administrationsoberflache von MODx, den Manager.';
$_lang["connection_screen_default_admin_login"] = 'Administrator Username:';
$_lang["connection_screen_default_admin_email"] = 'Administrator E-Mail:';
$_lang["connection_screen_default_admin_password"] = 'Administrator Passwort:';
$_lang["connection_screen_default_admin_password_confirm"] = 'Passwort bestatigen:';
$_lang["optional_items"] = 'Optionale Einstellungen';
$_lang["optional_items_note"] = 'Bitte wahlen Sie Ihre Installationsoptionen und klicken Sie auf Install:';
$_lang["sample_web_site"] = 'Beispiel-WEbsite';
$_lang["install_overwrite"] = 'Installieren/Uberschreiben';
$_lang["sample_web_site_note"] = 'Beachten Sie, dass damit alle Dokumente und Ressourcen <b style=\"color:#CC0000\">uberschrieben</b> werden.';
$_lang["checkbox_select_options"] = 'Checkbox Auswahlmoglichkeiten:';
$_lang["all"] = 'Alle';
$_lang["none"] = 'Keine';
$_lang["toggle"] = 'Umschalten';
$_lang["templates"] = 'Templates';
$_lang["install_update"] = 'Installation/Update';
$_lang["chunks"] = 'Chunks';
$_lang["modules"] = 'Module';
$_lang["plugins"] = 'Plugins';
$_lang["snippets"] = 'Snippets';
$_lang["preinstall_validation"] = 'Pre-install Prufung';
$_lang["summary_setup_check"] = 'Das Programm fuhrt eine Checks durch, um zu prufen ob alles fur die Installation bereit ist.';
$_lang["checking_php_version"] = "Uberprufe PHP-Version: ";
$_lang["failed"] = 'fehlgeschlagen!';
$_lang["ok"] = 'OK!';
$_lang["you_running_php"] = ' - Benutzte PHP Version ';
$_lang["modx_requires_php"] = ', und MODx benotigt PHP 4.1.0. oder hoher';
$_lang["php_security_notice"] = '<legend>Sicherheitshinweis</legend><p>MODx wird mit Ihrer PHP Version wohl laufen, aber unter dieser PHP Version wird die Benutzung von MODx nicht empfohlen. Ihre Version von PHP ist angreifbar aufgrund verschiedener Sicherheitslocher. Bitte upgraden Sie auf PHP 4.3.8 oder hoher, was diese Lucken schliest. Bitte upgraden, es geht um Ihre Sicherheit.</p>';
$_lang["checking_sessions"] = 'Uberprufe ob sessions sauber definiert sind: ';
$_lang["checking_if_cache_exist"] = 'Uberprufen ob Ordner <span class=\"mono\">assets/cache</span> existiert: ';
$_lang["checking_if_cache_writable"] = 'Uberprufen ob Ordner <span class=\"mono\">assets/cache</spanbeschreibbar ist: ';
$_lang["checking_if_cache_file_writable"] = 'Uberprufen ob die Datei <span class=\"mono\">assets/cache/siteCache.idx.php</span> beschreibbar ist: ';
$_lang["checking_if_cache_file2_writable"] = 'Uberprufen ob die Datei <span class=\"mono\">assets/cache/sitePublishing.idx.php</span> beschreibbar ist: ';
$_lang["checking_if_images_exist"] = 'Uberprufen ob Ordner <span class=\"mono\">assets/images</span> existiert: ';
$_lang["checking_if_images_writable"] = 'Uberprufen ob Ordner <span class=\"mono\">assets/images</span> beschreibbar ist: ';
$_lang["checking_if_export_exists"] = 'Uberprufen ob Ordner <span class=\"mono\">assets/export</span> existiert: ';
$_lang["checking_if_export_writable"] = 'Uberprufen ob Ordner <span class=\"mono\">assets/export</span> beschreibbar ist: ';
$_lang["checking_if_config_exist_and_writable"] = 'Uberprufen ob Datei <span class=\"mono\">manager/includes/config.inc.php</span> existiert und beschreibbar ist: ';
$_lang["config_permissions_note"] = 'Fur neue Linux/Unix Installationen bitt eine leere Datei <span class=\"mono\">config.inc.php</span> im Ordner <span class=\"mono\">manager/includes/</span> anlegen und die Dateirechte auf 0666 setzen.';
$_lang["creating_Datenbank_connection"] = 'Stelle Verbindung zur Datenbank: ';
$_lang["Datenbank_connection_fehlgeschlagen"] = 'Datenbankverbindund fehlgeschlagen!';
$_lang["Datenbank_connection_fehlgeschlagen_note"] = 'Bitte Uberprufen Sie Ihre Datenbank-Login-Daten und versuchen Sie es erneut.';
$_lang["Datenbank_use_fehlgeschlagen"] = 'Datenbank konnte nicht ausgewahlt werden!';
$_lang["Datenbank_use_fehlgeschlagen_note"] = 'Bitte prufen Sie den Datenbankzugang fur den gewahlten Benutzer und versuchen Sie es erneut.';
$_lang["checking_table_prefix"] = 'Check Tabellen Prefix `';
$_lang["table_prefix_already_inuse"] = ' - Tabellen Prefix wird bereits benutzt!';
$_lang["table_prefix_already_inuse_note"] = 'Das Programm konnte nicht in die gewahlte Datenbank installiert werden, da der Tabellen Prefix bereits verwendet wird. Bitte wahlen Sie einen anderen Prefix und wiederholen Sie die Installation.';
$_lang["table_prefix_not_exist"] = ' - Tabellen Prefix existiert nicht in der gewahlten Datenbank!';
$_lang["table_prefix_not_exist_note"] = 'Das Programm konnte nicht in der gewahlten Datenbank installiert werden, da keine Tabellen mit dem gewahlten Prefix existieren. to be upgraded. Bitte wahlen Sie einen exisiterenden Prefix und wiederholen Sie die Installation.';
$_lang["setup_cannot_continue"] = 'Leider kann die Installation nicht fortgesetzt werden wegen oben aufgefuhrter Grunde. ';
$_lang["error"] = 'Fehler';
$_lang["errors"] = 'Fehler'; //Plural form
$_lang["please_correct_error"] = '. Bitte korrigieren Sie den Fehler';
$_lang["please_correct_errors"] = '. Bitte korrigieren Sie die Fehler'; //Plural form
$_lang["and_try_again"] = ', und versuchen Sie es erneut. Falls Sie Hilfe bei der Losung des Problems benotigen';
$_lang["and_try_again_plural"] = ', und versuchen Sie es erneut. Falls Sie Hilfe bei der Losung der Probleme benotigen'; //Plural form
$_lang["visit_forum"] = ', besuchen Sie die <a href="http://www.modxcms.com/forums/" target="_blank">MODx Foren</a>.';
$_lang["testing_connection"] = 'Teste Verbindung...';
$_lang["btnback_value"] = 'zuruck';
$_lang["btnnext_value"] = 'Weiter';
$_lang["retry"] = 'Nochmal versuchen';
$_lang["alert_enter_Datenbank_name"] = 'Bitte einen Datenbank Namen eintragen!';
$_lang["alert_table_prefixes"] = 'Table Prefixe mussen mit einem Buchstaben beginnen!';
$_lang["alert_enter_host"] = 'Sie mussen einen Datenbank Host angeben!';
$_lang["alert_enter_login"] = 'Sie mussen einen Datenbank Login Name angeben!';
$_lang["alert_enter_adminlogin"] = 'Sie mussen einen Usernamen fur den Standard Administratoraccount angeben!';
$_lang["alert_enter_adminpassword"] = 'Sie mussen ein Passwort fur den Standard Administratoraccount angeben!';
$_lang["alert_enter_adminconfirm"] = 'Administrator-Passwort und dessen Bestatigung stimmen nicht uberein!';
$_lang["iagree_box"] = 'Ich stimmen den Lizenzbedingungen zu.';
$_lang["btnclose_value"] = 'Schliesen';
$_lang["running_setup_script"] = 'Starte Setup Script... bitte warten';
$_lang["modx_footer1"] = '&copy; 2005-2007 <a href="http://www.modxcms.com/" target="_blank" style="color: green; text-decoration:underline">MODx</a> Content Mangement Framework (CMF) Projekt. Alle Rechte vorbehalten. MODx unter der GNU GPL lizenziert.';
$_lang["modx_footer2"] = 'MODx is freie Software.  Wir ermutigen Sie, kreativ zu sein und MODx so zu nutzen wie es Ihnen am besten passt. Stellen Sie nur sicher, dass Sie bei Veranderungen des Quelltextes und der Weiterverbreitung der modifizierten MODx-Version den Quelltext frei zuganglich belassen!';
$_lang["setup_Datenbank"] = 'Setup richtet nun die Datenbank ein:<br />';
$_lang["setup_Datenbank_create_connection"] = 'Verbindund zur Datenbank: ';
$_lang["setup_Datenbank_create_connection_fehlgeschlagen"] = 'Datenbank Verbindung fehlgeschlagen!';
$_lang["setup_Datenbank_create_connection_fehlgeschlagen_note"] = 'Bitte prufen Sie die Datenbank-Details und versuchen Sie es erneut.';
$_lang["setup_Datenbank_selection"] = 'Datenbank wahlen`';
$_lang["setup_Datenbank_selection_fehlgeschlagen"] = 'Datenbank Auswahl fehlgeschlagen...';
$_lang["setup_Datenbank_selection_fehlgeschlagen_note"] = 'Die Datenbank existiert nicht. Setup versucht sie anzulegen.';
$_lang["setup_Datenbank_creation"] = 'Lege Datenbank an `';
$_lang["setup_Datenbank_creation_fehlgeschlagen"] = 'Datenbank Erstellung fehlgeschlagen!';
$_lang["setup_Datenbank_creation_fehlgeschlagen_note"] = ' - Setup konnte die Datenbank nicht anlegen!';
$_lang["setup_Datenbank_creation_fehlgeschlagen_note2"] = 'Setup konnte die Datenbank nicht anlegen, und keine Datenbank mit gleichem Namen wurde gefunden. Hochstwahrscheinlich lasst Ihr Provider das Anlegen von Datenbanken mittel externem Script nicht zu. Bitte legen Sie die Datenbank wie vom Provider beschrieben an oder geben Sie die Verbindungsdaten einer bereits angelegten Datenbank an.';
$_lang["setup_Datenbank_creating_tables"] = 'Erstelle Datenbanktabellen: ';
$_lang["Datenbank_alerts"] = 'Datenbank Meldungen!';
$_lang["setup_couldnt_install"] = 'MODx setup konnte die Tabellen in der gewahlten Datenbank nicht anlegen/andern.';
$_lang["installation_error_occured"] = 'Folgende Fehler sind wahrend der Installation aufgetreten';
$_lang["during_execution_of_sql"] = ' wahrend des Ausfuhres des SQL-Statements ';
$_lang["some_tables_not_updated"] = 'Manche Tabellen wurden nicht geupdatet. Dies konnte an zuvor individuell ausgefuhrten Modifikationen liegen.';
$_lang["installing_demo_site"] = 'Installiere Beispielinhalt: ';
$_lang["writing_config_file"] = 'Schreibe Konfigurationsdatei: ';
$_lang["cant_write_config_file"] = 'MODx konnte die Konfigurationsdatei nicht erstellen. Bitte fugen Sie folgendes in eine leere Datei ein:';
$_lang["cant_write_config_file_note"] = 'Sobald dies beendet ist, konnen Sie sich im Manager einloggen unter YourSiteName.com/manager/.';
$_lang["unable_install_template"] = 'Konnte Template nicht installieren.  Datei';
$_lang["unable_install_chunk"] = 'Konnte Chunk nicht installieren.  Datei';
$_lang["unable_install_module"] = 'Konnte Modul nicht installieren.  Datei';
$_lang["unable_install_plugin"] = 'Konnte Plugin nicht installieren.  Datei';
$_lang["unable_install_snippet"] = 'Konnte Snippet nicht installieren.  Datei';
$_lang["not_found"] = 'nicht gefunden';
$_lang["upgraded"] = 'Aktualisiert';
$_lang["installed"] = 'Installiert';
$_lang["running_Datenbank_updates"] = 'Fuhre Datenbank Updates aus: ';
$_lang["installation_successful"] = 'Installation war erfolgreich!';
$_lang["to_log_into_content_manager"] = 'Um sich im Manager einzuloggen, (manager/index.php) klicken Sie auf den `Schliesen` Button.';
$_lang["install"] = 'Installieren';
$_lang["remove_install_folder_auto"] = 'Entferne den Installationsordner von meinem Webspace <br />&nbsp;(Dies erfordert das Recht, Ordner loschen zu konnen).';
$_lang["remove_install_folder_manual"] = 'Bitte denken Sie daran den Ornder &quot;<b>install</b>&quot;zu loschen bevor Sie sich das erste Mal im Manager einloggen.';
$_lang["install_results"] = 'Installationsergebnisse';
$_lang["installation_note"] = '<strong>Achtung:</strong> Nach dem Einloggen im Manager sollten Sie die Konfigurationseinstellungen vornehmen und speichern, bevor Sie Ihre Seite aufrufen: <strong>Werkzeuge</strong> -> Konfiguration im MODx Manager.';
$_lang["upgrade_note"] = '<strong>Achtung:</strong> Nach dem Einloggen im Manager sollten Sie die Konfigurationseinstellungen uberprufen und speichern, bevor Sie Ihre Seite aufrufen: <strong>Werkzeuge</strong> -> Konfiguration im MODx Manager.';
?>