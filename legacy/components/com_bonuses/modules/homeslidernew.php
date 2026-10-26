<?php

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

defined('_JEXEC') or die('Restricted access');

require( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'currenciesarray.php' );
require_once( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'bonustypesarray.php' );
require( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'helpers'.DS.'bonuslabelsarray.php' );

require( JPATH_BASE .DS.'components'.DS.'com_bonuses'.DS.'scripts'.DS.'functions.php' );

require_once JPATH_BASE .DS. '/components/com_bonuses/chron/api.php';

// Percorso del file JSON
$file = JPATH_BASE .DS. '/components/com_bonuses/chron/homePageData.json';

// Leggi il file JSON
$json = file_get_contents($file);

// Decodifica il JSON in un array associativo
$data = json_decode($json, true);

// Accedi al sotto-array 'totals'
$totalsData = $data['totals'];

// Salva l'ultimo elemento per il totale dei casino
$totalCasinos = array_pop($totalsData);

// Copia l'array $totalsData in un nuovo array $datasoft
$datasoft = $totalsData;

// Controlla se ci sono abbastanza elementi per estrarre 6 elementi casuali
if (count($datasoft) < 6) {
    echo "Non ci sono abbastanza elementi nel file JSON per estrarre 6 elementi casuali.";
    exit;
}

// Ottieni 6 chiavi casuali dall'array
$randomSoftKeys = array_rand($datasoft, 6);

// Usa le chiavi per ottenere 6 elementi casuali dall'array
$randomSoftElements = array();
foreach ($randomSoftKeys as $key) {
    $randomSoftElements[] = $datasoft[$key];
}

$half = count($randomSoftElements) / 2;
$firstHalf = array_slice($randomSoftElements, 0, $half);
$secondHalf = array_slice($randomSoftElements, $half);

// Accessing bonusTypes data from homePageData.json
$combinedBonusArray = $data['bonusTypes'];

$datacofm = get_api_data(['cofm' => 'yes']);
$cofm = json_decode($datacofm, true);
;?>
<div class="home-bg bg-blue-light header-space"> 
    <div class="pt-25 mb-sm-35 mb-20">
  <div class="container">
    <div class="row">
      <div class="col-sm-6 col-12 mb-sm-0 mb-20 casino-container">
        <div class="d-sm-flex flex-sm-column h-100 ">
          <div class="col-sm-auto">
            <h2 class="h6 mb-10 text-blue">Casino of the Month</h2>
          </div>
          <div class="col d-flex flex-column p-20 position-relative rounded bg-white">
            <div class="row  mx-n10">
              <div class="col-auto px-10">
                <figure class="thumb thumb-s-sm-108 thumb-s-96 thumb-rounded">
                  <a href="<?php echo $cofm['0']['reviewurl'];?>"><img src="images/casino-logos/<?php echo $cofm['0']['jr_logowebp'];?>" alt="Logo"></a>
                </figure>
              </div>
              <div class="col px-10 align-self-center">
                <h3 class="h5 pr-40 mb-10 font-family-krub text-black"><a href="<?php echo $cofm['0']['reviewurl'];?>"><?php echo $cofm['0']['title'];?></a></h3>
                <div class="casino-facts text-center mt-sm-0 mt-20">
                  <div class="row justify-content-center">
                    <div class="col-4">
                      <figure class="colored-svg thumb thumb-h-16 mb-5">
                        <?php echo $trophy_icon;?>
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold"><?php echo ordinal($cofm['0']['rank']);?></p>
                      <p class="text-uppercase ranking-total"> out of <?php echo $totalCasinos;?></p>
                    </div>
  
                    <div class="col-4">
                      <figure class="colored-svg thumb thumb-h-16 mb-5">
                        <?php echo $reviews_icon;?>
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold"><?php echo abbrcount($cofm['0']['rcount']);?> </p>
                      <p class="text-uppercase">reviews</p>
                    </div>
  
                    <div class="col-4">
                      <figure class="colored-svg thumb thumb-h-16 mb-5">
                        <?php echo $users_icon;?>
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold"><?php echo abbrcount($cofm['0']['hits']);?> </p>
                      <p class="text-uppercase">Readers</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="casino-rating">
              <svg version="1.1" id="L3" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 60 60" width="60" height="60" enable-background="new 0 0 0 0" xml:space="preserve">
                <circle fill="none" stroke="#EBEBEB" stroke-width="1" cx="30" cy="30" r="28"></circle>
                <circle class="progress-circle" fill="none" stroke="#16B58F" stroke-width="3" stroke-linecap="round" cx="30" cy="30" r="28" stroke-dasharray="175.93" stroke-dashoffset="175.93" transform="rotate(-90, 30, 30)">
                </circle>
              </svg>
              <strong class="casino-rating__value absolute-center flex-center text-black font-family-krub"><?php echo roundToOneDecimal($cofm['0']['frankvalue']);?></strong>
            </div>
  
            <div class="row my-auto mx-n10 justify-content-center">
              
              <div class="col-4 px-10">
                <div class="casino-stats d-flex flex-column h-100 py-15 px-sm-15">
                  <p class="my-auto">Ranking</p>
                  <div class="casino-stats__bar mt-10 mx-auto position-relative">
                    <svg version="1.1" id="L3" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 72 72" width="72" height="72" enable-background="new 0 0 0 0" xml:space="preserve">
                      <circle fill="none" stroke="#E2E2E2" stroke-width="1" cx="36" cy="36" r="34"></circle>
                      <circle class="ranking-circle" fill="none" stroke="#16B58F" stroke-width="4" stroke-linecap="round" cx="36" cy="36" r="34" stroke-dasharray="213.62830044410595" stroke-dashoffset="213.62830044410595" transform="rotate(-90, 36, 36)">
                      </circle>
                    </svg>
                    <strong class="absolute-center px-15 flex-center text-black font-family-krub font-size-sm ranking-text"><?php echo ordinal($cofm['0']['rank']);?></strong>
                  </div>
                </div>
              </div>
  
             <div class="col-4 px-10">
                <div class="casino-stats d-flex flex-column h-100 py-15 px-sm-15">
                  <p class="my-auto">Facts Influence</p>
                  <div class="casino-stats__bar mt-10 mx-auto position-relative">
                    <svg version="1.1" id="L3" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 72 72" width="72" height="72" enable-background="new 0 0 0 0" xml:space="preserve">
                      <circle fill="none" stroke="#E2E2E2" stroke-width="1" cx="36" cy="36" r="34"></circle>
                      <circle class="facts-circle" fill="none" stroke="#17305f" stroke-width="4" stroke-linecap="round" cx="36" cy="36" r="34" stroke-dasharray="210" stroke-dashoffset="34.65" transform="rotate(-90, 36, 36)">
                      </circle>
                    </svg>
                    <strong class="absolute-center px-15 flex-center text-black font-family-krub font-size-sm facts-text"><?php echo $cofm['0']['fvalueContributionPercentage'];?>%</strong>
                  </div>
                </div>
              </div>
              <div class="col-4 px-10">
                <div class="casino-stats d-flex flex-column h-100 py-15 px-sm-15">
                  <p class="my-auto">Feedbacks Influence</p>
                  <div class="casino-stats__bar mt-10 mx-auto position-relative">
                    <svg version="1.1" id="L3" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 72 72" width="72" height="72" enable-background="new 0 0 0 0" xml:space="preserve">
                      <circle fill="none" stroke="#E2E2E2" stroke-width="1" cx="36" cy="36" r="34"></circle>
                      <circle class="facts-circle" fill="none" stroke="#086e56" stroke-width="4" stroke-linecap="round" cx="36" cy="36" r="34" stroke-dasharray="210" stroke-dashoffset="175.35" transform="rotate(-90, 36, 36)">
                      </circle>
                    </svg>
                    <strong class="absolute-center px-15 flex-center text-black font-family-krub font-size-sm facts-text"><?php echo $cofm['0']['rvalueContributionPercentage'];?>%</strong>
                    
                  </div>
                </div>
              </div>
            </div>

            <div>
              <div class="col-sm-8 mx-auto">
                  <?php echo render_join_btn($countrycode,$cofm['0']['jr_restrictedcountries'],$cofm['0']['jr_gourl'],$countryname);?>
              </div>
    
              <p class="casino-tc mt-10">18+  |  New customers only  |  <a href="">T&amp;C Apply</a></p>
            </div>
          </div>
        </div>
      </div>
