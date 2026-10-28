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

class JElementGeoipcountrynames extends JElement {

	var   $_name = 'Geoipcountrynames';
	static $gi = null;
	static $options = null;

	function init(){
		if( !empty( $this->gi )){
			return;
		}

		$this->gi = new GeoIP;
		foreach( $this->gi->GEOIP_COUNTRY_CODES as $key=>$nesto ){
			if( !$nesto ) continue;
			$this->options[] = JHTML::_('select.option',$nesto, $this->gi->GEOIP_COUNTRY_NAMES[$key], 'value', 'text' );
		}
		//		print_r( $this->options );
		usort( $this->options, array('JElementGeoipcountrynames','sort_by_name'));
		//		print_r( $this->options );
	}

	function sort_by_name($a, $b){
		$al = strtolower($a->text);
        $bl = strtolower($b->text);
        if ($al == $bl) {
            return 0;
        }
        return ($al > $bl) ? +1 : -1;
	}

	function fetchElement($name, $value, &$node, $control_name) {
		$this->init();

		return JHTML::_('select.genericlist',  $this->options, ''.$control_name.'['.$name.'][] ', 
			'class="inputbox" size="15" multiple="multiple"',
			'value', 'text', $value, $control_name.$name);
	}

}


?>