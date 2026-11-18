<?php

namespace App\Observers;

use App\Models\{Patient, PortalAccount};
use App\Services\MiSalud\PortalPatientLinkService;

class PortalLinkSubjectObserver
{
    public function saved(Patient|PortalAccount $subject): void
    {
        app(PortalPatientLinkService::class)->request($subject instanceof Patient?'patient':'account',(int)$subject->id);
    }

    public function deleted(Patient|PortalAccount $subject): void { $this->saved($subject); }
}
