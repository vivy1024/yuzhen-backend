<?php

namespace App\Modules\Progress\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Services\Calculator\FFMICalculator;

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
     * 计算FFMI（委托给 FFMICalculator）
     *
     * @deprecated 使用 FFMICalculator::calculateSimple() 代替
     */
    public static function calculateFFMI(float $weight, float $bodyFat, float $height): array
    {
        return FFMICalculator::calculateSimple($weight, $bodyFat, $height);
    }

    /**
     * 保存前自动计算FFMI
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($record) {
            if ($record->body_fat && $record->weight) {
                $user = $record->user;
                if ($user && $user->userProfile) {
                    $height = $user->userProfile->height;
                    if ($height) {
                        $result = FFMICalculator::calculateSimple($record->weight, $record->body_fat, $height);
                        $record->ffmi = $result['ffmi'];
                        $record->lean_body_mass = $result['lean_body_mass'];
                    }
                }
            }
        });
    }
}
