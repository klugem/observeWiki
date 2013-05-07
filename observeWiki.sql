CREATE TABLE IF NOT EXISTS `observer` (
  `oid` int(11) NOT NULL AUTO_INCREMENT,
  `wid` int(11) NOT NULL,
  `mail` varchar(128) NOT NULL,
  `status` varchar(16) NOT NULL,
  `akey` varchar(32) NOT NULL,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `observeWiki` (
  `wid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `url` varchar(1024) NOT NULL,
  `timestamp` varchar(14) NOT NULL,
  `oldid` int(11) NOT NULL,
  `lastCheck` varchar(14) NOT NULL,
  PRIMARY KEY (`wid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;