<?php $datalatest = get_api_data(['order' => 'created', 'limit'=> '0,1']);
$latest_casino = json_decode($datalatest, true);?>
      <div class="col-sm-6 col-12">
        <h3 class="h6 mb-10 text-blue">Latest Reviewed Casino</h3>

        <div class="mb-sm-15 mb-20 p-sm-15 p-20 position-relative rounded bg-white casino-container">
          <div class="row mx-n10">
            <div class="col-auto px-10">
              <figure class="thumb thumb-s-sm-64 thumb-s-96 thumb-rounded">
               <a href="<?php echo $latest_casino['0']['reviewurl'];?>"><img src="images/casino-logos/<?php echo $latest_casino['0']['jr_logowebp'];?>" alt="Logo">
              </figure>
            </div>

            <div class="col px-10 d-sm-block d-none">
              <div class="d-flex pr-40 flex-column justify-content-center h-100 bb-1 b-gray-300">
                <h3 class="h5 font-family-krub text-black"><a href="<?php echo $latest_casino['0']['reviewurl'];?>"><?php echo $latest_casino['0']['title'];?></a></h3>
              </div>
            </div>

            <div class="casino-rating">
              <svg version="1.1" id="L3" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 60 60" width="60" height="60" enable-background="new 0 0 0 0" xml:space="preserve">
                <circle fill="none" stroke="#EBEBEB" stroke-width="1" cx="30" cy="30" r="28"></circle>
                <circle class="progress-circle" fill="none" stroke="#16B58F" stroke-width="3" stroke-linecap="round" cx="30" cy="30" r="28" stroke-dasharray="176" stroke-dashoffset="0" transform="rotate(-90, 30, 30)">
                </circle>
              </svg>
              <strong class="casino-rating__value absolute-center flex-center text-black font-family-krub"><?php echo roundToOneDecimal($latest_casino['0']['frankvalue']);?></strong>
            </div>

            <div class="d-sm-block d-none w-100 mb-sm-10"></div>

            <div class="col px-10">
              <h3 class="h5 mb-10 font-family-krub text-black d-sm-none"><a href=""><?php echo $latest_casino['0']['title'];?></a></h3>

              <div class="casino-facts text-center mt-sm-0 mt-20">
                <div class="row justify-content-center">
                  <div class="col-4">
                    <figure class="colored-svg thumb thumb-h-16 mb-5">
                      <?php echo $trophy_icon;?>
                    </figure>
                    <p class="text-black font-size-sm font-weight-semibold"><?php echo ordinal($latest_casino['0']['rank']);?>
                    <p class="text-uppercase">out of <?php echo $totalCasinos;?></p>
                  </div>

                  <div class="col-4">
                    <figure class="colored-svg thumb thumb-h-16 mb-5">
                      <?php echo $reviews_icon;?>
                    </figure>
                    <p class="text-black font-size-sm font-weight-semibold"><?php echo abbrcount($latest_casino['0']['rcount']);?></p>
                    <p class="text-uppercase">reviews</p>
                  </div>

                  <div class="col-4">
                    <figure class="colored-svg thumb thumb-h-16 mb-5">
                      <?php echo $users_icon;?>
                    </figure>
                    <p class="text-black font-size-sm font-weight-semibold"><?php echo abbrcount($latest_casino['0']['hits']);?> </p>
                    <p class="text-uppercase">Readers</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-sm-5 col-12">
              <div class="mt-sm-0 mt-20 pt-sm-0 pt-20 bt-sm-0 bt-1 b-gray-300">
                <?php echo render_join_btn($countrycode,$latest_casino['0']['jr_restrictedcountries'],$latest_casino['0']['jr_gourl'],$countryname);?>
                <p class="casino-tc mt-8">18+  |  New customers only  |  <a href="">T&amp;C Apply</a></p>
              </div>
            </div>
          </div>
        </div>
