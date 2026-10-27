<?php
defined('_JEXEC') or die('Restricted access');

/**
 * Watchout overrides per-cid (brand pages — view=casino).
 *
 * Stampati raw nel template default.php dentro la sezione .cb-watchout.
 * Se un cid NON e' presente qui (o questo file non esiste), il template usa
 * i 3 watchout di default hardcoded.
 *
 * Formato:
 *   <cid> => array(
 *     '<b>Title</b> &mdash; rest of the warning text.',
 *     '<b>Another</b> warning ...',
 *     ...
 *   ),
 *
 * Regole markup (HTML consentito):
 * - Solo <b>, <strong>, <a>, <em>, <span>. NIENTE <script>, <style>, <iframe>.
 * - Usa <b> per i lead-in evidenziati (CSS lo formatta col color ink + 600).
 * - Usa &mdash; (—), &ldquo;/&rdquo; (" "), &apos; (') per la tipografia.
 * - Mantieni i punti corti (~80-120 char per item).
 * - 2-4 items per cid e' il sweet spot. Troppi sopraffanno il messaggio.
 * - NON mettere mai input utente qui dentro. Editoriale controllato.
 *
 * Esempi (commentati). Decommenta + sostituisci col cid corretto.
 */

return array(

    /*
    // ==== ESEMPIO: brand con piattaforma SpinLogic Gaming ====
    1099 => array(
        '<b>SpinLogic cashout caps</b> usually matter more than the wagering &mdash; verify the cap before claiming.',
        '<b>Free spins on specific titles</b> only &mdash; check the eligible games in Details.',
        '<b>Account verification</b> required before first withdrawal &mdash; submit ID early.',
    ),

    // ==== ESEMPIO: brand crypto-first ====
    829 => array(
        '<b>Bonuses denominated in crypto</b> &mdash; cashout value depends on coin price at withdrawal.',
        '<b>Some offers list wagering as &ldquo;Not stated&rdquo;</b> &mdash; ask support before claiming.',
    ),
    */

);
