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


# works with media wiki version 1.15.5-2squeeze4 
# other versions are not explicitly supported but may work as well

require_once('../include/config.php');

if($_GET['pw']== CHECK_PW && $_GET['secure'] == CHECK_SEC) {
	# error settings
	error_reporting(E_ERROR);

	# try to disable time limits
	ignore_user_abort(true);
	set_time_limit(0);
	
	require_once('../include/functions.php');
	connect2DB();
	# set time for german dates
	setlocale(LC_ALL, REGION);
	echo '<pre>';

	# load all the pages, which should be monitored
	$select = "SELECT wid, name, url, timestamp, oldid FROM observeWiki WHERE lastCheck+".CHECK_INTERVAL."<".time();
	$result = mysql_query($select);

	while(($row = mysql_fetch_assoc($result)) != FALSE) {
		$lastChange = $row['timestamp'];
		$info = getLastChange($row['url']);
		echo 'checking '.$row['name'].'...<br/>';
		# check for change
		if($lastChange < $info['timestamp']) {
			# inform observers
			informObservers($row['wid'], $row['name'], $row['url'], $row['oldid'], $info['timestamp'], $info['user'], $info['comment'], $info['oldid']);
			# update the information about the wiki page
			$update = "UPDATE observeWiki SET timestamp='".$info['timestamp']."', oldid='".$info['oldid']."' WHERE wid='".$row['wid']."'";
			mysql_query($update);
		}
	}
	# update check time
	$update = "UPDATE observeWiki SET lastCheck='".time()."' WHERE lastCheck+".CHECK_INTERVAL."<".time();
	mysql_query($update);
}
exit;

/* send mail to all observers */
function informObservers($wid, $name, $url, $oldid, $timestamp, $user, $comment, $newid) {
	echo 'informing '.$name.'...<br/>';
	# select all with abo
	$select = "SELECT mail, akey FROM observer WHERE wid='$wid' AND status='ok'";
	$result = mysql_query($select);
	# calculate date
	$date = date('d.m.Y, H:i', $timestamp).' Uhr';
	
	# get base URL
	$tmp = explode('?', $url);
	$urlbase = $tmp[0];

	while(($row = mysql_fetch_assoc($result)) != FALSE) {
		$akey = $row['akey'];
		$mail = $row['mail'];
		# action=historysubmit
$text="Hallo,

die MediaWiki-Page mit dem Namen '".$name."' wurde verändert.

URL: $url
DIFF: $urlbase?diff=$newid&oldid=$oldid

Weitere Informationen, falls vorhanden:
- zuletzt geändert von: $user
- Datum: $date
- Kommentar: $comment

------------------------------------------------------
Möchten Sie in Zukunft nicht mehr über Änderungen benachrichtigt werden, klicken Sie hier: http://".URL."?side=akey&akey=$akey&action=del";
		echo 'send mail to '.$mail.'...<br/>';
		send_mail($mail,'Änderung von MediaWiki-Page '.$name, $text);
	}
}  
?>