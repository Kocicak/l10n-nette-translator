CREATE TABLE `localization` (
  `id` int(1) unsigned NOT NULL AUTO_INCREMENT,
  `text_id` int(1) unsigned NOT NULL,
  `lang` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `translation` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `text_id_lang_variant` (`text_id`,`lang`,`variant`),
  KEY `lang` (`lang`),
  CONSTRAINT `x` FOREIGN KEY (`text_id`) REFERENCES `localization_text` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='text translations';

CREATE TABLE `localization_text` (
  `id` int(1) unsigned NOT NULL AUTO_INCREMENT,
  `ns` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `text` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ns` (`ns`,`text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='default texts for translations';
