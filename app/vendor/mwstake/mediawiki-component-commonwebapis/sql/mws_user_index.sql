CREATE TABLE /*$wgDBprefix*/mws_user_index (
	mui_user_id int unsigned NOT NULL PRIMARY KEY,
	mui_user_name varchar(255) binary NOT NULL,
	mui_user_real_name varchar(255) binary NOT NULL
) /*$wgDBTableOptions*/;
