<?php
/**
 * 403 — editorial dashboard access denied.
 * Renders when AuthorsModelDashboard::isAuthorized() returns false.
 */
defined('_JEXEC') or die;
?>
<div class="fecx">
  <div class="dash-403">
    <h1>Editorial access only</h1>
    <p>This dashboard is restricted to the FreeExtraChips editorial team.</p>
    <p><a href="<?php echo JURI::root(true); ?>/">Back to homepage</a></p>
  </div>
</div>
<style>
.fecx .dash-403{max-width:560px;margin:80px auto;padding:32px;text-align:center}
.fecx .dash-403 h1{font-family:Georgia,serif;font-size:28px;margin:0 0 16px}
.fecx .dash-403 p{font-size:15px;color:#555;line-height:1.6}
.fecx .dash-403 a{color:#0b6b62;text-decoration:none;font-weight:600}
</style>