<?php $datalcb = get_api_data_bonus([
    'status' => 'active', 
    'limit' => '0,1',
    'order' => ['added','DESC'],
    'countrycode' => $countrycode
]);



$lcb = json_decode($datalcb, true);

if (isset($lcb['data'][0])) {
    $bonus = $lcb['data'][0];
}
?>
        <h3 class="h6 mb-10 text-blue"><?php echo $bonus['label'];?></h3>

        <div class="p-15 position-relative rounded bg-white">
          <div class="row mb-10 mx-n10">
            <div class="col-auto px-10">
              <figure class="thumb thumb-s-sm-64 thumb-s-82 thumb-circle">
                <a href="<?php echo $bonus['BonusUrl'];?>"><img src="images/casino-logos/<?php echo $bonus['jr_logowebp'];?>" alt="Logo"></a>
              </figure>
            </div>

            <div class="col px-10">
              <div class="d-flex flex-column justify-content-center h-100 bb-sm-1 b-gray-300">
                <h3 class="h5 font-family-krub text-black"><a href="<?php echo $bonus['BonusUrl'];?>"><?php echo $bonus['BonusTitle'];?></a></h3>
                <p class="mt-5 font-size-sm"><?php echo $bonus['brand'];?></p>
              </div>
            </div>
          </div>

          <div class="row mx-n10">
            <div class="col">
              <div class="mt-sm-0 mt-10 py-sm-0 py-20 bt-sm-0 bt-1 b-gray-300">
                <div class="casino-facts text-center mx-sm-0 mx-auto">
                  <div class="row justify-content-center">
                    <div class="col-4">
                      <figure class="thumb thumb-h-16 mb-5">
                        <?php echo $wr_icon_blue;?>
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold"><?php echo $bonus['Wager'];?></p>
                      <p class="text-uppercase">wr</p>
                    </div>

                    <div class="col-4">
                      <figure class="thumb thumb-h-16 mb-5">
                        <?php echo $cashable_icon_blue;?>
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold"><?php echo ucfirst($bonus['cashable']);?></p>
                      <p class="text-uppercase">cashable</p>
                    </div>

                    <div class="col-4">
                      <figure class="thumb thumb-h-16 mb-5">
                        <?php echo $code_icon_blue;?>
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold"><?php echo $bonus['CodeCard'];?></p>
                      <p class="text-uppercase">Code</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-sm-5 col-12">
              <?php echo $bonus['BonusPlay'];?>
              <p class="casino-tc mt-8">18+  |  New customers only  |  <a href="">T&amp;C Apply</a></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="ws my-sm-35 my-20 container">
  <div class="p-sm-50 p-25 bg-white rounded">
    <div class="row justify-content-center">
      <div class="col-lg-11 px-lg-0">
        <p class="mb-15 font-family-korona-one font-size-sm text-uppercase text-blue">Welcome to FreeExtraChips</p>

        <h1 class="h2 mb-20">Your Ultimate Guide to the Latest Casino Bonuses, Free Spins & Free Chips</h1>

        <div class="text">
          <p>At FreeExtraChips, we know that bonuses are a big part of the online casino experience. From welcome offers for new players to loyalty rewards for returning customers, bonuses can make all the difference in your gaming sessions. That's why we've made it our mission to keep you up-to-date with the latest and greatest casino bonuses, free spins, and free chips.</p>
  
          <p>Our team scours the web to find the most generous and reputable online casinos, so you don't have to. We have a passion for finding the best free bets, odds multipliers, and of course, free spins with no deposit required. We understand that keeping up with all the different offers and promotions can be overwhelming, but we're here to simplify the process.</p>
          
          <p>Our site features comprehensive reviews of top-rated online casinos, highlighting their games, bonuses, and payment options. We also provide in-depth guides and tutorials to help you navigate the online casino world with ease. Whether you're a seasoned player or just starting out, we're confident that we can help you find the best casino sites with the best bonuses.</p>
          
          <p>So, if you're looking for an online casino experience that offers the most exciting bonuses and promotions, look no further than FreeExtraChips. Let us be your guide to the world of online gambling!</p>
        </div>

        <a href="" class="btn btn-base btn-red btn-only-xs-100 px-sm-50 mt-20">Sign up now</a>
      </div>
    </div>
  </div>
</div>


