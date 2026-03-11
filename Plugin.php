<?php

namespace Plugin\V2nodeCompat;

use App\Services\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    public function boot(): void
    {
        // No hooks needed; this plugin only provides API routes
    }
}
