<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Checklist;
use App\Models\Department;
use App\Models\Location;
use App\Models\Machine;
use DateTime;
use Illuminate\Database\Seeder;

class MachineSpreadsheetSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = database_path('data/machines.csv');
        if (! file_exists($csvPath)) {
            $this->command?->warn("CSV file not found at: {$csvPath}");

            return;
        }

        $branch = Branch::firstOrCreate(['name' => 'Cabang Utama']);
        $department = Department::firstOrCreate(['name' => 'Produksi'], ['branch_id' => $branch->id]);

        $file = fopen($csvPath, 'r');
        fgetcsv($file); // Skip empty line 1
        $header = fgetcsv($file); // Header line 2

        $usedCodes = [];
        $rowNum = 0;
        $insertedCount = 0;

        while (($row = fgetcsv($file)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }

            $namaMesin = trim($row[2] ?? '');
            if ($namaMesin === '' || $namaMesin === 'EXAMPLE MACHINE') {
                continue;
            }

            $rowNum++;

            // Clean machine code
            $rawCode = trim($row[5] ?? '');
            if ($rawCode === '') {
                $rawCode = $namaMesin.'-'.$rowNum;
            }

            $finalCode = $rawCode;
            if (isset($usedCodes[$finalCode])) {
                $finalCode = $namaMesin.'-'.$rawCode;
            }
            if (isset($usedCodes[$finalCode])) {
                $finalCode = $finalCode.'-'.$rowNum;
            }
            $usedCodes[$finalCode] = true;

            // Handle location
            $locName = trim($row[16] ?? '');
            $locationId = null;
            if ($locName !== '' && ! in_array(strtolower($locName), ['-', 'n/a'], true)) {
                $location = Location::firstOrCreate(
                    ['name' => $locName, 'branch_id' => $branch->id],
                    ['area' => 'Area Pabrik']
                );
                $locationId = $location->id;
            } else {
                $defaultLoc = Location::firstOrCreate(
                    ['name' => 'Workshop Utama', 'branch_id' => $branch->id],
                    ['area' => 'Area Pabrik']
                );
                $locationId = $defaultLoc->id;
            }

            // Handle checklist form
            $formName = trim($row[3] ?? '');
            if ($formName !== '') {
                Checklist::firstOrCreate(
                    ['name' => $formName, 'branch_id' => $branch->id],
                    ['items' => [
                        ['label' => 'Kondisi Fisik & Kebersihan', 'type' => 'condition', 'unit' => ''],
                        ['label' => 'Fungsi Operasional & Kelistrikan', 'type' => 'condition', 'unit' => ''],
                    ]]
                );
            }

            // Technical specifications
            $brand = $this->nullify($row[6] ?? null);
            $machineType = $this->nullify($row[7] ?? null);
            $model = $this->nullify($row[8] ?? null);
            $serialNumber = $this->nullify($row[9] ?? null);
            $assetNumber = $this->nullify($row[10] ?? null);
            $assetDesc = $this->nullify($row[11] ?? null);
            $originCountry = $this->nullify($row[12] ?? null);
            $year = $this->parseYear($row[13] ?? null);
            $receivedDate = $this->parseDate($row[14] ?? null);
            $operationalDate = $this->parseDate($row[15] ?? null);
            $condition = $this->nullify($row[17] ?? null) ?? 'Baik';
            $kw = $this->parseDecimal($row[18] ?? null);
            $hp = $this->parseDecimal($row[19] ?? null);
            $capacity = $this->nullify($row[20] ?? null);
            $status = $this->nullify($row[21] ?? null) ?? 'Beroperasi';
            $percentage = $this->parsePercentage($row[22] ?? null);
            $quantity = ! empty($row[23]) && is_numeric($row[23]) ? (int) $row[23] : 1;
            $poNumber = $this->nullify($row[24] ?? null);
            $imageUrl = $this->nullify($row[4] ?? null);

            // Format QR Code: "[Nama mesin] / [Nomor Mesin] / ..."
            $qrValue = Machine::formatQrValue($namaMesin, $finalCode);

            $machine = Machine::updateOrCreate(
                ['code' => $finalCode],
                [
                    'name' => $namaMesin,
                    'qr_value' => $qrValue,
                    'department_id' => $department->id,
                    'location_id' => $locationId,
                    'branch_id' => $branch->id,
                    'active' => ! in_array(strtolower($status), ['rusak', 'nonaktif'], true),
                    'brand' => $brand,
                    'machine_type' => $machineType,
                    'model' => $model,
                    'serial_number' => $serialNumber,
                    'asset_number' => $assetNumber,
                    'asset_description' => $assetDesc,
                    'origin_country' => $originCountry,
                    'manufacture_year' => $year,
                    'received_date' => $receivedDate,
                    'operational_date' => $operationalDate,
                    'condition' => $condition,
                    'power_kw' => $kw,
                    'power_hp' => $hp,
                    'capacity' => $capacity,
                    'machine_status' => $status,
                    'condition_percentage' => $percentage,
                    'quantity' => $quantity,
                    'po_number' => $poNumber,
                    'image_url' => $imageUrl,
                    'form_name' => $formName ?: null,
                ]
            );

            if ($formName !== '') {
                $checklist = Checklist::where('name', $formName)->first();
                if ($checklist) {
                    \App\Models\MaintenanceSchedule::updateOrCreate(
                        ['machine_id' => $machine->id, 'period' => 'daily'],
                        [
                            'checklist_id' => $checklist->id,
                            'starts_on' => today(),
                            'weekdays' => [1, 2, 3, 4, 5, 6, 7],
                            'active' => $machine->active,
                        ]
                    );
                }
            }

            $insertedCount++;
        }

        fclose($file);

        $this->command?->info("Berhasil mengimpor {$insertedCount} mesin dari spreadsheet.");
    }

    private function nullify(?string $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $val = trim($val);
        if (in_array(strtolower($val), ['', '-', 'n/a', 'na', 'data tidak tersedia', 'on proccess', 'null'], true)) {
            return null;
        }

        return $val;
    }

    private function parseYear(?string $val): ?int
    {
        if ($val === null) {
            return null;
        }
        if (preg_match('/\b(19\d\d|20\d\d)\b/', $val, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function parseDate(?string $val): ?string
    {
        $val = $this->nullify($val);
        if (! $val) {
            return null;
        }

        $formats = ['j/M/y', 'j/M/Y', 'j-M-y', 'j-M-Y', 'Y-m-d', 'd/m/Y'];
        foreach ($formats as $fmt) {
            $d = DateTime::createFromFormat($fmt, $val);
            if ($d !== false) {
                return $d->format('Y-m-d');
            }
        }

        $ts = strtotime($val);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    private function parseDecimal(?string $val): ?float
    {
        $val = $this->nullify($val);
        if (! $val) {
            return null;
        }
        $clean = str_replace(',', '.', preg_replace('/[^0-9.,]/', '', $val));

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function parsePercentage(?string $val): int
    {
        if (! $val) {
            return 100;
        }
        $num = preg_replace('/[^0-9]/', '', $val);

        return is_numeric($num) && $num !== '' ? min(100, max(0, (int) $num)) : 100;
    }
}
