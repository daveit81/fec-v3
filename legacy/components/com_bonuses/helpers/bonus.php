<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * com_bonuses - shared dashboard helper
 *
 * Centralises everything the add/edit form, the list view and the
 * Actions controller all need, so the logic lives in exactly one place:
 *   - the single-user authorisation gate
 *   - starlist (*a*b*c*) (de)serialisation for multi-value columns
 *   - form <-> DB date conversion
 *   - brand / game-name lookups
 *   - the option-list queries (casinos, games, banks, authors)
 *   - the static option maps (bonus types, countries, currency, ...)
 *   - welcome packages (jos_fec_bonus_packages / _legs): read, write,
 *     attach/detach a bonus row, the article parser bridge and FXRate
 *     (schema 2, 2026-10-16: legs carry max_cashout / max_cashout_kind;
 *     schema 3, 2026-10-19: legs carry spins_wr, the wagering on the leg's
 *     spins, for real and virtual legs alike -- a bonus row has only the
 *     match's wagering term. NULL = not stated, 0 = no wagering)
 *
 * Functions that used to be declared *inside* the controller method
 * (format_db_date, join_array, get_brand, get_gname) now live here as
 * static methods, so a second task can reuse them without "cannot
 * redeclare function" fatals.
 */
class BonusesHelperBonus
{
	/**
	 * The single Joomla user ID allowed to use the bonus dashboard.
	 * To hand the dashboard to a different account, change THIS LINE ONLY.
	 */
	const DASHBOARD_USER_ID = 42;

	/**
	 * Slug of the author chosen as the default editorial author when
	 * a new bonus is created without an explicit author selection.
	 * If this slug is not found among active authors, the helper
	 * falls back to the first active 'main' author, then to the
	 * lowest-id active author, then to null.
	 */
	const DEFAULT_AUTHOR_SLUG = 'david-romano';

	/* =============================================================
	 * Authorisation
	 * ============================================================= */

	/**
	 * True if the current user may add / edit / toggle / delete bonuses.
	 *
	 * @return boolean
	 */
	public static function authorized()
	{
		$user = JFactory::getUser();

		return (!$user->guest && (int) $user->id === self::DASHBOARD_USER_ID);
	}

	/**
	 * Hard gate. Call at the top of every controller task that writes.
	 * Aborts the request with a 403 if the user is not authorised.
	 *
	 * @return void
	 */
	public static function assertAuthorized()
	{
		if (!self::authorized())
		{
			JError::raiseError(403, JText::_('JERROR_ALERTNOAUTHOR'));
			jexit();
		}
	}

	/* =============================================================
	 * Multi-value (de)serialisation - legacy starlist  *a*b*c*
	 * ============================================================= */

	/**
	 * Joins an array into the legacy starlist format: *a*b*c*
	 * Blanks are dropped. Empty / non-array input returns ''.
	 *
	 * This is the exact format the old join_array() produced, so rows
	 * written by the new save task stay readable by every existing
	 * read path (fec_common_lib, etc.).
	 *
	 * @param   mixed  $array
	 * @return  string
	 */
	public static function joinStarlist($array)
	{
		if (empty($array) || !is_array($array))
		{
			return '';
		}

		$clean = array();

		foreach ($array as $v)
		{
			$v = trim((string) $v);

			if ($v !== '')
			{
				$clean[] = $v;
			}
		}

		if (empty($clean))
		{
			return '';
		}

		return '*' . implode('*', $clean) . '*';
	}

	/**
	 * Splits a starlist (*a*b*c*) back into a flat array of values.
	 * The inverse of joinStarlist(). Empty input returns array().
	 *
	 * @param   string  $string
	 * @return  array
	 */
	public static function splitStarlist($string)
	{
		$string = trim((string) $string);

		if ($string === '')
		{
			return array();
		}

		$string = trim($string, '*');

		if ($string === '')
		{
			return array();
		}

		$parts = explode('*', $string);
		$out   = array();

		foreach ($parts as $p)
		{
			$p = trim($p);

			if ($p !== '')
			{
				$out[] = $p;
			}
		}

		return $out;
	}

	/* =============================================================
	 * Date conversion
	 * ============================================================= */

	/**
	 * Form format (MM-DD-YYYY hh:mm A) -> DB datetime (Y-m-d H:i:s).
	 * Returns null on empty or unparseable input.
	 *
	 * @param   string  $date
	 * @return  string|null
	 */
	public static function formToDbDate($date)
	{
		$date = trim((string) $date);

		if ($date === '')
		{
			return null;
		}

		$dt = DateTime::createFromFormat('m-d-Y h:i A', $date);

		if (!$dt)
		{
			return null;
		}

		return $dt->format('Y-m-d H:i:s');
	}

	/**
	 * DB datetime (Y-m-d H:i:s) -> form format (MM-DD-YYYY hh:mm A).
	 * Returns '' on empty / zero / unparseable input.
	 *
	 * @param   string  $date
	 * @return  string
	 */
	public static function dbToFormDate($date)
	{
		$date = trim((string) $date);

		if ($date === '' || $date === '0000-00-00 00:00:00')
		{
			return '';
		}

		$ts = strtotime($date);

		if (!$ts)
		{
			return '';
		}

		return date('m-d-Y h:i A', $ts);
	}

	/* =============================================================
	 * Lookups
	 * ============================================================= */

	/**
	 * Casino / brand title from a #__content id. '' if not found.
	 *
	 * @param   integer  $id
	 * @return  string
	 */
	public static function getBrand($id)
	{
		$id = (int) $id;

		if ($id <= 0)
		{
			return '';
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('title'))
			->from($db->quoteName('#__content'))
			->where($db->quoteName('id') . ' = ' . $db->quote($id));

		$db->setQuery($query);
		$title = $db->loadResult();

		return ($title !== null) ? $title : '';
	}

	/**
	 * Game name from a #__games gid. '' if not found / no game.
	 *
	 * @param   integer  $id
	 * @return  string
	 */
	public static function getGameName($id)
	{
		$id = (int) $id;

		if ($id <= 0)
		{
			return '';
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('game_name'))
			->from($db->quoteName('#__games'))
			->where($db->quoteName('gid') . ' = ' . $db->quote($id));

		$db->setQuery($query);
		$name = $db->loadResult();

		return ($name !== null) ? $name : '';
	}

	/**
	 * Editorial author name from a jos_casino_authors id.
	 * Returns '' if not found, regardless of is_active.
	 *
	 * @param   integer  $id
	 * @return  string
	 */
	public static function getAuthorName($id)
	{
		$id = (int) $id;

		if ($id <= 0)
		{
			return '';
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('name'))
			->from($db->quoteName('#__casino_authors'))
			->where($db->quoteName('id') . ' = ' . $db->quote($id));

		$db->setQuery($query);
		$name = $db->loadResult();

		return ($name !== null) ? $name : '';
	}

	/* =============================================================
	 * Option-list queries (for the form selects)
	 * ============================================================= */

	/**
	 * Casinos: jReviews content in the casino category (8), excluding
	 * closed / suspended / blacklisted entries. Same scope the old
	 * view used.
	 *
	 * @return  array  assoc list with keys: id, title
	 */
	public static function getCasinoOptions()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select(array('a.id', 'a.title'))
			->from($db->quoteName('#__content', 'a'))
			->join(
				'INNER',
				$db->quoteName('#__jreviews_content', 'b')
				. ' ON (' . $db->quoteName('a.id') . ' = ' . $db->quoteName('b.contentid') . ')'
			)
			->where($db->quoteName('a.catid') . ' = ' . $db->quote('8'))
			->where($db->quoteName('b.jr_closed') . " LIKE ''")
			->where($db->quoteName('b.jr_suspended') . " LIKE ''")
			->where($db->quoteName('b.jr_blacklisted') . " LIKE ''")
			->order($db->quoteName('a.title') . ' ASC');

		$db->setQuery($query);

		return (array) $db->loadAssocList();
	}

	/**
	 * Active games, ordered by name.
	 *
	 * @return  array  assoc list (full rows)
	 */
	public static function getGameOptions()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__games'))
			->where($db->quoteName('status') . ' = ' . $db->quote('1'))
			->order($db->quoteName('game_name') . ' ASC');

		$db->setQuery($query);

		return (array) $db->loadAssocList();
	}

	/**
	 * Deposit-method options from jReviews field 137.
	 *
	 * @return  array  assoc list with keys: text, value
	 */
	public static function getBankOptions()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select(array('text', 'value'))
			->from($db->quoteName('#__jreviews_fieldoptions'))
			->where($db->quoteName('fieldid') . ' = ' . $db->quote('137'))
			->order($db->quoteName('text') . ' ASC');

		$db->setQuery($query);

		return (array) $db->loadAssocList();
	}

	/**
	 * Active editorial authors, ordered by name. Used by both the
	 * add/edit form select and the inline list dropdown.
	 *
	 * @return  array  assoc list with keys: id, name, role, author_type, slug
	 */
	public static function getAuthorOptions()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select(array('id', 'slug', 'name', 'role', 'author_type'))
			->from($db->quoteName('#__casino_authors'))
			->where($db->quoteName('is_active') . ' = ' . $db->quote('1'))
			->order($db->quoteName('name') . ' ASC');

		$db->setQuery($query);

		return (array) $db->loadAssocList();
	}

	/**
	 * Id of the default editorial author for new bonuses.
	 *
	 * Lookup order (each step requires is_active = 1):
	 *   1. the author with slug = DEFAULT_AUTHOR_SLUG (david-romano)
	 *   2. the first author of author_type = 'main'
	 *   3. the active author with the lowest id
	 *   4. null if there are no active authors at all
	 *
	 * Returning null is fine: the bonus will simply have no editorial
	 * author until one is assigned from the list.
	 *
	 * @return  integer|null
	 */
	public static function getDefaultAuthorId()
	{
		$db = JFactory::getDbo();

		// step 1: by slug
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__casino_authors'))
			->where($db->quoteName('slug') . ' = ' . $db->quote(self::DEFAULT_AUTHOR_SLUG))
			->where($db->quoteName('is_active') . ' = ' . $db->quote('1'));
		$db->setQuery($query);
		$id = (int) $db->loadResult();

		if ($id > 0)
		{
			return $id;
		}

		// step 2: first 'main' author
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__casino_authors'))
			->where($db->quoteName('author_type') . ' = ' . $db->quote('main'))
			->where($db->quoteName('is_active') . ' = ' . $db->quote('1'))
			->order($db->quoteName('id') . ' ASC');
		$db->setQuery($query, 0, 1);
		$id = (int) $db->loadResult();

		if ($id > 0)
		{
			return $id;
		}

		// step 3: any active author
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__casino_authors'))
			->where($db->quoteName('is_active') . ' = ' . $db->quote('1'))
			->order($db->quoteName('id') . ' ASC');
		$db->setQuery($query, 0, 1);
		$id = (int) $db->loadResult();

		return ($id > 0) ? $id : null;
	}

	/**
	 * True if the given id matches an ACTIVE author. Used by the
	 * controller to validate POSTed author selections against the
	 * current authors table - we never trust the client.
	 *
	 * @param   integer  $id
	 * @return  boolean
	 */
	public static function isActiveAuthor($id)
	{
		$id = (int) $id;

		if ($id <= 0)
		{
			return false;
		}

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__casino_authors'))
			->where($db->quoteName('id') . ' = ' . $db->quote($id))
			->where($db->quoteName('is_active') . ' = ' . $db->quote('1'));

		$db->setQuery($query);

		return ((int) $db->loadResult()) > 0;
	}

	/* =============================================================
	 * Static option maps (loaded from the legacy helper files)
	 * ============================================================= */

	/**
	 * Loads one of the legacy option arrays from
	 * components/com_bonuses/helpers/ and returns it. Cached per request.
	 *
	 * Plain require() (not require_once) is used on purpose: the legacy
	 * files only define arrays, so re-defining them is harmless, and
	 * require_once would skip the include - leaving the variable
	 * undefined - if the same file was already pulled in elsewhere.
	 *
	 * @param   string  $which  bonustype | countries | currency |
	 *                          platform | players | gametype
	 * @return  array
	 */
	public static function optionMap($which)
	{
		static $cache = array();

		if (isset($cache[$which]))
		{
			return $cache[$which];
		}

		$base = JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR;
		$map  = array();

		switch ($which)
		{
			case 'bonustype':
				require $base . 'bonustypesarray.php';
				$map = isset($bonustype) ? $bonustype : array();
				break;

			case 'countries':
				require $base . 'countriesarray.php';
				$map = isset($countries_array) ? $countries_array : array();
				break;

			case 'currency':
				require $base . 'currenciesarray.php';
				$map = isset($currency) ? $currency : array();
				break;

			case 'platform':
				require $base . 'platformsarray.php';
				$map = isset($platform) ? $platform : array();
				break;

			case 'players':
				require $base . 'playertypesarray.php';
				$map = isset($players) ? $players : array();
				break;

			case 'gametype':
				require $base . 'otherparams.php';
				$map = isset($gametype) ? $gametype : array();
				break;
		}

		$cache[$which] = $map;

		return $map;
	}

	/**
	 * Human label for a bonus-type key. Falls back to a capitalised
	 * version of the key if the map has no entry.
	 *
	 * @param   string  $type
	 * @return  string
	 */
	public static function typeLabel($type)
	{
		$type = trim((string) $type);

		if ($type === '')
		{
			return '';
		}

		$map = self::optionMap('bonustype');

		// The form select lower-cases its option values, so compare loosely.
		foreach ($map as $k => $label)
		{
			if (strtolower($k) === strtolower($type))
			{
				return $label;
			}
		}

		return ucfirst($type);
	}

	/**
	 * Valid (lower-cased) bonus-type keys, for server-side validation.
	 *
	 * @return  array
	 */
	public static function validTypeKeys()
	{
		$keys = array();

		foreach (array_keys(self::optionMap('bonustype')) as $k)
		{
			$keys[] = strtolower($k);
		}

		return $keys;
	}

	/* =============================================================
	 * Welcome packages (FXRate)
	 * -------------------------------------------------------------
	 * Tables: #__fec_bonus_packages, #__fec_bonus_package_legs
	 * (fxrate_tables.sql, schema 1). A leg is a real #__bonuses row
	 * (bid set) or a VIRTUAL leg whose terms live in the legs table
	 * (bid NULL). The package calculation (fec_fxrate.php) and the
	 * article parser (fec_fxrate_text.php) are standalone helpers in
	 * this same folder; they are loaded lazily and every public method
	 * here degrades to "no package" when the tables or the helpers are
	 * missing, so the dashboard keeps working on a half-installed site.
	 * ============================================================= */

	/** Types that open a package at position 1: the first-deposit match of the standard, high-roller and crypto tracks. */
	public static function openerTypes()
	{
		return array('su', 'hr', 'crypto');
	}

	/** Ladder types that can be legs of a welcome package: su/hr/crypto=1, 2d=2 ... 10d=10. */
	public static function ladderPosition($type)
	{
		$type = strtolower(trim((string) $type));

		if (in_array($type, self::openerTypes(), true))
		{
			return 1;
		}

		if (preg_match('/^([2-9]|10)d$/', $type, $m))
		{
			return (int) $m[1];
		}

		return 0;
	}

	/** Type key of a ladder position: 1 -> su, 2 -> 2d ... */
	public static function ladderType($position)
	{
		$position = (int) $position;

		return ($position === 1) ? 'su' : ($position . 'd');
	}

	/** Loads fec_fxrate.php (calculation) once. True when available. */
	public static function loadFxrate()
	{
		if (function_exists('fec_fxrate_package'))
		{
			return true;
		}

		$file = JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'fec_fxrate.php';

		if (is_readable($file))
		{
			require_once $file;
		}

		return function_exists('fec_fxrate_package');
	}

	/** Loads fec_fxrate_text.php (article parser) once. True when available. */
	public static function loadFxrateText()
	{
		if (function_exists('fec_fxrate_text_parse'))
		{
			return true;
		}

		$file = JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'fec_fxrate_text.php';

		if (is_readable($file))
		{
			require_once $file;
		}

		return function_exists('fec_fxrate_text_parse');
	}

	/**
	 * The package a bonus row belongs to, with its legs, or null.
	 *
	 * @param   integer  $bid
	 * @return  object|null  ->pkg (row) ->legs (array, see getPackageLegs)
	 */
	public static function getPackageForBid($bid)
	{
		$bid = (int) $bid;

		if ($bid <= 0)
		{
			return null;
		}

		try
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName('pkg_id'))
				->from($db->quoteName('#__fec_bonus_package_legs'))
				->where($db->quoteName('bid') . ' = ' . $bid);
			$db->setQuery($query, 0, 1);
			$pkgId = (int) $db->loadResult();
		}
		catch (Exception $e)
		{
			return null;
		}

		return ($pkgId > 0) ? self::getPackage($pkgId) : null;
	}

	/**
	 * One package with its legs, or null.
	 *
	 * @param   integer  $pkgId
	 * @return  object|null
	 */
	public static function getPackage($pkgId)
	{
		$pkgId = (int) $pkgId;

		if ($pkgId <= 0)
		{
			return null;
		}

		try
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true)
				->select('*')
				->from($db->quoteName('#__fec_bonus_packages'))
				->where($db->quoteName('pkg_id') . ' = ' . $pkgId);
			$db->setQuery($query);
			$pkg = $db->loadObject();
		}
		catch (Exception $e)
		{
			return null;
		}

		if (!$pkg)
		{
			return null;
		}

		$out       = new stdClass;
		$out->pkg  = $pkg;
		$out->legs = self::getPackageLegs($pkgId);

		return $out;
	}

	/**
	 * Legs of a package, ordered by position. Real legs carry the live
	 * terms of their #__bonuses row (bonus/max_bonus/...), so callers
	 * never need a second query; virtual legs carry their own columns.
	 *
	 * @param   integer  $pkgId
	 * @return  array  assoc rows: leg_id, pkg_id, position, bid, pct, cap,
	 *                 min_dep, max_dep, wr, wr_meth, max_cashout, max_cashout_kind,
	 *                 spins, spins_wr, code, source, row_label, row_type, row_status, row_currency
	 */
	public static function getPackageLegs($pkgId)
	{
		$pkgId = (int) $pkgId;

		if ($pkgId <= 0)
		{
			return array();
		}

		try
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true)
				->select(array(
					'l.leg_id', 'l.pkg_id', 'l.position', 'l.bid', 'l.spins', 'l.spins_wr', 'l.code', 'l.source',
					// virtual terms, or the row's when the leg is a real bonus
					'CASE WHEN l.bid IS NULL THEN l.pct     ELSE b.bonus     END AS pct',
					'CASE WHEN l.bid IS NULL THEN l.cap     ELSE b.max_bonus END AS cap',
					'CASE WHEN l.bid IS NULL THEN l.min_dep ELSE b.min_dep   END AS min_dep',
					'CASE WHEN l.bid IS NULL THEN l.max_dep ELSE b.max_dep   END AS max_dep',
					'CASE WHEN l.bid IS NULL THEN l.wr      ELSE b.wr        END AS wr',
					'CASE WHEN l.bid IS NULL THEN l.wr_meth ELSE b.wr_meth   END AS wr_meth',
					'CASE WHEN l.bid IS NULL THEN l.max_cashout      ELSE b.max_cashout      END AS max_cashout',
					'CASE WHEN l.bid IS NULL THEN l.max_cashout_kind ELSE b.max_cashout_kind END AS max_cashout_kind',
					'b.label AS row_label', 'b.type AS row_type', 'b.status AS row_status',
					'b.currency AS row_currency', 'b.code AS row_code', 'b.brand AS row_brand',
				))
				->from($db->quoteName('#__fec_bonus_package_legs', 'l'))
				->join('LEFT', $db->quoteName('#__bonuses', 'b') . ' ON b.bid = l.bid')
				->where('l.pkg_id = ' . $pkgId)
				->order('l.position ASC');
			$db->setQuery($query);

			return (array) $db->loadAssocList();
		}
		catch (Exception $e)
		{
			return array();
		}
	}

	/**
	 * Packages of one casino (newest first), each with its legs. Used
	 * by the form (select + panel prefill) and the JSON endpoint.
	 *
	 * @param   integer  $cid
	 * @return  array  of object ->pkg ->legs
	 */
	public static function getPackagesForCasino($cid)
	{
		$cid = (int) $cid;

		if ($cid <= 0)
		{
			return array();
		}

		try
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true)
				->select('*')
				->from($db->quoteName('#__fec_bonus_packages'))
				->where($db->quoteName('cid') . ' = ' . $cid)
				->order($db->quoteName('status') . ' ASC')   // active first
				->order($db->quoteName('pkg_id') . ' DESC');
			$db->setQuery($query);
			$rows = (array) $db->loadObjectList();
		}
		catch (Exception $e)
		{
			return array();
		}

		$out = array();

		foreach ($rows as $pkg)
		{
			$o       = new stdClass;
			$o->pkg  = $pkg;
			$o->legs = self::getPackageLegs($pkg->pkg_id);
			$out[]   = $o;
		}

		return $out;
	}

	/**
	 * Ladder-type bonus rows of a casino (su / hr / crypto, 2d ... 10d), for the
	 * "row" selects of the legs table. Active and pending first.
	 *
	 * @param   integer  $cid
	 * @return  array  assoc rows: bid, type, label, status, bonus, max_bonus,
	 *                 min_dep, max_dep, wr, wr_meth, currency, code
	 */
	public static function getLadderRows($cid)
	{
		$cid = (int) $cid;

		if ($cid <= 0)
		{
			return array();
		}

		$types = self::openerTypes();

		for ($i = 2; $i <= 10; $i++)
		{
			$types[] = $i . 'd';
		}

		$db = JFactory::getDbo();
		$in = array();

		foreach ($types as $t)
		{
			$in[] = $db->quote($t);
		}

		$query = $db->getQuery(true)
			->select($db->quoteName(array('bid', 'type', 'label', 'status', 'bonus', 'max_bonus', 'min_dep', 'max_dep', 'wr', 'wr_meth', 'max_cashout', 'max_cashout_kind', 'currency', 'code')))
			->from($db->quoteName('#__bonuses'))
			->where($db->quoteName('cid') . ' = ' . $cid)
			->where($db->quoteName('type') . ' IN (' . implode(',', $in) . ')')
			->order('FIELD(' . $db->quoteName('status') . ', ' . $db->quote('active') . ', ' . $db->quote('pending') . ', ' . $db->quote('expired') . ')')
			->order($db->quoteName('bid') . ' DESC');
		$db->setQuery($query, 0, 200);

		return (array) $db->loadAssocList();
	}

	/**
	 * Which bids of a list belong to a package, and whether that package
	 * still has virtual legs without terms. One query, for the list view.
	 *
	 * @param   array  $bids
	 * @return  array  bid => array('pkg_id', 'position', 'deposits', 'title', 'incomplete')
	 */
	public static function getPackageBadges(array $bids)
	{
		$ids = array();

		foreach ($bids as $b)
		{
			$b = (int) $b;

			if ($b > 0)
			{
				$ids[$b] = $b;
			}
		}

		if (!$ids)
		{
			return array();
		}

		try
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true)
				->select(array(
					'l.bid', 'l.position', 'p.pkg_id', 'p.deposits', 'p.title', 'p.status',
					'(SELECT COUNT(*) FROM ' . $db->quoteName('#__fec_bonus_package_legs', 'v')
						. ' WHERE v.pkg_id = p.pkg_id AND v.bid IS NULL AND (v.pct IS NULL OR v.cap IS NULL)) AS missing',
				))
				->from($db->quoteName('#__fec_bonus_package_legs', 'l'))
				->join('INNER', $db->quoteName('#__fec_bonus_packages', 'p') . ' ON p.pkg_id = l.pkg_id')
				->where('l.bid IN (' . implode(',', $ids) . ')');
			$db->setQuery($query);
			$rows = (array) $db->loadAssocList();
		}
		catch (Exception $e)
		{
			return array();
		}

		$out = array();

		foreach ($rows as $r)
		{
			$out[(int) $r['bid']] = array(
				'pkg_id'     => (int) $r['pkg_id'],
				'position'   => (int) $r['position'],
				'deposits'   => (int) $r['deposits'],
				'title'      => (string) $r['title'],
				'status'     => (string) $r['status'],
				'incomplete' => ((int) $r['missing'] > 0),
			);
		}

		return $out;
	}

	/**
	 * Creates or updates a package and its legs in one go.
	 *
	 * $data keys (all optional on update): title, headline, currency,
	 *   deposits, spins_total, window_days, sequential, status, notes,
	 *   alt_of (pkg_id of the package this one is an alternative to; 0 = none)
	 * $legs: position => array(bid|null, pct, cap, min_dep, max_dep, wr,
	 *   wr_meth, max_cashout, max_cashout_kind, spins, spins_wr, code). A leg with a bid takes its terms from the
	 *   row (the virtual columns are nulled); a leg without a bid keeps
	 *   the posted terms (empty -> NULL). spins and spins_wr stay on the
	 *   leg either way (the row has no column for them). Positions not listed are
	 *   removed only when $pruneLegs is true (the form always posts the
	 *   full ladder, the auto path never prunes).
	 *
	 * @param   integer  $pkgId       0 = create
	 * @param   integer  $cid
	 * @param   array    $data
	 * @param   array    $legs
	 * @param   string   $source      rows | text | editor
	 * @param   boolean  $pruneLegs
	 * @return  integer  pkg_id, or 0 on failure
	 */
	public static function savePackage($pkgId, $cid, array $data, array $legs, $source = 'editor', $pruneLegs = true)
	{
		$pkgId = (int) $pkgId;
		$cid   = (int) $cid;
		$db    = JFactory::getDbo();
		$now   = date('Y-m-d H:i:s');

		if ($cid <= 0)
		{
			return 0;
		}

		$row = new stdClass;

		if (isset($data['title']))       { $row->title       = self::clip($data['title'], 120, 'Welcome Package'); }
		if (isset($data['headline']))    { $row->headline    = self::clip($data['headline'], 160, ''); }
		if (isset($data['currency']))    { $row->currency    = self::clip(strtolower($data['currency']), 10, ''); }
		if (isset($data['deposits']))    { $row->deposits    = max(0, min(10, (int) $data['deposits'])); }
		if (isset($data['spins_total'])) { $row->spins_total = max(0, (int) $data['spins_total']); }
		if (array_key_exists('window_days', $data))
		{
			$w = trim((string) $data['window_days']);
			$row->window_days = ($w === '' || !is_numeric($w)) ? null : max(0, (int) $w);
		}
		if (isset($data['sequential']))  { $row->sequential  = ((int) $data['sequential']) ? 1 : 0; }
		if (isset($data['status']) && in_array($data['status'], array('active', 'paused', 'retired'), true))
		{
			$row->status = $data['status'];
		}
		if (isset($data['notes']))       { $row->notes       = (string) $data['notes']; }
		if (array_key_exists('opener_bid', $data))
		{
			$row->opener_bid = ((int) $data['opener_bid'] > 0) ? (int) $data['opener_bid'] : null;
		}
		if (array_key_exists('alt_of', $data))
		{
			// the package this one is an alternative to: another package of the same
			// casino, never itself; anything else clears the link
			$alt = (int) $data['alt_of'];
			$row->alt_of = null;
			if ($alt > 0 && $alt !== $pkgId)
			{
				$other = self::getPackage($alt);
				if ($other && (int) $other->pkg->cid === $cid)
				{
					$row->alt_of = $alt;
				}
			}
		}

		$row->modified    = $now;
		$row->modified_by = (int) JFactory::getUser()->id;

		try
		{
			if ($pkgId > 0)
			{
				$row->pkg_id = $pkgId;
				$db->updateObject('#__fec_bonus_packages', $row, 'pkg_id', true);
			}
			else
			{
				$row->cid     = $cid;
				$row->source  = in_array($source, array('rows', 'text', 'editor'), true) ? $source : 'editor';
				$row->created = $now;

				if (!isset($row->title))    { $row->title    = 'Welcome Package'; }
				if (!isset($row->headline)) { $row->headline = ''; }
				if (!isset($row->currency)) { $row->currency = ''; }
				if (!isset($row->deposits)) { $row->deposits = count($legs); }

				$db->insertObject('#__fec_bonus_packages', $row);
				$pkgId = (int) $db->insertid();
			}
		}
		catch (Exception $e)
		{
			error_log('com_bonuses savePackage: ' . $e->getMessage());

			return 0;
		}

		if ($pkgId <= 0)
		{
			return 0;
		}

		// ---- legs -------------------------------------------------------
		$existing = array();

		foreach (self::getPackageLegs($pkgId) as $l)
		{
			$existing[(int) $l['position']] = $l;
		}

		$seen = array();

		foreach ($legs as $position => $leg)
		{
			$position = (int) $position;

			if ($position < 1 || $position > 10)
			{
				continue;
			}

			$seen[$position] = true;
			$bid = isset($leg['bid']) ? (int) $leg['bid'] : 0;

			$l           = new stdClass;
			$l->pkg_id   = $pkgId;
			$l->position = $position;
			$l->bid      = ($bid > 0) ? $bid : null;
			$l->pct      = ($bid > 0) ? null : self::decOrNull(isset($leg['pct']) ? $leg['pct'] : null);
			$l->cap      = ($bid > 0) ? null : self::decOrNull(isset($leg['cap']) ? $leg['cap'] : null);
			$l->min_dep  = ($bid > 0) ? null : self::decOrNull(isset($leg['min_dep']) ? $leg['min_dep'] : null);
			$l->max_dep  = ($bid > 0) ? null : self::decOrNull(isset($leg['max_dep']) ? $leg['max_dep'] : null);
			$l->wr       = ($bid > 0) ? null : self::decOrNull(isset($leg['wr']) ? $leg['wr'] : null);
			$wm          = isset($leg['wr_meth']) ? strtolower(trim((string) $leg['wr_meth'])) : '';
			$l->wr_meth  = ($bid > 0 || !in_array($wm, array('b', 'bd'), true)) ? null : $wm;
			// schema 2: the withdrawal cap of a virtual leg; the unit only with a value
			$mc          = ($bid > 0) ? null : self::decOrNull(isset($leg['max_cashout']) ? $leg['max_cashout'] : null);
			$mk          = isset($leg['max_cashout_kind']) ? strtolower(trim((string) $leg['max_cashout_kind'])) : '';
			$l->max_cashout      = ($mc !== null && $mc > 0) ? $mc : null;
			$l->max_cashout_kind = ($l->max_cashout !== null && in_array($mk, array('abs', 'x_dep', 'x_bonus'), true)) ? $mk : null;
			$l->spins    = isset($leg['spins']) ? max(0, (int) $leg['spins']) : 0;
			// schema 3: the spins' own wagering, real legs too; nothing without spins
			$sw          = self::decOrNull(isset($leg['spins_wr']) ? $leg['spins_wr'] : null);
			$l->spins_wr = ($l->spins > 0 && $sw !== null && $sw >= 0) ? $sw : null;
			$l->code     = ($bid > 0) ? '' : self::clip(isset($leg['code']) ? $leg['code'] : '', 60, '');
			if ($bid > 0)
			{
				$l->source = 'row';
			}
			elseif (isset($leg['source']) && in_array($leg['source'], array('text', 'editor'), true))
			{
				$l->source = $leg['source'];
			}
			else
			{
				$l->source = ($source === 'text') ? 'text' : 'editor';
			}

			try
			{
				if (isset($existing[$position]))
				{
					$l->leg_id = (int) $existing[$position]['leg_id'];
					$db->updateObject('#__fec_bonus_package_legs', $l, 'leg_id', true);
				}
				else
				{
					$db->insertObject('#__fec_bonus_package_legs', $l);
				}
			}
			catch (Exception $e)
			{
				error_log('com_bonuses savePackage leg ' . $position . ': ' . $e->getMessage());
			}
		}

		if ($pruneLegs)
		{
			foreach ($existing as $position => $l)
			{
				if (!isset($seen[$position]))
				{
					try
					{
						$query = $db->getQuery(true)
							->delete($db->quoteName('#__fec_bonus_package_legs'))
							->where($db->quoteName('leg_id') . ' = ' . (int) $l['leg_id']);
						$db->setQuery($query);
						$db->execute();
					}
					catch (Exception $e)
					{
						error_log('com_bonuses savePackage prune: ' . $e->getMessage());
					}
				}
			}
		}

		return $pkgId;
	}

	/**
	 * Attaches a bonus row to a package at a position: the leg at that
	 * position becomes the row (virtual terms dropped), creating the leg
	 * if the ladder is shorter. The bid is first detached from any other
	 * package (a row is a leg of one package at most).
	 *
	 * @param   integer  $pkgId
	 * @param   integer  $bid
	 * @param   integer  $position
	 * @return  boolean
	 */
	public static function attachBid($pkgId, $bid, $position)
	{
		$pkgId    = (int) $pkgId;
		$bid      = (int) $bid;
		$position = (int) $position;

		if ($pkgId <= 0 || $bid <= 0 || $position < 1)
		{
			return false;
		}

		self::detachBid($bid, false, $pkgId);

		$db = JFactory::getDbo();

		try
		{
			$legs = self::getPackageLegs($pkgId);
			$hit  = null;

			foreach ($legs as $l)
			{
				if ((int) $l['position'] === $position)
				{
					$hit = $l;
					break;
				}
			}

			$row = new stdClass;
			$row->pkg_id   = $pkgId;
			$row->position = $position;
			$row->bid      = $bid;
			$row->pct      = null;
			$row->cap      = null;
			$row->min_dep  = null;
			$row->max_dep  = null;
			$row->wr       = null;
			$row->wr_meth  = null;
			$row->max_cashout      = null;
			$row->max_cashout_kind = null;
			$row->code     = '';
			$row->source   = 'row';

			if ($hit)
			{
				$row->leg_id = (int) $hit['leg_id'];
				$db->updateObject('#__fec_bonus_package_legs', $row, 'leg_id', true);
			}
			else
			{
				$row->spins = 0;
				$db->insertObject('#__fec_bonus_package_legs', $row);

				// the ladder grew: keep deposits in step
				$q = $db->getQuery(true)
					->update($db->quoteName('#__fec_bonus_packages'))
					->set($db->quoteName('deposits') . ' = GREATEST(' . $db->quoteName('deposits') . ', ' . $position . ')')
					->set($db->quoteName('modified') . ' = ' . $db->quote(date('Y-m-d H:i:s')))
					->where($db->quoteName('pkg_id') . ' = ' . $pkgId);
				$db->setQuery($q);
				$db->execute();
			}

			return true;
		}
		catch (Exception $e)
		{
			error_log('com_bonuses attachBid: ' . $e->getMessage());

			return false;
		}
	}

	/**
	 * Detaches a bonus row from its package(s). The leg does not vanish:
	 * it turns virtual, and with $copyTerms the row's live terms are
	 * copied into it first so the package keeps its FXRate price (this
	 * is what remove() wants). $exceptPkg leaves that package alone.
	 *
	 * @param   integer  $bid
	 * @param   boolean  $copyTerms
	 * @param   integer  $exceptPkg
	 * @return  integer  legs changed
	 */
	public static function detachBid($bid, $copyTerms = true, $exceptPkg = 0)
	{
		$bid = (int) $bid;

		if ($bid <= 0)
		{
			return 0;
		}

		$db = JFactory::getDbo();
		$n  = 0;

		try
		{
			$query = $db->getQuery(true)
				->select(array('l.leg_id', 'l.pkg_id', 'b.bonus', 'b.max_bonus', 'b.min_dep', 'b.max_dep', 'b.wr', 'b.wr_meth', 'b.max_cashout', 'b.max_cashout_kind', 'b.code'))
				->from($db->quoteName('#__fec_bonus_package_legs', 'l'))
				->join('LEFT', $db->quoteName('#__bonuses', 'b') . ' ON b.bid = l.bid')
				->where('l.bid = ' . $bid);
			$db->setQuery($query);
			$legs = (array) $db->loadAssocList();

			foreach ($legs as $l)
			{
				if ($exceptPkg > 0 && (int) $l['pkg_id'] === (int) $exceptPkg)
				{
					continue;
				}

				$row         = new stdClass;
				$row->leg_id = (int) $l['leg_id'];
				$row->bid    = null;
				$row->source = 'editor';

				if ($copyTerms)
				{
					$row->pct     = self::decOrNull($l['bonus']);
					$row->cap     = self::decOrNull($l['max_bonus']);
					$row->min_dep = self::decOrNull($l['min_dep']);
					$row->max_dep = self::decOrNull($l['max_dep']);
					$row->wr      = self::decOrNull($l['wr']);
					$row->wr_meth = in_array((string) $l['wr_meth'], array('b', 'bd'), true) ? $l['wr_meth'] : null;
					$mc = self::decOrNull($l['max_cashout']);
					$row->max_cashout      = ($mc !== null && $mc > 0) ? $mc : null;
					$row->max_cashout_kind = ($row->max_cashout !== null && in_array((string) $l['max_cashout_kind'], array('abs', 'x_dep', 'x_bonus'), true)) ? $l['max_cashout_kind'] : null;
					$row->code    = self::clip($l['code'], 60, '');
				}

				$db->updateObject('#__fec_bonus_package_legs', $row, 'leg_id', true);
				$n++;
			}
		}
		catch (Exception $e)
		{
			error_log('com_bonuses detachBid: ' . $e->getMessage());
		}

		return $n;
	}

	/**
	 * What the article of a bonus states about its package, through
	 * fec_fxrate_text_parse(). Null when the parser is not installed.
	 *
	 * @param   string      $html   additional_infos
	 * @param   float|null  $ownPct the row's own match % (so its own
	 *                              headline is not mistaken for the package's)
	 * @param   float|null  $ownCap
	 * @return  array|null  parser result + 'summary' (human line)
	 */
	public static function parseArticleForPackage($html, $ownPct = null, $ownCap = null)
	{
		if (!self::loadFxrateText())
		{
			return null;
		}

		$t = fec_fxrate_text_parse((string) $html, array('own' => array('pct' => $ownPct, 'cap' => $ownCap)));
		$t['summary'] = fec_fxrate_text_describe($t);

		return $t;
	}

	/**
	 * FXRate of a package: the four-number line and the per-leg lines,
	 * computed from real rows + virtual legs. Null when fec_fxrate.php
	 * is not installed or the package is unknown.
	 *
	 * @param   integer  $pkgId
	 * @return  array|null  line, priced (bool), legs (text lines), symbol
	 */
	public static function packageFxrate($pkgId)
	{
		if (!self::loadFxrate())
		{
			return null;
		}

		$p = self::getPackage($pkgId);

		if (!$p)
		{
			return null;
		}

		$map   = self::optionMap('currency');
		$cmap  = array();

		foreach ($map as $k => $v)
		{
			$cmap[strtolower((string) $k)] = html_entity_decode((string) $v, ENT_QUOTES, 'UTF-8');
		}

		$legs = array();

		foreach ($p->legs as $l)
		{
			$cur = ($l['bid'] !== null && $l['row_currency'] !== null) ? (string) $l['row_currency'] : (string) $p->pkg->currency;
			$row = array(
				'bid'       => (int) $l['bid'],
				'cid'       => (int) $p->pkg->cid,
				'type'      => self::ladderType($l['position']),
				'bonus'     => $l['pct'],
				'max_bonus' => $l['cap'],
				'min_dep'   => $l['min_dep'],
				'max_dep'   => $l['max_dep'],
				'wr'        => $l['wr'],
				'wr_meth'   => $l['wr_meth'],
				'currency'  => $cur,
				'code'      => ($l['bid'] !== null) ? (string) $l['row_code'] : (string) $l['code'],
				'spins'     => (int) $l['spins'],
				'spins_wr'  => isset($l['spins_wr']) ? $l['spins_wr'] : null,
				'max_cashout'      => isset($l['max_cashout']) ? $l['max_cashout'] : null,
				'max_cashout_kind' => isset($l['max_cashout_kind']) ? (string) $l['max_cashout_kind'] : '',
			);
			$legs[] = fec_fxrate_leg($row, $cmap);
		}

		$pkg = fec_fxrate_package($legs, array('spins' => (int) $p->pkg->spins_total));
		$sym = ($pkg['symbol'] === 'MIXED') ? '$' : $pkg['symbol'];

		$lines = array();

		foreach ($pkg['legs'] as $leg)
		{
			$lines[] = fec_fxrate_leg_line($leg, $sym);
		}

		return array(
			'line'   => fec_fxrate_summary_line($pkg, $sym),
			'priced' => ($pkg['effective_pct'] !== null),
			'legs'   => $lines,
			'symbol' => $sym,
			'flags'  => $pkg['flags'],
		);
	}

	/**
	 * Everything the Welcome package section needs for one casino: its
	 * packages (legs + FXRate line), the ladder-type rows that can be
	 * legs, and the package the edited bonus belongs to. Served by
	 * actions.packageoptions and used by the add view for the prefill.
	 *
	 * @param   integer  $cid
	 * @param   integer  $exclude  bid being edited (0 in add mode)
	 * @return  array
	 */
	public static function packagePayload($cid, $exclude = 0)
	{
		$cid     = (int) $cid;
		$exclude = (int) $exclude;
		$out     = array('packages' => array(), 'rows' => array(), 'this_pkg_id' => 0);

		foreach (self::getLadderRows($cid) as $r)
		{
			$text = '#' . (int) $r['bid'] . ' ' . self::typeLabel($r['type']);

			if ($r['bonus'] !== null && $r['bonus'] !== '')
			{
				$text .= ' ' . $r['bonus'] . '%';

				if ($r['max_bonus'] !== null && $r['max_bonus'] !== '' && (float) $r['max_bonus'] > 0)
				{
					$text .= ' up to ' . $r['max_bonus'];
				}
			}

			if ((string) $r['code'] !== '')
			{
				// the middle dot as an escape: the literal byte pair did not survive some transfers
				$text .= ' ' . "\xC2\xB7" . ' ' . $r['code'];
			}

			$text .= ' (' . $r['status'] . ')';

			$out['rows'][] = array(
				'bid'       => (int) $r['bid'],
				'type'      => (string) $r['type'],
				'position'  => self::ladderPosition($r['type']),
				'text'      => $text,
				'pct'       => $r['bonus'],
				'cap'       => $r['max_bonus'],
				'min_dep'   => $r['min_dep'],
				'max_dep'   => $r['max_dep'],
				'wr'        => $r['wr'],
				'wr_meth'   => (string) $r['wr_meth'],
				'max_cashout'      => isset($r['max_cashout']) ? $r['max_cashout'] : null,
				'max_cashout_kind' => isset($r['max_cashout_kind']) ? (string) $r['max_cashout_kind'] : '',
				'currency'  => (string) $r['currency'],
				'code'      => (string) $r['code'],
				'status'    => (string) $r['status'],
				'is_this'   => ($exclude > 0 && (int) $r['bid'] === $exclude),
			);
		}

		foreach (self::getPackagesForCasino($cid) as $p)
		{
			$legs = array();

			foreach ($p->legs as $l)
			{
				$legs[] = array(
					'position' => (int) $l['position'],
					'bid'      => ($l['bid'] === null) ? 0 : (int) $l['bid'],
					'pct'      => $l['pct'],
					'cap'      => $l['cap'],
					'min_dep'  => $l['min_dep'],
					'max_dep'  => $l['max_dep'],
					'wr'       => $l['wr'],
					'wr_meth'  => (string) $l['wr_meth'],
					'max_cashout'      => isset($l['max_cashout']) ? $l['max_cashout'] : null,
					'max_cashout_kind' => isset($l['max_cashout_kind']) ? (string) $l['max_cashout_kind'] : '',
					'spins'    => (int) $l['spins'],
					'spins_wr' => isset($l['spins_wr']) ? $l['spins_wr'] : null,
					'code'     => ($l['bid'] === null) ? (string) $l['code'] : (string) $l['row_code'],
					'source'   => (string) $l['source'],
					'row_status' => (string) $l['row_status'],
				);

				if ($exclude > 0 && (int) $l['bid'] === $exclude)
				{
					$out['this_pkg_id'] = (int) $p->pkg->pkg_id;
				}
			}

			$fx = self::packageFxrate($p->pkg->pkg_id);

			$out['packages'][] = array(
				'pkg_id'      => (int) $p->pkg->pkg_id,
				'title'       => (string) $p->pkg->title,
				'headline'    => (string) $p->pkg->headline,
				'currency'    => (string) $p->pkg->currency,
				'deposits'    => (int) $p->pkg->deposits,
				'spins_total' => (int) $p->pkg->spins_total,
				'window_days' => ($p->pkg->window_days === null) ? '' : (int) $p->pkg->window_days,
				'sequential'  => (int) $p->pkg->sequential,
				'status'      => (string) $p->pkg->status,
				'source'      => (string) $p->pkg->source,
				'notes'       => (string) $p->pkg->notes,
				'opener_bid'  => ($p->pkg->opener_bid === null) ? 0 : (int) $p->pkg->opener_bid,
				'alt_of'      => (isset($p->pkg->alt_of) && $p->pkg->alt_of !== null) ? (int) $p->pkg->alt_of : 0,
				'legs'        => $legs,
				'fxrate'      => $fx ? $fx['line'] : '',
				'priced'      => $fx ? (bool) $fx['priced'] : false,
			);
		}

		return $out;
	}

	/** Trimmed string clipped to $max chars, $default when empty. */
	private static function clip($v, $max, $default)
	{
		$v = trim((string) $v);

		if ($v === '')
		{
			return $default;
		}

		if (function_exists('mb_substr'))
		{
			return mb_substr($v, 0, $max, 'UTF-8');
		}

		return substr($v, 0, $max);
	}

	/** Empty / non-numeric -> null, else the number (accepts "1,000"). */
	private static function decOrNull($v)
	{
		if ($v === null)
		{
			return null;
		}

		$v = str_replace(array(',', ' '), '', trim((string) $v));

		if ($v === '' || !is_numeric($v))
		{
			return null;
		}

		return $v + 0;
	}
}