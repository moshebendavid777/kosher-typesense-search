document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.kayco-typesense-simple-search[data-post-type="recipes"]').forEach((root) => {
    const fixedFilter = '(community-recipe:=false || (community-recipe:=true && user_consent_public:=true))';
    const existingFilter = String(root.dataset.filterBy || '').trim();

    if (!/(^|\W)community-recipe\s*:/.test(existingFilter)) {
      root.dataset.filterBy = existingFilter
        ? `(${existingFilter}) && (${fixedFilter})`
        : fixedFilter;
    }
  });

  const searchForms = document.querySelectorAll('.kosher-search-form'); // Select all search forms
	  const ajaxUrl = typeSenseConfig.ajaxUrl || typeSenseConfig.ajax_url;
	  const searchAction = typeSenseConfig.searchAction || 'kosher_typesense_search';
	  const collectionPrefix = typeSenseConfig.collectionPrefix || 'live_';
	  const collectionName = (name) => `${collectionPrefix}${name}`;

  function typesenseSearch(payload, signal) {
    console.log('[Kosher Typesense] payload sent to Typesense', payload);
    return fetch(`${ajaxUrl}?action=${encodeURIComponent(searchAction)}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Kosher-Typesense-Nonce': typeSenseConfig.searchNonce || '',
        'X-Kosher-Typesense-Suggestions': '1'
      },
      body: JSON.stringify(payload),
      signal
    }).then(response => {
      return response.json().then(data => {
        if (!response.ok) {
          throw new Error(data && data.error ? data.error : `Search request failed with status ${response.status}`);
        }

        return data;
      });
    });
  }

  function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function escapeHTML(value) {
    const div = document.createElement('div');
    div.textContent = value || '';
    return div.innerHTML;
  }

  function safeUrl(value, fallback = '#') {
    const url = value || fallback;

    if (typeof url !== 'string') {
      return fallback;
    }

    return /^https?:\/\//i.test(url) || url.startsWith('/') || url.startsWith('#') ? url : fallback;
  }

  function escapeTypesenseValue(value) {
    return String(value || '').replace(/\\/g, '\\\\').replace(/`/g, '\\`');
  }

  searchForms.forEach(function (searchForm) {
    const searchInput = searchForm.querySelector('.js-kayco-simple-search-input, .form-control'); // Adjust the selector to match the search input inside the form
    const predictionList = searchForm.querySelector('.list-group'); // Adjust the selector to match the prediction list inside the form
    const searchIcon = searchForm.querySelector('.input-group-text, .kayco-typesense-simple-search__search .bi-search'); // Magnifier button
    const filterControls = searchForm.querySelector('[data-simple-search-filters]');
    const clearAllFiltersButton = searchForm.querySelector('[data-simple-filter-clear-all]');
    const simpleSortRadios = Array.from(searchForm.querySelectorAll('[data-simple-sort-radio]'));
    let suggestionAbortController = null;
    let suggestionDebounceTimer = null;

    // Ensure the predictionList element exists
    if (!predictionList) {
      console.error('Element .list-group not found within this form.');
      return;
    }

    // Function to map and transform the type value
    function transformType(type) {
      const typeMapping = {
        'chef_recipes': 'Recipe',
        'community_recipes': 'Community Recipe',
        'episodes': 'Episode',
        'articles': 'Article',
        // Add more mappings as needed
      };
      return typeMapping[type] || type.replace(/_/g, ' '); // Replace underscores with spaces for unmapped types
    }

    function getSelectedSimpleFilters() {
      if (!filterControls) {
        return [];
      }

      return Array.from(filterControls.querySelectorAll('input[type="checkbox"]:checked')).map((input) => ({
        field: input.dataset.filterField || '',
        mode: input.dataset.filterMode || 'include_any',
        label: input.dataset.filterLabel || input.value,
        value: input.value
      })).filter((filter) => filter.field && filter.value);
    }

    function buildGroupedSimpleFilters() {
      const grouped = new Map();

      getSelectedSimpleFilters().forEach((filter) => {
        const key = `${filter.field}:${filter.mode}`;

        if (!grouped.has(key)) {
          grouped.set(key, {
            field: filter.field,
            mode: filter.mode,
            values: []
          });
        }

        grouped.get(key).values.push(filter.value);
      });

      return Array.from(grouped.values());
    }

    function buildSimpleFilterBy() {
      const filters = [];
      const archiveField = searchForm.dataset.archiveFilterField || '';
      const archiveValue = searchForm.dataset.archiveFilterValue || '';

      if (archiveField && archiveValue) {
        filters.push(`${archiveField}:=\`${escapeTypesenseValue(archiveValue)}\``);
      }

      buildGroupedSimpleFilters().forEach((group) => {
        const values = group.values.filter(Boolean).map(escapeTypesenseValue);

        if (!values.length) {
          return;
        }

        if (group.mode === 'exclude_all') {
          filters.push(values.map((value) => `${group.field}:!=\`${value}\``).join(' && '));
        } else if (group.mode === 'include_all') {
          filters.push(values.map((value) => `${group.field}:=\`${value}\``).join(' && '));
        } else if (group.mode === 'cook_time') {
          filters.push(values.map((value) => `cook_time:${value}`).join(' || '));
        } else {
          filters.push(`(${values.map((value) => `${group.field}:=\`${value}\``).join(' || ')})`);
        }
      });

      return filters.filter(Boolean).join(' && ');
    }

    function getSimpleSortBy() {
      const checkedSort = simpleSortRadios.find((radio) => radio.checked);
      const sortValue = checkedSort ? checkedSort.value : 'default';

      if (sortValue === 'title:asc') {
        return 'title_sort:asc';
      }

      if (sortValue === 'title:desc') {
        return 'title_sort:desc';
      }

      return sortValue && sortValue !== 'default' ? sortValue : '_text_match:desc,date:desc';
    }

    function updateSimpleFilterPills() {
      const selected = getSelectedSimpleFilters();

      updateSimpleFilterControls(selected);
    }

    function updateSimpleFilterControls(selected = getSelectedSimpleFilters()) {
      if (clearAllFiltersButton) {
        clearAllFiltersButton.hidden = selected.length === 0;
      }

      if (!filterControls) {
        return;
      }

      filterControls.querySelectorAll('[data-simple-filter-group]').forEach((group) => {
        const checkedInputs = Array.from(group.querySelectorAll('input[type="checkbox"]:checked'));
        const countElement = group.querySelector('[data-simple-filter-count]');
        const clearButton = group.querySelector('[data-simple-filter-clear]');

        if (countElement) {
          countElement.textContent = checkedInputs.length;
          countElement.hidden = checkedInputs.length === 0;
        }

        if (clearButton) {
          clearButton.hidden = checkedInputs.length === 0;
        }
      });
    }

    function clearSimpleFilterInputs(inputs) {
      inputs.forEach((input) => {
        input.checked = false;
      });

      updateSimpleFilterPills();
      refreshSimpleSearch();
    }

    function filterSimpleFilterOptions(searchInputElement) {
      const group = searchInputElement.closest('[data-simple-filter-group]');
      const query = searchInputElement.value.trim().toLowerCase();

      if (!group) {
        return;
      }

      group.querySelectorAll('.kayco-typesense-simple-search__filter-option').forEach((option) => {
        const label = option.textContent.trim().toLowerCase();
        option.hidden = query.length > 0 && !label.includes(query);
      });
    }

    function refreshSimpleSearch() {
      const inputEvent = new Event('input', { bubbles: true });
      searchInput.dispatchEvent(inputEvent);
    }

    if (filterControls) {
      filterControls.addEventListener('change', (event) => {
        if (!event.target.matches('input[type="checkbox"]')) {
          return;
        }

        if (event.target.checked && event.target.dataset.filterMode === 'cook_time') {
          const group = event.target.closest('[data-simple-filter-group]');

          if (group) {
            group.querySelectorAll('input[type="checkbox"][data-filter-mode="cook_time"]').forEach((checkbox) => {
              if (checkbox !== event.target) {
                checkbox.checked = false;
              }
            });
          }
        }

        updateSimpleFilterPills();
        refreshSimpleSearch();
      });

      filterControls.addEventListener('input', (event) => {
        if (!event.target.matches('[data-simple-filter-search]')) {
          return;
        }

        filterSimpleFilterOptions(event.target);
      });

      filterControls.addEventListener('click', (event) => {
        const groupClearButton = event.target.closest('[data-simple-filter-clear]');

        if (!groupClearButton) {
          return;
        }

        const group = groupClearButton.closest('[data-simple-filter-group]');
        const inputs = group ? Array.from(group.querySelectorAll('input[type="checkbox"]:checked')) : [];
        clearSimpleFilterInputs(inputs);
      });

      filterControls.querySelectorAll('[data-simple-filter-group]').forEach((group) => {
        const toggle = group.querySelector('.kayco-typesense-simple-search__filter-toggle');

        if (toggle) {
          toggle.addEventListener('click', (event) => {
            event.preventDefault();

            const shouldOpen = !group.open;

            filterControls.querySelectorAll('[data-simple-filter-group][open]').forEach((openGroup) => {
              if (openGroup !== group) {
                openGroup.open = false;
              }
            });

            group.open = shouldOpen;
          });
        }

        group.addEventListener('toggle', () => {
          if (!group.open) {
            return;
          }

          filterControls.querySelectorAll('[data-simple-filter-group][open]').forEach((openGroup) => {
            if (openGroup !== group) {
              openGroup.open = false;
            }
          });
        });
      });
    }

    if (clearAllFiltersButton && filterControls) {
      clearAllFiltersButton.addEventListener('click', () => {
        clearSimpleFilterInputs(Array.from(filterControls.querySelectorAll('input[type="checkbox"]:checked')));
      });
    }

    simpleSortRadios.forEach((radio) => {
      radio.addEventListener('change', () => {
        refreshSimpleSearch();
      });
    });

    updateSimpleFilterPills();
// Function to highlight matched input in title
function highlightMatchedText(title, query) {
  if (!query) {
    return escapeHTML(title);
  }

  // Create a regular expression to match the query, case-insensitive
  const safeTitle = escapeHTML(title);
  const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
  // Replace the matching parts with <strong> tags around them
  return safeTitle.replace(regex, '<strong>$1</strong>');
}

    function updateSuggestionList(data) {
      predictionList.innerHTML = ''; // Clear the previous suggestions list
    
      // First, display the suggestions from the fetched data (limited to 3)
// Function to highlight matched input in title
function highlightMatchedText(title, query) {
  if (!query) {
    return escapeHTML(title);
  }

  // Create a regular expression to match the query, case-insensitive
  const safeTitle = escapeHTML(title);
  const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
  // Replace the matching parts with <strong> tags around them
  return safeTitle.replace(regex, '<strong>$1</strong>');
}

// First, display the suggestions from the fetched data (limited to 3)
if (data.length > 0) {
  const query = searchInput.value.trim(); // Get the user's input
  const renderCards = window.matchMedia('(max-width: 1023px)').matches;

  // Display only the first 3 suggestions
  data.slice(0, 3).forEach(function (item) {
    const listItem = document.createElement('li');
    listItem.classList.add('list-group-item');

    let imgUrl = item.img || '';

    // Check if image URL is valid and not empty
    if (imgUrl) {
      if (imgUrl.startsWith('https://www.kosher.com')) {
        // Extract the image name from the URL
        const imageName = imgUrl.substring(imgUrl.lastIndexOf('/') + 1);
        imgUrl = `https://images.kosher.com/uploads/${imageName}`;
      }
    } else {
      imgUrl = `${typeSenseConfig.pluginDirUrl}/images/default-suggestion.jpg`; // Default image
    }

    // Highlight the matching part of the title
    const highlightedTitle = highlightMatchedText(item.title, query);
    const itemType = item.type || '';
    const cardType = itemType === 'articles' ? 'articles' : itemType === 'episodes' ? 'episodes' : 'recipes';
    const cardLabel = transformType(itemType);
    const itemUrl = safeUrl(item.url, '#');
    const itemHref = escapeHTML(itemUrl);
    const safeImageUrl = safeUrl(imgUrl, '');

    if (renderCards) {
      listItem.classList.add('result-item');
      listItem.innerHTML = `
        <article class="kayco-card kayco-card--standard kayco-card--${cardType}" data-kayco-result-type="${escapeHTML(itemType)}">
          <div class="kayco-card__media">
            <a class="kayco-card__image-link suggestion-link" href="${itemHref}">
              ${safeImageUrl ? `<img src="${safeImageUrl}" alt="${escapeHTML(item.title)}" class="kayco-card__image" loading="lazy" decoding="async">` : '<span class="kayco-card__image kayco-card__image--placeholder" aria-hidden="true"></span>'}
            </a>
          </div>
          <div class="kayco-card__body">
            <h3 class="kayco-card__title"><a href="${itemHref}" class="suggestion-link">${highlightedTitle}</a></h3>
            ${cardLabel ? `<div class="kayco-card__meta"><span class="kayco-card__text">${escapeHTML(cardLabel)}</span></div>` : ''}
          </div>
        </article>
      `;
    } else {
      listItem.innerHTML = `
        <div class="name">
          <a href="${itemHref}" class="suggestion-link">
            <div class="name__content">
              <div class="name__content__title">${highlightedTitle}</div>
            </div>
          </a>
        </div>
      `;
    }
    predictionList.appendChild(listItem);
  });
}

    
      // Now, display the "Recently Searched" section
      const recentSearches = getRecentSearchesFromCookie(); // Get recent searches from the cookie
      if (recentSearches.phrases.length > 0 || recentSearches.links.length > 0) {
        const recentSearchesSection = document.createElement('div');
        recentSearchesSection.classList.add('recent-searches-section');
        

        if(recentSearches.phrases.length > 0) {
          recentSearchesSection.innerHTML = '<h5>Recently Searched</h5>';
        }
    
        // Create list for recently searched phrases
        const recentSearchesList = document.createElement('ul');
        recentSearches.phrases.forEach(function (phrase) {
          const listItem = document.createElement('li');
          listItem.classList.add('list-group-item');
          listItem.innerHTML = `
            <a href="/search?q=${encodeURIComponent(phrase)}">${phrase}</a>
          `;
          recentSearchesList.appendChild(listItem);
        });
    
        recentSearchesSection.appendChild(recentSearchesList);
        predictionList.appendChild(recentSearchesSection);
      }
    
      // Display the "Recently Visited" section
      if (recentSearches.links.length > 0) {
        const recentVisitedSection = document.createElement('div');
        recentVisitedSection.classList.add('recent-visited-section');
      
        if (recentSearches.links) {
          recentVisitedSection.innerHTML = '<h5>Recently Visited</h5>';
        }
      
        // Create list for recently visited links
        const recentVisitedList = document.createElement('ul');
        
        recentSearches.links.forEach(function (link) {
          const listItem = document.createElement('li');
          listItem.classList.add('list-group-item');
      
          // Include the featured image if available
          const featuredImage = link.image ? `<img src="${link.image}" alt="${link.title}" class="recent-visited-img" style="width: 16px; height: 16px; margin-right: 10px; border-radius: 4px;">` : '';
      
          listItem.innerHTML = `
            <a href="${link.url}">
              <div class="recent-visited-item">
                ${featuredImage}
                <span>${link.title}</span>
              </div>
            </a>
          `;
          
          recentVisitedList.appendChild(listItem);
        });
      
        recentVisitedSection.appendChild(recentVisitedList);
        predictionList.appendChild(recentVisitedSection);
      }
      
    
    }
    
    

    // Helper function to set the recent searches cookie
