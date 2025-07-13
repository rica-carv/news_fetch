CREATE TABLE news_fetch (
  id smallint(10) unsigned NOT NULL auto_increment,
  src_name varchar(200) NOT NULL,
  src_url varchar(200) NOT NULL,
  src_xpath_link VARCHAR(255) DEFAULT NULL,
  src_xpath_title VARCHAR(255) DEFAULT NULL,
  src_xpath_body VARCHAR(255) DEFAULT NULL;
  src_xpath_img VARCHAR(255) DEFAULT NULL;
  src_last_url VARCHAR(255) DEFAULT NULL;
  src_last_run int(8) unsigned;
  src_cat tinyint(1) NOT NULL,
  src_active tinyint(1) NOT NULL,
  PRIMARY KEY (id),
  KEY id (id)
) ENGINE=InnoDB;
