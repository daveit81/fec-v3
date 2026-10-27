<?php
defined('_JEXEC') or die('Restricted access');

/**
 * common.php (WRAPPER)
 * - incluso sia da LIST che da DETAILS
 * - carica SEMPRE arrays + libreria
 * - esegue il bootstrap (query + output) SOLO nelle LIST
 */

require_once JPATH_COMPONENT.DS.'helpers'.DS.'pagination.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'countriesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'currenciesarray.php';
require_once JPATH_COMPONENT.DS.'helpers'.DS.'bonustypesarray.php';

// Lib: solo funzioni (nessun side-effect)
require_once JPATH_COMPONENT.DS.'helpers'.DS.'fec_common_lib.php';

$app    = JFactory::getApplication();
$jinput = $app->input;

// Joomla view (di solito: "", "casino", "details", "ads", ...)
$view = strtolower(trim($jinput->getString('view', '')));

// Heuristic robusta: se ho bid > 0 e non sono in "casino", è praticamente sempre DETAILS-like
$bid = (int)$jinput->getInt('bid', 0);

// Views che NON devono eseguire il list bootstrap
$detailsLikeViews = array('details', 'ads');

$isDetailsLike = in_array($view, $detailsLikeViews, true) || ($bid > 0 && $view !== 'casino');

if ($isDetailsLike) {

  // -------------------------------------------------
  // DETAILS MODE: NO query list / NO pagination / NO $output builder
  // Serve però il contesto GEO come prima (countrycode/countryname)
  // -------------------------------------------------

  $geo = fec_detect_country('US');

  $countrycodeup = $geo['cc_up'];              // es: "IT"
  $countrycode   = strtolower($countrycodeup); // es: "it"
  $GLOBALS['fec_geo_src'] = $geo['src'];       // cf | geoip | default

  // IMPORTANT: "Italy" (non uppercase) grazie a fec_country_display_name()
  $countryname = fec_country_name($countrycodeup, $countries_array);

  // Variabili locali (molte view le usano direttamente)
  // NB: qui definiamo anche type/subtype/cid come nel vecchio common.php
  $type    = $jinput->getString('type', '');
  $subtype = $jinput->getString('subtype', '');
  $cid     = $jinput->getInt('cid', 0);

  $fec_preset = $type;
  $GLOBALS['fec_preset'] = $fec_preset;

  // Globals come prima (usate da varie view)
  $GLOBALS['fec_countrycode'] = $countrycode;
  $GLOBALS['fec_countryname'] = $countryname;

  // Compat: se qualche template si aspetta $db già pronto
  $db = JFactory::getDbo();

  // Compat: output deve esistere per evitare notice
  if (!isset($output) || !is_string($output)) $output = '';

  // Default globals “list-only” (così non esplode niente se qualcuno li legge anche in details)
  if (!isset($GLOBALS['fec_notice'])) $GLOBALS['fec_notice'] = '';
  if (!isset($GLOBALS['fec_hide_pagination'])) $GLOBALS['fec_hide_pagination'] = 1;
  
  // --- Breadcrumb (DETAILS) ---
$uri  = JUri::getInstance();
$path = $uri->getPath();

// base host "https://www.freeextrachips.com"
$siteBase = $uri->toString(array('scheme','host','port'));
if (!$siteBase) $siteBase = rtrim(JUri::root(false), '/');

$items = fec_build_breadcrumb_items_from_path($path, $siteBase);

// opzionale: se sei in details bonus, vuoi che l'ultimo sia "titolo bonus" (non lo slug)
if (!empty($GLOBALS['fec_is_details_like'])) {
  // se nel template costruisci $vm, puoi anche fare override lì.
  // qui lasciamo base.
}

$GLOBALS['fec_breadcrumb_html']   = fec_render_breadcrumb_html($items);
$GLOBALS['fec_breadcrumb_jsonld'] = fec_render_breadcrumb_jsonld($items);


} else {

  // -------------------------------------------------
  // LIST MODE: esegui il runtime completo (query + fallback + output + globals)
  // -------------------------------------------------
  require_once JPATH_COMPONENT.DS.'helpers'.DS.'fec_common_bootstrap.php';
}