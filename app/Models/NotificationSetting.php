<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'enabled',
        'config',
        'on_print_done',
        'on_print_failed',
        'on_hms_error',
        'on_filament_runout',
    ];

    protected $casts = [
        'enabled'            => 'boolean',
        'config'             => 'array',
        'on_print_done'      => 'boolean',
        'on_print_failed'    => 'boolean',
        'on_hms_error'       => 'boolean',
        'on_filament_runout' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
