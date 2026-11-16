<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Access;
use Illuminate\Http\Request;

class AccessController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->is_admin, 403);

        return redirect()->route('admin', ['tab' => 'users']);
    }

    public function save(Request $request, string $type)
    {
        abort(404);
    }

    public function audit(Request $request)
    {
        Access::require('audit.view');
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d', 'actor' => 'nullable|integer', 'action' => 'nullable|string|max:100']);
        $query = Access::scope(ActivityLog::query())->with('user');
        if (! empty($data['date'])) {
            $query->whereDate('created_at', $data['date']);
        }
        if (! empty($data['actor'])) {
            $query->where('user_id', $data['actor']);
        }
        if (! empty($data['action'])) {
            $query->where('action', $data['action']);
        }

        return view('audit', ['logs' => $query->latest('id')->paginate(30)->withQueryString(), 'actors' => Access::scope(User::query())->get()]);
    }
}
