<?php

return [
    'settings' => [
        'default' => [
            'auto_activate'    => true,
            'activate_parents' => true,
            'active_class'     => 'active',
            'restful'          => false,
            'cascade_data'     => true,
            'rest_base'        => '',      // string|array
            'active_element'   => 'item',  // item|link
        ],
        'master' => [
            'restful'           => true
        ],
        '_forum' => [
            'auto_activate'    => false,
            'restful'          => true
        ],
        'job_tabs' => [
            'auto_activate'    => false
        ]
    ],
    'master' => [
        'Forum' => ['route' => 'forum.home'],
        'Mikroblogi' => ['route' => 'microblog.home'],
        'Praca' => ['route' => 'job.home'],
        'Kompendium' => [
            'url' => 'Kategorie',
            'children' => [
                'Delphi' => ['url' => 'Delphi'],
                'C/C++' => ['url' => 'C'],
                'C#' => ['url' => 'C_sharp'],
                'Python' => ['url' => 'Python'],
                'Java' => ['url' => 'Java'],
                'Turbo Pascal' => ['url' => 'Turbo_Pascal'],
                'Z pogranicza' => ['url' => 'Z_pogranicza'],
                'Assembler' => ['url' => 'Assembler'],
                'Algorytmy' => ['url' => 'Algorytmy'],
                '(X)HTML' => ['url' => '(X)HTML'],
                'CSS' => ['url' => 'CSS']
            ]
        ],
        'Inne' => [
            'url' => 'javascript:void(0)',
            'children' => [
                'Czat' => [
                    'url' => 'http://chat.4programmers.net/'
                ],
                'Pastebin' => [
                    'route' => 'pastebin.show'
                ],
                'Użytkownicy' => [
                    'route' => 'profile.list'
                ]
            ]
        ]
    ],
    // _ na poczatku gdyz ten plugin korzysta z metody share() klasy View, a nazwa "forum" moze
    // wchodzic w konflikt z innymi zmiennymi przekazywanymi do twiga
    '_forum' => [
        'Kategorie' => ['route' => 'forum.categories', 'rel' => 'nofollow'],
        'Wszystkie' => ['route' => 'forum.all'],
        'Bez odpowiedzi' => ['route' => 'forum.unanswered'],
        'Obserwowane' => ['route' => 'forum.subscribes', 'data' => ['role' => true]],
        'Moje' => ['route' => 'forum.mine', 'data' => ['role' => true], 'title' => 'Wątki w których brałem udział'],
        'Moje tagi' => ['route' => 'forum.interesting', 'title' => 'Wątki zawierające moje tagi']
    ],
    '_jobs' => [
        'Wszystkie' => ['route' => ['job.home', 'tab' => 'all'], 'nickname' => 'all'],
        'Wybrane dla mnie' => ['route' => ['job.home', 'tab' => 'filtered'], 'nickname' => 'filtered'],
        'Moje oferty' => ['route' => 'job.my', 'nickname' => 'my_offers', 'data' => ['role' => true]]
    ]
];