<?php $dataranked = get_api_data([
    'order' => [
        ['field' => 'rank', 'direction' => 'ASC']
    ],
    'limit' => '0,4', 
    'countrycode' => $countrycode
]);
$ranked = json_decode($dataranked, true);?>
<div class="my-sm-50 my-30  overflow-hidden">
  <div class="container">
    <h3 class="h2 mb-sm-20 text-center">Best Ranked Casinos</h3>

    <div class="row mx-lg-n18">
        
    <?php    if (!empty($ranked)) {

    foreach ($ranked as $result) {
        
       echo '<div class="col-md-3 col-sm-6 col-12 mt-30 px-lg-18">
        <div class="h-100 position-relative casino-container">
          <div class="casino-box d-flex flex-column h-100 p-20 rounded bg-white overflow-hidden">
            <div class="casino-rating">
              <svg version="1.1" id="L3" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 60 60" width="60" height="60" enable-background="new 0 0 0 0" xml:space="preserve">
                <circle fill="none" stroke="#EBEBEB" stroke-width="1" cx="30" cy="30" r="28"></circle>
                <circle class="progress-circle" fill="none" stroke="#16B58F" stroke-width="3" stroke-linecap="round" cx="30" cy="30" r="28" stroke-dasharray="176" stroke-dashoffset="17.6" transform="rotate(-90, 30, 30)">
                </circle>
              </svg>
              <strong class="casino-rating__value absolute-center flex-center text-black font-family-krub">'.roundToOneDecimal($result['frankvalue']).'</strong>
            </div>

            <div class="state-default">
              <div class="row mx-n5">
                <div class="col-auto px-5">
                  <figure class="thumb thumb-s-64 thumb-circle">
                    <a href="'.$result['reviewurl'].'"><img src="images/casino-logos/'.$result['jr_logowebp'].'" alt="'.$result['title'].' Logo"></a>
                  </figure>
                </div>
                <div class="col align-self-center px-5">
                  <h3 class="h6 font-family-krub pr-35"><a href="'.$result['reviewurl'].'">'.$result['title'].'</a></h3>
                  <p class="mt-5 font-size-sm">'.processSoftware($result['jr_soft'], $rowsoft).'</p>
                </div>
              </div>
    
              <div class="mt-15 py-15 bt-1 b-gray-300">
                <div class="casino-facts mx-auto text-center">
                  <div class="row justify-content-center mx-n5">
                    <div class="col-4 px-5">
                      <figure class="colored-svg thumb thumb-h-16 mb-5">
                        '.$trophy_icon.'
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold">'.ordinal($result['rank']).'</p>
                      <p class="text-uppercase">of '.$totalCasinos.'</p>
                    </div>
    
                    <div class="col-4 px-5">
                      <figure class="colored-svg thumb thumb-h-16 mb-5">
                        '.$reviews_icon.'
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold">'.abbrcount($result['rcount']).'</p>
                      <p class="text-uppercase">reviews</p>
                    </div>
    
                    <div class="col-4 px-5">
                      <figure class="colored-svg thumb thumb-h-16 mb-5">
                        '.$users_icon.'
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold">'.abbrcount($result['hits']).'</p>
                      <p class="text-uppercase">Readers</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="state-active">
              <div class="font-size-xs mt-n10 text-gray-900 text-center">
                <div class="casino-box-facts">
                  <p><strong>Established</strong></p>
                  <p>'.extractYear($result['jr_established']).'</p>
                </div>
                <div class="casino-box-facts">
                  <p><strong>License</strong></p>
                  <p>'.getTitlesForField('jr_jurisdiction', $result['jr_jurisdiction'], $rowsfields).'</p>
                </div>
                <div class="casino-box-facts">
                  <p><strong>Website</strong></p>
                  <p>'.getTitlesForField('jr_officialwebsite', $result['jr_officialwebsite'], $rowsfields).'</p>
                </div>
              </div>
            </div>
  
            '.render_join_btn($countrycode,$result['jr_restrictedcountries'],$result['jr_gourl'],$countryname).'
  
            <button type="button" class="casino-box-btn-cf py-15 w-100 font-size-xs text-center js-state" data-parent=".casino-box">
              <span class="state-default">Quick Facts</span>
              <span class="state-active">Close <img src="images/icons/i-casino-quick-facts-close.svg" alt=""></span>
            </button>
  
            <p class="casino-tc py-5 mx-n20 mb-n20 bg-gray-200">18+  |  New customers only  |  <a href="">T&amp;C Apply</a></p>
          </div>
        </div>
      </div>';
    }
} else {
    echo "Nessun risultato trovato.";
}
;?>
      
   </div>
    <div class="mt-30 text-center">
      <a href="" class="btn btn-md btn-outline-blue rounded-pill">View all ranked casinos</a>
    </div>
  </div>
</div>


<?php $datalatests = get_api_data(['order' => 'created', 'limit'=> '1,4']);
$latest_casinos = json_decode($datalatests, true);?>
<div class="my-sm-50 my-30 py-sm-50 py-30 bg-blue-medium overflow-hidden">
  <div class="container">
    <h3 class="h2 mb-sm-20 text-center">Latest Reviewed Casinos</h3>

    <div class="row mx-lg-n18">
      <?php    if (!empty($latest_casinos)) {
    foreach ($latest_casinos as $result) {
        
       echo '<div class="col-md-3 col-sm-6 col-12 mt-30 px-lg-18">
        <div class="h-100 position-relative casino-container">
          <div class="casino-box d-flex flex-column h-100 p-20 rounded bg-white overflow-hidden">
            <div class="casino-rating">
              <svg version="1.1" id="L3" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 60 60" width="60" height="60" enable-background="new 0 0 0 0" xml:space="preserve">
                <circle fill="none" stroke="#EBEBEB" stroke-width="1" cx="30" cy="30" r="28"></circle>
                <circle class="progress-circle" fill="none" stroke="#16B58F" stroke-width="3" stroke-linecap="round" cx="30" cy="30" r="28" stroke-dasharray="176" stroke-dashoffset="17.6" transform="rotate(-90, 30, 30)">
                </circle>
              </svg>
              <strong class="casino-rating__value absolute-center flex-center text-black font-family-krub">'.roundToOneDecimal($result['frankvalue']).'</strong>
            </div>

            <div class="state-default">
              <div class="row mx-n5">
                <div class="col-auto px-5">
                  <figure class="thumb thumb-s-64 thumb-circle">
                    <a href="'.$result['reviewurl'].'"><img src="images/casino-logos/'.$result['jr_logowebp'].'" alt="'.$result['title'].' Logo"></a>
                  </figure>
                </div>
                <div class="col align-self-center px-5">
                  <h3 class="h6 font-family-krub pr-35"><a href="'.$result['reviewurl'].'">'.$result['title'].'</a></h3>
                  <p class="mt-5 font-size-sm">'.processSoftware($result['jr_soft'], $rowsoft).'</p>
                </div>
              </div>
    
              <div class="mt-15 py-15 bt-1 b-gray-300">
                <div class="casino-facts mx-auto text-center">
                  <div class="row justify-content-center mx-n5">
                    <div class="col-4 px-5">
                      <figure class="colored-svg thumb thumb-h-16 mb-5">
                        '.$trophy_icon.'
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold">'.ordinal($result['rank']).'</p>
                      <p class="text-uppercase">of '.$totalCasinos.'</p>
                    </div>
    
                    <div class="col-4 px-5">
                      <figure class="colored-svg thumb thumb-h-16 mb-5">
                        '.$reviews_icon.'
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold">'.abbrcount($result['rcount']).'</p>
                      <p class="text-uppercase">reviews</p>
                    </div>
    
                    <div class="col-4 px-5">
                      <figure class="colored-svg thumb thumb-h-16 mb-5">
                        '.$users_icon.'
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold">'.abbrcount($result['hits']).'</p>
                      <p class="text-uppercase">Readers</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="state-active">
              <div class="font-size-xs mt-n10 text-gray-900 text-center">
                <div class="casino-box-facts">
                  <p><strong>Established</strong></p>
                  <p>'.extractYear($result['jr_established']).'</p>
                </div>
                <div class="casino-box-facts">
                  <p><strong>License</strong></p>
                  <p>'.getTitlesForField('jr_jurisdiction', $result['jr_jurisdiction'], $rowsfields).'</p>
                </div>
                <div class="casino-box-facts">
                  <p><strong>Website</strong></p>
                  <p>'.getTitlesForField('jr_officialwebsite', $result['jr_officialwebsite'], $rowsfields).'</p>
                </div>
              </div>
            </div>
  
            '.render_join_btn($countrycode,$result['jr_restrictedcountries'],$result['jr_gourl'],$countryname).'
  
            <button type="button" class="casino-box-btn-cf py-15 w-100 font-size-xs text-center js-state" data-parent=".casino-box">
              <span class="state-default">Quick Facts</span>
              <span class="state-active">Close <img src="images/icons/i-casino-quick-facts-close.svg" alt=""></span>
            </button>
  
            <p class="casino-tc py-5 mx-n20 mb-n20 bg-gray-200">18+  |  New customers only  |  <a href="">T&amp;C Apply</a></p>
          </div>
        </div>
      </div>';
    }
} else {
    echo "Nessun risultato trovato.";
}
;?>

   </div>
    <div class="mt-30 text-center">
      <a href="" class="btn btn-md btn-outline-blue rounded-pill">View other newest casinos</a>
    </div>
  </div>
