<?php

namespace App\Http\Controllers;

use App\Fec\Url\Router;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /__registry?path=/online-casinos/king-billy-casino
 * Development probe (v3 host is behind Cloudflare Access): the Router's
 * decision for a public path, registry lookup included.
 */
class RegistryController extends Controller
{
    public function __invoke(Request $request, Router $router): JsonResponse
    {
        $path = (string) $request->query('path', '/');

        return response()->json($router->route($path)->toArray());
    }
}
