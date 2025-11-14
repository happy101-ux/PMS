-- phpMyAdmin SQL Dump
-- Database: pms
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ------------------------
-- Table structure for userlogin
-- ------------------------
CREATE TABLE userlogin (
  id int(11) NOT NULL AUTO_INCREMENT,
  officerid varchar(20) NOT NULL,
  status varchar(50) NOT NULL,
  password varchar(50) NOT NULL,
  last_name varchar(50) NOT NULL,    -- previously surname
  first_name varchar(50) NOT NULL,   -- previously othernames
  date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- NEW COLUMN
  PRIMARY KEY (id),
  UNIQUE KEY (officerid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------
-- Data for userlogin
-- Passwords = SHA1(officerid)
-- ------------------------
INSERT INTO userlogin (officerid, status, password, last_name, first_name, date_added) VALUES
('1001', 'Chief Inspector', SHA1('1001'), 'Phiri', 'Mwila', CURRENT_TIMESTAMP),
('1002', 'Admin',           SHA1('1002'), 'Banda', 'Chileshe', CURRENT_TIMESTAMP),
('1003', 'Sergeant',        SHA1('1003'), 'Zimba', 'Mubanga', CURRENT_TIMESTAMP),
('1004', 'Inspector',       SHA1('1004'), 'Mwamba', 'Lungu', CURRENT_TIMESTAMP),
('1005', 'Constable',       SHA1('1005'), 'Ngoma', 'Kapasa', CURRENT_TIMESTAMP),
('1006', 'Constable',       SHA1('1006'), 'Muleya', 'Kabwe', CURRENT_TIMESTAMP),
('1007', 'Chief Inspector', SHA1('1007'), 'Sakala', 'Mutale', CURRENT_TIMESTAMP),
('1008', 'Sergeant',        SHA1('1008'), 'Mulenga', 'Tembo', CURRENT_TIMESTAMP);

-- ------------------------
-- Table structure for case_table
-- ------------------------
CREATE TABLE case_table (
  caseid int(11) NOT NULL AUTO_INCREMENT,
  officerid varchar(20) NOT NULL,
  casetype varchar(100) NOT NULL,
  status varchar(50) NOT NULL,
  description text,
  PRIMARY KEY (caseid),
  FOREIGN KEY (officerid) REFERENCES userlogin(officerid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------
-- Table structure for complainant
-- ------------------------
CREATE TABLE complainant (
  case_id varchar(20) NOT NULL,
  comp_name varchar(100) NOT NULL,
  tel varchar(10) NOT NULL,
  occupation varchar(20) NOT NULL,
  region varchar(50) NOT NULL,
  district varchar(100) NOT NULL,
  loc varchar(50) NOT NULL,
  addrs varchar(100) NOT NULL,
  age int(3) NOT NULL,
  gender varchar(6) NOT NULL,
  date_added timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------
-- Table structure for crime_type
-- ------------------------
CREATE TABLE crime_type (
  id int(11) NOT NULL AUTO_INCREMENT,
  des varchar(50) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------
-- Table structure for investigation
-- ------------------------
CREATE TABLE investigation (
  id int(11) NOT NULL AUTO_INCREMENT,
  case_id varchar(20) NOT NULL,
  investigator varchar(20) NOT NULL,
  statement2 text NOT NULL,
  assigned_date timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  status2 varchar(100) NOT NULL,
  completed_date varchar(20) NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (investigator) REFERENCES userlogin(officerid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------
-- Table structure for resources
-- ------------------------
CREATE TABLE resources (
  id INT(11) NOT NULL AUTO_INCREMENT,
  title VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  uploaded_by VARCHAR(50),
  upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------
-- Table structure for duties
-- ------------------------
CREATE TABLE duties (
  dutyid int(11) NOT NULL AUTO_INCREMENT,
  officerid varchar(20) NOT NULL,
  task varchar(200) NOT NULL,
  dutydate date NOT NULL,
  PRIMARY KEY (dutyid),
  FOREIGN KEY (officerid) REFERENCES userlogin(officerid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
