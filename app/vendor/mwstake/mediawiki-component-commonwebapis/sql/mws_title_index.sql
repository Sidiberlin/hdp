CREATE TABLE /*$wgDBprefix*/mws_title_index (
	mti_page_id int unsigned NOT NULL PRIMARY KEY,
	mti_namespace INT unsigned NOT NULL,
	mti_title varchar(255) binary NOT NULL,
    mti_displaytitle varchar(255) binary NOT NULL
) /*$wgDBTableOptions*/;