</div>


<?php $datacrypto = get_api_data([
    'customField' => 'jr_currencies', 
    'values' => ['btc', 'eth', 'ltc'],
    'limit' => '0,4', 
    'vote' => ['operator' => '>=', 'value' => '7'],
    'noclosed' => 'yes',
    'countrycode' => $countrycode,
    'order' => 'random'
]);
$rowcrypto = json_decode($datacrypto, true);?>

<div class="my-sm-50 my-30  overflow-hidden">
  <div class="container">
    <h3 class="h2 mb-sm-20 text-center">Top Crypto Casinos</h3>

    <div class="row mx-lg-n18">
      <?php    if (!empty($rowcrypto)) {
    foreach ($rowcrypto as $result) {
        
       echo '<div class="col-md-3 col-sm-6 col-12 mt-30 px-lg-18">
        <div class="h-100 position-relative casino-container">
          <div class="casino-box d-flex flex-column h-100 p-20 rounded bg-white overflow-hidden">
            <div class="casino-rating">
              <svg version="1.1" id="L3" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 60 60" width="60" height="60" enable-background="new 0 0 0 0" xml:space="preserve">
                <circle fill="none" stroke="#EBEBEB" stroke-width="1" cx="30" cy="30" r="28"></circle>
                <circle class="progress-circle" fill="none" stroke="#16B58F" stroke-width="3" stroke-linecap="round" cx="30" cy="30" r="28" stroke-dasharray="176" stroke-dashoffset="17.6" transform="rotate(-90, 30, 30)">
                </circle>
              </svg>
              <strong class="casino-rating__value absolute-center flex-center text-black font-family-krub">'.roundToOneDecimal($result['frankvalue']).'</strong>
            </div>

            <div class="state-default">
              <div class="row mx-n5">
                <div class="col-auto px-5">
                  <figure class="thumb thumb-s-64 thumb-circle">
                    <a href=""><img src="images/casino-logos/'.$result['jr_logowebp'].'" alt="'.$result['title'].' Logo"></a>
                  </figure>
                </div>
                <div class="col align-self-center px-5">
                  <h3 class="h6 font-family-krub pr-35"><a href="'.$result['reviewurl'].'">'.$result['title'].'</a></h3>
                  <p class="mt-5 font-size-sm">'.processSoftware($result['jr_soft'], $rowsoft).'</p>
                </div>
              </div>
    
              <div class="mt-15 py-15 bt-1 b-gray-300">
                <div class="casino-facts mx-auto text-center">
                  <div class="row justify-content-center mx-n5">
                    <div class="col-4 px-5">
                      <figure class="colored-svg thumb thumb-h-16 mb-5">
                        '.$trophy_icon.'
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold">'.ordinal($result['rank']).'</p>
                      <p class="text-uppercase">of '.$totalCasinos.'</p>
                    </div>
    
                    <div class="col-4 px-5">
                      <figure class="colored-svg thumb thumb-h-16 mb-5">
                        '.$reviews_icon.'
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold">'.abbrcount($result['rcount']).'</p>
                      <p class="text-uppercase">reviews</p>
                    </div>
    
                    <div class="col-4 px-5">
                      <figure class="colored-svg thumb thumb-h-16 mb-5">
                        '.$users_icon.'
                      </figure>
                      <p class="text-black font-size-sm font-weight-semibold">'.abbrcount($result['hits']).'</p>
                      <p class="text-uppercase">Readers</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="state-active">
              <div class="font-size-xs mt-n10 text-gray-900 text-center">
                <div class="casino-box-facts">
                  <p><strong>Established</strong></p>
                  <p>'.extractYear($result['jr_established']).'</p>
                </div>
                <div class="casino-box-facts">
                  <p><strong>License</strong></p>
                  <p>'.getTitlesForField('jr_jurisdiction', $result['jr_jurisdiction'], $rowsfields).'</p>
                </div>
                <div class="casino-box-facts">
                  <p><strong>Website</strong></p>
                  <p>'.getTitlesForField('jr_officialwebsite', $result['jr_officialwebsite'], $rowsfields).'</p>
                </div>
              </div>
            </div>
  
            '.render_join_btn($countrycode,$result['jr_restrictedcountries'],$result['jr_gourl'],$countryname).'
  
            <button type="button" class="casino-box-btn-cf py-15 w-100 font-size-xs text-center js-state" data-parent=".casino-box">
              <span class="state-default">Quick Facts</span>
              <span class="state-active">Close <img src="images/icons/i-casino-quick-facts-close.svg" alt=""></span>
            </button>
  
            <p class="casino-tc py-5 mx-n20 mb-n20 bg-gray-200">18+  |  New customers only  |  <a href="">T&amp;C Apply</a></p>
          </div>
        </div>
      </div>';
    }
} else {
    echo "Nessun risultato trovato.";
}
;?>
    </div>

    <div class="mt-30 text-center">
      <a href="" class="btn btn-md btn-outline-blue rounded-pill">View all ranked casinos</a>
    </div>
  </div>
