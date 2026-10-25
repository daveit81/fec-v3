<?php

namespace App\Http\Controllers;

use App\Fec\Url\UrlRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /__registry?path=/online-casinos/king-billy-casino
 * Development probe (v3 host is behind Cloudflare Access): what the URL
 * registry says about a public path. Removed when the router takes over.
 */
class RegistryController extends Controller
{
    public function __invoke(Request $request, UrlRegistry $registry): JsonResponse
    {
        $path = (string) $request->query('path', '/');

        return response()->json($registry->resolve($path)->toArray());
    }
}
