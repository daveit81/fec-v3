<?php
defined('_JEXEC') or die('Restricted access');
jimport('joomla.application.component.view');

class BonusesViewCasino extends JView
{
  function display($tpl = null)
  {
    $app    = JFactory::getApplication();
    $jinput = $app->input;

    // --- Resolve CID (your working fix)
    $cid = (int) $jinput->getInt('cid', 0);
    if (!$cid) {
      $id = (int) $jinput->getInt('id', 0);
      if ($id) { $jinput->set('cid', $id); $cid = $id; }
    }
    if (!$cid) {
      $path = trim(JUri::getInstance()->getPath(), '/');
      $parts = explode('/', $path);
      $alias = end($parts);

      if ($alias !== '') {
        $db = JFactory::getDbo();
        $q  = $db->getQuery(true)
          ->select($db->quoteName('id'))
          ->from($db->quoteName('#__content'))
          ->where($db->quoteName('alias') . ' = ' . $db->quote($alias))
          ->order($db->quoteName('id') . ' DESC');
        $db->setQuery($q, 0, 1);
        $found = (int) $db->loadResult();
        if ($found) { $jinput->set('cid', $found); $cid = $found; }
      }
    }

    // --- This populates $row, $pagination, globals, etc.
    require JPATH_COMPONENT . DS . 'renders' . DS . 'common.php';
    
    $this->bonustypeMap = isset($bonustype) && is_array($bonustype) ? $bonustype : array();
    $this->currencyMap  = isset($currency)  && is_array($currency)  ? $currency  : array();

    // Pass data to the tmpl
    $this->sets        = (isset($row) && is_array($row)) ? $row : array();
    $this->pagination  = isset($pagination) ? $pagination : null;

    $this->countrycode = isset($GLOBALS['fec_countrycode']) ? (string)$GLOBALS['fec_countrycode'] : 'us';
    $this->countryname = isset($GLOBALS['fec_countryname']) ? (string)$GLOBALS['fec_countryname'] : 'United States';

    $this->pageUpdatedDate     = isset($GLOBALS['fec_pageUpdatedDate']) ? (string)$GLOBALS['fec_pageUpdatedDate'] : '';
    $this->pageUpdatedRelative = isset($GLOBALS['fec_pageUpdatedRelative']) ? (string)$GLOBALS['fec_pageUpdatedRelative'] : '';
    $this->pageUpdatedIso      = isset($GLOBALS['fec_pageUpdatedIso']) ? (string)$GLOBALS['fec_pageUpdatedIso'] : '';
    
    // ------------------------------------------------------------
// FEC v2 — Dynamic SEO meta (Casino bonus hub)
// ------------------------------------------------------------
$doc = JFactory::getDocument();

// ---- Casino name (from first bonus set)
$casinoName = 'Casino';
if (!empty($this->sets) && !empty($this->sets[0]['brand'])) {
  $casinoName = trim((string)$this->sets[0]['brand']);
}

// ---- META TITLE
$title = $casinoName . ' Bonuses & Codes — Verified FXCheck™';

// ---- META DESCRIPTION
$description =
  'All ' . $casinoName . ' bonuses in one place: bonus codes, free spins, wagering terms and real FXCheck user reports. Updated and verified.';

// ---------------------------------------------------------------------
// Meta overrides per-cid (SEO surgery brand pages)
// File esterno: helpers/meta_overrides_cid.php
// Ritorna array [cid => ['title' => ..., 'desc' => ...]]
// Se il cid non è presente o il file manca, si usano i default sopra.
// ---------------------------------------------------------------------
$overrideFile = JPATH_COMPONENT . DS . 'helpers' . DS . 'meta_overrides_cid.php';
if (is_file($overrideFile)) {
    $overridesCid = include $overrideFile;
    if (is_array($overridesCid) && isset($overridesCid[$cid])) {
        $ov = $overridesCid[$cid];

        if (!empty($ov['title'])) {
            $title = (string)$ov['title'];
        }
        if (!empty($ov['desc'])) {
            $description = (string)$ov['desc'];
        }
    }
}

// ---- Apply
$doc->setTitle($title);
$doc->setDescription($description);
$doc->setMetaData('robots', 'index, follow');

    // Override: noindex,follow se casino è closed/suspended/blacklisted
    if (!empty($this->sets) && is_array($this->sets[0])) {
      fec_apply_casino_noindex_if_needed($this->sets[0]);
    }

    parent::display($tpl);

  }
}
