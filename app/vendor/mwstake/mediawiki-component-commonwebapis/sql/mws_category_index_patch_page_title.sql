ALTER TABLE `mws_category_index` ADD `mci_page_title` VARBINARY(255) NOT NULL DEFAULT '' AFTER `mci_title`;
ALTER TABLE `mws_category_index` DROP PRIMARY KEY;
