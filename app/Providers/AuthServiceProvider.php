<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\ActivityLog;
use App\Models\Checklist;
use App\Models\Department;
use App\Models\Inspection;
use App\Models\Location;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\TransferRequest;
use App\Models\User;
use App\Policies\BranchPolicy;
use App\Services\Access;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        foreach ([Department::class, Location::class, Machine::class, Checklist::class, MaintenanceSchedule::class, Inspection::class, TransferRequest::class, User::class, ActivityLog::class] as $model) {
            Gate::policy($model, BranchPolicy::class);
        }
        foreach (Access::catalog() as $permission) {
            Gate::define($permission, fn ($user) => Access::allows($user, $permission));
        }

        //
    }
}
