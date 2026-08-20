/**
 * File: kosher-typesense-search-filter.js
 * Description: Handles Typesense search UI state, filters, tabs, requests, rendering, and pagination.
 * Author: Kosher Dev Team
 */

document.addEventListener('DOMContentLoaded', function () {
  const TomSelect = window.TomSelect;
  if (typeof TomSelect === 'undefined') {
    console.error('Tom Select failed to load. Search filters cannot initialize.');
    return;
  }

  const searchForms = document.querySelectorAll('.kosher-search-filter-form');
  const sectionOptions = document.querySelector('.section__selections__options');
 if(sectionOptions) {
  const includesList = sectionOptions.querySelector('.includes-list');
  const excludesList = sectionOptions.querySelector('.excludes-list');
 }
  const clearAllButton = document.querySelector('.clear-all');
  const sortDropdown = document.getElementById('search-filters__sort__dropdown');
  const sidebarSortRadios = document.querySelectorAll('input[name="kosher-sidebar-sort"]');
  const authorTypeCheckboxes = document.querySelectorAll('[data-author-type-option]');
  const ingredientsSelect = document.getElementById('ingredients-select');
  const ingredientsExcludeSelect = document.getElementById('ingredients-exclude-select');
  const selectedIngredientsContainer = document.getElementById('selected-ingredients-container');
  const selectedExcludeIngredientsContainer = document.getElementById('selected-ingredients-exclude-container');
  const filterButton = document.querySelector('.btn-kosher--filter');
  const filterFormResult = document.querySelector('.kosher-search-filter-form-result__filter');
  const headerContainer = document.querySelector('.header__area .container');
  const searchFilterForm = document.querySelector('.kosher-search-filter-form');
  const applyFilterBtn = document.querySelector('.btn-apply');
  const urlParams = new URLSearchParams(window.location.search);
  const valueUrl = urlParams.get('op'); // Replace 'variable' with your actual parameter name
  let customSorting = '';
  let lastQuery = '';
  let activeSearchController = null;
  let activeSearchRequestId = 0;
  let searchDebounceTimer = null;
  const searchDebounceDelay = 300;
	  const ajaxUrl = typeSenseConfig.ajaxUrl || typeSenseConfig.ajax_url;
	  const searchAction = typeSenseConfig.searchAction || 'kosher_typesense_search';
	  const collectionPrefix = typeSenseConfig.collectionPrefix || 'live_';
	  const collectionName = (name) => `${collectionPrefix}${name}`;

  function getSelectedAuthorTypes(params = new URLSearchParams(window.location.search)) {
    const selected = params.getAll('author_type').filter((value) => value === 'kosher' || value === 'home');

    if (selected.length) {
      const uniqueSelected = Array.from(new Set(selected));
      return [uniqueSelected.includes('home') ? 'home' : 'kosher'];
    }

    return params.get('community') === '1' ? ['home'] : ['kosher'];
  }

  function getAuthorTypeLabel(value) {
    return value === 'home' ? 'Home Cooks' : 'Kosher.com';
  }

  function syncAuthorTypeCounter(selectedTypes = getSelectedAuthorTypes()) {
    const counterElement = document.querySelector('.counter-selection--author-type');

    if (!counterElement) {
      return;
    }

    const isDefault = selectedTypes.length === 1 && selectedTypes[0] === 'kosher';
    const selectedCount = isDefault ? 0 : selectedTypes.length;

    if (selectedCount > 0) {
      counterElement.style.display = 'flex';
      counterElement.textContent = selectedCount;
    } else {
      counterElement.style.display = 'none';
      counterElement.textContent = '';
    }
  }

function syncAuthorTypeCheckboxes(selectedTypes = getSelectedAuthorTypes()) {
    const selected = selectedTypes.length ? selectedTypes : ['kosher'];

    authorTypeCheckboxes.forEach((checkbox) => {
      checkbox.checked = selected.includes(checkbox.value);
    });

    syncAuthorTypeCounter(selected);
  }

  function updateAuthorTypeCounts(kosherCount, homeCount) {
    document.querySelectorAll('[data-author-type-count="kosher"]').forEach((countElement) => {
      countElement.textContent = `(${kosherCount})`;
    });

    document.querySelectorAll('[data-author-type-count="home"]').forEach((countElement) => {
      countElement.textContent = `(${homeCount})`;
    });
  }

  function updateAuthorTypesInURL(selectedTypes) {
    const url = new URL(window.location.href);
    const selected = selectedTypes.length ? selectedTypes : ['kosher'];
    const isDefault = selected.length === 1 && selected[0] === 'kosher';

    url.searchParams.delete('author_type');
    url.searchParams.delete('community');

    if (!isDefault) {
      selected.forEach((value) => {
        url.searchParams.append('author_type', value);
      });
    }

    history.pushState({}, '', url);
  }

  function removeAuthorTypeFilter(value) {
    let selected = getSelectedAuthorTypes().filter((selectedValue) => selectedValue !== value);

    if (!selected.length) {
      selected = ['kosher'];
    }

    updateAuthorTypesInURL(selected);
    syncAuthorTypeCheckboxes(selected);
    syncCommunitySwitches(selected.includes('home'));
    updateSelections();
    executeSearch();
  }

  function summarizeTypesenseSearches(payload) {
    return (payload.searches || []).map((search, index) => ({
      index,
      collection: search.collection || '',
      preset: search.preset || '',
      q: search.q || '',
      filter_by: search.filter_by || '',
      page: search.page || '',
      per_page: search.per_page || '',
      sort_by: search.sort_by || '',
    }));
  }

  function summarizeTypesenseResults(data, payload) {
    return (data.results || []).map((result, index) => {
      const request = payload.searches && payload.searches[index] ? payload.searches[index] : {};
      const params = result && result.request_params ? result.request_params : {};

      return {
        index,
        label: request.debug_label || request.preset || request.collection || params.collection_name || '',
        requested_filter_by: request.filter_by || '',
        collection: params.collection_name || request.collection || '',
        preset: request.preset || '',
        found: result ? result.found : '',
        out_of: result ? result.out_of : '',
        hits_returned: result && Array.isArray(result.hits) ? result.hits.length : 0,
        first_hit: result && result.hits && result.hits[0] ? {
          postID: result.hits[0].document && result.hits[0].document.postID,
          title: result.hits[0].document && result.hits[0].document.title,
          type: result.hits[0].document && result.hits[0].document.type,
          community_recipe: result.hits[0].document && result.hits[0].document['community-recipe'],
          sources: result.hits[0].document && result.hits[0].document.sources,
        } : null,
      };
    });
  }

  function typesenseSearch(payload, signal, debugContext = {}) {
    const requestUrl = `${ajaxUrl}?action=${encodeURIComponent(searchAction)}`;
    const requestPayload = {
      ...payload,
      searches: (payload.searches || []).map(({ debug_label, ...search }) => search),
    };

    console.groupCollapsed('[Kosher Typesense] request');
    console.log('url', requestUrl);
    console.log('debug context', debugContext);
    console.table(summarizeTypesenseSearches(payload));
    console.log('payload', payload);
    console.groupEnd();

    return fetch(requestUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Kosher-Typesense-Nonce': typeSenseConfig.searchNonce || ''
      },
      body: JSON.stringify(requestPayload),
      signal
    }).then((response) => {
      return response.json().then((data) => {
        console.groupCollapsed('[Kosher Typesense] response');
        console.log('status', response.status);
        console.log('ok', response.ok);
        console.log('debug context', debugContext);
        console.table(summarizeTypesenseResults(data, payload));
        console.log('raw response', data);
        console.groupEnd();

        if (!response.ok) {
          throw new Error(data && data.error ? data.error : `Search request failed with status ${response.status}`);
        }

        return data;
      });
    });
  }

  function enhanceFilterDropdownSearch(instance) {
    if (!instance || !instance.dropdown_content || instance.dropdown_content.dataset.kosherFilterSearchBound) {
      return;
    }

    const dropdown = instance.dropdown || instance.dropdown_content.parentElement;
    const remoteSearchSelectIds = [
      'chef-select',
      'author-select',
      'ingredients-select',
      'ingredients-exclude-select',
      'show-chef-select'
    ];
    const usesNativeRemoteSearch = remoteSearchSelectIds.includes(instance.inputId);

    if (!dropdown) {
      return;
    }

    const clearButton = document.createElement('button');

    instance.dropdown_content.dataset.kosherFilterSearchBound = 'true';

    let searchWrap = null;
    let searchInput = null;

    if (!usesNativeRemoteSearch) {
      searchWrap = document.createElement('div');
      searchInput = document.createElement('input');
      searchWrap.className = 'kosher-search-filter-dropdown-search';
      searchInput.className = 'kosher-search-filter-dropdown-search__input';
      searchInput.type = 'search';
      searchInput.placeholder = 'Search...';
      searchInput.autocomplete = 'off';
      searchInput.setAttribute('aria-label', 'Search filter options');
      searchWrap.appendChild(searchInput);
      dropdown.insertBefore(searchWrap, instance.dropdown_content);
    }

    clearButton.type = 'button';
    clearButton.className = 'kosher-search-filter-dropdown-clear';
    clearButton.textContent = 'Clear All';
    clearButton.hidden = !instance.items.length;
    dropdown.insertBefore(clearButton, instance.dropdown_content.nextSibling);

    const syncClearButton = () => {
      clearButton.hidden = !instance.items.length;
    };

    if (searchWrap) {
      ['mousedown', 'click', 'keydown'].forEach((eventName) => {
        searchWrap.addEventListener(eventName, (event) => {
          event.stopPropagation();
        });
      });
    }

    clearButton.addEventListener('mousedown', (event) => {
      event.preventDefault();
      event.stopPropagation();
    });

    clearButton.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      instance.clear();
      syncClearButton();
      window.setTimeout(() => {
        instance.open();
      }, 0);
    });

    if (searchInput) {
      searchInput.addEventListener('input', () => {
        instance.setTextboxValue(searchInput.value);
        instance.refreshOptions(true);
      });
    }

    instance.on('dropdown_open', () => {
      window.setTimeout(() => {
        if (searchInput) {
          searchInput.focus();
          return;
        }

        if (instance.control_input) {
          instance.control_input.focus();
        }
      }, 0);
    });

    instance.on('dropdown_close', () => {
      if (searchInput) {
        searchInput.value = '';
        instance.setTextboxValue('');
        instance.refreshOptions(false);
      }
    });
    instance.on('item_add', syncClearButton);
    instance.on('item_remove', syncClearButton);
    instance.on('clear', syncClearButton);
    instance.on('change', syncClearButton);

    const collapse = instance.wrapper ? instance.wrapper.closest('.accordion-collapse') : null;

    if (collapse) {
      collapse.addEventListener('shown.bs.collapse', () => {
        instance.open();
      });

      if (collapse.classList.contains('show')) {
        window.setTimeout(() => {
          instance.open();
        }, 0);
      }

      instance.on('item_add', () => {
        window.setTimeout(() => {
          if (collapse.classList.contains('show')) {
            instance.open();
          }
        }, 0);
      });

      instance.on('item_remove', () => {
        window.setTimeout(() => {
          if (collapse.classList.contains('show')) {
            instance.open();
          }
        }, 0);
      });
    }
  }

  function enhanceAllFilterDropdownSearches() {
    document.querySelectorAll('.kosher-search-filter-form select').forEach((select) => {
      if (select.tomselect) {
        enhanceFilterDropdownSearch(select.tomselect);
      }
    });

    openShownFilterDropdowns(document);
  }

  function openShownFilterDropdowns(scope = document) {
    scope.querySelectorAll('.accordion-collapse.show select').forEach((select) => {
      if (!select.tomselect) {
        return;
      }

      enhanceFilterDropdownSearch(select.tomselect);

      window.setTimeout(() => {
        if (select.closest('.accordion-collapse.show')) {
          select.tomselect.open();
        }
      }, 0);
    });
  }

  function closeFilterDropdowns(collapse) {
    collapse.querySelectorAll('select').forEach((select) => {
      if (select.tomselect) {
        select.tomselect.close();
      }
    });
  }

  function syncAccordionButton(collapse, isOpen) {
    const button = document.querySelector(`[data-bs-target="#${collapse.id}"]`);

    if (!button) {
      return;
    }

    button.classList.toggle('collapsed', !isOpen);
    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }

  function hideFilterCollapse(collapse) {
    closeFilterDropdowns(collapse);

    if (window.bootstrap && window.bootstrap.Collapse) {
      window.bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).hide();
      return;
    }

    collapse.classList.remove('show', 'collapsing');
    collapse.classList.add('collapse');
    collapse.style.height = '';
    syncAccordionButton(collapse, false);
  }

  function bindSidebarAccordionBehavior() {
    document.querySelectorAll('.kosher-search-filter-form .filters-group').forEach((group) => {
      if (group.dataset.sidebarAccordionBound) {
        return;
      }

      group.dataset.sidebarAccordionBound = 'true';

      group.querySelectorAll('.accordion-collapse').forEach((collapse) => {
        collapse.addEventListener('show.bs.collapse', () => {
          group.querySelectorAll('.accordion-collapse.show, .accordion-collapse.collapsing').forEach((openCollapse) => {
            if (openCollapse !== collapse) {
              hideFilterCollapse(openCollapse);
            }
          });
        });

        collapse.addEventListener('hide.bs.collapse', () => {
          closeFilterDropdowns(collapse);
        });

        collapse.addEventListener('hidden.bs.collapse', () => {
          syncAccordionButton(collapse, false);
        });

        collapse.addEventListener('shown.bs.collapse', () => {
          syncAccordionButton(collapse, true);
          openShownFilterDropdowns(group);
        });
      });
    });
  }

  function bindRemoteFilterSearchInputs() {
    const remoteFilterActions = {
      'chef-select': 'get_chefs',
      'author-select': 'get_chefs',
      'ingredients-select': 'get_ingredients_terms',
      'ingredients-exclude-select': 'get_ingredients_terms',
      'show-chef-select': 'get_chefs'
    };

    document.querySelectorAll('[data-remote-filter-search]').forEach((input) => {
      if (input.dataset.remoteFilterSearchBound) {
        return;
      }

      const selectId = input.dataset.remoteFilterSearch;
      const select = document.getElementById(selectId);

      if (!select || !select.tomselect) {
        return;
      }

      const instance = select.tomselect;
      const action = remoteFilterActions[selectId];
      input.dataset.remoteFilterSearchBound = 'true';
      let remoteSearchTimer = null;
      let remoteSearchRequestId = 0;

      const syncSearch = () => {
        const query = input.value.trim();

        instance.setTextboxValue(query);

        if (!action || query.length < 3) {
          instance.refreshOptions(true);
          instance.open();
          return;
        }

        const requestId = ++remoteSearchRequestId;

        window.clearTimeout(remoteSearchTimer);
        remoteSearchTimer = window.setTimeout(() => {
          fetch(`${typeSenseConfig.ajaxUrl}?action=${action}&q=${encodeURIComponent(query)}`)
            .then((response) => response.json())
            .then((items) => {
              if (requestId !== remoteSearchRequestId || !Array.isArray(items)) {
                return;
              }

              items.forEach((item) => {
                if (item && item.id && item.name) {
                  instance.addOption(item);
                }
              });

              instance.setTextboxValue(query);
              instance.refreshOptions(true);
              instance.open();
            })
            .catch((error) => {
              console.error(`Error fetching ${selectId}:`, error);
              instance.refreshOptions(true);
              instance.open();
            });
        }, 180);
      };

      input.addEventListener('input', syncSearch);
      input.addEventListener('focus', () => {
        instance.open();
      });

      instance.on('item_add', () => {
        input.value = '';
        instance.setTextboxValue('');
      });
    });
  }

  const searchFilterParamsByTab = {
    recipes: [
      'hi', 'ae', 'ic', 'cui', 'is', 'pf', 'dif', 'di', 'ge', 'gi',
      'act', 'chi', 'author_type', 'community'
    ],
    menus: ['imc', 'mau', 'msc', 'mcc'],
    articles: ['iac', 'au'],
    shows: ['sn', 'vchi', 'vl']
  };

  function removeFiltersFromOtherTabs(url, opValue) {
    // The All tab exposes the recipe filters, so it shares recipe filter state.
    const activeFilterType = opValue === 'all' || !opValue ? 'recipes' : opValue;
    const allowedParams = new Set(searchFilterParamsByTab[activeFilterType] || []);

    Object.values(searchFilterParamsByTab).flat().forEach((param) => {
      if (!allowedParams.has(param)) {
        url.searchParams.delete(param);
      }
    });
  }

  function updateSearchTabInURL(tabId) {
    const url = new URL(window.location.href);
    const opValue = (tabId || '').replace(/^tab-/, '');

    removeFiltersFromOtherTabs(url, opValue);
    url.searchParams.delete('op');

    if (opValue && opValue !== 'all') {
      url.searchParams.set('op', opValue);
    }

    window.history.replaceState({}, '', url);
  }

  // Normalize copied, bookmarked, or previously generated URLs that contain
  // filters owned by a different result type.
  if (valueUrl) {
    updateSearchTabInURL(`tab-${valueUrl === 'episodes' ? 'shows' : valueUrl}`);
  }

  async function activateTab(event) {
    event.preventDefault(); // Prevent default link behavior

  
    // Find the closest anchor tag in case the click is on a child element
    const anchor = event.target.closest('a');
    if (!anchor) {
      console.error('No anchor tag found in the event target');
      return;
    }
  
    // Validate the href value
    const targetID = anchor.getAttribute('href');
    if (!targetID || !targetID.startsWith('#')) {
      console.error('Invalid or missing href value:', targetID);
      return;
    }

    updateSearchTabInURL(`tab-${targetID.replace('#', '')}`);
  
    // Remove 'show' and 'active' classes from all tab-panes
    await Promise.all(
      Array.from(document.querySelectorAll('.tab-pane')).map(async (tab) => {
        tab.classList.remove('show', 'active');
      })
    );
  
    // Remove 'active' class from all nav-links
    await Promise.all(
      Array.from(document.querySelectorAll('.header-tabs .nav-link')).map(async (link) => {
        link.classList.remove('active');
      })
    );
  
    // Activate the target tab-pane
    const targetTabPane = document.querySelector(targetID);
    if (targetTabPane) {
      targetTabPane.classList.add('show', 'active');
    } else {
      console.error('Target tab-pane not found:', targetID);
    }
  
    // Activate the corresponding nav-link
    const correspondingNavLink = document.querySelector(`.header-tabs .nav-link[href="${targetID}"]`);
    if (correspondingNavLink) {
      correspondingNavLink.classList.add('active');
  
      // Smooth scroll to the corresponding nav-link
      await new Promise((resolve) => {
        correspondingNavLink.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(resolve, 400); // Adjust timeout as needed
      });
    } else {
      console.error('Corresponding nav-link not found:', targetID);
    }
  
  }
  
  
  
  
  

  if (valueUrl) {
    // Select all nav-links inside .header-tabs
    const navLinks = document.querySelectorAll('.header-tabs .nav-link');
    // Select all tab-panes inside .search-filters-nav
    const tabPanes = document.querySelectorAll('.search-filters-nav .tab-pane');
  
    // Remove the 'active' class from all nav-links
    navLinks.forEach(navLink => navLink.classList.remove('active'));
  
    // Remove 'active' and 'show' classes from all tab-panes
    tabPanes.forEach(tabPane => {
      tabPane.classList.remove('active', 'show');
    });
  
    // Determine the target ID (episodes -> shows)
    const targetId = valueUrl === 'episodes' ? 'shows' : valueUrl;
  
    // Add the 'active' class to the appropriate nav-link
    const activeNavLink = document.getElementById(`tab-${targetId}`);
    if (activeNavLink) activeNavLink.classList.add('active');
  
    // Add 'active' and 'show' classes to the appropriate tab-pane
    const activeTabPane = document.getElementById(targetId);
    if (activeTabPane) activeTabPane.classList.add('active', 'show');
  
    // ---- Toggle filters visibility + .no-filters class ----
    const searchFilters  = document.querySelector('.search-filters');
    const filterResults  = document.querySelector('.kosher-search-filter-form-result');
    const sideFitlers = document.querySelector('.kosher-search-filter-form-result__filter');

  }
  

  if(filterButton) {
  // Add click event listener to the button 
  filterButton.addEventListener('click', function() {
    if (!filterFormResult) {
      return;
    }

    // Toggle the class .opened-filter on the form result filter
    filterFormResult.classList.toggle('opened-filter');
    if (applyFilterBtn) {
      applyFilterBtn.classList.toggle('active-btn');
    }
  });

  }


  if(    document.querySelector('.close-side-filter')) {
    // Optional: If you want to add a separate toggle for .collapse__filter as well
    document.querySelector('.close-side-filter').addEventListener('click', function() {
      if (!filterFormResult) {
        return;
      }

      // Toggle the class .opened-filter on the form result filter
      filterFormResult.classList.toggle('opened-filter');
      if (applyFilterBtn) {
        applyFilterBtn.classList.toggle('active-btn');
      }
      
    });

  }

if(document.querySelector('.btn-apply')) {
  document.querySelector('.btn-apply').addEventListener('click', function() {
    if (!filterFormResult) {
      return;
    }

    // Toggle the class .opened-filter on the form result filter
    filterFormResult.classList.toggle('opened-filter');
    if (applyFilterBtn) {
      applyFilterBtn.classList.toggle('active-btn');
    }
  });
}



    
  if (headerContainer) {
    headerContainer.classList.remove('container');
    headerContainer.classList.add('container-fluid');
  }

  // Attach event listeners to nav-links under header-tabs
  document.querySelectorAll('.header-tabs .nav-link').forEach(navLink => {
    navLink.addEventListener('click', activateTab);
  });

  function updatePageInURL(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page); // Update the page parameter in the URL
    history.pushState({}, '', url);
  }

  preselectIngredientsFromURL();


// Function to check for the 'gi' parameter in the URL on page load and preselect the item
  function preselectIngredientsFromURL() {
    const url = new URL(window.location.href);
    const giParam = url.searchParams.get('gi'); // Get the 'gi' parameter from the URL

    if (giParam) {
      // Delay the action slightly to ensure Tom Select is ready
      setTimeout(function() {
        // Find the corresponding ID for the given 'gi' parameter (name) in Tom Select
        const itemId = findItemIdByName(giParam);

        if (itemId) {
          // Add the item to Tom Select as selected
          ingredientsSelect.addItem(itemId);

          // Add the selected ingredient to the selected container
          addIngredientButton(itemId, giParam);
        }
      }, 100); // Add a slight delay to ensure everything is ready
    }
  }

// Generalized findItemIdByName function for any Tom Select instance
function findItemIdByName(name, selectInstance) {
  let itemId = null;

  // Iterate through the options object in the given Tom Select instance
  Object.keys(selectInstance.options).forEach(function(id) {
    let option = selectInstance.options[id];

    // Match the value of the option with the provided name
    if (option.value.trim() === name.trim()) {
      itemId = id; // Get the ID of the matching item
    }
  });

  return itemId; // Return the ID or null if not found
}

