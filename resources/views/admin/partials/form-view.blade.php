<div class="form-back-bar" style="margin-bottom: 20px;">
    <a class="button secondary" href="{{ route('admin', ['tab' => $tab]) }}">
        ← Kembali ke Daftar {{ $tabs[$tab] }}
    </a>
</div>

<div class="card">
    <div style="margin-bottom: 24px; border-bottom: 1px solid var(--line); padding-bottom: 16px;">
        <span class="eyebrow">{{ strtoupper($tabs[$tab]) }}</span>
        <h2 style="margin: 0 0 6px;">{{ $record ? 'Edit Data: '.($record->name ?? $record->code ?? 'ID #'.$record->id) : 'Tambah '.$tabs[$tab].' Baru' }}</h2>
        <p class="muted" style="margin: 0;">Lengkapi formulir di bawah ini dengan teliti. Klik simpan setelah selesai.</p>
    </div>

    <form method="post" action="{{ route('admin.save', $tab) }}" class="stack">
        @csrf
        <input type="hidden" name="id" value="{{ $record?->id }}">

        @if($tab === 'machines')
            <!-- SECTION 1: IDENTITAS & PENEMPATAN MESIN -->
            <fieldset class="form-section-card">
                <legend class="form-section-title">1. Identitas & Penempatan Mesin</legend>
                <div class="grid">
                    <div>
                        <label for="machine-code">Kode Mesin <span class="required">*</span></label>
                        <input id="machine-code" name="code" value="{{ old('code', $record?->code) }}" required maxlength="100" placeholder="Contoh: GMAW-003">
                    </div>
                    <div>
                        <label for="machine-name">Nama Jenis Mesin <span class="required">*</span></label>
                        <input id="machine-name" name="name" value="{{ old('name', $record?->name) }}" required maxlength="150" placeholder="Contoh: MESIN LAS GMAW">
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label for="machine-dept">Bagian Penanggung Jawab <span class="required">*</span></label>
                        <select id="machine-dept" name="department_id" required>
                            <option value="">Pilih bagian</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}" @selected(old('department_id', $record?->department_id) == $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                        @if($record)
                            <small class="muted">Perubahan bagian dilakukan via modul permintaan perpindahan mesin.</small>
                        @endif
                    </div>
                    <div>
                        <label for="machine-loc">Lokasi Penempatan Fisik <span class="required">*</span></label>
                        <select id="machine-loc" name="location_id" required>
                            <option value="">Pilih lokasi</option>
                            @foreach($locations as $l)
                                <option value="{{ $l->id }}" @selected(old('location_id', $record?->location_id) == $l->id)>{{ $l->area }} / {{ $l->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label for="machine-form">Template Formulir Ceklist Terkait</label>
                        <select id="machine-form" name="form_name">
                            <option value="">— Tidak terhubung ke formulir —</option>
                            @foreach($checklists as $c)
                                <option value="{{ $c->name }}" @selected(old('form_name', $record?->form_name) === $c->name)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <small class="muted">Saat diisi, jadwal daily harian mesin akan disinkronkan otomatis.</small>
                    </div>
                    <div>
                        <label for="machine-qr">Format Nilai QR Code (Otomatis bila dikosongkan)</label>
                        <input id="machine-qr" name="qr_value" value="{{ old('qr_value', $record?->qr_value) }}" placeholder="Contoh: GMAW / GMAW-003 / ..." maxlength="255">
                        <small class="muted">Standar format: [Nama Mesin] / [Nomor Mesin] / ...</small>
                    </div>
                </div>
            </fieldset>

            <!-- SECTION 2: SPESIFIKASI TEKNIS -->
            <fieldset class="form-section-card">
                <legend class="form-section-title">2. Spesifikasi Teknis Mesin</legend>
                <div class="grid">
                    <div>
                        <label for="machine-brand">Merk / Pabrikan</label>
                        <input id="machine-brand" name="brand" value="{{ old('brand', $record?->brand) }}" maxlength="100" placeholder="Contoh: Miller, Panasonic, Taichuan">
                    </div>
                    <div>
                        <label for="machine-model">Model / Tipe</label>
                        <input id="machine-model" name="model" value="{{ old('model', $record?->model) }}" maxlength="100" placeholder="Contoh: CP-302, YD-350GR4">
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label for="machine-kw">Daya Listrik (KW)</label>
                        <input id="machine-kw" type="number" step="0.01" name="power_kw" value="{{ old('power_kw', $record?->power_kw) }}" placeholder="Contoh: 18.5">
                    </div>
                    <div>
                        <label for="machine-hp">Daya Listrik (HP)</label>
                        <input id="machine-hp" type="number" step="0.01" name="power_hp" value="{{ old('power_hp', $record?->power_hp) }}" placeholder="Contoh: 25">
                    </div>
                    <div>
                        <label for="machine-cap">Kapasitas Kerja</label>
                        <input id="machine-cap" name="capacity" value="{{ old('capacity', $record?->capacity) }}" placeholder="Contoh: 350A, 5 Ton, 3000 RPM" maxlength="100">
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label for="machine-sn">Nomor Seri (Serial Number)</label>
                        <input id="machine-sn" name="serial_number" value="{{ old('serial_number', $record?->serial_number) }}" maxlength="100">
                    </div>
                    <div>
                        <label for="machine-asset">Nomor Aset</label>
                        <input id="machine-asset" name="asset_number" value="{{ old('asset_number', $record?->asset_number) }}" maxlength="100">
                    </div>
                    <div>
                        <label for="machine-po">Nomor Purchase Order (PO)</label>
                        <input id="machine-po" name="po_number" value="{{ old('po_number', $record?->po_number) }}" maxlength="100">
                    </div>
                </div>
            </fieldset>

            <!-- SECTION 3: STATUS & KONDISI OPERASIONAL -->
            <fieldset class="form-section-card">
                <legend class="form-section-title">3. Status & Kondisi Operasional</legend>
                <div class="grid">
                    <div>
                        <label for="machine-condition">Kondisi Fisik</label>
                        <input id="machine-condition" name="condition" value="{{ old('condition', $record?->condition ?? 'Baik') }}" maxlength="50" placeholder="Baik / Rusak / Perbaikan">
                    </div>
                    <div>
                        <label for="machine-percent">Persentase Kondisi (%)</label>
                        <input id="machine-percent" type="number" min="0" max="100" name="condition_percentage" value="{{ old('condition_percentage', $record?->condition_percentage ?? 100) }}">
                    </div>
                    <div>
                        <label for="machine-opstatus">Status Operasional</label>
                        <input id="machine-opstatus" name="machine_status" value="{{ old('machine_status', $record?->machine_status ?? 'Beroperasi') }}" maxlength="50" placeholder="Beroperasi / Nonaktif">
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label for="machine-received">Tanggal Diterima</label>
                        <input id="machine-received" type="date" name="received_date" value="{{ old('received_date', $record?->received_date?->toDateString()) }}">
                    </div>
                    <div>
                        <label for="machine-opdate">Tanggal Mulai Beroperasi</label>
                        <input id="machine-opdate" type="date" name="operational_date" value="{{ old('operational_date', $record?->operational_date?->toDateString()) }}">
                    </div>
                    <div>
                        <label for="machine-active">Status Master Mesin <span class="required">*</span></label>
                        <select id="machine-active" name="active">
                            <option value="1" @selected(old('active', $record?->active ?? true) == 1)>Aktif (Dapat dipindai & dijadwalkan)</option>
                            <option value="0" @selected(old('active', $record?->active ?? true) == 0)>Nonaktif (Dinonaktifkan / Tidak muncul)</option>
                        </select>
                    </div>
                </div>
            </fieldset>

        @elseif($tab === 'locations')
            <!-- FORM MASTER LOKASI -->
            <div class="grid">
                <div>
                    <label for="loc-name">Nama Lokasi / Line / Ruangan <span class="required">*</span></label>
                    <input id="loc-name" name="name" value="{{ old('name', $record?->name) }}" required maxlength="100" placeholder="Contoh: Line 1 Body 4, Workshop Machining">
                </div>
                <div>
                    <label for="loc-area">Area / Gedung <span class="required">*</span></label>
                    <input id="loc-area" name="area" value="{{ old('area', $record?->area) }}" required maxlength="100" placeholder="Contoh: Area Pabrik, Gedung A">
                </div>
            </div>
            <div>
                <label for="loc-active">Status Lokasi</label>
                <select id="loc-active" name="active">
                    <option value="1" @selected(old('active', $record?->active ?? true) == 1)>Aktif</option>
                    <option value="0" @selected(old('active', $record?->active ?? true) == 0)>Nonaktif</option>
                </select>
            </div>

        @elseif($tab === 'checklists')
            <!-- FORM MASTER TEMPLATE CHECKLIST -->
            <div class="grid">
                <div>
                    <label for="chk-name">Nama Template Formulir Ceklist <span class="required">*</span></label>
                    <input id="chk-name" name="name" value="{{ old('name', $record?->name) }}" required maxlength="150" placeholder="Contoh: CEKLIST HARIAN MESIN LAS GMAW">
                    <small class="muted">Nama ini yang akan dicocokkan dengan mesin terkait.</small>
                </div>
                <div>
                    <label for="chk-active">Status Template</label>
                    <select id="chk-active" name="active">
                        <option value="1" @selected(old('active', $record?->active ?? true) == 1)>Aktif</option>
                        <option value="0" @selected(old('active', $record?->active ?? true) == 0)>Nonaktif</option>
                    </select>
                </div>
            </div>

            <fieldset class="form-section-card" style="margin-top: 20px;">
                <legend class="form-section-title">Daftar Butir Pemeriksaan (Checklist Items)</legend>
                <p class="muted">Tentukan butir pemeriksaan yang wajib diperiksa oleh operator saat scan harian. Tipe kondisi (OK/NG) digunakan untuk pemeriksaan visual/fisik, tipe angka untuk pengukuran dengan satuan.</p>

                <div id="checklist-items">
                    @foreach(old('items', $record?->items ?? [['label' => '', 'type' => 'condition', 'unit' => '']]) as $i => $item)
                        <div class="checkrow" style="background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid var(--line); margin-bottom: 10px;">
                            <div>
                                <label style="font-size: 11px; margin-bottom: 4px;">Butir Pemeriksaan</label>
                                <input name="items[{{ $i }}][label]" value="{{ $item['label'] ?? '' }}" placeholder="Contoh: Kebersihan nozel & kabel massa" required>
                            </div>
                            <div>
                                <label style="font-size: 11px; margin-bottom: 4px;">Jenis Penilaian</label>
                                <select name="items[{{ $i }}][type]">
                                    <option value="condition" @selected(($item['type'] ?? '') === 'condition')>Kondisi (OK / NG)</option>
                                    <option value="number" @selected(($item['type'] ?? '') === 'number')>Angka (Pengukuran)</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size: 11px; margin-bottom: 4px;">Satuan (Jika Angka)</label>
                                <input name="items[{{ $i }}][unit]" value="{{ $item['unit'] ?? '' }}" placeholder="Bar / Volt / Ampere / mm">
                            </div>
                            <div style="display: flex; align-items: flex-end;">
                                <button type="button" class="button secondary small-btn btn-deactivate" onclick="this.closest('.checkrow').remove()" title="Hapus butir ini">Hapus</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button type="button" class="button secondary" id="add-item" style="margin-top: 10px;">
                    + Tambah Butir Pemeriksaan
                </button>
            </fieldset>

        @elseif($tab === 'departments')
            <!-- FORM MASTER DEPARTEMEN -->
            <div>
                <label for="dept-name">Nama Bagian / Departemen <span class="required">*</span></label>
                <input id="dept-name" name="name" value="{{ old('name', $record?->name) }}" required maxlength="100" placeholder="Contoh: Produksi, Maintenance, Logistik">
            </div>
            <div>
                <label for="dept-active">Status Bagian</label>
                <select id="dept-active" name="active">
                    <option value="1" @selected(old('active', $record?->active ?? true) == 1)>Aktif</option>
                    <option value="0" @selected(old('active', $record?->active ?? true) == 0)>Nonaktif</option>
                </select>
            </div>

        @elseif($tab === 'schedules')
            <!-- FORM MASTER JADWAL -->
            <div class="grid">
                <div>
                    <label for="sched-machine">Pilih Mesin <span class="required">*</span></label>
                    <select id="sched-machine" name="machine_id" required>
                        <option value="">Pilih mesin</option>
                        @foreach($machines as $m)
                            <option value="{{ $m->id }}" @selected(old('machine_id', $record?->machine_id) == $m->id)>{{ $m->code }} · {{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="sched-checklist">Pilih Template Checklist <span class="required">*</span></label>
                    <select id="sched-checklist" name="checklist_id" required>
                        <option value="">Pilih template checklist</option>
                        @foreach($checklists as $c)
                            <option value="{{ $c->id }}" @selected(old('checklist_id', $record?->checklist_id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid">
                <div>
                    <label for="sched-period">Periode Pemeriksaan <span class="required">*</span></label>
                    <select id="sched-period" name="period">
                        @foreach(['daily' => 'Daily (Harian)', 'monthly' => 'Monthly (Bulanan)', 'quarterly' => '3 Bulanan', 'semiannual' => '6 Bulanan'] as $p => $plabel)
                            <option value="{{ $p }}" @selected(old('period', $record?->period) === $p)>{{ $plabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="sched-starts">Tanggal Mulai Berlaku <span class="required">*</span></label>
                    <input id="sched-starts" type="date" name="starts_on" value="{{ old('starts_on', $record?->starts_on?->toDateString() ?? today()->toDateString()) }}" required>
                </div>
            </div>

            <label>Hari Kerja Aktif Pemeriksaan (Senin – Minggu)</label>
            <div class="row" style="margin-bottom: 16px;">
                @foreach([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'] as $n => $day)
                    <label style="min-height: auto; cursor: pointer;">
                        <input type="checkbox" name="weekdays[]" value="{{ $n }}" @checked(in_array($n, old('weekdays', $record?->weekdays ?? [1, 2, 3, 4, 5, 6, 7])))> {{ $day }}
                    </label>
                @endforeach
            </div>

            <div>
                <label for="sched-active">Status Jadwal</label>
                <select id="sched-active" name="active">
                    <option value="1" @selected(old('active', $record?->active ?? true) == 1)>Aktif</option>
                    <option value="0" @selected(old('active', $record?->active ?? true) == 0)>Nonaktif</option>
                </select>
            </div>

        @elseif($tab === 'users')
            <!-- FORM MASTER USER / AKUN -->
            <div class="grid">
                <div>
                    <label for="user-name">Nama Lengkap <span class="required">*</span></label>
                    <input id="user-name" name="name" value="{{ old('name', $record?->name) }}" required maxlength="100">
                </div>
                <div>
                    <label for="user-email">Email Login <span class="required">*</span></label>
                    <input id="user-email" type="email" name="email" value="{{ old('email', $record?->email) }}" required maxlength="150">
                </div>
            </div>

            <div class="grid">
                <div>
                    <label for="user-dept">Bagian Penempatan</label>
                    <select id="user-dept" name="department_id">
                        <option value="">— Tidak terikat bagian (Admin Pusat) —</option>
                        @foreach($departments as $d)
                            <option value="{{ $d->id }}" @selected(old('department_id', $record?->department_id) == $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="user-loc">Lokasi / Line Penugasan (PIC Line)</label>
                    <select id="user-loc" name="location_id">
                        <option value="">— Tidak terikat line spesifik —</option>
                        @foreach($locations as $l)
                            <option value="{{ $l->id }}" @selected(old('location_id', $record?->location_id) == $l->id)>{{ $l->area }} / {{ $l->name }}</option>
                        @endforeach
                    </select>
                    <small class="muted">Tentukan line agar aset mesin di line ini otomatis tampil di menu PIC.</small>
                </div>
            </div>

            <div>
                <label for="user-pos">Jabatan</label>
                <input id="user-pos" name="position" value="{{ old('position', $record?->position) }}" placeholder="Contoh: Kepala Regu Aksesoris, Operator Las" maxlength="100">
            </div>

            <div class="grid">
                <div>
                    <label for="user-type">Tipe Hak Akses</label>
                    <select id="user-type" name="is_admin">
                        <option value="0" @selected(!old('is_admin', $record?->is_admin))>Pengguna Modul Operasional</option>
                        <option value="1" @selected(old('is_admin', $record?->is_admin))>Administrator Sistem</option>
                    </select>
                </div>
                <div>
                    <label for="user-pass">Kata Sandi {{ $record ? '(Kosongkan jika tidak diubah)' : '*' }}</label>
                    <input id="user-pass" type="password" name="password" minlength="10" maxlength="100" autocomplete="new-password" @required(!$record)>
                </div>
            </div>

            <div>
                <label for="user-active">Status Akun</label>
                <select id="user-active" name="active">
                    <option value="1" @selected(old('active', $record?->active ?? true) == 1)>Aktif</option>
                    <option value="0" @selected(old('active', $record?->active ?? true) == 0)>Nonaktif</option>
                </select>
            </div>

            <fieldset class="form-section-card" style="margin-top: 18px;">
                <legend class="form-section-title">Akses Modul Pengguna</legend>
                <p class="muted">Centang modul yang dapat diakses oleh akun ini. Administrator secara default memiliki izin mengelola seluruh modul.</p>
                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
                    @foreach($moduleCatalog as $key => $module)
                        <label style="border: 1px solid var(--line); padding: 12px; border-radius: 8px; background: #fafbfc; min-height: auto;">
                            <input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key, old('modules', $record?->modules()->pluck('module')->all() ?? [])))>
                            <div>
                                <strong>{{ $module['name'] }}</strong>
                                <small class="muted" style="display:block;">{{ $module['description'] }}</small>
                            </div>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        <div class="actions" style="margin-top: 30px; display: flex; gap: 12px; align-items: center;">
            <button type="submit" class="button">
                Simpan {{ $tabs[$tab] }}
            </button>
            <a class="button secondary" href="{{ route('admin', ['tab' => $tab]) }}">
                Batal
            </a>
        </div>
    </form>
</div>
