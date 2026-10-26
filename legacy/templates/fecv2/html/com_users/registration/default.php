<?php
/**
 * FEC v2 — Override pagina di Registrazione (com_users) — Joomla 2.5
 * "Create an account"
 *
 * Posizione:  templates/<tuo_template>/html/com_users/registration/default.php
 * Caricare via FTP/SFTP come file .php grezzo.
 *
 * I campi sono generati da JForm (ciclo sui fieldset): NON vanno scritti
 * a mano, altrimenti si rompono captcha, validazione e campi dei plugin.
 * Lo stile FEC e' applicato via CSS (fec-login.min.css).
 */

defined('_JEXEC') or die;

JHtml::_('behavior.keepalive');
JHtml::_('behavior.tooltip');
JHtml::_('behavior.formvalidation');

// CSS dedicato: file in /css/ nella root del sito.
JFactory::getDocument()->addStyleSheet(JURI::base(true) . '/css/fec-login.min.css');

$pageTitle = $this->params->get('show_page_heading')
	? $this->escape($this->params->get('page_heading'))
	: JText::_('JREGISTER');
?>
<div class="fec-auth<?php echo $this->pageclass_sfx; ?>">
	<div class="fec-auth__shell fec-auth__shell--wide">

		<div class="fec-auth__card fec-card">

			<div class="fec-auth__head">
				<span class="fec-auth__mark" aria-hidden="true">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none"
						stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round">
						<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
						<circle cx="9" cy="7" r="4"></circle>
						<path d="M19 8v6"></path>
						<path d="M22 11h-6"></path>
					</svg>
				</span>
				<h1 class="fec-auth__title"><?php echo $pageTitle; ?></h1>
			</div>

			<form id="member-registration" class="form-validate fec-auth__form"
				action="<?php echo JRoute::_('index.php?option=com_users&task=registration.register'); ?>"
				method="post">

				<?php foreach ($this->form->getFieldsets() as $fieldset) : ?>
					<?php $fields = $this->form->getFieldset($fieldset->name); ?>
					<?php if (count($fields)) : ?>
						<fieldset class="fec-auth__fieldset">
							<?php if (isset($fieldset->label) && $fieldset->label) : ?>
								<legend class="fec-auth__legend">
									<?php echo JText::_($fieldset->label); ?>
								</legend>
							<?php endif; ?>
							<dl class="fec-auth__fields">
								<?php foreach ($fields as $field) : ?>
									<?php if (!$field->hidden) : ?>
										<dt class="fec-auth__dt">
											<?php echo $field->label; ?>
											<?php if ($field->required) : ?>
												<span class="fec-auth__req" aria-hidden="true">*</span>
											<?php endif; ?>
										</dt>
										<dd class="fec-auth__dd">
											<?php echo $field->input; ?>
										</dd>
									<?php endif; ?>
								<?php endforeach; ?>
							</dl>
						</fieldset>
					<?php endif; ?>
				<?php endforeach; ?>

				<div class="fec-auth__actions">
					<button type="submit" class="fec-btn fec-btn--primary fec-auth__submit validate">
						<?php echo JText::_('JREGISTER'); ?>
					</button>
					<a class="fec-btn fec-btn--ghost fec-auth__cancel"
						href="<?php echo JRoute::_('index.php?option=com_users&view=login'); ?>"
						title="<?php echo JText::_('JCANCEL'); ?>">
						<?php echo JText::_('JCANCEL'); ?>
					</a>
				</div>

				<input type="hidden" name="option" value="com_users" />
				<input type="hidden" name="task" value="registration.register" />
				<?php echo JHtml::_('form.token'); ?>
			</form>

		</div>

		<div class="fec-auth__foot">
			<a class="fec-auth__footlink"
				href="<?php echo JRoute::_('index.php?option=com_users&view=login'); ?>">
				<?php echo JText::_('JLOGIN'); ?>
			</a>
		</div>

	</div>
</div>