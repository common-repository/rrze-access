=== RRZE-Access ===
Contributors: rvdforst
Tags: access, visibility
Requires at least: 3.4
Tested up to: 3.4
Stable tag: 1.0
License: GPLv2 or later

Zugriffsbeschränkung von IP-Adressen auf Artikel, Seiten und die entsprechenden Media-Dateien.



== Description ==

<p>Beschränken Sie den Zugriff von bestimmte IP-Adressen auf Artikel, Seiten und die entsprechenden Media-Dateien.</p>

<p>Bei der Erstellung und Bearbeitung eines Artikels oder einer Seite, das Plugin fügt innerhalb des Veröffentlichen-Metaboxes der Feldbereich "Beschränkung von IP-Adressen" hinzu. Siehe <a href="http://wordpress.org/extend/plugins/rrze-access/screenshots/">Screenshot</a>.</p>

<p>Die IP-Adressen die gefiltert werden sollen müssen im Textfeld "IP-Adressen" eingegeben werden. Regeln:</p>

<ul>
	<li>Eine IP-Adresse pro Zeile ist erlaubt</li>
	<li>Nur IPv4-Adressen sind zulässig</li>
	<li>Platzhalterzeichen "*" ist akzeptiert. Beispielsweise:
		<ul>
			<li>192.168.1.*</li>
			<li>192.168.*.2</li>
			<li>192.168.*</li>
            <li>192.*</li>
            <li>*</li>
		</ul>
	</li>
</ul>

<p>Konfigurationsschritte für die Datei ".htaccess" finden Sie unter Einstellungen > Zugriffsbeschränkung-Einrichtung.</p>



== Screenshots ==

1. Beschränkung von IP-Adressen auf einen Artikel