</div>

<div class="about-us mt-sm-50 mt-30 bg-blue-dark overflow-hidden">
  <div class="container">
    <div class="px-sm-50">
      <div class="row py-sm-40 py-30">
        <div class="col-lg col-sm-7 col-12">
          <p class="mb-15 font-family-korona-one font-size-sm text-uppercase text-blue">About FreeExtraChips</p>
          <h2 class="h2 mb-20 text-white">Your Ultimate Casino Bonus Guide</h2>
          <div class="text text-white">
            <p>At FreeExtraChips, our mission is to provide you with the most comprehensive and up-to-date guide to online casino bonuses, promotions, and exclusive deals. With a focus on quality and user experience, we aim to be your one-stop destination for all things related to the online gambling world.</p>
            <p>Our team of passionate and knowledgeable casino enthusiasts is dedicated to continuously scouring the internet for the latest and greatest bonuses and promotions. We meticulously review and rate each casino, taking into consideration factors such as game selection, customer support, payment options, and overall user experience. This allows us to present you with a curated list of top-rated casinos, ensuring that you have a seamless and enjoyable gaming experience.</p>
            <p>At FreeExtraChips, we understand the importance of staying informed in the ever-changing landscape of online gambling. That's why we also provide our visitors with up-to-date industry news, expert insights, and detailed casino reviews. Whether you're a seasoned player or just starting out, our website offers valuable resources to help you make informed decisions and get the most out of your online gaming experience.</p>
            <p>We take pride in our commitment to transparency and responsible gambling. We only feature casinos that adhere to strict industry standards, maintain a valid gaming license, and promote responsible gaming practices. Your safety and enjoyment are our top priorities, and we strive to create a trustworthy and reliable platform for our users.</p>
            <p>Discover the best casino bonuses, promotions, and exclusive deals at FreeExtraChips, your ultimate casino bonus guide. Join our community today and start your journey towards a thrilling and rewarding online gaming experience.</p>
          </div>
        </div>
        <div class="col-lg-auto col-sm-5 col-12 mt-sm-0 mt-30">
          <figure class="text-center">
            <img src="images/img/about-us.svg" alt="">
          </figure>
        </div>
      </div>
    </div>

    <div class="about-us__wbox pt-20 pb-30 position-relative">
      <div class="about-us__box py-sm-50 p-25 position-relative bg-white rounded">
        <div class="row justify-content-center">
          <div class="col-lg-10 px-lg-0">
            <p class="mb-15 font-family-korona-one font-size-sm text-uppercase text-blue">Our Database</p>
    
            <h2 class="h2 mb-20">Online Casinos</h2>
    
            <div class="text">
              <p>We have sorted all of our featured online casinos into an easily accessible database and filtered them according to various promotional offers and bonuses, iGaming software providers, banking options, release date, global ranking and much, much more. Because of that you can be sure to find what you’re looking for in seconds and get the most important facts, with a gameplay video, about the operator on a single page! Use our exclusive online casino directory to have a better gaming experience and get the most out of our exclusive bonuses from the comfort of your home and while you’re on the go.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
</div>
</div>

<div class="tabs tabs--blue my-40 bg-white">
  <div class="container">
    <h3 class="h3 mb-sm-35 mb-20 text-center">Casino Bonuses</h3>
  
    <div class="row justify-content-center">
      <div class="col-sm-5">
        <div class="tabs__item rounded">
          <div class="row mx-n5 align-items-center">
            <div class="col-auto px-10">
              <figure class="thumb thumb-s-sm-50 thumb-s-30">
                <a href="<?php echo $combinedBonusArray['nd']['url'];?>"><img src="images/misc/tab-bonuses-1.svg" alt="<?php echo $combinedBonusArray['nd']['url'];?> Icon"></a>
              </figure>
            </div>
            <div class="col px-5">
              <p class="tabs__item__truncate"><a href="<?php echo $combinedBonusArray['nd']['url'];?>"><?php echo $combinedBonusArray['nd']['title'];?></a></p>
            </div>
            <div class="col-auto px-8"><a href="<?php echo $combinedBonusArray['nd']['url'];?>" class="btn btn-blue"><?php echo $combinedBonusArray['nd']['count'];?></a></div>
          </div>
        </div>

        <div class="tabs__item rounded">
          <div class="row mx-n5 align-items-center">
            <div class="col-auto px-10">
              <figure class="thumb thumb-s-sm-50 thumb-s-30">
                <a href="<?php echo $combinedBonusArray['fs']['url'];?>"><img src="images/misc/tab-bonuses-2.svg" alt=""></a>
              </figure>
            </div>
            <div class="col px-5">
              <p class="tabs__item__truncate"><a href="<?php echo $combinedBonusArray['fs']['url'];?>"><?php echo $combinedBonusArray['fs']['title'];?></a></p>
            </div>
            <div class="col-auto px-8"><a href="<?php echo $combinedBonusArray['fs']['url'];?>" class="btn btn-blue"><?php echo $combinedBonusArray['fs']['count'];?></a></div>
          </div>
        </div>

        <div class="tabs__item rounded">
          <div class="row mx-n5 align-items-center">
            <div class="col-auto px-10">
              <figure class="thumb thumb-s-sm-50 thumb-s-30">
                <a href="<?php echo $combinedBonusArray['nds']['url'];?>"><img src="images/misc/tab-bonuses-3.svg" alt=""></a>
              </figure>
            </div>
            <div class="col px-5">
              <p class="tabs__item__truncate"><a href="<?php echo $combinedBonusArray['nds']['url'];?>"><?php echo $combinedBonusArray['nds']['title'];?></a></p>
            </div>
            <div class="col-auto px-8"><a href="<?php echo $combinedBonusArray['nds']['url'];?>" class="btn btn-blue"><?php echo $combinedBonusArray['nds']['count'];?></a></div>
          </div>
        </div>
      </div>
      <div class="col-sm-5">
        <div class="tabs__item rounded">
          <div class="row mx-n5 align-items-center">
            <div class="col-auto px-10">
              <figure class="thumb thumb-s-sm-50 thumb-s-30">
                <a href="<?php echo $combinedBonusArray['su']['url'];?>"><img src="images/misc/tab-bonuses-4.svg" alt=""></a>
              </figure>
            </div>
            <div class="col px-5">
              <p class="tabs__item__truncate"><a href="<?php echo $combinedBonusArray['su']['url'];?>"><?php echo $combinedBonusArray['su']['title'];?></a></p>
            </div>
            <div class="col-auto px-8"><a href="<?php echo $combinedBonusArray['su']['url'];?>" class="btn btn-blue"><?php echo $combinedBonusArray['su']['count'];?></a></div>
          </div>
        </div>

        <div class="tabs__item rounded">
          <div class="row mx-n5 align-items-center">
            <div class="col-auto px-10">
              <figure class="thumb thumb-s-sm-50 thumb-s-30">
                <a href="<?php echo $combinedBonusArray['hr']['url'];?>"><img src="images/misc/tab-bonuses-5.svg" alt=""></a>
              </figure>
            </div>
            <div class="col px-5">
              <p class="tabs__item__truncate"><a href="<?php echo $combinedBonusArray['hr']['url'];?>"><?php echo $combinedBonusArray['hr']['title'];?></a></p>
            </div>
            <div class="col-auto px-8"><a href="<?php echo $combinedBonusArray['hr']['url'];?>" class="btn btn-blue"><?php echo $combinedBonusArray['hr']['count'];?></a></div>
          </div>
        </div>

        <div class="tabs__item rounded">
          <div class="row mx-n5 align-items-center">
            <div class="col-auto px-10">
              <figure class="thumb thumb-s-sm-50 thumb-s-30">
                <a href="<?php echo $combinedBonusArray['sl']['url'];?>"><img src="images/misc/tab-bonuses-6.svg" alt=""></a>
              </figure>
            </div>
            <div class="col px-5">
              <p class="tabs__item__truncate"><a href="<?php echo $combinedBonusArray['sl']['url'];?>"><?php echo $combinedBonusArray['sl']['title'];?></a></p>
            </div>
            <div class="col-auto px-8"><a href="<?php echo $combinedBonusArray['sl']['url'];?>" class="btn btn-blue"><?php echo $combinedBonusArray['sl']['count'];?></a></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>



