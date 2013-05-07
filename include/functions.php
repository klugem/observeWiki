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


# settings work with MediaWiki Version 1.15
const ID_OF_HISTORY = 'pagehistory';
const HISTORY_USER_CLASS = 'history-user';
const COMMENT_USER_CLASS = 'comment';
const USER_REMOVE = '(talk | contribs)';
const TALK_REMOVE = '(talk)';
const COMMENT_REMOVE ='[\(\)]';
const OLDID_PATTERN = 'oldid=([0-9]+)';
const SHOW_HISTORY = 'action=history';
const LINK_TAG = 'a';
const SPAN_TAG = 'span';
const CLASS_ATTR = 'class';
const HREF_ATTR = 'href';
const SPLIT_DAY_AND_TIME = ', ';
const SPLIT_HOUR_AND_MINUTE = ':';
const SPLIT_DAY = ' ';

/* connects to the mysql database */
function connect2DB() {
	global $db_connected;
	# connect only once
	if($db_connected != 1) {
		if(!@mysql_connect(DB_HOST, DB_USER, DB_PASS)) {
			$db_connected = -1;
			die('Cannot connect to DB.');
		}
		# select correct db
		@mysql_select_db(DB_NAME) or die ("Die Datenbank existiert nicht.");
		mysql_set_charset('utf8');
		$db_connected = 1;
	}
}

/* avoid SQL-/HTML-Injection */
function sql($input) {
	if (get_magic_quotes_gpc()) $input = stripslashes($input);
	if (!is_int($input)) {
		$input = mb_convert_encoding($input, 'UTF-8', 'UTF-8');
		$input = htmlentities($input, ENT_QUOTES, 'UTF-8');
		$input = mysql_real_escape_string($input);
	}
	return $input;
}

/* get content of a url using curl */
function curl_get_contents($url) {
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
	$data = curl_exec($curl);
	curl_close($curl);
	return $data;
}

/* generate a random salt */
function getSalt() {
	return sha1(md5(time()).rand());
}

/* generate a random 32 char key */
function getAkey() {
	return substr(getSalt(), 0, 32);
}

/* sends a mail */
function send_mail($email,$topic,$text) {
	$headers = "MIME-Version: 1.0\n";
	$headers .= "Content-type: text/plain; charset=UTF-8\n";
	$headers .= "X-Mailer: php\n";
	$headers .= "From: \"observeWiki\" <observewiki@".DOMAIN.">\n";
	$headers .= 'Date: ' . date('r');
	$temp_ary = explode(' ', (string) microtime());
	$headers .= 'Message-Id: <' . date('YmdHis') . '.' . substr($temp_ary[0],2) . '@'.DOMAIN.'>';
	mail($email, '=?UTF-8?B?'.base64_encode('observewiki: '.$topic).'?=', $text, $headers);
}

/* find the last change in a media wiki page via its history */
function getLastChange($mediaWikiPageURL) {
	global $error;
	
	if(strpos($mediaWikiPageURL, '?') == FALSE) {
		$url = $mediaWikiPageURL.'?'.SHOW_HISTORY;
	}
	else {
		$url = $mediaWikiPageURL.'&'.SHOW_HISTORY;
	}

	# get content of the side
	if($html = curl_get_contents($url)) { #file_get_contents
		# create XML parser
		$doc = new DomDocument;
		$doc->strictErrorChecking = FALSE; 	# disable xml checking
		$doc->loadHTML($html); # load the html file
				
		# find node with the id "pagehistory"
		$changeUL = $doc->getElementById(ID_OF_HISTORY);

		# check, if node with that id was found
		if($changeUL instanceof DOMElement) {
			# get the current version
			$current = $changeUL->firstChild; 
			$info = getEditInformation($current);
			return $info;
		}
		else {
			$error[] = "Could not find history node with id ".ID_OF_HISTORY.". Perhaps wrong version of mediawiki or wrong URL was entered.";
		}
	}
	else {
		$error[] = "Could not load url: $mediaWikiPageURL<br>Protokoll registered?URL correct?";
	}
	return -1;
}

/* gets the information about the a change */
function getEditInformation($liNode) {
	global $error;
	# get links
	$linkList = $liNode->getElementsByTagName(LINK_TAG);
	$info = getTimeStampOutOfAllLinks($linkList);
	
	# check, if date of change was found
	if($info != -1) {
		# get additional info, if can be found
		$info = getAdditionalInformation($liNode->getElementsByTagName(SPAN_TAG), $info);
		return $info;
	}
	else {
		$error[] = "Could not found Link with last change date. Perhaps wrong version of mediawiki or wrong URL was entered.";
	}
}

/* gets additional information like username and comment */
function getAdditionalInformation($spanList, $info) {
	# check for span with class 'history-user' and 'comment' HISTORY_USER_CLASS COMMENT_USER_CLASS
	for($i=0; $i<$spanList->length; $i++) {
		$item = $spanList->item($i);
		# get the class attribute
		$classAttr = $item->attributes->getNamedItem(CLASS_ATTR);
		# check, if user css class
		if($classAttr->value == HISTORY_USER_CLASS) {
			$info['user'] = str_ireplace(USER_REMOVE, '', $item->textContent);
			$info['user'] = str_ireplace(TALK_REMOVE, '', $info['user']);
		}
		# check, if comment css class
		elseif($classAttr->value == COMMENT_USER_CLASS) {
			$info['comment'] = str_ireplace(TALK_REMOVE, '', $item->textContent);
			$info['comment'] = preg_replace('/'.COMMENT_REMOVE.'/', '', $info['comment']);
		}
	}
	return $info;
}

/* try to extract a timestamp out of all the links in the node; makes it a bit independent of the used version / design */
function getTimeStampOutOfAllLinks($linkList) {
	# check for link containg date in format 'HH:MM, DD germanMonthName YYYY'
	for($i=0; $i<$linkList->length; $i++) {
		$item = $linkList->item($i);
		$name = $item->textContent;
		# check for timestamp
		$timestamp = getTimestamp($name);
		if($timestamp != -1) { 
			$info['timestamp'] = $timestamp;
		
			# search for oldid
			$search = $item->attributes->getNamedItem(HREF_ATTR)->value;
			if(preg_match('/'.OLDID_PATTERN.'/', $search, $hits)) {
				$info['oldid'] = $hits[1];
			}
			return $info;
		}
	}
	return -1;
}

/* gets a timestamp out of a formated date HH:MM, DD englishMonthName YYYY' */
function getTimestamp($string) {
	$timestamp = -1; # default value
	# try to split at ', '
	$tmp = explode(SPLIT_DAY_AND_TIME, $string);
	if(count($tmp) == 2) {
		$time = $tmp[0];
		$day = $tmp[1];
		
		# try to split time
		$tmp = explode(SPLIT_HOUR_AND_MINUTE, $time);
		if(count($tmp) == 2) {
			$hour = $tmp[0];
			$minute = $tmp[1];
		
			# try to split day
			$tmp = explode(SPLIT_DAY, $day);
			if(count($tmp) == 3) {
				$day = $tmp[0];
				$month = $tmp[1];
				$year = $tmp[2];
				
				# create timestamp
				$timestamp = mktime($hour, $minute, 0, getMonthNumber($month), $day, $year);
			}
		}
	}	
	return $timestamp;
}

/* gets the month number out of a german month name */
function getMonthNumber($month) {
	for($i=0; $i<=12; $i++) {
		if($month == strftime('%B', mktime(12, 0, 0, $i, 1, 1970))) {
			return $i;
		}
	}
	return -1;
}
?>