<?php

namespace App\Actions;

use App\Models\Service;

class ToggleServiceActiveAction
{
    /**
     * Deactivates an active service and reactivates an inactive one (RF-7). Nothing is
     * deleted: the service and its price history are kept (RF-9).
     */
    public function handle(Service $service): Service
    {
        $service->update(['is_active' => ! $service->is_active]);

        return $service;
    }
}
