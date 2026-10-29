<?php
/**
 * Operator view — single published entity profile (/operators/<slug>).
 *
 * Unpublished or unknown slugs 404 (no soft redirect: the register must not
 * hint at unpublished research). JSON-LD Organization is emitted with only
 * the fields the register actually holds — nothing invented.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/models/publicbase.php';
require_once JPATH_COMPONENT . '/helpers/icons.php';
require_once JPATH_COMPONENT . '/helpers/route.php';

class AtlasViewOperator extends JView
{
    public $entity   = null;
    public $licences = array();
    public $brands   = array();
    public $events   = array();
    public $sources  = array();
    public $cluster  = null;

    public function display($tpl = null)
    {
        $slug = JRequest::getCmd('slug', '');
        $model = new AtlasModelOperator();
        $this->entity = $model->getEntityBySlug($slug);

        if (!$this->entity) {
            JError::raiseError(404, JText::_('Operator profile not found'));
            return;
        }

        $id = (int)$this->entity->id;
        $this->licences = $model->getLicences($id);
        $this->brands   = $model->getBrandLinks($id);
        $this->events   = $model->getEvents($id);
        $this->sources  = $model->getSources($id);
        $this->cluster  = AtlasModelPublicbase::getClusterFor($this->entity->name);

        $doc = JFactory::getDocument();
        $name = $this->entity->name;
        $doc->setTitle($name . ' — Operator Profile | Casino Operator Atlas');

        $desc = trim((string)$this->entity->public_notes);
        if ($desc === '') {
            $desc = $name . ': registration, licences and the casino brands it operates, with sourced confidence grades and ownership history.';
        }
        $doc->setDescription(function_exists('mb_substr') ? mb_substr($desc, 0, 300) : substr($desc, 0, 300));
        // Canonical URL - deterministic, from atlas_route (the same content
        // also answers on the non-SEF URL, so the canonical matters).
        $rel = atlas_route('index.php?option=com_atlas&view=operator&slug=' . $slug);
        $rootPath = JURI::root(true);
        if ($rootPath !== '' && strpos($rel, $rootPath) === 0) {
            $rel = substr($rel, strlen($rootPath));
        }
        $doc->addCustomTag('<link href="' . htmlspecialchars(rtrim(JURI::root(), '/') . $rel, ENT_QUOTES, 'UTF-8') . '" rel="canonical" />');


        // Assets.
        // Design-system stylesheet (me-page / au-hero / me-hub-card) reused from
        // com_authors for full site coherence, then the slim atlas-specific one.
        $methCss = JPATH_SITE . '/components/com_authors/assets/methodology.css';
        if (file_exists($methCss)) {
            $doc->addStyleSheet(JURI::root(true) . '/components/com_authors/assets/methodology.css?v=' . (@filemtime($methCss) ? filemtime($methCss) : 1));
        }
        $base       = JURI::root(true) . '/components/com_atlas/assets/';
        $assetsPath = JPATH_SITE . '/components/com_atlas/assets/';
        $v = @filemtime($assetsPath . 'atlas.css');
        if (!$v) $v = 1;
        $doc->addStyleSheet($base . 'atlas.css?v=' . $v);

        // JSON-LD Organization — only fields the register holds.
        $ld = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $name,
            'legalName'=> $name,
        );
        if ($this->entity->reg_number) {
            $ld['identifier'] = array(
                '@type'      => 'PropertyValue',
                'propertyID' => 'Company registration number',
                'value'      => $this->entity->reg_number,
            );
        }
        if ($this->entity->jurisdiction) {
            $ld['address'] = array(
                '@type'          => 'PostalAddress',
                'addressCountry' => strtoupper($this->entity->jurisdiction),
            );
        }
        if (trim((string)$this->entity->public_notes) !== '') {
            $ld['description'] = trim((string)$this->entity->public_notes);
        }
        $doc->addCustomTag('<script type="application/ld+json">'
            . json_encode($ld) . '</script>');

        parent::display($tpl);
    }
}
