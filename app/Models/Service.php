<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Service extends Model
{
    use BelongsToCompany, HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'services';

    protected $table = 'services';

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'description',
        'duration_minutes',
        'price',
        'assigned_user_ids', // array de strings/ObjectIds de usuarios que brindan el servicio
        'is_active',
        'image_path',
        'image_url',
        'metadata',
    ];

    public function getImageUrlAttribute(?string $value): ?string
    {
        if (! empty($value)) {
            return $value;
        }

        if (! empty($this->image_path)) {
            return asset('storage/'.$this->image_path);
        }

        return self::resolveFallbackFreeImage($this->name, $this->description);
    }

    public static function resolveFallbackFreeImage(?string $name = '', ?string $description = ''): string
    {
        $text = mb_strtolower(trim(($name ?? '').' '.($description ?? '')));

        // 1. Limpieza dental / Profilaxis
        if (str_contains($text, 'limpieza') || str_contains($text, 'profilaxis') || str_contains($text, 'sarro') || str_contains($text, 'ultrasonido')) {
            return 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=800&q=80';
        }

        // 2. Blanqueamiento dental / Estética dental
        if (str_contains($text, 'blanqueamiento') || str_contains($text, 'aclaramiento') || str_contains($text, 'estética') || str_contains($text, 'estetica') || str_contains($text, 'láser') || str_contains($text, 'laser')) {
            return 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=800&q=80';
        }

        // 3. Consulta / Valoración / Diagnóstico odontológico o médico
        if (str_contains($text, 'consulta') || str_contains($text, 'valoracion') || str_contains($text, 'valoración') || str_contains($text, 'diagnostico') || str_contains($text, 'diagnóstico') || str_contains($text, 'revisión') || str_contains($text, 'revision')) {
            return 'https://images.unsplash.com/photo-1629909613654-28e377c37b09?auto=format&fit=crop&w=800&q=80';
        }

        // 4. Ortodoncia / Brackets / Alineadores
        if (str_contains($text, 'ortodoncia') || str_contains($text, 'bracket') || str_contains($text, 'alineador') || str_contains($text, 'invisalign')) {
            return 'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=800&q=80';
        }

        // 5. Endodoncia / Conducto / Dolor de muela
        if (str_contains($text, 'endodoncia') || str_contains($text, 'conducto') || str_contains($text, 'pulpar')) {
            return 'https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&fit=crop&w=800&q=80';
        }

        // 6. Extracción / Cirugía dental
        if (str_contains($text, 'extraccion') || str_contains($text, 'extracción') || str_contains($text, 'cirugia') || str_contains($text, 'cirugía') || str_contains($text, 'cordal') || str_contains($text, 'muela del juicio')) {
            return 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800&q=80';
        }

        // 7. Implantes / Prótesis / Coronas
        if (str_contains($text, 'implante') || str_contains($text, 'prótesis') || str_contains($text, 'protesis') || str_contains($text, 'corona') || str_contains($text, 'puente')) {
            return 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=800&q=80';
        }

        // 8. Radiografía / Rayos X
        if (str_contains($text, 'radiografia') || str_contains($text, 'radiografía') || str_contains($text, 'rayos x') || str_contains($text, 'panorámica') || str_contains($text, 'tomografia')) {
            return 'https://images.unsplash.com/photo-1516549655169-df83a0774514?auto=format&fit=crop&w=800&q=80';
        }

        // 9. Jurídico / Legal / Asesoría / Abogado
        if (str_contains($text, 'jurídic') || str_contains($text, 'juridic') || str_contains($text, 'legal') || str_contains($text, 'abogad') || str_contains($text, 'mercantil') || str_contains($text, 'contrato')) {
            return 'https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=800&q=80';
        }

        // 10. Auditoría / Fiscal / Contable / Finanzas
        if (str_contains($text, 'auditoria') || str_contains($text, 'auditoría') || str_contains($text, 'fiscal') || str_contains($text, 'contable') || str_contains($text, 'impuesto') || str_contains($text, 'tributari')) {
            return 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?auto=format&fit=crop&w=800&q=80';
        }

        // 11. Masajes / Spa / Terapia
        if (str_contains($text, 'masaje') || str_contains($text, 'spa') || str_contains($text, 'relajante') || str_contains($text, 'terapia') || str_contains($text, 'facial')) {
            return 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?auto=format&fit=crop&w=800&q=80';
        }

        // 12. Peluquería / Barbería / Corte
        if (str_contains($text, 'corte') || str_contains($text, 'cabello') || str_contains($text, 'barba') || str_contains($text, 'peluqueria') || str_contains($text, 'barberia')) {
            return 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=800&q=80';
        }

        $genericPool = [
            'https://images.unsplash.com/photo-1629909613654-28e377c37b09?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1576091160550-2173dba999ef?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=800&q=80',
        ];

        $index = abs(crc32($text ?: 'service')) % count($genericPool);

        return $genericPool[$index];
    }

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'float',
            'assigned_user_ids' => 'array',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
