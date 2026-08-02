(function () {
  const STORAGE_KEY = 'reservehub-language';

  // Synchronous fallback used before the API responds
  const FALLBACK_LANGUAGES = [
    { code: 'en', name: 'English' },
    { code: 'fr', name: 'Français' },
  ];
  let supportedLanguages = FALLBACK_LANGUAGES.map((l) => l.code);

  // Single source of truth for the selector string — avoids repetition across functions
  const LANGUAGE_SELECTOR = [
    '.index-language-localization',
    '.admin-language-localization',
    '.about-language-localization',
    '.terms-language-localization',
    '.policy-language-localization',
    '.contact-language-localization',
    '.help-language-localization',
    '.auth-language-localization',
    '.search-language-localization',
    '.vendor-language-localization',
    '.profile-language-localization',
    '.reset-language-localization',
    '.restaurant-language-localization',
  ].join(', ');

  const CLASS_TO_PAGE = {
    'index-language-localization': 'index',
    'admin-language-localization': 'admin',
    'about-language-localization': 'about',
    'terms-language-localization': 'terms',
    'policy-language-localization': 'policy',
    'contact-language-localization': 'contact',
    'help-language-localization': 'help-center',
    'auth-language-localization': 'login-signup',
    'search-language-localization': 'search',
    'vendor-language-localization': 'vendor',
    'profile-language-localization': 'profile',
    'reset-language-localization': 'reset-password',
    'restaurant-language-localization': 'restaurant',
  };

  // Validates against the live supportedLanguages list; handles browser locale tags like 'fr-CA'
  function normalizeLanguage(lang) {
    if (!lang) return 'en';
    const normalized = String(lang).trim().toLowerCase();
    if (supportedLanguages.includes(normalized)) return normalized;
    const prefix = supportedLanguages.find((code) => normalized.startsWith(code));
    return prefix ?? 'en';
  }

  function getStoredLanguage() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      return normalizeLanguage(saved);
    } catch (error) {
      return 'en';
    }
  }

  function getPreferredLanguage() {
    const saved = getStoredLanguage();
    if (saved !== 'en' || localStorage.getItem(STORAGE_KEY)) {
      return saved;
    }
    const browserLang =
      navigator.languages?.[0] ||
      navigator.language ||
      navigator.userLanguage ||
      '';
    return normalizeLanguage(browserLang);
  }

  function getPageKey() {
    for (const [cls, page] of Object.entries(CLASS_TO_PAGE)) {
      if (document.querySelector(`.${cls}`)) return page;
    }
    return null;
  }

  // Three-tier fallback: DB → local JSON (requested lang) → local JSON (English)
  async function fetchTranslationsWithFallback(lang, page) {
    const attempts = [
      `../api/language.php?lang=${encodeURIComponent(lang)}&page=${encodeURIComponent(page)}`,
      `../json/${page}-${lang}.json`,
      `../json/${page}-en.json`,
    ];

    for (const url of attempts) {
      try {
        const res = await fetch(url);
        if (res.ok) return await res.json();
      } catch (_) {}
    }

    return {};
  }

  function applyTranslations(translations) {
    document.querySelectorAll('[localization-key]').forEach((element) => {
      const key = element.getAttribute('localization-key');
      const value = translations[key];
      if (!value) return;

      const target = element.getAttribute('localization-target') || 'text';

      if (target === 'placeholder') {
        element.setAttribute('placeholder', value);
        return;
      }
      if (target === 'content') {
        element.setAttribute('content', value);
        return;
      }
      if (target === 'html' || target === 'title') {
        element.innerHTML = value;
        return;
      }

      element.textContent = value;
    });
  }

  // Exposed so callers can inject options themselves if needed
  function populateLanguageSelectors(languages) {
    document.querySelectorAll(LANGUAGE_SELECTOR).forEach((select) => {
      const current = select.value;
      select.innerHTML = languages
        .map(
          ({ code, name }) =>
            `<option value="${code}"${code === current ? ' selected' : ''}>${name}</option>`,
        )
        .join('');
    });
  }

  async function fetchSupportedLanguages() {
    try {
      const res = await fetch('../api/language.php?action=languages');
      if (!res.ok) throw new Error(`Status: ${res.status}`);
      const langs = await res.json();
      if (Array.isArray(langs) && langs.length) {
        supportedLanguages = langs.map((l) => l.code);
        return langs;
      }
    } catch (_) {}
    return FALLBACK_LANGUAGES;
  }

  async function loadLanguage(lang, { persist = true } = {}) {
    const normalized = normalizeLanguage(lang);
    const page = getPageKey();

    if (!page) return normalized;

    if (persist) {
      try {
        localStorage.setItem(STORAGE_KEY, normalized);
      } catch (error) {
        console.warn('Unable to save language preference:', error);
      }
    }

    document.documentElement.lang = normalized;
    document.querySelectorAll(LANGUAGE_SELECTOR).forEach((select) => {
      select.value = normalized;
    });

    const translations = await fetchTranslationsWithFallback(normalized, page);
    window.reservehubTranslations = translations;
    applyTranslations(translations);

    window.dispatchEvent(
      new CustomEvent('reservehub:languageChanged', {
        detail: { language: normalized, translations },
      }),
    );

    if (typeof window.updateSupportStatus === 'function') {
      window.updateSupportStatus(normalized);
    }

    return normalized;
  }

  function initializeLanguageSelectors() {
    document.querySelectorAll(LANGUAGE_SELECTOR).forEach((select) => {
      select.addEventListener('change', () => {
        loadLanguage(select.value, { persist: true });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const languages = await fetchSupportedLanguages();
    populateLanguageSelectors(languages);
    initializeLanguageSelectors();
    loadLanguage(getPreferredLanguage(), { persist: false });
  });

  window.setLanguage = loadLanguage;
  window.getCurrentLanguage = () => getStoredLanguage();
  window.populateLanguageSelectors = populateLanguageSelectors;
})();
