<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    'jquery' => [
        'version' => '4.0.0',
    ],
    'sortablejs' => [
        'version' => '1.15.7',
    ],
    'sweetalert2' => [
        'version' => '11.26.25',
    ],
    'bootstrap' => [
        'version' => '5.3.8',
    ],
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.8',
        'type' => 'css',
    ],
    'apexcharts' => [
        'version' => '8.5.0',
    ],
    'tom-select' => [
        'version' => '2.6.2',
    ],
    'tom-select/dist/css/tom-select.bootstrap5.min.css' => [
        'version' => '2.6.2',
        'type' => 'css',
    ],
    'flatpickr' => [
        'version' => '4.6.13',
    ],
    'flatpickr/dist/l10n/sr.js' => [
        'version' => '4.6.13',
    ],
    'flatpickr/dist/flatpickr.min.css' => [
        'version' => '4.6.13',
        'type' => 'css',
    ],
    'imask' => [
        'version' => '7.6.1',
    ],
    'countup.js' => [
        'version' => '2.10.1',
    ],
    'autosize' => [
        'version' => '6.0.1',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'apexcharts/core' => [
        'version' => '8.5.0',
    ],
    '@orchidjs/sifter' => [
        'version' => '1.1.0',
    ],
    '@orchidjs/unicode-variants' => [
        'version' => '1.1.2',
    ],
    'filepond' => [
        'version' => '4.32.12',
    ],
    'filepond/dist/filepond.min.css' => [
        'version' => '4.32.12',
        'type' => 'css',
    ],
    'filepond-plugin-file-validate-type' => [
        'version' => '1.2.9',
    ],
    'filepond-plugin-file-validate-size' => [
        'version' => '2.2.8',
    ],
    'pdfjs-dist' => [
        'version' => '6.4.299',
    ],
    'pdfjs-dist/build/pdf.worker.min.mjs' => [
        'version' => '6.4.299',
    ],
    '@fullcalendar/core' => [
        'version' => '6.1.22',
    ],
    '@fullcalendar/daygrid' => [
        'version' => '6.1.22',
    ],
    '@fullcalendar/timegrid' => [
        'version' => '6.1.22',
    ],
    '@fullcalendar/list' => [
        'version' => '6.1.22',
    ],
    '@fullcalendar/interaction' => [
        'version' => '6.1.22',
    ],
    '@fullcalendar/core/index.js' => [
        'version' => '6.1.22',
    ],
    '@fullcalendar/core/internal.js' => [
        'version' => '6.1.22',
    ],
    '@fullcalendar/core/preact.js' => [
        'version' => '6.1.22',
    ],
    '@fullcalendar/daygrid/internal.js' => [
        'version' => '6.1.22',
    ],
    'preact' => [
        'version' => '10.12.1',
    ],
    'preact/compat' => [
        'version' => '10.12.1',
    ],
    'preact/hooks' => [
        'version' => '10.12.1',
    ],
];
