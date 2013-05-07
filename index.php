<?PHP
# ObserveWiki - Checks if MediaWiki-pages has been changed and informs users via mail in that case.
# Copyright (C) 2013 Michael Kluge (michael.kluge@human-evo.de)
# 
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with this program. If not, see http://www.gnu.org/licenses/


# error settings
error_reporting(E_ERROR);
require_once('include/config.php');
require_once('include/functions.php');
# connect to DB
connect2DB();

# status vars
$error = array();
$succ = array();

# check, if some action should be performed
$side = $_POST['side'];

# aktivate or deaktivate some abo
if('akey' == $_GET['side']) {
	$akey = sql($_GET['akey']);
	$action = sql($_GET['action']);
	
	# deaktivate
	if($action == 'del') {
		$delete = "DELETE FROM observer WHERE akey='".$akey."'";
		mysql_query($delete);
		if(mysql_affected_rows() == 1) {
			$succ[] = 'ObserveWikiabo wurde erfolgreich deaktiviert.';
		}
		else {
			$error[] = 'Diese URL ist ungültig. Entweder wurde das ObserveWikiabo bereits bestätigt oder ein Teil der URL fehlt.';
		}
	}
	# aktivate
	else {
		$update="UPDATE observer set akey='".getAkey()."',status='ok' WHERE akey='".$akey."'";
		mysql_query($update);
		if(mysql_affected_rows() == 1) {
			$succ[] = 'ObserveWikiabo wurde erfolgreich aktiviert.';
		}
		else {
			$error[] = 'Diese URL ist ungültig. Entweder wurde das ObserveWikiabo bereits bestätigt oder ein Teil der URL fehlt.';
		}
	}
}
# add some page to watch
elseif($side == 'addURL') {
	$name = sql($_POST['name']);
	$url = sql($_POST['url']);
	
	# check input
	if(strlen($name) > 3 && filter_var($url, FILTER_VALIDATE_URL)) {
		# check, if ObserveWikiname is free
		$select="SELECT wid, name, url FROM observeWiki WHERE name='$name' OR url='$url'";
		$result=mysql_query($select);
		
		# check, if entry with that name is new
		if(mysql_num_rows($result) == 0) {
			# check URL
			$info = getLastChange($url);
			if($info != -1) {
				# save it		
				$insert = "INSERT INTO observeWiki (name, url, timestamp, oldid, lastCheck) VALUES ('$name', '$url', '".$info['timestamp']."', '".$info['oldid']."', '".time()."')";
				mysql_query($insert);
				if(mysql_affected_rows() == 1) {
					$succ[] = 'Neue ObserveWiki mit dem Namen '.$name.' wurde hinzugefügt und kann nun aboniert werden.';
				}
				else {
					$error[] = 'Unbekannter DB Fehler ist aufgetreten.';
				}
			}
		}
		else {
			$row = mysql_fetch_assoc($result);
			if($url == $row['url']) {
				$error[] = 'Eine ObserveWiki mit der URL '.$url.' existiert bereits unter dem Namen \''.$row['name'].'\'.';
			}
			else {
				$error[] = 'Eine ObserveWiki mit dem Namen \''.$name.'\' existiert bereits.';
			}
		}
		
	}
	else {
		if(strlen($name) <= 3) {
			$error[] = 'Sie müssen einen ObserveWikinamen mit mehr als 3 Zeichen eingeben.';
		}
		if(!filter_var($url, FILTER_VALIDATE_URL)) {
			$error[] = 'Bitte geben Sie eine gültige MediaWiki-PageURL ein.';
		}
	}
}
# add some abo
elseif($side == 'addMail') {
	$wid = sql($_POST['wid']);
	$mail = sql($_POST['mail']);
	
	# check input
	if(is_numeric($wid) && $wid > 0 && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
		# get name of list
		$select = "SELECT name FROM observeWiki WHERE wid='$wid'";
		$result = mysql_query($select);
		
		if(($row = mysql_fetch_assoc($result)) != FALSE) {
			$name = $row['name'];
			# save it
			$select = "SELECT oid FROM observer WHERE mail='$mail' AND wid='$wid'";
			$result = mysql_query($select);

			# check, if entry is new
			if(mysql_num_rows($result) == 0) {
				$akey = getAkey();
				$insert = "INSERT INTO observer (wid, mail, status, akey) VALUES ('$wid', '$mail', 'pending', '".$akey."')";
				$result=mysql_query($insert);

				# check, if entry with that name is new
				if(mysql_affected_rows() == 1) {
					# send the mail
$text='Hallo,

Sie haben gerade die ObserveWiki mit dem Namen \''.$name.'\' für eine MediaWiki-Page aboniert. Um die Anmeldung zu bestätigen, klicken Sie bitte auf diesen Link:

http://'.URL.'?side=akey&akey='.$akey.'

Sollten Sie sich nicht angemeldet haben, ignorieren Sie diese E-Mail einfach.';

					send_mail($mail,'Abonierung von MediaWiki-Page '.$name,$text);
				
					$succ[] = 'An ihre E-Mailadresse '.$mail.' wurde eine Bestätigungsemail verschickt. Sollte demnächst keine E-Mail ankommen, ist diese wohl im Spamordner zu finden.';
				}
				else {
					$error[] = 'Unbekannter DB Fehler ist aufgetreten.';
				}
			}
			else {
				$error[] = 'Diese Kombination ist bereits gespeichert.';
			}
		}
		else {
			$error[] = 'Manipulieren Sie die Links nicht.';
		}
	}
	else {
		if(!is_numeric($wid) || $wid <= 0) {
			$error[] = 'Sie müssen eine gültige ObserveWikie auswählen.';
		}
		if(!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
			$error[] = 'Bitte geben Sie eine gültige E-Mailadresse ein.';
		}
	}
}

