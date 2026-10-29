<?php
defined('_JEXEC') or die('Restricted access');

class Zebra_Pagination
{
    private $_properties = array(
        'always_show_navigation' => true,
        'avoid_duplicate_content' => true,
        'method' => 'get',
        'next' => '&raquo;',
        'padding' => true,
        'page' => 1,
        'page_set' => false,
        'navigation_position' => 'outside',
        'preserve_query_string' => 0,
        'previous' => '&laquo;',
        'records' => '',
        'records_per_page' => '',
        'reverse' => false,
        'selectable_pages' => 6,
        'total_pages' => 0,
        'trailing_slash' => true,
        'variable_name' => 'page',
    );

    function __construct()
    {
        $this->base_url();
    }

    public function always_show_navigation($show = true)
    {
        $this->_properties['always_show_navigation'] = $show;
    }

    public function avoid_duplicate_content($avoid_duplicate_content = true)
    {
        $this->_properties['avoid_duplicate_content'] = $avoid_duplicate_content;
    }

    public function base_url($base_url = '', $preserve_query_string = true)
    {
        $base_url = ($base_url == '' ? $_SERVER['REQUEST_URI'] : $base_url);
        $parsed_url = parse_url($base_url);
        $this->_properties['base_url'] = $parsed_url['path'];
        $this->_properties['base_url_query'] = isset($parsed_url['query']) ? $parsed_url['query'] : '';
        parse_str($this->_properties['base_url_query'], $this->_properties['base_url_query']);
        $this->_properties['preserve_query_string'] = $preserve_query_string;
    }

    public function get_page()
    {
        if (!$this->_properties['page_set']) {
            if ($this->_properties['method'] == 'url' &&
                preg_match('/\b' . preg_quote($this->_properties['variable_name']) . '([0-9]+)\b/i', $_SERVER['REQUEST_URI'], $matches) > 0) {
                $this->set_page((int)$matches[1]);
            } elseif (isset($_GET[$this->_properties['variable_name']])) {
                $this->set_page((int)$_GET[$this->_properties['variable_name']]);
            }
        }

        if ($this->_properties['reverse'] && $this->_properties['records'] == '') trigger_error('When showing records in reverse order you must specify the total number of records (by calling the "records" method) *before* the first use of the "get_page" method!', E_USER_ERROR);

        if ($this->_properties['reverse'] && $this->_properties['records_per_page'] == '') trigger_error('When showing records in reverse order you must specify the number of records per page (by calling the "records_per_page" method) *before* the first use of the "get_page" method!', E_USER_ERROR);

        $this->_properties['total_pages'] = $this->get_pages();

        if ($this->_properties['total_pages'] > 0) {
            if ($this->_properties['page'] > $this->_properties['total_pages']) $this->_properties['page'] = $this->_properties['total_pages'];
            elseif ($this->_properties['page'] < 1) $this->_properties['page'] = 1;
        }

        if (!$this->_properties['page_set'] && $this->_properties['reverse']) $this->set_page($this->_properties['total_pages']);

        return $this->_properties['page'];
    }

    public function get_pages()
    {
        // PORT: get_page() calls this before records()/records_per_page() are set ('' / ''): PHP 5.6
        // gave 0 (division by zero, silenced), PHP 8 throws on string operands. Same result, explicitly
        // (as in com_bonuses' copy of the class).
        $records = (int) $this->_properties['records'];
        $perPage = (int) $this->_properties['records_per_page'];
        return $perPage > 0 ? ceil($records / $perPage) : 0;
    }

    public function labels($previous = '&laquo;', $next = '&raquo;')
    {
        $this->_properties['previous'] = $previous;
        $this->_properties['next'] = $next;
    }

    public function method($method = 'get')
    {
        $this->_properties['method'] = (strtolower($method) == 'url' ? 'url' : 'get');
    }

    public function navigation_position($position)
    {
        $this->_properties['navigation_position'] = (in_array(strtolower($position), array('left', 'right')) ? strtolower($position) : 'outside');
    }

    public function padding($enabled = true)
    {
        $this->_properties['padding'] = $enabled;
    }

    public function records($records)
    {
        $this->_properties['records'] = (int)$records;
    }

    public function records_per_page($records_per_page)
    {
        $this->_properties['records_per_page'] = (int)$records_per_page;
    }

