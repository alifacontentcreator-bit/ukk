{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman rekening create, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.admin')

@section('title', 'Buat Rekening')

@section('content')
    <div class="page-head">
        <div>
            <h1>Buat Rekening</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><a href="{{ route('admin.rekening') }}">Master Rekening</a><i>/</i><b>Buat Rekening</b></div>
        </div>
    </div>

    <section class="card" style="max-width:720px">
        <div class="section-head">
            <div>
                <div class="section-title">Pembuatan Rekening Nasabah</div>
                <div class="section-sub">Hak Administrator: membuat rekening.</div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.rekening.store') }}">
            @csrf
            <div class="card-body stack">
                <div class="field">
                    <label for="user_id">Nasabah</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">Pilih nasabah</option>
                        @forelse($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('user_id') == $customer->id)>
                                {{ $customer->name }} — {{ $customer->username }}
                            </option>
                        @empty
                            <option value="" disabled>Belum ada nasabah tanpa rekening.</option>
                        @endforelse
                    </select>
                    @error('user_id')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="account_number">Nomor Rekening</label>
                        <input id="account_number" type="text" value="Dibuat otomatis oleh sistem" disabled
                               title="Nomor rekening dibuat otomatis setelah disimpan">
                    </div>
                    <div class="field">
                        <label for="balance">Saldo Awal</label>
                        <input id="balance" type="number" name="balance" value="{{ old('balance', 0) }}" min="0" step="0.01" required>
                        @error('balance')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <p class="callout callout-info">Nomor rekening dibentuk otomatis mengikuti aturan sekolah, sehingga tidak perlu diisi manual.</p>
            </div>

            <div class="form-actions" style="padding:0 12px 12px;margin:0;border-top:0">
                <a class="btn btn-soft btn-sm" href="{{ route('admin.rekening') }}">Batal</a>
                <button class="btn btn-primary btn-sm" type="submit"><x-icon name="save" /> Simpan Rekening</button>
            </div>
        </form>
    </section>
@endsection
