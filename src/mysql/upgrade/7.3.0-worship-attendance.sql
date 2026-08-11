-- Worship greeter attendance (person + local service date)
CREATE TABLE `worship_attend` (
  `wa_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` mediumint(9) unsigned NOT NULL,
  `attend_date` date NOT NULL,
  `checkin_datetime` timestamp NULL DEFAULT NULL,
  `checked_in_by_id` mediumint(9) unsigned DEFAULT NULL,
  PRIMARY KEY (`wa_id`),
  UNIQUE KEY `worship_attend_per_date` (`person_id`,`attend_date`),
  KEY `worship_attend_date` (`attend_date`),
  CONSTRAINT `worship_attend_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
