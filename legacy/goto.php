<?php
// Set flag that this is a parent file
define('_JEXEC', 1);

define('DS', DIRECTORY_SEPARATOR);

/* DON'T EDIT THE FOLLOWING LINE! IT CAN BE EDITED IN THE COMPONENT CONFIGURATION. */
define('JPATH_BASE', dirname(__FILE__));

// get component parameters
$params = getParams();

// generate target link. Defaults to 'index.php' if no valid link/phrase is found
$link = getLink($params);

////////////////////////////////////////////////////
// internal functions


function getParams() {
   require_once (JPATH_BASE . DS . 'includes' . DS . 'defines.php');
   require_once (JPATH_BASE . DS . 'includes' . DS . 'framework.php');

   // initializes the framework, so that the JComponentHelper can be found
   $app = JFactory::getApplication('site');
   $params = &JComponentHelper::getParams('com_shortlink');

   return $params;
}

function getLink($params) {
   // default location
   //$link = "index.php";

   $linknames = explode(",", $params->get('paramname', 'link'));
   foreach ($linknames as $linkname) {
      $phrase = $_GET[$linkname];
      if ($phrase) {
         $url_path = getUrlPath();

         // optionally go to a subdirectory
         $link = $url_path;

         // base url
         $link .= "index.php?option=com_shortlink&phrase=" . $phrase;

         // don't let Joomla send any output
         // see http://blog.joomlatools.eu/2008/01/joomla-15-generating-raw-ouput.html
         $link .= "&tmpl=plugin";

         break;
      }
   } // foreach

   return $link;
}

function getUrlPath() {
   $url_path = '';

   $this_dir = dirname(__FILE__);
   if ($this_dir != JPATH_BASE) {
      if (strlen($this_dir) > strlen(JPATH_BASE)) {
         $url_path = substr($this_dir, strlen(JPATH_BASE) + 1);
      }
      else if (strlen($this_dir) < strlen(JPATH_BASE)) {
         $url_path = substr(JPATH_BASE, strlen($this_dir) + 1);
      }
      else {
         // Houston, we've got a problem...
      }
   }

   if (!empty($url_path) && substr($url_path, -1) != '/') {
      $url_path .= '/';
   }

   return $url_path;
}

// --- FEC click tracker + subid forwarding (v2.1, Aug 2026 batch) -------------
// Joomla is already bootstrapped (getParams ran), so JFactory::getDbo() works.
// This block does two things, both non-blocking:
//   1) logs the click (slug path) with the landing-page subid token — always;
//   2) OPTIONAL direct hand-off: when the phrase resolves to an affiliate URL
//      whose program is ENABLED in fec_subid_program_map(), redirect straight
//      to that URL with the subid appended, skipping the com_shortlink hop.
//      com_shortlink itself stays untouched; its own hit counter is bypassed
//      only for enabled programs (jos_bonus_clicks is the tracker of record).
// The direct hand-off is OFF until the three FEC_SL_* constants are filled
// from the output of /home/freeextr/scripts/fec_shortlink_diag.php. While
// they are empty, behavior is byte-identical to the previous version, minus
// the extra subid value now stored in jos_bonus_clicks.
// VERIFY the helper path below matches your install.

define('FEC_SL_TABLE',      '#__shortlink');  // confirmed via fec_shortlink_diag.php, Aug 5 2026
define('FEC_SL_COL_PHRASE', 'phrase');
define('FEC_SL_COL_URL',    'link');

if (!function_exists('fec_log_bonus_click')) {
   $fecLib = JPATH_BASE . DS . 'components' . DS . 'com_bonuses' . DS . 'helpers' . DS . 'fec_common_lib.php';
   if (is_file($fecLib)) {
      require_once $fecLib;
   }
}

$fecSlug = '';
foreach (explode(',', $params->get('paramname', 'link')) as $fecLn) {
   $fecLn = trim($fecLn);
   if ($fecLn !== '' && !empty($_GET[$fecLn])) {
      $fecSlug = (string) $_GET[$fecLn];
      break;
   }
}

$fecSubid = function_exists('fec_subid_from_src')
   ? fec_subid_from_src(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '')
   : '';

if (function_exists('fec_log_bonus_click') && $fecSlug !== '') {
   fec_log_bonus_click(JFactory::getDbo(), 0, 0, array(
      'slug'     => $fecSlug,
      'src_page' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
      'subid'    => $fecSubid,
   ));
}

// optional direct hand-off (gated: constants filled + program enabled)
if ($fecSlug !== '' && $fecSubid !== ''
   && function_exists('fec_subid_append')
   && FEC_SL_TABLE !== '' && FEC_SL_COL_PHRASE !== '' && FEC_SL_COL_URL !== '') {
   try {
      $fecDb = JFactory::getDbo();
      $fecDb->setQuery(
         'SELECT ' . $fecDb->qn(FEC_SL_COL_URL)
         . ' FROM ' . $fecDb->qn(FEC_SL_TABLE)
         . ' WHERE ' . $fecDb->qn(FEC_SL_COL_PHRASE) . ' = ' . $fecDb->q($fecSlug),
         0, 1
      );
      $fecTarget = trim((string) $fecDb->loadResult());
      if ($fecTarget !== '' && preg_match('#^https?://#i', $fecTarget)) {
         $fecWith = fec_subid_append($fecTarget, $fecSubid);
         if ($fecWith !== $fecTarget) {
            // program enabled -> we are now the terminal redirect.
            // Keep the legacy com_shortlink counter consistent: same write
            // it would have done itself (best-effort, never blocks the click).
            try {
               $fecDb->setQuery(
                  'UPDATE ' . $fecDb->qn(FEC_SL_TABLE)
                  . ' SET ' . $fecDb->qn('counter') . ' = ' . $fecDb->qn('counter') . ' + 1,'
                  . ' ' . $fecDb->qn('last_call') . ' = NOW()'
                  . ' WHERE ' . $fecDb->qn(FEC_SL_COL_PHRASE) . ' = ' . $fecDb->q($fecSlug)
               );
               $fecDb->execute();
            } catch (Exception $fecE2) {
               // counter is cosmetic — the tracker of record is jos_bonus_clicks
            }
            header('Location: ' . $fecWith);
            exit;
         }
      }
   } catch (Exception $fecE) {
      // swallow: fall through to the original com_shortlink redirect
   }
}
// -----------------------------------------------------------------------------

header("Location: " . $link);
?>