    public function render($return_output = false)
    {
        $this->get_page();

        if ($this->_properties['total_pages'] <= 1) return '';

        $output = '<nav class="fec-pg"><ul class="pagination pagination-lg">';

        if ($this->_properties['reverse']) {
            if ($this->_properties['navigation_position'] == 'left')
                $output .= $this->_show_next() . $this->_show_previous() . $this->_show_pages();
            elseif ($this->_properties['navigation_position'] == 'right')
                $output .= $this->_show_pages() . $this->_show_next() . $this->_show_previous();
            else
                $output .= $this->_show_next() . $this->_show_pages() . $this->_show_previous();
        } else {
            if ($this->_properties['navigation_position'] == 'left')
                $output .= $this->_show_previous() . $this->_show_next() . $this->_show_pages();
            elseif ($this->_properties['navigation_position'] == 'right')
                $output .= $this->_show_pages() . $this->_show_previous() . $this->_show_next();
            else
                $output .= $this->_show_previous() . $this->_show_pages() . $this->_show_next();
        }

        $output .= '</ul></nav>';

        if ($return_output) return $output;

        echo $output;

        // Aggiungi il tag canonical per tutte le pagine che puntano alla prima pagina
        if ($this->_properties['page'] > 1) {
            $canonical_url = $this->_build_uri(1);
            $doc = JFactory::getDocument();
            $doc->addHeadLink(htmlspecialchars($canonical_url), 'canonical');
        }
    }

    public function reverse($reverse = false)
    {
        $this->_properties['reverse'] = $reverse;
    }

    public function selectable_pages($selectable_pages)
    {
        $this->_properties['selectable_pages'] = (int)$selectable_pages;
    }

    public function set_page($page)
    {
        $this->_properties['page'] = (int)$page;
        if ($this->_properties['page'] < 1) $this->_properties['page'] = 1;
        $this->_properties['page_set'] = true;
    }

    public function trailing_slash($enabled)
    {
        $this->_properties['trailing_slash'] = $enabled;
    }

    public function variable_name($variable_name)
    {
        $this->_properties['variable_name'] = strtolower($variable_name);
    }

    private function _build_uri($page)
    {
        if ($this->_properties['method'] == 'url') {
            if (preg_match('/\b' . $this->_properties['variable_name'] . '([0-9]+)\b/i', $this->_properties['base_url'], $matches) > 0) {
                $url = str_replace('//', '/', preg_replace(
                    '/\b' . $this->_properties['variable_name'] . '([0-9]+)\b/i',
                    ($page == 1 ? '' : $this->_properties['variable_name'] . $page),
                    $this->_properties['base_url']
                ));
            } else {
                $url = rtrim($this->_properties['base_url'], '/') . '/' . ($this->_properties['variable_name'] . $page);
            }

            $url = rtrim($url, '/') . ($this->_properties['trailing_slash'] ? '/' : '');

            if (!$this->_properties['preserve_query_string']) {
                $query = implode('&', $this->_properties['base_url_query']);
            } else {
                $query = $_SERVER['QUERY_STRING'];
            }

            return $url . ($query != '' ? '?' . $query : '');
        } else {
            if (!$this->_properties['preserve_query_string']) {
                $query = $this->_properties['base_url_query'];
            } else {
                parse_str($_SERVER['QUERY_STRING'], $query);
            }

            if (!$this->_properties['avoid_duplicate_content'] || ($page != ($this->_properties['reverse'] ? $this->_properties['total_pages'] : 1))) {
                $query[$this->_properties['variable_name']] = $page;
            } elseif ($this->_properties['avoid_duplicate_content'] && $page == ($this->_properties['reverse'] ? $this->_properties['total_pages'] : 1)) {
                unset($query[$this->_properties['variable_name']]);
            }

            return htmlspecialchars(html_entity_decode($this->_properties['base_url']) . (!empty($query) ? '?' . urldecode(http_build_query($query)) : ''));

        }
    }