function mountFilterSearchInHeader(searchForm, searchInput) {
  const headerSearchSlot = document.querySelector('.header-bar__search');
  const searchWrapper = searchInput ? searchInput.closest('.container__wrapper') : null;

  if (window.matchMedia('(max-width: 856px)').matches) {
    return;
  }

  if (!headerSearchSlot || !searchWrapper || searchWrapper.dataset.kosherHeaderMounted === '1') {
    return;
  }

  searchInput.setAttribute('data-kosher-filter-search', 'true');
  searchWrapper.dataset.kosherHeaderMounted = '1';
  searchWrapper.classList.add('kosher-search-filter-form__header-search');
  searchForm.classList.add('is-header-search-mounted');
  headerSearchSlot.appendChild(searchWrapper);
}


  searchForms.forEach((searchForm) => {
    const searchInput = searchForm.querySelector('.form-control');
    const searchIcon = searchForm.querySelector('.input-group-text .fa-search');
    const closeIcon = searchForm.querySelector('.input-group-text.close-icon');
    const tabs = document.querySelectorAll('.header-tabs .nav-link');
    const resultsContainerAll = document.querySelector('#all .list-all');
    const resultsContainerRecipes = document.querySelector('#recipes .list');
    const resultsContainerMenus = document.querySelector('#menus .list');
    const resultsContainerArticles = document.querySelector('#articles .list');
    const resultsContainerShows = document.querySelector('#shows .list');

    if (!searchInput || !resultsContainerAll || !resultsContainerRecipes || !resultsContainerMenus || !resultsContainerArticles || !resultsContainerShows) {
      return;
    }

    mountFilterSearchInHeader(searchForm, searchInput);

    let currentPage = 1;
    let currentPageRecipes = 1;
    let currentPageMenus = 1;
    let currentPageArticles = 1;
    let currentPageShows = 1;
    const perPage = 45;

    const resultContainers = [
      resultsContainerAll,
      resultsContainerRecipes,
      resultsContainerArticles,
      resultsContainerShows,
    ];

    function createSkeletonGrid(count = 6) {
      const fragment = document.createDocumentFragment();

      for (let index = 0; index < count; index++) {
        const item = document.createElement('li');
        item.className = 'list-item result-item col-12 col-lg-4 kosher-result-skeleton';
        item.innerHTML = `
          <div class="kosher-result-skeleton__image"></div>
          <div class="kosher-result-skeleton__line kosher-result-skeleton__line--title"></div>
          <div class="kosher-result-skeleton__line"></div>
          <div class="kosher-result-skeleton__line kosher-result-skeleton__line--short"></div>
        `;
        fragment.appendChild(item);
      }

      return fragment;
    }

    function setSearchLoading(isLoading) {
      searchForm.classList.toggle('is-loading', isLoading);

      if (!isLoading) {
        resultContainers.forEach((container) => {
          if (!container) {
            return;
          }

          container.querySelectorAll('.kosher-result-skeleton').forEach((item) => item.remove());
        });
        return;
      }

      resultContainers.forEach((container) => {
        if (!container || container.children.length > 0) {
          return;
        }

        container.appendChild(createSkeletonGrid());
      });
    }

    function updateSearchSummary(query) {
      const summaryQuery = document.querySelector('[data-search-summary-query]');

      if (!summaryQuery) {
        return;
      }

      summaryQuery.textContent = query ? `‘${query}’` : '';
    }

    function getAnalyticsFilters() {
      const params = new URLSearchParams(window.location.search);
      const filters = {};

      params.forEach((value, key) => {
        if (key === 'q' || key === 'op' || key === 'sort') {
          return;
        }

        if (!filters[key]) {
          filters[key] = [];
        }

        filters[key].push(value);
      });

      return filters;
    }

    function trackResultClick(event) {
      const link = event.target.closest('a[href]');
      const card = event.target.closest('.kayco-card, .item-card');

      if (!link || !card || !typeSenseConfig.analyticsNonce) {
        return;
      }

      const resultItem = card.closest('.result-item');
      const list = resultItem ? resultItem.parentElement : null;
      const position = list ? Array.prototype.indexOf.call(list.children, resultItem) + 1 : 0;
      const formData = new FormData();
      const activeTab = document.querySelector('.header-tabs .nav-link.active');

      formData.append('action', typeSenseConfig.analyticsAction || 'kosher_search_track_event');
      formData.append('nonce', typeSenseConfig.analyticsNonce);
      formData.append('event_type', 'click');
      formData.append('query_text', searchInput ? searchInput.value.trim() : '');
      formData.append('post_id', card.dataset.kaycoPostId || card.dataset.kosherPostId || '');
      formData.append('result_position', position);
      formData.append('result_type', card.dataset.kaycoResultType || card.dataset.kosherResultType || '');
      formData.append('tab', activeTab ? activeTab.id.replace('tab-', '') : '');
      formData.append('filters', JSON.stringify(getAnalyticsFilters()));
      formData.append('url', link.href);

      if (navigator.sendBeacon) {
        navigator.sendBeacon(ajaxUrl, formData);
        return;
      }

      fetch(ajaxUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        keepalive: true
      }).catch(() => {});
    }

    searchForm.addEventListener('click', trackResultClick);

    function escapeHTML(value) {
      const element = document.createElement('span');
      element.textContent = value == null ? '' : String(value);
      return element.innerHTML;
    }

    function safeUrl(value, fallback = '#') {
      if (!value) {
        return fallback;
      }

      try {
        const url = new URL(String(value), window.location.origin);

        if (!['http:', 'https:'].includes(url.protocol)) {
          return fallback;
        }

        return url.href;
      } catch (error) {
        return fallback;
      }
    }

    const formatCookTime = (cookTime) => {
      if (!cookTime) return '';
      const hours = Math.floor(cookTime / 60);
      const minutes = cookTime % 60;
      return hours > 0 ? `${hours}h ${minutes > 0 ? `${minutes}m` : ''}` : `${minutes}m`;
    };

    function generateStars(rating) {
      let starsHTML = '';

      for (let index = 1; index <= 5; index++) {
        if (rating >= index) {
          starsHTML += '<span class="kayco-card__star kayco-card__star--full"><i class="bi bi-star-fill"></i></span>';
          continue;
        }

        if (rating >= (index - 0.5)) {
          starsHTML += '<span class="kayco-card__star kayco-card__star--half"><i class="bi bi-star-fill kayco-card__star-base"></i><i class="bi bi-star-fill kayco-card__star-fill"></i></span>';
          continue;
        }

        starsHTML += '<span class="kayco-card__star kayco-card__star--empty"><i class="bi bi-star-fill"></i></span>';
      }

      return starsHTML;
    }

    function normalizeList(value) {
      if (Array.isArray(value)) {
        return value.map((entry) => String(entry).trim()).filter(Boolean);
      }

      if (value == null || value === '') {
        return [];
      }

      return String(value)
        .split(',')
        .map((entry) => entry.trim())
        .filter(Boolean);
    }

    function formatNameList(value) {
      const names = normalizeList(value);

      if (names.length <= 2) {
        return names.join(' and ');
      }

      return names.slice(0, -1).join(', ') + ' and ' + names[names.length - 1];
    }

    function formatResultDate(timestamp) {
      const normalizedTimestamp = Number.parseInt(timestamp, 10);

      if (!Number.isFinite(normalizedTimestamp) || normalizedTimestamp <= 0) {
        return '';
      }

      return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      }).format(new Date(normalizedTimestamp * 1000));
    }

    function renderLinkedText(label, url, className = '') {
      const text = label == null ? '' : String(label);
      const safeHref = safeUrl(url, '');
      const classAttribute = className ? ` class="${className}"` : '';

      if (!text) {
        return '';
      }

      if (safeHref) {
        return `<a href="${safeHref}"${classAttribute}>${text}</a>`;
      }

      return `<span${classAttribute}>${text}</span>`;
    }

    function renderCardImage(imgUrl, itemTitle) {
      if (imgUrl) {
        return `<img src="${imgUrl}" alt="${itemTitle}" class="kayco-card__image" loading="lazy" decoding="async">`;
      }

      return '<span class="kayco-card__image kayco-card__image--placeholder" aria-hidden="true"></span>';
    }

    function renderLikesBubble(likesCount, postId = '') {
      if (likesCount <= 0) {
        return '';
      }

      return `<button type="button" class="kayco-card__like like-button" data-post="${escapeHTML(postId)}" aria-label="${likesCount} saves"><i class="bi bi-heart-fill" aria-hidden="true"></i><span class="like-count">${escapeHTML(likesCount)}</span></button>`;
    }

    const renderResults = (data, totals = {}) => {
      const isAppendingPage = false;
      const activeTabId = getActiveSearchTabId();
      const shouldRenderItemType = (itemType) => {
        if (!isAppendingPage || activeTabId === 'tab-all') {
          return true;
        }

        if (activeTabId === 'tab-recipes') {
          return itemType === 'chef_recipes' || itemType === 'community_recipes';
        }

        if (activeTabId === 'tab-menus') {
          return itemType === 'menus';
        }

        if (activeTabId === 'tab-articles') {
          return itemType === 'articles';
        }

        if (activeTabId === 'tab-shows') {
          return itemType === 'episodes';
        }

        return true;
      };

      if (!isAppendingPage) {
        resultsContainerAll.innerHTML = '';
        resultsContainerRecipes.innerHTML = '';
        resultsContainerMenus.innerHTML = '';
        resultsContainerArticles.innerHTML = '';
        resultsContainerShows.innerHTML = '';
      }

      let allRecipes = [];
      let allMenus = [];
      let allArticles = [];
      let allEpisodes = [];
      let recipesCount = 0;
      let menusCount = 0;
      let articlesCount = 0;
      let showsCount = 0;

      // Define limits for each category in the All tab
      const maxItemsPerCategory = 6;
      let totalRecipes = Number.parseInt(totals.recipes, 10) || 0;
      let totalMenus = Number.parseInt(totals.menus, 10) || 0;
      let totalArticles = Number.parseInt(totals.articles, 10) || 0;
      let totalShows = Number.parseInt(totals.shows, 10) || 0;

      if (data.length > 0) {

        data.forEach((item) => {
          if (!shouldRenderItemType(item.type)) {
            return;
          }

          const resultItem = document.createElement('li');
          resultItem.classList.add('list-item', 'result-item', 'col-12', 'col-lg-4');
          const rating = Number.parseFloat(item.rating) || 0;
          const ratingStarHTML = generateStars(rating);
          const itemTitle = escapeHTML(item.title);
          const itemType = escapeHTML(item.type);
          const itemPostID = escapeHTML(item.postID);
          const imgUrl = safeUrl(item.img, '');
          const itemPermalink = safeUrl(item.permalink || item.url);
          const chefName = escapeHTML(formatNameList(item.chef));
          const authorName = escapeHTML(item.author);
          const showName = escapeHTML(item.show);
          const episodeName = escapeHTML(item.episode);
          const duration = escapeHTML(item.duration);
          const cookTime = escapeHTML(item.cook_time);
          const likesCount = parseInt(item.likes, 10) || 0;
          const sourceImgUrl = safeUrl(item.sourceImgUrl, '');
          const hasVideo = item.hasVideo === true || item.hasVideo === 'true' || item.hasVideo === 1 || item.hasVideo === '1';
          const itemDate = formatResultDate(item.date);
          const articleCategories = normalizeList(item.article_category);
          const menuCategories = normalizeList(item.categories);
          const occasions = normalizeList(item.occasions);

          let itemContent = '';
          let clonedItem;

          switch (item.type) {
            case 'chef_recipes':
            case 'community_recipes': {
              const defaultSourceImg = 'https://images.kosher.com/uploads/Home_Cooks.png';
              const chefUrl = item.sourceImgUrl === defaultSourceImg ? '' : safeUrl(item.chef_url, '');
              const recipeMeta = [];
              const difficulty = escapeHTML(item.difficulty || '');
              const hasPassoverTag = occasions.some((occasion) => occasion.toLowerCase() === 'passover');

              if (chefName) {
                recipeMeta.push(renderLinkedText(chefName, chefUrl, 'kayco-card__author'));
              }

              if (cookTime && cookTime !== 'N/A') {
                recipeMeta.push(`<span class="kayco-card__text">${cookTime}</span>`);
              }

              if (sourceImgUrl) {
                recipeMeta.push(`<span class="kayco-card__source"><img src="${sourceImgUrl}" alt="" loading="lazy" decoding="async"></span>`);
              }

              itemContent = `
                <article class="kayco-card kayco-card--standard kayco-card--recipes" data-kayco-post-id="${itemPostID}" data-kayco-result-type="${itemType}">
                  <div class="kayco-card__media">
                    <a class="kayco-card__image-link" href="${itemPermalink}">
                      ${renderCardImage(imgUrl, itemTitle)}
                    </a>
                    ${hasVideo ? '<span class="kayco-card__has-video" aria-hidden="true"><i class="bi bi-play"></i></span>' : ''}
                    ${renderLikesBubble(likesCount, itemPostID)}
                    ${difficulty ? `<span class="kayco-card__badge kayco-card__badge--primary">${difficulty}</span>` : ''}
                    ${hasPassoverTag ? '<span class="kayco-card__badge kayco-card__badge--secondary">Passover</span>' : ''}
                  </div>
                  <div class="kayco-card__body">
                    <h3 class="kayco-card__title"><a href="${itemPermalink}">${itemTitle}</a></h3>
                    ${recipeMeta.length ? `<div class="kayco-card__meta">${recipeMeta.join('')}</div>` : ''}
                    ${rating > 0 ? `<div class="kayco-card__rating"><div class="kayco-card__stars" aria-hidden="true">${ratingStarHTML}</div><span class="kayco-card__rating-summary">${escapeHTML(rating.toFixed(1))}</span></div>` : ''}
                  </div>
                </article>
              `;

              resultItem.innerHTML = itemContent;
              clonedItem = resultItem.cloneNode(true);
              resultsContainerRecipes.appendChild(clonedItem);

              recipesCount++;

              if (allRecipes.length < maxItemsPerCategory) {
                allRecipes.push(itemContent);
              }

              break;
            }

            case 'menus': {
              const menuMeta = [];
              const menuAuthor = escapeHTML(item.author_name || item.author || '');
              const sectionsCount = parseInt(item.sections_count, 10) || 0;
              const cardsCount = parseInt(item.cards_count, 10) || 0;
              const categoryMarkup = menuCategories.length
                ? `<div class="kayco-card__pills">${menuCategories.map((category) => `<span class="kayco-card__pill">${escapeHTML(category)}</span>`).join('')}</div>`
                : '';

              if (menuAuthor) {
                menuMeta.push(`<span class="kayco-card__author">${menuAuthor}</span>`);
              }

              if (sectionsCount > 0) {
                menuMeta.push(`<span class="kayco-card__text">${sectionsCount} ${sectionsCount === 1 ? 'section' : 'sections'}</span>`);
              }

              if (cardsCount > 0) {
                menuMeta.push(`<span class="kayco-card__text">${cardsCount} ${cardsCount === 1 ? 'item' : 'items'}</span>`);
              }

              itemContent = `
                <article class="kayco-card kayco-card--standard kayco-card--menus" data-kayco-post-id="${itemPostID}" data-kayco-result-type="${itemType}">
                  <div class="kayco-card__media">
                    <a class="kayco-card__image-link" href="${itemPermalink}">
                      ${renderCardImage(imgUrl, itemTitle)}
                    </a>
                    ${renderLikesBubble(likesCount, itemPostID)}
                    <span class="kayco-card__badge kayco-card__badge--primary">Menu</span>
                  </div>
                  <div class="kayco-card__body">
                    <h3 class="kayco-card__title"><a href="${itemPermalink}">${itemTitle}</a></h3>
                    ${menuMeta.length ? `<div class="kayco-card__meta">${menuMeta.join('')}</div>` : ''}
                    ${categoryMarkup}
                  </div>
                </article>
              `;

              resultItem.innerHTML = itemContent;
              clonedItem = resultItem.cloneNode(true);
              resultsContainerMenus.appendChild(clonedItem);

              menusCount++;

              if (allMenus.length < maxItemsPerCategory) {
                allMenus.push(itemContent);
              }

              break;
            }

            case 'articles': {
              const authorUrl = safeUrl(item.author_article_url, '');
              const articleMeta = [];
              const pillsMarkup = articleCategories.length
                ? `<div class="kayco-card__pills">${articleCategories.map((category) => `<span class="kayco-card__pill">${escapeHTML(category)}</span>`).join('')}</div>`
                : '';

              if (authorName) {
                articleMeta.push(renderLinkedText(authorName, authorUrl, 'kayco-card__author'));
              }

              if (itemDate) {
                articleMeta.push(`<span class="kayco-card__text">${escapeHTML(itemDate)}</span>`);
              }

              itemContent = `
                <article class="kayco-card kayco-card--standard kayco-card--articles" data-kayco-post-id="${itemPostID}" data-kayco-result-type="${itemType}">
                  <div class="kayco-card__media">
                    <a class="kayco-card__image-link" href="${itemPermalink}">
                      ${renderCardImage(imgUrl, itemTitle)}
                    </a>
                    ${renderLikesBubble(likesCount, itemPostID)}
                  </div>
                  <div class="kayco-card__body">
                    <h3 class="kayco-card__title"><a href="${itemPermalink}">${itemTitle}</a></h3>
                    ${articleMeta.length ? `<div class="kayco-card__meta">${articleMeta.join('')}</div>` : ''}
                    ${pillsMarkup}
                    <span class="kayco-card__type">article</span>
                  </div>
                </article>
              `;

              resultItem.innerHTML = itemContent;
              clonedItem = resultItem.cloneNode(true);
              resultsContainerArticles.appendChild(clonedItem);

              articlesCount++;

              if (allArticles.length < maxItemsPerCategory) {
                allArticles.push(itemContent);
              }

              break;
            }

            case 'episodes': {
              const showUrl = safeUrl(item.show_url, '');
              const episodeChefUrl = safeUrl(item.episode_chef_url, '');
              const episodeUser = escapeHTML(item.user);
              const episodeMeta = [];

              if (episodeUser) {
                episodeMeta.push(renderLinkedText(episodeUser, episodeChefUrl, 'kayco-card__author'));
              }

              if (episodeName) {
                episodeMeta.push(`<span class="kayco-card__text">${episodeName}</span>`);
              }

              if (itemDate) {
                episodeMeta.push(`<span class="kayco-card__text">${escapeHTML(itemDate)}</span>`);
              }

              itemContent = `
                <article class="kayco-card kayco-card--standard kayco-card--episodes" data-kayco-post-id="${itemPostID}" data-kayco-result-type="${itemType}">
                  <div class="kayco-card__media">
                    <a class="kayco-card__image-link" href="${itemPermalink}">
                      ${renderCardImage(imgUrl, itemTitle)}
                    </a>
                    ${hasVideo ? '<span class="kayco-card__has-video" aria-hidden="true"><i class="bi bi-play"></i></span>' : ''}
                    ${renderLikesBubble(likesCount, itemPostID)}
                    ${duration ? `<span class="kayco-card__badge kayco-card__badge--secondary">${duration}</span>` : ''}
                  </div>
                  <div class="kayco-card__body">
                    <h3 class="kayco-card__title"><a href="${itemPermalink}">${itemTitle}</a></h3>
                    ${showName ? `<p class="kayco-card__context kayco-card__context--after-title">${renderLinkedText(showName, showUrl)}</p>` : ''}
                    ${episodeMeta.length ? `<div class="kayco-card__meta">${episodeMeta.join('')}</div>` : ''}
                  </div>
                </article>
              `;

              resultItem.innerHTML = itemContent;
              clonedItem = resultItem.cloneNode(true);
              resultsContainerShows.appendChild(clonedItem);

              showsCount++;

              if (allEpisodes.length < maxItemsPerCategory) {
                allEpisodes.push(itemContent);
              }

              break;
            }
          }
        });




     
        

// Main function to create and append the ul for results
const createAndAppendUl = async (items, queryTitle, link, type, hideLink = false, totalItems = 0) => {
  // Create the query title and "Load More" link as separate elements, not part of the ul
  const titleLinkElement = createQueryTitleAndLink(queryTitle, link, hideLink, type, items.length, totalItems);
  resultsContainerAll.appendChild(titleLinkElement); // Append it before the swiper container

  // Determine the maximum number of items per ul and column size based on content type
  const maxItemsPerUl = 6;
  const columnClass = 'col-lg-4';

  // Create the Swiper container div
  const divElement = document.createElement('div');
  divElement.classList.add('swiper'); // Swiper container class

  // Create a new ul element for the items
  let ulElement = document.createElement('ul');
  ulElement.classList.add('list', 'row', `list-${type}`, 'swiper-wrapper');
  divElement.appendChild(ulElement); // Append ul to the swiper container

  // Append items to the ul
  items.forEach((itemContent, index) => {
    const allItem = document.createElement('li');
    allItem.classList.add('list-item', 'result-item', 'swiper-slide', 'col-12', columnClass);
    allItem.innerHTML = itemContent;
    ulElement.appendChild(allItem);

    // After reaching the max number of items (4 for articles, 6 for others), close the current <ul> and start a new one
    if ((index + 1) % maxItemsPerUl === 0) {
      resultsContainerAll.appendChild(divElement);  // Append Swiper container with the current <ul>
      
      // Create a new Swiper container and ul element for the next set of items
      const newDivElement = document.createElement('div');
      newDivElement.classList.add('swiper'); // New Swiper container

      ulElement = document.createElement('ul');
      ulElement.classList.add('list', 'row', `list-${type}`, 'swiper-wrapper');
      newDivElement.appendChild(ulElement); // Append new ul to the new swiper container

      divElement.appendChild(newDivElement); // Add the new swiper to the main container
    }
  });

  // Append any remaining items in the last <ul>
  if (ulElement.children.length > 0) {
    resultsContainerAll.appendChild(divElement);
  }

  if (totalItems > items.length) {
    resultsContainerAll.appendChild(createLoadMoreLink(link, type));
  }



  // Initialize Swiper after the DOM elements have been appended
  const md = new MobileDetect(window.navigator.userAgent);

  if (md.mobile()) {
    const swiperSlides = document.querySelectorAll('.swiper .swiper-slide');
    swiperSlides.forEach(slide => {
      slide.classList.remove('col-12', 'col-lg-4');
    });

    new Swiper('.swiper', {
      direction: 'horizontal',
      slidesPerView: 1.5,
      spaceBetween: 20,
    });
  }
};


const getPostTypeLabel = (type) => {
  if (type === 'recipe') {
    return 'Recipes';
  }

  if (type === 'episodes') {
    return 'Episodes';
  }

  return type.charAt(0).toUpperCase() + type.slice(1);
};

const createQueryTitleAndLink = (queryTitle, link, hideLink, type, shownCount = 0, totalItems = 0) => {
  const queryContainer = document.createElement('div');
  queryContainer.classList.add('list-all__wrapper');

  // Add the title
  const titleElement = document.createElement('p');
  titleElement.classList.add('query_title');
  titleElement.textContent = getPostTypeLabel(type);
  queryContainer.appendChild(titleElement);

  return queryContainer;
};

const createLoadMoreLink = (link, type) => {
  const linkContainer = document.createElement('div');
  linkContainer.classList.add('list-all__load-more');
    const linkElement = document.createElement('a');
    linkElement.href = link;
    if(type == 'recipe') {
      type = 'recipes';
    }

    linkElement.innerHTML = '<span>Load More</span>';
    linkElement.classList.add('query-link'); // Optional: Add a class for easier debugging

    // Add the event listener
    linkElement.addEventListener('click', function (event) {
      event.preventDefault(); // Prevent the default link behavior
      activateTab(event); // Trigger the tab activation
      syncFilterLayout(`tab-${linkElement.getAttribute('href').replace('#', '')}`);
    });
    

    linkContainer.appendChild(linkElement);

  return linkContainer;
};

// CUSTOM TITLES
function buildTitles(searchQuery, customSorting) {

  const formattedQuery = capitalizeSearchQuery(searchQuery);

  if (!customSorting || customSorting == 'default') {
    return {
      recipesTitle: searchQuery ? `"${formattedQuery}" in Recipes` : 'Most Recent Recipes',
      menusTitle: searchQuery ? `"${formattedQuery}" in Menus` : 'Most Recent Menus',
      articlesTitle: searchQuery ? `"${formattedQuery}" in Articles` : 'Most Recent Articles',
      episodesTitle: searchQuery ? `"${formattedQuery}" in Episodes` : 'Most Recent Episodes'
    };
  }

  let prefix = '';

  switch (customSorting) {
    case 'title:asc':
    case 'title_sort:asc':
      prefix = 'A-Z';
      break;

    case 'title:desc':
    case 'title_sort:desc':
      prefix = 'Z-A';
      break;

    case 'date:desc':
      prefix = 'Newest';
      break;

    case 'date:asc':
      prefix = 'Oldest';
      break;

    case 'rating:desc':
      prefix = 'Most Popular';
      break;
  }

  return {
    recipesTitle: searchQuery
      ? `${prefix} "${formattedQuery}" in Recipes`
      : `${prefix} Recipes`,

    menusTitle: searchQuery
      ? `${prefix} "${formattedQuery}" in Menus`
      : `${prefix} Menus`,

    articlesTitle: searchQuery
      ? `${prefix} "${formattedQuery}" in Articles`
      : `${prefix} Articles`,

    episodesTitle: searchQuery
      ? `${prefix} "${formattedQuery}" in Episodes`
      : `${prefix} Episodes`
  };
}

/*____________________________*/
/* SORT                       */
/*____________________________*/



function mapSortToUrl(value) {
  switch (value) {
    case 'title:asc':
    case 'title_sort:asc': return 'title-asc';
    case 'title:desc':
    case 'title_sort:desc': return 'title-desc';
    case 'date:desc': return 'new';
    case 'date:asc': return 'old';
    case 'rating:desc': return 'popular';
    default: return '';
  }
}

function mapUrlToSort(value) {
  switch (value) {
    case 'title-asc': return 'title_sort:asc';
    case 'title-desc': return 'title_sort:desc';
    case 'new': return 'date:desc';
    case 'old': return 'date:asc';
    case 'popular': return 'rating:desc';
    default: return '';
  }
}

function updateUrlSort(sortValue) {
  const url = new URL(window.location);

  if (!sortValue) {
    url.searchParams.delete('sort');
  } else {
    url.searchParams.set('sort', sortValue);
  }

  window.history.replaceState({}, '', url);
}

function resetSorting() {
  customSorting = '';

  if (sortDropdown) {
    sortDropdown.value = 'default';
  }

  const url = new URL(window.location);
  url.searchParams.delete('sort');
  window.history.replaceState({}, '', url);
}

if (searchInput) {
    
  searchInput.addEventListener('input', function () {

    const currentQuery = this.value.trim();

    if (currentQuery !== lastQuery) {
      resetSorting();
    }

    lastQuery = currentQuery;
  });
}



function applySorting(value) {
  const normalizedValue = value && value !== 'default' ? value : '';

  customSorting = normalizedValue;

  if (sortDropdown) {
    sortDropdown.value = normalizedValue || 'default';
  }

  sidebarSortRadios.forEach((radio) => {
    radio.checked = radio.value === (normalizedValue || 'default');
  });

  updateUrlSort(mapSortToUrl(normalizedValue));
  resetPaginationState();
  executeSearch(1, 1, 1, 1);
}

if (sortDropdown) {
  sortDropdown.addEventListener('change', function () {
    applySorting(this.value);
  });
}

sidebarSortRadios.forEach((radio) => {
  radio.addEventListener('change', function () {
    if (!this.checked) {
      return;
    }

    applySorting(this.value);
  });
});

function initSortFromUrl() {
  if (!sortDropdown) return;

  const urlParams = new URLSearchParams(window.location.search);
  const sortParam = urlParams.get('sort');

  if (!sortParam) {
    customSorting = '';
    sortDropdown.value = 'default';
    sidebarSortRadios.forEach((radio) => {
      radio.checked = radio.value === 'default';
    });
    return;
  }

  const mappedSort = mapUrlToSort(sortParam);

  customSorting = mappedSort;
  sortDropdown.value = mappedSort || 'default';
  sidebarSortRadios.forEach((radio) => {
    radio.checked = radio.value === (mappedSort || 'default');
  });
}

if (searchInput) {
  lastQuery = searchInput.value.trim();
}


initSortFromUrl();


        // Function to create the query title and link, hiding the link if necessary

        // Check if the `q` variable exists in the URL
        const urlParams = new URLSearchParams(window.location.search);
        const searchQuery = urlParams.get('q'); // Get the value of `q`

const capitalizeSearchQuery = (query) => {
  if (!query) return '';

  return query
    .toString()
    .toLowerCase()
    .replace(/\b\w/g, char => char.toUpperCase());
};

        // If `q` exists, use it in the title, otherwise default to "Most Recent"
        const formattedQuery = searchQuery ? capitalizeSearchQuery(searchQuery) : '';

const titles = buildTitles(searchQuery, customSorting);

const recipesTitle = titles.recipesTitle;
const menusTitle = titles.menusTitle;
const articlesTitle = titles.articlesTitle;
const episodesTitle = titles.episodesTitle;


        // Determine if we should hide the "See All" link based on the existence of the `q` variable
        const hideLink = searchQuery; // Hide link if `q` does not exist

        // Now dynamically create and append ul only for items, but append the title link separately.
        if (!isAppendingPage) {
          createAndAppendUl(allRecipes, recipesTitle, '#recipes', 'recipe', hideLink, totalRecipes || recipesCount);
        }


        function handleComboButtonClick(event) {
          document.querySelector('#tab-recipes').removeAttribute('data-page');
          document.querySelector('#tab-menus').removeAttribute('data-page');
          document.querySelector('#tab-articles').removeAttribute('data-page');
          document.querySelector('#tab-shows').removeAttribute('data-page');
          
          const comboText = event.target.innerText.trim();
          searchInput.value = comboText;
          updateQueryInURL(comboText);  // Update the query parameter in the URL
          executeSearch();
          toggleCloseIcon();
          tabs[0].click();
      }




        //loadComboTerms(); // Call the async function
        if (!isAppendingPage) {
          createAndAppendUl(allMenus, menusTitle, '#menus', 'menus', hideLink, totalMenus || menusCount);
          createAndAppendUl(allArticles, articlesTitle, '#articles', 'articles', hideLink, totalArticles || articlesCount);
          createAndAppendUl(allEpisodes, episodesTitle, '#shows', 'episodes', hideLink, totalShows || showsCount);
        }

      } else if (!isAppendingPage) {
        const urlParams = new URLSearchParams(window.location.search);
        const searchQuery = urlParams.get('q'); // Get the value of `q`
        const formattedQuery = searchQuery ? searchQuery : '';
        showNoResultsMessage('#all', formattedQuery);
      }

      // Now check if each specific tab has results, otherwise show "No Results"
      if (!isAppendingPage && recipesCount === 0) {
        const urlParams = new URLSearchParams(window.location.search);
        const searchQuery = urlParams.get('q'); // Get the value of `q`
        const formattedQuery = searchQuery ? searchQuery : '';
        showNoResultsMessage('#recipes', formattedQuery);
      }

      if (!isAppendingPage && articlesCount === 0) {
        const urlParams = new URLSearchParams(window.location.search);
        const searchQuery = urlParams.get('q'); // Get the value of `q`
        const formattedQuery = searchQuery ? searchQuery : '';

        showNoResultsMessage('#articles', formattedQuery);
      }

      if (!isAppendingPage && menusCount === 0) {
        const urlParams = new URLSearchParams(window.location.search);
        const searchQuery = urlParams.get('q');
        const formattedQuery = searchQuery ? searchQuery : '';
        showNoResultsMessage('#menus', formattedQuery);
      }

      if (!isAppendingPage && showsCount === 0) {
        const urlParams = new URLSearchParams(window.location.search);
        const searchQuery = urlParams.get('q'); // Get the value of `q`
        const formattedQuery = searchQuery ? searchQuery : '';
        showNoResultsMessage('#shows', formattedQuery);
      }

      if (!isAppendingPage && recipesCount === 0 && menusCount === 0 && articlesCount === 0 && showsCount === 0) {
        const urlParams = new URLSearchParams(window.location.search);
        const searchQuery = urlParams.get('q'); // Get the value of `q`
        const formattedQuery = searchQuery ? searchQuery : '';
        showNoResultsMessage('#all', formattedQuery);
      }
      
    };

    function getActiveNoResultsFilters() {
      const urlParams = new URLSearchParams(window.location.search);
      const ignoredParams = new Set(['q', 'op', 'sort']);
      const filterLabels = [];

      urlParams.forEach((value, key) => {
        if (ignoredParams.has(key) || !value) {
          return;
        }

        const decodedValue = value;

        if (key === 'author_type') {
          filterLabels.push(getAuthorTypeLabel(decodedValue));
          return;
        }

        if (key === 'ae') {
          filterLabels.push(`${decodedValue} free`);
          return;
        }

        if (key === 'ge') {
          filterLabels.push(`without ${decodedValue}`);
          return;
        }

        if (key === 'cui') {
          filterLabels.push(`excluding ${decodedValue}`);
          return;
        }

        filterLabels.push(decodedValue);
      });

      return filterLabels;
    }

    function formatNoResultsFilters(filters) {
      if (!filters.length) {
        return '';
      }

      if (filters.length === 1) {
        return filters[0];
      }

      if (filters.length === 2) {
        return `${filters[0]} and ${filters[1]}`;
      }

      return `${filters.slice(0, -1).join(', ')}, and ${filters[filters.length - 1]}`;
    }

    function getNoResultsCopy(query) {
      const cleanQuery = (query || '').trim();
      const activeFilters = getActiveNoResultsFilters();
      const filterText = formatNoResultsFilters(activeFilters);

      if (cleanQuery && filterText) {
        return {
          title: `Sorry, we couldn't find anything for "${cleanQuery}" with ${filterText}.`,
          detail: 'Try a different search term or remove one of the filters.'
        };
      }

      if (cleanQuery) {
        return {
          title: `Sorry, we checked and we don't have anything for "${cleanQuery}" by that name!`,
          detail: 'Check your spelling or try a different search term. You can also try asking Aidel at <a href="https://aidel.kosher.com" target="_blank" style="color:rgb(122, 8, 122)">aidel.kosher.com</a>.'
        };
      }

      if (filterText) {
        return {
          title: `Sorry, we couldn't find anything matching ${filterText}.`,
          detail: 'Try removing a filter or choosing a broader option.'
        };
      }

      return {
        title: "Sorry, we couldn't find anything for that search.",
        detail: 'Try a different search term or browse with fewer filters.'
      };
    }

    // Function to show the no-results message inside a specific tab
    const showNoResultsMessage = (tabSelector, query) => {
      const tabContainer = document.querySelector(`${tabSelector} .list`);
      if (tabContainer) {
        const noResultsCopy = getNoResultsCopy(query);
        tabContainer.innerHTML = `
        <div class="kosher-search-filter-no-results">
          <div class="kosher-search-filter-no-results__image">
            <figure>
              <svg width="169" height="110" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g filter="url(&#35;a)">
                  <rect x="1.531" y="13.693" width="93.215" height="93.215" rx="19.42" transform="rotate(-8 1.531 13.693)" fill="#F5F5F5"/>
                  <rect x="2.08" y="14.106" width="92.244" height="92.244" rx="18.934" transform="rotate(-8 2.08 14.106)" stroke="#D9D9D9" stroke-width=".971"/>
                </g>
                <g filter="url(&#35;b)">
                  <rect x="27.438" y="3.568" width="93.215" height="93.215" rx="19.42" fill="#F5F5F5"/>
                  <rect x="27.924" y="4.053" width="92.244" height="92.244" rx="18.934" stroke="#D9D9D9" stroke-width=".971"/>
                </g>
                <g filter="url(&#35;c)">
                  <rect x="54.253" width="93.215" height="93.215" rx="19.42" transform="rotate(8 54.253 0)" fill="#fff"/>
                  <rect x="54.666" y=".548" width="92.244" height="92.244" rx="18.934" transform="rotate(8 54.666 .548)" stroke="#D9D9D9" stroke-width=".971"/>
                </g>
                <mask id="e" fill="#fff">
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M110.21 59.409c0-11.922 9.664-21.586 21.586-21.586 11.922 0 21.586 9.664 21.586 21.586 0 4.585-1.429 8.836-3.867 12.331l17.838 17.838a3.81 3.81 0 0 1-5.387 5.387l-17.838-17.837a21.486 21.486 0 0 1-12.332 3.867c-11.922 0-21.586-9.664-21.586-21.586Zm21.583 13.965c7.714 0 13.968-6.253 13.968-13.967s-6.254-13.968-13.968-13.968-13.967 6.254-13.967 13.968 6.253 13.967 13.967 13.967Z"/>
                </mask>
                <g filter="url(&#35;d)">
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M110.21 59.409c0-11.922 9.664-21.586 21.586-21.586 11.922 0 21.586 9.664 21.586 21.586 0 4.585-1.429 8.836-3.867 12.331l17.838 17.838a3.81 3.81 0 0 1-5.387 5.387l-17.838-17.837a21.486 21.486 0 0 1-12.332 3.867c-11.922 0-21.586-9.664-21.586-21.586Zm21.583 13.965c7.714 0 13.968-6.253 13.968-13.967s-6.254-13.968-13.968-13.968-13.967 6.254-13.967 13.968 6.253 13.967 13.967 13.967Z" fill="#F5F5F5"/>
                </g>
                <path d="m149.515 71.74-.796-.555-.465.667.575.575.686-.687Zm17.838 23.225-.687-.687.687.687Zm-5.387 0-.687.686.687-.686Zm-17.838-17.837.687-.687-.575-.575-.667.465.555.797Zm-12.332-40.276c-12.458 0-22.557 10.1-22.557 22.557h1.942c0-11.385 9.23-20.615 20.615-20.615v-1.942Zm22.557 22.557c0-12.458-10.099-22.557-22.557-22.557v1.942c11.385 0 20.615 9.23 20.615 20.615h1.942Zm-4.041 12.887a22.459 22.459 0 0 0 4.041-12.887h-1.942c0 4.38-1.365 8.438-3.692 11.776l1.593 1.11Zm17.727 16.595-17.837-17.837-1.373 1.373 17.837 17.837 1.373-1.373Zm0 6.76a4.78 4.78 0 0 0 0-6.76l-1.373 1.373a2.837 2.837 0 0 1 0 4.014l1.373 1.373Zm-6.76 0a4.78 4.78 0 0 0 6.76 0l-1.373-1.373a2.838 2.838 0 0 1-4.014 0l-1.373 1.373Zm-17.837-17.837 17.837 17.838 1.373-1.374-17.837-17.837-1.373 1.373Zm-11.646 4.152a22.46 22.46 0 0 0 12.888-4.042l-1.111-1.593a20.516 20.516 0 0 1-11.777 3.693v1.942Zm-22.557-22.557c0 12.458 10.099 22.557 22.557 22.557v-1.942c-11.385 0-20.615-9.23-20.615-20.615h-1.942Zm35.551-.002c0 7.177-5.819 12.996-12.997 12.996v1.942c8.251 0 14.939-6.688 14.939-14.938h-1.942ZM131.793 46.41c7.178 0 12.997 5.819 12.997 12.997h1.942c0-8.25-6.688-14.939-14.939-14.939v1.942Zm-12.996 12.997c0-7.178 5.819-12.997 12.996-12.997v-1.942c-8.25 0-14.938 6.688-14.938 14.939h1.942Zm12.996 12.996c-7.177 0-12.996-5.819-12.996-12.996h-1.942c0 8.25 6.688 14.938 14.938 14.938v-1.942Z" fill="#D9D9D9" mask="url(&#35;e)"/>
                <g filter="url(&#35;f)">
                  <circle cx="131.721" cy="59.383" r="13.963" fill="#fff" fill-opacity=".04"/>
                </g>
                <defs>
                  <filter id="a" x="-2.353" y="-1.222" width="113.049" height="113.049" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                    <feFlood flood-opacity="0" result="BackgroundImageFix"/>
                    <feColorMatrix in="SourceAlpha" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
                    <feOffset dy="1.942"/>
                    <feGaussianBlur stdDeviation="1.942"/>
                    <feComposite in2="hardAlpha" operator="out"/>
                    <feColorMatrix values="0 0 0 0 0.0696 0 0 0 0 0.215657 0 0 0 0 0.4104 0 0 0 0.08 0"/>
                    <feBlend in2="BackgroundImageFix" result="effect1_dropShadow_134_502"/>
                    <feBlend in="SourceGraphic" in2="effect1_dropShadow_134_502" result="shape"/>
                  </filter>
                  <filter id="b" x="23.554" y="1.626" width="100.983" height="100.983" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                    <feFlood flood-opacity="0" result="BackgroundImageFix"/>
                    <feColorMatrix in="SourceAlpha" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
                    <feOffset dy="1.942"/>
                    <feGaussianBlur stdDeviation="1.942"/>
                    <feComposite in2="hardAlpha" operator="out"/>
                    <feColorMatrix values="0 0 0 0 0.0696 0 0 0 0 0.215657 0 0 0 0 0.4104 0 0 0 0.08 0"/>
                    <feBlend in2="BackgroundImageFix" result="effect1_dropShadow_134_502"/>
                    <feBlend in="SourceGraphic" in2="effect1_dropShadow_134_502" result="shape"/>
                  </filter>
                  <filter id="c" x="37.396" y="-1.942" width="113.049" height="113.049" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                    <feFlood flood-opacity="0" result="BackgroundImageFix"/>
                    <feColorMatrix in="SourceAlpha" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
                    <feOffset dy="1.942"/>
                    <feGaussianBlur stdDeviation="1.942"/>
                    <feComposite in2="hardAlpha" operator="out"/>
                    <feColorMatrix values="0 0 0 0 0.0696 0 0 0 0 0.215657 0 0 0 0 0.4104 0 0 0 0.08 0"/>
                    <feBlend in2="BackgroundImageFix" result="effect1_dropShadow_134_502"/>
                    <feBlend in="SourceGraphic" in2="effect1_dropShadow_134_502" result="shape"/>
                  </filter>
                  <filter id="d" x="110.21" y="37.823" width="58.259" height="58.258" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                    <feFlood flood-opacity="0" result="BackgroundImageFix"/>
                    <feBlend in="SourceGraphic" in2="BackgroundImageFix" result="shape"/>
                    <feColorMatrix in="SourceAlpha" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
                    <feOffset dy="-1.942"/>
                    <feComposite in2="hardAlpha" operator="arithmetic" k2="-1" k3="1"/>
                    <feColorMatrix values="0 0 0 0 1 0 0 0 0 1 0 0 0 0 1 0 0 0 1 0"/>
                    <feBlend in2="effect1_innerShadow_134_502" result="effect2_innerShadow_134_502"/>
                  </filter>
                  <filter id="f" x="115.816" y="43.478" width="31.81" height="31.81" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                    <feFlood flood-opacity="0" result="BackgroundImageFix"/>
                    <feGaussianBlur in="BackgroundImageFix" stdDeviation=".971"/>
                    <feComposite in2="SourceAlpha" operator="in" result="effect1_backgroundBlur_134_502"/>
                    <feBlend in="SourceGraphic" in2="effect1_backgroundBlur_134_502" result="shape"/>
                  </filter>
                </defs>
              </svg>
            </figure>
          </div>
          <div class="kosher-search-filter-no-results__content">
            <div class="kosher-search-filter-no-results__content-title">
              <p style="font-size: 25px;">${escapeHTML(noResultsCopy.title)}</p>
              <p style="text-transform: initial !important;font-size:1rem;">${noResultsCopy.detail}</p>
              
            </div>
          </div>
        </div>
      `;

        // Check URL for other parameters besides 'q'
        const hasOtherParams = getActiveNoResultsFilters().length > 0;

        if (hasOtherParams) {
          const filterMessage = document.createElement('div');
          filterMessage.classList.add('kosher-search-filter-no-results__content-filter');
          filterMessage.textContent = `Selected filters: ${formatNoResultsFilters(getActiveNoResultsFilters())}`;

          // Append the message to the end of kosher-search-filter-no-results__content-title
          const contentTitleDiv = tabContainer.querySelector('.kosher-search-filter-no-results__content-title');
          contentTitleDiv.appendChild(filterMessage);
        }

        // Function to add suggestions to the specific tab's comboDiv based on the type
        const addSuggestionsToTab = (tabSelector, suggestions) => {
          const comboDiv = document.querySelector(`${tabSelector} .kosher-search-filter-no-results__content-combo`);

          if (comboDiv) {
            // Clear any existing suggestions in the specific tab
            comboDiv.innerHTML = '';

            // Check if there are suggestions in the config and append them to the comboDiv
            if (suggestions && suggestions.length > 0) {
              suggestions.forEach((suggestion) => {
                const suggestionItem = document.createElement('div');
                suggestionItem.classList.add('kosher-search-filter-no-results__content-combo__item');
                suggestionItem.innerText = suggestion.trim(); // Trim any extra spaces
                comboDiv.appendChild(suggestionItem);

                // Add click event listener to each suggestion item
                suggestionItem.addEventListener('click', () => {
                  searchInput.value = suggestion.trim(); // Set the search input value
                  updateQueryInURL(suggestion.trim());  // Update the query parameter in the URL
                  executeSearch();                      // Execute the search
                  toggleCloseIcon();                    // Optionally toggle close icon
                  tabs[0].click();                      // Simulates clicking the first tab (optional)
                });
              });
            }
          }
        };
        

        addSuggestionsToTab('#all', typeSenseConfig.noResultsSuggestions.recipes);
        addSuggestionsToTab('#recipes', typeSenseConfig.noResultsSuggestions.recipes);
        addSuggestionsToTab('#menus', typeSenseConfig.noResultsSuggestions.menus || typeSenseConfig.noResultsSuggestions.recipes);
        addSuggestionsToTab('#articles', typeSenseConfig.noResultsSuggestions.articles);
        addSuggestionsToTab('#shows', typeSenseConfig.noResultsSuggestions.shows);

      }
    };





