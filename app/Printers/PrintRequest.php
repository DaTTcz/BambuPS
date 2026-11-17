<?php

namespace App\Printers;

/**
 * Požadavek na spuštění tisku - stejný pro všechny ovladače. Ovladač si
 * z něj vezme, co jeho tiskárna umí, a zbytek ignoruje.
 */
final class PrintRequest
{
    /**
     * @param string $remoteName  jméno souboru na tiskárně (po uploadFile)
     * @param int    $plateIndex  deska v .3mf projektu (1 = první); jinde se ignoruje
     * @param array  $materials   mapování filamentů na sloty:
     *                            [['unit' => int, 'slot' => int, 'extruder_index' => int], ...]
     * @param array  $options     volby tisku (klíče viz konstanty OPT_*), neznámé se ignorují
     */
    public function __construct(
        public readonly string $remoteName,
        public readonly int $plateIndex = 1,
        public readonly array $materials = [],
        public readonly array $options = [],
    ) {}

    public const OPT_USE_MATERIAL_SYSTEM  = 'use_material_system';
    public const OPT_BED_TYPE             = 'bed_type';
    public const OPT_BED_LEVELING         = 'bed_leveling';
    public const OPT_TIMELAPSE            = 'timelapse';
    public const OPT_FIRST_LAYER_INSPECT  = 'first_layer_inspection';
    public const OPT_FLOW_CALIBRATION     = 'flow_calibration';
    public const OPT_VIBRATION_CALIBRATION = 'vibration_calibration';

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
