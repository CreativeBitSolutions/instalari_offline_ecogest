(function () {
  'use strict';

  var STORAGE_KEY = 'agecs.restaurant.ui_lang';
  var DEBUG_KEY = 'agecs.restaurant.i18n_debug';
  var ALLOWED_LANGUAGES = ['ro', 'en', 'vi'];
  var VISUAL_ATTRIBUTES = ['placeholder', 'title', 'aria-label'];
  var dictionary = window.AGECS_RESTAURANT_I18N || { exact: {}, attributes: {}, patterns: [] };
  var applying = false;
  var debugSeen = Object.create(null);
  var queue = [];
  var queueScheduled = false;

  var ignoreSelectors = [
    'script',
    'style',
    'code',
    'pre',
    'noscript',
    'template',
    '[data-i18n-ignore]',
    'input[type="hidden"]',
    '#product-list-container',
    '.receipt-item .name',
    '.receipt-item .obs',
    '.product-name',
    '.produs-denumire',
    '.observatie',
    '.customer-name',
    '.operator-name',
    '.table-name',
    '.masa-title',
    '.modal-header-masa',
    '.tablinks',
    '.masaCard .masa-sub + .masa-sub',
    '.note-body li',
    '.produs-terminat',
    '.produs-neterminat',
    '#product-name-confirm',
    '.product-item .product-info strong',
    '.product-table tbody',
    '.order-products-scroll tbody',
    '.js-existing-note-box tbody',
    '.modal-products-scroll tbody',
    '#detailsModal tbody',
    '#wooSiteOrderModalBody tbody',
    '.wp-detail-value',
    'select[name="cod_masa_target"] option:not(:first-child)',
    '#masaSelect option:not(:first-child)',
    '#category-tabs .category-tab-btn[data-value]:not([data-value="all"]):not([data-value="__MENIURI__"])',
    '.category-wrapper .category-tab-btn[data-value]:not([data-value="all"]):not([data-value="__MENIURI__"])'
  ];
  var ignoreSelector = ignoreSelectors.join(',');

  function getStoredLanguage() {
    var stored = '';
    try {
      stored = window.localStorage.getItem(STORAGE_KEY) || '';
    } catch (error) {
      stored = '';
    }
    return ALLOWED_LANGUAGES.indexOf(stored) !== -1 ? stored : 'ro';
  }

  function debugEnabled() {
    var queryEnabled = false;
    try {
      queryEnabled = new URLSearchParams(window.location.search).get('i18n_debug') === '1';
    } catch (error) {
      queryEnabled = false;
    }

    if (queryEnabled) {
      return true;
    }

    try {
      return window.localStorage.getItem(DEBUG_KEY) === '1';
    } catch (error) {
      return false;
    }
  }

  var language = getStoredLanguage();
  var debug = debugEnabled();

  function isIgnoredElement(element) {
    if (!element || element.nodeType !== 1) {
      return false;
    }
    return Boolean(element.closest(ignoreSelector));
  }

  function isIgnoredNode(node) {
    var parent = node && node.nodeType === 1 ? node : node && node.parentElement;
    return isIgnoredElement(parent);
  }

  function hasRomanianLetters(value) {
    return /[ĂÂÎȘȚăâîșț]/.test(value);
  }

  function reportUnmapped(value) {
    if (!debug || !value || value.length > 160 || debugSeen[value]) {
      return;
    }
    if (!hasRomanianLetters(value) && !/\b(Bon|Masa|Nota|Numerar|Inchide|Sterge|Produs|Cantitate)\b/i.test(value)) {
      return;
    }
    if (/\d{4}-\d{2}-\d{2}|\b\d{6,}\b|@|https?:\/\//i.test(value)) {
      return;
    }
    debugSeen[value] = true;
    console.info('[AGECS i18n] Text UI fără traducere:', value);
  }

  function translateKnownString(value, useAttributeDictionary) {
    if (language === 'ro' || typeof value !== 'string' || value === '') {
      return value;
    }

    var source = useAttributeDictionary ? dictionary.attributes : dictionary.exact;
    var entry = source && source[value] ? source[value] : dictionary.exact[value];
    if (entry && typeof entry[language] === 'string' && entry[language] !== '') {
      return entry[language];
    }

    var decorated = value.match(/^([^\p{L}\p{N}]*)(.+)$/u);
    if (decorated && decorated[1] && dictionary.exact[decorated[2]]) {
      entry = dictionary.exact[decorated[2]];
      if (entry && entry[language]) {
        return decorated[1] + entry[language];
      }
    }

    var patterns = dictionary.patterns || [];
    for (var index = 0; index < patterns.length; index += 1) {
      var pattern = patterns[index];
      pattern.regex.lastIndex = 0;
      if (pattern.regex.test(value)) {
        pattern.regex.lastIndex = 0;
        return value.replace(pattern.regex, pattern[language]);
      }
    }

    reportUnmapped(value);
    return value;
  }

  function translateRuntimeString(value) {
    var match = String(value).match(/^(\s*)(.*?)(\s*)$/s);
    if (!match) {
      return value;
    }
    return match[1] + translateKnownString(match[2], false) + match[3];
  }

  function translateTextNode(node) {
    if (!node || node.nodeType !== 3 || isIgnoredNode(node)) {
      return;
    }

    var original = node.nodeValue;
    var translated = translateRuntimeString(original);
    if (translated !== original) {
      node.nodeValue = translated;
    }
  }

  function translateAttributes(element) {
    if (!element || element.nodeType !== 1 || isIgnoredElement(element)) {
      return;
    }

    VISUAL_ATTRIBUTES.forEach(function (attribute) {
      if (!element.hasAttribute(attribute)) {
        return;
      }
      var original = element.getAttribute(attribute);
      var translated = translateKnownString(original, true);
      if (translated !== original) {
        element.setAttribute(attribute, translated);
      }
    });
  }

  function translateSubtree(root) {
    if (!root || language === 'ro' || isIgnoredNode(root)) {
      return;
    }

    applying = true;
    try {
      if (root.nodeType === 3) {
        translateTextNode(root);
        return;
      }

      if (root.nodeType !== 1 && root.nodeType !== 9 && root.nodeType !== 11) {
        return;
      }

      if (root.nodeType === 1) {
        translateAttributes(root);
      }

      var textWalker = document.createTreeWalker(
        root,
        NodeFilter.SHOW_TEXT,
        {
          acceptNode: function (node) {
            return isIgnoredNode(node) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
          }
        }
      );
      var textNode;
      while ((textNode = textWalker.nextNode())) {
        translateTextNode(textNode);
      }

      if (root.querySelectorAll) {
        root.querySelectorAll('[placeholder],[title],[aria-label]').forEach(translateAttributes);
      }
    } finally {
      applying = false;
    }
  }

  function flushQueue() {
    queueScheduled = false;
    if (applying || language === 'ro') {
      queue = [];
      return;
    }

    var pending = queue;
    queue = [];
    pending.forEach(translateSubtree);
  }

  function enqueue(node) {
    if (!node || isIgnoredNode(node)) {
      return;
    }
    queue.push(node);
    if (!queueScheduled) {
      queueScheduled = true;
      window.setTimeout(flushQueue, 16);
    }
  }

  function createLanguageSwitcher() {
    if (!document.body || document.getElementById('agecs-restaurant-language-switcher')) {
      return;
    }

    var container = document.createElement('div');
    container.id = 'agecs-restaurant-language-switcher';
    container.setAttribute('data-i18n-ignore', '1');
    container.setAttribute('role', 'group');
    container.setAttribute('aria-label', 'Limbă interfață');

    ALLOWED_LANGUAGES.forEach(function (code) {
      var button = document.createElement('button');
      button.type = 'button';
      button.textContent = code.toUpperCase();
      button.setAttribute('aria-pressed', code === language ? 'true' : 'false');
      button.setAttribute('title', code === 'ro' ? 'Română' : (code === 'en' ? 'English' : 'Tiếng Việt'));
      button.addEventListener('click', function () {
        if (code === language) {
          return;
        }
        try {
          window.localStorage.setItem(STORAGE_KEY, code);
        } catch (error) {
          return;
        }
        window.location.reload();
      });
      container.appendChild(button);
    });

    document.body.appendChild(container);
  }

  function installRuntimeWrappers() {
    var nativeAlert = window.alert.bind(window);
    var nativeConfirm = window.confirm.bind(window);

    window.alert = function (message) {
      return nativeAlert(translateRuntimeString(String(message)));
    };
    window.confirm = function (message) {
      return nativeConfirm(translateRuntimeString(String(message)));
    };
  }

  function validateDictionary() {
    if (!debug) {
      return;
    }
    Object.keys(dictionary.exact || {}).forEach(function (key) {
      var entry = dictionary.exact[key] || {};
      if (!entry.en || !entry.vi) {
        console.warn('[AGECS i18n] Intrare incompletă:', key);
      }
    });
  }

  function start() {
    document.documentElement.setAttribute('lang', language);
    createLanguageSwitcher();
    validateDictionary();

    if (language === 'ro') {
      return;
    }

    installRuntimeWrappers();
    if (document.title) {
      document.title = translateKnownString(document.title, false);
    }
    translateSubtree(document.body);

    var observer = new MutationObserver(function (mutations) {
      if (applying) {
        return;
      }
      mutations.forEach(function (mutation) {
        if (mutation.type === 'characterData') {
          enqueue(mutation.target);
          return;
        }
        mutation.addedNodes.forEach(enqueue);
      });
    });
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      characterData: true
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
}());
