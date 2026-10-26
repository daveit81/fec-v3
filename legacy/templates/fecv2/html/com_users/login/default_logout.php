<?php
/**
 * FEC v2 — Override pagina di Logout (com_users) — Joomla 2.5
 *
 * Posizione:  templates/<tuo_template>/html/com_users/login/default_logout.php
 * Caricare via FTP/SFTP come file .php grezzo.
 */

defined('_JEXEC') or die;

// CSS dedicato: file in /css/ nella root del sito.
JFactory::getDocument()->addStyleSheet(JURI::base(true) . '/css/fec-login.min.css');

// IDENTICO al core: il valore di "return" va codificato in base64.
$return = base64_encode($this->params->get('logout_redirect_url', $this->form->getValue('return')));

$pageTitle = $this->params->get('show_page_heading')
	? $this->escape($this->params->get('page_heading'))
	: JText::_('JLOGOUT');

$descShow = ($this->params->get('logoutdescription_show') == 1);
$descText = $this->params->get('logout_description');
$logoImg  = $this->params->get('logout_image');
?>
<div class="fec-auth<?php echo $this->pageclass_sfx; ?>">
	<div class="fec-auth__shell">

		<div class="fec-auth__card fec-card fec-auth__logout">

			<div class="fec-auth__head">
				<span class="fec-auth__mark" aria-hidden="true">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none"
						stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round">
						<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
						<path d="M16 17l5-5-5-5"></path>
						<path d="M21 12H9"></path>
					</svg>
				</span>
				<h1 class="fec-auth__title"><?php echo $pageTitle; ?></h1>
			</div>

			<?php if (($descShow && str_replace(' ', '', $descText) != '') || $logoImg != '') : ?>
				<div class="fec-auth__desc">
					<?php if ($descShow) : ?>
						<?php echo $descText; ?>
					<?php endif; ?>
					<?php if ($logoImg != '') : ?>
						<img class="fec-auth__img"
							src="<?php echo $this->escape($logoImg); ?>"
							alt="<?php echo JText::_('COM_USER_LOGOUT_IMAGE_ALT'); ?>" />
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<form action="<?php echo JRoute::_('index.php?option=com_users&task=user.logout'); ?>"
				method="post" id="login-form" class="fec-auth__form">

				<button type="submit" class="fec-btn fec-btn--primary fec-auth__submit">
					<?php echo JText::_('JLOGOUT'); ?>
				</button>

				<input type="hidden" name="return" value="<?php echo $return; ?>" />
				<?php echo JHtml::_('form.token'); ?>
			</form>

		</div>

	</div>
</div>