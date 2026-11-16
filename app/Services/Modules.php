<?php

namespace App\Services;

class Modules
{
    public static function all(): array
    {
        $master = [];
        foreach (['departments', 'locations', 'machines', 'checklists', 'schedules'] as $module) {
            foreach (['view', 'create', 'update'] as $action) {
                $master[] = "$module.$action";
            }
        }

        return [
            'monitoring' => ['name' => 'Monitoring Mesin', 'description' => 'Pantau kondisi dan riwayat mesin.', 'route' => 'dashboard', 'permissions' => ['monitoring.view', 'machines.history', 'inspections.view']],
            'daily' => ['name' => 'Pemeriksaan Harian', 'description' => 'Scan mesin, isi checklist dan ajukan perpindahan antarbagian.', 'route' => 'daily', 'permissions' => ['machines.history', 'inspections.view', 'inspections.scan', 'inspections.draft', 'inspections.submit', 'transfers.request']],
            'verification' => ['name' => 'Verifikasi Pemeriksaan', 'description' => 'Periksa hasil checklist dan kembalikan untuk revisi.', 'route' => 'reviews', 'permissions' => ['inspections.view', 'inspections.verify', 'inspections.revise']],
            'transfers' => ['name' => 'Verifikasi Perpindahan Mesin', 'description' => 'Setujui atau tolak perubahan bagian penanggung jawab.', 'route' => 'reviews', 'permissions' => ['transfers.decide']],
            'master' => ['name' => 'Master Data', 'description' => 'Kelola bagian, lokasi, mesin, checklist dan jadwal.', 'route' => 'admin', 'permissions' => $master],
            'audit' => ['name' => 'Audit Aktivitas', 'description' => 'Lihat catatan tindakan pengguna.', 'route' => 'audit', 'permissions' => ['audit.view']],
        ];
    }

    public static function legacy(array $permissions): array
    {
        $modules = [];
        foreach (self::all() as $key => $module) {
            if (! array_diff($module['permissions'], $permissions)) {
                $modules[] = $key;
            }
        }

        return $modules;
    }
}
