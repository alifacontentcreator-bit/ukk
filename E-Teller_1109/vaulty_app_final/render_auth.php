<?php
// Skrip sementara: merender halaman auth ke HTML statis untuk pemeriksaan visual.
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

view()->share('errors', new Illuminate\Support\ViewErrorBag());

$base = 'file:///' . str_replace('\\', '/', __DIR__ . '/public');
$dir = __DIR__ . '/public/_preview';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$pages = [
    'login' => view('auth.login')->render(),
    'lupa' => view('auth.lupa-sandi')->render(),
    'reset' => view('auth.reset-sandi', [
        'token' => 'demo-token',
        'email' => 'teller@smkn1bantul.sch.id',
    ])->render(),
];

foreach ($pages as $name => $html) {
    // asset() menghasilkan URL absolut (APP_URL); untuk pratinjau file:// diarahkan ke folder public.
    $html = preg_replace(
        '#(?:https?:)?//[^/"\']+/(build|images)/#i',
        $base . '/$1/',
        $html
    );
    // Instrumentasi khusus pratinjau: tulis ukuran tata letak ke atribut body.
    $probe = <<<'HTML'
<script>
window.addEventListener('load', function () {
    var p = document.querySelector('.auth-panel');
    var w = document.querySelector('.auth-form-wrap');
    var v = document.querySelector('.auth-visual-inner');
    var r = w ? w.getBoundingClientRect() : null;
    var vr = v ? v.getBoundingClientRect() : null;
    var m = {
        win: [window.innerWidth, window.innerHeight],
        docScroll: document.documentElement.scrollHeight,
        panelScroll: p ? p.scrollHeight : null,
        panelClient: p ? p.clientHeight : null,
        wrapTop: r ? Math.round(r.top) : null,
        wrapBottom: r ? Math.round(r.bottom) : null,
        visualBottom: vr ? Math.round(vr.bottom) : null,
        visualTop: vr ? Math.round(vr.top) : null
    };
    document.body.setAttribute('data-metrics', JSON.stringify(m));
});
</script>
HTML;
    $html = str_replace('</body>', $probe . "\n</body>", $html);
    file_put_contents($dir . '/' . $name . '.html', $html);
    echo $name . " rendered\n";
}

// Harness: iframe berukuran tepat supaya media query & tangkapan layar akurat.
$harness = <<<'HTML'
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
html,body{margin:0;height:100%;background:#111;overflow:hidden}
iframe{border:0;display:block;background:#fff}
</style>
</head>
<body>
<iframe id="f"></iframe>
<script>
var q = new URLSearchParams(location.search);
var f = document.getElementById('f');
f.style.width = (q.get('w') || 1366) + 'px';
f.style.height = (q.get('h') || 768) + 'px';
f.src = (q.get('p') || 'login') + '.html';
function measure() {
    var d = f.contentDocument;
    if (!d || !d.body) return;
    var p = d.querySelector('.auth-panel');
    var w = d.querySelector('.auth-form-wrap');
    var v = d.querySelector('.auth-visual-inner');
    var vis = d.querySelector('.auth-visual');
    var idn = d.querySelector('.auth-identity');
    var ttl = d.querySelector('.auth-title');
    var r = w ? w.getBoundingClientRect() : null;
    var vr = v ? v.getBoundingClientRect() : null;
    var ir = idn ? idn.getBoundingClientRect() : null;
    var tr = ttl ? ttl.getBoundingClientRect() : null;
    var m = {
        win: [d.defaultView.innerWidth, d.defaultView.innerHeight],
        docScroll: d.documentElement.scrollHeight,
        panelScroll: p ? p.scrollHeight : null,
        panelClient: p ? p.clientHeight : null,
        wrapTop: r ? Math.round(r.top) : null,
        wrapBottom: r ? Math.round(r.bottom) : null,
        visualTop: vr ? Math.round(vr.top) : null,
        visualBottom: vr ? Math.round(vr.bottom) : null,
        visualDisplay: vis ? d.defaultView.getComputedStyle(vis).display : null,
        identityH: ir ? Math.round(ir.height) : null,
        titleH: tr ? Math.round(tr.height) : null
    };
    document.body.setAttribute('data-metrics', JSON.stringify(m));
}
setInterval(measure, 120);
</script>
</body>
</html>
HTML;
file_put_contents($dir . '/harness.html', $harness);
echo "harness rendered\n";

