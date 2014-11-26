/*
 Navicat Premium Data Transfer

 Source Server         : Localhost
 Source Server Type    : MySQL
 Source Server Version : 50534
 Source Host           : localhost
 Source Database       : xtions

 Target Server Type    : MySQL
 Target Server Version : 50534
 File Encoding         : utf-8

 Date: 11/26/2014 14:30:04 PM
*/

SET NAMES utf8;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
--  Table structure for `action`
-- ----------------------------
DROP TABLE IF EXISTS `action`;
CREATE TABLE `action` (
  `pk` int(11) NOT NULL AUTO_INCREMENT,
  `device_pk` int(11) NOT NULL,
  `label` text NOT NULL,
  `status` tinyint(4) NOT NULL,
  `url` text NOT NULL,
  PRIMARY KEY (`pk`),
  KEY `device_pk` (`device_pk`),
  CONSTRAINT `action_ibfk_1` FOREIGN KEY (`device_pk`) REFERENCES `device` (`pk`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ----------------------------
--  Table structure for `device`
-- ----------------------------
DROP TABLE IF EXISTS `device`;
CREATE TABLE `device` (
  `pk` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `ipaddress` text NOT NULL,
  `date_created` date NOT NULL,
  `is_inactive` tinyint(4) NOT NULL,
  `ping_port` int(11) NOT NULL,
  PRIMARY KEY (`pk`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ----------------------------
--  Table structure for `log`
-- ----------------------------
DROP TABLE IF EXISTS `log`;
CREATE TABLE `log` (
  `pk` int(11) NOT NULL AUTO_INCREMENT,
  `device_pk` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `status` int(11) NOT NULL,
  PRIMARY KEY (`pk`),
  KEY `device_pk` (`device_pk`),
  CONSTRAINT `log_ibfk_1` FOREIGN KEY (`device_pk`) REFERENCES `device` (`pk`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

SET FOREIGN_KEY_CHECKS = 1;
