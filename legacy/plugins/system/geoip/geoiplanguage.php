<?php
  /**
   * @version SVN: $Id$
   * @package    plg_geoip
   * @subpackage Content
   * @author     odmoriSe {@link http://portfolio.odmorise.com}
   * @author     Created on Avgust-2010
   */

  /*
   * @license		GNU/GPL, see LICENSE.php
   * This program is free software; you can redistribute it and/or modify
   * it under the terms of the GNU General Public License as published by
   * the Free Software Foundation; either version 2 of the License, or
   * (at your option) any later version.
   * 
   * This program is distributed in the hope that it will be useful,
   * but WITHOUT ANY WARRANTY; without even the implied warranty of
   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   * GNU General Public License for more details.
   * 
   * You should have received a copy of the GNU General Public License
   * along with this program; if not, write to the Free Software
   * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
   */

  //-- No direct access
defined('_JEXEC') or die('=;)');

require_once(dirname(__FILE__) . "/geoip.inc.php");

class JElementGeoiplanguage extends JElement {

  var   $_name = 'Geoiplanguage';
  static $languages = null;

  function init(){
	  if( !empty( $this->languages )){
		  return;
	  }

	  $db =& JFactory::getDBO();
	  $query = "SELECT * FROM #__languages WHERE active = 1 ORDER BY ordering";
	  $db->setQuery( $query );
	  $languages = $db->loadObjectList();

	  $this->languages[] = JHTML::_('select.option','0', 'Browser settings', 'value', 'text' );;
	  foreach( $languages as $lang ){
		  $this->languages[] = JHTML::_('select.option',$lang->shortcode, $lang->name, 'value', 'text' );;
	  }
  }

  function fetchElement($name, $value, &$node, $control_name) {
	  $this->init();


	  $size = count( $this->languages );
	  return JHTML::_('select.genericlist',  $this->languages, ''.$control_name.'['.$name.'][] ', 
		  'class="inputbox" ',
		  'value', 'text', $value, $control_name.$name);
  }

}

?>