function updateCuisineInURL(instance = null) {
  const url = new URL(window.location.href);

  // Remove any existing 'ic' parameters to prevent duplication
  url.searchParams.delete('cui');

  let  selectedCuisines = [];

  // If Tom Select is passed as the instance, add its items to the selected categories
  if (instance) {
    selectedCuisines = instance.items;
  }

  // Get the checked categories from the checkboxes under #nav-include-category
  const includeCuisineCheckboxes = document.querySelectorAll('#nav-include-cuisine input[type="checkbox"]');
  includeCuisineCheckboxes.forEach(checkbox => {
    if (checkbox.checked) {
      selectedCuisines.push(checkbox.value);
    }
  });

  // Loop through selected categories and update the 'ic' parameter in the URL
  selectedCuisines.forEach(cuisine => {
    let decodedCuisine = cuisine;

    // Handle double encoding
    if (cuisine.includes('%2520')) {
      decodedCuisine = decodeURIComponent(decodeURIComponent(cuisine));
    } else if (cuisine.includes('%20')) {
      decodedCuisine = decodeURIComponent(cuisine);
    }

    // Append the formatted category value to 'ic'
    url.searchParams.append('cui', decodedCuisine);
  });

  // Update the URL without reloading the page
  history.pushState({}, '', url);
}


