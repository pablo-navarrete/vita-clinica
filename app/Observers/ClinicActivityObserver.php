<?php

namespace App\Observers;

use App\Support\ClinicActivityLogger;
use Illuminate\Database\Eloquent\Model;

class ClinicActivityObserver
{
    public function created(Model $model): void
    {
        ClinicActivityLogger::recordModel($model, 'created');
    }

    public function updated(Model $model): void
    {
        ClinicActivityLogger::recordModel($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        ClinicActivityLogger::recordModel($model, 'deleted');
    }

    public function trashed(Model $model): void
    {
        ClinicActivityLogger::recordModel($model, 'trashed');
    }
}