function setRecentSearchesCookie(recentSearches) {
  try {
    const expires = new Date();
    expires.setTime(expires.getTime() + 365 * 24 * 60 * 60 * 1000); // 1 year expiration
    const cookieValue = `kosher-user-searches=${encodeURIComponent(JSON.stringify(recentSearches))}; expires=${expires.toUTCString()}; path=/`;

    // Set the cookie
    document.cookie = cookieValue;

  } catch (error) {
    console.error('Error setting cookie:', error);
  }
}
    
// Debugging: Check if the cookie exists or initialize it
if (!document.cookie.includes('kosher-user-searches')) {
  setRecentSearchesCookie({ phrases: [], links: [] }); // Set the cookie with default values
} 


function getRecentSearchesFromCookie() {
  const cookieValue = document.cookie
    .split('; ')
    .find(row => row.startsWith('kosher-user-searches='));

  if (cookieValue) {
    try {
      const recentSearches = JSON.parse(decodeURIComponent(cookieValue.split('=')[1]));
      if (recentSearches && typeof recentSearches === 'object') {
        return recentSearches;
      }
    } catch (e) {
      console.error('Error parsing recent searches cookie:', e);
    }
  }

  return { phrases: [], links: [] }; // Return default empty structure if no valid cookie found
}


function getCookie(cookieName) {
  const cookieArr = document.cookie.split(';');
  for (let i = 0; i < cookieArr.length; i++) {
    let cookiePair = cookieArr[i].split('=');

    // Remove whitespace at the beginning of the cookie name and compare it
    if (cookieName === cookiePair[0].trim()) {
      try {
        // Decode the cookie value
        const cookieData = decodeURIComponent(cookiePair[1]);
        
        // Parse the cookie data into a JavaScript object
        return JSON.parse(cookieData); // Parse the string into an object
      } catch (e) {
        console.error('Error parsing cookie:', e);
        return null;
      }
    }
  }
  return null; // Return null if the cookie is not found
}




