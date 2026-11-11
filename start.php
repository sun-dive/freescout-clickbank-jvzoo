<?php

// Run by FreeScout when the module starts: its routes.
if (!app()->routesAreCached()) {
    require __DIR__.'/Http/routes.php';
}
