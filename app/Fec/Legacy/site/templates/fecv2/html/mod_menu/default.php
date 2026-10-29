<?php
/**
 * @package		Joomla.Site
 * @subpackage	mod_menu
 * @copyright	Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access.
defined('_JEXEC') or die;
$app  = JFactory::getApplication();
$user = JFactory::getUser();
// Note. It is important to remove spaces between elements.


/** Return URL: torna alla pagina corrente dopo login/logout */
$return = base64_encode(JUri::getInstance()->toString());

/** Logout URL. Nessun link di login pubblico: il blocco utente in testa al
 *  drawer esiste solo per chi e' loggato (desk); i visitatori non vedono
 *  nulla, nemmeno il <li> (2026-10-04). */
$logoutUrl = JRoute::_('index.php?option=com_users&task=user.logout&return=' . $return . '&' . JSession::getFormToken() . '=1', false);

/** Display name + initial (solo se loggato) */
$displayName = trim($user->name);
if ($displayName === '') $displayName = trim($user->username);
$initial = $displayName ? strtoupper(substr($displayName, 0, 1)) : 'U';

?>

<ul class="menu fec-menu<?php echo $class_sfx;?>"<?php
	$tag = '';
	if ($params->get('tag_id')!=NULL) {
		$tag = $params->get('tag_id').'';
		echo ' id="'.$tag.'"';
	}
?>>
<?php if (!$user->guest): ?>
<li class="fec-navuser">

    <div class="fec-navuser__wrap" data-fec-navuser>
      <button class="fec-navuser__btn" type="button" aria-haspopup="menu" aria-expanded="false">
        <span class="fec-navuser__avatar" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="fec-navuser__name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="fec-navuser__caret" aria-hidden="true"></span>
      </button>

      <div class="fec-navuser__dd" role="menu" aria-label="User menu">
        <div class="fec-navuser__head">
          <div class="fec-navuser__title"><?php echo htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="fec-navuser__sub"><?php echo htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <div class="fec-navuser__items">
          <a class="fec-navuser__item" role="menuitem"
             href="<?php echo JRoute::_('index.php?option=com_users&view=profile', false); ?>">Profile</a>

          <a class="fec-navuser__item" role="menuitem"
             href="<?php echo JRoute::_('index.php?option=com_users&view=profile&layout=edit', false); ?>">Account settings</a>

          <div class="fec-navuser__sep" aria-hidden="true"></div>

          <a class="fec-navuser__item fec-dd__item--danger" role="menuitem"
             href="<?php echo $logoutUrl; ?>">Logout</a>
        </div>
      </div>
    </div>

</li>
<?php endif; ?>


<?php
foreach ($list as $i => &$item) :
	$class = 'item-'.$item->id;
	if ($item->id == $active_id) {
		$class .= ' current';
	}

	if (in_array($item->id, $path)) {
		$class .= ' active';
	}
	elseif ($item->type == 'alias') {
		$aliasToId = $item->params->get('aliasoptions');
		if (count($path) > 0 && $aliasToId == $path[count($path)-1]) {
			$class .= ' active';
		}
		elseif (in_array($aliasToId, $path)) {
			$class .= ' alias-parent-active';
		}
	}

	if ($item->deeper) {
		$class .= ' deeper';
	}

	if ($item->parent) {
		$class .= ' parent';
	}

	if (!empty($class)) {
		$class = ' class="'.trim($class) .'"';
	}

	echo '<li'.$class.'>';

	// Render the menu item.
	switch ($item->type) :
		case 'separator':
		case 'url':
		case 'component':
			require JModuleHelper::getLayoutPath('mod_menu', 'default_'.$item->type);
			break;

		default:
			require JModuleHelper::getLayoutPath('mod_menu', 'default_url');
			break;
	endswitch;

	// The next item is deeper.
	if ($item->deeper) {
		echo '<ul>';
	}
	// The next item is shallower.
	elseif ($item->shallower) {
		echo '</li>';
		echo str_repeat('</ul></li>', $item->level_diff);
	}
	// The next item is on the same level.
	else {
		echo '</li>';
	}
endforeach;
?></ul>