// Function to save recent searches to a cookie with debugging
function saveRecentSearch(searchTerm, clickedLink = null) {
  const cookieData = getCookie('kosher-user-searches');
  
  // Parse cookie data only if it's a valid JSON string
  let recentSearches;
  try {
    recentSearches = cookieData;
  } catch (e) {
    console.error('Error parsing recent searches cookie:', e);
    recentSearches = { phrases: [], links: [] }; // Reset the data structure if parsing fails
  }

  // Ensure we're working with arrays for both phrases and links
  if (!Array.isArray(recentSearches.phrases)) {
    recentSearches.phrases = [];
  }
  if (!Array.isArray(recentSearches.links)) {
    recentSearches.links = [];
  }

  // Debugging the current state of phrases and links

  // Add the search term if it's valid and doesn't already exist
  if (searchTerm && !recentSearches.phrases.includes(searchTerm)) {
    recentSearches.phrases.unshift(searchTerm); // Add to the beginning of the array
    if (recentSearches.phrases.length > 4) {
      recentSearches.phrases.pop(); // Remove the oldest (last) item if array exceeds 5
    }
  }

  // Add the clicked link if it's valid and unique
  if (clickedLink && !recentSearches.links.some(link => link.url === clickedLink.url)) {
    recentSearches.links.unshift(clickedLink); // Add to the beginning of the array
    if (recentSearches.links.length > 4) {
      recentSearches.links.pop(); // Remove the oldest (last) item if array exceeds 5
    }
  }

  // Save back to the cookie
  try {
    const cookieValue = JSON.stringify(recentSearches);
    document.cookie = `kosher-user-searches=${encodeURIComponent(cookieValue)};path=/;max-age=${60 * 60 * 24 * 30};`; // Cookie valid for 30 days
  } catch (e) {
    console.error('Error setting cookie:', e);
  }
}



