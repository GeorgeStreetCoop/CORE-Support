CREATE DATABASE IF NOT EXISTS core_opdata;
CREATE DATABASE IF NOT EXISTS core_server;
CREATE DATABASE IF NOT EXISTS core_translog;


DROP USER IF EXISTS lane@localhost;

CREATE USER lane@localhost
IDENTIFIED BY PASSWORD '*495ACE53FFADA4FA19845AD2C9C2BDDA676CBFBE';

GRANT
	SELECT, INSERT, UPDATE, DELETE,
	CREATE, DROP, INDEX, ALTER,
	CREATE TEMPORARY TABLES, CREATE VIEW, EVENT, TRIGGER,
	SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EXECUTE
ON core_opdata.*
TO lane@localhost;

GRANT
	SELECT, INSERT, UPDATE, DELETE,
	CREATE, DROP, INDEX, ALTER,
	CREATE TEMPORARY TABLES, CREATE VIEW, EVENT, TRIGGER,
	SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EXECUTE
ON core_server.*
TO lane@localhost;

GRANT
	SELECT, INSERT, UPDATE, DELETE,
	CREATE, DROP, INDEX, ALTER,
	CREATE TEMPORARY TABLES, CREATE VIEW, EVENT, TRIGGER,
	SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EXECUTE
ON core_translog.*
TO lane@localhost;


DROP USER IF EXISTS office@'192.168.1.%';

CREATE USER office@'192.168.1.%'
IDENTIFIED BY PASSWORD '*D65D4DCBD3434E7355CC23798E9BBDA85B441A48';

GRANT ALL PRIVILEGES
ON core_opdata.*
TO office@'192.168.1.%';

GRANT ALL PRIVILEGES
ON core_server.*
TO office@'192.168.1.%';

GRANT ALL PRIVILEGES
ON core_translog.*
TO office@'192.168.1.%';


USE core_translog;


CREATE TABLE IF NOT EXISTS taxrates (
	id int(11) NOT NULL DEFAULT 0,
 	rate float DEFAULT NULL,
 	description varchar(50) DEFAULT NULL,
PRIMARY KEY (id)
)

TRUNCATE TABLE taxrates;

INSERT taxrates ( id, rate, description )
VALUES ( 1, 0.035, 'UEZ' );
