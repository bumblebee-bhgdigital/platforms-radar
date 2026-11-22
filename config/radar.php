<?php

/**
 * Radar – Außenwahrnehmung (VSM System 4) ohne Deutung: Quellen abonnieren, Einträge roh sammeln.
 */
return [
    'routing' => [
        'mode' => env('RADAR_MODE', 'path'),
        'prefix' => 'radar',
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'radar.dashboard',
        'icon'  => 'heroicon-o-signal',
        'order' => 100,
    ],

    'sidebar' => [
        [
            'group' => 'Radar',
            'items' => [
                ['label' => 'Einträge', 'route' => 'radar.dashboard', 'icon' => 'heroicon-o-newspaper'],
                ['label' => 'Quellen', 'route' => 'radar.sources.index', 'icon' => 'heroicon-o-rss'],
            ],
        ],
    ],

    // Abruf: Zeitlimit je Feed, Höchstzahl neuer Einträge je Abruf, Länge des gespeicherten Auszugs.
    'fetch' => [
        'timeout' => env('RADAR_FETCH_TIMEOUT', 30),
        'max_items' => 100,
        'excerpt_length' => 1000,
        'user_agent' => env('RADAR_USER_AGENT', 'PlatformRadar/1.0 (+feed reader)'),
    ],

    // Einträge sind Durchlauf: nach so vielen Tagen gelöscht (0 = nie). Was bleiben soll, ist die Erkenntnis –
    // sie lebt beim Leser (Gedächtnis, Signal, Notiz) mit Titel und Link als Quellenangabe.
    'retention_days' => env('RADAR_RETENTION_DAYS', 90),
];
