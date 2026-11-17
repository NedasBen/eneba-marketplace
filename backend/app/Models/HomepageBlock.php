<?php

namespace App\Models;

use App\Enums\HomepageBlockType;
use Database\Factories\HomepageBlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomepageBlock extends Model
{
    /** @use HasFactory<HomepageBlockFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'title',
        'position',
        'is_active',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HomepageBlockType::class,
            'position' => 'integer',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }
}
