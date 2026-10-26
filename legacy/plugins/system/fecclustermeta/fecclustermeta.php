<?php
/**
 * System plugin: re-applies the casino-cluster page title + meta description
 * AFTER sh404SEF (which otherwise overrides whatever the component set on the
 * document). The cluster renderer stashes the computed values on the
 * application registry; this plugin writes them into the final HTML buffer.
 *
 * Order this plugin LAST among System plugins (below sh404SEF) so it wins.
 *
 * 1.1 (2026-10-13): the title and the description are inserted through
 * preg_replace_callback, never as a replacement string. With the 1.0
 * preg_replace a title such as "...$205k in jackpot winnings" came out as
 * "...5k": "$20" was read as a backreference. Also used by the Casino Bonus
 * Blog (fec_blog.php >= 1.0.8), whose listing title sh404SEF swapped for the
 * menu item title.
 */
defined('_JEXEC') or die;

class plgSystemFecclustermeta extends JPlugin
{
    public function onAfterRender()
    {
        $app = JFactory::getApplication();
        if ($app->isAdmin()) {
            return;
        }

        $title = (string) $app->get('fec_cluster_meta_title', '');
        $desc  = (string) $app->get('fec_cluster_meta_desc', '');
        if ($title === '' && $desc === '') {
            return; // not a cluster page (renderer didn't stash anything)
        }

        $body = JResponse::getBody();
        if ($body === '' || stripos($body, '</title>') === false) {
            return;
        }

        if ($title !== '') {
            $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $body = $this->replaceOnce('#<title>.*?</title>#is', '<title>' . $t . '</title>', $body);
            $body = $this->setMeta($body, 'property', 'og:title', $t);
            $body = $this->setMeta($body, 'name', 'twitter:title', $t);
        }

        if ($desc !== '') {
            $d = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
            $body = $this->setMeta($body, 'name', 'description', $d);
            $body = $this->setMeta($body, 'property', 'og:description', $d);
            $body = $this->setMeta($body, 'name', 'twitter:description', $d);
        }

        JResponse::setBody($body);
    }

    /**
     * Replace an existing <meta {attr}="{key}" ...> tag, or inject one after <title>.
     */
    protected function setMeta($body, $attr, $key, $val)
    {
        $tag     = '<meta ' . $attr . '="' . $key . '" content="' . $val . '" />';
        $pattern = '#<meta\s+' . $attr . '=(["\'])' . preg_quote($key, '#') . '\1[^>]*/?>#i';
        if (preg_match($pattern, $body)) {
            return $this->replaceOnce($pattern, $tag, $body);
        }
        return $this->replaceOnce('#</title>#i', '</title>' . "\n\t" . $tag, $body);
    }

    /**
     * First match of $pattern replaced by the literal $literal: a callback
     * returns the string as it is, so "$1", "\\1" or "$20" inside a title or a
     * description are text, not backreferences.
     */
    protected function replaceOnce($pattern, $literal, $body)
    {
        $out = preg_replace_callback($pattern, function ($m) use ($literal) { return $literal; }, $body, 1);
        return ($out === null) ? $body : $out;
    }
}