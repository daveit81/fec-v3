<?php

namespace App\Fec\Url;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a ported section implements: it receives the request and the resolved
 * URL (option, view, ids as Joomla saw them) and renders the page with the
 * legacy markup. One class per legacy view; registered in Dispatcher::HANDLERS.
 */
interface PageHandler
{
    public function handle(Request $request, ResolvedUrl $url): Response;
}
