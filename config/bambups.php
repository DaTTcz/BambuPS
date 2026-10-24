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

    /*
    |--------------------------------------------------------------------
    | Nejstarší verze, na kterou jde přejít ze stránky Nastavení → Verze
    |--------------------------------------------------------------------
    | Starší verze neumí aktualizaci z appky (nebo bez restartu služeb),
    | takže by se z nich nedalo vrátit zpátky na novou verzi.
    */
    /*
    | Chytré vyrovnání podložky: měřit znovu, když je poslední mesh starší
    | než N dní, nebo se teplota podložky liší o víc než X °C.
    */
    'bed_mesh_max_age_days'   => (int) env('BAMBUPS_BED_MESH_MAX_AGE_DAYS', 7),
    'bed_mesh_temp_tolerance' => (int) env('BAMBUPS_BED_MESH_TEMP_TOLERANCE', 10),

    'min_switch_version' => env('BAMBUPS_MIN_SWITCH_VERSION', 'v0.1.10'),
];
