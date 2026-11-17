<div class="card">
    <div class="admin-toolbar">
        <div>
            <h2>Daftar {{ $tabs[$tab] }}</h2>
            <p class="muted" style="margin: 0;">Total data: {{ $records->total() }} entri @if($tab === 'users') · Akses modul pengguna @endif</p>
        </div>
        <div>
            @can($tab.'.create')
                <a class="button" href="{{ route('admin', ['tab' => $tab, 'action' => 'create']) }}">
                    + Tambah {{ $tabs[$tab] }}
                </a>
            @endcan
        </div>
    </div>

    <!-- Bilah Filter & Pencarian -->
    <form method="get" action="{{ route('admin') }}" class="filter-bar">
        <input type="hidden" name="tab" value="{{ $tab }}">
        
        <div class="filter-group">
            <label for="filter-status">Status</label>
            <select id="filter-status" name="status" onchange="this.form.submit()">
                <option value="active" @selected($status === 'active')>Hanya Aktif</option>
                <option value="inactive" @selected($status === 'inactive')>Hanya Nonaktif</option>
                <option value="all" @selected($status === 'all')>Semua Status</option>
            </select>
        </div>

        <div class="filter-group filter-search">
            <label for="filter-q">Pencarian</label>
            <div class="search-input-wrap">
                <input id="filter-q" type="search" name="q" value="{{ $q }}" placeholder="Ketik kata kunci pencarian...">
                <button type="submit" class="secondary">Cari</button>
                @if($q !== '' || $status !== 'active')
                    <a href="{{ route('admin', ['tab' => $tab]) }}" class="button secondary" title="Reset filter">Reset</a>
                @endif
            </div>
        </div>
    </form>

    <!-- Tabel Data Master -->
    <div class="table-wrap">
        <table class="responsive-table" role="table">
            <thead role="rowgroup">
                <tr role="row">
                    @switch($tab)
                        @case('machines')
                            <th scope="col" role="columnheader">Mesin</th>
                            <th scope="col" role="columnheader">Penempatan</th>
                            <th scope="col" role="columnheader">Formulir Ceklist</th>
                            <th scope="col" role="columnheader">Spesifikasi</th>
                            <th scope="col" role="columnheader">Status</th>
                            <th scope="col" role="columnheader">Aksi</th>
                            @break
                        @case('locations')
                            <th scope="col" role="columnheader">Nama Lokasi</th>
                            <th scope="col" role="columnheader">Area / Gedung</th>
                            <th scope="col" role="columnheader">Status</th>
                            <th scope="col" role="columnheader">Aksi</th>
                            @break
                        @case('checklists')
                            <th scope="col" role="columnheader">Nama Template Formulir</th>
                            <th scope="col" role="columnheader">Jumlah Item Periksa</th>
                            <th scope="col" role="columnheader">Status</th>
                            <th scope="col" role="columnheader">Aksi</th>
                            @break
                        @case('departments')
                            <th scope="col" role="columnheader">Nama Bagian</th>
                            <th scope="col" role="columnheader">Status</th>
                            <th scope="col" role="columnheader">Aksi</th>
                            @break
                        @case('schedules')
                            <th scope="col" role="columnheader">Mesin</th>
                            <th scope="col" role="columnheader">Template Checklist</th>
                            <th scope="col" role="columnheader">Periode</th>
                            <th scope="col" role="columnheader">Status</th>
                            <th scope="col" role="columnheader">Aksi</th>
                            @break
                        @case('users')
                            <th scope="col" role="columnheader">Nama & Email</th>
                            <th scope="col" role="columnheader">Jabatan & Bagian</th>
                            <th scope="col" role="columnheader">Tipe Akun</th>
                            <th scope="col" role="columnheader">Status</th>
                            <th scope="col" role="columnheader">Aksi</th>
                            @break
                        @default
                            <th scope="col" role="columnheader">Identitas</th>
                            <th scope="col" role="columnheader">Informasi</th>
                            <th scope="col" role="columnheader">Aksi</th>
                    @endswitch
                </tr>
            </thead>
            <tbody role="rowgroup">
                @forelse($records as $r)
                    <tr role="row">
                        @switch($tab)
                            @case('machines')
                                <td data-label="Mesin" role="cell">
                                    <strong>{{ $r->code }}</strong>
                                    <div class="muted">{{ $r->name }}</div>
                                    @if($r->brand || $r->model)
                                        <small class="muted">{{ $r->brand }} {{ $r->model ? '('.$r->model.')' : '' }}</small>
                                    @endif
                                </td>
                                <td data-label="Penempatan" role="cell">
                                    <div>{{ $r->department->name ?? '—' }}</div>
                                    <small class="muted">{{ $r->location?->area }} · {{ $r->location?->name ?? '—' }}</small>
                                </td>
                                <td data-label="Formulir Ceklist" role="cell">
                                    @if($r->form_name)
                                        <span class="badge" title="Template Ceklist Terhubung">{{ $r->form_name }}</span>
                                    @else
                                        <span class="muted"><small>Belum diatur</small></span>
                                    @endif
                                </td>
                                <td data-label="Spesifikasi" role="cell">
                                    <div><small>Kondisi: <strong>{{ $r->condition ?? 'Baik' }}</strong> ({{ $r->condition_percentage ?? 100 }}%)</small></div>
                                    @if($r->power_kw || $r->power_hp)
                                        <div><small class="muted">Daya: {{ $r->power_kw ? $r->power_kw.' KW' : '' }} {{ $r->power_hp ? $r->power_hp.' HP' : '' }}</small></div>
                                    @endif
                                    @if($r->capacity)
                                        <div><small class="muted">Kapasitas: {{ $r->capacity }}</small></div>
                                    @endif
                                </td>
                                <td data-label="Status" role="cell">
                                    @if($r->active)
                                        <span class="badge verified">Aktif</span>
                                    @else
                                        <span class="badge rejected">Nonaktif</span>
                                    @endif
                                </td>
                                <td data-label="Aksi" role="cell">
                                    <div class="table-actions">
                                        @can('machines.update')
                                            <a class="button secondary small-btn" href="{{ route('admin', ['tab' => $tab, 'edit' => $r->id]) }}">Edit</a>
                                        @endcan
                                        <a class="button secondary small-btn" href="{{ route('machines.print-qr', $r) }}" target="_blank">Cetak QR</a>
                                        @can('machines.update')
                                            <form method="post" action="{{ route('admin.toggle-active', ['type' => 'machines', 'id' => $r->id, 'status' => $status]) }}" onsubmit="return confirm('Apakah Anda yakin ingin {{ $r->active ? 'menonaktifkan' : 'mengaktifkan' }} mesin {{ $r->code }}?')">
                                                @csrf
                                                <button type="submit" class="button small-btn {{ $r->active ? 'btn-deactivate' : 'btn-activate' }}">
                                                    {{ $r->active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                                @break

                            @case('locations')
                                <td data-label="Nama Lokasi" role="cell">
                                    <strong>{{ $r->name }}</strong>
                                </td>
                                <td data-label="Area / Gedung" role="cell">
                                    {{ $r->area }}
                                </td>
                                <td data-label="Status" role="cell">
                                    @if($r->active)
                                        <span class="badge verified">Aktif</span>
                                    @else
                                        <span class="badge rejected">Nonaktif</span>
                                    @endif
                                </td>
                                <td data-label="Aksi" role="cell">
                                    <div class="table-actions">
                                        @can('locations.update')
                                            <a class="button secondary small-btn" href="{{ route('admin', ['tab' => $tab, 'edit' => $r->id]) }}">Edit</a>
                                            <form method="post" action="{{ route('admin.toggle-active', ['type' => 'locations', 'id' => $r->id, 'status' => $status]) }}" onsubmit="return confirm('Apakah Anda yakin ingin {{ $r->active ? 'menonaktifkan' : 'mengaktifkan' }} lokasi ini?')">
                                                @csrf
                                                <button type="submit" class="button small-btn {{ $r->active ? 'btn-deactivate' : 'btn-activate' }}">
                                                    {{ $r->active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                                @break

                            @case('checklists')
                                <td data-label="Nama Template" role="cell">
                                    <strong>{{ $r->name }}</strong>
                                </td>
                                <td data-label="Item Periksa" role="cell">
                                    <span class="badge draft">{{ count($r->items ?? []) }} butir pemeriksaan</span>
                                </td>
                                <td data-label="Status" role="cell">
                                    @if($r->active)
                                        <span class="badge verified">Aktif</span>
                                    @else
                                        <span class="badge rejected">Nonaktif</span>
                                    @endif
                                </td>
                                <td data-label="Aksi" role="cell">
                                    <div class="table-actions">
                                        @can('checklists.update')
                                            <a class="button secondary small-btn" href="{{ route('admin', ['tab' => $tab, 'edit' => $r->id]) }}">Edit Form</a>
                                            <form method="post" action="{{ route('admin.toggle-active', ['type' => 'checklists', 'id' => $r->id, 'status' => $status]) }}" onsubmit="return confirm('Apakah Anda yakin ingin {{ $r->active ? 'menonaktifkan' : 'mengaktifkan' }} formulir ini?')">
                                                @csrf
                                                <button type="submit" class="button small-btn {{ $r->active ? 'btn-deactivate' : 'btn-activate' }}">
                                                    {{ $r->active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                                @break

                            @case('departments')
                                <td data-label="Nama Bagian" role="cell">
                                    <strong>{{ $r->name }}</strong>
                                </td>
                                <td data-label="Status" role="cell">
                                    @if($r->active)
                                        <span class="badge verified">Aktif</span>
                                    @else
                                        <span class="badge rejected">Nonaktif</span>
                                    @endif
                                </td>
                                <td data-label="Aksi" role="cell">
                                    <div class="table-actions">
                                        @can('departments.update')
                                            <a class="button secondary small-btn" href="{{ route('admin', ['tab' => $tab, 'edit' => $r->id]) }}">Edit</a>
                                            <form method="post" action="{{ route('admin.toggle-active', ['type' => 'departments', 'id' => $r->id, 'status' => $status]) }}" onsubmit="return confirm('Apakah Anda yakin ingin {{ $r->active ? 'menonaktifkan' : 'mengaktifkan' }} bagian ini?')">
                                                @csrf
                                                <button type="submit" class="button small-btn {{ $r->active ? 'btn-deactivate' : 'btn-activate' }}">
                                                    {{ $r->active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                                @break

                            @case('schedules')
                                <td data-label="Mesin" role="cell">
                                    <strong>{{ $r->machine?->code }}</strong>
                                    <div class="muted">{{ $r->machine?->name }}</div>
                                </td>
                                <td data-label="Template Checklist" role="cell">
                                    {{ $r->checklist?->name ?? '—' }}
                                </td>
                                <td data-label="Periode" role="cell">
                                    <span class="badge">{{ strtoupper($r->period) }}</span>
                                    <small class="muted">Mulai: {{ $r->starts_on?->format('d/m/Y') }}</small>
                                </td>
                                <td data-label="Status" role="cell">
                                    @if($r->active)
                                        <span class="badge verified">Aktif</span>
                                    @else
                                        <span class="badge rejected">Nonaktif</span>
                                    @endif
                                </td>
                                <td data-label="Aksi" role="cell">
                                    <div class="table-actions">
                                        @can('schedules.update')
                                            <a class="button secondary small-btn" href="{{ route('admin', ['tab' => $tab, 'edit' => $r->id]) }}">Edit</a>
                                            <form method="post" action="{{ route('admin.toggle-active', ['type' => 'schedules', 'id' => $r->id, 'status' => $status]) }}" onsubmit="return confirm('Apakah Anda yakin ingin {{ $r->active ? 'menonaktifkan' : 'mengaktifkan' }} jadwal ini?')">
                                                @csrf
                                                <button type="submit" class="button small-btn {{ $r->active ? 'btn-deactivate' : 'btn-activate' }}">
                                                    {{ $r->active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                                @break

                            @case('users')
                                <td data-label="Nama & Email" role="cell">
                                    <strong>{{ $r->name }}</strong>
                                    <div class="muted">{{ $r->email }}</div>
                                </td>
                                <td data-label="Jabatan & Bagian" role="cell">
                                    <div>{{ $r->position ?? '—' }}</div>
                                    <small class="muted">
                                        {{ $r->department?->name ?? 'Semua Bagian (Admin)' }}
                                        @if($r->location) · Line: <strong>{{ $r->location->name }}</strong> @endif
                                    </small>
                                </td>
                                <td data-label="Tipe Akun" role="cell">
                                    @if($r->is_admin)
                                        <span class="badge" style="background:#f3f0ff;color:#7054b1;">Administrator</span>
                                    @else
                                        <span class="badge draft">Pengguna Modul</span>
                                    @endif
                                </td>
                                <td data-label="Status" role="cell">
                                    @if($r->active)
                                        <span class="badge verified">Aktif</span>
                                    @else
                                        <span class="badge rejected">Nonaktif</span>
                                    @endif
                                </td>
                                <td data-label="Aksi" role="cell">
                                    <div class="table-actions">
                                        @can('users.update')
                                            <a class="button secondary small-btn" href="{{ route('admin', ['tab' => $tab, 'edit' => $r->id]) }}">Edit</a>
                                            <form method="post" action="{{ route('admin.toggle-active', ['type' => 'users', 'id' => $r->id, 'status' => $status]) }}" onsubmit="return confirm('Apakah Anda yakin ingin {{ $r->active ? 'menonaktifkan' : 'mengaktifkan' }} akun ini?')">
                                                @csrf
                                                <button type="submit" class="button small-btn {{ $r->active ? 'btn-deactivate' : 'btn-activate' }}">
                                                    {{ $r->active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                                @break

                            @default
                                <td data-label="Identitas" role="cell">{{ $r->name ?? $r->id }}</td>
                                <td data-label="Informasi" role="cell">—</td>
                                <td data-label="Aksi" role="cell">
                                    <a class="button secondary small-btn" href="{{ route('admin', ['tab' => $tab, 'edit' => $r->id]) }}">Edit</a>
                                </td>
                        @endswitch
                    </tr>
                @empty
                    <tr role="row">
                        <td colspan="6" role="cell" class="empty-state">
                            <p>Tidak ada data {{ strtolower($tabs[$tab]) }} yang ditemukan.</p>
                            @if($q !== '' || $status !== 'active')
                                <a href="{{ route('admin', ['tab' => $tab]) }}" class="button secondary small-btn" style="margin-top: 10px;">Tampilkan Semua Data Aktif</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Paginasi -->
    <div style="margin-top: 20px;">
        {{ $records->links() }}
    </div>
</div>
