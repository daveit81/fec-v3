{{--
    Primary menu as mod_menu renders it from jos_menu (menutype of the
    fec_nav position), snapshot of 25 Oct 2026 in partials/nav-static;
    the "current"/"active" classes Joomla adds per page come from the
    active menu item of the request (legacy dispatch), as mod_menu did.
    Phase 4 renders it from jos_menu through the URL registry.
--}}
{!! \App\Fec\Legacy\Joomla\Menu::markActive(view('partials.nav-static')->render(), \App\Fec\Legacy\Joomla\Menu::currentTrail()) !!}
