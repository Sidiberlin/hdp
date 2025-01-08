CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/bmbf_index_pages(
    `bmbf_page` VARCHAR(255) NOT NULL PRIMARY KEY,
    `bmbf_action` VARCHAR(16) NOT NULL,
    `bmbf_data` MEDIUMBLOB NOT NULL
) /*$wgDBTableOptions*/;
