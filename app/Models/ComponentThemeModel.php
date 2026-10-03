<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class ComponentThemeModel extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'component_theme';

    protected $table = 'component_theme';

    protected $fillable = [
        'styles',
        'active_theme',
        'landing_palette',
        'landing_palette_preset',
        'logo_url',
        'auth_side_image_url',
        'auth_side_image_pos_x',
        'auth_side_image_pos_y',
    ];
}
