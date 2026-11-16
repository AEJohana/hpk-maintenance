<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Access;
use Illuminate\Auth\Access\Response;

class BranchPolicy
{
    public function accessBranch(User $user, $record): Response
    {
        return $user->active && ($user->role === 'admin' || ($user->branch_id && Access::branch($record) === $user->branch_id))
            ? Response::allow() : Response::denyAsNotFound();
    }
}
