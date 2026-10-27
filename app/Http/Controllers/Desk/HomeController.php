<?php

namespace App\Http\Controllers\Desk;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** /desk: the redazione's entry page, with the ported desks (config fec.legacy.desks). */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('desk.home', ['desks' => (array) config('fec.legacy.desks', [])]);
    }
}
