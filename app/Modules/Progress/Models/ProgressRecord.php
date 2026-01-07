<?php

namespace App\Modules\Progress\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

/**
 * Progress Record Model
 * 
 * 进度记录模型 - 存储体重、体脂、围度等身体数据
 * 
 * @property int $id
 * @property int $user_id
 * @property string $date
 * @property float $weight
 * @property float|null $body_fat
 * @property float|null $ffmi
 * @property float|null $lean_body_mass
 * @property array|null $measurements
 * @property array|null $photos
 * @property string|null $notes
 */
class ProgressRecord extends Model
{
    use HasFactory;

    protected $table = 'progress_records';

    protected $fillable = [
        'user_id',
        'date',
        'weight',
        'body_fat',
        'ffmi',
        'lean_body_mass',
        'measurements',
        'photos',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'weight' => 'decimal:2',
        'body_fat' => 'decimal:1',
        'ffmi' => 'decimal:2',
        'lean_body_mass' => 'decimal:2',
        'measurements' => 'array',
        'photos' => 'array',
    ];

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 计算FFMI
     * FFMI = 瘦体重(kg) / 身高(m)^2
     * 
     * @param float $weight 体重(kg)
     * @param float $bodyFat 体脂率(%)
     * @param float $height 身高(cm)
     * @return array ['ffmi' => float, 'lean_body_mass' => float]
     */
    public static function calculateFFMI(float $weight, float $bodyFat, float $height): array
    {
        $heightM = $height / 100;
        $leanBodyMass = $weight * (1 - $bodyFat / 100);
        $ffmi = $leanBodyMass / ($heightM * $heightM);
        
        return [
            'ffmi' => round($ffmi, 2),
            'lean_body_mass' => round($leanBodyMass, 2),
        ];
    }

    /**
     * 保存前自动计算FFMI
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($record) {
            // 如果有体脂率，尝试计算FFMI
            if ($record->body_fat && $record->weight) {
                // 需要从用户档案获取身高
                $user = $record->user;
                if ($user && $user->userProfile) {
                    $height = $user->userProfile->height;
                    if ($height) {
                        $result = self::calculateFFMI($record->weight, $record->body_fat, $height);
                        $record->ffmi = $result['ffmi'];
                        $record->lean_body_mass = $result['lean_body_mass'];
                    }
                }
            }
        });
    }
}
