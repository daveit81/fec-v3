<?php
/**
 * FEC v2 — Override pagina di Login (com_users) — Joomla 2.5
 * Allineato 1:1 alla logica PHP del default_login.php del sito.
 *
 * Posizione:  templates/<tuo_template>/html/com_users/login/default_login.php
 * Caricare via FTP/SFTP come file .php grezzo.
 *
 * NOTA: l'errore "invalid token" NON dipende da questo file ma dalla
 * cache (vedi istruzioni). Questo file riproduce la stessa logica del core.
 */

defined('_JEXEC') or die;

JHtml::_('behavior.keepalive');

// CSS dedicato: file in /css/ nella root del sito.
JFactory::getDocument()->addStyleSheet(JURI::base(true) . '/css/fec-login.min.css');

$usersConfig = JComponentHelper::getParams('com_users');
$canRegister = $usersConfig->get('allowUserRegistration');
$rememberOn  = JPluginHelper::isEnabled('system', 'remember');

// IDENTICO al core: il valore di "return" va codificato in base64.
$return = base64_encode($this->params->get('login_redirect_url', $this->form->getValue('return')));

$pageTitle = $this->params->get('show_page_heading')
	? $this->escape($this->params->get('page_heading'))
	: JText::_('JLOGIN');

$descShow = ($this->params->get('logindescription_show') == 1);
$descText = $this->params->get('login_description');
$loginImg = $this->params->get('login_image');
?>
<div class="fec-auth<?php echo $this->pageclass_sfx; ?>">
	<div class="fec-auth__shell">

		<div class="fec-auth__card fec-card">

			<div class="fec-auth__head">
				<span class="fec-auth__mark" aria-hidden="true">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none"
						stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round">
						<rect x="4" y="11" width="16" height="9" rx="2"></rect>
						<path d="M8 11V8a4 4 0 0 1 8 0v3"></path>
					</svg>
				</span>
				<h1 class="fec-auth__title"><?php echo $pageTitle; ?></h1>
			</div>

			<?php if (($descShow && str_replace(' ', '', $descText) != '') || $loginImg != '') : ?>
				<div class="fec-auth__desc">
					<?php if ($descShow) : ?>
						<?php echo $descText; ?>
					<?php endif; ?>
					<?php if ($loginImg != '') : ?>
						<img class="fec-auth__img"
							src="<?php echo $this->escape($loginImg); ?>"
							alt="<?php echo JText::_('COM_USER_LOGIN_IMAGE_ALT'); ?>" />
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<form action="<?php echo JRoute::_('index.php?option=com_users&task=user.login'); ?>"
				method="post" id="login-form" class="fec-auth__form">

				<div class="fec-auth__field">
					<label class="fec-fgrp__title fec-auth__label" for="username">
						<?php echo JText::_('COM_USERS_LOGIN_USERNAME_LABEL'); ?>
					</label>
					<input id="username" type="text" name="username"
						class="fec-auth__input inputbox"
						autocomplete="username" autocapitalize="off"
						autocorrect="off" required />
				</div>

				<div class="fec-auth__field">
					<label class="fec-fgrp__title fec-auth__label" for="passwd">
						Password
					</label>
					<div class="fec-auth__inputwrap">
						<input id="passwd" type="password" name="password"
							class="fec-auth__input inputbox"
							autocomplete="current-password" required />
						<button type="button" class="fec-auth__reveal" data-reveal
							aria-pressed="false"
							aria-label="<?php echo JText::_('COM_USERS_LOGIN_PASSWORD_LABEL'); ?>">
							<svg class="fec-auth__eye" width="20" height="20" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2"
								stroke-linecap="round" stroke-linejoin="round">
								<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path>
								<circle cx="12" cy="12" r="3"></circle>
							</svg>
							<svg class="fec-auth__eye-off" width="20" height="20" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2"
								stroke-linecap="round" stroke-linejoin="round">
								<path d="M3 3l18 18"></path>
								<path d="M10.6 10.6a3 3 0 0 0 4.2 4.2"></path>
								<path d="M9.4 4.3A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.4 4.3"></path>
								<path d="M6.1 6.1A18 18 0 0 0 2 12s3.5 7 10 7a10.6 10.6 0 0 0 4.6-1"></path>
							</svg>
						</button>
					</div>
				</div>

				<div class="fec-auth__row">
					<?php if ($rememberOn) : ?>
						<label class="fec-switch fec-auth__remember" id="remember-lbl" for="remember">
							<input id="remember" type="checkbox" name="remember"
								class="inputbox" value="yes" />
							<span class="fec-switch__ui" aria-hidden="true"></span>
							<span class="fec-auth__remember-txt">
								<?php echo JText::_('JGLOBAL_REMEMBER_ME'); ?>
							</span>
						</label>
					<?php else : ?>
						<span></span>
					<?php endif; ?>
					<a class="fec-auth__forgot"
						href="<?php echo JRoute::_('index.php?option=com_users&view=reset'); ?>">
						<?php echo JText::_('COM_USERS_LOGIN_RESET'); ?>
					</a>
				</div>

				<button type="submit" class="fec-btn fec-btn--primary fec-auth__submit">
					<?php echo JText::_('JLOGIN'); ?>
				</button>

				<input type="hidden" name="return" value="<?php echo $return; ?>" />
				<?php echo JHtml::_('form.token'); ?>
			</form>

		</div>

		<div class="fec-auth__foot">
			<a class="fec-auth__footlink"
				href="<?php echo JRoute::_('index.php?option=com_users&view=remind'); ?>">
				<?php echo JText::_('COM_USERS_LOGIN_REMIND'); ?>
			</a>
			<?php if ($canRegister) : ?>
				<span class="fec-auth__footsep" aria-hidden="true">&middot;</span>
				<a class="fec-auth__footlink"
					href="<?php echo JRoute::_('index.php?option=com_users&view=registration'); ?>">
					<?php echo JText::_('COM_USERS_LOGIN_REGISTER'); ?>
				</a>
			<?php endif; ?>
		</div>

	</div>
</div>

<script>
(function () {
	var btn = document.querySelector('#login-form [data-reveal]');
	if (!btn) { return; }
	var input = document.getElementById('passwd');
	btn.addEventListener('click', function () {
		var show = input.type === 'password';
		input.type = show ? 'text' : 'password';
		btn.setAttribute('aria-pressed', show ? 'true' : 'false');
		btn.classList.toggle('is-on', show);
		input.focus();
	});
})();
</script>