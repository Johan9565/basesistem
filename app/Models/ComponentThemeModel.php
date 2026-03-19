<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class ComponentThemeModel extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'component_theme';
    protected $table      = 'component_theme';

    protected $fillable = ['styles', 'active_theme'];
}
