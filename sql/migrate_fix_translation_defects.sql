-- =============================================================================
-- migrate_fix_translation_defects.sql
--
-- Corrective migration for defects found during the language rendering QA pass.
-- Run AFTER migrate_add_languages.sql and migrate_add_translations.sql.
--
-- Covers the four highest-impact issues only. Lower-severity translation
-- quality problems are logged in the QA ticket and left for the team to triage.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- FIX 1: Italian is registered but has zero translation rows.
--
-- 'it' is in the languages table with is_active = 1, so it shows up in the
-- language picker, but nothing was ever seeded for it. Selecting Italian gives
-- a fully untranslated page.
--
-- Deactivating rather than deleting: the row stays available for whoever
-- actually writes the Italian strings later, it just stops being offered.
-- -----------------------------------------------------------------------------

UPDATE `languages`
SET `is_active` = 0
WHERE `code` = 'it';


-- -----------------------------------------------------------------------------
-- FIX 2: Interpolation placeholders were machine-translated.
--
-- The {name} token got translated along with the surrounding text in five
-- languages, so the frontend can no longer find the token to substitute.
-- Users see a literal "{nombre}" instead of their own name.
--
-- Affects auth.success.loginMessage, auth.success.signupMessage,
-- auth.success.socialWelcome, and vendor.dynamic.welcome. Using REPLACE across
-- the whole language rather than naming each key, so any occurrence we missed
-- during review gets caught too.
-- -----------------------------------------------------------------------------

UPDATE `translations`
SET `translation_value` = REPLACE(`translation_value`, '{nombre}', '{name}')
WHERE `language_code` = 'es' AND `translation_value` LIKE '%{nombre}%';

UPDATE `translations`
SET `translation_value` = REPLACE(`translation_value`, '{nome}', '{name}')
WHERE `language_code` = 'pt' AND `translation_value` LIKE '%{nome}%';

UPDATE `translations`
SET `translation_value` = REPLACE(`translation_value`, '{Name}', '{name}')
WHERE `language_code` = 'de' AND `translation_value` LIKE '%{Name}%';

UPDATE `translations`
SET `translation_value` = REPLACE(`translation_value`, '{الاسم}', '{name}')
WHERE `language_code` = 'ar' AND `translation_value` LIKE '%{الاسم}%';

UPDATE `translations`
SET `translation_value` = REPLACE(`translation_value`, '{名前}', '{name}')
WHERE `language_code` = 'ja' AND `translation_value` LIKE '%{名前}%';


-- -----------------------------------------------------------------------------
-- FIX 3: French is missing the shared nav and mobile-menu keys on the admin page.
--
-- Every other page has these for fr. The admin page seed skipped them, so the
-- header and mobile drawer render in English when an admin switches to French.
--
-- Values copied from the fr rows on the about / contact / index pages so the
-- wording stays consistent site-wide.
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `translations` (`language_code`, `page_key`, `translation_key`, `translation_value`) VALUES
  ('fr', 'admin', 'nav.home', 'ACCUEIL'),
  ('fr', 'admin', 'nav.how', 'COMMENT ÇA MARCHE'),
  ('fr', 'admin', 'nav.restaurants', 'RESTAURANTS'),
  ('fr', 'admin', 'nav.contact', 'CONTACT'),
  ('fr', 'admin', 'nav.login', 'S\'INSCRIRE / SE CONNECTER'),
  ('fr', 'admin', 'mobile.home', 'Accueil'),
  ('fr', 'admin', 'mobile.how', 'Comment ça Marche'),
  ('fr', 'admin', 'mobile.restaurants', 'Restaurants'),
  ('fr', 'admin', 'mobile.contact', 'Contact'),
  ('fr', 'admin', 'mobile.login', 'S\'Inscrire / Se Connecter'),
  ('fr', 'admin', 'mobile.theme', 'Changer le Thème'),
  ('fr', 'admin', 'admin.restaurants.searchPlaceholder', 'Rechercher des restaurants...'),
  ('fr', 'admin', 'admin.users.searchPlaceholder', 'Rechercher des utilisateurs...');


-- -----------------------------------------------------------------------------
-- FIX 4: English privacy policy prints the contact email and closing sentence
-- twice.
--
-- Section 8's contact line is split across three keys (item1 / item2 / item3),
-- where item2 is the email and item3 is the closing sentence. The English
-- item1 wrongly contains the entire sentence including both, so the rendered
-- page repeats them.
--
-- French already splits this correctly. Bringing English in line with it.
-- -----------------------------------------------------------------------------

UPDATE `translations`
SET `translation_value` = 'To exercise any of these rights, contact us at '
WHERE `language_code` = 'en'
  AND `page_key` = 'policy'
  AND `translation_key` = 'policy.section8.contact.item1';


-- =============================================================================
-- NOT FIXED HERE - needs team decision, see QA ticket for the full list:
--
--   * Several hundred rows where the "translated" value is just the English
--     string (silent fallback in the generator). Needs real translation, and
--     the generator itself should probably fail loudly instead of falling back.
--
--   * ~60 admin keys that exist only in French (admin.common.*, admin.tables.*,
--     admin.status.*, admin.role.*, and others). These have no English source
--     row, so English is the language that breaks. Someone needs to write the
--     English originals before the other 8 languages can be filled in.
--
--   * Assorted mistranslations: 'HOGAR' for nav.home in Spanish (household,
--     not homepage), '接触' for nav.contact in Japanese (physical contact),
--     'EU IA' for the ID column header in Portuguese, '입술 ID' (lip ID) for
--     the reservation ID header in Korean, 'Libro' for the Book step in
--     Spanish (the noun, not the verb).
--
--   * Dead row: fr / contact / contact.form.placeholder.name. The key used
--     everywhere else is contact.form.name.placeholder.
-- =============================================================================