// Function to retrieve the featured image
function getFeaturedImage() {
  let imageUrl = '';

  // Check for .latest__recipe__thumb img
  const latestRecipeThumb = document.querySelector('.latest__recipe__thumb img');
  if (latestRecipeThumb) {
    imageUrl = latestRecipeThumb.getAttribute('src');
  }

  // Check for .recipe-image-print img if not found in the first case
  if (!imageUrl) {
    const recipeImagePrint = document.querySelector('.recipe-image-print img');
    if (recipeImagePrint) {
      imageUrl = recipeImagePrint.getAttribute('src');
    }
  }

  // Check for background-image in any element with class .jw-preview if not found in previous cases
  if (!imageUrl) {
    const jwPreview = document.querySelector('.recipe-thumb img');
    if (jwPreview) {
      imageUrl = jwPreview.getAttribute('src');
    }
  }

  return imageUrl;
}

// Capture link visits
const currentUrl = window.location.href;
const isHomePage = currentUrl === window.location.origin || currentUrl === `${window.location.origin}/`;
const isSearchPage = currentUrl.includes('/search');
const isSearchShows = currentUrl.includes('/shows');

// Only capture non-homepage and non-search page visits
if (!isHomePage && !isSearchPage && !isSearchShows) {
  // Retrieve the title from the .recipe__by__area h3 element
  const recipeTitleElement = document.querySelector('.recipe__by__area h3');
  const pageTitle = recipeTitleElement ? recipeTitleElement.textContent || recipeTitleElement.innerText : document.title || 'Untitled';

  // Retrieve the featured image
  const featuredImage = getFeaturedImage();

  const visitedLink = {
    url: currentUrl,
    title: pageTitle,
    image: featuredImage || '' // If no image is found, use 'No Image'
  };

  // Save the visited link to the cookie
  saveRecentSearch(null, visitedLink);
}