######################################################################################
 
# select all watchable lists
$select = "SELECT wid, name, url FROM observeWiki";
$result = mysql_query($select);

$i = 0;
while(($row = mysql_fetch_assoc($result)) != FALSE) {
	$options[$i]['wid'] = $row['wid'];
	$options[$i]['name'] = $row['name'];
	$options[$i]['url'] = $row['url'];
	$i++;
}
if($i == 0) { $options[$i]['wid'] = -1; }
?>

<!DOCTYPE html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<meta name="author" content="Michael Kluge" />
<meta name="generator" content="hand-written" />
<script type="text/javascript">
function setURL(wid) {
	document.getElementById('changeURL').innerHTML = document.getElementById('url_'+wid).value 
	if(wid == -1) {
		document.getElementById('aboList').disabled = "disabled"
	}
	else {
		document.getElementById('aboList').disabled = ""
	}
} 
</script>
<title>ObserveWiki</title>
</head>
<body onLoad="setURL(<?PHP echo $options[0]['wid']; ?>)">
<?PHP
if(count($succ) > 0 || count($error) > 0) {
	echo '<h3>Statusmeldungen</h3>';
	echo '<div style="border: 1px solid black;">';
	if(count($succ) > 0) {
		foreach($succ as $s) {
			echo '<div style="color: green;"><b>'.$s.'</b></div>';
		}
	}
	if(count($error) > 0) {
		foreach($error as $e) {
			echo '<div style="color: red;"><b>'.$e.'</b></div>';
		}
	}
	echo '</div>';
}
?>
<h3>Seiten zum Beobachten hinzufügen</h3>
<form name="addURL" action="index.php" method="post">
<input type="hidden" name="side" value="addURL" />
<table>
<tr><td>ObserveWikiname:</td><td><input type="text" name="name" maxlength="128" style="width: 150px;" /></td><td></td></tr>
<tr><td>MediaWiki-PageURL:</td><td><input type="url" required="required" name="url" maxlength="1024" style="width: 300px;" /></td><td>f.e. http://www.mediawiki.org/w/index.php?title=MediaWiki</td></tr>
<tr><td colspan="3" style="text-align: left"><input type="submit" value="Neue MediaWikiPage zur ObserveWiki hinzufügen" /></td></tr>
</table>
</form>
<br /><br />
<h3>ObserveWiki abonieren</h3>
<form name="addMail" action="index.php" method="post">
<input type="hidden" name="side" value="addMail" />
<table>
<tr><td>E-Mail:</td><td><input type="email" name="mail" required="required" maxlength="128" style="width: 150px;" /></td></tr>
<tr><td>ObserveWikiname:</td><td>
<select name="wid" onchange="setURL(this.value)">
<?PHP 
foreach($options as $option) {
	echo '<option value="'.$option['wid'].'">'.$option['name'].'</option>';
}
?>
</select>
<?PHP
echo '<input type="hidden" id="url_-1" value="" />';
	foreach($options as $option) {
		echo '<input type="hidden" id="url_'.$option['wid'].'" value="'.$option['url'].'"/>';
	}
?>
</td></tr>
<tr><td>MediaWiki-PageURL:</td><td><span id="changeURL">enable JS to see the URLs</span></td></tr>
<tr><td colspan="2" style="text-align: left"><input type="submit" id="aboList" value="Neue MediaWikiPage zur ObserveWiki hinzufügen" /></td></tr>
</table>
</form>
</body>
</html>