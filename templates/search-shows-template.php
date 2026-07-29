<section class="kosher-search kosher-search-form kayco-typesense-simple-search">
  <div class=" container">
    <div class="container__wrapper">
      <label class="kayco-typesense-simple-search__search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" id="searchInput" class="form-control js-kayco-simple-search-input" value="" placeholder="<?php echo esc_attr($placeholder); ?>">
        <span class="screen-reader-text"><?php esc_html_e('Search this archive', 'kosher-typesense-search'); ?></span>
        <span class="kayco-typesense-simple-search__status js-kayco-simple-search-status" aria-live="polite"></span>
      </label>
      <div id="predictionList" class="list-group mt-2 kayco-typesense-simple-search__results"></div>
    </div>
  </div>

</section>
