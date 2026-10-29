{{--
    Primary menu: the mod_menu module of the fec_nav position rendered from
    jos_menu through the template's override, as the live site does
    (App\Fec\Legacy\MenuModule). fec.legacy.nav_from_menu = false, or a
    failure of the module, serves the snapshot of 25 Oct 2026
    (partials/nav-static) with the "current"/"active" classes of the
    request's active menu item.
--}}
@php($fecNav = config('fec.legacy.nav_from_menu', true) ? \App\Fec\Legacy\MenuModule::render() : null)
@if ($fecNav !== null)
{!! $fecNav !!}
@else
{!! \App\Fec\Legacy\Joomla\Menu::markActive(view('partials.nav-static')->render(), \App\Fec\Legacy\Joomla\Menu::currentTrail()) !!}
@endif
