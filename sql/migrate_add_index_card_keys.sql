-- =============================================================================
-- migrate_add_index_card_keys.sql
-- Adds the dynamic restaurant card translation keys used by main.js.
-- =============================================================================

INSERT IGNORE INTO `translations` (`language_code`, `page_key`, `translation_key`, `translation_value`) VALUES
  ('en', 'index', 'index.featured.noRestaurants', 'No restaurants found in this category.'),
  ('en', 'index', 'index.featured.serverError',   'Could not connect to the server.'),
  ('en', 'index', 'index.featured.noImage',        'No Image Available'),
  ('en', 'index', 'index.featured.reserve',        'Reserve a Table'),
  ('en', 'index', 'index.featured.openNow',        'Open now'),

  ('fr', 'index', 'index.featured.noRestaurants', 'Aucun restaurant trouvé dans cette catégorie.'),
  ('fr', 'index', 'index.featured.serverError',   'Impossible de se connecter au serveur.'),
  ('fr', 'index', 'index.featured.noImage',        'Aucune Image Disponible'),
  ('fr', 'index', 'index.featured.reserve',        'Réserver une Table'),
  ('fr', 'index', 'index.featured.openNow',        'Ouvert maintenant');
