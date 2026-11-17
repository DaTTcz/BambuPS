<?php

namespace App\Printers;

/**
 * Schopnosti, které ovladač tiskárny může umět. Appka podle nich zobrazuje
 * nebo skrývá ovládací prvky (např. AMS, světlo, kalibrace v dialogu tisku).
 */
final class Capability
{
    /** Pozastavit / pokračovat / zastavit tisk */
    public const PRINT_CONTROL = 'print_control';
    /** Nastavení teploty trysky a podložky */
    public const TEMPERATURE = 'temperature';
    /** Ovládání ventilátorů */
    public const FANS = 'fans';
    /** Rychlost tisku v % */
    public const SPEED = 'speed';
    /** Pohyb os a home */
    public const MOTION = 'motion';
    /** Odeslání libovolného G-code */
    public const GCODE = 'gcode';
    /** Ovládání světla */
    public const LIGHT = 'light';
    /** Vestavěná kamera (stream bez dalšího nastavení) */
    public const CAMERA = 'camera';
    /** Přepínání rozlišení kamery */
    public const CAMERA_RESOLUTION = 'camera_resolution';
    /** Zásobník filamentů s mapováním slotů (Bambu AMS, Prusa MMU...) */
    public const MATERIAL_SYSTEM = 'material_system';
    /** Víc nástrojů / hlav (Prusa XL, toolchanger) */
    public const MULTI_TOOL = 'multi_tool';
    /** Projekt s víc deskami (.3mf s plate_N.gcode) */
    public const MULTI_PLATE = 'multi_plate';
    /** Volby tisku Bambu: vyrovnání podložky, kalibrace, timelapse... */
    public const BED_LEVELING = 'bed_leveling';
    public const FLOW_CALIBRATION = 'flow_calibration';
    public const VIBRATION_CALIBRATION = 'vibration_calibration';
    public const FIRST_LAYER_INSPECTION = 'first_layer_inspection';
    public const TIMELAPSE = 'timelapse';
    /** Vynechání objektů (před tiskem i během něj) */
    public const SKIP_OBJECTS = 'skip_objects';
    /** Kódy chyb tiskárny (Bambu HMS) */
    public const ERROR_CODES = 'error_codes';
}
