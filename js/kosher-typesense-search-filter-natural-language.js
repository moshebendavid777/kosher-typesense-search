/**
 * File: kosher-typesense-search-filter-natural-language.js
 * Description: Enables Typesense Natural Language Search for the existing filtered-search UI.
 * Author: Kosher Dev Team
 *
 * This intentionally leaves kosher-typesense-search-filter.js in charge of the UI,
 * URL state, rendering, facets and pagination. On /search-ai it augments only that
 * script's multi-search request so free-form queries are parsed by Typesense's NLS
 * model. Explicit sidebar filters are retained and combined with generated filters.
 */

(function () {
  'use strict';

  const nativeFetch = window.fetch.bind(window);
  const naturalLanguageConfig = window.kosherNaturalLanguageConfig || {};
  const searchAction = naturalLanguageConfig.searchAction || 'kosher_typesense_search';
  const translateAction = naturalLanguageConfig.translateAction || 'kosher_typesense_nl_translate';
  const modelId = typeof naturalLanguageConfig.modelId === 'string'
    ? naturalLanguageConfig.modelId.trim()
    : '';
  const debugEnabled = naturalLanguageConfig.debugEnabled === true
    || naturalLanguageConfig.debugEnabled === '1';
  const recipeRankingFieldsAvailable = naturalLanguageConfig.recipeRankingFieldsAvailable === true
    || naturalLanguageConfig.recipeRankingFieldsAvailable === '1';
  let lastNaturalLanguageSearches = [];
  let lastFallbackDebug = [];
  let lastOriginalNaturalLanguageQuery = '';
  let activeNaturalLanguageRequests = 0;

  function installNaturalLanguageSubmitBridge() {
    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return;

      const submittedInput = event.target && event.target.closest
        ? event.target.closest('input[type="search"], input[type="text"]')
        : null;
      const resultsSearchInput = document.querySelector('.kosher-search-filter-form .form-control');

      if (!submittedInput || !resultsSearchInput) return;

      const isSearchInput = submittedInput.id === 'searchInput'
        || submittedInput.classList.contains('js-kayco-simple-search-input');

      if (!isSearchInput) return;

      const query = submittedInput.value.trim();
      event.preventDefault();
      event.stopImmediatePropagation();
      resultsSearchInput.value = query;

      const url = new URL(window.location.href);
      url.searchParams.delete('act');
      url.searchParams.delete('op');
      if (query) url.searchParams.set('q', query);
      else url.searchParams.delete('q');
      window.history.replaceState({}, '', url);

      resultsSearchInput.dispatchEvent(new CustomEvent('kosher:natural-language-search', {
        detail: { query },
      }));
      resultsSearchInput.blur();
    }, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', installNaturalLanguageSubmitBridge, { once: true });
  } else {
    installNaturalLanguageSubmitBridge();
  }

  function setNaturalLanguageLoading(isLoading) {
    let indicator = document.getElementById('kosher-natural-language-search-loading');

    if (!indicator && isLoading) {
      indicator = document.createElement('div');
      indicator.id = 'kosher-natural-language-search-loading';
      indicator.className = 'kosher-natural-language-search-loading';
      indicator.setAttribute('role', 'status');
      indicator.setAttribute('aria-live', 'polite');
      indicator.innerHTML = '<span class="kosher-natural-language-search-loading__spinner" aria-hidden="true"></span><span>Searching…</span>';
      document.body.appendChild(indicator);

      if (!document.getElementById('kosher-natural-language-search-loading-styles')) {
        const style = document.createElement('style');
        style.id = 'kosher-natural-language-search-loading-styles';
        style.textContent = `@keyframes kosher-nls-spin{to{transform:rotate(360deg)}}.kosher-natural-language-search-loading{position:fixed;z-index:99998;left:50%;bottom:28px;display:flex;align-items:center;gap:10px;transform:translate(-50%,16px);padding:12px 18px;border-radius:999px;background:#7a147a;color:#fff;box-shadow:0 10px 30px rgba(55,12,60,.3);font:600 15px/1.2 Inter,Arial,sans-serif;opacity:0;visibility:hidden;transition:opacity .18s ease,transform .18s ease,visibility .18s}.kosher-natural-language-search-loading.is-visible{opacity:1;visibility:visible;transform:translate(-50%,0)}.kosher-natural-language-search-loading__spinner{width:19px;height:19px;border:3px solid rgba(255,255,255,.38);border-top-color:#fff;border-radius:50%;animation:kosher-nls-spin .75s linear infinite}@media(max-width:700px){.kosher-natural-language-search-loading{bottom:18px}}`;
        document.head.appendChild(style);
      }
    }

    if (!indicator) {
      return;
    }

    if (isLoading) {
      activeNaturalLanguageRequests += 1;
      indicator.classList.add('is-visible');
      return;
    }

    activeNaturalLanguageRequests = Math.max(0, activeNaturalLanguageRequests - 1);
    if (activeNaturalLanguageRequests === 0) {
      indicator.classList.remove('is-visible');
    }
  }

  function getCollectionSlug(collection) {
    const name = String(collection || '');
    return ['recipes', 'menus', 'articles', 'episodes'].find((slug) => name === slug || name.endsWith(`_${slug}`)) || '';
  }

  function getNaturalLanguageQueryBy(collection, fallback) {
    const collectionName = String(collection || '');

    if (collectionName.endsWith('_recipes') || collectionName === 'recipes') {
      return [
        'title',
        'chefs',
        'tags',
        'ingredients',
        'occasions',
        'preference',
        'diets',
        'contains_allergents',
        'difficulty',
        'sources',
        'recipe_category',
        'cuisine',
      ].join(',');
    }

    if (collectionName.endsWith('_menus') || collectionName === 'menus') {
      return [
        'title',
        'description',
        'categories',
        'menus_categories',
        'holidays',
        'section_titles',
        'recipe_titles',
        'card_text',
        'author_name',
      ].join(',');
    }

    if (collectionName.endsWith('_articles') || collectionName === 'articles') {
      return 'title,author,article_sub_category,tags';
    }

    if (collectionName.endsWith('_episodes') || collectionName === 'episodes') {
      return 'title,chef,show,tags';
    }

    return fallback || 'title';
  }

  function renderNaturalLanguageDebug(data) {
    if (!debugEnabled || !data || !Array.isArray(data.results)) {
      return;
    }

    const debugResults = data.results.map((result, index) => {
      const search = lastNaturalLanguageSearches[index] || {};
      const parsed = result && result.parsed_nl_query
        ? result.parsed_nl_query
        : (lastFallbackDebug[index] || {});

      return {
        collection: search.collection || (result && result.request_params && result.request_params.collection_name) || '',
        natural_language_query: lastOriginalNaturalLanguageQuery || search.q || '',
        query_by_exposed_to_ai: search.query_by || '',
        generated_params: parsed.generated_params || {},
        augmented_params: parsed.augmented_params || {},
        parsed_nl_query: parsed,
      };
    }).filter((entry) => Object.keys(entry.generated_params).length || Object.keys(entry.augmented_params).length);

    if (!debugResults.length) {
      return;
    }

    const panel = getDebugPanel();
    const output = panel.querySelector('[data-nls-debug-content]');
    output.innerHTML = debugResults.map((entry) => {
      const generated = entry.generated_params || {};
      const augmented = entry.augmented_params || {};
      const entities = generated.resolved_entities || {};
      const candidates = Array.isArray(entities.candidate_matches) ? entities.candidate_matches : [];
      const error = entry.parsed_nl_query && entry.parsed_nl_query.error;

      return `<section class="kosher-nls-debug__card">
        <h3>${escapeDebugHTML(entry.collection.replace(/^.*_/, ''))}</h3>
        ${debugRow('User query', entry.natural_language_query)}
        ${debugRow('AI query', generated.q)}
        ${debugRow('Generated filter', generated.filter_by || 'None')}
        ${debugRow('Final filter', augmented.filter_by || 'None')}
        ${debugRow('Sort', augmented.sort_by || 'Default relevance')}
        ${debugRow('Search fields', augmented.query_by || entry.query_by_exposed_to_ai)}
        ${generated.primary_dish ? debugRow('Primary dish', generated.primary_dish) : ''}
        ${entities.chef ? debugRow('Chef match', `${entities.chef} (${entities.match_type || 'exact'})`) : ''}
        ${candidates.length ? debugRow('Candidates', candidates.join(', ')) : ''}
        ${error ? `<div class="kosher-nls-debug__error">${escapeDebugHTML(error)}</div>` : ''}
      </section>`;
    }).join('');
  }

  function escapeDebugHTML(value) {
    const element = document.createElement('div');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
  }

  function debugRow(label, value) {
    return `<div class="kosher-nls-debug__row"><strong>${escapeDebugHTML(label)}</strong><code>${escapeDebugHTML(value || '—')}</code></div>`;
  }

  function getDebugPanel() {
    let panel = document.getElementById('kosher-typesense-natural-language-debug');
    if (panel) {
      return panel;
    }

    panel = document.createElement('aside');
    panel.id = 'kosher-typesense-natural-language-debug';
    panel.className = 'kosher-nls-debug';
    panel.innerHTML = `<div class="kosher-nls-debug__header">
      <div><span>ADMIN ONLY</span><h2>Natural Language Search Debug</h2></div>
      <button type="button" aria-label="Collapse debug panel" data-nls-debug-toggle>×</button>
    </div><div class="kosher-nls-debug__content" data-nls-debug-content></div>`;
    document.body.appendChild(panel);

    const style = document.createElement('style');
    style.textContent = `.kosher-nls-debug{position:fixed;z-index:99999;top:110px;right:18px;width:min(430px,calc(100vw - 36px));max-height:calc(100vh - 140px);overflow:hidden;background:#fff;border:2px solid #7a147a;border-radius:14px;box-shadow:0 16px 45px rgba(40,10,45,.3);font-family:Inter,Arial,sans-serif;color:#241426}.kosher-nls-debug__header{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;padding:14px 16px;background:#7a147a;color:#fff}.kosher-nls-debug__header span{display:block;font-size:10px;font-weight:800;letter-spacing:.12em;opacity:.8}.kosher-nls-debug__header h2{margin:2px 0 0;font-size:16px;line-height:1.25;color:#fff}.kosher-nls-debug__header button{border:0;background:rgba(255,255,255,.16);color:#fff;border-radius:50%;width:30px;height:30px;font-size:22px;line-height:1;cursor:pointer}.kosher-nls-debug__content{padding:12px;max-height:calc(100vh - 215px);overflow:auto;background:#f7f3f7}.kosher-nls-debug__card{padding:12px;margin:0 0 10px;background:#fff;border:1px solid #e2d5e3;border-radius:9px}.kosher-nls-debug__card h3{margin:0 0 10px;text-transform:capitalize;font-size:15px;color:#7a147a}.kosher-nls-debug__row{margin:0 0 9px}.kosher-nls-debug__row strong{display:block;margin-bottom:3px;font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:#756476}.kosher-nls-debug__row code{display:block;padding:7px 8px;border-radius:5px;background:#f4f1f4;color:#292029;font-size:12px;line-height:1.4;white-space:pre-wrap;overflow-wrap:anywhere}.kosher-nls-debug__error{padding:8px;border-radius:5px;background:#fff0f0;color:#a21919;font-size:12px;font-weight:600}.kosher-nls-debug.is-collapsed{width:auto}.kosher-nls-debug.is-collapsed .kosher-nls-debug__content,.kosher-nls-debug.is-collapsed .kosher-nls-debug__header div{display:none}@media(max-width:700px){.kosher-nls-debug{top:auto;right:10px;bottom:10px;width:calc(100vw - 20px);max-height:60vh}.kosher-nls-debug__content{max-height:calc(60vh - 65px)}}`;
    document.head.appendChild(style);

    panel.querySelector('[data-nls-debug-toggle]').addEventListener('click', () => {
      panel.classList.toggle('is-collapsed');
      const collapsed = panel.classList.contains('is-collapsed');
      panel.querySelector('[data-nls-debug-toggle]').textContent = collapsed ? 'AI' : '×';
      panel.querySelector('[data-nls-debug-toggle]').setAttribute('aria-label', collapsed ? 'Open debug panel' : 'Collapse debug panel');
    });

    return panel;
  }

  function renderDebugStatus(status, details = {}) {
    if (!debugEnabled) return;
    const output = getDebugPanel().querySelector('[data-nls-debug-content]');
    output.innerHTML = `<section class="kosher-nls-debug__card"><h3>Status</h3>${debugRow('State', status)}${details.provider ? debugRow('Provider', details.provider) : ''}${details.natural_language_query ? debugRow('User query', details.natural_language_query) : ''}${details.error ? `<div class="kosher-nls-debug__error">${escapeDebugHTML(details.error)}</div>` : ''}</section>`;
  }

  function isTypesenseSearchRequest(resource, options) {
    const url = typeof resource === 'string'
      ? resource
      : (resource && typeof resource.url === 'string' ? resource.url : '');
    const method = String((options && options.method) || (resource && resource.method) || 'GET').toUpperCase();

    if (method !== 'POST' || !url) {
      return false;
    }

    try {
      const requestUrl = new URL(url, window.location.href);
      return requestUrl.searchParams.get('action') === searchAction;
    } catch (error) {
      return false;
    }
  }

  function enableNaturalLanguageSearch(search) {
    if (!search || typeof search !== 'object') {
      return search;
    }

    const query = typeof search.q === 'string' ? search.q.trim() : '';
    if (!query || query === '*') {
      return search;
    }

    const naturalSearch = {
      ...search,
      query_by: getNaturalLanguageQueryBy(search.collection, search.query_by),
    };

    if (recipeRankingFieldsAvailable && getCollectionSlug(search.collection) === 'recipes') {
      naturalSearch.query_by = `main_dish,${naturalSearch.query_by}`;
      naturalSearch.query_by_weights = '30,12,5,3,2,2,2,2,2,2,2,2,2';

      if (naturalSearch.sort_by === '_text_match:desc,date:desc') {
        naturalSearch.sort_by = '_text_match:desc,search_priority:desc,date:desc';
      }
    }

    if (modelId) {
      naturalSearch.nl_query = true;
      naturalSearch.nl_query_debug = true;
      naturalSearch.nl_model_id = modelId;
    }

    // Let the NLS-generated sort win for requests such as "newest", "most
    // popular" or "highest rated". A sort explicitly chosen in the UI is kept.
    if (naturalSearch.sort_by === '_text_match:desc,date:desc') {
      delete naturalSearch.sort_by;
    }

    // These keyword-only tuning options can override or distort the structured
    // query produced by NLS. query_by and explicit UI filter_by remain intact.
    if (!recipeRankingFieldsAvailable || getCollectionSlug(search.collection) !== 'recipes') {
      delete naturalSearch.query_by_weights;
    }
    delete naturalSearch.num_typos;
    delete naturalSearch.prioritize_exact_match;
    delete naturalSearch.prioritize_token_position;
    delete naturalSearch.text_match_type;
    delete naturalSearch.drop_tokens_threshold;
    delete naturalSearch.typo_tokens_threshold;

    return naturalSearch;
  }

  function augmentRequestBody(options) {
    if (!options || typeof options.body !== 'string') {
      return options;
    }

    try {
      const payload = JSON.parse(options.body);
      if (!payload || !Array.isArray(payload.searches)) {
        return options;
      }

      const searches = payload.searches.map(enableNaturalLanguageSearch);
      lastNaturalLanguageSearches = searches;

      return {
        ...options,
        body: JSON.stringify({
          ...payload,
          searches,
        }),
      };
    } catch (error) {
      console.warn('[Kosher Typesense NLS] Could not parse the search request body.', error);
      return options;
    }
  }

  function combineFilters(explicitFilter, generatedFilter) {
    const filters = [explicitFilter, generatedFilter].filter((value) => typeof value === 'string' && value.trim());
    return filters.map((value) => `(${value})`).join(' && ');
  }

  function inferPrimaryDish(query) {
    const normalized = String(query || '').trim().toLowerCase();

    if (!normalized || normalized === '*' || normalized.split(/\s+/).length > 5) {
      return '';
    }

    // Structured requests are handled by generated filters; use this fallback
    // only for short, dish-like searches when AI translation is unavailable.
    if (/\b(without|free|under|less than|more than|for|by|from|chef|author|newest|oldest|popular|rated)\b/.test(normalized)) {
      return '';
    }

    return normalized;
  }

  function augmentWithOpenAITranslation(options) {
    if (!options || typeof options.body !== 'string') {
      return Promise.resolve(options);
    }

    let payload;
    try {
      payload = JSON.parse(options.body);
    } catch (error) {
      return Promise.resolve(options);
    }

    if (!payload || !Array.isArray(payload.searches)) {
      return Promise.resolve(options);
    }

    const querySearch = payload.searches.find((search) => search && typeof search.q === 'string' && search.q.trim());
    if (!querySearch) {
      return Promise.resolve(options);
    }

    const translateUrl = `${naturalLanguageConfig.ajaxUrl || window.typeSenseConfig?.ajaxUrl || '/wp-admin/admin-ajax.php'}?action=${encodeURIComponent(translateAction)}`;
    lastOriginalNaturalLanguageQuery = querySearch.q.trim();
    const translationController = new AbortController();
    const translationTimeout = window.setTimeout(() => translationController.abort(), 12000);

    renderDebugStatus('translating', {
      provider: 'openai_fallback',
      natural_language_query: querySearch.q.trim(),
    });

    return nativeFetch(translateUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Kosher-Typesense-Nonce': naturalLanguageConfig.searchNonce || window.typeSenseConfig?.searchNonce || '',
      },
      body: JSON.stringify({ q: querySearch.q.trim() }),
      signal: translationController.signal,
    }).then((response) => response.json().then((data) => {
      if (!response.ok || !data || !data.translations) {
        throw new Error(data && data.error ? data.error : 'Natural-language translation failed.');
      }

      let primaryDish = inferPrimaryDish(querySearch.q);
      const searches = payload.searches.map((search, index) => {
        const naturalSearch = enableNaturalLanguageSearch(search);
        const translation = data.translations[getCollectionSlug(search.collection)] || {};
        const hasGeneratedFilter = typeof translation.filter_by === 'string' && translation.filter_by.trim() !== '';
        const hasResolvedEntity = translation.resolved_entities
          && typeof translation.resolved_entities === 'object'
          && Object.keys(translation.resolved_entities).length > 0;
        const translatedQuery = typeof translation.q === 'string' ? translation.q.trim() : '';
        const safeQuery = translatedQuery === '*' && !hasGeneratedFilter && !hasResolvedEntity
          ? search.q
          : (translatedQuery || search.q);
        const generated = {
          q: safeQuery,
          filter_by: translation.filter_by || '',
          sort_by: translation.sort_by || '',
          primary_dish: translation.primary_dish || '',
          resolved_entities: translation.resolved_entities || {},
        };

        if (getCollectionSlug(search.collection) === 'recipes' && generated.primary_dish) {
          primaryDish = generated.primary_dish;
        }

        naturalSearch.q = generated.q;
        naturalSearch.filter_by = combineFilters(search.filter_by, generated.filter_by) || undefined;
        naturalSearch.sort_by = naturalSearch.sort_by || generated.sort_by || undefined;

        if (generated.resolved_entities && generated.resolved_entities.match_type === 'partial') {
          naturalSearch.query_by = 'chefs';
        }

        lastFallbackDebug[index] = {
          provider: 'openai_fallback',
          generated_params: generated,
          augmented_params: {
            q: naturalSearch.q,
            filter_by: naturalSearch.filter_by || '',
            sort_by: naturalSearch.sort_by || '',
            query_by: naturalSearch.query_by,
          },
        };

        return naturalSearch;
      });

      lastNaturalLanguageSearches = searches;
      window.clearTimeout(translationTimeout);
      return {
        ...options,
        headers: {
          ...(options.headers || {}),
          'X-Kosher-Primary-Dish': primaryDish,
        },
        body: JSON.stringify({ ...payload, searches }),
      };
    })).catch((error) => {
      window.clearTimeout(translationTimeout);
      lastFallbackDebug = [];
      lastNaturalLanguageSearches = payload.searches;
      renderDebugStatus('translation_failed_searching_original_query', {
        provider: 'openai_fallback',
        natural_language_query: querySearch.q.trim(),
        error: error && error.name === 'AbortError'
          ? 'OpenAI translation timed out after 12 seconds.'
          : (error && error.message ? error.message : 'Unknown translation error.'),
      });
      console.error('[Kosher Typesense NLS] OpenAI translation failed.', error);
      const fallbackDish = inferPrimaryDish(querySearch.q);
      return fallbackDish ? {
        ...options,
        headers: {
          ...(options.headers || {}),
          'X-Kosher-Primary-Dish': fallbackDish,
        },
      } : options;
    });
  }

  function fetchAndRenderDebug(resource, options) {
    return nativeFetch(resource, options).then((response) => {
      response.clone().json()
        .then(renderNaturalLanguageDebug)
        .catch((error) => {
          console.warn('[Kosher Typesense NLS] Could not read the debug response.', error);
        });

      return response;
    });
  }

  window.fetch = function (resource, options) {
    if (!isTypesenseSearchRequest(resource, options)) {
      return nativeFetch(resource, options);
    }

    setNaturalLanguageLoading(true);

    if (modelId) {
      lastFallbackDebug = [];
      return fetchAndRenderDebug(resource, augmentRequestBody(options))
        .finally(() => setNaturalLanguageLoading(false));
    }

    return augmentWithOpenAITranslation(options)
      .then((translatedOptions) => fetchAndRenderDebug(resource, translatedOptions))
      .finally(() => setNaturalLanguageLoading(false));
  };
}());
