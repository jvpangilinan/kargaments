<?php
// includes/icons.php — simple line icons (inline SVG, no internet or icon font needed).
//   echo km_icon('home');
// They take the text color, so they work in normal and High contrast mode.

if (!function_exists('km_icon')) {
    function km_icon(string $name, string $class = 'km-svg'): string
    {
        static $paths = [
            'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h5v-6h4v6h5V9.5"/>',
            'box'      => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>',
            'swap'     => '<path d="M4 8h13l-3-3M20 16H7l3 3"/>',
            'receipt'  => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
            'inbox'    => '<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M5 5h14l2 8v6H3v-6z"/>',
            'send'     => '<path d="M21 3 10 14"/><path d="M21 3 14 21l-4-7-7-4z"/>',
            'tag'      => '<path d="M3 3h8l10 10-8 8L3 11z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
            'truck'    => '<path d="M2 6h11v10H2zM13 10h4l4 4v2h-8z"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
            'chart'    => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-5M12 16V8M16 16v-3"/>',
            'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.6 3.4-5.5 6.5-5.5s5.7 1.9 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.8c1.8.7 3 2.3 3.5 5.2"/>',
            'list'     => '<path d="M9 6h12M9 12h12M9 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
            'save'     => '<path d="M4 4h13l3 3v13H4z"/><path d="M8 4v5h8V4M8 20v-6h8v6"/>',
            'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4.2 4.2-6.5 8-6.5s7 2.3 8 6.5"/>',
            'logout'   => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
            'cart'     => '<path d="M3 4h2.5l2.2 11h10.5L21 7H6.3"/><circle cx="9" cy="19.5" r="1.5"/><circle cx="17" cy="19.5" r="1.5"/>',
            'download' => '<path d="M12 4v11M7 10.5l5 5 5-5"/><path d="M4 20h16"/>',
            'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
            'alert'    => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>',
            'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
            'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
        ];
        $p = $paths[$name] ?? $paths['box'];
        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
    }
}
