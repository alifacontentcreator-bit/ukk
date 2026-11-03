<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['name']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $icons = [
        'home' => '<path d="M3 11l9-8 9 8M5 10v10h14V10"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
        'flag' => '<path d="M6 21V4M6 5h11l-2 4 2 4H6"/>',
        'file' => '<path d="M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6"/>',
        'swap' => '<path d="M7 4L3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'refresh' => '<path d="M20 12a8 8 0 1 1-2.5-5.8M20 4v5h-5"/>',
        'logout' => '<path d="M10 4H5v16h5M15 8l4 4-4 4M19 12H9"/>',
        'plus' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-5.5 6.5-5.5s5.9 1.9 6.5 5.5M16 4.5a3.5 3.5 0 010 7M18 14.8c2 .6 3.3 2.3 3.5 5.2"/>',
        'card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'sliders' => '<path d="M4 6h10M18 6h2M4 12h2M10 12h10M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/>',
        'bell' => '<path d="M6 16v-5a6 6 0 1112 0v5l2 2H4zM10 21h4"/>',
        'qr' => '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M16 15h2"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'down' => '<path d="M12 4v12M7 11l5 5 5-5M5 20h14"/>',
        'up' => '<path d="M12 20V8M7 13l5-5 5 5M5 4h14"/>',
        'vault' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="12" cy="12" r="3.5"/><path d="M12 8.5V10M12 14v1.5M8.5 12H10M14 12h1.5"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
        'chevron-up' => '<path d="M6 15l6-6 6 6"/>',
        'chevron-left' => '<path d="M15 6l-6 6 6 6"/>',
        'chevron-right' => '<path d="M9 6l6 6-6 6"/>',
        'pencil' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M14 6l4 4"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6"/>',
        'eye' => '<path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/>',
        'eye-off' => '<path d="M3 3l18 18M10.6 6.2A10 10 0 0112 6c6.4 0 10 6 10 6a17 17 0 01-3.2 3.8M6.3 8.3A17 17 0 002 12s3.6 6 10 6a9.7 9.7 0 004.3-1"/>',
        'printer' => '<path d="M7 9V4h10v5M7 18H5a1 1 0 01-1-1v-6a1 1 0 011-1h14a1 1 0 011 1v6a1 1 0 01-1 1h-2M7 14h10v6H7z"/>',
        'save' => '<path d="M5 4h11l3 3v13H5z"/><path d="M8 4v6h7V4M8 20v-5h8v5"/>',
        'filter' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'bank' => '<path d="M3 10l9-6 9 6M5 10v9h14v-9M9 19v-5h6v5"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M10 21v-3h4v3"/>',
        'box' => '<path d="M12 3l8 4v10l-8 4-8-4V7z"/><path d="M4 7l8 4 8-4M12 11v10"/>',
        'lock' => '<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 118 0v3"/>',
        'unlock' => '<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 017-2.6"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'id-card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.5-1.7 1.7-2.5 3-2.5s2.5.8 3 2.5M15 10h4M15 13.5h4"/>',
        'coins' => '<ellipse cx="12" cy="6.5" rx="7" ry="3"/><path d="M5 6.5v5c0 1.7 3.1 3 7 3s7-1.3 7-3v-5M5 11.5v5c0 1.7 3.1 3 7 3s7-1.3 7-3v-5"/>',
        'chart' => '<path d="M4 20V4M4 20h16M8 17v-5M12 17V8M16 17v-7"/>',
        'alert' => '<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'minus' => '<path d="M6 12h12"/>',
        'plus-circle' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
        'panel-left' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/>',
        'grid' => '<rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/>',
        'book' => '<path d="M5 4h11a2 2 0 012 2v14H7a2 2 0 01-2-2z"/><path d="M8 4v16M11 8h5"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.7-3.6 3.5-5.5 7-5.5s6.3 1.9 7 5.5"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5"/><path d="M12 8v4l3 2"/>',
        'send' => '<path d="M21 3L3 10l7 3 3 7z"/><path d="M21 3l-9 15"/>',
        'phone' => '<path d="M6 3h3l2 5-2 1a11 11 0 005 5l1-2 5 2v3a2 2 0 01-2 2A16 16 0 014 6a2 2 0 012-3z"/>',
        'dots' => '<circle cx="6" cy="12" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="18" cy="12" r="1.2"/>',
        'map-pin' => '<path d="M12 21s7-5.6 7-11a7 7 0 10-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    ];
?>
<svg <?php echo e($attributes->merge(['class' => 'et-ico'])); ?> viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?php echo $icons[$name] ?? ''; ?></svg><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/components/icon.blade.php ENDPATH**/ ?>