// Function to observe changes to the #predictionList and toggle the 'active' class
function observePredictionListChanges() {
  const observer = new MutationObserver(function (mutationsList) {
    mutationsList.forEach(function (mutation) {
      if (mutation.type === 'childList') {
        // Check if predictionList has content and toggle the 'active' class
        if (predictionList.innerHTML.trim() !== '') {
          predictionList.classList.add('active');
        } else {
          predictionList.classList.remove('active');
        }
      }
    });
  });

  // Start observing the predictionList for changes in the child elements
  observer.observe(predictionList, {
    childList: true, // Watch for changes in child elements
    subtree: true,   // Watch all descendant elements too
  });
}

// Call the function to start observing
observePredictionListChanges();

    
    // Handle input event
    searchInput.addEventListener('input', function () {
      const query = searchInput.value.trim();
      const hasSelectedFilters = getSelectedSimpleFilters().length > 0;
      const hasArchiveFilter = !!(searchForm.dataset.archiveFilterField && searchForm.dataset.archiveFilterValue);
      clearTimeout(suggestionDebounceTimer);

      if (suggestionAbortController) {
        suggestionAbortController.abort();
      }

      if (query.length > 0 || hasSelectedFilters || hasArchiveFilter) {
suggestionDebounceTimer = setTimeout(() => {
suggestionAbortController = new AbortController();
const target = searchForm.dataset.target || '';
const typesenseQuery = query.length > 0 ? query : '*';
let searches = [];

if (target === 'shows') {

	  searches.push({
	    collection: collectionName('episodes'),
	    q: typesenseQuery,
	    query_by: 'title,chef,show',
	    per_page: 3,
	    include_fields: 'title,permalink,url,image,type'
  });

} else if (target === 'articles') {

	  searches.push({
	    collection: collectionName('articles'),
	    q: typesenseQuery,
	    query_by: 'title,author,author_name,article_sub_category',
	    per_page: 3,
	    include_fields: 'title,permalink,url,image,type'
  });

} else {
  // Default = search everything
  const recipeFilterBy = buildSimpleFilterBy();
  const recipeSearch = {
	      collection: collectionName('recipes'),
	      q: typesenseQuery,
	      query_by: 'title,diets,chefs,ingredients,occasions',
	      per_page: 3,
	      include_fields: 'title,permalink,url,image,type',
	      sort_by: getSimpleSortBy(),
	      ...(recipeFilterBy ? { filter_by: recipeFilterBy } : {})
	    };

  searches = searchForm.dataset.archiveFilterField ? [recipeSearch] : [
	    recipeSearch,
	    {
	      collection: collectionName('articles'),
	      q: typesenseQuery,
	      query_by: 'title,author,article_sub_category',
	      per_page: 3,
	      include_fields: 'title,permalink,url,image,type'
	    },
	    {
	      collection: collectionName('episodes'),
	      q: typesenseQuery,
	      query_by: 'title,chef,show',
	      per_page: 3,
	      include_fields: 'title,permalink,url,image,type'
    }
  ];
}

typesenseSearch({
  searches: searches
}, suggestionAbortController.signal)
.then(data => {
  if (!Array.isArray(data.results)) {
    return;
  }

  const suggestions = data.results.flatMap(result => {
    if (result.error || !Array.isArray(result.hits)) {
      return [];
    }

    return result.hits.map(hit => ({
      title: hit.document.title || '',
      url: hit.document.permalink || hit.document.url || '#',
      img: hit.document.image || '',
      type: hit.document.type || ''
    }));
  });

  updateSuggestionList(suggestions);
})
  .catch(error => {
    if (error.name === 'AbortError') {
      return;
    }
    console.error('Error fetching suggestions:', error);
  });
}, 180);
      } else {
        predictionList.classList.remove('active');
        predictionList.innerHTML = '';  // Clear the list if the query is empty
      }
    });

    // Close suggestions when clicking outside the form, but keep the magnifier button active
    document.addEventListener('click', function (event) {
      if (!searchForm.contains(event.target) && (!searchIcon || !searchIcon.contains(event.target))) {
        predictionList.innerHTML = ''; // Clear suggestions
      }
    });

// Handle Enter key press
const target = searchForm.dataset.target || '';

searchInput.addEventListener('keypress', function (event) {
  if (event.key === 'Enter') {
    event.preventDefault();

    const query = searchInput.value.trim();

    if (query.length > 0) {
      saveRecentSearch(query);

      let url = '/search?q=' + encodeURIComponent(query);

      if (target) {
        url += '&op=' + encodeURIComponent(target);
      }

      window.location.href = url;
    } else {
      window.location.href = '/search';
    }
  }
});

// Handle click on the search icon
if (searchIcon) {
  searchIcon.addEventListener('click', function () {
    const query = searchInput.value.trim();
    
    if (query.length > 0) {
      // Save the search term to the cookie before navigating
      saveRecentSearch(query);
      let url = '/search?q=' + encodeURIComponent(query);

      if (target) {
        url += '&op=' + encodeURIComponent(target);
      }

      window.location.href = url;
    } else {
      window.location.href = '/search';
    }
  });
}


  });
});