// Helper function to synchronize items between two select instances
function syncSelects(source, target, value, action) {
  if (action === 'add') {
    if (!target.items.includes(value)) {
      target.addItem(value); // Add item if not already selected in the other instance
    }
  } else if (action === 'remove') {
    if (target.items.includes(value)) {
      target.removeItem(value); // Remove item if selected in the other instance
    }
  }
}


// Function to update the counter text based on the number of selected items
function updateCounterSelectionText(tomSelectInstance) {
  const selectedCount = tomSelectInstance.getValue().length; // Get the number of selected options
  const counterElement = document.querySelector(`.counter-selection--${tomSelectInstance.inputId}`)
    || document.querySelector(`.counter-selection--${tomSelectInstance.inputId.replace(/-side$/, '')}`);

  if (!counterElement) {
    return;
  }
  
  // Update the text content based on the selected count
  if (selectedCount > 0) {
    counterElement.style.display = 'flex'; 
    counterElement.textContent = selectedCount;
  } else {
    counterElement.style.display = 'none'; 
    counterElement.textContent = ''; // Clear text if no items are selected
  }
}

function updateCounterElement(selector, count) {
  const counterElement = document.querySelector(selector);

  if (!counterElement) {
    return;
  }

  if (count > 0) {
    counterElement.style.display = 'flex';
    counterElement.textContent = count;
    return;
  }

  counterElement.style.display = 'none';
  counterElement.textContent = '';
}

function getTomSelectOptionLabel(selectInstance, itemId) {
  const option = selectInstance.options[itemId] || {};
  return option.name || option.text || option.value || itemId;
}

function renderRemoteSelectionPills(selectInstance, containerId) {
  const container = document.getElementById(containerId);

  if (!container || !selectInstance) {
    return;
  }

  container.innerHTML = '';

  selectInstance.items.forEach((itemId) => {
    const label = getTomSelectOptionLabel(selectInstance, itemId);
    const button = document.createElement('button');

    button.type = 'button';
    button.classList.add('item-single');
    button.setAttribute('data-value', itemId);
    button.innerHTML = `<span>${escapeHTML(label)}</span> <i class="fa-solid fa-xmark"></i>`;
    button.addEventListener('click', () => {
      selectInstance.removeItem(itemId);
    });

    container.appendChild(button);
  });
}

function syncRemoteFilterSelectionUI(selectInstance, containerId, counterSelector) {
  renderRemoteSelectionPills(selectInstance, containerId);
  updateCounterElement(counterSelector, selectInstance ? selectInstance.items.length : 0);
}

function syncRecipeIngredientsCounter() {
  const includeSelect = document.getElementById('ingredients-select');
  const excludeSelect = document.getElementById('ingredients-exclude-select');
  const includeCount = includeSelect && includeSelect.tomselect ? includeSelect.tomselect.items.length : 0;
  const excludeCount = excludeSelect && excludeSelect.tomselect ? excludeSelect.tomselect.items.length : 0;
  updateCounterElement('.counter-selection--ingredients-remote', includeCount + excludeCount);
}


    // Initialize the include holiday checkboxes
    function initializeIncludeCategoriesCheckboxes() {
      const includeOccasionsCheckboxes = document.querySelectorAll('#nav-include-category input[type="checkbox"]');

      includeOccasionsCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
          updateCategoriesInURL(this);  // Update the URL when a checkbox is changed
          executeSearch();        // Trigger the search after updating the URL
        });
      });
    }

    // Call this function when the page loads
    initializeIncludeCategoriesCheckboxes();




// Initialize Tom Select for Categories
const categoriesSelect = new TomSelect('#in-checkbox-category', {
  placeholder: 'Categories',
  hideSelected: false, // Fix: keep false so deselected options appear again
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>';
    }
  },
  // Event when a new category is selected (added)
  onItemAdd: function (value, item) {
    syncSelects(this, categoriesSelect2, value, 'add');
    updateCategoriesInURL(this); // Update the URL when a category is added
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Event when a category is removed (deselected)
  onItemRemove: function (value) {
    syncSelects(this, categoriesSelect2, value, 'remove');
    updateCategoriesInURL(this); // Update the URL when a category is removed
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Pre-select categories from URL on initialization
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedCategories = urlVars.getAll('ic').map(category => decodeURIComponent(category)); // Decode 'ic' parameters

      // Iterate over each category from the URL
      selectedCategories.forEach(category => {
        let itemId = null;

      // Iterate through the options in this Tom Select instance
      // Iterate through the options in this Tom Select instance
      Object.keys(this.options).forEach((id) => {
        let option = this.options[id];

        // Match the diet name with the option's 'text' value
        if (option.text && option.text.trim() === category.trim()) {
          itemId = id; // Get the ID of the matching option
        }
      });

        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});



// Initialize Tom Select for Categories
const categoriesSelect2 = new TomSelect('#in-checkbox-category-side', {
  placeholder: 'Categories',
  hideSelected: false, // Fix: keep false so deselected options appear again
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>';
    }
  },
  // Event when a new category is selected (added)
  onItemAdd: function (value, item) {
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    syncSelects(this, categoriesSelect, value, 'add');
    updateCategoriesInURL(this); // Update the URL when a category is added
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Event when a category is removed (deselected)
  onItemRemove: function (value) {
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    syncSelects(this, categoriesSelect, value, 'remove');
    updateCategoriesInURL(this); // Update the URL when a category is removed
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Pre-select categories from URL on initialization
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedCategories = urlVars.getAll('ic').map(category => decodeURIComponent(category)); // Decode 'ic' parameters

      // Iterate over each category from the URL
      selectedCategories.forEach(category => {
        let itemId = null;

      // Iterate through the options in this Tom Select instance
      // Iterate through the options in this Tom Select instance
      Object.keys(this.options).forEach((id) => {
        let option = this.options[id];

        // Match the diet name with the option's 'text' value
        if (option.text && option.text.trim() === category.trim()) {
          itemId = id; // Get the ID of the matching option
        }
      });

        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});



// Update Categories (ic) in the URL for both Tom Select and checkboxes
function updateCategoriesInURL(instance = null) {
  const url = new URL(window.location.href);

  // Remove any existing 'ic' parameters to prevent duplication
  url.searchParams.delete('ic');

  let selectedCategories = [];

  // If Tom Select is passed as the instance, add its items to the selected categories
  if (instance) {
    selectedCategories = instance.items;
  }

  // Get the checked categories from the checkboxes under #nav-include-category
  const includeCategoriesCheckboxes = document.querySelectorAll('#nav-include-category input[type="checkbox"]');
  includeCategoriesCheckboxes.forEach(checkbox => {
    if (checkbox.checked) {
      selectedCategories.push(checkbox.value);
    }
  });

  // Loop through selected categories and update the 'ic' parameter in the URL
  selectedCategories.forEach(category => {
    let decodedCategory = category;

    // Handle double encoding
    if (category.includes('%2520')) {
      decodedCategory = decodeURIComponent(decodeURIComponent(category));
    } else if (category.includes('%20')) {
      decodedCategory = decodeURIComponent(category);
    }

    // Append the formatted category value to 'ic'
    url.searchParams.append('ic', decodedCategory);
  });

  // Update the URL without reloading the page
  history.pushState({}, '', url);
}


// ==========================
// MENU FILTER SELECTS
// ==========================
const menuCategoriesSelect = new TomSelect('#in-checkbox-menu-category', {
  placeholder: 'Menu Categories',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: { item: () => '<span style="display:none;"></span>' },

  onItemAdd() {
    updateMenuCategoriesInURL(this);
    updateSelections();
    executeSearch();
    this.control_input.blur();
    this.close();
  },

  onItemRemove() {
    updateMenuCategoriesInURL(this);
    updateSelections();
    executeSearch();
    this.control_input.blur();
    this.close();
  },

  onInitialize() {
    setTimeout(() => {
      const urlVars = new URLSearchParams(window.location.search);
      const selectedFromURL = urlVars.getAll('imc').map(v => decodeURIComponent(v));

      selectedFromURL.forEach(category => {
        let idMatch = null;

        Object.keys(this.options).forEach(id => {
          const opt = this.options[id];
          if (opt.text && opt.text.trim() === category.trim()) idMatch = id;
        });

        if (idMatch) this.addItem(idMatch);
      });
    }, 200);
  }
});

function updateMenuCategoriesInURL(instance = null) {
  const url = new URL(window.location.href);
  url.searchParams.delete('imc');

  const selected = instance ? [...instance.items] : [];

  selected.forEach(v => {
    const decoded = v.includes('%2520')
      ? decodeURIComponent(decodeURIComponent(v))
      : v.includes('%20') ? decodeURIComponent(v) : v;

    url.searchParams.append('imc', decoded);
  });

  history.pushState({}, '', url);
}

function updateMenuRangeInURL(instance, param) {
  const url = new URL(window.location.href);
  url.searchParams.delete(param);

  (instance ? instance.items : []).forEach(value => {
    url.searchParams.append(param, value);
  });

  history.pushState({}, '', url);
}

function initMenuRangeSelect(selector, param, placeholder) {
  return new TomSelect(selector, {
    placeholder,
    hideSelected: false,
    plugins: {
      'checkbox_options': {
        'checkedClassNames': ['ts-checked'],
        'uncheckedClassNames': ['ts-unchecked'],
      }
    },
    controlClass: 'ts-control custom-select-control',
    render: { item: () => '<span style="display:none;"></span>' },

    onItemAdd() {
      updateMenuRangeInURL(this, param);
      updateSelections();
      executeSearch();
      this.control_input.blur();
      this.close();
    },

    onItemRemove() {
      updateMenuRangeInURL(this, param);
      updateSelections();
      executeSearch();
      this.control_input.blur();
      this.close();
    },

    onInitialize() {
      setTimeout(() => {
        const selectedFromURL = new URLSearchParams(window.location.search).getAll(param);
        selectedFromURL.forEach(value => {
          if (this.options[value]) this.addItem(value);
        });
      }, 200);
    }
  });
}

const menuSectionsSelect = initMenuRangeSelect('#menu-sections-select', 'msc', 'Sections');
const menuCardsSelect = initMenuRangeSelect('#menu-cards-select', 'mcc', 'Menu Items');
const menuAuthorSelect = initMenuRangeSelect('#menu-author-select', 'mau', 'Creators');


// ==========================
// ARTICLE CATEGORIES SELECT
// ==========================
const articleCategoriesSelect = new TomSelect('#in-checkbox-article-category', {
  placeholder: 'Article Categories',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: { item: () => '<span style="display:none;"></span>' },

  onItemAdd(value) {
    updateArticleCategoriesInURL(this);
    updateSelections();
    executeSearch();
    this.control_input.blur();
    this.close();
  },

  onItemRemove(value) {
    updateArticleCategoriesInURL(this);
    updateSelections();
    executeSearch();
    this.control_input.blur();
    this.close();
  },

  onInitialize() {
    setTimeout(() => {
      const urlVars = new URLSearchParams(window.location.search);
      const selectedFromURL = urlVars.getAll('iac').map(v => decodeURIComponent(v));

      selectedFromURL.forEach(cat => {
        let idMatch = null;

        Object.keys(this.options).forEach(id => {
          let opt = this.options[id];
          if (opt.text && opt.text.trim() === cat.trim()) idMatch = id;
        });

        if (idMatch) this.addItem(idMatch);
      });
    }, 200);
  }
});

// UPDATE URL FOR ARTICLE CATEGORIES
function updateArticleCategoriesInURL(instance = null) {
  const url = new URL(window.location.href);
  url.searchParams.delete('iac');

  let selected = instance ? [...instance.items] : [];

  document.querySelectorAll('#nav-include-article-category input[type="checkbox"]').forEach(cb => {
    if (cb.checked) selected.push(cb.value);
  });

  selected.forEach(v => {
    let decoded = v.includes('%2520')
      ? decodeURIComponent(decodeURIComponent(v))
      : v.includes('%20') ? decodeURIComponent(v) : v;

    url.searchParams.append('iac', decoded);
  });

  history.pushState({}, '', url);
}


// ==========================
// AUTHORS SELECT
// ==========================
const authorsSelect = new TomSelect('#author-select', {
  placeholder: 'Authors',
  hideSelected: false,
  valueField: 'id',
  labelField: 'name',
  searchField: 'name',
  shouldLoad(query) {
    return query.length >= 3;
  },

  load(query, callback) {
    if (query.length < 3) return callback();
    fetch(`${typeSenseConfig.ajaxUrl}?action=get_chefs&q=${encodeURIComponent(query)}`)
      .then(r => r.json())
      .then(json => callback(json))
      .catch(() => callback());
  },

  render: {
    item: () => '<span style="display:none;"></span>',
    no_results: function (data, escape) {
      return `<div class="no-results">${data.input.length < 3 ? 'Write at least 3 letters to search...' : 'No authors found.'}</div>`;
    },
    option_loading: function () {
      return '<div class="option loading">Searching authors...</div>';
    }
  },

  onItemAdd(value) {
    updateAuthorsInURL(this);
    syncRemoteFilterSelectionUI(this, 'selected-author-container', '.counter-selection--article-author');
    updateSelections();
    executeSearch();
    this.close();
    this.control_input.blur();
  },

  onItemRemove(value) {
    updateAuthorsInURL(this);
    syncRemoteFilterSelectionUI(this, 'selected-author-container', '.counter-selection--article-author');
    updateSelections();
    executeSearch();
    this.close();
    this.control_input.blur();
  },

  onInitialize() {
    const tomSelectInstance = this;

    setTimeout(() => {
      const urlVars = new URLSearchParams(window.location.search);
      const selected = urlVars.getAll('au').map(x => decodeURIComponent(x));

      selected.forEach(authorName => {
        fetch(`${typeSenseConfig.ajaxUrl}?action=get_chefs&q=${encodeURIComponent(authorName)}`)
          .then(response => response.json())
          .then((json) => {
            if (!Array.isArray(json)) {
              return;
            }

            json.forEach((option) => {
              if (option.name && option.name.trim() === authorName.trim()) {
                tomSelectInstance.addOption(option);
                tomSelectInstance.addItem(option.id);
              }
            });

            syncRemoteFilterSelectionUI(tomSelectInstance, 'selected-author-container', '.counter-selection--article-author');
          })
          .catch((error) => {
            console.error('Error fetching authors:', error);
          });
      });
      syncRemoteFilterSelectionUI(tomSelectInstance, 'selected-author-container', '.counter-selection--article-author');
    }, 200);
  }
});

// UPDATE URL FOR AUTHORS
function updateAuthorsInURL(instance = null) {
  const url = new URL(window.location.href);
  url.searchParams.delete('au');

  let selected = [];

  if (instance) {
    instance.items.forEach(id => {
      selected.push(instance.options[id].name);
    });
  }

  selected.forEach(name => {
    let decoded = name.includes('%2520')
      ? decodeURIComponent(decodeURIComponent(name))
      : name.includes('%20') ? decodeURIComponent(name) : name;

    url.searchParams.append('au', decoded);
  });

  history.pushState({}, '', url);
}


// ==========================
// SHOW NAME SELECT
// ==========================
const showSelect = new TomSelect('#show-select', {
  placeholder: 'Show Name',
  hideSelected: false,

  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked']
    }
  },

  render: { item: () => '<span style="display:none;"></span>' },

  onItemAdd() {
    updateShowInURL(this);
    updateSelections();
    executeSearch();
    this.close();
    this.control_input.blur();
  },

  onItemRemove() {
    updateShowInURL(this);
    updateSelections();
    executeSearch();
    this.close();
    this.control_input.blur();
  },

  onInitialize() {
    setTimeout(() => {
      const urlVars = new URLSearchParams(window.location.search);
      const selectedShows = urlVars.getAll('sn').map(x => decodeURIComponent(x));

      selectedShows.forEach(name => {
        Object.keys(this.options).forEach(id => {
          if (this.options[id].text.trim() === name.trim()) this.addItem(id);
        });
      });
    }, 200);
  }
});

// UPDATE URL FOR SHOW NAME
function updateShowInURL(instance = null) {
  const url = new URL(window.location.href);
  url.searchParams.delete('sn');

  let selected = instance ? instance.items : [];

  selected.forEach(v => {
    let decoded = v.includes('%2520')
      ? decodeURIComponent(decodeURIComponent(v))
      : v.includes('%20') ? decodeURIComponent(v) : v;

    url.searchParams.append('sn', decoded);
  });

  history.pushState({}, '', url);
}


// ==========================
// CHEF SELECT  (URL param: vchi)
// ==========================
const showchefSelect = new TomSelect('#show-chef-select', {
  placeholder: 'Chefs',
  hideSelected: false,
  valueField: 'id',
  labelField: 'name',
  searchField: 'name',
  shouldLoad(query) {
    return query.length >= 3;
  },

  load(query, callback) {
    if (query.length < 3) return callback();
    fetch(`${typeSenseConfig.ajaxUrl}?action=get_chefs&q=${encodeURIComponent(query)}`)
      .then(res => res.json())
      .then(json => callback(json))
      .catch(() => callback());
  },

  render: { 
    item: () => '<span style="display:none;"></span>',
    no_results: function (data, escape) {
      return `<div class="no-results">${data.input.length < 3 ? 'Write at least 3 letters to search...' : 'No chefs found.'}</div>`;
    },
    option_loading: function () {
      return '<div class="option loading">Searching chefs...</div>';
    }
  },

  onItemAdd() {
    updateShowChefsInURL(this);
    syncRemoteFilterSelectionUI(this, 'selected-show-chef-container', '.counter-selection--show-chef');
    updateSelections();
    executeSearch();
    this.close();
    this.control_input.blur();
  },

  onItemRemove() {
    updateShowChefsInURL(this);
    syncRemoteFilterSelectionUI(this, 'selected-show-chef-container', '.counter-selection--show-chef');
    updateSelections();
    executeSearch();
    this.close();
    this.control_input.blur();
  },

  onInitialize() {
    const tomSelectInstance = this;

    setTimeout(() => {
      const urlVars = new URLSearchParams(window.location.search);
      const selectedNames = urlVars.getAll('vchi').map(v => decodeURIComponent(v));

      selectedNames.forEach(name => {
        fetch(`${typeSenseConfig.ajaxUrl}?action=get_chefs&q=${encodeURIComponent(name)}`)
          .then(response => response.json())
          .then((json) => {
            if (!Array.isArray(json)) {
              return;
            }

            json.forEach((option) => {
              if (option.name && option.name.trim() === name.trim()) {
                tomSelectInstance.addOption(option);
                tomSelectInstance.addItem(option.id);
              }
            });

            syncRemoteFilterSelectionUI(tomSelectInstance, 'selected-show-chef-container', '.counter-selection--show-chef');
          })
          .catch((error) => {
            console.error('Error fetching show chefs:', error);
          });
      });
      syncRemoteFilterSelectionUI(tomSelectInstance, 'selected-show-chef-container', '.counter-selection--show-chef');
    }, 200);
  }
});

// ==========================
// UPDATE URL FOR CHEFS
// ==========================
function updateShowChefsInURL(instance = null) {
  const url = new URL(window.location.href);
  url.searchParams.delete('vchi');

  let selected = [];

  if (instance) {
    instance.items.forEach(id => {
      selected.push(instance.options[id].name);
    });
  }

  selected.forEach(name => {
    let decoded = name.includes('%2520')
      ? decodeURIComponent(decodeURIComponent(name))
      : name.includes('%20')
        ? decodeURIComponent(name)
        : name;

    url.searchParams.append('vchi', decoded);
  });

  history.pushState({}, '', url);
}


// ==========================
// VIDEO LENGTH SELECT
// ==========================
const videoLengthSelect = new TomSelect('#video-length-select', {
  placeholder: 'Video Length',
  hideSelected: false,

  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked']
    }
  },

  render: { item: () => '<span style="display:none;"></span>' },

  onItemAdd() {
    updateVideoLengthInURL(this);
    updateSelections();
    executeSearch();
    this.close();
    this.control_input.blur();
  },

  onItemRemove() {
    updateVideoLengthInURL(this);
    updateSelections();
    executeSearch();
    this.close();
    this.control_input.blur();
  },

  onInitialize() {
    setTimeout(() => {
      const urlVars = new URLSearchParams(window.location.search);
      const selected = urlVars.getAll('vl').map(v => decodeURIComponent(v));

      selected.forEach(len => {
        Object.keys(this.options).forEach(id => {
          if (this.options[id].text === len) this.addItem(id);
        });
      });
    }, 200);
  }
});

// UPDATE URL FOR VIDEO LENGTH
function updateVideoLengthInURL(instance = null) {
  const url = new URL(window.location.href);
  url.searchParams.delete('vl');

  let selected = instance ? instance.items : [];

  selected.forEach(v => {
    let decoded = v.includes('%2520')
      ? decodeURIComponent(decodeURIComponent(v))
      : v.includes('%20') ? decodeURIComponent(v) : v;

    url.searchParams.append('vl', decoded);
  });

  history.pushState({}, '', url);
}




















