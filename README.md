# AdLer Moodle Plugin

[![Coverage Status](https://coveralls.io/repos/github/ProjektAdLer/MoodlePluginLocal/badge.svg?branch=main)](https://coveralls.io/github/ProjektAdLer/MoodlePluginLocal?branch=main)



## MBZ api endpunkt
Damit der mbz api endpunkt auch mit größeren Dateien funktioniert sind folgende Änderungen an der php.ini notwendig:
- `post_max_size` auf mindestens 2048M setzen
- `upload_max_filesize` auf 2048M setzen
- `max_input_time` auf 600 setzen
- `memory_limit` auf mindestens 256M setzen
- `max_execution_time` auf mindestens 60 setzen
- `output_buffering` auf 8192 setzen

```


## Kompabilität

Die minimal notwendige Moodle Version ist auf 3.11.12 gesetzt, daher wird die Installation auf älteren Versionen nicht funktionieren.
Prinzipiell sollte das Plugin auch auf älteren Versionen funktionieren, dies wird aber nicht getestet.

Folgende Versionen werden unterstützt (mit mariadb und postresql getestet):

| Moodle Branch     | PHP Version |
|-------------------|-------------|
| MOODLE_311_STABLE | 7.4         |
| MOODLE_401_STABLE | 7.4         |
| MOODLE_401_STABLE | 8.1         |


## Deinstallation der Plugins
### Halbautomatische Deinstallation
Es gibt ein dev_utils script, welches dieses Plugin deinstalliert `php local/adler/dev_utils/uninstall_plugin.php`.
Möglicherweise wird dieses Script in Zukunft zu einem PHP CLI script ausgebaut.

### Manuelle Deinstallation und Erklärung des Problems
Die Plugins sind gegenseitig voneinander abhängig. Diese sind ausschließlich dafür gedacht in Kombination verwendet zu werden, 
für sich alleine können diese nicht eingesetzt werden. Der Uninstaller von moodle kann Plugins mit gegenseitigen Abhängigkeiten
nicht deinstallieren. Dazu gibt es eine "Won't fix" Issue im moodle issue tracker: [MDL-55624](https://tracker.moodle.org/browse/MDL-56624).
Die Deinstallation der Plugins erfordert deshalb manuelles Eingreifen.
1. In der Datei `local/adler/version.php` das Feld `$plugin->dependencies` komplett löschen.
2. Evtl. ist ein Erhöhen der Versionsnummer und ein upgrade der Moodle Installation notwendig.
3. Die Plugins können nun deinstalliert werden.

Ein alternativer Ansatz dazu könnte wie folgt aussehen: Das Plugin `availability_adler` kann im Grunde eigenständig installiert werden,
ist dann aber ohne Funktion, da es ohne das Plugin `local_adler` nicht funktionieren kann (alle Adler-Conditions werden deshalb als 
erfüllt behandelt). Die Abhängigkeit des Plugins `availability_adler` von `local_adler` könnte somit entfernt werden. So könnten 
die Plugins erwartungsgemäß deinstalliert werden. Dieser Ansatz hätte aber die folgenden gravierenden Nachteile:
- Dem Moodle Plugin Konzept folgend dürfte `availability_adler` nicht auf Funktionen von `local_adler` zugreifen, da keine Abhängigkeit
  besteht. Dies würde aber bedeuten, dass die Adler Raumlogiken nicht funktionieren können.
- Wird `local_adler` deinstalliert verbleibt `availability_adler` installiert und muss danach manuell entfernt werden.
- Wird `availability_adler` installiert wird `local_adler` nicht automatisch installiert.


## Setup

Setup funktioniert exakt wie bei allen anderen Plugins auch (nach dem manuellen Installationsvorgang, da unser Plugin nicht im Moodle Store ist).

1. Plugin in moodle in den Ordner `local` entpacken (bspw moodle/local/adler/lib.php muss es geben)
2. Moodle upgrade ausführen

Damit ist die Installation abgeschlossen. Als Nächstes kann ein mbz mit den Plugin-Feldern wiederhergestellt werden.


### Kurs mit dummy Daten seeden
Für Testzwecke können bestehende normale Kurse mit dummy Daten gefüllt werden.
Im Ordner `dev_utils` liegt dazu das Skript `seed.php`.

Beispiel: \
Für den Kurs mit der ID 142 werden Daten für folgende Elemente geseedet:
- Kurs: Markierung als Adler Kurs
- Section (Räume): availability conditions & required points to complete
- course modules (Lernelemente): AdLer Scoring
Befehl: `php local/adler/dev_utils/seed.php --course-id=142 -c -m -s`

~~Dazu liegen im Ordner `dev_utils/seed` Skripte, die das automatisieren.~~
1) ~~Zuerst im Script `course.php` die Kurs-ID eintragen, die gefüllt werden soll.
Dann das Script ausführen `php local/adler/dev_utils/seed/course.php`.~~
2) ~~Danach im Script `scores.php` die Kurs-ID eintragen, die gefüllt werden soll (dieselbe wie im vorherigen Script).
Dann das Script ausführen `php local/adler/dev_utils/seed/scores.php`.~~
3) ~~Section seeden: `php local/adler/dev_utils/seed/section.php`~~

Nun kann dieser Kurs zum Testen genutzt werden.


## Dev Setup / Tests
Dieses Plugin nutzt Mockery für Tests. 
Die composer.json von Moodle enthält Mockery nicht, daher enthält das Plugin eine eigene composer.json.
Diese ist (derzeit) nur für die Entwicklung relevant, sie enthält keine Dependencies die für die normale Nutzung notwendig sind.
Um die dev-dependencies zu installieren muss `composer install` im Plugin-Ordner ausgeführt werden.


## Verschiedenes

### Context ID für xapi events

xapi Events nutzen die Context-ID, um die Kurs-ID zu referenzieren.
Diese sind aktuell über keine API des Plugins verfügbar, da sie für das moodle-interne Rechtemanagement gedacht sind.
Um für Testzwecke die context-id eines Lernelements zu erhalten, kann wie folgt vorgegangen werden:

1. cmid des Lernelements herausfinden: h5p Element im Browser öffnen, in der URL steht die cmid als Parameter `id=123` (bspw `http://localhost/mod/h5pactivity/view.php?id=2`)
2. in der DB folgende query ausführen: `SELECT id FROM mdl_context where contextlevel = 70 and instanceid = 2;`  (`instanceid` ist die id aus step 1)


### Löschen eines Kurses / von Lernelementen

Beim Löschen eines Kurses oder Lernelements werden die Daten aus der Datenbank gelöscht. Dabei gibt es aber zwei Dinge zu beachten:

1. Moodle hat einen Trashbin der standardmäßig aktiv ist. Dann werden die Daten erst Zeitverzögert gelöscht. Ob dies wirklich funktioniert wurde bisher nicht getestet, sollte aber
   funktionieren.
2. Wird ein Kurs gelöscht gibt es keine mir bekannte Möglichkeit zu erfahren, welche Kursbestandteile dabei entfernt wurden.
   Daher wird nach Löschen eines Kurses für alle Adler-Score Einträge verglichen, ob das dazugehörige cm noch existiert. Dies könnte bei sehr großen Moodle Instanzen zu Performance
   Problemen führen.
   Die folgende Tabelle zeigt einige Testergebnisse für eine verschiedene Anzahl von Lernelementen in einer Moodle Installation. 
   Ausgeführt wurde der Test auf einem Github Actions Runner.

| Anzahl verbleibende Lernelemente mit Adler-Scores | Anzahl der Lernelemente mit Adler-Scores des gelöschten Kurses | Zeit  |
|---------------------------------------------------|----------------------------------------------------------------|-------|
| 100                                               | 10                                                             | 0,01s |
| 100                                               | 100                                                            | 0,07s |
| 1k                                                | 10                                                             | 0,05s |
| 1k                                                | 100                                                            | 0,12s |
| 10k                                               | 10                                                             | 2,4s  |
| 10k                                               | 100                                                            | 2,5s  |
| 100k                                              | 100                                                            | 186s  |

Ein alternativer Ansatz wäre eine redundante Datenhaltung der Kurs-ID. Dies würde die Performanceprobleme umgehen.
