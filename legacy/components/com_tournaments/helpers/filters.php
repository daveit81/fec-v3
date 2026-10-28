<?php
//define constant
defined('_JEXEC') or die('Restricted access');
$jinput = JFactory::getApplication()->input;
$type = JRequest::getVar('type');
$filter = $jinput->get('filter');
$template = $jinput->get('template', '', 'CMD');




if ($template == 'newdesign') {
    
    $subfilters =  '<div class="filter p-sm-20 rounded">
            <button class="filter-btn d-sm-none w-100 text-left js-state" data-parent=".filter" type="button">
              <img src="images/icons/i-filter.svg" alt="">
              <strong class="mx-10 text-blue text-uppercase">Filter</strong>
            </button>

            <div class="position-relative">
              <div class="filter-content p-sm-0 pt-40 px-20 pb-20 mt-sm-0 mt-n20">
                <div class="row align-items-center">
                  <div class="col-sm col-12">
                    <ul class="news__nav row no-gutters bg-white">
                      <li class="col-sm col-12 mb-sm-0 mb-10">
                        <a class="d-flex flex-column justify-content-center px-5 text-center position-relative py-5 h-100 '.(isset($filter) ? '' : 'active').'" href="'.JRoute::_('index.php?option=com_tournaments&view=show').'">All</a>
                      </li>
                      <li class="col-sm col-12 mb-sm-0 mb-10">
                        <a class="d-flex flex-column justify-content-center px-5 text-center position-relative py-5 h-100 '.(isset($filter) && $filter == 'fr' ? 'active' : '').'" href="'.JRoute::_('index.php?option=com_tournaments&view=show&filter=fr').'">Freeroll</a>
                      </li>
                      <li class="col-sm col-12 mb-sm-0 mb-10">
                        <a class="d-flex flex-column justify-content-center px-5 text-center position-relative py-5 h-100 '.(isset($filter) && $filter == 'wf' ? 'active' : '').'" href="'.JRoute::_('index.php?option=com_tournaments&view=show&filter=wf').'">Whit Fee</a>
                      </li>
                    </ul>
                  </div>
                  
                  <div class="col-sm-auto d-sm-block d-none ml-sm-auto text-blue-dark font-size-sm">Showing <span class="text-white bg-blue rounded-pill py-5 px-8">'.$total.'</span> Tournaments - Live Feed</div>
                  
                </div>
            
        </div>
      </div>
    </div>';

} else {

$subfilters = '<div class="subfilters-wrapper">
<div class="subfilters">
  <a class="fbtn '.(isset($filter) ? '' : 'active').'" href="'.JRoute::_('index.php?option=com_tournaments&view=show').'">All</a>
  <a class="fbtn '.(isset($filter) && $filter == 'fr' ? 'active' : '').'" href="'.JRoute::_('index.php?option=com_tournaments&view=show&filter=fr').'">Freeroll</a>
  <a class="fbtn '.(isset($filter) && $filter == 'wf' ? 'active' : '').'" href="'.JRoute::_('index.php?option=com_tournaments&view=show&filter=wf').'">Whit Fee</a>
</div>
</div>';
}