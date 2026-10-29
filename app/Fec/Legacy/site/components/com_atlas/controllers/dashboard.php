<?php
/**
 * AtlasControllerDashboard
 *
 * AJAX endpoint for all reads/writes from the Atlas editorial dashboard.
 * Every task returns JSON. Every task hard-checks isAuthorized() first;
 * every mutating task requires a valid Joomla form token.
 *
 * Tasks:
 *   stats             — header counters
 *   listEntities      — filtered entity list (q, type, status, limitstart)
 *   fetchEntity       — entity + licences + brand links + events + sources
 *   saveEntity        — insert/update entity (never touches published)
 *   setPublished      — publish gate (R1)
 *   saveLicence       — insert/update licence (R2 two-source rule)
 *   deleteLicence
 *   fetchLicenceSources
 *   saveBrandLink     — insert/update link (R3 one current operator)
 *   closeBrandLink    — era close (date_to)
 *   deleteBrandLink
 *   searchReviews     — review picker autocomplete
 *   listSources / saveSource / linkSource / unlinkSource
 *   saveEvent / deleteEvent
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

require_once JPATH_COMPONENT . '/models/dashboard.php';

class AtlasControllerDashboard extends JController
{
    /**
     * Common preamble for all tasks: auth + JSON + cache headers.
     * The X-LiteSpeed-Cache-Control header keeps LiteSpeed from caching
     * AJAX responses (house rule for all dashboard endpoints).
     */
    protected function gateAndJson($checkToken = true)
    {
        while (ob_get_level() > (defined('FEC_OB_BASE') ? FEC_OB_BASE : 0)) { @ob_end_clean(); } // PORT: keep the runner's buffer
        fec_header('Content-Type: application/json; charset=utf-8');
        fec_header('Cache-Control: no-store, must-revalidate');
        fec_header('X-LiteSpeed-Cache-Control: no-cache');

        if (!AtlasModelDashboard::isAuthorized()) {
            echo json_encode(array('ok' => false, 'error' => 'forbidden'));
            JFactory::getApplication()->close();
        }
        if ($checkToken) {
            if (!JRequest::checkToken('request') && !JRequest::checkToken('post')) {
                echo json_encode(array('ok' => false, 'error' => 'invalid_token'));
                JFactory::getApplication()->close();
            }
        }
    }

    protected function out($payload)
    {
        echo json_encode($payload);
        JFactory::getApplication()->close();
    }

    // -----------------------------------------------------------------------
    // Reads
    // -----------------------------------------------------------------------

    public function stats()
    {
        $this->gateAndJson(false);
        $model = new AtlasModelDashboard();
        $this->out(array('ok' => true, 'stats' => $model->getStats()));
    }

    public function listEntities()
    {
        $this->gateAndJson(false);
        $model  = new AtlasModelDashboard();
        $q      = JRequest::getString('q', '');
        $type   = JRequest::getCmd('type', '');
        $status = JRequest::getCmd('status', '');
        $start  = max(0, JRequest::getInt('limitstart', 0));
        $this->out(array(
            'ok'       => true,
            'total'    => $model->getEntitiesCount($q, $type, $status),
            'entities' => $model->getEntitiesList($q, $type, $status, null, $start),
        ));
    }

    public function fetchEntity()
    {
        $this->gateAndJson(false);
        $id = JRequest::getInt('id', 0);
        $model = new AtlasModelDashboard();
        $entity = $model->getEntity($id);
        if (!$entity) $this->out(array('ok' => false, 'error' => 'not_found'));
        $this->out(array(
            'ok'          => true,
            'entity'      => $entity,
            'licences'    => $model->getEntityLicences($id),
            'brand_links' => $model->getEntityBrandLinks($id),
            'events'      => $model->getEntityEvents($id),
            'sources'     => $model->getEntitySources($id),
        ));
    }

    public function fetchLicenceSources()
    {
        $this->gateAndJson(false);
        $model = new AtlasModelDashboard();
        $this->out(array('ok' => true, 'sources' => $model->getLicenceSources(JRequest::getInt('id', 0))));
    }

    public function searchReviews()
    {
        $this->gateAndJson(false);
        $model = new AtlasModelDashboard();
        $this->out(array('ok' => true, 'results' => $model->searchReviews(JRequest::getString('q', ''))));
    }

    public function listSources()
    {
        $this->gateAndJson(false);
        $model = new AtlasModelDashboard();
        $this->out(array('ok' => true, 'sources' => $model->listSources(JRequest::getString('q', ''))));
    }

    public function driftReport()
    {
        $this->gateAndJson(false);
        $model = new AtlasModelDashboard();
        $this->out(array('ok' => true, 'drift' => $model->getDriftReport()));
    }

    // -----------------------------------------------------------------------
    // Writes
    // -----------------------------------------------------------------------

    public function saveEntity()
    {
        $this->gateAndJson(true);
        $model = new AtlasModelDashboard();
        $data = array(
            'id'                  => JRequest::getInt('id', 0),
            'slug'                => JRequest::getString('slug', ''),
            'name'                => JRequest::getString('name', ''),
            'entity_type'         => JRequest::getCmd('entity_type', 'operator'),
            'jurisdiction'        => JRequest::getString('jurisdiction', ''),
            'reg_number'          => JRequest::getString('reg_number', ''),
            'reg_address'         => JRequest::getString('reg_address', ''),
            'status'              => JRequest::getCmd('status', 'unverified'),
            'status_date'         => JRequest::getString('status_date', ''),
            'successor_entity_id' => JRequest::getInt('successor_entity_id', 0),
            'editor_notes'        => JRequest::getString('editor_notes', ''),
            'public_notes'        => JRequest::getString('public_notes', ''),
        );
        $this->out($model->saveEntity($data));
    }

    public function setPublished()
    {
        $this->gateAndJson(true);
        $model = new AtlasModelDashboard();
        $this->out($model->setPublished(JRequest::getInt('id', 0), JRequest::getInt('published', 0)));
    }

    public function saveLicence()
    {
        $this->gateAndJson(true);
        $model = new AtlasModelDashboard();
        $data = array(
            'id'             => JRequest::getInt('id', 0),
            'entity_id'      => JRequest::getInt('entity_id', 0),
            'regulator'      => JRequest::getString('regulator', ''),
            'licence_number' => JRequest::getString('licence_number', ''),
            'licence_type'   => JRequest::getString('licence_type', ''),
            'status'         => JRequest::getCmd('status', 'unverified'),
            'valid_from'     => JRequest::getString('valid_from', ''),
            'valid_to'       => JRequest::getString('valid_to', ''),
            'confidence'     => JRequest::getCmd('confidence', 'probable'),
            'editor_notes'   => JRequest::getString('editor_notes', ''),
        );
        $this->out($model->saveLicence($data));
    }

    public function deleteLicence()
    {
        $this->gateAndJson(true);
        $model = new AtlasModelDashboard();
        $this->out($model->deleteLicence(JRequest::getInt('id', 0)));
    }

    public function saveBrandLink()
    {
        $this->gateAndJson(true);
        $model = new AtlasModelDashboard();
        $data = array(
            'id'            => JRequest::getInt('id', 0),
            'entity_id'     => JRequest::getInt('entity_id', 0),
            'contentid'     => JRequest::getInt('contentid', 0),
            'relation_type' => JRequest::getCmd('relation_type', 'operator'),
            'date_from'     => JRequest::getString('date_from', ''),
            'date_to'       => JRequest::getString('date_to', ''),
            'confidence'    => JRequest::getCmd('confidence', 'probable'),
            'notes'         => JRequest::getString('notes', ''),
        );
        $this->out($model->saveBrandLink($data));
    }

    public function closeBrandLink()
    {
        $this->gateAndJson(true);
        $model = new AtlasModelDashboard();
        $this->out($model->closeBrandLink(JRequest::getInt('id', 0), JRequest::getString('date_to', '')));
    }

    public function deleteBrandLink()
    {
        $this->gateAndJson(true);
        $model = new AtlasModelDashboard();
        $this->out($model->deleteBrandLink(JRequest::getInt('id', 0)));
    }

    public function saveSource()
    {
        $this->gateAndJson(true);
        $model = new AtlasModelDashboard();
        $data = array(
            'id'          => JRequest::getInt('id', 0),
            'source_type' => JRequest::getCmd('source_type', 'other'),
            'url'         => JRequest::getString('url', ''),
            'description' => JRequest::getString('description', ''),
            'captured_at' => JRequest::getString('captured_at', ''),
        );
        $this->out($model->saveSource($data));
    }

    public function linkSource()
    {
        $this->gateAndJson(true);
        $model = new AtlasModelDashboard();
        $this->out($model->linkSource(
            JRequest::getInt('source_id', 0),
            JRequest::getCmd('ref_table', ''),
            JRequest::getInt('ref_id', 0)
        ));
    }

    public function unlinkSource()
    {
        $this->gateAndJson(true);
        $model = new AtlasModelDashboard();
        $this->out($model->unlinkSource(JRequest::getInt('link_id', 0)));
    }

    public function saveEvent()
    {
        $this->gateAndJson(true);
        $model = new AtlasModelDashboard();
        $data = array(
            'id'          => JRequest::getInt('id', 0),
            'entity_id'   => JRequest::getInt('entity_id', 0),
            'event_date'  => JRequest::getString('event_date', ''),
            'event_type'  => JRequest::getCmd('event_type', 'other'),
            'title'       => JRequest::getString('title', ''),
            'description' => JRequest::getString('description', ''),
            'confidence'  => JRequest::getCmd('confidence', 'probable'),
            'published'   => JRequest::getInt('published', 0),
        );
        $this->out($model->saveEvent($data));
    }

    public function deleteEvent()
    {
        $this->gateAndJson(true);
        $model = new AtlasModelDashboard();
        $this->out($model->deleteEvent(JRequest::getInt('id', 0)));
    }
}
