# licence: GNU General Public License v3

# requirements: 
- php 5.3 or greater with curl and working mail 
- mysql database

# works with: 
- media wiki version 1.15
- other versions may be supported too but only 1.15 was tested

# install it:
- create mysql database and import observeWiki.sql
- edit the 'include/config.php' file
- copy all files except 'observeWiki.sql' to your web server
- add cronjob which calls protected/check.php?pw=CHECK_PW&secure=CHECK_SEC every X minutes
- test it!

# todo:
- see TODO.txt file!