<?php

defined('_JEXEC') or die('Restricted access');


require_once JPATH_BASE .DS. '/components/com_bonuses/chron/api.php';
require_once( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'scripts'.DS.'functions.php' );

$filebar = JPATH_BASE .DS. '/components/com_bonuses/chron/totals.json';

$jsonbar = file_get_contents($filebar);

// Decodifica il JSON in un array associativo
$databar = json_decode($jsonbar, true);

$countryData = array();
foreach ($databar['countries'] as $country) {
    $countryData[strtoupper($country['code'])] = $country;
}

//print_r($countryData);

$datarankedgeo = get_api_data([
    'order' => [
        ['field' => 'rank', 'direction' => 'ASC']
    ],
    'limit' => '0,5', 
    'countrycode' => $countrycode
]);
$rankedgeo = json_decode($datarankedgeo, true);

if (!empty($rankedgeo)) {

    foreach ($rankedgeo as $result) {
        $layoutbargeo .= '<div class="tabs__item py-5 px-10 rounded">
                      <div class="row mx-n5 align-items-center">
                        <div class="col-auto px-5">
                          <figure class="thumb thumb-s-sm-50 thumb-s-30 thumb-circle">
                            <a href="'.$result['reviewurl'].'"><img src="images/casino-logos/'.$result['jr_logowebp'].'" alt="'.$result['title'].' Logo"></a>
                          </figure>
                        </div>
                        <div class="col px-5">
                          <p class="tabs__item__truncate font-size-sm"><a href="'.$result['reviewurl'].'">'.$result['title'].'</a></p>
                        </div>
                        <div class="col-auto px-5">'.render_join_btn_sm($countrycode,$result['jr_restrictedcountries'],$result['jr_gourl'],$countryname).'</div>
                      </div>
                    </div>
';
    }
}

$datarankedall = get_api_data([
    'order' => [
        ['field' => 'rank', 'direction' => 'ASC']
    ],
    'limit' => '0,5'
]);
$rankedall = json_decode($datarankedall, true);

if (!empty($rankedall)) {

    foreach ($rankedall as $result) {
        $layoutbarall .= '<div class="tabs__item py-5 px-10 rounded">
                    <div class="row mx-n5 align-items-center">
                        <div class="col-auto px-5">
                          <figure class="thumb thumb-s-sm-50 thumb-s-30 thumb-circle">
                            <a href="'.$result['reviewurl'].'"><img src="images/casino-logos/'.$result['jr_logowebp'].'" alt="'.$result['title'].' Logo"></a>
                          </figure>
                        </div>
                        <div class="col px-5">
                          <p class="tabs__item__truncate font-size-sm"><a href="'.$result['reviewurl'].'">'.$result['title'].'</a></p>
                        </div>
                        <div class="col-auto px-5">'.render_join_btn_sm($countrycode,$result['jr_restrictedcountries'],$result['jr_gourl'],$countryname).'
                        </div>
                      </div>
                    </div>
';
    }
}

$datacofmbar = get_api_data(['cofm' => 'yes']);
$cofmbar = json_decode($datacofmbar, true);

