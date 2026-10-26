<?php
/**
 * com_partnerdesk - submit form template
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

$data = $this->data;

$val = function ($key) use ($data) {
	return isset($data[$key]) ? htmlspecialchars($data[$key], ENT_QUOTES, 'UTF-8') : '';
};
?>
<style>
.fec-pdesk { max-width: 860px; margin: 0 auto; }
.fec-pdesk h1 { display: flex; align-items: center; gap: 10px; }
.fec-pdesk .fec-pdesk-intro { color: #555; margin-bottom: 1.5em; }
.fec-pdesk fieldset { border: 1px solid #e3e3e3; border-radius: 8px; padding: 18px 20px 8px; margin: 0 0 22px; background: #fdfdfd; }
.fec-pdesk legend { font-weight: 600; font-size: 1.05em; padding: 0 8px; }
.fec-pdesk .fec-row { margin-bottom: 14px; }
.fec-pdesk label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 0.94em; }
.fec-pdesk label .fec-req { color: #c0392b; }
.fec-pdesk input[type=text], .fec-pdesk input[type=email], .fec-pdesk input[type=url],
.fec-pdesk select, .fec-pdesk textarea {
	width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #ccc;
	border-radius: 6px; font: inherit; background: #fff;
}
.fec-pdesk textarea { min-height: 70px; resize: vertical; }
.fec-pdesk .fec-hint { display: block; color: #888; font-size: 0.85em; margin-top: 3px; }
.fec-pdesk .fec-hp { position: absolute; left: -9999px; top: -9999px; height: 0; overflow: hidden; }
.fec-pdesk .fec-submit-bar { text-align: center; padding: 10px 0 30px; }
.fec-pdesk .fec-submit-btn {
	display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px;
	border: 0; border-radius: 8px; background: #2f6f4f; color: #fff;
	font-size: 1.05em; font-weight: 600; cursor: pointer;
}
.fec-pdesk .fec-submit-btn:hover { background: #275e43; }
.fec-pdesk .fec-privacy { font-size: 0.85em; color: #777; text-align: center; margin-top: 8px; }
</style>

<div class="fec-pdesk">

	<h1>
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12l2 2 4-4"/></svg>
		List Your Brand
	</h1>

	<p class="fec-pdesk-intro">
		Are you an affiliate manager and want your casino listed on FreeExtraChips?
		Fill in the sheet below. One brand per submission. If a value is unknown, write
		<strong>pending</strong> &mdash; never guess and never leave a field silently blank.
		Values should reflect the current public T&amp;C and cashier. All submissions are
		reviewed manually by our editorial desk and nothing is published automatically.
	</p>

	<form action="<?php echo JRoute::_('index.php?option=com_partnerdesk'); ?>" method="post" id="fecPartnerForm">

		<?php foreach ($this->sections as $section) : ?>
			<fieldset>
				<legend><?php echo $section['title']; ?></legend>

				<?php foreach ($section['fields'] as $field) : ?>
					<?php
					$key      = $field['key'];
					$required = !empty($field['required']);
					$hint     = isset($field['hint']) ? $field['hint'] : '';
					?>
					<div class="fec-row">
						<label for="fec_<?php echo $key; ?>">
							<?php echo $field['label']; ?>
							<?php if ($required) : ?><span class="fec-req">*</span><?php endif; ?>
						</label>

						<?php if ($field['type'] === 'textarea') : ?>
							<textarea name="<?php echo $key; ?>" id="fec_<?php echo $key; ?>"<?php echo $required ? ' required' : ''; ?>><?php echo $val($key); ?></textarea>

						<?php elseif ($field['type'] === 'select') : ?>
							<select name="<?php echo $key; ?>" id="fec_<?php echo $key; ?>"<?php echo $required ? ' required' : ''; ?>>
								<option value="">- select -</option>
								<?php foreach ($field['options'] as $opt) : ?>
									<option value="<?php echo htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($val($key) === htmlspecialchars($opt, ENT_QUOTES, 'UTF-8')) ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?></option>
								<?php endforeach; ?>
							</select>

						<?php else : ?>
							<input
								type="<?php echo $field['type'] === 'email' ? 'email' : ($field['type'] === 'url' ? 'url' : 'text'); ?>"
								name="<?php echo $key; ?>"
								id="fec_<?php echo $key; ?>"
								value="<?php echo $val($key); ?>"
								<?php echo $required ? 'required' : ''; ?>
							/>
						<?php endif; ?>

						<?php if ($hint !== '') : ?>
							<span class="fec-hint">e.g. <?php echo $hint; ?></span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

			</fieldset>
		<?php endforeach; ?>

		<div class="fec-hp" aria-hidden="true">
			<label for="fec_website">Website</label>
			<input type="text" name="website" id="fec_website" value="" tabindex="-1" autocomplete="off" />
		</div>

		<div class="fec-submit-bar">
			<button type="submit" class="fec-submit-btn">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
				Submit for review
			</button>
			<p class="fec-privacy">
				Your submission is private: it is visible only to the FreeExtraChips editorial desk
				and is never published as-is.
			</p>
		</div>

		<input type="hidden" name="option" value="com_partnerdesk" />
		<input type="hidden" name="task" value="send" />
		<?php echo JHtml::_('form.token'); ?>
	</form>
</div>