<div class="tabs tabs--green my-sm-40 my-20 bg-white">
  <div class="container">
    <h3 class="h3 mb-sm-35 mb-20 text-center">Recommended Online Casinos</h3>
  
    <div class="row justify-content-center">
       <?php

$datar= get_api_data(['featured' => 'yes']);
$datarecomm = json_decode($datar, true);

// If there are less than 6 elements, take all of them. Otherwise, take 6 random ones.
$randomElementsrecomm = (count($datarecomm) <= 6) ? $datarecomm : array_intersect_key($datarecomm, array_flip(array_rand($datarecomm, 6)));

// Divide the array into two halves
$firstHalfrecomm = array_slice($randomElementsrecomm, 0, 3);
$secondHalfrecomm = array_slice($randomElementsrecomm, 3, 3);


    // Print the first half of the elements in the first column
    echo '<div class="col-sm-5">';
    foreach ($firstHalfrecomm as $element) {
        echo '<div class="tabs__item rounded">
          <div class="row mx-n5 align-items-center">
            <div class="col-auto px-10">
              <figure class="thumb thumb-s-sm-50 thumb-s-30 thumb-circle">
                <a href="'.$element['reviewurl'].'"><img src="images/casino-logos/'.$element['jr_logowebp'].'" alt="'.$element['title'].' Logo"></a>
              </figure>
            </div>
            <div class="col px-5">
              <p class="tabs__item__truncate"><a href="'.$element['reviewurl'].'">'.$element['title'].'</a></p>
            </div>
            <div class="col-auto px-8">'.render_join_btn_sm($countrycode,$element['jr_restrictedcountries'],$element['jr_gourl'],$countryname).'</div>
          </div></div>';
    }
    echo '</div>';

    // Print the second half of the elements in the second column
    echo '<div class="col-sm-5">';
    foreach ($secondHalfrecomm as $element) {
        echo '        <div class="tabs__item rounded">
          <div class="row mx-n5 align-items-center">
            <div class="col-auto px-10">
              <figure class="thumb thumb-s-sm-50 thumb-s-30 thumb-circle">
                <a href="'.$element['reviewurl'].'"><img src="images/casino-logos/'.$element['jr_logowebp'].'" alt="'.$element['title'].' Logo"></a>
              </figure>
            </div>
            <div class="col px-5">
              <p class="tabs__item__truncate"><a href="'.$element['reviewurl'].'">'.$element['title'].'</a></p>
            </div>
            <div class="col-auto px-8">'.render_join_btn_sm($countrycode,$element['jr_restrictedcountries'],$element['jr_gourl'],$countryname).'</div>
          </div></div>';
    }
    echo '</div>';
;?>

    </div>
  </div>
   <div class="mt-30 text-center">
<a href="https://www.freeextrachips.com/online-casinos" class="btn btn-md btn-outline-blue rounded-pill">View all Casinos</a>
</div>
</div>



<div class="tabs tabs--gray my-sm-40 my-20 bg-white">
  <div class="container">
    <h3 class="h3 mb-sm-35 mb-20 text-center">Casinos By Software</h3>
  
    <div class="row justify-content-center">
      <div class="col-sm-5">
          
          
          <?php foreach ($secondHalf as $element) {

       echo' <div class="tabs__item rounded">
          <div class="row mx-n5 align-items-center">
            <div class="col px-5">
              <p class="tabs__item__truncate"><a href="'.$element['url'].'">'.$element['text'].'</a></p>
            </div>
            <div class="col-auto px-8"><a href="'.$element['url'].'" class="btn btn-black">'.$element['count'].'</a></div>
          </div>
        </div>';
}?>

      </div>
      <div class="col-sm-5">
          <?php foreach ($firstHalf as $element) {

       echo' <div class="tabs__item rounded">
          <div class="row mx-n5 align-items-center">
            <div class="col px-5">
              <p class="tabs__item__truncate"><a href="'.$element['url'].'">'.$element['text'].'</a></p>
            </div>
            <div class="col-auto px-8"><a href="'.$element['url'].'" class="btn btn-black">'.$element['count'].'</a></div>
          </div>
        </div>';
}?>
      </div>
    </div>
  </div>
  <div class="mt-30 text-center">
