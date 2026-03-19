<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ComponentThemeModel extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'component_theme';
    protected $table      = 'component_theme';

    protected $fillable = ['styles'];
}
