<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class Access
{
    public static function catalog(): array
    {
        $keys = ['monitoring.view', 'machines.history', 'inspections.view', 'inspections.scan', 'inspections.draft', 'inspections.submit', 'inspections.verify', 'inspections.revise', 'transfers.request', 'transfers.decide', 'audit.view'];
        foreach (['departments', 'locations', 'machines', 'checklists', 'schedules', 'users'] as $module) {
            foreach (['view', 'create', 'update'] as $action) {
                $keys[] = "$module.$action";
            }
        }

        return array_merge($keys, ['users.status', 'users.password']);
    }

    public static function label(string $key): string
    {
        [$module, $action] = explode('.', $key);
        $modules = ['monitoring' => 'Monitoring', 'machines' => 'Mesin', 'departments' => 'Bagian', 'locations' => 'Lokasi', 'checklists' => 'Checklist', 'schedules' => 'Jadwal', 'users' => 'Akun', 'inspections' => 'Pemeriksaan', 'transfers' => 'Perpindahan', 'audit' => 'Audit'];
        $actions = ['view' => 'Lihat', 'history' => 'Lihat riwayat', 'create' => 'Tambah', 'update' => 'Ubah', 'status' => 'Aktif/nonaktif', 'password' => 'Ganti kata sandi', 'scan' => 'Scan', 'draft' => 'Simpan draft', 'submit' => 'Kirim hasil', 'verify' => 'Verifikasi', 'revise' => 'Kembalikan revisi', 'request' => 'Ajukan', 'decide' => 'Setujui/tolak'];

        return ($actions[$action] ?? $action).' '.($modules[$module] ?? $module);
    }

    public static function ceiling(string $type): array
    {
        // Retained only for the original role migration; live access uses Modules.
        return match ($type) {
            'admin' => self::catalog(),
            'branch_admin' => self::catalog(),
            'maintenance' => ['monitoring.view', 'machines.history', 'inspections.view', 'inspections.verify', 'inspections.revise', 'transfers.decide'],
            'department' => ['monitoring.view', 'machines.history', 'inspections.view', 'inspections.scan', 'inspections.draft', 'inspections.submit', 'transfers.request'],
            default => [],
        };
    }

    public static function allows(User $user, string $permission): bool
    {
        if (! $user->active) {
            return false;
        }
        if (in_array($permission, ['inspections.scan', 'inspections.draft', 'inspections.submit', 'transfers.request'], true) && ! $user->department_id) {
            return false;
        }
        if ($user->is_admin) {
            return true;
        }
        if (str_starts_with($permission, 'users.')) {
            return false;
        }
        foreach (Modules::all() as $key => $module) {
            if (in_array($permission, $module['permissions'], true) && $user->hasModule($key)) {
                return true;
            }
        }

        return false;
    }

    public static function require(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    public static function branch($record): ?int
    {
        return $record->branch_id ?? (method_exists($record, 'machine') ? $record->machine?->branch_id : null);
    }

    public static function scope(Builder $query): Builder
    {
        $user = auth()->user();
        if (! $user || $user->role === 'admin') {
            return $query;
        }

        return self::inBranch($query, $user->branch_id);
    }

    public static function inBranch(Builder $query, int $branch): Builder
    {
        return in_array($query->getModel()->getTable(), ['maintenance_schedules', 'inspections', 'transfer_requests', 'trnks_maintenance_schedules', 'trnks_inspections', 'trnks_transfer_requests'])
            ? $query->whereHas('machine', fn ($q) => $q->where('branch_id', $branch))
            : $query->where($query->getModel()->qualifyColumn('branch_id'), $branch);
    }

    public static function object($record): void
    {
        Gate::authorize('accessBranch', $record);
    }
}
