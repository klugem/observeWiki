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

const CHECK_INTERVAL = 800;	# check only every x seconds

# domain
const URL = 'observewiki.your-domain.de/'; # domain to index.php
const DOMAIN = 'your-domain.de'; # for mail

# mysql database
const DB_HOST = 'localhost';
const DB_USER = 'db_user';
const DB_PASS = 'db_passwd';
const DB_NAME = 'db_name';

# # needed for month detection! correct language must be SET!
const REGION = 'en_US';

# secure variables for check.php; call with check.php?pw=CHECK_PW&secure=CHECK_SEC
# all strings are possible; are used to check, if the check.php is accessed by the cronjob
const CHECK_PW = 'any$kind5OfLongSting321';
const CHECK_SEC = 'the&same(here=';
?>
