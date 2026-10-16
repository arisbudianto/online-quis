{{-- Pilihan peran saat mendaftar. Dipakai formulir register dan lengkapi-data Google. --}}
@php
    $teacherOptions = \App\Support\RoleRegistration::teacherOptions();
    $role = old('role', 'mahasiswa');
    $chosen = collect(old('teachers', []))->map(fn ($v) => (int) $v)->all();
    $inputClass = 'block mt-1.5 w-full rounded-none border-gray-300 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]';
@endphp

<div>
    <span class="text-xs font-bold uppercase tracking-wide text-gray-700">Daftar sebagai</span>
    <div class="mt-1.5 grid grid-cols-2 gap-4">
        <label class="flex items-center gap-2 border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-[#111111]">
            <input type="radio" name="role" value="mahasiswa" class="text-[#DC3B1C] focus:ring-[#DC3B1C]" @checked($role === 'mahasiswa')>
            Mahasiswa / Siswa
        </label>
        <label class="flex items-center gap-2 border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-[#111111]">
            <input type="radio" name="role" value="dosen" class="text-[#DC3B1C] focus:ring-[#DC3B1C]" @checked($role === 'dosen')>
            Dosen / Guru
        </label>
    </div>
    <x-input-error :messages="$errors->get('role')" class="mt-2" />
</div>

<div data-role-section="mahasiswa" class="space-y-5">
    <div>
        <label for="nim" class="text-xs font-bold uppercase tracking-wide text-gray-700">NIM / NIS</label>
        <x-text-input id="nim" :class="$inputClass" type="text" name="nim" :value="old('nim')" autocomplete="off" />
        <x-input-error :messages="$errors->get('nim')" class="mt-2" />
    </div>

    <div>
        <span class="text-xs font-bold uppercase tracking-wide text-gray-700">Dosen / Guru Anda</span>
        @if (count($teacherOptions) === 0)
            <p class="mt-2 text-sm text-gray-600">Belum ada dosen/guru yang terdaftar. Minta dosen/guru Anda mendaftar lebih dulu.</p>
        @else
            <p class="mt-1 text-sm text-gray-600">Pilih satu atau lebih. Dosen/guru lain bisa ditambahkan nanti dari halaman profil.</p>
            @if (count($teacherOptions) > 6)
                <input type="search" id="teacher-search" placeholder="Cari nama atau instansi" class="{{ $inputClass }}" autocomplete="off">
            @endif
            <div class="mt-2 border border-gray-300 bg-white" style="max-height: 14rem; overflow-y: auto;">
                @foreach ($teacherOptions as $t)
                    <label class="flex items-center gap-2 px-4 py-2 text-sm text-[#111111]" data-teacher-row
                           data-search="{{ strtolower($t['name'] . ' ' . ($t['institution'] ?? '')) }}">
                        <input type="checkbox" name="teachers[]" value="{{ $t['id'] }}" class="rounded-none border-gray-300 text-[#DC3B1C] focus:ring-[#DC3B1C]"
                               data-classes='@json($t['classes'])' @checked(in_array($t['id'], $chosen, true))>
                        <span>
                            <span class="font-semibold">{{ $t['name'] }}</span>
                            @if ($t['institution'])<span class="text-gray-500"> · {{ $t['institution'] }}</span>@endif
                        </span>
                    </label>
                @endforeach
            </div>
        @endif
        <x-input-error :messages="$errors->get('teachers')" class="mt-2" />
        <x-input-error :messages="$errors->get('teachers.*')" class="mt-2" />
    </div>

    <div>
        <label for="kelas" class="text-xs font-bold uppercase tracking-wide text-gray-700">Kelas</label>
        <select id="kelas" name="kelas" class="block mt-1.5 w-full rounded-none border-gray-300 py-2 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]"
                data-old="{{ old('kelas') }}">
            <option value="">Pilih dosen/guru dulu</option>
        </select>
        <x-input-error :messages="$errors->get('kelas')" class="mt-2" />
    </div>
</div>

<div data-role-section="dosen" class="space-y-5">
    <div>
        <label for="institution" class="text-xs font-bold uppercase tracking-wide text-gray-700">Kampus / Sekolah / Instansi</label>
        <x-text-input id="institution" :class="$inputClass" type="text" name="institution" :value="old('institution')" placeholder="contoh FKIP UNS, SMK Negeri 2 Surakarta" />
        <x-input-error :messages="$errors->get('institution')" class="mt-2" />
    </div>
    <p class="text-sm text-gray-600">
        Akun dosen/guru aktif setelah disetujui admin. Anda akan menerima email begitu disetujui.
    </p>
</div>

<script>
(function () {
    var radios = document.querySelectorAll('input[name="role"]');
    var sections = document.querySelectorAll('[data-role-section]');
    var kelas = document.getElementById('kelas');
    var boxes = document.querySelectorAll('input[name="teachers[]"]');
    var search = document.getElementById('teacher-search');

    function currentRole() {
        var r = document.querySelector('input[name="role"]:checked');
        return r ? r.value : 'mahasiswa';
    }

    function applyRole() {
        var role = currentRole();
        sections.forEach(function (s) {
            var on = s.getAttribute('data-role-section') === role;
            s.style.display = on ? '' : 'none';
            s.querySelectorAll('input, select').forEach(function (el) {
                if (el.type === 'search') return;
                el.disabled = !on;
            });
        });
        var nim = document.getElementById('nim');
        if (nim) nim.required = role === 'mahasiswa';
        var inst = document.getElementById('institution');
        if (inst) inst.required = role === 'dosen';
        refreshKelas();
    }

    function refreshKelas() {
        if (!kelas) return;
        var keep = kelas.value || kelas.getAttribute('data-old') || '';
        var set = {};
        var anyChecked = false;
        boxes.forEach(function (b) {
            if (!b.checked) return;
            anyChecked = true;
            JSON.parse(b.getAttribute('data-classes') || '[]').forEach(function (c) { set[c] = true; });
        });
        var names = Object.keys(set).sort();
        kelas.innerHTML = '';
        var first = document.createElement('option');
        first.value = '';
        first.textContent = !anyChecked ? 'Pilih dosen/guru dulu'
            : (names.length ? 'Pilih kelas' : 'Dosen/guru belum mengatur kelas');
        kelas.appendChild(first);
        names.forEach(function (n) {
            var o = document.createElement('option');
            o.value = n; o.textContent = n;
            if (n === keep) o.selected = true;
            kelas.appendChild(o);
        });
        kelas.required = currentRole() === 'mahasiswa' && names.length > 0;
    }

    radios.forEach(function (r) { r.addEventListener('change', applyRole); });
    boxes.forEach(function (b) { b.addEventListener('change', refreshKelas); });
    if (search) {
        search.addEventListener('input', function () {
            var q = search.value.toLowerCase().trim();
            document.querySelectorAll('[data-teacher-row]').forEach(function (row) {
                row.style.display = !q || row.getAttribute('data-search').indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }
    applyRole();
})();
</script>
