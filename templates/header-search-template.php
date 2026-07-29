<style>
  .recent-searches-section,
  .recent-visited-section {
    background: #fff;
    font-family: "Inter", sans-serif;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
  }

  .recent-searches-section h5,
  .recent-visited-section h5 {
    background: #fff;
    font-family: "Inter", sans-serif;
    font-size: 11px;
    font-weight: 500;
    line-height: 13.31px;
    letter-spacing: 0.12em;
    text-align: left;
    color: #5A5A5A;
    text-transform: uppercase;
    padding: 0 15px;
    margin: 10px 0 5px 0;
  }

  .recent-searches-section ul,
  .recent-visited-section ul {
    font-family: "Inter", sans-serif;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
  }

  .recent-searches-section ul .list-group-item,
  .recent-visited-section ul .list-group-item {
    color: #1E1E1E;
    padding: 0 15px;
    border: none;
    margin-bottom: 6px;
    line-height: 1;
  }

  #predictionList.active {
    background: #fff;
    border: 1px solid #d3d3d3;
    padding: 9px;
  }

  .name__content__title {
    font-family: "Inter", sans-serif;
    text-align: left !important;
    font-size: 14px !important;
    font-weight: 400 !important;
    line-height: 16.94px !important;
    margin: 5px 0;
    color: #1E1E1E;
  }

  .list-group-item {
    border: none;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
  }

  .list-group-item:hover {
    background: none !important;
  }


  .list-group-item:hover a {
    color: #7A147A !important;
  }


  .list-group-item:hover a .name__content__title {
    color: #7A147A !important;
  }



  .recent-searches-section ul .list-group-item:last-child,
  .recent-visited-section ul .list-group-item:last-child {
    margin-bottom: 0;
  }

  .recent-visited-item {
    display: flex;
    align-items: center;
  }


  .recent-searches-section ul .list-group-item a,
  .recent-visited-section ul .list-group-item a {
    font-family: "Inter", sans-serif;
    text-align: left;
    font-size: 14px;
    font-weight: 400;
    line-height: 16.94px;
    margin: 5px 0;
    color: #1E1E1E;
  }

  .recent-searches-section ul .list-group-item:hover,
  .recent-visited-section ul .list-group-item:hover{
    background: none !important;
    color: #7A147A !important;
  }

  
</style>
<section class="kosher-header-search kosher-search-form">
  <div class=" container">
    <div class="container__wrapper">
      <div class="input-group">
        <input type="text" id="searchInput" class="form-control" placeholder="Search anything on Kosher...">
        <span class="input-group-text">
          <i class="fa fa-search"></i>
        </span>
      </div>
      <div id="predictionList" class="list-group mt-2"></div>
    </div>
  </div>
</section>