<a href="https://www.freeextrachips.com/online-casinos/software" class="btn btn-md btn-outline-blue rounded-pill">View all software providers</a>
</div>
</div>


<?php $exclusivedata = get_api_data_bonus([
    'status' => 'active',
    'limit' => '0,4',
    'order' => ['added','DESC'],
    'countrycode' => $countrycode,
    'exclusive' => 'no'
    
]);

$exclusive = json_decode($exclusivedata, true);
$results = $exclusive['data'];
$total = $exclusive['total'];
?>

<div class="my-sm-50 my-30 py-sm-50 py-30 bg-blue-light text-center overflow-hidden">
  <div class="container">
    <h3 class="h2 mb-sm-20">Exclusive Bonuses</h3>

    <div class="row mx-n18">
    <?php 
    foreach ($results as $result) { echo '
      <div class="col-md-3 col-sm-6 col-12 mt-40 px-lg-18">
        <div class="h-100 position-relative">
          <div class="d-flex flex-column h-100 px-20 pb-20 rounded bg-white">
            <div class="bonus-box-head d-flex flex-column">
              <figure class="thumb thumb-s-64 thumb-circle mb-15 mt-n20 mx-auto">
                <a href="'.$result['BonusUrl'].'"><img src="images/casino-logos/'.$result['jr_logowebp'].'" alt=""></a>
              </figure>
              '.$result['ExcBadge'].'
              <h3 class="h6 mb-5 font-family-krub"><a href="'.$result['BonusUrl'].'">'.$result['BonusTitleCard'].'</a></h3>
              <p class="font-size-sm">'.$result['LabelCard'].'</p>
              <div class="my-auto py-10">
                <p class="bonus-box-code text-center py-8 rounded-sm">Code: <strong>'.$result['CodeCard'].'</strong></p>
                <div class="bonus-box-expires mt-5 js-countdown" data-prefix="Expires in" data-exprired="Expired" data-date="'.$result['end'].'">'.$result['brand'].'</div>
              </div>
            </div>
            
            <div class="bonus-box-foot bt-1 b-gray-300">
              <div class="casino-facts text-center my-15 mx-auto">
                <div class="row justify-content-center">
                  <div class="col-4">
                    <figure class="thumb thumb-h-16 mb-5">
                      <img src="images/icons/i-wr-blue.svg" alt="">
                    </figure>
                    <p class="text-black font-size-sm font-weight-semibold">'.$result['Wager'].'</p>
                    <p class="text-uppercase">wr</p>
                  </div>

                  <div class="col-4">
                    <figure class="thumb thumb-h-16 mb-5">
                      <img src="images/icons/i-bills-blue.svg" alt="">
                    </figure>
                    <p class="text-black font-size-sm font-weight-semibold">'.ucfirst($result['cashable']).'</p>
                    <p class="text-uppercase">cashable</p>
                  </div>

                  <div class="col-4">
                  <a href="'.$result['BonusUrl'].'">
                    <figure class="thumb thumb-h-16 mb-5">
                      <img src="images/icons/i-info-blue.svg" alt="">
                    </figure>
                    <p class="text-black font-size-sm font-weight-semibold">Read</p>
                    <p class="text-uppercase">Details</p>
                  </div>
                </div>
                </a>
              </div>

              '.$result['BonusPlay'].'
            </div>
          </div>
        </div>
      </div>';
}
?>
    </div>

    <div class="mt-30 text-center">
      <a href="" class="btn btn-md btn-outline-blue rounded-pill">View all exclusive bonuses</a>
    </div>
  </div>
</div>

<div class="my-sm-50 my-30 overflow-hidden">
  <div class="container">
    <h3 class="h2 mb-sm-50 mb-30 text-center">Blog</h3>

    <div class="blog-slider pb-30 swiper-container js-blog-slider swiper-container-initialized swiper-container-horizontal">
      <div class="swiper-wrapper" style="transform: translate3d(0px, 0px, 0px);">
        <?php $index = 0;

$rowsBlog = $data['latestBlog'];

foreach ($rowsBlog as $row) {
    $imageUrl = $row['resizedImageUrl'];
    $id = $row['id'];
    $introTextRaw = $row['intro'];
    
    if (!empty(trim($introTextRaw))) {
        $introTextRaw = strip_tags($introTextRaw);
    }

    $introText = substr($introTextRaw, 0, 100) . '...';
    $url = 'index.php?option=com_easyblog&Itemid=416&id=' . $id . '&lang=en&view=entry';
    $slideClass = ($index == 0) ? "swiper-slide swiper-slide-active" : "swiper-slide";
    
    echo '<div class="'.$slideClass.'" style="margin-right: 26px;">
            <div class="post">
                <div class="post__head position-relative">
                    <figure class="post__thumb">
                        <a href="'.$url.'"><img src="'.$imageUrl.'" alt=""></a>
                    </figure>
                </div>
                <div class="post__cont">
                    <h3 class="post__title font-family-krub"><a href="'.$url.'">'.$row['title'].'</a></h3>
                    <div class="post__text text my-10 font-size-sm">
                        <p>'.$introText.'</p>
                    </div>
                    <div class="row">
                        <div class="post__meta col">meta data</div>
                        <div class="col-auto">
                            <a href="'.$url.'" class="text-blue font-size-xs text-hover-underline">read more</a>
                        </div>
                    </div>
                </div>
            </div>
          </div>';
    $index++;


};?>
        
        </div>
      </div>

    <div class="text-center">
      <a href="" class="btn btn-md btn-outline-blue rounded-pill">Got to Blog</a>
    </div>
  </div>
</div>