;?>
<div class="col-lg-3 col-md-4 col-12 mt-md-0 mt-30">
          <div class="bg-white rounded">
            <div class="row align-items-center no-gutters py-8 px-20">
              <div class="col">
                <p class="font-family-korona-one text-blue-dark">Top 5 Picks for</p>
              </div>
              <div class="col-auto line-height-1">
                <button class="top-picks__btn rounded-circle js-picks-country active">
                  <span class="thumb thumb-s-30 rounded-circle">
                    <img src="images/flags/<?php echo $countrycode;?>.svg" alt="<?php echo $countryname;?>">
                  </span>
                </button>
                <button class="top-picks__btn rounded-circle js-picks-world">
                  <span class="thumb thumb-s-30 rounded-circle">
                    <img src="images/icons/i-earth.svg" alt="">
                  </span>
                </button>
              </div>
            </div>

            <div class="swiper-container js-top-picks swiper-container-initialized swiper-container-horizontal">
              <div class="swiper-wrapper" style="transform: translate3d(0px, 0px, 0px);">
                <div class="swiper-slide tabs tabs--green swiper-slide-active" style="width: 323px;">
                  <?php echo $layoutbargeo;?>
                  </div>

                <div class="swiper-slide tabs tabs--green swiper-slide-next" style="width: 323px;">
                  <?php echo $layoutbarall;?>
              </div>
            </div>
          </div>

          <div class="my-20 p-lg-20 p-md-10 p-20 bg-white rounded">
            <h3 class="h6 mb-15 text-center text-blue-dark">Casino of the month</h3>
            <div class="tabs tabs--green mb-20">
              <div class="tabs__item py-5 px-10 rounded">
                <div class="row mx-n5 align-items-center">
                  <div class="col-auto px-5">
                    <figure class="thumb thumb-s-sm-50 thumb-s-30 thumb-circle">
                      <a href=""><img src="images/casino-logos/<?php echo $cofmbar['0']['jr_logowebp'];?>" alt="<?php echo $cofmbar['0']['title'];?> Logo"></a>
                    </figure>
                  </div>
                  <div class="col px-5">
                    <p class="tabs__item__truncate font-size-sm"><a href="<?php echo $cofmbar['0']['reviewurl'];?>"><?php echo $cofmbar['0']['title'];?></a></p>
                  </div>
                  <div class="col-auto px-0 casino-container">
                    <div class="casino-rating position-static p-0">
                      <svg version="1.1" id="L3" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 50 50" width="50" height="50" enable-background="new 0 0 0 0" xml:space="preserve">
                        <circle fill="none" stroke="#D6E3E0" stroke-width="1" cx="26" cy="26" r="23"></circle>
                        <circle class="progress-circle" fill="none" stroke="#16B58F" stroke-width="3" stroke-linecap="round" cx="26" cy="26" r="23" stroke-dasharray="123" stroke-dashoffset="7.1" transform="rotate(-90, 26, 26)">
                        </circle>
                      </svg>
                      <strong class="casino-rating__value absolute-center flex-center text-black font-family-krub font-size-sm"><?php echo roundToOneDecimal($cofmbar['0']['frankvalue']);?></strong>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <h3 class="h6 mb-15 text-center text-blue-dark">Other Casinos</h3>

            <div class="tabs tabs--green">
              <div class="tabs__item p-8 font-size-sm rounded">
                <div class="row mx-n5 align-items-center">
                  <div class="col-auto pl-10 pr-5">
                    <figure class="thumb thumb-s-30">
                      <a href="https://www.freeextrachips.com/online-casinos/newest"><img src="images/icons/new-icon.svg" alt="New Casinos"></a>
                    </figure>
                  </div>
                  <div class="col px-5">
                    <p class="tabs__item__truncate font-weight-bold"><a href="">Newest Casinos</a></p>
                  </div>
                  <div class="col-auto px-5 pr-10"><a href="" class="btn btn-green btn-sm px-10">24</a></div>
                </div>
              </div>
  
              <div class="tabs__item p-8 font-size-sm rounded">
                <div class="row mx-n5 align-items-center">
                  <div class="col-auto pl-10 pr-5">
                    <figure class="thumb thumb-s-30">
                      <a href="<?php echo $countryData[strtoupper($countrycode)]['url'];?>"><img src="/images/flags/<?php echo $countrycode;?>.svg" alt=""></a>
                    </figure>
                  </div>
                  <div class="col px-5">
                    <p class="tabs__item__truncate font-weight-bold"><a href="<?php echo $countryData[strtoupper($countrycode)]['url'];?>">Casinos for <?php echo $countryname;?></a></p>
                  </div>
                  <div class="col-auto px-5 pr-10"><a href="<?php echo $countryData[strtoupper($countrycode)]['url'];?>" class="btn btn-green btn-sm px-10"><?php echo $countryData[strtoupper($countrycode)]['count'];?></a></div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-20 text-center js-sticky" data-margin-top="66" data-sticky-for="991">
            <div class="p-10 bg-white rounded d-inline-block">
              <a href=""><img src="images/examples/fruitycasa-exclusive-fec.gif" alt="" class="rounded"></a>
            </div>
          </div>
        </div>
        </div>