CREATE DATABASE IF NOT EXISTS core_opdata;
CREATE DATABASE IF NOT EXISTS core_server;
CREATE DATABASE IF NOT EXISTS core_translog;


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


GRANT SELECT, INSERT, CREATE, DROP, ALTER, LOCK TABLES
ON core_opdata.*
TO office@'192.168.1.%';

GRANT SELECT
ON core_translog.*
TO office@'192.168.1.%';


USE core_translog;

CREATE TABLE IF NOT EXISTS taxrates (
	id int(11) NOT NULL DEFAULT 0,
 	rate float DEFAULT NULL,
 	description varchar(50) DEFAULT NULL,
PRIMARY KEY (id)
);

TRUNCATE TABLE taxrates;

INSERT taxrates
	( id, rate, description )
VALUES
	( 1, 0.06625, 'Tax' ),
	( 2, 0.06625, 'UEZ' )
;
