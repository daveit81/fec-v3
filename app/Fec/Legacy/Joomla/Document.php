<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JDocument (HTML) as a collector: the component sets the title, the meta
 * tags, the stylesheets, the scripts, the head links and the custom tags,
 * and the runner hands them to the Blade layout, which prints them in the
 * <head> in the same order Joomla 2.5 did (meta, links, stylesheets,
 * scripts, declarations, custom tags). Nothing is printed from here.
 */
class Document
{
    public string $title = '';

    public string $description = '';

    public string $base = '';

    public string $link = '';

    /** @var array<string, string> name => content (meta name="...") */
    public array $metaTags = [];

    /** @var array<string, string> http-equiv => content */
    public array $httpEquiv = [];

    /** @var list<array{href: string, relation: string, relType: string, attribs: array<string, string>}> */
    public array $headLinks = [];

    /** @var array<string, array{mime: string, media: ?string, attribs: array<string, string>}> href => options */
    public array $styleSheets = [];

    /** @var array<string, array{mime: string, defer: bool, async: bool}> src => options */
    public array $scripts = [];

    /** @var array<string, string> mime => code */
    public array $scriptDeclarations = [];

    /** @var array<string, string> mime => code */
    public array $styleDeclarations = [];

    /** @var list<string> */
    public array $customTags = [];

    private string $charset = 'utf-8';

    public function getType(): string
    {
        return 'html';
    }

