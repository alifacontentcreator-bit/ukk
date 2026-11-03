<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->log($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->log($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->log($model, 'deleted');
    }

    protected function log(Model $model, string $action)
    {
        // Hindari logging tabel audit_log itu sendiri (looping)
        if ($model instanceof AuditLog) return;

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'module' => get_class($model),
            'description' => "Data {$model->getTable()} telah di {$action}.",
            'method' => Request::method(),
            'route' => Request::fullUrl(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
