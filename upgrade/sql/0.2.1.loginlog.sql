ALTER TABLE #db#`ac_ban_log`
  CHANGE COLUMN `id` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

DROP TABLE IF EXISTS #db#`ac_password_reset_log`;

CREATE TABLE IF NOT EXISTS #db#`ac_password_reset_log` (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id INT UNSIGNED NOT NULL,
  ip_address VARCHAR(46) NOT NULL,
  reset_key CHAR(128) NOT NULL,
  request_date DATETIME NOT NULL,
  reset_date DATETIME,
  PRIMARY KEY ( id ),
  INDEX `_password_reset_log__reset_key_IN` ( reset_key ),
  INDEX `_password_reset_log__account_id_IN` ( account_id )
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8
  COLLATE = utf8_unicode_ci;