    private function _show_next()
    {
        $output = '';

        if ($this->_properties['always_show_navigation'] || $this->_properties['total_pages'] > $this->_properties['selectable_pages']) {
            $output = '<li ' . ($this->_properties['page'] == $this->_properties['total_pages']
                ? ' class="pagination-next disabled"' 
                : ' class="pagination-next"') . '>
                <a href="' . ($this->_properties['page'] == $this->_properties['total_pages'] 
                ? 'javascript:void(0)' 
                : $this->_build_uri($this->_properties['page'] + 1)) . '">' .
                ($this->_properties['reverse'] ? $this->_properties['previous'] : $this->_properties['next']) . '</a></li>';

            if ($this->_properties['page'] < $this->_properties['total_pages']) {
                $burl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://') . '' . $_SERVER['SERVER_NAME'];
                $prevurl = $burl . '' . $this->_build_uri($this->_properties['page'] + 1);
                $doc = JFactory::getDocument();
                $doc->addHeadLink($prevurl, 'next', 'rel');
            }
        }
        return $output;
    }

    private function _show_previous()
    {
        $output = '';

        if ($this->_properties['always_show_navigation'] || $this->_properties['total_pages'] > $this->_properties['selectable_pages']) {
            $output = '<li' . ($this->_properties['page'] == 1 ? ' class="pagination-next disabled"' : ' class="pagination-next"') . '>
            <a href="' . ($this->_properties['page'] == 1 ? 'javascript:void(0)' : $this->_build_uri($this->_properties['page'] - 1)) . '">' .
                ($this->_properties['reverse'] ? $this->_properties['next'] : $this->_properties['previous']) . '</a></li>';

            if ($this->_properties['page'] > 1) {
                $burl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://') . '' . $_SERVER['SERVER_NAME'];
                $prevurl = $burl . '' . $this->_build_uri($this->_properties['page'] - 1);
                $doc = JFactory::getDocument();
                $doc->addHeadLink($prevurl, 'prev', 'rel');
            }
        }
        return $output;
    }

    private function _show_pages()
    {
        $output = '';

        if ($this->_properties['total_pages'] <= $this->_properties['selectable_pages']) {
            for ($i = ($this->_properties['reverse'] ? $this->_properties['total_pages'] : 1);
                ($this->_properties['reverse'] ? $i >= 1 : $i <= $this->_properties['total_pages']);
                ($this->_properties['reverse'] ? $i-- : $i++)) {
                $output .= '<li' . ($this->_properties['page'] == $i ? ' class="active"' : '') . '>
                <a href="' . $this->_build_uri($i) . '">' .
                    ($this->_properties['padding'] ? str_pad($i, strlen($this->_properties['total_pages']), '0', STR_PAD_LEFT) : $i) .
                    '</a></li>';
            }
        } else {
            $output .= '<li' . ($this->_properties['page'] == ($this->_properties['reverse'] ? $this->_properties['total_pages'] : 1) 
                ? ' class="active"' 
                : '') . '>
                <a href="' . $this->_build_uri($this->_properties['reverse'] ? $this->_properties['total_pages'] : 1) . '">' .
                ($this->_properties['padding'] 
                    ? str_pad(($this->_properties['reverse'] ? $this->_properties['total_pages'] : 1), 
                        strlen($this->_properties['total_pages']), '0', STR_PAD_LEFT) 
                    : ($this->_properties['reverse'] ? $this->_properties['total_pages'] : 1)) .
                '</a></li>';

            $adjacent = floor(($this->_properties['selectable_pages'] - 3) / 2);
            if ($adjacent == 0) $adjacent = 1;

            $scroll_from = ($this->_properties['reverse'] 
                ? $this->_properties['total_pages'] - ($this->_properties['selectable_pages'] - $adjacent) + 1 
                : $this->_properties['selectable_pages'] - $adjacent);

            $starting_page = ($this->_properties['reverse'] ? $this->_properties['total_pages'] - 1 : 2);

            if (
                ($this->_properties['reverse'] && $this->_properties['page'] <= $scroll_from) ||
                (!$this->_properties['reverse'] && $this->_properties['page'] >= $scroll_from)
            ) {
                $starting_page = $this->_properties['page'] + ($this->_properties['reverse'] ? $adjacent : -$adjacent);

                if (
                    ($this->_properties['reverse'] && $starting_page < ($this->_properties['selectable_pages'] - 1)) ||
                    (!$this->_properties['reverse'] && $this->_properties['total_pages'] - $starting_page < ($this->_properties['selectable_pages'] - 2))
                ) {
                    if ($this->_properties['reverse']) $starting_page = $this->_properties['selectable_pages'] - 1;
                    else $starting_page -= ($this->_properties['selectable_pages'] - 2) - ($this->_properties['total_pages'] - $starting_page);
                }

                $output .= '<li><span>&hellip;</span></li>';
            }

            $ending_page = $starting_page + (($this->_properties['reverse'] ? -1 : 1) * ($this->_properties['selectable_pages'] - 3));

            if ($this->_properties['reverse'] && $ending_page < 2) $ending_page = 2;
            elseif (!$this->_properties['reverse'] && $ending_page > $this->_properties['total_pages'] - 1) $ending_page = $this->_properties['total_pages'] - 1;

            for ($i = $starting_page; $this->_properties['reverse'] ? $i >= $ending_page : $i <= $ending_page; $this->_properties['reverse'] ? $i-- : $i++)
                $output .= '<li' . ($this->_properties['page'] == $i ? ' class="active"' : '') . '>
                <a href="' . $this->_build_uri($i) . '">' .
                    ($this->_properties['padding'] ? str_pad($i, strlen($this->_properties['total_pages']), '0', STR_PAD_LEFT) : $i) .
                    '</a></li>';

            if (
                ($this->_properties['reverse'] && $ending_page > 2) ||
                (!$this->_properties['reverse'] && $this->_properties['total_pages'] - $ending_page > 1)
            ) $output .= '<li><span>&hellip;</span></li>';

            $output .= '<li' . ($this->_properties['page'] == $i ? ' class="active"' : '') . '>
            <a href="' . $this->_build_uri($this->_properties['reverse'] ? 1 : $this->_properties['total_pages']) . '">' .
                ($this->_properties['padding'] ? str_pad(($this->_properties['reverse'] ? 1 : $this->_properties['total_pages']), 
                    strlen($this->_properties['total_pages']), '0', STR_PAD_LEFT) 
                : ($this->_properties['reverse'] ? 1 : $this->_properties['total_pages'])) .
                '</a></li>';
        }

        return $output;
    }
}
?>
