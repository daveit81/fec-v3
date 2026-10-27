<?php
/**
 * com_bonuses :: views/add/tmpl/default.php
 * Add / Edit bonus form.
 *
 * Renders in two modes off $this->item:
 *   - add  : $this->item is null  -> empty form, task=actions.save inserts
 *   - edit : $this->item is set   -> pre-filled form, save updates by bid
 *
 * All data is prepared in view.html.php. This file is presentation only.
 */
defined('_JEXEC') or die('Restricted access');

if (empty($this->authorized))
{
	return;
}

$item   = $this->item;
$isEdit = $this->isEdit;

// --- tiny presentation helpers --------------------------------------
$E = function ($v) {
	return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
$NUM = function ($v) {
	return ($v === null || $v === '') ? '' : $v;
};
$SEL = function ($a, $b) {
	return ((string) $a === (string) $b) ? ' selected="selected"' : '';
};
$CHK = function ($a, $b) {
	return ((string) $a === (string) $b) ? ' checked="checked"' : '';
};
$MSEL = function ($v, $arr) {
	return in_array((string) $v, array_map('strval', (array) $arr), true)
		? ' selected="selected"' : '';
};

// --- flatten the item into scalar values (null-safe in add mode) ----
$v_bid       = $isEdit ? (int) $item->bid : 0;
$v_cid       = $isEdit ? $item->cid : '';
$v_type      = $isEdit ? strtolower((string) $item->type) : '';
$v_exclusive = $isEdit ? $item->exclusive : '';
$v_platform  = $isEdit ? $item->platform : '';
$v_players   = $isEdit ? $item->players : '';
$v_currency  = $isEdit ? $item->currency : '';
$v_bonus     = $isEdit ? $NUM($item->bonus) : '';
$v_valspin   = $isEdit ? $NUM($item->val_spin) : '';
$v_maxbonus  = $isEdit ? $NUM($item->max_bonus) : '';
$v_fptimeden = $isEdit ? $item->fp_time_den : '';
$v_fptime    = $isEdit ? $NUM($item->fp_time) : '';
$v_code      = $isEdit ? $item->code : '';
$v_mindep    = $isEdit ? $NUM($item->min_dep) : '';
$v_maxdep    = $isEdit ? $NUM($item->max_dep) : '';
$v_mincash   = $isEdit ? $NUM($item->min_cashout) : '';
$v_maxcash   = $isEdit ? $NUM($item->max_cashout) : '';
$v_maxcashkind = ($isEdit && isset($item->max_cashout_kind)) ? trim((string)$item->max_cashout_kind) : '';
$v_wrmeth    = $isEdit ? ($item->wr_meth !== '' ? $item->wr_meth : 'b') : 'b';
$v_wr        = $isEdit ? $NUM($item->wr) : '';
$v_maxbet    = $isEdit ? $NUM($item->max_bet) : '';
$v_gid       = $isEdit ? $NUM($item->gid) : '';
$v_cashable  = $isEdit ? $item->cashable : '';
$v_related   = $isEdit ? (int) $item->related : 0;
$v_infos     = $isEdit ? $item->additional_infos : '';
$v_link      = $isEdit ? $item->link : '';
$v_tc        = $isEdit ? $item->tc : '';

// editorial author:
//   edit mode -> the value saved on the bonus
//   add mode  -> the default author id from the helper (David Romano)
$v_author = $isEdit
	? (int) (isset($item->author_id) ? $item->author_id : 0)
	: (int) $this->defaultAuthorId;

// editorial last-editor (modified_by):
//   edit mode + value set on the bonus  -> use it
//   edit mode + NULL (never edited yet) -> default editorial author
//   add mode                            -> not shown (irrelevant on insert)
$v_modified_by = ($isEdit && isset($item->modified_by) && (int) $item->modified_by > 0)
	? (int) $item->modified_by
	: (int) $this->defaultAuthorId;

$sel = $this->selected; // pre-split starlist arrays

$submitLabel = $isEdit ? 'Save changes' : 'Create bonus';
$listUrl     = JRoute::_('index.php?option=com_bonuses&view=manage', false);
$formAction  = JRoute::_('index.php?option=com_bonuses&task=actions.save', false);

// CSRF token, needed for the Restore link (the inline form submit
// uses its own JHtml::_('form.token') below, but the Restore action
// is a GET link and must carry the token in the query string).
$token = JSession::getFormToken();
?>

<?php
/*
 * NOTE: all front-end assets are loaded by BonusesViewAdd::loadAssets()
 * in view.html.php, in dependency order. Do not re-add asset tags here.
 */
?>

<div class="fec-dash fec-dash-form">

	<header class="fec-dash-head">
		<div>
			<h1><?php echo $isEdit ? 'Edit bonus' : 'Add a bonus'; ?></h1>
			<p class="fec-dash-sub">
				<?php if ($isEdit) : ?>
					Editing <strong>#<?php echo $v_bid; ?></strong>
					&mdash; <?php echo $E($item->brand); ?>
				<?php else : ?>
					Create a new bonus listing.
				<?php endif; ?>
			</p>
		</div>
		<a class="fec-btn fec-btn-ghost" href="<?php echo $listUrl; ?>">&larr; Back to list</a>
	</header>

	<?php
	$msgs = JFactory::getApplication()->getMessageQueue();
	if (!empty($msgs)) :
		foreach ($msgs as $m) :
			$cls = ($m['type'] === 'error') ? 'is-error' : 'is-success';
			?>
			<div class="fec-banner <?php echo $cls; ?>"><?php echo $E($m['message']); ?></div>
			<?php
		endforeach;
	endif;
	?>

	<div id="fec-form-banner" class="fec-banner" style="display:none;"></div>

	<?php
	/*
	 * Live preview - a sticky summary card that mirrors how the bonus
	 * will read, rebuilt client-side on every field change (see the
	 * LIVE PREVIEW block in the script below). Pure presentation: the
	 * server knows nothing about it. The headline construction follows
	 * the same per-type rules as applyTypeRules(); the status badge
	 * replicates the controller's autoStatus() prediction.
	 */
	?>
	<div id="fec-preview" class="fec-preview">
		<div class="fec-preview-head">
			<span class="fec-preview-eyebrow">Live preview</span>
			<span id="fec-preview-status" class="fec-status" style="display:none;"></span>
		</div>
		<div class="fec-preview-headline">
			<strong id="fec-preview-casino"></strong>
			<span id="fec-preview-title" class="fec-muted">Fill in the form to see the preview.</span>
			<span id="fec-preview-excl" class="fec-tag fec-tag-excl" style="display:none;">Exclusive</span>
		</div>
		<div id="fec-preview-meta" class="fec-preview-meta"></div>
	</div>

	<form id="bonusform" method="post" action="<?php echo $formAction; ?>" class="fec-form" data-token-refreshed="true">

		<input type="hidden" name="bid" value="<?php echo $v_bid; ?>" />
		<?php echo JHtml::_('form.token'); ?>

		<!-- ============================================================
			 SECTION 1 - Casino & bonus type
			 ============================================================ -->
		<fieldset class="fec-section">
			<legend>Casino &amp; type</legend>

			<div class="form-group casino">
				<label class="control-label">Casino</label>
				<div class="fec-field selectContainer">
					<select name="casino" class="form-control">
						<option value=""></option>
						<?php foreach ($this->casinos as $c) : ?>
							<option value="<?php echo (int) $c['id']; ?>"<?php echo $SEL($v_cid, $c['id']); ?>>
								<?php echo $E($c['title']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div id="step-1"<?php echo $v_cid === '' ? ' class="hide"' : ''; ?>>
				<div class="form-group type">
					<label class="control-label">Bonus type</label>
					<div class="fec-field selectContainer">
						<select name="type" class="form-control">
							<option value=""></option>
							<?php foreach ($this->bonusType as $key => $label) : ?>
								<option value="<?php echo $E(strtolower($key)); ?>"<?php echo $SEL($v_type, strtolower($key)); ?>>
									<?php echo $E($label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</div>
		</fieldset>

		<div id="step-2"<?php echo $v_type === '' ? ' class="hide"' : ''; ?>>

			<!-- ========================================================
				 SECTION 2 - Targeting
				 ======================================================== -->
			<fieldset class="fec-section">
				<legend>Targeting</legend>

				<div class="form-group">
					<label class="control-label">Exclusive</label>
					<div class="fec-field">
						<label class="fec-radio">
							<input type="radio" name="exclusive" value="yes"<?php echo $CHK($v_exclusive, 'yes'); ?> /> Yes
						</label>
						<label class="fec-radio">
							<input type="radio" name="exclusive" value="no"<?php echo $CHK($v_exclusive, 'no'); ?> /> No
						</label>
					</div>
				</div>

				<div class="form-group platform" id="platform">
					<label class="control-label">Platform</label>
					<div class="fec-field selectContainer">
						<select name="platform" class="form-control">
							<option value="md"<?php echo $SEL($v_platform, 'md'); ?>>All</option>
							<?php foreach ($this->platform as $key => $label) : ?>
								<option value="<?php echo $E(strtolower($key)); ?>"<?php echo $SEL($v_platform, strtolower($key)); ?>>
									<?php echo $E($label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="form-group players" id="players">
					<label class="control-label">Player type</label>
					<div class="fec-field selectContainer">
						<select name="players" class="form-control">
							<option value="">Select player (default &ldquo;New&rdquo;)</option>
							<?php foreach ($this->players as $key => $label) : ?>
								<option value="<?php echo $E(strtolower($key)); ?>"<?php echo $SEL($v_players, strtolower($key)); ?>>
									<?php echo $E($label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="form-group currency" id="currency">
					<label class="control-label">Currency</label>
					<div class="fec-field selectContainer">
						<select name="currency" class="form-control">
							<option value="">All</option>
							<?php foreach ($this->currency as $key => $label) : ?>
								<option value="<?php echo $E(strtolower($key)); ?>"<?php echo $SEL($v_currency, strtolower($key)); ?>>
									<?php echo $E($label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</fieldset>

			<!-- ========================================================
				 SECTION 3 - Bonus value
				 ======================================================== -->
			<fieldset class="fec-section">
				<legend>Bonus value</legend>

				<div class="form-group">
					<label class="control-label">Bonus</label>
					<div class="fec-field input-group">
						<span class="input-group-addon js-curr-val hide" id="curr-val">All</span>
						<input type="text" class="form-control" name="bonus" value="<?php echo $E($v_bonus); ?>" />
						<span class="input-group-addon hide" id="percent">%</span>
					</div>
				</div>

				<div class="form-group" id="valxspin">
					<label class="control-label">Value per spin</label>
					<div class="fec-field input-group">
						<span class="input-group-addon js-curr-val">All</span>
						<input type="text" class="form-control" name="valxspin" value="<?php echo $E($v_valspin); ?>" />
					</div>
				</div>

				<div class="form-group" id="maxbonus">
					<label class="control-label">Max bonus</label>
					<div class="fec-field input-group">
						<span class="input-group-addon js-curr-val">All</span>
						<input type="text" class="form-control" name="maxbonus" value="<?php echo $E($v_maxbonus); ?>" />
					</div>
				</div>

				<div class="form-group" id="fptimeden">
					<label class="control-label">Time denomination</label>
					<div class="fec-field selectContainer">
						<select name="fptimeden" class="form-control">
							<option value=""></option>
							<option value="m"<?php echo $SEL($v_fptimeden, 'm'); ?>>Minutes</option>
							<option value="h"<?php echo $SEL($v_fptimeden, 'h'); ?>>Hours</option>
							<option value="d"<?php echo $SEL($v_fptimeden, 'd'); ?>>Days</option>
						</select>
					</div>
				</div>

				<div class="form-group" id="fptime">
					<label class="control-label">Free play time</label>
					<div class="fec-field input-group">
						<span class="input-group-addon"><i class="fa fa-clock-o"></i></span>
						<input type="text" class="form-control" name="fptime" value="<?php echo $E($v_fptime); ?>" />
					</div>
				</div>
			</fieldset>

			<!-- ========================================================
				 SECTION 4 - Deposit & cashout
				 ======================================================== -->
			<fieldset class="fec-section">
				<legend>Deposit &amp; cashout</legend>

				<div class="form-group code">
					<label class="control-label">Code</label>
					<div class="fec-field">
						<input type="text" name="code" class="form-control" value="<?php echo $E($v_code); ?>" />
					</div>
				</div>

				<div class="form-group mindep" id="mindep">
					<label class="control-label">Min. deposit</label>
					<div class="fec-field input-group">
						<span class="input-group-addon js-curr-val">All</span>
						<input type="text" name="mindep" class="form-control" value="<?php echo $E($v_mindep); ?>" />
					</div>
				</div>

				<div class="form-group maxdep" id="maxdep">
					<label class="control-label">Max. deposit</label>
					<div class="fec-field input-group">
						<span class="input-group-addon js-curr-val">All</span>
						<input type="text" name="maxdep" class="form-control" value="<?php echo $E($v_maxdep); ?>" />
					</div>
				</div>

				<div class="form-group mincash">
					<label class="control-label">Min. cashout</label>
					<div class="fec-field input-group">
						<span class="input-group-addon js-curr-val">All</span>
						<input type="text" name="mincash" class="form-control" value="<?php echo $E($v_mincash); ?>" />
					</div>
				</div>

				<div class="form-group maxcash">
					<label class="control-label">Max. cashout</label>
					<div class="fec-field input-group">
						<span class="input-group-addon js-curr-val">All</span>
						<input type="text" class="form-control" name="maxcash" value="<?php echo $E($v_maxcash); ?>" />
					</div>
				</div>

				<div class="form-group maxcashkind">
					<label class="control-label">Max cashout kind</label>
					<div class="fec-field">
						<select name="maxcashkind" class="form-control">
							<option value=""<?php echo $v_maxcashkind === '' ? ' selected' : ''; ?>>Not classified</option>
							<option value="abs"<?php echo $v_maxcashkind === 'abs' ? ' selected' : ''; ?>>Amount (money)</option>
							<option value="x_dep"<?php echo $v_maxcashkind === 'x_dep' ? ' selected' : ''; ?>>Multiple of deposit</option>
							<option value="x_bonus"<?php echo $v_maxcashkind === 'x_bonus' ? ' selected' : ''; ?>>Multiple of bonus</option>
						</select>
					</div>
					<p class="help-block">Required when a positive max cashout is set: the unit the number is expressed in. Zero (no cap) and empty need no kind.</p>
				</div>
			</fieldset>

			<!-- ========================================================
				 SECTION 5 - Wagering
				 ======================================================== -->
			<fieldset class="fec-section">
				<legend>Wagering</legend>

				<div class="form-group" id="wrmeth">
					<label class="control-label">WR method</label>
					<div class="fec-field">
						<label class="fec-radio">
							<input type="radio" name="wrmethod" value="b"<?php echo $CHK($v_wrmeth, 'b'); ?> /> Only bonus
						</label>
						<label class="fec-radio">
							<input type="radio" name="wrmethod" value="bd"<?php echo $CHK($v_wrmeth, 'bd'); ?> /> Bonus + deposit
						</label>
					</div>
				</div>

				<div class="form-group wr">
					<label class="control-label">Min. WR</label>
					<div class="fec-field input-group">
						<input type="text" name="wr" class="form-control" value="<?php echo $E($v_wr); ?>" />
						<span class="input-group-addon" id="wr-val">X Bonus</span>
					</div>
				</div>

				<div class="form-group" id="maxbet">
					<label class="control-label">Max bet per spin</label>
					<div class="fec-field input-group">
						<span class="input-group-addon js-curr-val">All</span>
						<input type="text" class="form-control" name="maxbet" value="<?php echo $E($v_maxbet); ?>" />
					</div>
				</div>
			</fieldset>

			<!-- ========================================================
				 SECTION 5b - Welcome package (FXRate)
				 Shown only for the deposit-match ladder types (su, 2d ...
				 10d). A bonus row is at most one leg of one package; the
				 position comes from its type. The package's other legs
				 are either other rows of the same casino or VIRTUAL legs
				 whose terms are typed here. Data: $this->pkgPayload
				 (server prefill) and actions.packageoptions (on casino
				 change); the article parser is actions.packagedetect.
				 ======================================================== -->
			<fieldset class="fec-section hide" id="pkg-section">
				<legend>Welcome package</legend>

				<div class="form-group">
					<label class="control-label">Package</label>
					<div class="fec-field">
						<select name="pkg_choice" id="pkg-choice" class="form-control">
							<option value="auto">Auto-detect from the article when saving</option>
							<option value="none">Not part of a package</option>
							<!-- the casino's packages are injected here (pkg:N) -->
							<option value="new">+ New package</option>
						</select>
						<p class="help-block" id="pkg-hint">
							Auto-detect creates a text-sourced package when the article describes a multi-deposit welcome package (deposits, headline, track) and never touches packages edited by hand.
						</p>
					</div>
				</div>

				<div id="pkg-panel" class="hide">

					<div class="form-group">
						<label class="control-label">This bonus is</label>
						<div class="fec-field">
							<span class="fec-pkg-position" id="pkg-position-label">deposit 1</span>
							<span class="fec-pkg-fx" id="pkg-fxrate"></span>
						</div>
					</div>

					<div class="form-group">
						<label class="control-label">Title / track</label>
						<div class="fec-field">
							<input type="text" name="pkg_title" id="pkg-title" class="form-control" value="Welcome Package" maxlength="120" />
							<p class="help-block">&ldquo;Welcome Package&rdquo;, &ldquo;Highroller Welcome Pack&rdquo;, &ldquo;Crypto Track&rdquo;&hellip;</p>
						</div>
					</div>

					<div class="form-group">
						<label class="control-label">Operator headline</label>
						<div class="fec-field">
							<input type="text" name="pkg_headline" id="pkg-headline" class="form-control" value="" maxlength="160" placeholder="250% up to $1,500 + 75 free spins" />
							<p class="help-block">The line the casino prints. Shown as &ldquo;Advertised&rdquo; next to the FXRate numbers.</p>
						</div>
					</div>

					<div class="form-group">
						<label class="control-label">Deposits</label>
						<div class="fec-field fec-pkg-inline">
							<input type="number" name="pkg_deposits" id="pkg-deposits" class="form-control fec-pkg-num" value="3" min="1" max="10" />
							<label class="fec-pkg-inline-label">Spins in total</label>
							<input type="number" name="pkg_spins_total" id="pkg-spins" class="form-control fec-pkg-num" value="0" min="0" />
							<label class="fec-pkg-inline-label">Window (days)</label>
							<input type="number" name="pkg_window_days" id="pkg-window" class="form-control fec-pkg-num" value="" min="0" placeholder="&mdash;" />
							<label class="fec-radio"><input type="checkbox" name="pkg_sequential" id="pkg-sequential" value="1" checked="checked" /> Legs in order (one at a time)</label>
						</div>
					</div>

					<div class="form-group">
						<label class="control-label">Status</label>
						<div class="fec-field fec-pkg-inline">
							<select name="pkg_status" id="pkg-status" class="form-control fec-pkg-num">
								<option value="active">Active</option>
								<option value="paused">Paused</option>
								<option value="retired">Retired</option>
							</select>
							<label class="fec-pkg-inline-label" for="pkg-alt-of">Alternative to</label>
							<select name="pkg_alt_of" id="pkg-alt-of" class="form-control fec-pkg-alt" title="Another package of this casino the player would take INSTEAD of this one (a standard track beside a high-roller or crypto track). Both pages then say so.">
								<option value="0">&mdash; none &mdash;</option>
							</select>
							<button type="button" class="fec-btn fec-btn-ghost fec-btn-sm" id="pkg-detect">Detect from article</button>
							<span class="fec-muted" id="pkg-detect-out"></span>
						</div>
					</div>

					<div class="form-group form-group-block">
						<label class="control-label">Legs</label>
						<div class="fec-field fec-field-wide">
							<div class="fec-pkg-scroll">
							<table class="fec-pkg-table" id="pkg-legs">
								<thead>
									<tr>
										<th>#</th>
										<th>Row</th>
										<th>Match %</th>
										<th>Up to</th>
										<th>Min dep</th>
										<th>Max dep</th>
										<th>WR</th>
										<th>On</th>
										<th>Max cashout</th>
										<th>Unit</th>
										<th>Spins</th>
										<th title="Wagering on the spins' winnings: 0 = no wagering, 40 = 40x winnings, empty = not stated">Spins WR</th>
										<th>Code</th>
									</tr>
								</thead>
								<tbody><!-- rows rendered by renderPkgLegs() --></tbody>
							</table>
							</div>
							<div class="fec-pkg-inline" style="margin-top:8px">
								<button type="button" class="fec-btn fec-btn-ghost fec-btn-sm" id="pkg-copy-base">Copy this bonus&rsquo;s terms to the virtual legs</button>
								<label class="fec-radio"><input type="checkbox" id="pkg-copy-overwrite" value="1" /> overwrite what is already typed</label>
								<span class="fec-muted" id="pkg-copy-out"></span>
							</div>
							<p class="help-block">A leg linked to a bonus row reads its terms from that row (greyed). A virtual leg keeps the terms typed here; leave what the operator does not state empty, FXRate prints &ldquo;not stated&rdquo; for it. &ldquo;Copy&rdquo; fills the empty cells of every virtual leg with this bonus&rsquo;s match, cap, deposits, wagering, max cashout and code (the &ldquo;redeemable &times;N&rdquo; case); ticked, it mirrors this bonus exactly, blanks included. Spins are never copied, nor is their wagering. &ldquo;Spins WR&rdquo; is the wagering on the spins&rsquo; winnings, apart from the match&rsquo;s: 0 for &ldquo;no wagering&rdquo;, 40 for 40&times; winnings, empty for not stated; it is typed for linked rows too (a bonus row carries only the match&rsquo;s wagering) and printed beside the leg, never priced. Max cashout is reported beside the leg and never priced into FXRate; give it a unit (money, &times; deposit, &times; bonus) or the number is shown bare.</p>
						</div>
					</div>

					<div class="form-group form-group-block">
						<label class="control-label">Package notes (never rendered)</label>
						<div class="fec-field fec-field-wide">
							<textarea name="pkg_notes" id="pkg-notes" class="form-control" style="height:70px;"></textarea>
						</div>
					</div>

				</div><!-- /#pkg-panel -->
			</fieldset>

			<!-- ========================================================
				 SECTION 6 - Games
				 ======================================================== -->
			<fieldset class="fec-section">
				<legend>Games</legend>

				<div class="form-group game" id="game">
					<label class="control-label">Featured game</label>
					<div class="fec-field selectContainer">
						<select name="game" class="form-control">
							<option value="">All</option>
							<?php foreach ($this->games as $g) : ?>
								<option value="<?php echo (int) $g['gid']; ?>"<?php echo $SEL($v_gid, $g['gid']); ?>>
									<?php echo $E($g['game_name']); ?> (<?php echo $E($g['provider_name']); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="form-group">
					<label class="control-label">Allowed games</label>
					<div class="fec-field">
						<select class="form-control" name="gamesok[]" multiple="multiple">
							<?php foreach ($this->gametype as $par => $g) : ?>
								<option value="<?php echo $E(strtolower($par)); ?>"
									gameallow="<?php echo $E(strtolower($g)); ?>"
									<?php echo $MSEL(strtolower($par), $sel['gamesok']); ?>>
									<?php echo $E($g); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="form-group">
					<label class="control-label">Restricted games</label>
					<div class="fec-field">
						<select class="form-control" name="gamesno[]" multiple="multiple">
							<?php foreach ($this->gametype as $par => $g) : ?>
								<option value="<?php echo $E(strtolower($par)); ?>"
									gameallow="<?php echo $E(strtolower($g)); ?>"
									<?php echo $MSEL(strtolower($par), $sel['gamesno']); ?>>
									<?php echo $E($g); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="form-group">
					<label class="control-label">Cashable</label>
					<div class="fec-field">
						<label class="fec-radio">
							<input type="radio" name="cashable" value="yes"<?php echo $CHK($v_cashable, 'yes'); ?> /> Yes
						</label>
						<label class="fec-radio">
							<input type="radio" name="cashable" value="no"<?php echo $CHK($v_cashable, 'no'); ?> /> No
						</label>
					</div>
				</div>
			</fieldset>

			<!-- ========================================================
				 SECTION 7 - Banking
				 ======================================================== -->
			<fieldset class="fec-section">
				<legend>Banking</legend>

				<div class="form-group">
					<label class="control-label">Allowed deposit methods</label>
					<div class="fec-field">
						<select class="form-control" name="bankok[]" multiple="multiple">
							<?php foreach ($this->banks as $b) : ?>
								<option value="<?php echo $E(strtolower($b['value'])); ?>"
									<?php echo $MSEL(strtolower($b['value']), $sel['bankok']); ?>>
									<?php echo $E($b['text']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="form-group">
					<label class="control-label">Restricted deposit methods</label>
					<div class="fec-field">
						<select class="form-control" name="bankno[]" multiple="multiple">
							<?php foreach ($this->banks as $b) : ?>
								<option value="<?php echo $E(strtolower($b['value'])); ?>"
									<?php echo $MSEL(strtolower($b['value']), $sel['bankno']); ?>>
									<?php echo $E($b['text']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</fieldset>

			<!-- ========================================================
				 SECTION 8 - Geo
				 ======================================================== -->
			<fieldset class="fec-section">
				<legend>Countries</legend>

				<div class="form-group">
					<label class="control-label">Allowed countries</label>
					<div class="fec-field">
						<select class="form-control" name="countriesok[]" multiple="multiple">
							<?php foreach ($this->countries as $code => $country) : ?>
								<option value="<?php echo $E(strtolower($code)); ?>"
									country="<?php echo $E(strtolower($country)); ?>"
									<?php echo $MSEL(strtolower($code), $sel['country_ok']); ?>>
									<?php echo $E($country); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="form-group">
					<label class="control-label">Restricted countries</label>
					<div class="fec-field">
						<select class="form-control" name="countriesno[]" multiple="multiple">
							<?php foreach ($this->countries as $code => $country) : ?>
								<option value="<?php echo $E(strtolower($code)); ?>"
									country="<?php echo $E(strtolower($country)); ?>"
									<?php echo $MSEL(strtolower($code), $sel['country_no']); ?>>
									<?php echo $E($country); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</fieldset>

			<!-- ========================================================
				 SECTION 9 - Schedule
				 ======================================================== -->
			<fieldset class="fec-section">
				<legend>Schedule</legend>

				<div class="form-group">
					<label class="control-label">Start date</label>
					<div class="fec-field dateContainer">
						<div class="input-group date" id="datetimepicker1">
							<input type="text" class="form-control" name="start"
								placeholder="MM-DD-YYYY h:m A" value="<?php echo $E($this->startForm); ?>" />
							<span class="input-group-addon"><span class="fa fa-calendar"></span></span>
						</div>
						<div id="fec-start-warning" class="fec-date-warning"
							 style="display:none; margin-top:6px; color:#c46a00; font-size:13px; font-weight:600;">
							&#9888; This start date is in the past. Save anyway if that is intentional.
						</div>
					</div>
				</div>

				<div class="form-group">
					<label class="control-label">End date</label>
					<div class="fec-field dateContainer">
						<div class="input-group date" id="datetimepicker2">
							<input type="text" class="form-control" name="end"
								placeholder="MM-DD-YYYY h:m A" value="<?php echo $E($this->endForm); ?>" />
							<span class="input-group-addon"><span class="fa fa-calendar"></span></span>
						</div>
						<div id="fec-end-warning" class="fec-date-warning"
							 style="display:none; margin-top:6px; color:#c46a00; font-size:13px; font-weight:600;">
							&#9888; The end date is earlier than the start date.
						</div>
					</div>
				</div>

				<?php
				/*
				 * Related bonus - links this bonus to another bonus of
				 * the SAME casino (e.g. "100 free spins" related to the
				 * "100% up to 100" deposit match it rides on). Stored
				 * in jos_bonuses.related as the target's bid.
				 *
				 * The options are loaded by loadRelated() below from
				 * the actions.relatedoptions JSON endpoint, both at
				 * boot (edit mode) and on every casino change. This
				 * replaces the legacy helpers/related.php block - that
				 * file is untouched on disk, the dashboard just no
				 * longer calls it.
				 *
				 * Server side, save() only accepts a related bid that
				 * exists AND belongs to the selected casino; anything
				 * else is nulled.
				 */
				?>
				<div class="form-group" id="related-group">
					<label class="control-label">Related bonus</label>
					<div class="fec-field selectContainer">
						<select name="related" class="form-control" id="related-select">
							<option value="">&mdash; No related bonus &mdash;</option>
							<!-- options injected by loadRelated() -->
						</select>
					</div>
				</div>
			</fieldset>

			<!-- ========================================================
				 SECTION 10 - Content, author & links
				 ======================================================== -->
			<fieldset class="fec-section">
				<legend>Content, author &amp; links</legend>

				<div class="form-group form-group-block">
					<label class="control-label">Content</label>
					<div class="fec-field fec-field-wide">
						<textarea class="form-control" name="infos" style="height: 400px;"><?php echo $E($v_infos); ?></textarea>
					</div>
				</div>

				<div class="form-group">
					<label class="control-label">Author</label>
					<div class="fec-field selectContainer">
						<select name="author_id" class="form-control">
							<option value="">&mdash; No author &mdash;</option>
							<?php foreach ($this->authors as $a) : ?>
								<option value="<?php echo (int) $a['id']; ?>"<?php echo $SEL($v_author, $a['id']); ?>>
									<?php echo $E($a['name']); ?>
									<?php if (!empty($a['role'])) : ?>
										&mdash; <?php echo $E($a['role']); ?>
									<?php endif; ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<?php
				/*
				 * "Last modified by" - editorial credit for the most
				 * recent content edit. Shown only in edit mode (a fresh
				 * bonus has no last-editor concept yet). On save the
				 * controller writes:
				 *   modified    = NOW()
				 *   modified_by = the value chosen here, validated
				 *                 against active casino_authors, with
				 *                 fallback to the default editorial
				 *                 author if missing/invalid.
				 * Other side-effect tasks (toggle, setauthor, bulk*)
				 * stamp the timestamp but leave modified_by alone.
				 */
				?>
				<?php if ($isEdit) : ?>
					<div class="form-group">
						<label class="control-label">Last modified by</label>
						<div class="fec-field selectContainer">
							<select name="modified_by" class="form-control">
								<option value="">&mdash; No editor &mdash;</option>
								<?php foreach ($this->authors as $a) : ?>
									<option value="<?php echo (int) $a['id']; ?>"<?php echo $SEL($v_modified_by, $a['id']); ?>>
										<?php echo $E($a['name']); ?>
										<?php if (!empty($a['role'])) : ?>
											&mdash; <?php echo $E($a['role']); ?>
										<?php endif; ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				<?php endif; ?>

				<div class="form-group">
					<label class="control-label">Link</label>
					<div class="fec-field">
						<input class="form-control" name="link" type="url"
							value="<?php echo $E($v_link); ?>"
							data-fv-uri-message="The website address is not valid" />
					</div>
				</div>

				<div class="form-group">
					<label class="control-label">T&amp;Cs link</label>
					<div class="fec-field">
						<input class="form-control" name="tc" type="url"
							value="<?php echo $E($v_tc); ?>"
							data-fv-uri-message="The website address is not valid" />
					</div>
				</div>
			</fieldset>

		</div><!-- /#step-2 -->

		<div class="fec-form-actions">
			<a class="fec-btn fec-btn-ghost" href="<?php echo $listUrl; ?>">Cancel</a>

			<?php if ($isEdit && !empty($this->lastHistory)) : ?>
				<?php
				$restoreUrl = JRoute::_(
					'index.php?option=com_bonuses&task=actions.restore&bid=' . (int) $v_bid . '&' . $token . '=1',
					false
				);
				$lastSaved  = (string) $this->lastHistory->saved_at;
				$lastReason = (string) $this->lastHistory->reason;
				?>
				<a class="fec-btn fec-btn-warn js-confirm-restore"
				   href="<?php echo $restoreUrl; ?>"
				   data-bid="<?php echo (int) $v_bid; ?>"
				   data-saved="<?php echo $E($lastSaved); ?>"
				   data-reason="<?php echo $E($lastReason); ?>"
				   rel="nofollow noprefetch noopener"
				   data-no-instant>
					&#8634; Restore previous version
				</a>
			<?php endif; ?>

			<button type="submit" class="fec-btn fec-btn-primary">
				<?php echo $E($submitLabel); ?>
			</button>
		</div>

	</form>
</div>

<script>
jQuery(document).ready(function ($) {

	var SUBMIT_LABEL = <?php echo json_encode($submitLabel); ?>;
	var IS_EDIT      = <?php echo $isEdit ? 'true' : 'false'; ?>;
	var INITIAL_TYPE = <?php echo json_encode($v_type); ?>;
	var INITIAL_CID  = <?php echo json_encode((string) $v_cid); ?>;
	var RELATED_SEL  = <?php echo (int) $v_related; ?>;
	var CSRF_TOKEN   = <?php echo json_encode($token); ?>;
	var BONUS_BID    = <?php echo (int) $v_bid; ?>;
	var CURRENT_STATUS = <?php echo json_encode($isEdit ? strtolower((string) $item->status) : ''); ?>;
	var PKG = <?php echo json_encode(isset($this->pkgPayload) ? $this->pkgPayload : array('packages' => array(), 'rows' => array(), 'this_pkg_id' => 0)); ?>;

	var $form = $('#bonusform');

	function applyTypeRules(val) {
		// the free-play timer doubles as the rakeback boost window
		var isRb = (val === 'rb');
		$('#fptime .control-label').text(isRb ? 'Boost window' : 'Free play time');
		$('#fptimeden .control-label').text(isRb ? 'Window unit' : 'Time denomination');
		if (isRb) {
			$('#wr-val').text('X Rakeback');
		} else {
			$('#wr-val').text($('input[name=wrmethod]:checked').val() === 'bd' ? 'X Bonus + Deposit' : 'X Bonus');
		}

		switch (val) {
			case 'nd':
				$('#percent').addClass('hide');
				$('#curr-val').removeClass('hide');
				$('#maxbonus').addClass('hide');
				$('#wrmeth').addClass('hide');
				$('#fptime').addClass('hide');
				$('#fptimeden').addClass('hide');
				$('#mindep,#maxdep').addClass('hide');
				$form.formValidation('enableFieldValidators', 'fptime', false);
				$form.formValidation('enableFieldValidators', 'fptimeden', false);
				$form.formValidation('enableFieldValidators', 'wrmethod', false);
				break;

			case 'fc':
			case 'sweeps':
				// 'sweeps' shares the Free Cash layout on purpose:
				// currency addon visible (shows GC/SC), no percent, no
				// max-bonus, no WR-method (bonus-vs-deposit is meaningless
				// for sweepstakes coins), no free-play timer. Min/max
				// deposit stay visible - for purchase-linked SC offers
				// they read as the GC purchase thresholds; for
				// no-purchase offers they are simply left empty.
				$('#percent').addClass('hide');
				$('#curr-val').removeClass('hide');
				$('#maxbonus').addClass('hide');
				$('#wrmeth').addClass('hide');
				$('#fptime').addClass('hide');
				$('#fptimeden').addClass('hide');
				$('#mindep,#maxdep').removeClass('hide');
				$form.formValidation('enableFieldValidators', 'fptime', false);
				$form.formValidation('enableFieldValidators', 'fptimeden', false);
				$form.formValidation('enableFieldValidators', 'wrmethod', false);
				break;

			case 'fs':
				$('#percent').addClass('hide');
				$('#curr-val').addClass('hide');
				$('#maxbonus').addClass('hide');
				$('#wrmeth').addClass('hide');
				$('#fptime').addClass('hide');
				$('#mindep,#maxdep').removeClass('hide');
				$form.formValidation('enableFieldValidators', 'fptime', false);
				$form.formValidation('enableFieldValidators', 'fptimeden', false);
				$form.formValidation('enableFieldValidators', 'wrmethod', false);
				break;

			case 'fp':
				$('#percent').addClass('hide');
				$('#curr-val').addClass('hide');
				$('#maxbonus').addClass('hide');
				$('#wrmeth').addClass('hide');
				$('#fptime').removeClass('hide');
				$('#mindep,#maxdep').addClass('hide');
				$form.formValidation('enableFieldValidators', 'fptime');
				$form.formValidation('enableFieldValidators', 'fptimeden');
				$form.formValidation('enableFieldValidators', 'wrmethod', false);
				break;

			case 'rb':
				// 'rb' (Rakeback): `bonus` is the rakeback rate, a percentage
				// paid back on every bet win or lose, so the percent addon is
				// on and the currency addon off. Max bonus stays as an optional
				// cap. No WR method: there is no bonus balance to wager against
				// a deposit, `wr` alone says whether the rakeback is wager-free
				// (0) or not. Min/max deposit are the qualifying deposit. The
				// free-play timer is reused as the BOOST WINDOW ("30% boosted
				// for 7 days": fp_time = 7, fp_time_den = d), relabelled above;
				// empty for a standing rakeback. Its validators stay off: the
				// window is optional (the server nulls anything non-numeric).
				$('#maxbonus').removeClass('hide');
				$('#percent').removeClass('hide');
				$('#curr-val').addClass('hide');
				$('#wrmeth').addClass('hide');
				$('#fptime').removeClass('hide');
				$('#fptimeden').removeClass('hide');
				$('#mindep,#maxdep').removeClass('hide');
				$form.formValidation('enableFieldValidators', 'fptime', false);
				$form.formValidation('enableFieldValidators', 'fptimeden', false);
				$form.formValidation('enableFieldValidators', 'wrmethod', false);
				break;

			default:
				$('#maxbonus').removeClass('hide');
				$('#percent').removeClass('hide');
				$('#curr-val').addClass('hide');
				$('#wrmeth').removeClass('hide');
				$('#fptime').addClass('hide');
				$('#mindep,#maxdep').removeClass('hide');
				$form.formValidation('enableFieldValidators', 'fptime', false);
				$form.formValidation('enableFieldValidators', 'fptimeden', false);
				$form.formValidation('enableFieldValidators', 'wrmethod');
				break;
		}

		// the Welcome package section exists only for the ladder types
		$('#pkg-section').toggleClass('hide', pkgPosition(val) === 0);
		$('#pkg-position-label').text('deposit ' + (pkgPosition(val) || 1));
		if (pkgPosition(val) > 0) { renderPkgLegs(); }
	}

	/* --------------------------------------------------------------
	 * WELCOME PACKAGE (FXRate)
	 *
	 * PKG = { packages: [...], rows: [...], this_pkg_id: N } from the
	 * server (edit mode) or from actions.packageoptions on casino
	 * change. The section is a select (auto / none / pkg:N / new) and
	 * a panel with the package fields and the legs table. The bonus
	 * being edited always owns the position its type gives; the other
	 * legs are other ladder rows of the casino or virtual legs typed
	 * here. Nothing is saved from here directly: the fields travel
	 * with the form and the controller's syncPackage() applies them.
	 * -------------------------------------------------------------- */
	var LEG_FIELDS = ['pct', 'cap', 'min_dep', 'max_dep', 'wr', 'wr_meth', 'max_cashout', 'max_cashout_kind', 'spins', 'spins_wr', 'code'];

	function pkgPosition(type) {
		type = String(type || '').toLowerCase();
		// su, hr and crypto are first-deposit matches of their track: they open a package
		if (type === 'su' || type === 'hr' || type === 'crypto') { return 1; }
		var m = /^([2-9]|10)d$/.exec(type);
		return m ? parseInt(m[1], 10) : 0;
	}

	function pkgRowByBid(bid) {
		bid = parseInt(bid, 10) || 0;
		for (var i = 0; i < PKG.rows.length; i++) {
			if (parseInt(PKG.rows[i].bid, 10) === bid) { return PKG.rows[i]; }
		}
		return null;
	}

	function pkgById(id) {
		id = parseInt(id, 10) || 0;
		for (var i = 0; i < PKG.packages.length; i++) {
			if (parseInt(PKG.packages[i].pkg_id, 10) === id) { return PKG.packages[i]; }
		}
		return null;
	}

	function pkgLegsOfPanel() {
		// current legs as typed in the panel: position -> {bid, pct, ...}
		var legs = {};
		$('#pkg-legs tbody tr').each(function () {
			var pos = parseInt($(this).data('pos'), 10);
			var leg = { bid: parseInt($(this).find('.pkg-leg-bid').val(), 10) || 0 };
			for (var i = 0; i < LEG_FIELDS.length; i++) {
				leg[LEG_FIELDS[i]] = $(this).find('[name="pkg_leg[' + pos + '][' + LEG_FIELDS[i] + ']"]').val();
			}
			legs[pos] = leg;
		});
		return legs;
	}

	function renderPkgChoices() {
		var $sel = $('#pkg-choice');
		var cur  = $sel.val() || 'auto';
		$sel.find('option[value^="pkg:"]').remove();
		var $new = $sel.find('option[value="new"]');
		$.each(PKG.packages, function (i, p) {
			var txt = '#' + p.pkg_id + ' ' + p.title
				+ (p.headline ? ' \u2014 ' + p.headline : '')
				+ ' (' + p.deposits + ' deposits' + (p.priced ? '' : ', terms missing') + ')'
				+ (p.status !== 'active' ? ' [' + p.status + ']' : '');
			$('<option>').attr('value', 'pkg:' + p.pkg_id).text(txt).insertBefore($new);
		});
		if (PKG.this_pkg_id > 0) {
			$sel.val('pkg:' + PKG.this_pkg_id);
		} else if (cur.indexOf('pkg:') === 0) {
			$sel.val('auto');
		} else {
			$sel.val(cur);
		}
		onPkgChoice();
	}

	// "Alternative to": the other packages of the casino (never the one being edited)
	function renderPkgAltOptions(selfId, selected) {
		var $sel = $('#pkg-alt-of');
		$sel.find('option:not(:first)').remove();
		$.each(PKG.packages, function (i, q) {
			if (parseInt(q.pkg_id, 10) === parseInt(selfId, 10)) { return; }
			var txt = '#' + q.pkg_id + ' ' + q.title + (q.headline ? ' \u2014 ' + q.headline : '') + (q.status !== 'active' ? ' [' + q.status + ']' : '');
			$sel.append($('<option>').attr('value', q.pkg_id).text(txt));
		});
		$sel.val(String(selected || 0));
		if ($sel.val() === null) { $sel.val('0'); }
	}

	function fillPkgPanel(p) {
		renderPkgAltOptions(p ? p.pkg_id : 0, p ? p.alt_of : 0);
		$('#pkg-title').val(p ? p.title : 'Welcome Package');
		$('#pkg-headline').val(p ? p.headline : '');
		$('#pkg-deposits').val(p ? p.deposits : Math.max(2, pkgPosition(previewVal('type'))));
		$('#pkg-spins').val(p ? p.spins_total : 0);
		$('#pkg-window').val(p && p.window_days !== '' ? p.window_days : '');
		$('#pkg-sequential').prop('checked', !p || parseInt(p.sequential, 10) === 1);
		$('#pkg-status').val(p ? p.status : 'active');
		$('#pkg-notes').val(p ? p.notes : '');
		$('#pkg-fxrate').text(p && p.fxrate ? p.fxrate : (p ? '' : 'FXRate is computed after saving.'));
		var legs = {};
		if (p) {
			$.each(p.legs, function (i, l) { legs[l.position] = l; });
		}
		renderPkgLegs(legs);
	}

	function renderPkgLegs(legs) {
		if (!legs) { legs = pkgLegsOfPanel(); }
		var deposits = Math.max(1, Math.min(10, parseInt($('#pkg-deposits').val(), 10) || 1));
		var myPos    = pkgPosition(previewVal('type'));
		if (deposits < myPos) { deposits = myPos; $('#pkg-deposits').val(deposits); }

		var $tb = $('#pkg-legs tbody').empty();

		for (var pos = 1; pos <= deposits; pos++) {
			var leg  = legs[pos] || {};
			var mine = (pos === myPos);
			var $tr  = $('<tr>').attr('data-pos', pos);

			$tr.append($('<td>').addClass('fec-pkg-pos').text(pos));

			// ---- row select: this bonus / virtual / another ladder row
			var $sel = $('<select>').addClass('form-control pkg-leg-bid').attr('name', 'pkg_leg[' + pos + '][bid]');
			if (mine) {
				$sel.append($('<option>').attr('value', BONUS_BID).text(BONUS_BID ? 'this bonus (#' + BONUS_BID + ')' : 'this bonus (new)'));
				$sel.prop('disabled', true);
				// a disabled select is not posted: carry the bid in a hidden input
				$tr.append($('<td>').append($sel).append($('<input>').attr({ type: 'hidden', name: 'pkg_leg[' + pos + '][bid]', value: BONUS_BID })));
			} else {
				$sel.append($('<option>').attr('value', '0').text('virtual (type the terms)'));
				$.each(PKG.rows, function (i, r) {
					if (parseInt(r.bid, 10) === BONUS_BID) { return; }
					$sel.append($('<option>').attr('value', r.bid).text(r.text));
				});
				if (leg.bid && !pkgRowByBid(leg.bid)) {
					// a linked row not in the casino's ladder list (beyond the
					// cap, or re-typed): keep the link visible rather than
					// silently turning the leg virtual
					$sel.append($('<option>').attr('value', leg.bid).text('#' + leg.bid + ' (linked row)'));
				}
				if (leg.bid) { $sel.val(String(leg.bid)); }
				$tr.append($('<td>').append($sel));
			}

			// ---- term cells
			var fields = [
				['pct', 'text', '%'], ['cap', 'text', 'up to'], ['min_dep', 'text', 'min'], ['max_dep', 'text', 'max'],
				['wr', 'text', 'x'], ['wr_meth', 'select', ''], ['max_cashout', 'text', 'cap'], ['max_cashout_kind', 'select', ''], ['spins', 'text', '0'], ['spins_wr', 'text', 'x win'], ['code', 'text', 'code']
			];
			for (var f = 0; f < fields.length; f++) {
				var name = 'pkg_leg[' + pos + '][' + fields[f][0] + ']';
				var $inp;
				if (fields[f][0] === 'max_cashout_kind') {
					// unit of the withdrawal cap: money / x deposit / x bonus (site rule: a cap without a unit asserts none)
					$inp = $('<select>').addClass('form-control fec-pkg-cell').attr('name', name)
						.append($('<option>').attr('value', '').text('\u2014'))
						.append($('<option>').attr('value', 'abs').text('money'))
						.append($('<option>').attr('value', 'x_dep').text('\u00d7 dep'))
						.append($('<option>').attr('value', 'x_bonus').text('\u00d7 bonus'));
					$inp.val(leg.max_cashout_kind || '');
				} else if (fields[f][1] === 'select') {
					$inp = $('<select>').addClass('form-control fec-pkg-cell').attr('name', name)
						.append($('<option>').attr('value', '').text('\u2014'))
						.append($('<option>').attr('value', 'b').text('bonus'))
						.append($('<option>').attr('value', 'bd').text('D+B'));
					$inp.val(leg.wr_meth || '');
				} else {
					$inp = $('<input>').attr({ type: 'text', name: name, placeholder: fields[f][2] }).addClass('form-control fec-pkg-cell');
					var v = leg[fields[f][0]];
					$inp.val(v === null || v === undefined ? '' : v);
				}
				$tr.append($('<td>').append($inp));
			}

			$tb.append($tr);
			syncPkgLegRow($tr, mine);
		}
	}

	// a leg linked to a row shows the row's live terms, greyed and
	// not posted (the server reads them from the row anyway)
	function syncPkgLegRow($tr, mine) {
		var bid = mine ? BONUS_BID : (parseInt($tr.find('.pkg-leg-bid').val(), 10) || 0);
		var row = mine ? null : pkgRowByBid(bid);
		var linked = mine || !!row;
		var pos = parseInt($tr.data('pos'), 10);

		$.each(LEG_FIELDS, function (i, fld) {
			if (fld === 'spins' || fld === 'spins_wr') { return; } // spins and their wagering stay editable (the row has no column for them)
			var $inp = $tr.find('[name="pkg_leg[' + pos + '][' + fld + ']"]');
			if (linked) {
				var v = '';
				if (mine) {
					if (fld === 'pct')     { v = previewVal('bonus'); }
					if (fld === 'cap')     { v = previewVal('maxbonus'); }
					if (fld === 'min_dep') { v = previewVal('mindep'); }
					if (fld === 'max_dep') { v = previewVal('maxdep'); }
					if (fld === 'wr')      { v = previewVal('wr'); }
					if (fld === 'wr_meth') { v = $('input[name="wrmethod"]:checked').val() || ''; }
					if (fld === 'code')    { v = previewVal('code'); }
					if (fld === 'max_cashout')      { v = previewVal('maxcash'); }
					if (fld === 'max_cashout_kind') { v = previewVal('maxcashkind'); }
				} else if (row) {
					v = row[fld] === null || row[fld] === undefined ? '' : row[fld];
				}
				$inp.val(v).prop('disabled', true).addClass('is-linked');
			} else {
				$inp.prop('disabled', false).removeClass('is-linked');
			}
		});
	}

	function onPkgChoice() {
		var v = $('#pkg-choice').val() || 'auto';
		var $panel = $('#pkg-panel');
		if (v.indexOf('pkg:') === 0) {
			$panel.removeClass('hide');
			fillPkgPanel(pkgById(v.substr(4)));
		} else if (v === 'new') {
			$panel.removeClass('hide');
			fillPkgPanel(null);
		} else {
			$panel.addClass('hide');
		}
	}

	function loadPackageOptions(cid) {
		if (!cid) {
			PKG = { packages: [], rows: [], this_pkg_id: 0 };
			renderPkgChoices();
			return;
		}
		var url = 'index.php?option=com_bonuses&task=actions.packageoptions'
				+ '&cid=' + encodeURIComponent(cid)
				+ '&exclude=' + BONUS_BID
				+ '&' + CSRF_TOKEN + '=1';
		$.ajax({
			url: url, type: 'GET', dataType: 'json',
			success: function (res) {
				if (!res || !res.ok) { return; }
				PKG = { packages: res.packages || [], rows: res.rows || [], this_pkg_id: res.this_pkg_id || 0 };
				renderPkgChoices();
			}
		});
	}

	function detectFromArticle() {
		if (window.CKEDITOR && CKEDITOR.instances) {
			for (var k in CKEDITOR.instances) {
				if (CKEDITOR.instances.hasOwnProperty(k)) { CKEDITOR.instances[k].updateElement(); }
			}
		}
		var $out = $('#pkg-detect-out').text('Reading the article\u2026');
		var data = {
			option: 'com_bonuses', task: 'actions.packagedetect',
			infos: $form.find('textarea[name="infos"]').val() || '',
			own_pct: previewVal('bonus'), own_cap: previewVal('maxbonus')
		};
		data[CSRF_TOKEN] = 1;
		$.ajax({
			url: 'index.php', type: 'POST', dataType: 'json', data: data,
			success: function (res) {
				if (!res || !res.ok) { $out.text((res && res.message) ? res.message : 'Detection failed.'); return; }
				var t = res.detected;
				$out.text('Article says: ' + (res.message || ''));
				if (!t.package) { return; }
				var dep = t.deposits || (t.legs_stated ? Object.keys(t.legs_stated).length : 0);
				if (dep > 1) { $('#pkg-deposits').val(dep); }
				if (t.headline && t.headline.raw) {
					$('#pkg-headline').val(t.headline.raw);
					if (t.headline.spins > 0) { $('#pkg-spins').val(t.headline.spins); }
				}
				if (t.track) { $('#pkg-title').val(t.track); }
				var legs = pkgLegsOfPanel();
				if (t.legs_stated) {
					$.each(t.legs_stated, function (pos, ls) {
						pos = parseInt(pos, 10);
						if (!legs[pos]) { legs[pos] = { bid: 0 }; }
						if (!legs[pos].bid) {
							legs[pos].pct = ls.pct; legs[pos].cap = ls.cap;
							if (ls.max_cashout !== null && ls.max_cashout !== undefined) { legs[pos].max_cashout = ls.max_cashout; legs[pos].max_cashout_kind = ls.max_cashout_kind || ''; }
						}
						// spins and their wagering belong to the leg whether or not a row is linked
						if (ls.spins) {
							legs[pos].spins = ls.spins;
							var sw = (ls.spins_wr !== null && ls.spins_wr !== undefined) ? ls.spins_wr
								: ((t.headline && t.headline.spins_wr !== null && t.headline.spins_wr !== undefined) ? t.headline.spins_wr : null);
							if (sw !== null && (legs[pos].spins_wr === null || legs[pos].spins_wr === undefined || legs[pos].spins_wr === '')) { legs[pos].spins_wr = sw; }
						}
					});
				}
				renderPkgLegs(legs);
			},
			error: function (xhr) { $out.text('Server error (' + xhr.status + ').'); }
		});
	}

	/* --------------------------------------------------------------
	 * Copy this bonus's terms into the virtual legs ("100% up to $200
	 * redeemable x5": one set of terms typed once). Rows linked to a
	 * bonus (this one, or another ladder row) are left alone, their
	 * cells are read from the row anyway. Default: only empty cells,
	 * only values this bonus has. Overwrite: every cell mirrors this
	 * bonus, blanks included. Spins are per leg and never copied.
	 * -------------------------------------------------------------- */
	var COPY_FIELDS = ['pct', 'cap', 'min_dep', 'max_dep', 'wr', 'wr_meth', 'max_cashout', 'max_cashout_kind', 'code'];

	function baseTerms() {
		return {
			pct:     previewVal('bonus'),
			cap:     previewVal('maxbonus'),
			min_dep: previewVal('mindep'),
			max_dep: previewVal('maxdep'),
			wr:      previewVal('wr'),
			wr_meth: $('input[name="wrmethod"]:checked').val() || '',
			code:    previewVal('code'),
			max_cashout:      previewVal('maxcash'),
			max_cashout_kind: previewVal('maxcashkind')
		};
	}

	function copyBaseToVirtualLegs(overwrite) {
		var base = baseTerms();
		var legs = 0, cells = 0;
		$('#pkg-legs tbody tr').each(function () {
			var $tr  = $(this);
			var pos  = parseInt($tr.data('pos'), 10);
			var $sel = $tr.find('.pkg-leg-bid');
			if ($sel.prop('disabled')) { return; }                      // this bonus's own leg
			if ((parseInt($sel.val(), 10) || 0) > 0) { return; }        // linked to another row
			legs++;
			$.each(COPY_FIELDS, function (i, fld) {
				var $inp = $tr.find('[name="pkg_leg[' + pos + '][' + fld + ']"]');
				if (!$inp.length || $inp.prop('disabled')) { return; }
				var cur = $.trim(String($inp.val() === null || $inp.val() === undefined ? '' : $inp.val()));
				var val = base[fld];
				if (!overwrite) {
					if (val === '' || cur !== '') { return; }
				}
				if (cur === val) { return; }
				$inp.val(val);
				cells++;
			});
		});
		var msg;
		if (!legs) {
			msg = 'No virtual leg to fill.';
		} else if (!cells) {
			msg = overwrite ? 'Already identical.' : 'Nothing empty to fill (tick overwrite to replace).';
		} else {
			msg = cells + ' cell' + (cells === 1 ? '' : 's') + ' filled across ' + legs + ' virtual leg' + (legs === 1 ? '' : 's') + '.';
		}
		$('#pkg-copy-out').text(msg);
	}

	$('#pkg-copy-base').on('click', function () {
		copyBaseToVirtualLegs($('#pkg-copy-overwrite').is(':checked'));
	});

	$('#pkg-choice').on('change', onPkgChoice);
	$('#pkg-deposits').on('change', function () { renderPkgLegs(); });
	$('#pkg-detect').on('click', detectFromArticle);
	$form.on('change', '.pkg-leg-bid', function () {
		var $tr = $(this).closest('tr');
		syncPkgLegRow($tr, parseInt($tr.data('pos'), 10) === pkgPosition(previewVal('type')));
	});
	// the "this bonus" leg mirrors the bonus fields as they are typed
	$form.on('change input', '[name="bonus"],[name="maxbonus"],[name="mindep"],[name="maxdep"],[name="wr"],[name="code"],input[name="wrmethod"],[name="maxcash"],[name="maxcashkind"]', function () {
		var myPos = pkgPosition(previewVal('type'));
		if (myPos > 0) { syncPkgLegRow($('#pkg-legs tbody tr[data-pos="' + myPos + '"]'), true); }
	});

	/* --------------------------------------------------------------
	 * Related-bonus select.
	 *
	 * Fetches the bonuses of one casino from the JSON endpoint
	 * actions.relatedoptions and rebuilds the #related-select
	 * options. Called at boot (edit mode, with the saved value to
	 * re-select) and on every casino change (selection resets - a
	 * related bonus only makes sense within its own casino).
	 *
	 * The first option ("No related bonus", value '') is permanent
	 * and never removed.
	 * -------------------------------------------------------------- */
	function loadRelated(cid, selectedVal) {
		var $sel = $('#related-select');

		// drop everything but the permanent empty option
		$sel.find('option:not(:first)').remove();
		$sel.val('');

		if (!cid) {
			return;
		}

		var url = 'index.php?option=com_bonuses&task=actions.relatedoptions'
				+ '&cid=' + encodeURIComponent(cid)
				+ '&exclude=' + BONUS_BID
				+ '&' + CSRF_TOKEN + '=1';

		$.ajax({
			url: url,
			type: 'GET',
			dataType: 'json',
			success: function (res) {
				if (!res || !res.ok || !res.options) {
					return;
				}
				$.each(res.options, function (i, o) {
					$sel.append(
						$('<option>').attr('value', o.bid).text(o.text)
					);
				});
				if (selectedVal) {
					$sel.val(String(selectedVal));
					// if the saved value vanished (target deleted),
					// .val() leaves the select on '' - which is the
					// honest representation of a dangling reference.
				}
				// the Related chip in the live preview can only be
				// built once the option text exists - refresh now.
				refreshPreview();
			}
		});
	}

	/* --------------------------------------------------------------
	 * Past-date soft warnings (start in the past, end before start).
	 * -------------------------------------------------------------- */
	var DATE_FMT = 'MM-DD-YYYY hh:mm A';

	function parseFormDate(val) {
		val = $.trim(val || '');
		if (val === '' || !window.moment) { return null; }
		var m = moment(val, DATE_FMT);
		return m.isValid() ? m : null;
	}

	function refreshDateWarnings() {
		var startM = parseFormDate($('input[name="start"]').val());
		var endM   = parseFormDate($('input[name="end"]').val());

		var pastCutoff = window.moment
			? moment().subtract(1, 'days').startOf('day')
			: null;

		if (startM && pastCutoff && startM.isBefore(pastCutoff)) {
			$('#fec-start-warning').show();
		} else {
			$('#fec-start-warning').hide();
		}

		if (startM && endM && endM.isBefore(startM)) {
			$('#fec-end-warning').show();
		} else {
			$('#fec-end-warning').hide();
		}
	}

	/* --------------------------------------------------------------
	 * LIVE PREVIEW
	 *
	 * Rebuilds the sticky #fec-preview card from the current form
	 * values. Pure client-side, no server roundtrip.
	 *
	 * Headline construction mirrors applyTypeRules() per type:
	 *   nd / fc       ->  "{cur}{bonus} {type label}"
	 *   fs            ->  "{bonus} {type label} @ {cur}{valspin}/spin"
	 *   fp            ->  "{cur}{bonus} {type label} for {n} min/hours"
	 *   default (dep) ->  "{bonus}% up to {cur}{maxbonus} - {type label}"
	 *
	 * Status badge mirrors the controller:
	 *   - an expired bonus STAYS expired on save (CURRENT_STATUS)
	 *   - otherwise autoStatus(): start in the future -> Pending,
	 *     anything else -> Active.
	 *
	 * Everything is injected with .text() - field values never become
	 * HTML, so nothing the user types can break out of the card.
	 * -------------------------------------------------------------- */
	function previewVal(name) {
		return $.trim($('[name="' + name + '"]').val() || '');
	}

	// "minute(s)" / "hour(s)" / "day(s)" for the free-play timer and the
	// rakeback boost window
	function fpUnit(den, n) {
		var u = (den === 'h') ? 'hour' : ((den === 'd') ? 'day' : 'minute');
		return u + (String(n) === '1' ? '' : 's');
	}

	function previewSelTxt(name) {
		var v = $('[name="' + name + '"]').val();
		if (v === null || v === undefined || String(v) === '') { return ''; }
		return $.trim($('[name="' + name + '"] option:selected').text() || '');
	}

	function refreshPreview() {
		var typeVal   = previewVal('type');
		var typeLabel = previewSelTxt('type');
		var casino    = previewSelTxt('casino');
		var cur       = previewSelTxt('currency'); // '' when "All"/none
		var bonus     = previewVal('bonus');
		var valspin   = previewVal('valxspin');
		var maxbonus  = previewVal('maxbonus');
		var fptime    = previewVal('fptime');
		var fptimeden = previewVal('fptimeden');

		// ---- headline ------------------------------------------------
		var title = '';

		if (typeVal !== '') {
			switch (typeVal) {
				case 'nd':
				case 'fc':
				case 'sweeps':
					title = (bonus !== '' ? cur + bonus + ' ' : '') + typeLabel;
					break;

				case 'fs':
					title = (bonus !== '' ? bonus + ' ' : '') + typeLabel;
					if (valspin !== '') {
						title += ' @ ' + cur + valspin + '/spin';
					}
					break;

				case 'fp':
					title = (bonus !== '' ? cur + bonus + ' ' : '') + typeLabel;
					if (fptime !== '') {
						title += ' for ' + fptime + ' ' + fpUnit(fptimeden, fptime);
					}
					break;

				case 'rb':
					// rakeback: "30% Rakeback Bonus", then the cap and the
					// boost window when stated
					title = (bonus !== '' ? bonus + '% ' : '') + typeLabel;
					if (maxbonus !== '') {
						title += ' up to ' + cur + maxbonus;
					}
					if (fptime !== '') {
						title += ' for ' + fptime + ' ' + fpUnit(fptimeden, fptime);
					}
					break;

				default:
					// deposit-match family
					var parts = '';
					if (bonus !== '')    { parts += bonus + '%'; }
					if (maxbonus !== '') {
						parts += (parts !== '' ? ' up to ' : 'Up to ') + cur + maxbonus;
					}
					title = (parts !== '') ? (parts + ' \u00B7 ' + typeLabel) : typeLabel;
					break;
			}
		}

		var $title = $('#fec-preview-title');

		if (title === '') {
			$title.addClass('fec-muted').text('Fill in the form to see the preview.');
		} else {
			$title.removeClass('fec-muted').text(title);
		}

		$('#fec-preview-casino').text(casino !== '' ? casino + ' \u2014 ' : '');

		// ---- exclusive tag -------------------------------------------
		var excl = $('input[name="exclusive"]:checked').val();
		$('#fec-preview-excl').toggle(excl === 'yes');

		// ---- predicted status badge ----------------------------------
		var $status = $('#fec-preview-status');
		var sKey, sTxt;

		if (CURRENT_STATUS === 'expired') {
			// the controller keeps expired bonuses expired on save
			sKey = 'expired';
			sTxt = 'Expired';
		} else {
			var startM = parseFormDate($('input[name="start"]').val());
			if (startM && window.moment && startM.isAfter(moment())) {
				sKey = 'pending';
				sTxt = 'Pending';
			} else {
				sKey = 'ok';
				sTxt = 'Active';
			}
		}

		$status
			.attr('class', 'fec-status fec-status-' + sKey)
			.text(sTxt)
			.show();

		// ---- meta chips ----------------------------------------------
		var chips = [];

		var code = previewVal('code');
		if (code !== '') { chips.push('Code: ' + code); }

		var wr = previewVal('wr');
		if (wr !== '') {
			var wrMeth = $('input[name="wrmethod"]:checked').val();
			chips.push('WR ' + wr + 'x ' + (typeVal === 'rb' ? 'Rakeback' : (wrMeth === 'bd' ? 'Bonus + Deposit' : 'Bonus')));
		}

		var mindep = previewVal('mindep');
		if (mindep !== '') { chips.push('Min dep ' + cur + mindep); }

		var maxbet = previewVal('maxbet');
		if (maxbet !== '') { chips.push('Max bet ' + cur + maxbet); }

		var cashable = $('input[name="cashable"]:checked').val();
		if (cashable === 'yes' || cashable === 'no') {
			chips.push('Cashable: ' + (cashable === 'yes' ? 'Yes' : 'No'));
		}

		var platform = previewSelTxt('platform');
		if (platform !== '' && platform !== 'All') { chips.push(platform); }

		var players = previewSelTxt('players');
		if (players !== '') { chips.push(players); }

		var startRaw = previewVal('start');
		var endRaw   = previewVal('end');
		if (startRaw !== '' || endRaw !== '') {
			chips.push(
				(startRaw !== '' ? 'From ' + startRaw : '')
				+ (startRaw !== '' && endRaw !== '' ? ' ' : '')
				+ (endRaw !== '' ? 'To ' + endRaw : '')
			);
		}

		var relatedTxt = previewSelTxt('related');
		if (relatedTxt !== '') { chips.push('Related: ' + relatedTxt); }

		var $meta = $('#fec-preview-meta').empty();
		$.each(chips, function (i, c) {
			$meta.append($('<span>').addClass('fec-preview-chip').text(c));
		});
	}

	/* --------------------------------------------------------------
	 * CKEditor on the content textarea
	 *
	 * protectedSource keeps blocks opaque - CKE leaves them alone
	 * when switching between WYSIWYG and source view, instead of
	 * trying to parse and "fix" their internals.
	 * -------------------------------------------------------------- */
	$form.find('textarea[name="infos"]').each(function () {
		$(this).ckeditor(function () {}, {
			toolbar: [
				['Source', '-', 'Bold', 'Italic', '-', 'NumberedList', 'BulletedList', '-', 'Link', 'Format']
			],
			extraPlugins: 'link,sourcearea',
			allowedContent: true,
			entities: false,
			extraAllowedContent:
				'div[itemscope,itemtype,itemprop,class];' +
				'details[open,itemscope,itemtype,itemprop,class];' +
				'summary[itemprop,class];p[itemprop,class];',
			protectedSource: [
				/<!--FECFAQ-->[\s\S]*?<!--\/FECFAQ-->/gi,
				/<script\b[^>]*\btype=("|')application\/ld\+json\1[^>]*>[\s\S]*?<\/script>/gi
			]
		});
	});

	/* --------------------------------------------------------------
	 * Deferred plugin initialisation (multiselect + datetimepicker).
	 * -------------------------------------------------------------- */
	var step2MultiselectDone = false;
	var step2DatepickerDone  = false;

	function initMultiselects() {
		if (step2MultiselectDone) { return; }
		if (!$.fn.multiselect) { return; }
		step2MultiselectDone = true;

		$form
			.find('[name="countriesok[]"],[name="countriesno[]"],[name="bankok[]"],[name="bankno[]"],[name="gamesok[]"],[name="gamesno[]"]')
			.multiselect({
				enableFiltering: true,
				enableCaseInsensitiveFiltering: true,
				filterCallback: function (element, query) {
					if (query === '' || query == null) { return true; }
					var q = String(query).toLowerCase();
					var $el = $(element);
					var hay = ($el.text() || '');
					hay += ' ' + ($el.find('label').text() || '');
					return hay.toLowerCase().indexOf(q) !== -1;
				},
				includeSelectAllOption: false,
				onDropdownShown: function (e) { adjustByScrollHeight(); },
				onDropdownHidden: function (e) { adjustByHeight(); },
				onChange: function () {
					var optionsok = $('[name="countriesok[]"]').val();
					var optionsno = $('[name="countriesno[]"]').val();
					if (optionsok !== null && optionsno !== null) {
						$form.formValidation('enableFieldValidators', 'countriesok[]')
							.formValidation('enableFieldValidators', 'countriesno[]');
						$form.formValidation('revalidateField', 'countriesok[]')
							.formValidation('revalidateField', 'countriesno[]');
					} else {
						$form.formValidation('enableFieldValidators', 'countriesok[]', false)
							.formValidation('enableFieldValidators', 'countriesno[]', false);
					}
				}
			});

		$form.on('click', '.btn-group > button.multiselect', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $bg = $(this).closest('.btn-group');
			var wasOpen = $bg.hasClass('open');
			$form.find('.btn-group.open').removeClass('open');
			if (!wasOpen) {
				$bg.addClass('open');
				$bg.find('.multiselect-search').focus();
				adjustByScrollHeight();
			} else {
				adjustByHeight();
			}
		});

		$form.on('click', '.multiselect-container', function (e) {
			e.stopPropagation();
		});

		$(document).on('click.fecMultiselect', function () {
			$form.find('.btn-group.open').removeClass('open');
		});

		$(document).on('keydown.fecMultiselect', function (e) {
			if (e.key === 'Escape' || e.keyCode === 27) {
				$form.find('.btn-group.open').removeClass('open');
			}
		});
	}

	function initDatePickers() {
		if (step2DatepickerDone) { return; }
		if (!$.fn.datetimepicker) { return; }
		step2DatepickerDone = true;

		$('#datetimepicker1, #datetimepicker2').datetimepicker({
			icons: {
				time: 'fa fa-clock-o',
				date: 'fa fa-calendar',
				up: 'fa fa-arrow-up',
				down: 'fa fa-arrow-down'
			},
			format: 'MM-DD-YYYY hh:mm A'
		});

		$('#datetimepicker1').on('dp.change dp.show', function () {
			$form.formValidation('revalidateField', 'start');
		});
		$('#datetimepicker2').on('dp.change dp.show', function () {
			$form.formValidation('revalidateField', 'end');
		});

		$('#datetimepicker1, #datetimepicker2').on('dp.change', refreshDateWarnings);
		$('#datetimepicker1, #datetimepicker2').on('dp.change', refreshPreview);
		$form.on('change blur', 'input[name="start"], input[name="end"]', refreshDateWarnings);

		refreshDateWarnings();
	}

	function initStep2Plugins() {
		initMultiselects();
		initDatePickers();
	}

	/* ---- 1. formValidation core ---------------------------------- */
	// keep the kind validator in sync while typing the cap value
	$form.on('input', 'input[name="maxcash"]', function () {
		try { $form.formValidation('revalidateField', 'maxcashkind'); } catch (e) {}
	});

	$form.formValidation({
		framework: 'bootstrap',
		icon: {
			valid: 'fa fa-check',
			invalid: 'fa fa-times',
			validating: 'fa fa-refresh'
		},
		fields: {
			casino: {
				onSuccess: function (e, data) { $form.find('#step-1').removeClass('hide'); },
				onError: function (e, data) { $form.find('#step-1').addClass('hide'); },
				validators: { notEmpty: { message: 'Please select a casino.' } }
			},
			type: {
				onSuccess: function (e, data) {
					$form.find('#step-2').removeClass('hide');
					setTimeout(initStep2Plugins, 0);
				},
				onError: function (e, data) { $form.find('#step-2').addClass('hide'); },
				validators: { notEmpty: { message: 'Please select a bonus type.' } }
			},
			bonus: {
				validators: {
					notEmpty: { message: 'Please enter a value.' },
					numeric: { message: 'The value is not a number' }
				}
			},
			mincash:  { validators: { numeric: { message: 'The value is not a number' } } },
			maxcash:  { validators: { numeric: { message: 'The value is not a number' } } },
			maxcashkind: {
				validators: {
					callback: {
						message: 'A positive max cashout needs its unit classified.',
						callback: function (value, validator) {
							// hard rule client-side (server stays soft: never lose a save):
							// cap > 0 requires abs / x_dep / x_bonus. Cap 0 or empty is free.
							var mc = parseFloat(validator.getFieldElements('maxcash').val());
							return !(isFinite(mc) && mc > 0) || value !== '';
						}
					}
				}
			},
			maxbonus: { validators: { numeric: { message: 'The value is not a number' } } },
			maxbet:   { validators: { numeric: { message: 'The value is not a number' } } },
			valxspin: { validators: { numeric: { message: 'The value is not a number' } } },
			wr: {
				validators: {
					notEmpty: { message: 'Please enter a value.' },
					numeric: { message: 'The value is not a number' }
				}
			},
			fptime: {
				enabled: false,
				validators: {
					numeric: { message: 'The value is not a number' },
					notEmpty: { message: 'Please, insert value' }
				}
			},
			fptimeden: {
				enabled: false,
				validators: { notEmpty: { message: 'Please, choose an option' } }
			},
			cashable:  { validators: { notEmpty: { message: 'The cashable option is required' } } },
			exclusive: { validators: { notEmpty: { message: 'Please select "Yes" for exclusive, "No" for regular promotion' } } },
			start: {
				validators: {
					date: { message: 'The start date is not valid', format: 'MM-DD-YYYY hh:mm A', max: '' }
				},
				onSuccess: function (e, data) {
					if (!data.fv.isValidField('end')) { data.fv.revalidateField('end'); }
				}
			},
			end: {
				validators: {
					date: { message: 'The end date is not valid', format: 'MM-DD-YYYY hh:mm A', min: '' }
				},
				onSuccess: function (e, data) {
					if (!data.fv.isValidField('start')) { data.fv.revalidateField('start'); }
				}
			},
			'countriesok[]': {
				enabled: false,
				validators: {
					callback: {
						message: 'Allowed and restricted countries overlap.',
						callback: function (value, validator, $field) {
							var optionsok = validator.getFieldElements('countriesok[]').val();
							var optionsno = validator.getFieldElements('countriesno[]').val();
							var common = $.grep(value, function (el) {
								return ($.inArray(el, optionsno) !== -1);
							});
							var error = (common == '') ? 0 : 1;
							return (error == 0 && optionsok !== null);
						}
					}
				}
			},
			'countriesno[]': {
				enabled: false,
				validators: {
					callback: {
						message: 'Allowed and restricted countries overlap.',
						callback: function (value, validator, $field) {
							var optionsno = validator.getFieldElements('countriesno[]').val();
							var optionsok = validator.getFieldElements('countriesok[]').val();
							var common = $.grep(value, function (el) {
								return ($.inArray(el, optionsok) !== -1);
							});
							var error = (common == '') ? 0 : 1;
							return (error == 0 && optionsno !== null);
						}
					}
				}
			},
			wrmethod: {
				enabled: false,
				validators: { notEmpty: { message: 'Please, select WR method' } }
			}
		}
	});

	/* ---- 2. type change -> conditional field visibility ---------- */
	$form.on('change', '[name="type"]', function () {
		applyTypeRules($(this).val());
	});

	/* ---- 3. iCheck skinning on the radio groups ----------------- */
	if ($.fn.iCheck) {
		$form.find('input[name="exclusive"], input[name="cashable"], input[name="wrmethod"]')
			.iCheck({
				tap: true,
				checkboxClass: 'icheckbox_square-red',
				radioClass: 'iradio_square-red'
			})
			.on('ifChanged', function (e) {
				$form.formValidation('revalidateField', $(this).attr('name'));
				refreshPreview();
			});
	}

	/* ---- 4. THE SUBMIT HANDLER ---------------------------------- */
	$form.on('success.form.fv', function (e) {
		e.preventDefault();

		if (window.CKEDITOR && CKEDITOR.instances) {
			for (var k in CKEDITOR.instances) {
				if (CKEDITOR.instances.hasOwnProperty(k)) {
					CKEDITOR.instances[k].updateElement();
				}
			}
		}

		var $banner = $('#fec-form-banner');
		var $submit = $form.find('button[type="submit"]');

		$submit.prop('disabled', true).text('Saving\u2026');
		$banner.removeClass('is-error is-success')
			.addClass('is-busy').text('Saving\u2026').show();

		$.ajax({
			url: 'index.php?option=com_bonuses&task=actions.save',
			type: 'POST',
			dataType: 'json',
			data: $form.serialize(),
			success: function (res) {
				if (res && res.ok) {
					$banner.removeClass('is-busy is-error').addClass('is-success')
						.text((res.message || 'Saved.') + ' Redirecting\u2026');
					window.location = res.redirect || 'index.php?option=com_bonuses&view=manage';
				} else {
					$banner.removeClass('is-busy is-success').addClass('is-error')
						.text((res && res.message) ? res.message : 'Save failed.');
					$submit.prop('disabled', false).text(SUBMIT_LABEL);
				}
			},
			error: function (xhr) {
				$banner.removeClass('is-busy is-success').addClass('is-error')
					.text('Server error (' + xhr.status + '). Nothing was saved.');
				$submit.prop('disabled', false).text(SUBMIT_LABEL);
			}
		});
	});

	/* ---- 5. casino change -> load the related-bonuses block ----- */
	$form.on('change', '[name="casino"]', function () {
		loadRelated($(this).val(), null);
		loadPackageOptions($(this).val());
	});

	/* ---- 6. currency change -> update the input-group labels ---- */
	$form.on('change', '[name="currency"]', function () {
		var txt = $('option:selected', this).text();
		$('.js-curr-val').text(txt);
	});

	/* ---- 7. Restore previous version: confirm before following -- */
	$('.js-confirm-restore').on('click', function (e) {
		var bid    = $(this).data('bid');
		var saved  = $(this).data('saved');
		var reason = $(this).data('reason');
		var msg    = 'Restore bonus #' + bid + ' to the version saved on '
				   + saved + ' (' + reason + ')?\n\n'
				   + 'Your current saved values will be overwritten with '
				   + 'that snapshot. This can itself be undone by clicking '
				   + 'Restore again.';
		if (!window.confirm(msg)) {
			e.preventDefault();
		}
	});

	/* ---- 8. live preview: refresh on any field change ----------- */
	// Delegated and broad on purpose: refreshPreview() is cheap (a
	// handful of DOM reads + text writes), so no debounce is needed.
	// iCheck-skinned radios fire through ifChanged (handler 3) and
	// the datetimepickers through dp.change (initDatePickers); this
	// catches everything else, including plain radios if iCheck is
	// ever absent.
	$form.on('change input', 'select, input[type="text"], input[type="radio"], input[type="url"]', refreshPreview);

	function adjustByHeight() {
		var $body = $('body'), $iframe = $body.data('iframe.fv');
		if ($iframe) { $iframe.height($body.height()); }
	}
	function adjustByScrollHeight() {
		var $body = $('body'), $iframe = $body.data('iframe.fv');
		if ($iframe) { $iframe.height($body.get(0).scrollHeight); }
	}

	$('input[name=wrmethod]').change(function () {
		switch ($(this).val()) {
			case 'b':  $('#wr-val').text('X Bonus'); break;
			case 'bd': $('#wr-val').text('X Bonus + Deposit'); break;
		}
	});

	/* --------------------------------------------------------------
	 * EDIT-MODE BOOTSTRAP
	 * -------------------------------------------------------------- */
	if (INITIAL_CID !== '' && INITIAL_CID !== '0') {
		$('#step-1').removeClass('hide');
	}
	if (INITIAL_TYPE !== '') {
		$('#step-2').removeClass('hide');
		applyTypeRules(INITIAL_TYPE);
		setTimeout(initStep2Plugins, 0);
	}

	var curTxt = $('[name="currency"] option:selected').text();
	if (curTxt) { $('.js-curr-val').text(curTxt); }

	var wrVal = $('input[name=wrmethod]:checked').val();
	if (wrVal === 'bd') { $('#wr-val').text('X Bonus + Deposit'); }

	if (INITIAL_CID !== '' && INITIAL_CID !== '0') {
		loadRelated(INITIAL_CID, RELATED_SEL || null);
	}

	// Welcome package section: edit mode has the payload inline; add
	// mode gets it when a casino is chosen.
	renderPkgChoices();
	if (!IS_EDIT && INITIAL_CID !== '' && INITIAL_CID !== '0') {
		loadPackageOptions(INITIAL_CID);
	}

	// First preview render. In edit mode the form is fully populated,
	// so the card is complete immediately (minus the Related chip,
	// which loadRelated() adds with its own refresh once the options
	// arrive). In add mode this just shows the placeholder.
	refreshPreview();
});
</script>