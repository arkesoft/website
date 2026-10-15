<?php
declare(strict_types=1);

/** Transparent logo artwork; CSS frames the monogram and wordmark responsively. */
function brand_logo(bool $full=false): string {
    $image='<img src="/assets/arkesoft-logo-transparent.png" width="1600" height="1600" alt="" decoding="async" aria-hidden="true">';
    return '<span class="brand-logo'.($full?' brand-logo-full':'').'" role="img" aria-label="ARKESOFT — Yazılım ve Dijital Stüdyo">'.($full?'<span class="brand-window brand-full">'.$image.'</span>':'<span class="brand-window brand-monogram">'.$image.'</span><span class="brand-window brand-name">'.$image.'</span>').'</span>';
}