// Initialize Tom Select for Cuisine
const cuisineSelect = new TomSelect('#in-checkbox-cuisine', {
  placeholder: 'Cuisines',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>';
    }
  },
  // Event when a new category is selected (added)
  onItemAdd: function (value, item) {
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    updateCuisineInURL(this); // Update the URL when a category is added
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Event when a category is removed (deselected)
  onItemRemove: function (value) {
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    updateCuisineInURL(this); // Update the URL when a category is removed
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Pre-select cuisine from URL on initialization
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedCuisine = urlVars.getAll('cui').map(cuisine => decodeURIComponent(cuisine)); // Decode 'ic' parameters

      // Iterate over each cuisine from the URL
      selectedCuisine.forEach(cuisine => {
        let itemId = null;

      // Iterate through the options in this Tom Select instance
      // Iterate through the options in this Tom Select instance
      Object.keys(this.options).forEach((id) => {
        let option = this.options[id];

        // Match the diet name with the option's 'text' value
        if (option.text && option.text.trim() === cuisine.trim()) {
          itemId = id; // Get the ID of the matching option
        }
      });

        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});

  // Update Sources (is) in the URL
  function updateSourcesInURL(instance) {
    const selectedSources = instance.items;
    const url = new URL(window.location.href);

    // Remove any existing 'is' parameters to prevent duplication
    url.searchParams.delete('is');

    selectedSources.forEach(source => {
      // Decode source if needed
      let decodedSource = decodeURIComponent(source);

      // Append the formatted source value
      url.searchParams.append('is', decodedSource);
    });

    // Update the URL without reloading the page
    history.pushState({}, '', url);
  }

// Initialize Tom Select for Sources
const sourcesSelect = new TomSelect('#in-checkbox-sources', {
  placeholder: 'Sources',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>';
    }
  },
  // Event when a new source is selected (added)
  onItemAdd: function (value, item) {
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    updateSourcesInURL(this); // Update the URL when a source is added
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Event when a source is removed (deselected)
  onItemRemove: function (value) {
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    updateSourcesInURL(this); // Update the URL when a source is removed
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Pre-select sources from URL on initialization
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedSources = urlVars.getAll('is').map(source => decodeURIComponent(source)); // Decode 'is' parameters

      // Iterate over each source from the URL
      selectedSources.forEach(source => {
        let itemId = null;

    // Iterate through the options in this Tom Select instance
    Object.keys(this.options).forEach((id) => {
      let option = this.options[id];

      // Match the diet name with the option's 'text' value
      if (option.text && option.text.trim() === source.trim()) {
        itemId = id; // Get the ID of the matching option
      }
    });


        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});


  function updateDifficultyInURL(instance) {
    const selectedDifficulty = instance.items;
    const url = new URL(window.location.href);

    // Remove any existing 'is' parameters to prevent duplication
    url.searchParams.delete('dif');

    selectedDifficulty.forEach(difficulty => {
      // Decode source if needed
      let decodedDifficulty = decodeURIComponent(difficulty);

      // Append the formatted source value
      url.searchParams.append('dif', decodedDifficulty);
    });

    // Update the URL without reloading the page
    history.pushState({}, '', url);
  }

  // Update Preferences (pf) in the URL
  function updatePreferencesInURL(instance) {
    const selectedPreferences = instance.items;
    const url = new URL(window.location.href);

    // Remove any existing 'is' parameters to prevent duplication
    url.searchParams.delete('pf');

    selectedPreferences.forEach(preference => {
      // Decode source if needed
      let decodedPreferences = decodeURIComponent(preference);

      // Append the formatted source value
      url.searchParams.append('pf', decodedPreferences);
    });

    // Update the URL without reloading the page
    history.pushState({}, '', url);
  }

// Initialize Tom Select for Preferences
const preferencesSelect = new TomSelect('#in-checkbox-preferences', {
  placeholder: 'Dairy | Meat | Parve ',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>';
    }
  },
  // Event when a new source is selected (added)
  onItemAdd: function (value, item) {
    syncSelects(this, preferencesSelect2, value, 'add');
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    updatePreferencesInURL(this); // Update the URL when a source is added
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Event when a source is removed (deselected)
  onItemRemove: function (value) {
    syncSelects(this, preferencesSelect2, value, 'remove');
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    updatePreferencesInURL(this); // Update the URL when a source is removed
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Pre-select sources from URL on initialization
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedPreferences = urlVars.getAll('pf').map(preference => decodeURIComponent(preference)); // Decode 'is' parameters

      // Iterate over each source from the URL
      selectedPreferences.forEach(preference => {
        let itemId = null;

    // Iterate through the options in this Tom Select instance
    Object.keys(this.options).forEach((id) => {
      let option = this.options[id];

      // Match the diet name with the option's 'text' value
      if (option.text && option.text.trim() === preference.trim()) {
        itemId = id; // Get the ID of the matching option
      }
    });


        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});

const preferencesSelect2 = new TomSelect('#in-checkbox-preferences-side', {
  placeholder: 'Dairy | Meat | Parve ',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>';
    }
  },
  // Event when a new source is selected (added)
  onItemAdd: function (value, item) {
    syncSelects(this, preferencesSelect, value, 'add');
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    updatePreferencesInURL(this); // Update the URL when a source is added
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Event when a source is removed (deselected)
  onItemRemove: function (value) {
    syncSelects(this, preferencesSelect, value, 'remove');
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    updatePreferencesInURL(this); // Update the URL when a source is removed
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Pre-select sources from URL on initialization
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedPreferences = urlVars.getAll('pf').map(preference => decodeURIComponent(preference)); // Decode 'is' parameters

      // Iterate over each source from the URL
      selectedPreferences.forEach(preference => {
        let itemId = null;

    // Iterate through the options in this Tom Select instance
    Object.keys(this.options).forEach((id) => {
      let option = this.options[id];

      // Match the diet name with the option's 'text' value
      if (option.text && option.text.trim() === preference.trim()) {
        itemId = id; // Get the ID of the matching option
      }
    });


        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});


// Initialize Tom Select for Preferences
const difficultySelect = new TomSelect('#in-checkbox-difficulty', {
  placeholder: 'Difficulty',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>';
    }
  },
  // Event when a new source is selected (added)
  onItemAdd: function (value, item) {
    syncSelects(this, difficultySelect2, value, 'add');
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    updateDifficultyInURL(this); // Update the URL when a source is added
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Event when a source is removed (deselected)
  onItemRemove: function (value) {
    syncSelects(this, difficultySelect2, value, 'remove');
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    updateDifficultyInURL(this); // Update the URL when a source is removed
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Pre-select sources from URL on initialization
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedDifficulty = urlVars.getAll('dif').map(difficulty => decodeURIComponent(difficulty)); // Decode 'is' parameters

      // Iterate over each source from the URL
      selectedDifficulty.forEach(difficulty => {
        let itemId = null;

    // Iterate through the options in this Tom Select instance
    Object.keys(this.options).forEach((id) => {
      let option = this.options[id];

      // Match the diet name with the option's 'text' value
      if (option.text && option.text.trim() === difficulty.trim()) {
        itemId = id; // Get the ID of the matching option
      }
    });


        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});

const difficultySelect2 = new TomSelect('#in-checkbox-difficulty-side', {
  placeholder: 'Difficulty',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>';
    }
  },
  // Event when a new source is selected (added)
  onItemAdd: function (value, item) {
    syncSelects(this, difficultySelect, value, 'add');
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    updateDifficultyInURL(this); // Update the URL when a source is added
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Event when a source is removed (deselected)
  onItemRemove: function (value) {
    syncSelects(this, difficultySelect, value, 'remove');
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    updateDifficultyInURL(this); // Update the URL when a source is removed
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Pre-select sources from URL on initialization
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedDifficulty = urlVars.getAll('dif').map(difficulty => decodeURIComponent(difficulty)); // Decode 'is' parameters

      // Iterate over each source from the URL
      selectedDifficulty.forEach(difficulty => {
        let itemId = null;

    // Iterate through the options in this Tom Select instance
    Object.keys(this.options).forEach((id) => {
      let option = this.options[id];

      // Match the diet name with the option's 'text' value
      if (option.text && option.text.trim() === difficulty.trim()) {
        itemId = id; // Get the ID of the matching option
      }
    });


        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});

  // Update Allergens (e) in the URL
  function updateAllergensInURL(instance) {
    const selectedAllergens = instance.items;
    const url = new URL(window.location.href);

    // Remove any existing 'ae' parameters to prevent duplication
    url.searchParams.delete('ae');

    selectedAllergens.forEach(allergen => {
      // Remove the "Free" suffix from the allergen string
      let allergenWithoutSuffix = allergen.replace(/Free$/, '').trim();

      // If the allergen has %2520, it's double encoded, so decode it twice
      let decodedAllergen = allergenWithoutSuffix;

      if (allergen.includes('%2520')) {
        decodedAllergen = decodeURIComponent(decodeURIComponent(allergenWithoutSuffix));
      } else if (allergen.includes('%20')) {
        decodedAllergen = decodeURIComponent(allergenWithoutSuffix);
      }

      // Append the formatted allergen value (spaces as '+')
      url.searchParams.append('ae', decodedAllergen);
    });

    // Update the URL without reloading the page
    history.pushState({}, '', url);
  }


// Initialize Tom Select for Allergens
const allergensSelect = new TomSelect('#ex-checkbox-allergens', {
  placeholder: 'Allergens',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>'; // Custom rendering to hide selected items
    }
  },
  // Event when a new allergen is selected (added)
  onItemAdd: function (value, item) {
    syncSelects(this, allergensSelect2, value, 'add');
    updateAllergensInURL(this);  // Update the URL when an allergen is added
    updateSelections();
    executeSearch();             // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  
  },
  // Event when an allergen is removed (deselected)
  onItemRemove: function (value) {
    syncSelects(this, allergensSelect2, value, 'remove');
    updateAllergensInURL(this);  // Update the URL when an allergen is removed
    updateSelections();
    executeSearch();   
    this.control_input.blur(); // Blur the input after adding an item
    this.close();          // Trigger the search after updating the URL
  },
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedAllergens = urlVars.getAll('ae').map(allergen => decodeURIComponent(allergen)); // Decode 'ae' parameters

      // Iterate over each allergen from the URL
      selectedAllergens.forEach(allergen => {
        let itemId = null;
        // Iterate through the options in this Tom Select instance
        Object.keys(this.options).forEach((id) => {
          let option = this.options[id];

          // Match the allergen name with the option value
          if (option.value.trim() === allergen.trim()) {
            itemId = id; // Get the ID of the matching option
          }
        });

        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});

const allergensSelect2 = new TomSelect('#ex-checkbox-allergens-side', {
  placeholder: 'Allergens',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>'; // Custom rendering to hide selected items
    }
  },
  // Event when a new allergen is selected (added)
  onItemAdd: function (value, item) {
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    syncSelects(this, allergensSelect, value, 'add');
    updateAllergensInURL(this);  // Update the URL when an allergen is added
    updateSelections();
    executeSearch();             // Trigger the search after updating the URL
    this.close();
  },
  // Event when an allergen is removed (deselected)
  onItemRemove: function (value) {
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    syncSelects(this, allergensSelect, value, 'remove');
    updateAllergensInURL(this);  // Update the URL when an allergen is removed
    updateSelections();
    executeSearch();             // Trigger the search after updating the URL
  },
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedAllergens = urlVars.getAll('ae').map(allergen => decodeURIComponent(allergen)); // Decode 'ae' parameters

      // Iterate over each allergen from the URL
      selectedAllergens.forEach(allergen => {
        let itemId = null;
        // Iterate through the options in this Tom Select instance
        Object.keys(this.options).forEach((id) => {
          let option = this.options[id];

          // Match the allergen name with the option value
          if (option.value.trim() === allergen.trim()) {
            itemId = id; // Get the ID of the matching option
          }
        });

        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});

    // Initialize the include holiday checkboxes
    function initializeIncludeHolidaysCheckboxes() {
      const includeOccasionsCheckboxes = document.querySelectorAll('#nav-include-holiday input[type="checkbox"]');

      includeOccasionsCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
          updateHolidaysInURL(this);  // Update the URL when a checkbox is changed
          executeSearch();        // Trigger the search after updating the URL
        });
      });
    }

    // Call this function when the page loads
    initializeIncludeHolidaysCheckboxes();

    // Update Diets (di) in the URL
    function updateHolidaysInURL(instance) {
      const selectedHolidays = instance.items;
      const url = new URL(window.location.href);

      // Remove any existing 'di' parameters to prevent duplication
      url.searchParams.delete('hi');

      selectedHolidays.forEach(holiday => {
        // If the diet has %2520, it's double encoded, so decode it twice
        let decodedHoliday = holiday;

        if (holiday.includes('%2520')) {
          decodedHoliday = decodeURIComponent(decodeURIComponent(holiday));
        } else if (holiday.includes('%20')) {
          decodedHoliday = decodeURIComponent(holiday);
        }

        // Append the formatted diet value (spaces as '+')
        url.searchParams.append('hi', decodedHoliday);
      });

      // Update the URL without reloading the page
      history.replaceState({}, '', url);
    }


    const holidaysSelect = new TomSelect('#ex-checkbox-holidays', {
      placeholder: 'Holidays',
      hideSelected: false,
      plugins: {
        'checkbox_options': {
          'checkedClassNames': ['ts-checked'],
          'uncheckedClassNames': ['ts-unchecked'],
        }
      },
      controlClass: 'ts-control custom-select-control',
      render: {
        item: function (data, escape) {
          return '<span style="display:none;"></span>';
        }
      },
      // This event triggers when a new diet is added (selected)
      onItemAdd: function (value, item) {
        syncSelects(this, holidaysSelect2, value, 'add');
        updateHolidaysInURL(this);  // Update the URL when a diet is added
        updateSelections();
        executeSearch();         // Trigger the search after updating the URL
        this.control_input.blur(); // Blur the input after adding an item
        this.close();      },
      // This event triggers when a diet is removed (deselected)
      onItemRemove: function (value) {
        syncSelects(this, holidaysSelect2, value, 'remove');
        updateHolidaysInURL(this);  // Update the URL when a diet is removed
        updateSelections();
        executeSearch();  
        this.control_input.blur(); // Blur the input after adding an item
        this.close();       // Trigger the search after updating the URL
      },
      // Pre-select diets from URL on initialization
      onInitialize: function () {
        // Delay the selection process slightly to ensure the instance is fully initialized
        setTimeout(() => {
          // Fetch the URL parameters
          const urlVars = new URLSearchParams(window.location.search);
          const selectedDiets = urlVars.getAll('hi').map(holiday => decodeURIComponent(holiday)); // Decode 'di' parameters
    
          // Iterate over each diet from the URL
          selectedDiets.forEach(holiday => {
            let itemId = null;
            
            // Iterate through the options in this Tom Select instance
            Object.keys(this.options).forEach((id) => {
              let option = this.options[id];
            
    
          // Match the diet name with the option's 'text' value
          if (option.text && option.text.trim() === holiday.trim()) {
            itemId = id; // Get the ID of the matching option
          }
    });
    
            // Add the item to the select instance if a matching item is found
            if (itemId) {
              this.addItem(itemId); // Add the item by its ID to the Tom Select instance
            }
          });
        }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
      }
    });
    

// Initialize Tom Select for Holidays (side version)
const holidaysSelect2 = new TomSelect('#ex-checkbox-holidays-side', {
  placeholder: 'Holidays',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>'; // Custom rendering to hide selected items
    }
  },
  // This event triggers when a new holiday is added (selected)
  onItemAdd: function (value, item) {
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    syncSelects(this, holidaysSelect, value, 'add');
    updateHolidaysInURL(this);  // Update the URL when a holiday is added
    updateSelections();
    executeSearch();            // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // This event triggers when a holiday is removed (deselected)
  onItemRemove: function (value) {
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    syncSelects(this, holidaysSelect, value, 'remove');
    updateHolidaysInURL(this);  // Update the URL when a holiday is removed
    updateSelections();
    executeSearch();            // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedHolidays = urlVars.getAll('hi').map(holiday => decodeURIComponent(holiday)); // Decode 'hi' parameters

      // Iterate over each holiday from the URL
      selectedHolidays.forEach(holiday => {
        let itemId = null;

    // Iterate through the options in this Tom Select instance
    Object.keys(this.options).forEach((id) => {
      let option = this.options[id];


      // Match the diet name with the option's 'text' value
      if (option.text && option.text.trim() === holiday.trim()) {
        itemId = id; // Get the ID of the matching option
      }
      });


        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});

function updateSelectedIngredients(selectInstance) {
  const selectedItems = selectInstance.selectInstance.items;  // Get selected items
  const container = document.getElementById('selected-ingredients');  // The selected-ingredients container

  // Clear the container first to avoid duplicates
  container.innerHTML = '';

  // Loop through selected items and append them to the container
  selectedItems.forEach(function (item) {
    const ingredient = selectInstance.options[item].name;  // Get the ingredient name
    const ingredientElement = document.createElement('span');
    ingredientElement.classList.add('selected-ingredient-item');  // Add a class for styling
    ingredientElement.textContent = ingredient;  // Set the ingredient name as the text
    container.appendChild(ingredientElement);  // Append the ingredient element to the container
  });
}

let selectedChefs = [];

const chefsSelect = new TomSelect('#chef-select', {
  placeholder: 'Select Chefs',
  hideSelected: false,
  valueField: 'id',  // The field that will be submitted (user ID)
  labelField: 'name',  // The field to display in the dropdown (user display name)
  searchField: 'name',  // The field to search in
  shouldLoad(query) {
    return query.length >= 3;
  },

  load: function (query, callback) {
    if (query.length < 3) {
      return callback();  // Don't load until at least 3 characters are entered
    }

    fetch(`${typeSenseConfig.ajaxUrl}?action=get_chefs&q=${encodeURIComponent(query)}`)
      .then(response => response.json())
      .then(json => {
        callback(json);  // Pass the fetched users to Tom Select
      })
      .catch((error) => {
        callback();  // Handle errors
      });
  },

  render: {
    item: function () {
      return '<span style="display:none;"></span>';
    },
    no_results: function (data, escape) {
      return `<div class="no-results">${data.input.length < 3 ? 'Write at least 3 letters to search...' : 'No chefs found.'}</div>`;
    },
    option_loading: function () {
      return '<div class="option loading">Searching chefs...</div>';
    }
  },

  onItemAdd: function (value, item) {
    updateChefsInURL(this);  // Update the URL with the selected chefs
    updateSelectedChefs(this);  // Update the selected chefs container
    updateSelections();
    executeSearch();  // Trigger the search
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
    },

  onItemRemove: function (value) {
    updateChefsInURL(this);  // Update the URL with the removed chef
    updateSelectedChefs(this);  // Update the selected chefs container
    updateSelections();
    executeSearch();  // Trigger the search
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },

  onInitialize: function () {
    const tomSelectInstance = this;  // Store the reference to the Tom Select instance

    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      const urlVars = new URLSearchParams(window.location.search);
      const selectedChefs = urlVars.getAll('chi').map(chef => decodeURIComponent(chef)); // Decode 'chi' parameters


      selectedChefs.forEach(chefName => {
        let itemId = null;

        // Manually trigger the search to load the chef from the server
        fetch(`${typeSenseConfig.ajaxUrl}?action=get_chefs&q=${encodeURIComponent(chefName)}`)
          .then(response => response.json())
          .then(json => {

            // Iterate over the loaded options and find a match
            json.forEach(option => {
              if (option.name && option.name.trim() === chefName.trim()) {
                itemId = option.id;  // Get the ID of the matching option
                // Add the item to the Tom Select instance
                tomSelectInstance.addOption(option);  // Add the option to the Tom Select dropdown
                tomSelectInstance.addItem(itemId);    // Select the option (add it to the selected items)
              }
            });

            // If a matching item is found, call the custom functions
            if (itemId) {
              updateChefsInURL(tomSelectInstance);  // Update the URL when an allergen is added
              updateSelections();  // Update selections as needed
              executeSearch();  // Trigger the search
            } else {
              console.warn(`No match found for chef: ${chefName}`);
            }
          })
          .catch((error) => {
            console.error('Error fetching chefs:', error);
          });
      });

      updateSelectedChefs(tomSelectInstance);
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});

function updateChefsInURL(selectInstance) {
  const selectedChefs = selectInstance.getValue();  // Get selected chef IDs
  const urlVars = new URLSearchParams(window.location.search);
  urlVars.delete('chi');  // Remove old 'chi' values from the URL

  // Loop through selected chefs and add their names (not IDs) to the URL
  selectedChefs.forEach(chefId => {
    const chefName = selectInstance.options[chefId].name;  // Get the name based on the ID
    urlVars.append('chi', chefName);  // Append the name to 'chi' in the URL
  });

  const newUrl = `${window.location.pathname}?${urlVars.toString()}`;
  window.history.replaceState({}, '', newUrl);  // Update the URL without reloading
}

// Function to update the selected chefs container
function updateSelectedChefs(selectInstance) {
  syncRemoteFilterSelectionUI(selectInstance, 'selected-chef-container', '.counter-selection--chef-select');
}

// Initialize Tom Select for including Ingredients
const ingredientsSelect = new TomSelect('#ingredients-select', {
  placeholder: 'Select Ingredients',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  valueField: 'id',  // The field containing the term ID
  labelField: 'name',  // The field containing the term name
  searchField: 'name',  // The field to search
  shouldLoad(query) {
    return query.length >= 3;
  },
  load: function (query, callback) {
    if (query.length < 3) return callback();  // Do not make a request until at least 3 characters are entered
    fetch(`${typeSenseConfig.ajaxUrl}?action=get_ingredients_terms&q=${encodeURIComponent(query)}`)
      .then(response => response.json())
      .then(json => {
        callback(json);  // Pass the fetched data to Tom Select
      }).catch(() => {
        callback();  // Handle errors
      });
    },

  render: {
    item: function () {
      return '<span style="display:none;"></span>';
    },
    no_results: function (data, escape) {
      return `<div class="no-results">${data.input.length < 3 ? 'Write at least 3 letters to search...' : 'No ingredients found.'}</div>`;
    },
    option_loading: function () {
      return '<div class="option loading">Searching ingredients...</div>';
    }
  },

  onItemAdd: function (value, item) {
    updateIngredientsInURL(this);  // Update the URL when an ingredient is added
    updateSelectedIngredients(this);  // Update the selected ingredients display container
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.close();
  },

  onItemRemove: function (value) {    
    updateIngredientsInURL(this);  // Update the URL when an ingredient is removed
    updateSelectedIngredients(this);  // Update the selected ingredients display container
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
  },

  onInitialize: function () {
    const tomSelectInstance = this;  // Store the reference to the Tom Select instance

    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      const urlVars = new URLSearchParams(window.location.search);
      const selectedIngredients = urlVars.getAll('gi').map(ingredient => decodeURIComponent(ingredient)); // Decode 'gi' parameters


      selectedIngredients.forEach(ingredientName => {
        let itemId = null;

        // Manually trigger the search to load the ingredient from the server
        fetch(`${typeSenseConfig.ajaxUrl}?action=get_ingredients_terms&q=${encodeURIComponent(ingredientName)}`)
          .then(response => response.json())
          .then(json => {

            // Iterate over the loaded options and find a match
            json.forEach(option => {
              if (option.name && option.name.trim() === ingredientName.trim()) {
                itemId = option.id;  // Get the ID of the matching option
                // Add the item to the Tom Select instance
                tomSelectInstance.addOption(option);  // Add the option to the Tom Select dropdown
                tomSelectInstance.addItem(itemId);    // Select the option (add it to the selected items)
              }
            });

            // If a matching item is found, call the custom functions
            if (itemId) {
              updateIngredientsInURL(tomSelectInstance);  // Update the URL when an ingredient is added
              updateSelections();  // Update selections as needed
              executeSearch();  // Trigger the search
            } else {
              console.warn(`No match found for ingredient: ${ingredientName}`);
            }
          })
          .catch((error) => {
            console.error('Error fetching ingredients:', error);
          });
      });

      updateSelectedIngredients(tomSelectInstance);
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});

function updateIngredientsInURL(instance) {
  const url = new URL(window.location.href);

  // Remove any existing 'gi' parameters to prevent duplication
  url.searchParams.delete('gi');

  // Get the selected ingredients from the Tom Select instance
  let selectedIngredients = instance.items;

  // Log the selected items for debugging

  // Loop through the selected ingredients and update the 'gi' parameter with option names
  selectedIngredients.forEach(itemId => {
    const optionName = instance.options[itemId].name; // Get the option name (display text)

    // Log the option name for debugging

    if (optionName) {
      url.searchParams.append('gi', optionName); // Add option name to the URL
    }
  });

  // Update the URL without reloading the page
  history.pushState({}, '', url);
}

// Function to update the selected ingredients container
function updateSelectedIngredients(selectInstance) {
  renderRemoteSelectionPills(selectInstance, 'selected-ingredients-container');
  syncRecipeIngredientsCounter();
}


// Initialize Tom Select for excluding Ingredients
const ingredientsExcludeSelect = new TomSelect('#ingredients-exclude-select', {
  placeholder: 'Exclude Ingredients',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  valueField: 'id',  // The field containing the term ID
  labelField: 'name',  // The field containing the term name
  searchField: 'name',  // The field to search
  shouldLoad(query) {
    return query.length >= 3;
  },
  load: function (query, callback) {
    if (query.length < 3) return callback();  // Do not make a request until at least 3 characters are entered
    fetch(`${typeSenseConfig.ajaxUrl}?action=get_ingredients_terms&q=${encodeURIComponent(query)}`)
      .then(response => response.json())
      .then(json => {
        callback(json);  // Pass the fetched data to Tom Select
      }).catch(() => {
        callback();  // Handle errors
      });
  },

  render: {
    item: function () {
      return '<span style="display:none;"></span>';
    },
    no_results: function (data, escape) {
      return `<div class="no-results">${data.input.length < 3 ? 'Write at least 3 letters to search...' : 'No ingredients found.'}</div>`;
    },
    option_loading: function () {
      return '<div class="option loading">Searching ingredients...</div>';
    }
  },

  onItemAdd: function (value, item) {
    updateExcludedIngredientsInURL(this);  // Update the URL when an excluded ingredient is added
    updateSelectedExcludedIngredients(this);  // Update the selected excluded ingredients container
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
    this.close();
  },

  onItemRemove: function (value) {
    updateExcludedIngredientsInURL(this);  // Update the URL when an excluded ingredient is removed
    updateSelectedExcludedIngredients(this);  // Update the selected excluded ingredients container
    updateSelections();
    executeSearch();  // Trigger the search after updating the URL
  },

  onInitialize: function () {
    const tomSelectInstance = this;  // Store the reference to the Tom Select instance

    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      const urlVars = new URLSearchParams(window.location.search);
      const selectedExcludedIngredients = urlVars.getAll('ge').map(ingredient => decodeURIComponent(ingredient)); // Decode 'ge' parameters

      selectedExcludedIngredients.forEach(ingredientName => {
        let itemId = null;

        // Manually trigger the search to load the excluded ingredient from the server
        fetch(`${typeSenseConfig.ajaxUrl}?action=get_ingredients_terms&q=${encodeURIComponent(ingredientName)}`)
          .then(response => response.json())
          .then(json => {
            // Iterate over the loaded options and find a match
            json.forEach(option => {
              if (option.name && option.name.trim() === ingredientName.trim()) {
                itemId = option.id;  // Get the ID of the matching option
                // Add the item to the Tom Select instance
                tomSelectInstance.addOption(option);  // Add the option to the Tom Select dropdown
                tomSelectInstance.addItem(itemId);    // Select the option (add it to the selected items)
              }
            });

            // If a matching item is found, call the custom functions
            if (itemId) {
              updateExcludedIngredientsInURL(tomSelectInstance);  // Update the URL when an excluded ingredient is added
              updateSelections();  // Update selections as needed
              executeSearch();  // Trigger the search
            } else {
              console.warn(`No match found for excluded ingredient: ${ingredientName}`);
            }
          })
          .catch((error) => {
            console.error('Error fetching excluded ingredients:', error);
          });
      });

      updateSelectedExcludedIngredients(tomSelectInstance);
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});

// Function to update the excluded ingredients in the URL
function updateExcludedIngredientsInURL(instance) {
  const url = new URL(window.location.href);

  // Remove any existing 'ge' parameters to prevent duplication
  url.searchParams.delete('ge');

  // Get the selected excluded ingredients from the Tom Select instance
  let selectedExcludedIngredients = instance.items;

  // Loop through the selected excluded ingredients and update the 'ge' parameter with option names
  selectedExcludedIngredients.forEach(itemId => {
    const optionName = instance.options[itemId].name; // Get the option name (display text)

    if (optionName) {
      url.searchParams.append('ge', optionName); // Add option name to the URL
    }
  });

  // Update the URL without reloading the page
  history.pushState({}, '', url);
}


// Function to update the selected excluded ingredients container
function updateSelectedExcludedIngredients(selectInstance) {
  renderRemoteSelectionPills(selectInstance, 'selected-ingredients-exclude-container');
  syncRecipeIngredientsCounter();
}

  // Function to update excluded ingredients in URL
  function updateIngredientsExcludeInURL(selectInstance) {
    const selectedIngredientsExclude = selectInstance.items;
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.delete('ge');  // Remove the previous 'e' parameters

    selectedIngredientsExclude.forEach(function (ingredient) {
      urlParams.append('ge', encodeURIComponent(ingredient));  // Add new 'e' parameters for each excluded ingredient
    });

    history.replaceState(null, '', '?' + urlParams.toString());  // Update the URL without reloading the page
  }

  // Update Diets (di) in the URL
  function updateDietsInURL(instance) {
    const selectedDiets = instance.items;
    const url = new URL(window.location.href);

    // Remove any existing 'di' parameters to prevent duplication
    url.searchParams.delete('di');

    selectedDiets.forEach(diet => {
      // If the diet has %2520, it's double encoded, so decode it twice
      let decodedDiet = diet;

      if (diet.includes('%2520')) {
        decodedDiet = decodeURIComponent(decodeURIComponent(diet));
      } else if (diet.includes('%20')) {
        decodedDiet = decodeURIComponent(diet);
      }

      // Append the formatted diet value (spaces as '+')
      url.searchParams.append('di', decodedDiet);
    });

    // Update the URL without reloading the page
    history.pushState({}, '', url);
  }

  // Function to handle the All tab specifically
  function handleAllTabClick() {
    // When the All tab is clicked, always execute the search on page 1
    executeSearch(1);
  }

  // Function to update the data-page attribute for the active tab
  function updateTabPage(tabId, page) {
    const tabElement = document.getElementById(tabId);
    if (tabElement) {
      tabElement.setAttribute('data-page', page);
    }
  }

  function resetPaginationState() {
    currentPage = 1;
    currentPageRecipes = 1;
    currentPageArticles = 1;
    currentPageShows = 1;
    isPagination = false;

    document.querySelectorAll('.header-tabs .nav-link').forEach((tab) => {
      tab.removeAttribute('data-page');
    });
  }

// ================================
// SEARCH FILTER LAYOUT
// ================================
const filterLayoutQuery = window.matchMedia('(max-width: 856px)');

function getTabIdFromURL() {
  const op = (new URLSearchParams(window.location.search).get('op') || '').toLowerCase();

  if (op === 'articles') {
    return 'tab-articles';
  }

  if (op === 'menus') {
    return 'tab-menus';
  }

  if (op === 'shows' || op === 'episodes') {
    return 'tab-shows';
  }

  if (op === 'recipes') {
    return 'tab-recipes';
  }

  return 'tab-all';
}

function getActiveSearchTabId() {
  const activeTab = document.querySelector('.header-tabs .nav-link.active');
  return activeTab ? activeTab.id : getTabIdFromURL();
}

function isVisibleElement(element) {
  if (!element) {
    return false;
  }

  return window.getComputedStyle(element).display !== 'none';
}

function setDefaultAccordionState(container) {
  if (!container || !isVisibleElement(container)) {
    return;
  }

  const visibleItems = Array.from(container.querySelectorAll('.accordion-item'))
    .filter((item) => isVisibleElement(item) && !item.querySelector('#panelsStayOpen-collapseHomeCooks'));

  visibleItems.forEach((item, index) => {
    const button = item.querySelector('.accordion-button');
    const collapse = item.querySelector('.accordion-collapse');
    const shouldOpen = false;

    if (button) {
      button.classList.toggle('collapsed', !shouldOpen);
      button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    }

    if (collapse) {
      collapse.classList.toggle('show', shouldOpen);
    }
  });

  window.setTimeout(() => {
    openShownFilterDropdowns(container);
    bindRemoteFilterSearchInputs();
  }, 0);
}

function moveFilterWrapper(wrapper, desktopTarget, mobileTarget) {
  if (!wrapper || !desktopTarget || !mobileTarget) {
    return;
  }

  const target = filterLayoutQuery.matches ? mobileTarget : desktopTarget;

  if (wrapper.parentElement !== target) {
    target.appendChild(wrapper);
  }
}

function syncFilterLayout(tabId = getActiveSearchTabId()) {
  const searchForm = document.querySelector('.kosher-search-filter-form');
  const recipesWrapper = document.querySelector('.recipes-filters-wrapper');
  const menusWrapper = document.querySelector('.menus-filters-wrapper');
  const articlesWrapper = document.querySelector('.articles-filters-wrapper');
  const showsWrapper = document.querySelector('.shows-filters-wrapper');
  const recipeOnly = document.querySelector('.recipe-only');
  const menusMobilePanel = document.querySelector('[data-mobile-filter-panel="menus"]');
  const articlesMobilePanel = document.querySelector('[data-mobile-filter-panel="articles"]');
  const showsMobilePanel = document.querySelector('[data-mobile-filter-panel="shows"]');
  const filterResults = document.querySelector('.kosher-search-filter-form-result');
  const sideFilters = document.querySelector('.kosher-search-filter-form-result__filter');
  const isMobileLayout = filterLayoutQuery.matches;
  const isRecipeTab = tabId === 'tab-all' || tabId === 'tab-recipes';
  const isMenuTab = tabId === 'tab-menus';
  const isArticleTab = tabId === 'tab-articles';
  const isShowTab = tabId === 'tab-shows';

  if (searchForm) {
    searchForm.classList.toggle('is-recipe-tab', isRecipeTab);
    searchForm.classList.toggle('is-menus-tab', isMenuTab);
    searchForm.classList.toggle('is-articles-tab', isArticleTab);
    searchForm.classList.toggle('is-shows-tab', isShowTab);
  }

  moveFilterWrapper(menusWrapper, menusMobilePanel, menusMobilePanel);
  moveFilterWrapper(articlesWrapper, articlesMobilePanel, articlesMobilePanel);
  moveFilterWrapper(showsWrapper, showsMobilePanel, showsMobilePanel);

  if (recipesWrapper) {
    recipesWrapper.style.display = isRecipeTab ? 'block' : 'none';
  }

  if (recipeOnly) {
    recipeOnly.style.display = isRecipeTab ? 'flex' : 'none';
  }

  if (articlesWrapper) {
    articlesWrapper.style.display = isArticleTab ? 'block' : 'none';
  }

  if (menusWrapper) {
    menusWrapper.style.display = isMenuTab ? 'block' : 'none';
  }

  if (showsWrapper) {
    showsWrapper.style.display = isShowTab ? 'block' : 'none';
  }

  if (filterButton) {
    filterButton.style.display = isMobileLayout ? 'inline-flex' : 'none';
  }

  document.querySelectorAll('.toggle-button-container').forEach((toggleContainer) => {
    if (!isRecipeTab) {
      toggleContainer.style.display = 'none';
      return;
    }

    toggleContainer.style.display = toggleContainer.closest('.search-controls-right') && !isMobileLayout ? 'flex' : '';
  });

  if (filterResults) {
    filterResults.classList.remove('no-filters');
  }

  if (!isMobileLayout && sideFilters) {
    sideFilters.classList.remove('opened-filter');

    if (applyFilterBtn) {
      applyFilterBtn.classList.remove('active-btn');
    }
  }

  [recipesWrapper, menusWrapper, articlesWrapper, showsWrapper].forEach(setDefaultAccordionState);
  bindSidebarAccordionBehavior();
}

syncFilterLayout();

if (filterLayoutQuery.addEventListener) {
  filterLayoutQuery.addEventListener('change', function () {
    syncFilterLayout();
  });
} else if (filterLayoutQuery.addListener) {
  filterLayoutQuery.addListener(function () {
    syncFilterLayout();
  });
}


// ================================
// TABS INTERACTION LOGIC
// ================================
function clearInactiveTabFilterControls(tabId) {
  const activeFilterType = tabId === 'tab-all'
    ? 'recipes'
    : String(tabId || '').replace(/^tab-/, '');
  const controlsByType = {
    recipes: [
      holidaysSelect, holidaysSelect2, allergensSelect, allergensSelect2,
      categoriesSelect, categoriesSelect2, cuisineSelect, sourcesSelect,
      preferencesSelect, preferencesSelect2, difficultySelect, difficultySelect2,
      dietsSelect, dietsSelect2, ingredientsSelect, ingredientsExcludeSelect,
      chefsSelect
    ],
    menus: [menuCategoriesSelect, menuAuthorSelect, menuSectionsSelect, menuCardsSelect],
    articles: [articleCategoriesSelect, authorsSelect],
    shows: [showSelect, showchefSelect, videoLengthSelect]
  };

  Object.entries(controlsByType).forEach(([filterType, controls]) => {
    if (filterType === activeFilterType) {
      return;
    }

    controls.filter(Boolean).forEach((control) => {
      if (typeof control.clear === 'function') {
        control.clear(true);
      }
    });
  });

  if (activeFilterType === 'recipes') {
    syncAuthorTypeCheckboxes();
  }
}

document.querySelectorAll('.header-tabs .nav-link').forEach(tab => {
  tab.addEventListener('click', function () {
    const tabId = this.getAttribute('id');

    updateSearchTabInURL(tabId);
    clearInactiveTabFilterControls(tabId);
    updateSelections();
    syncFilterLayout(tabId);
    resetPaginationState();
    executeSearch(1, 1, 1, 1);
  });
});

// Initialize Tom Select for Diets
const dietsSelect = new TomSelect('#ex-checkbox-diets', {
  placeholder: 'Diets',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>';
    }
  },
  // This event triggers when a new diet is added (selected)
  onItemAdd: function (value, item) {
    syncSelects(this, dietsSelect2, value, 'add');
    updateDietsInURL(this);  // Update the URL when a diet is added
    updateSelections();
    executeSearch();         // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
    },
  // This event triggers when a diet is removed (deselected)
  onItemRemove: function (value) {
    syncSelects(this, dietsSelect2, value, 'remove');
    updateDietsInURL(this);  // Update the URL when a diet is removed
    updateSelections();
    executeSearch();         // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  // Pre-select diets from URL on initialization
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedDiets = urlVars.getAll('di').map(diet => decodeURIComponent(diet)); // Decode 'di' parameters

      // Iterate over each diet from the URL
      selectedDiets.forEach(diet => {
        let itemId = null;

        // Iterate through the options in this Tom Select instance
        Object.keys(this.options).forEach((id) => {
          let option = this.options[id];

          // Match the diet name with the option's 'text' value
          if (option.text && option.text.trim() === diet.trim()) {
            itemId = id; // Get the ID of the matching option
          }
        });

        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});


const dietsSelect2 = new TomSelect('#ex-checkbox-diets-side', {
  placeholder: 'Diets',
  hideSelected: false,
  plugins: {
    'checkbox_options': {
      'checkedClassNames': ['ts-checked'],
      'uncheckedClassNames': ['ts-unchecked'],
    }
  },
  controlClass: 'ts-control custom-select-control',
  render: {
    item: function (data, escape) {
      return '<span style="display:none;"></span>';
    }
  },
  // This event triggers when a new diet is added (selected)
  onItemAdd: function (value, item) {
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    syncSelects(this, dietsSelect, value, 'add');
    updateDietsInURL(this);  // Update the URL when a diet is added
    updateSelections();
    executeSearch();         // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();  },
  // This event triggers when a diet is removed (deselected)
  onItemRemove: function (value) {
    updateCounterSelectionText(this); // Pass the instance ID to the update function
    syncSelects(this, dietsSelect, value, 'remove');
    updateDietsInURL(this);  // Update the URL when a diet is removed
    updateSelections();
    executeSearch();         // Trigger the search after updating the URL
    this.control_input.blur(); // Blur the input after adding an item
    this.close();
  },
  
  // Pre-select diets from URL on initialization
  onInitialize: function () {
    // Delay the selection process slightly to ensure the instance is fully initialized
    setTimeout(() => {
      // Fetch the URL parameters
      const urlVars = new URLSearchParams(window.location.search);
      const selectedDiets = urlVars.getAll('di').map(diet => decodeURIComponent(diet)); // Decode 'di' parameters

      // Iterate over each diet from the URL
      selectedDiets.forEach(diet => {
        let itemId = null;

        // Iterate through the options in this Tom Select instance
        Object.keys(this.options).forEach((id) => {
          let option = this.options[id];

          // Match the diet name with the option's 'text' value
          if (option.text && option.text.trim() === diet.trim()) {
            itemId = id; // Get the ID of the matching option
          }
        });

        // Add the item to the select instance if a matching item is found
        if (itemId) {
          this.addItem(itemId); // Add the item by its ID to the Tom Select instance
        }
      });
    }, 200);  // Delay by 200 milliseconds to ensure Tom Select is ready
  }
});





function scrollToResultsStart() {
  const results = document.getElementById('results');

  if (!results) {
    return;
  }

  results.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function ensureTopPaginationContainer(suffix) {
  const tabPane = document.getElementById(suffix === 'shows' ? 'shows' : suffix);
  const list = tabPane ? tabPane.querySelector('.list') : null;

  if (!tabPane || !list) {
    return;
  }

  if (tabPane.querySelector('.pagination-' + suffix + '.pagination-top')) {
    return;
  }

  const topPagination = document.createElement('div');
  topPagination.className = 'pagination pagination-top pagination-' + suffix;
  list.parentElement.insertBefore(topPagination, list);
}

function getPaginationPages(totalPages, currentPage) {
  const pages = [];
  const firstPage = 1;
  const lastPage = totalPages;
  const startPage = Math.max(firstPage, currentPage - 5);
  const endPage = Math.min(lastPage, currentPage + 5);

  pages.push(firstPage);

  if (startPage > firstPage + 1) {
    pages.push('ellipsis-start');
  }

  for (let page = startPage; page <= endPage; page++) {
    if (page !== firstPage && page !== lastPage) {
      pages.push(page);
    }
  }

  if (endPage < lastPage - 1) {
    pages.push('ellipsis-end');
  }

  if (lastPage > firstPage) {
    pages.push(lastPage);
  }

  return pages;
}

function appendPaginationControl(pageList, options) {
  const pageItem = document.createElement('li');
  const pageButton = document.createElement('button');

  pageItem.className = 'page-item page-item--control';
  pageButton.type = 'button';
  pageButton.className = 'page-link page-link--control';
  pageButton.innerHTML = options.html;
  pageButton.setAttribute('aria-label', options.label);

  if (options.disabled) {
    pageButton.disabled = true;
    pageItem.classList.add('disabled');
  } else {
    pageButton.addEventListener('click', function () {
      pageButton.disabled = true;
      options.onClick();
      window.setTimeout(scrollToResultsStart, 150);
    });
  }

  pageItem.appendChild(pageButton);
  pageList.appendChild(pageItem);
}

function appendPaginationJumpControl(pageList, totalPages, currentPage, onPageChange) {
  const pageItem = document.createElement('li');
  const jumpForm = document.createElement('form');
  const pageLabel = document.createElement('label');
  const pageInput = document.createElement('input');
  const totalLabel = document.createElement('span');
  const submitButton = document.createElement('button');

  pageItem.className = 'page-item page-item--jump';
  jumpForm.className = 'pagination-jump-form';
  jumpForm.setAttribute('aria-label', 'Go to page by number');

  pageLabel.className = 'pagination-jump-form__label';
  pageLabel.textContent = 'Page';

  pageInput.type = 'number';
  pageInput.className = 'pagination-jump-form__input';
  pageInput.min = '1';
  pageInput.max = String(totalPages);
  pageInput.value = String(currentPage);
  pageInput.setAttribute('aria-label', 'Page number');
  pageInput.setAttribute('inputmode', 'numeric');

  totalLabel.className = 'pagination-jump-form__total';
  totalLabel.textContent = 'of ' + totalPages;

  submitButton.type = 'submit';
  submitButton.className = 'page-link pagination-jump-form__submit';
  submitButton.textContent = 'Go';

  jumpForm.addEventListener('submit', function (event) {
    event.preventDefault();

    const requestedPage = Math.min(totalPages, Math.max(1, parseInt(pageInput.value, 10) || currentPage));

    pageInput.value = String(requestedPage);

    if (requestedPage === currentPage) {
      return;
    }

    submitButton.disabled = true;
    onPageChange(requestedPage);
    window.setTimeout(scrollToResultsStart, 150);
  });

  jumpForm.appendChild(pageLabel);
  jumpForm.appendChild(pageInput);
  jumpForm.appendChild(totalLabel);
  jumpForm.appendChild(submitButton);
  pageItem.appendChild(jumpForm);
  pageList.appendChild(pageItem);
}

function initializePagination(totalItems, perPage, currentPage = 1, suffix, onPageChange) {
  ensureTopPaginationContainer(suffix);

  const paginationContainers = document.querySelectorAll('.pagination-' + suffix);
  const totalPages = Math.ceil(totalItems / perPage);

  paginationContainers.forEach((paginationContainer) => {
    paginationContainer.innerHTML = '';
    paginationContainer.classList.remove('load-more-pagination');
    paginationContainer.classList.add('numbered-pagination');

    if (!totalItems || totalPages <= 1) {
      return;
    }

    const pageList = document.createElement('ul');
    pageList.className = 'pagination-list';
    const goToPage = (page) => {
      onPageChange(page);
    };
    const isFirstPage = currentPage <= 1;
    const isLastPage = currentPage >= totalPages;

    appendPaginationControl(pageList, {
      html: '<i class="bi bi-chevron-double-left" aria-hidden="true"></i>',
      label: 'Go to first page',
      disabled: isFirstPage,
      onClick: () => goToPage(1)
    });

    appendPaginationControl(pageList, {
      html: '<i class="bi bi-chevron-left" aria-hidden="true"></i>',
      label: 'Go to previous page',
      disabled: isFirstPage,
      onClick: () => goToPage(currentPage - 1)
    });

    getPaginationPages(totalPages, currentPage).forEach((page) => {
      const pageItem = document.createElement('li');
      pageItem.className = 'page-item';

      if (typeof page !== 'number') {
        pageItem.classList.add('page-item--ellipsis');
        pageItem.innerHTML = '<span class="page-link">...</span>';
        pageList.appendChild(pageItem);
        return;
      }

      const pageButton = document.createElement('button');
      pageButton.type = 'button';
      pageButton.className = 'page-link';
      pageButton.textContent = page;
      pageButton.setAttribute('aria-label', 'Go to page ' + page);

      if (page === currentPage) {
        pageButton.classList.add('active');
        pageButton.setAttribute('aria-current', 'page');
      }

      pageButton.addEventListener('click', function () {
        if (page === currentPage) {
          return;
        }

        pageButton.disabled = true;
        onPageChange(page);
        window.setTimeout(scrollToResultsStart, 150);
      });

      pageItem.appendChild(pageButton);
      pageList.appendChild(pageItem);
    });

    appendPaginationControl(pageList, {
      html: '<i class="bi bi-chevron-right" aria-hidden="true"></i>',
      label: 'Go to next page',
      disabled: isLastPage,
      onClick: () => goToPage(currentPage + 1)
    });

    appendPaginationControl(pageList, {
      html: '<i class="bi bi-chevron-double-right" aria-hidden="true"></i>',
      label: 'Go to last page',
      disabled: isLastPage,
      onClick: () => goToPage(totalPages)
    });

    appendPaginationJumpControl(pageList, totalPages, currentPage, goToPage);

    paginationContainer.appendChild(pageList);
  });
}


    let isPagination = false;

function scheduleSearch(options = {}) {
  const delay = typeof options.delay === 'number' ? options.delay : searchDebounceDelay;

  window.clearTimeout(searchDebounceTimer);

  searchDebounceTimer = window.setTimeout(() => {
    executeSearch();
  }, delay);
}


    // Execute Search Function
const executeSearch = (
  pageRecipes = currentPageRecipes,
  pageMenus = currentPageMenus,
  pageArticles = currentPageArticles,
  pageShows = currentPageShows
) => {
  if (!isPagination) {
    pageRecipes = 1;
    pageMenus = 1;
    pageArticles = 1;
    pageShows = 1;
    currentPage = 1;
    currentPageRecipes = 1;
    currentPageMenus = 1;
    currentPageArticles = 1;
    currentPageShows = 1;
  }

  const query = searchInput ? searchInput.value.trim() : '';
  const urlParams = new URLSearchParams(window.location.search);
  updateSearchSummary(query);

  const selectedHolidays = urlParams.getAll('hi');
  
  const selectedAllergens = urlParams.getAll('ae');
  const selectedCategories = urlParams.getAll('ic');
  const selectedCuisines = urlParams.getAll('cui');
  const selectedSources = urlParams.getAll('is');
  const selectedPreferences = urlParams.getAll('pf');
const selectedDifficulty = urlParams.getAll('dif');
  const selectedDiets = urlParams.getAll('di');
  const selectedGe = urlParams.getAll('ge');
  const selectedGi = urlParams.getAll('gi');
  const selectedAct = urlParams.getAll('act');
  const selectedChefs = urlParams.getAll('chi');
  //MENUS
  const selectedMenuCategories = urlParams.getAll('imc');
  const selectedMenuAuthors = urlParams.getAll('mau');
  const selectedMenuSections = urlParams.getAll('msc');
  const selectedMenuCards = urlParams.getAll('mcc');
  //ARTICLES
  const selectedArticleCategories = urlParams.getAll('iac');
  const selectedArticleAuthors = urlParams.getAll('au');
  //SHOWS
  const selectedShowNames = urlParams.getAll('sn');
  const selectedShowChefs = urlParams.getAll('vchi');
  const selectedVideoLengths = urlParams.getAll('vl');


  const selectedAuthorTypes = getSelectedAuthorTypes(urlParams);
  const includeKosherAuthors = selectedAuthorTypes.includes('kosher');
  const includeHomeAuthors = selectedAuthorTypes.includes('home');

  const  filters = [];
  const  menufilters = [];
  const  articlefilters = [];
  const  showfilters = [];
  const valid = arr => arr.filter(v => v.trim() !== '');

  if (valid(selectedChefs).length)
    filters.push(valid(selectedChefs).map(v => `chefs:=\`${v}\``).join(' || '));

if (valid(selectedHolidays).length) {

  const values = valid(selectedHolidays);

  filters.push(
    values.includes('Passover')
      ? 'occasions:=`Passover`'
      : `(${values.map(v => `occasions:=\`${v}\``).join(' || ')})`
  );
}

  if (valid(selectedCategories).length)
    filters.push(valid(selectedCategories).map(v => `recipe_category:=\`${v}\``).join(' || '));

  if (valid(selectedCuisines).length)
    filters.push(valid(selectedCuisines).map(v => `cuisine:=\`${v}\``).join(' || '));

if (valid(selectedSources).length)
  filters.push(
    `(${valid(selectedSources).map(v => `sources:=\`${v}\``).join(' || ')})`
  );

  if (valid(selectedPreferences).length)
    filters.push(valid(selectedPreferences).map(v => `preference:=\`${v}\``).join(' || '));

  if (valid(selectedDifficulty).length)
    filters.push(valid(selectedDifficulty).map(v => `difficulty:=\`${v}\``).join(' || '));

  if (valid(selectedAllergens).length)
    filters.push(valid(selectedAllergens).map(v => `contains_allergents:!=\`${v}\``).join(' && '));

  if (valid(selectedDiets).length)
    filters.push(valid(selectedDiets).map(v => `diets:=\`${v}\``).join(' && '));

  if (valid(selectedGe).length)
    filters.push(valid(selectedGe).map(v => `ingredients:!=\`${v}\``).join(' && '));

  if (valid(selectedGi).length)
    filters.push(valid(selectedGi).map(v => `ingredients:=\`${v}\``).join(' && '));

  // Kosher.com means regular recipes; Home Cooks are marked by community-recipe.
  if (includeHomeAuthors)
    filters.push('community-recipe:=true');
  else if (includeKosherAuthors)
    filters.push('community-recipe:=false');

  //ARTICLES

  if (valid(selectedArticleCategories).length)
    articlefilters.push(valid(selectedArticleCategories).map(v => `article_sub_category:=\`${v}\``).join(' || '));

  if (valid(selectedArticleAuthors).length)
    articlefilters.push(valid(selectedArticleAuthors).map(v => `author:=\`${v}\``).join(' || '));

  //MENUS
  if (valid(selectedMenuCategories).length)
    menufilters.push(valid(selectedMenuCategories).map(v => `categories:=\`${v}\``).join(' || '));

  if (valid(selectedMenuAuthors).length)
    menufilters.push(valid(selectedMenuAuthors).map(v => `author_name:=\`${v}\``).join(' || '));

  function buildMenuCountFilter(values, field) {
    const countFilters = [];

    values.forEach(val => {
      if (val === '1') {
        countFilters.push(`${field}:=1`);
      }

      if (val === '2') {
        countFilters.push(`${field}:=2`);
      }

      if (val === '3') {
        countFilters.push(`${field}:=3`);
      }

      if (val === '4') {
        countFilters.push(`${field}:>=4`);
      }

      if (val === '5') {
        countFilters.push(`${field}:<=5`);
      }

      if (val === '10') {
        countFilters.push(`${field}:>=6 && ${field}:<=10`);
      }

      if (val === '20') {
        countFilters.push(`${field}:>=11 && ${field}:<=20`);
      }

      if (val === '21') {
        countFilters.push(`${field}:>=21`);
      }
    });

    return countFilters.length ? `(${countFilters.join(' || ')})` : '';
  }

  const menuSectionsFilter = buildMenuCountFilter(selectedMenuSections, 'sections_count');
  const menuCardsFilter = buildMenuCountFilter(selectedMenuCards, 'cards_count');

  if (menuSectionsFilter) {
    menufilters.push(menuSectionsFilter);
  }

  if (menuCardsFilter) {
    menufilters.push(menuCardsFilter);
  }

  menufilters.push('privacy:=`public`');


  //SHOWS
  if (valid(selectedShowNames).length)
    showfilters.push(valid(selectedShowNames).map(v => `show:=\`${v}\``).join(' || '));


function buildVideoLengthFilter(values) {
  const videoLengthFilters = [];

  values.forEach(val => {

      if (val === "5") {
          videoLengthFilters.push('video_duration: < "00:05:00"');
      }

      if (val === "10") {
          videoLengthFilters.push('video_duration: >= "00:05:00" && video_duration: <= "00:10:00"');
      }

      if (val === "20") {
          videoLengthFilters.push('video_duration: >= "00:10:00" && video_duration: <= "00:20:00"');
      }

      if (val === "21") {
          videoLengthFilters.push('video_duration: >= "00:21:00"');
      }
  });

  return videoLengthFilters.length ? `(${videoLengthFilters.join(' || ')})` : '';
}

const videoLengthFilter = buildVideoLengthFilter(selectedVideoLengths);

if (videoLengthFilter.trim() !== "") {
    showfilters.push(videoLengthFilter);
}

  if (valid(selectedShowChefs).length)
    showfilters.push(valid(selectedShowChefs).map(v => `chef:=\`${v}\``).join(' || '));


  const combinedFilters = filters.length ? filters.join(' && ') : undefined;
  const combinedMenuFilters = menufilters.length ? menufilters.join(' && ') : undefined;
  const combinedArticlesFilters = articlefilters.length ? articlefilters.join(' && ') : undefined;
  const combinedShowFilters = showfilters.length ? showfilters.join(' && ') : undefined;


  const filtersWithoutSource = filters
  .filter(f => !f.includes('sources:'))
  .filter(f => !f.includes('community-recipe:'))
  .filter(Boolean);

const baseFilter = filtersWithoutSource.length
  ? filtersWithoutSource.join(' && ')
  : '';

  let sortBy = '_text_match:desc,date:desc'; // default relevance

  if (customSorting) {
    sortBy = customSorting;
  }

  const menuSortBy = sortBy === 'rating:desc' ? 'date:desc' : sortBy;

  const resultFields = [
    'postID',
    'url',
    'title',
    'main_dish',
    'search_priority',
    'chef',
    'author',
    'article_sub_category',
    'show',
    'episode_number',
    'occasions',
    'contains_allergents',
    'comments_total',
    'video_duration',
    'author_article_url',
    'show_url',
    'rating',
    'likes',
    'cook_time',
    'serving',
    'difficulty',
    'chefs',
    'source_image',
    'has_video',
    'image',
    'type',
    'date',
    'chef_url',
    'user_url',
    'chefID',
    'episode_chef_url'
  ].join(',');

  const menuResultFields = [
    'postID',
    'url',
    'permalink',
    'title',
    'description',
    'author_name',
    'categories',
    'privacy',
    'sections_count',
    'cards_count',
    'image',
    'type',
    'date',
    'likes'
  ].join(',');

	  const relevanceOptions = {
	    prioritize_exact_match: true,
	    prioritize_token_position: true,
    text_match_type: 'max_weight',
    // Sidebar filters must narrow the active query. Never broaden a filtered
    // search by dropping the user's original search terms.
    drop_tokens_threshold: 0,
    // The search preset can cap accessible hits below `found`, which creates
    // pagination links whose later pages contain no documents.
    limit_hits: 10000,
    typo_tokens_threshold: 1,
    use_synonyms: true,
    enable_overrides: true,
    include_fields: resultFields
  };

  const searchPayload = {
    searches: [
	     {
      debug_label: 'recipes visible results',
	      collection: collectionName('recipes'),
	      q: query,
	      query_by: 'title,chefs,tags,ingredients',
	      query_by_weights: '12,5,3,2',
	      num_typos: '0,0,1,1',
	      filter_by: combinedFilters,
	      page: pageRecipes,
	      sort_by: sortBy,
      per_page: 45,
      ...relevanceOptions
	      },
	      {
      debug_label: 'menus visible results',
	      collection: collectionName('menus'),
	      q: query,
	      query_by: 'title,description,categories,section_titles,recipe_titles,card_text,author_name',
	      filter_by: combinedMenuFilters,
	      page: pageMenus,
	      sort_by: menuSortBy,
      per_page: 45,
      ...relevanceOptions,
      include_fields: menuResultFields
	      },
	      {
      debug_label: 'articles visible results',
	      collection: collectionName('articles'),
	      q: query,
	      query_by: 'title,author,article_sub_category',
	      query_by_weights: '12,5,3',
	      num_typos: '0,1,1',
	      filter_by: combinedArticlesFilters,
	      page: pageArticles,
	      sort_by: sortBy,
      per_page: 45,
      ...relevanceOptions
	      },
	      {
      debug_label: 'shows visible results',
	      collection: collectionName('episodes'),
	      q: query,
	      query_by: 'title,chef,show',
	      query_by_weights: '12,5,4',
	      num_typos: '0,1,1',
	      filter_by: combinedShowFilters,
	      page: pageShows,
	      sort_by: sortBy,
      per_page: 45,
      ...relevanceOptions
      },
	{
    debug_label: 'home cooks count',
	  collection: collectionName('recipes'),
	  q: query,
	  query_by: 'title,chefs,tags,ingredients',
  query_by_weights: '12,5,3,2',
  filter_by: [
    baseFilter,
    'community-recipe:=true'
  ].filter(v => v && v.trim() !== '').join(' && '),
	  per_page: 45,
	  prioritize_exact_match: true,
  prioritize_token_position: true,
  text_match_type: 'max_weight',
  num_typos: '0,0,1,1',
  drop_tokens_threshold: 0,
  typo_tokens_threshold: 0,
   sort_by: sortBy,
  use_synonyms: true,
  enable_overrides: true,
  include_fields: resultFields
},
	{
    debug_label: 'kosher.com count',
	  collection: collectionName('recipes'),
	  q: query,
	  query_by: 'title,chefs,tags,ingredients',
  query_by_weights: '12,5,3,2',
  filter_by: [
    baseFilter,
    'community-recipe:=false'
  ].filter(v => v && v.trim() !== '').join(' && '),
	  per_page: 1,
	  prioritize_exact_match: true,
  prioritize_token_position: true,
  text_match_type: 'max_weight',
  num_typos: '0,0,1,1',
  drop_tokens_threshold: 0,
  typo_tokens_threshold: 0,
   sort_by: sortBy,
  use_synonyms: true,
  enable_overrides: true,
  include_fields: resultFields
}
    ],
  };

  if (activeSearchController) {
    activeSearchController.abort();
  }

  activeSearchController = new AbortController();
  const requestId = ++activeSearchRequestId;
  setSearchLoading(true);

  const searchDebugContext = {
    requestId,
    query,
    url: window.location.href,
    author_type_url_values: urlParams.getAll('author_type'),
    selected_author_types: selectedAuthorTypes,
    include_kosher_authors: includeKosherAuthors,
    include_home_authors: includeHomeAuthors,
    recipe_filter_by: combinedFilters || '',
    home_cooks_count_filter_by: searchPayload.searches[4] ? searchPayload.searches[4].filter_by : '',
    kosher_count_filter_by: searchPayload.searches[5] ? searchPayload.searches[5].filter_by : '',
    base_filter_without_author: baseFilter,
    collection_prefix: collectionPrefix,
    recipes_collection: collectionName('recipes'),
  };

  typesenseSearch(searchPayload, activeSearchController.signal, searchDebugContext)
    .then((data) => {
      if (requestId !== activeSearchRequestId) {
        return;
      }

      if (!Array.isArray(data.results)) {
        return;
      }

      const results = [];
      let totalRecipes = 0, totalMenus = 0, totalArticles = 0, totalShows = 0;

      let homeCooksCount = 0;
      let kosherCount = 0;
        const homeCookResult = data.results[4]; // home cooks search follows recipes, menus, articles, and shows
        const kosherCountResult = data.results[5]; // kosher.com count search follows home cooks

        if (homeCookResult) {
        homeCooksCount = homeCookResult.found || 0;
        }

        if (kosherCountResult) {
        kosherCount = kosherCountResult.found || 0;
        }

      const recipeResult = data.results[0];
      if (recipeResult && Array.isArray(recipeResult.hits)) {
        totalRecipes = recipeResult.found || 0;
        recipeResult.hits.forEach(hit => results.push(formatHit(hit)));
      } else if (recipeResult && recipeResult.error) {
        console.error('[Kosher Typesense] recipes collection search failed:', recipeResult.error);
      }

      if (!kosherCountResult && includeKosherAuthors && !includeHomeAuthors) {
        kosherCount = totalRecipes;
        console.warn('[Kosher Typesense] kosher.com count response missing; using visible recipe total as fallback', {
          totalRecipes,
          selected_author_types: selectedAuthorTypes,
        });
      }

      if (!homeCookResult && includeHomeAuthors && !includeKosherAuthors) {
        homeCooksCount = totalRecipes;
        console.warn('[Kosher Typesense] home cooks count response missing; using visible recipe total as fallback', {
          totalRecipes,
          selected_author_types: selectedAuthorTypes,
        });
      }

      const menuResult = data.results[1];
      if (menuResult && Array.isArray(menuResult.hits)) {
        totalMenus = menuResult.found || 0;
        menuResult.hits.forEach(hit => results.push(formatHit(hit)));
      } else if (menuResult && menuResult.error) {
        console.error('[Kosher Typesense] menus collection search failed:', menuResult.error);
      }

      const articleResult = data.results[2];
      if (articleResult && Array.isArray(articleResult.hits)) {
        totalArticles = articleResult.found || 0;
        articleResult.hits.forEach(hit => results.push(formatHit(hit)));
      } else if (articleResult && articleResult.error) {
        console.error('[Kosher Typesense] articles collection search failed:', articleResult.error);
      }

      const showResult = data.results[3];
      if (showResult && Array.isArray(showResult.hits)) {
        totalShows = showResult.found || 0;
        showResult.hits.forEach(hit => results.push(formatHit(hit)));
      } else if (showResult && showResult.error) {
        console.error('[Kosher Typesense] episodes collection search failed:', showResult.error);
      }

      let recipeTabTotal = totalRecipes;

      if (includeKosherAuthors && !includeHomeAuthors) {
        recipeTabTotal = kosherCount;
      } else if (includeHomeAuthors && !includeKosherAuthors) {
        recipeTabTotal = homeCooksCount;
      }

      const totalAll = recipeTabTotal + totalMenus + totalArticles + totalShows;
      document.getElementById('tab-all').innerHTML = `All <span id="all-count">(${totalAll})</span>`;
      document.getElementById('tab-recipes').innerHTML = `Recipes <span id="recipes-count">(${recipeTabTotal})</span>`;
      document.getElementById('tab-menus').innerHTML = `Menus <span id="menus-count">(${totalMenus})</span>`;
      document.getElementById('tab-articles').innerHTML = `Articles <span id="articles-count">(${totalArticles})</span>`;
      document.getElementById('tab-shows').innerHTML = `Shows <span id="shows-count">(${totalShows})</span>`;


      document.querySelectorAll('[data-homecooks-count]').forEach((homeCookSpan) => {
        homeCookSpan.textContent = `(${homeCooksCount})`;
      });

      updateAuthorTypeCounts(kosherCount, homeCooksCount);
        
      renderResults(results, {
        recipes: recipeTabTotal,
        menus: totalMenus,
        articles: totalArticles,
        shows: totalShows
      });

      initializePagination(recipeTabTotal, 45, pageRecipes, 'recipes', (newPage) => {
        currentPageRecipes = newPage;
        isPagination = true;
        executeSearch(newPage, pageMenus, pageArticles, pageShows);
      });
      initializePagination(totalMenus, 45, pageMenus, 'menus', (newPage) => {
        currentPageMenus = newPage;
        isPagination = true;
        executeSearch(pageRecipes, newPage, pageArticles, pageShows);
      });
      initializePagination(totalArticles, 45, pageArticles, 'articles', (newPage) => {
        currentPageArticles = newPage;
        isPagination = true;
        executeSearch(pageRecipes, pageMenus, newPage, pageShows);
      });
      initializePagination(totalShows, 45, pageShows, 'shows', (newPage) => {
        currentPageShows = newPage;
        isPagination = true;
        executeSearch(pageRecipes, pageMenus, pageArticles, newPage);
      });

      isPagination = false;
    })
    .catch(err => {
      if (err.name === 'AbortError') {
        return;
      }

      console.error('Error fetching search results:', err);
    })
    .finally(() => {
      if (requestId === activeSearchRequestId) {
        setSearchLoading(false);
      }
    });
};

  // The search page and global header can both expose #searchInput. The NLS
  // bridge deliberately calls this scoped search instance with the submitted
  // value so another input cannot cause the old query to be rendered again.
  searchInput.addEventListener('kosher:natural-language-search', (event) => {
    const submittedQuery = event.detail && typeof event.detail.query === 'string'
      ? event.detail.query.trim()
      : searchInput.value.trim();

    searchInput.value = submittedQuery;
    resetPaginationState();
    window.clearTimeout(searchDebounceTimer);
    executeSearch(1, 1, 1, 1);
    toggleCloseIcon();
  });


// 🔧 Helper to format each hit into your result structure
function formatHit(hit, totalRecipes, totalMenus, totalArticles, totalShows) {
  const occasions = hit.document.occasions || [];
  let commaSeparatedAllergens = '';
  if ('contains_allergents' in hit.document) {
    const allergens = hit.document.contains_allergents;
    commaSeparatedAllergens = allergens.join(', ');
    if (commaSeparatedAllergens != '') {
      commaSeparatedAllergens = `Contains: ${commaSeparatedAllergens}`;
    }
  }
  const commaSeparatedHolidays = occasions.join(', ');
  const formatDuration = (duration) => {
    if (!duration) return '';
    if (duration.startsWith('00:')) {
      return duration.slice(3);
    }
    return duration;
  };

  return {
    totalCount: totalRecipes + totalMenus + totalArticles + totalShows || 0,
    totalRecipe: totalRecipes || 0,
    totalMenus: totalMenus || 0,
    totalArticles: totalArticles || 0,
    totalShows: totalShows || 0,
    postID: hit.document.postID || '',
    permalink: hit.document.url || '',
    title: hit.document.title || '',
    user: hit.document.chef || '',
    author: hit.document.author || '',
    author_name: hit.document.author_name || '',
    categories: hit.document.categories || [],
    sections_count: hit.document.sections_count || 0,
    cards_count: hit.document.cards_count || 0,
    privacy: hit.document.privacy || '',
    article_category: hit.document.article_sub_category || '',
    show: hit.document.show || '',
    episode: hit.document.episode_number ? `Ep. ${hit.document.episode_number}` : '',
    occasions: commaSeparatedHolidays || '',
    allergents: commaSeparatedAllergens || '',
    url: hit.document.url || '#',
    chef_url: hit.document.chef_url || '',
    user_url: hit.document.user_url || '',
    comments: hit.document.comments_total || '0',
    duration: formatDuration(hit.document.video_duration) || '',
    author_article_url: hit.document.author_article_url || '',
    show_url: hit.document.show_url || '',
    rating: hit.document.rating || '0',
    likes: hit.document.likes || '0',
    cook_time: formatCookTime(hit.document.cook_time) || 'N/A',
    serving: hit.document.serving || 'Serving info not available',
    difficulty: hit.document.difficulty || '',
    chef: normalizeList(hit.document.chefs || hit.document.chef || 'Unknown Chef'),
    sourceImg: hit.document.source_image ? `<img src="${hit.document.source_image}" alt="Source Image"/>` : '',
    sourceImgUrl: hit.document.source_image,
    hasVideo: hit.document.has_video || false,
    img: hit.document.image || '',
    type: hit.document.type || '',
    date: hit.document.date || '',
    episode_chef_url: hit.document.episode_chef_url || '',
  };
}


// Function to create a filter item with a remove button and data attributes
function createFilterItem(param, value, type) {
  const listItem = document.createElement('li');
  listItem.classList.add('filter-item');

  const removeButton = document.createElement('button');
  removeButton.classList.add('remove-filter');

  // Create the span with data-var and wrap the value
  const filterSpan = document.createElement('span');
  filterSpan.setAttribute('data-var', `${param}=${value}`);
  filterSpan.innerHTML = `${param === 'author_type' ? getAuthorTypeLabel(value) : value} <i class="fa-solid fa-xmark"></i>`;

  removeButton.appendChild(filterSpan);


  // Determine the correct Tom Select instance(s) based on the type or param
  let selectInstances = [];  // We'll use an array to handle multiple instances
  switch (param) {
    case 'ae': // Allergens
      selectInstances.push(allergensSelect, allergensSelect2);
      break;
    case 'hi': // Holidays
      selectInstances.push(holidaysSelect, holidaysSelect2);  // Add both instances for holidays
      break;
    case 'gi': // Ingredients
      selectInstances.push(ingredientsSelect);
      break;
    case 'ge': // Excluded Ingredients
      selectInstances.push(ingredientsExcludeSelect);
      break;
    case 'di': // Diets
      selectInstances.push(dietsSelect, dietsSelect2);
      break;
    case 'is': // Sources
      selectInstances.push(sourcesSelect);
      break;
    case 'pf': // Sources
      selectInstances.push(preferencesSelect, preferencesSelect2);
      break;
    case 'dif': // Sources
      selectInstances.push(difficultySelect, difficultySelect2);
      break;
    case 'ic': // Categories
      selectInstances.push(categoriesSelect, categoriesSelect2);
      break;
    case 'imc': // Menu categories
      selectInstances.push(menuCategoriesSelect);
      break;
    case 'msc': // Menu sections
      selectInstances.push(menuSectionsSelect);
      break;
    case 'mcc': // Menu items
      selectInstances.push(menuCardsSelect);
      break;
    case 'mau': // Menu creators
      selectInstances.push(menuAuthorSelect);
      break;
      case 'cui': // Cuisines
      selectInstances.push(cuisineSelect);
      break;
    case 'chi': // Chefs
      selectInstances.push(chefsSelect);
      break;

    case 'iac': // Chefs
        selectInstances.push(articleCategoriesSelect);
    break;
    case 'au': // Chefs
        selectInstances.push(authorsSelect);
    break;
    case 'sn': // Chefs
       selectInstances.push(showSelect);
    break;
    case 'vchi': // Chefs
        selectInstances.push(showchefSelect);
    break;
    case 'vl': // Chefs
        selectInstances.push(videoLengthSelect);
    break;


    // Add cases for other selects as needed
    default:
      selectInstances = []; // If no matching select found, set an empty array
  }

  // Attach the click event to remove the filter
  removeButton.addEventListener('click', () => {
    if (param === 'author_type') {
      removeAuthorTypeFilter(value);
      return;
    }

    // Loop through all select instances and remove the item from each
    selectInstances.forEach(selectInstance => {
      if (selectInstance) {
        removeFilter(param, value, selectInstance); // Pass the correct Tom Select instance
      } else {
        console.warn(`No matching Tom Select instance for param: ${param}`);
      }
    });
  });

  listItem.appendChild(removeButton);
  return listItem;
}



  function updateSelections() {
    const includesList = document.querySelector('.includes-list');
    const excludesList = document.querySelector('.excludes-list');
    const shouldRenderSelectionLists = !!(includesList && excludesList);

    if (shouldRenderSelectionLists) {
      includesList.innerHTML = ''; // Clear previous includes
      excludesList.innerHTML = ''; // Clear previous excludes
    }
  
    const urlParams = new URLSearchParams(window.location.search);
    let includesAdded = false;
    let excludesAdded = false;
    let hasFilters = false; // Track if any filters are applied
  
    // For Includes: parameters starting with 'hi', 'gi', 'di', 'ic'
    urlParams.forEach((value, key) => {
      let listItem;
  
      // Handle includes based on the key
      switch (key) {
        case 'hi': // Holidays
        case 'gi': // Ingredients
        case 'chi': // Chefs
        case 'di': // Diets
        case 'is': // Sources
        case 'author_type': // Authors
        case 'pf': // Preferences
        case 'dif': // Difficulty
        case 'ic': // Categories
        case 'imc': // Menu categories
        case 'msc': // Menu sections
        case 'mcc': // Menu items
        case 'mau': // Menu creators

        case 'iac': // Categories
        case 'au': // Categories
        case 'sn': // Categories
        case 'vchi': // Categories
        case 'vl': // Categories


        if (shouldRenderSelectionLists && !includesAdded) {
            const includesTitle = document.createElement('li');
            includesTitle.textContent = 'Includes:';
            includesList.appendChild(includesTitle);
            includesAdded = true;
          }
          if (shouldRenderSelectionLists) {
            listItem = createFilterItem(key, value, 'include');
            includesList.appendChild(listItem);
          }
          hasFilters = true;
          break;
  
        // For Excludes: parameters ending with 'e' (e.g., 'ae', 'ge')
        case 'ae': // Allergens
        case 'cui': // Cuisine
        case 'ge': // Excluded Ingredients
          if (shouldRenderSelectionLists && !excludesAdded) {
            const excludesTitle = document.createElement('li');
            excludesTitle.textContent = 'Excludes:';
            excludesList.appendChild(excludesTitle);
            excludesAdded = true;
          }
          if (shouldRenderSelectionLists) {
            listItem = createFilterItem(key, value, 'exclude');
            excludesList.appendChild(listItem);
          }
          hasFilters = true;
          break;
      }
    });
  
    // Show/Hide Clear All button based on whether there are filters
    const clearAllButton = document.querySelector('.clear-all');
    if (clearAllButton) {
      clearAllButton.hidden = !hasFilters;
    }
  }
  

  function removeFilter(param, value, selectInstance) {
    const url = new URL(window.location.href);
    const params = url.searchParams;
  
    // Rebuild the parameters, excluding the one we want to remove
    const newParams = new URLSearchParams();
    params.forEach((val, key) => {
      if (!(key === param && val === value)) {
        newParams.append(key, val); // Keep all params except the one to remove
      }
    });
  
    // Update the URL without reloading the page
    history.pushState({}, '', `${url.pathname}?${newParams.toString()}`);
  
    // Process the value to match internal formatting
    const processedValue = value.trim();
  
    // Get the ID of the matching option in Tom Select
    const itemId = findItemIdByName(selectInstance, processedValue);
  
    // Deselect the item without removing it from the dropdown
    if (itemId && selectInstance) {
      selectInstance.removeItem(itemId, true); // ✅ Important: use `true` to trigger events and UI update
    } else {
      console.warn(`No matching item found for: ${processedValue}`);
    }
  
    // Optional: remove item from DOM if needed
    removeIngredientButton(itemId);
  
    updateSelections();
    executeSearch();
  }
  
    

  function findItemIdByName(selectInstance, name) {
    let itemId = null;
  
    // Iterate through all available options in Tom Select
    Object.keys(selectInstance.options).forEach(id => {
      const option = selectInstance.options[id];
  
      // Match either by visible label (text) or value
      if (
        option.text && option.text.trim() === name.trim() ||
        option.value && option.value.trim() === name.trim()
      ) {
        itemId = id;
      }
    });
  
    return itemId;
  }
  

  // Function to remove the ingredient button from the container
  function removeIngredientButton(value) {
    const container = document.getElementById('selected-ingredients-container');
    if (!container) {
      return;
    }

    const button = container.querySelector(`button[data-value="${value}"]`);
    if (button) {
      container.removeChild(button);  // Remove the button from the container
    }
  }

  // Clear all filters when the "Clear" button is clicked
  if (clearAllButton) {
  clearAllButton.addEventListener('click', () => {
    const url = new URL(window.location.href);
    const query = url.searchParams.get('q') || (searchInput ? searchInput.value.trim() : '');

    url.search = '';

    if (query) {
      url.searchParams.set('q', query);
      if (searchInput) {
        searchInput.value = query;
      }
    }

    [
      ingredientsSelect,
      ingredientsExcludeSelect,
      holidaysSelect,
      holidaysSelect2,
      sourcesSelect,
      preferencesSelect,
      preferencesSelect2,
      difficultySelect,
      difficultySelect2,
      cuisineSelect,
      categoriesSelect,
      categoriesSelect2,
      dietsSelect,
      dietsSelect2,
      allergensSelect,
      allergensSelect2,
      chefsSelect,
      articleCategoriesSelect,
      authorsSelect,
      showSelect,
      showchefSelect,
      videoLengthSelect,
    ].forEach((selectInstance) => {
      if (selectInstance && typeof selectInstance.clear === 'function') {
        selectInstance.clear(true);
      }
    });

    customSorting = '';

    if (sortDropdown) {
      sortDropdown.value = 'default';
    }

    [
      'selected-ingredients-container',
      'selected-exclude-ingredients',
      'selected-ingredients-exclude-container',
      'selected-chef',
      'selected-chef-container',
      'selected-author',
      'selected-author-container',
      'selected-show-chef',
      'selected-show-chef-container',
    ].forEach((elementId) => {
      const element = document.getElementById(elementId);
      if (element) {
        element.innerHTML = '';
      }
    });

    document.querySelectorAll('.counter-selection').forEach((counter) => {
      counter.style.display = 'none';
      counter.textContent = '';
    });

    syncAuthorTypeCheckboxes(['kosher']);
    syncCommunitySwitches(false);
    resetPaginationState();
    history.pushState({}, '', url);
    updateSelections();
    executeSearch();
  });
  }
  



// Function to update the community parameter in the URL
function updateCommunityInURL(isChecked) {
  const selectedTypes = isChecked ? ['home'] : ['kosher'];

  updateAuthorTypesInURL(selectedTypes);
  syncAuthorTypeCheckboxes(selectedTypes);
  updateSelections();
  executeSearch();
}

// Function to sync all community switches
function syncCommunitySwitches(isChecked) {
  const communitySwitches = document.querySelectorAll('.community-switch');
  communitySwitches.forEach((communitySwitch) => {
    communitySwitch.checked = isChecked;
  });
}

// Select all elements with the class 'community-switch'
const communitySwitches = document.querySelectorAll('.community-switch');

// Check if there are any switches and add logic for each one
if (communitySwitches.length > 0) {
  // Get the initial state from the URL
  const urlParams = new URLSearchParams(window.location.search);
  const selectedAuthorTypes = getSelectedAuthorTypes(urlParams);
  const isChecked = selectedAuthorTypes.includes('home');

  // Set the initial state for all switches
  syncCommunitySwitches(isChecked);
  syncAuthorTypeCheckboxes(selectedAuthorTypes);

  // Add event listeners for each switch
  communitySwitches.forEach((communitySwitch) => {
    communitySwitch.addEventListener('change', function () {
      const isChecked = communitySwitch.checked;
      
      // Sync all switches when one changes
      syncCommunitySwitches(isChecked);
      
      // Update the URL and trigger the search
      updateCommunityInURL(isChecked);
    });
  });
}

if (authorTypeCheckboxes.length > 0) {
  syncAuthorTypeCheckboxes();

  authorTypeCheckboxes.forEach((checkbox) => {
    checkbox.addEventListener('change', function () {
      const selectedTypes = [this.checked ? this.value : 'kosher'];

      console.log('[Kosher Typesense] author filter changed', {
        clicked: this.value,
        checked: this.checked,
        selected_author_types: selectedTypes,
        will_filter_recipes_by: selectedTypes[0] === 'home' ? 'community-recipe:=true' : 'community-recipe:=false',
      });

      updateAuthorTypesInURL(selectedTypes);
      syncAuthorTypeCheckboxes(selectedTypes);
      syncCommunitySwitches(selectedTypes.includes('home'));
      updateSelections();
      executeSearch();
    });
  });
}


    // On page load, check if 'q' is present in the URL and execute the search
    const urlParams = new URLSearchParams(window.location.search);
    const query = urlParams.get('q'); // Get the 'q' parameter from the URL
    let didRunInitialQuerySearch = false;

    if (query && searchInput) {
      // Decode the query to handle cases like %20 (space)
      const decodedQuery = decodeURIComponent(query);
      searchInput.value = decodedQuery; // Update the search input with the 'q' value
      didRunInitialQuerySearch = true;
      executeSearch(undefined, undefined, undefined, false);                  // Trigger the search with the initial 'q' value
    }
    // Tab switching logic for switching between 'All', 'Recipes', 'Articles', and 'Shows'
    tabs.forEach((tab) => {
      tab.addEventListener('click', (event) => {
        event.preventDefault();
        const targetId = tab.getAttribute('href');
        if (targetId && targetId.startsWith('#')) {
          document.querySelector('.tab-pane.active').classList.remove('show', 'active');
          document.querySelector(targetId).classList.add('show', 'active');
          document.querySelector('.nav-link.active').classList.remove('active');
          tab.classList.add('active');
        }
      });
    });

    function updateQueryInURL(query) {
      const url = new URL(window.location.href);

      if (query.length > 0) {
        url.searchParams.set('q', query); // Set the query parameter in the URL
      } else {
        url.searchParams.delete('q'); // Remove the query parameter if empty
      }

      history.replaceState({}, '', url);
    }

    // Search when clicking on pre-defined filters
    const searchFilterButtons = document.querySelectorAll('.search-filters-combo__items__item__btn');

    searchFilterButtons.forEach((button) => {
      button.addEventListener('click', () => {
        document.querySelector('#tab-recipes').removeAttribute('data-page');
        document.querySelector('#tab-menus').removeAttribute('data-page');
        document.querySelector('#tab-articles').removeAttribute('data-page');
        document.querySelector('#tab-shows').removeAttribute('data-page');
        const comboText = button.innerText.trim();
        searchInput.value = comboText;
        updateQueryInURL(comboText);  // Update the query parameter in the URL
        executeSearch();
        toggleCloseIcon();
        tabs[0].click();
      });
    });

    const toggleCloseIcon = () => {
      if (closeIcon) {
        closeIcon.style.display = searchInput.value.trim() !== '' ? 'inline' : 'none';
      }
    };


    if (closeIcon) {
    closeIcon.addEventListener('click', () => {
      const url = new URL(window.location.href);
      const params = Array.from(url.searchParams.keys());
      
      params.forEach(param => url.searchParams.delete(param));
      
      // Update the browser's URL without reloading the page
      window.history.replaceState({}, '', url);

  
    // Clear all the Tom Select instances
    ingredientsSelect.clear();
    ingredientsExcludeSelect.clear();
    holidaysSelect.clear();
    holidaysSelect2.clear(); // If you have a second instance for holidays
    sourcesSelect.clear();
    preferencesSelect.clear();
    preferencesSelect2.clear();
    difficultySelect.clear();
    difficultySelect2.clear();
    cuisineSelect.clear();
    categoriesSelect.clear();
    categoriesSelect2.clear();
    dietsSelect.clear();
    dietsSelect2.clear();
    allergensSelect.clear();
    allergensSelect2.clear();
    chefsSelect.clear();
    articleCategoriesSelect.clear();
    menuCategoriesSelect.clear();
    menuAuthorSelect.clear();
    menuSectionsSelect.clear();
    menuCardsSelect.clear();
    authorsSelect.clear();
    showSelect.clear();
    showchefSelect.clear();
    videoLengthSelect.clear();

      searchInput.value = '';
      window.clearTimeout(searchDebounceTimer);
      executeSearch(); // Trigger the search after updating the query
      toggleCloseIcon(); // Adjust close icon visibility
    });
    }

    searchInput.addEventListener('input', () => {
      const queryValue = searchInput.value.trim();

      // Natural-language searches can be relatively expensive and should only
      // run after an intentional submission. Keep the typed value local until
      // Enter is pressed; the keypress handler below updates the URL and runs it.
      if (typeSenseConfig.submitSearchOnEnterOnly) {
        window.clearTimeout(searchDebounceTimer);
        toggleCloseIcon();
        return;
      }

      if (searchInput.value.trim() === '') {
        const url = new URL(window.location.href);
        const params = Array.from(url.searchParams.keys());
        
        params.forEach(param => url.searchParams.delete(param));
        
        // Update the browser's URL without reloading the page
        window.history.replaceState({}, '', url);
        searchInput.value = '';
        window.clearTimeout(searchDebounceTimer);
        executeSearch(); // Trigger the search after updating the query
        toggleCloseIcon(); // Adjust close icon visibility

        return;
      }

      document.querySelector('#tab-recipes').removeAttribute('data-page');
      document.querySelector('#tab-menus').removeAttribute('data-page');
      document.querySelector('#tab-articles').removeAttribute('data-page');
      document.querySelector('#tab-shows').removeAttribute('data-page');
      updateQueryInURL(queryValue);
      scheduleSearch();
    });


    searchInput.addEventListener('keypress', (event) => {
        if (event.key === 'Enter') {


          searchInput.blur();
          document.querySelector('#tab-recipes').removeAttribute('data-page');
          document.querySelector('#tab-menus').removeAttribute('data-page');
          document.querySelector('#tab-articles').removeAttribute('data-page');
          document.querySelector('#tab-shows').removeAttribute('data-page');
          event.preventDefault();
      
          // Update the URL and remove 'act' and 'p' parameters
          const url = new URL(window.location);
          url.searchParams.delete('act');
          url.searchParams.delete('op');
          url.searchParams.set('q', searchInput.value); // Update 'q' or your query parameter
          window.history.replaceState({}, '', url);
      
    // Remove 'show' and 'active' classes from all tab-panes
    document.querySelectorAll('.tab-pane').forEach((tabPane) => {
        tabPane.classList.remove('show', 'active');
      });
  
      // Add 'show' and 'active' to the tab-pane with ID 'all'
      const allTabPane = document.querySelector('.tab-pane#all');
      if (allTabPane) {
        allTabPane.classList.add('show', 'active');
      }
  
      // Remove 'active' class from all nav-items under #searchTabs
      document.querySelectorAll('#searchTabs .nav-item .nav-link').forEach((navLink) => {
        navLink.classList.remove('active');
      });

      const allTabNav = document.querySelector('#searchTabs .nav-link#tab-all');
      if ( allTabNav) {
        allTabNav.classList.add('active');
      }
          // Trigger the search
          window.clearTimeout(searchDebounceTimer);
          executeSearch(undefined, undefined, undefined, false);
          toggleCloseIcon();

        }
      });
      

    if (searchIcon) {
    searchIcon.addEventListener('click', () => {  
     const searchFiltersCombo = document.querySelector('.search-filters-combo');
  
      if (searchInput.value.trim()) {
        updateQueryInURL(searchInput.value.trim());
      }

      if (searchFiltersCombo) {
      if (searchFiltersCombo.style.display === 'none' || searchFiltersCombo.style.display === '') {
        searchFiltersCombo.style.display = 'flex'; // Show the element
      } else {
        searchFiltersCombo.style.display = 'none'; // Hide the element
      } 
      }
      // Remove the data-page attribute for each tab
      document.querySelector('#tab-recipes').removeAttribute('data-page');
      document.querySelector('#tab-menus').removeAttribute('data-page');
      document.querySelector('#tab-articles').removeAttribute('data-page');
      document.querySelector('#tab-shows').removeAttribute('data-page');
      const url = new URL(window.location);
      url.searchParams.delete('act');
      url.searchParams.delete('op');

    // Remove 'show' and 'active' classes from all tab-panes
    document.querySelectorAll('.tab-pane').forEach((tabPane) => {
        tabPane.classList.remove('show', 'active');
      });
  
      // Add 'show' and 'active' to the tab-pane with ID 'all'
      const allTabPane = document.querySelector('.tab-pane#all');
      if (allTabPane) {
        allTabPane.classList.add('show', 'active');
      }
  
      // Remove 'active' class from all nav-items under #searchTabs
      document.querySelectorAll('#searchTabs .nav-item .nav-link').forEach((navLink) => {
        navLink.classList.remove('active');
      });

      const allTabNav = document.querySelector('#searchTabs .nav-link#tab-all');
      if ( allTabNav) {
        allTabNav.classList.add('active');
      }

      window.clearTimeout(searchDebounceTimer);
      executeSearch(undefined, undefined, undefined, false); // Trigger the search after updating the query
    });
    }

    searchInput.addEventListener('input', toggleCloseIcon);

    if (!didRunInitialQuerySearch) {
      executeSearch();
    }

  });








    async function toggleLike(button) {
      try {
        // Retrieve the postId from the button's data-post attribute
        const postId = button.dataset.post;
    
        // Validate postId
        if (!postId) {
          throw new Error('Invalid postId: The data-post attribute is missing or invalid.');
        }
    
        // Construct the AJAX URL
        const url = `${typeSenseConfig.ajaxUrl}?action=kosher_typesense_toggle_favorite`;
    
        // Prepare the request payload
        const requestBody = {
          id: postId, // Use postId from the data-post attribute
          nonce: typeSenseConfig.security, // Pass the nonce for security
        };
    

    
        // Make the AJAX request
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(requestBody),
        });
    
        // Check if the response is successful
        if (!response.ok) {
          const errorText = await response.text();
          throw new Error(`HTTP error! Status: ${response.status} - ${errorText}`);
        }
    
        const result = await response.json();
    
        // Check the server's response
        if (result.success) {

          return result.data; // Return the data from the server
        } else {
          throw new Error(result.message || 'Unknown error occurred');
        }
      } catch (error) {
        console.error('Error toggling like:', error);
      }
    }
    
    
// Add event listener to like buttons, including dynamically rendered search results
document.addEventListener('click', async (event) => {
  const button = event.target.closest('.like-button, .kayco-card__like');

  if (!button || button.dataset.likePending === 'true') {
    return;
  }

  event.preventDefault();
  event.stopPropagation();

  if (!button.dataset.post) {
    const card = button.closest('.kayco-card');
    button.dataset.post = card ? (card.dataset.kaycoPostId || card.dataset.kosherPostId || '') : '';
  }

  const likeCount = button.querySelector('.like-count');
  const currentLikeCount = likeCount
    ? Math.max(0, Number.parseInt(likeCount.textContent, 10) || 0)
    : 0;

  button.dataset.likePending = 'true';
  button.disabled = true;

  try {
    // Pass the clicked button to the toggleLike function
    const result = await toggleLike(button);

    if (!result) {
      return;
    }

    // Update the UI based on the response
    const icon = button.querySelector('i');

    if (icon) {
      // Toggle the icon class based on the action
      if (result.exists) {
        icon.classList.remove('far');
        icon.classList.add('fas');
        icon.classList.remove('bi-heart');
        icon.classList.add('bi-heart-fill');
      } else {
        icon.classList.remove('fas');
        icon.classList.add('far');
        icon.classList.remove('bi-heart-fill');
        icon.classList.add('bi-heart');
      }
    }

    // Update the like count
    if (likeCount) {
      const responseLikeCount = Number.parseInt(result.total_likes, 10);
      const updatedLikeCount = Number.isFinite(responseLikeCount)
        ? Math.max(0, responseLikeCount)
        : result.exists
          ? currentLikeCount + 1
          : Math.max(0, currentLikeCount - 1);

      likeCount.textContent = updatedLikeCount;
      button.setAttribute('aria-label', `${updatedLikeCount} saves`);
    }
  } finally {
    delete button.dataset.likePending;
    button.disabled = false;
  }
});

});




document.addEventListener('DOMContentLoaded', function () {

  document.querySelectorAll('.new-feature .bi').forEach((icon, index) => {
const feature = document.querySelector('.new-feature');
    const COOKIE_NAME = 'hide_bi_icon_' + index;

    // Check cookie
    if (document.cookie.includes(COOKIE_NAME + '=true')) {
      icon.style.display = 'none';
      return;
    }

    icon.addEventListener('click', function () {

      icon.style.display = 'none';

      document.cookie = COOKIE_NAME + "=true; path=/; max-age=" + (60 * 60 * 24 * 365);

    });

  });

});

document.addEventListener('DOMContentLoaded', function () {

  document.querySelectorAll('.new-feature .bi').forEach((icon, index) => {

    const COOKIE_NAME = 'hide_new_feature_' + index;

    // ✅ Better cookie check
    const isHidden = document.cookie
      .split('; ')
      .find(row => row.startsWith(COOKIE_NAME + '='));

    if (isHidden) {
      icon.style.display = 'none';
      return;
    }

    icon.addEventListener('click', function () {

      // Hide icon
      icon.style.display = 'none';

      // OPTIONAL: hide whole feature block (better UX)
      const feature = icon.closest('.new-feature');
      if (feature) {
        feature.style.display = 'none';
      }

      // Set cookie (1 year)
      document.cookie = COOKIE_NAME + "=true; path=/; max-age=" + (60 * 60 * 24 * 365);

    });

  });

});
