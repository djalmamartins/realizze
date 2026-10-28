<?php
/** Ícones SVG compartilhados pelas telas da prévia CMS; caminhos fixos e sem HTML de usuários. */
$cmsIcons = [
    'overview' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'pages' => '<path d="M7 3h8l4 4v14H7z"/><path d="M15 3v5h4M10 12h6M10 16h6"/>',
    'edit' => '<path d="M4 20h16M6 16l10-10 3 3-10 10-4 1z"/>',
    'media' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1"/><path d="m3 17 6-6 4 4 3-3 5 5"/>',
    'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 6a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 5"/>',
    'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7m10 10 2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>',
];
$cmsIcon = static function (string $name) use ($cmsIcons): string {
    $safeName = array_key_exists($name, $cmsIcons) ? $name : 'overview';
    return '<svg data-cms-icon="' . $safeName . '" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $cmsIcons[$safeName] . '</svg>';
};