    public function setTitle(string|int|float|null $title): static
    {
        $this->title = (string) $title;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setDescription(string|int|float|null $description): static
    {
        $this->description = (string) $description;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setBase(string $base): static
    {
        $this->base = $base;

        return $this;
    }

    public function setLink(string $url): static
    {
        $this->link = $url;

        return $this;
    }

    /** Joomla took any scalar (com_content passes the author name, null when the category has none). */
    public function setMetaData(string $name, string|int|float|bool|null $content, bool $httpEquiv = false): static
    {
        $name = strtolower($name);
        $content = (string) $content;
        if ($name === 'description') {
            $this->description = $content;
        } elseif ($httpEquiv) {
            $this->httpEquiv[$name] = $content;
        } else {
            $this->metaTags[$name] = $content;
        }

        return $this;
    }

    public function getMetaData(string $name, bool $httpEquiv = false): string
    {
        $name = strtolower($name);
        if ($name === 'description') {
            return $this->description;
        }

        return $httpEquiv ? ($this->httpEquiv[$name] ?? '') : ($this->metaTags[$name] ?? '');
    }

    /** @param  array<string, string>  $attribs */
    public function addHeadLink(string $href, string $relation, string $relType = 'rel', array $attribs = []): static
    {
        $this->headLinks[] = ['href' => $href, 'relation' => $relation, 'relType' => $relType, 'attribs' => $attribs];

        return $this;
    }

    /** @param  array<string, string>  $attribs */
    public function addStyleSheet(string $url, string $type = 'text/css', ?string $media = null, array $attribs = []): static
    {
        $this->styleSheets[$url] = ['mime' => $type, 'media' => $media, 'attribs' => $attribs];

        return $this;
    }

    public function addScript(string $url, string $type = 'text/javascript', bool $defer = false, bool $async = false): static
    {
        $this->scripts[$url] = ['mime' => $type, 'defer' => $defer, 'async' => $async];

        return $this;
    }

    public function addScriptDeclaration(string $content, string $type = 'text/javascript'): static
    {
        $this->scriptDeclarations[$type] = ($this->scriptDeclarations[$type] ?? '').chr(13).$content;

        return $this;
    }

    public function addStyleDeclaration(string $content, string $type = 'text/css'): static
    {
        $this->styleDeclarations[$type] = ($this->styleDeclarations[$type] ?? '').chr(13).$content;

        return $this;
    }

    public function addCustomTag(string $html): static
    {
        $this->customTags[] = trim($html);

        return $this;
    }

    public function setCharset(string $type = 'utf-8'): static
    {
        $this->charset = $type;

        return $this;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function setGenerator(string $generator): static
    {
        return $this;
    }

    public function setMimeEncoding(string $type = 'text/html', bool $sync = true): static
    {
        return $this;
    }

    /*
    | The <head> pieces in the order Joomla 2.5's head renderer printed them:
    | standard metas, description, title, links, stylesheets, style
    | declarations, scripts, script declarations, custom tags. The layout
    | prints the title itself and asks for each group where the template
    | had it; everything is escaped the way Joomla escaped it.
    */

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_COMPAT, 'UTF-8', false);
    }

    /** Standard metas (robots, keywords, ...), then the description; content-type is the layout's. */
    public function renderMeta(): string
    {
        $lines = [];
        foreach ($this->httpEquiv as $name => $content) {
            if ($name !== 'content-type') {
                $lines[] = '  <meta http-equiv="'.$this->esc($name).'" content="'.$this->esc($content).'" />';
            }
        }
        foreach ($this->metaTags as $name => $content) {
            if ($content !== '') {
                $lines[] = '  <meta name="'.$this->esc($name).'" content="'.$this->esc($content).'" />';
            }
        }
        if ($this->description !== '') {
            $lines[] = '  <meta name="description" content="'.$this->esc($this->description).'" />';
        }

        return implode("\n", $lines);
    }

    /** Head links other than the canonical (which the layout prints first, as the template did). */
    public function renderLinks(): string
    {
        $lines = [];
        if ($this->base !== '') {
            $lines[] = '  <base href="'.$this->esc($this->base).'" />';
        }
        foreach ($this->headLinks as $link) {
            if ($link['relation'] === 'canonical') {
                continue;
            }
            $attribs = '';
            foreach ($link['attribs'] as $k => $v) {
                $attribs .= ' '.$this->esc((string) $k).'="'.$this->esc((string) $v).'"';
            }
            $lines[] = '  <link href="'.$this->esc($link['href']).'" '.$this->esc($link['relType']).'="'.$this->esc($link['relation']).'"'.$attribs.' />';
        }

        return implode("\n", $lines);
    }

    /** The canonical the component set, if any (the registry one applies otherwise). */
    public function canonical(): ?string
    {
        foreach ($this->headLinks as $link) {
            if ($link['relation'] === 'canonical') {
                return $link['href'];
            }
        }

        return null;
    }

    public function renderStyles(): string
    {
        $lines = [];
        foreach ($this->styleSheets as $href => $opt) {
            $tag = '  <link rel="stylesheet" href="'.$this->esc($href).'" type="'.$this->esc($opt['mime']).'"';
            if ($opt['media'] !== null && $opt['media'] !== '') {
                $tag .= ' media="'.$this->esc($opt['media']).'"';
            }
            foreach ($opt['attribs'] as $k => $v) {
                $tag .= ' '.$this->esc((string) $k).'="'.$this->esc((string) $v).'"';
            }
            $lines[] = $tag.' />';
        }
        foreach ($this->styleDeclarations as $type => $css) {
            $lines[] = '  <style type="'.$this->esc($type).'">'."\n".$css."\n  </style>";
        }

        return implode("\n", $lines);
    }

    public function renderScripts(): string
    {
        $lines = [];
        foreach ($this->scripts as $src => $opt) {
            $lines[] = '  <script src="'.$this->esc($src).'" type="'.$this->esc($opt['mime']).'"'.($opt['defer'] ? ' defer="defer"' : '').($opt['async'] ? ' async="async"' : '').'></script>';
        }

        return implode("\n", $lines);
    }

    /** Script declarations and custom tags (JSON-LD, og:* emitted through addCustomTag). */
    public function renderTail(): string
    {
        $lines = [];
        foreach ($this->scriptDeclarations as $type => $js) {
            $lines[] = '  <script type="'.$this->esc($type).'">'."\n".$js."\n  </script>";
        }
        foreach ($this->customTags as $tag) {
            $lines[] = '  '.$tag;
        }

        return implode("\n", $lines);
    }

    /** Everything in one block, for tools and the bare component shell (tmpl=component). */
    public function renderHead(): string
    {
        return implode("\n", array_filter([$this->renderMeta(), $this->renderLinks(), $this->renderStyles(), $this->renderScripts(), $this->renderTail()], fn ($s) => $s !== ''));
    }
}
