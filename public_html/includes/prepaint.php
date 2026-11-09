<?php
// includes/prepaint.php — tiny inline script placed in <head> BEFORE the stylesheet.
// It applies the "Aa" settings saved on this device (text size, simple view, high
// contrast) to <html> before anything is drawn, so the page never flashes small text.
?>
<script>
(function () {
    try {
        var s = JSON.parse(localStorage.getItem('km_display') || '{}');
        var c = document.documentElement.classList;
        if (s.size === 1) c.add('km-text-lg');
        if (s.size === 2) c.add('km-text-xl');
        if (s.simple) c.add('km-simple');
        if (s.contrast) c.add('km-contrast');
    } catch (e) { /* storage blocked: use the normal look */ }
})();
</script>
