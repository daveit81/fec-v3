<?php
defined('_JEXEC') or die;

/**
 * Verification launcher (default.php) — pick a casino to verify, or resume a draft.
 * Data: $this->scored, $this->openDrafts, $this->error.
 */
$scored = is_array($this->scored) ? $this->scored : array();
$drafts = is_array($this->openDrafts) ? $this->openDrafts : array();
$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$token = JHtml::_('form.token'); // hidden CSRF input
?>

<div class="fecx wrap fxa-page">

  <div class="fxa-head">
    <div class="eyebrow">Admin · FXShield verification</div>
    <h1>Start a first-party verification</h1>
    <p class="fxa-sub">Pick a casino, then work through the five steps —
    footer &amp; licence, RG tools, ADR, T&amp;C clauses, and a test withdrawal.
    Progress saves at every step, so you can stop after requesting the
    withdrawal and finish when it lands.</p>
  </div>

  <?php if ($this->error !== ''): ?>
    <div class="fxa-flash fxa-flash-error"><?php echo $h($this->error); ?></div>
  <?php endif; ?>

  <form method="post" action="<?php echo 'index.php?option=com_authors'; ?>" class="fxv-launch">
    <input type="hidden" name="option" value="com_authors">
    <input type="hidden" name="task" value="verification.start">
    <?php echo $token; ?>

    <label class="fxv-field">
      <span class="fxv-label">Casino</span>
      <select name="casino_id" required>
        <option value="">— select a scored casino —</option>
        <?php foreach ($scored as $c): ?>
          <option value="<?php echo (int)$c['casino_id']; ?>">
            <?php echo $h(($c['title'] !== null && $c['title'] !== '') ? $c['title'] : ('#' . $c['casino_id'])); ?>
            (<?php echo (int)$c['si_score']; ?> · conf <?php echo $h($c['si_confidence']); ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <button type="submit" class="btn btn-primary">Start verification</button>
  </form>

  <?php if (!empty($drafts)): ?>
    <h2 class="fxv-h2">Verifications</h2>
    <div class="fxa-tablewrap">
      <table class="fxa-table">
        <thead><tr>
          <th>Casino</th><th>Status</th><th class="num">Step</th>
          <th>Updated</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($drafts as $d):
          $title = ($d['casino_title'] !== null && $d['casino_title'] !== '')
                 ? $d['casino_title'] : ('#' . $d['casino_id']);
          $isDraft = ($d['status'] === 'draft');
          $resumeStep = max(1, min((int)$d['step'] + ($isDraft ? 1 : 0), 5));
          $url = 'index.php?option=com_authors&amp;view=verification&amp;id=' . (int)$d['id'] . '&amp;step=' . $resumeStep;
        ?>
          <tr>
            <td class="fxa-name"><?php echo $h($title); ?> <span class="fxa-cid">#<?php echo (int)$d['casino_id']; ?></span></td>
            <td>
              <span class="fxa-conf <?php echo $isDraft ? 'fxa-conf-C' : 'fxa-conf-A'; ?>">
                <?php echo $isDraft ? 'draft' : 'committed'; ?>
              </span>
            </td>
            <td class="num"><?php echo (int)$d['step']; ?>/5</td>
            <td><?php echo $h(date('j M Y, H:i', strtotime($d['updated_at']))); ?></td>
            <td>
              <?php if ($isDraft): ?>
                <a class="btn btn-ghost" href="<?php echo $url; ?>">Resume</a>
              <?php else: ?>
                <a class="btn btn-ghost" href="<?php echo $url; ?>">View</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div>
