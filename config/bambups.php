<?php

return [
    /*
    |--------------------------------------------------------------------
    | GitHub repozitář appky (formát "vlastník/repo")
    |--------------------------------------------------------------------
    | Používá se pro zjišťování dostupných aktualizací přes GitHub API.
    | Pokud appku forkneš, uprav BAMBUPS_GITHUB_REPO v .env.
    */
    'github_repo' => env('BAMBUPS_GITHUB_REPO', 'DaTTcz/bambups'),

    /*
    |--------------------------------------------------------------------
    | Cesta k appce na disku (kde běží "git")
    |--------------------------------------------------------------------
    */
    'app_path' => env('BAMBUPS_APP_PATH', '/opt/bambups/app'),
];
