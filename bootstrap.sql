USE core_translog;


TRUNCATE TABLE taxrates;

INSERT taxrates ( id, rate, description )
VALUES ( 1, 0.035, 'NJ UEZ' );
