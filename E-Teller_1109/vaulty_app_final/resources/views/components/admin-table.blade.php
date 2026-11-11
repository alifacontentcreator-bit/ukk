{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman admin table, termasuk data dan aksi yang disediakan aplikasi. --}}
@props([
    'title' => null,
    'description' => null,
])

{{-- Kartu tabel bersama: dipakai halaman yang membutuhkan tabel di dalam kartu
     lengkap dengan judul, keterangan, dan slot aksi di kanan judul. --}}
<section {{ $attributes->merge(['class' => 'card']) }}>
    @if($title || $description || isset($headerActions))
        <div class="section-head">
            <div>
                @if($title)
                    <div class="section-title">{{ $title }}</div>
                @endif
                @if($description)
                    <div class="section-sub">{{ $description }}</div>
                @endif
            </div>
            @if(isset($headerActions))
                <div>{{ $headerActions }}</div>
            @endif
        </div>
    @endif
    <div class="vaulty-table-wrap">
        <table class="table">
            {{ $slot }}
        </table>
    </div>
</section>
