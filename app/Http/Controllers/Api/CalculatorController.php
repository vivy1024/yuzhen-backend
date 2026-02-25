<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Services\Calculator\TDEECalculator;
use App\Services\Calculator\FFMICalculator;
use App\Services\Calculator\OneRMCalculator;
use App\Services\Calculator\IntensityConverter;
use App\Services\Calculator\WeightRecommender;
use App\Services\Calculator\CarbCyclingCalculator;
use App\Services\Calculator\MacroCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 计算器控制器
 *
 * 7 个健身计算器的统一入口，无需认证
 * 登录用户可选 save_to_profile 保存结果到档案
 */
class CalculatorController extends BaseController
{
    /**
     * POST /api/calculators/tdee
     */
    public function tdee(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'age'                => 'required|integer|between:10,120',
                'gender'             => 'required|in:male,female',
                'weight_kg'          => 'required|numeric|between:30,300',
                'height_cm'          => 'required|numeric|between:100,250',
                'activity_level'     => 'required|in:sedentary,lightly_active,moderately_active,very_active,extremely_active',
                'fitness_goal'       => 'required|in:fat_loss,mild_fat_loss,maintenance,lean_bulk,hypertrophy,recomp',
                'save_to_profile'    => 'boolean',
            ]);

            $result = TDEECalculator::calculate($validated);

            if ($request->boolean('save_to_profile') && $request->user()) {
                $this->saveToProfile($request->user(), 'nutrition_profile', 'auto_calculated', $result);
            }

            return $this->success($result);
        } catch (\Exception $e) {
            return $this->handleException($e, 'calculator.tdee');
        }
    }

    /**
     * POST /api/calculators/ffmi
     */
    public function ffmi(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'height_cm'       => 'required|numeric|between:100,250',
                'weight_kg'       => 'required|numeric|between:30,300',
                'gender'          => 'required|in:male,female',
                'body_fat'        => 'nullable|numeric|between:3,60',
                'save_to_profile' => 'boolean',
            ]);

            $result = FFMICalculator::calculate($validated);

            if ($request->boolean('save_to_profile') && $request->user()) {
                $this->saveToProfile($request->user(), 'ffmi_assessment', null, $result);
            }

            return $this->success($result);
        } catch (\Exception $e) {
            return $this->handleException($e, 'calculator.ffmi');
        }
    }

    /**
     * POST /api/calculators/one-rm
     */
    public function oneRM(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'weight_kg' => 'required|numeric|between:1,500',
                'reps'      => 'required|integer|between:1,50',
                'formula'   => 'nullable|in:epley,brzycki,average',
            ]);

            $result = OneRMCalculator::calculate($validated);

            return $this->success($result);
        } catch (\Exception $e) {
            return $this->handleException($e, 'calculator.one_rm');
        }
    }

    /**
     * POST /api/calculators/intensity
     */
    public function intensity(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'input_type' => 'required|in:rpe,rir,percentage',
                'value'      => 'required|numeric|between:0,100',
            ]);

            $result = IntensityConverter::convert($validated);

            return $this->success($result);
        } catch (\Exception $e) {
            return $this->handleException($e, 'calculator.intensity');
        }
    }

    /**
     * POST /api/calculators/weight
     */
    public function weight(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'estimated_1rm'  => 'required|numeric|between:1,500',
                'training_goal'  => 'required|in:strength,hypertrophy,endurance,power',
                'target_reps'    => 'nullable|integer|between:1,50',
                'target_rpe'     => 'nullable|numeric|between:6,10',
            ]);

            $result = WeightRecommender::recommend($validated);

            return $this->success($result);
        } catch (\Exception $e) {
            return $this->handleException($e, 'calculator.weight');
        }
    }

    /**
     * POST /api/calculators/carb-cycling
     */
    public function carbCycling(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'tdee'            => 'required|numeric|between:800,6000',
                'weight_kg'       => 'required|numeric|between:30,300',
                'fitness_goal'    => 'required|in:fat_loss,mild_fat_loss,maintenance,lean_bulk,hypertrophy,recomp',
                'training_days'   => 'required|array|min:1|max:7',
                'training_days.*' => 'integer|between:0,6',
                'save_to_profile' => 'boolean',
            ]);

            $result = CarbCyclingCalculator::calculate($validated);

            if ($request->boolean('save_to_profile') && $request->user()) {
                $this->saveToProfile($request->user(), 'nutrition_profile', 'carb_cycling', $result);
            }

            return $this->success($result);
        } catch (\Exception $e) {
            return $this->handleException($e, 'calculator.carb_cycling');
        }
    }

    /**
     * POST /api/calculators/macros
     */
    public function macros(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'target_calories' => 'required|numeric|between:800,6000',
                'weight_kg'       => 'required|numeric|between:30,300',
                'fitness_goal'    => 'required|in:fat_loss,mild_fat_loss,maintenance,lean_bulk,hypertrophy,recomp',
                'method'          => 'nullable|in:balanced,body_weight,ratio',
                'save_to_profile' => 'boolean',
            ]);

            $result = MacroCalculator::calculate($validated);

            if ($request->boolean('save_to_profile') && $request->user()) {
                $this->saveToProfile($request->user(), 'nutrition_profile', 'macro_distribution', $result);
            }

            return $this->success($result);
        } catch (\Exception $e) {
            return $this->handleException($e, 'calculator.macros');
        }
    }

    /**
     * 保存计算结果到用户档案
     */
    private function saveToProfile($user, string $field, ?string $subField, array $data): void
    {
        $profile = $user->userProfile;
        if (!$profile) {
            return;
        }

        $data['calculated_at'] = now()->toDateString();

        if ($subField) {
            $current = $profile->{$field} ?? [];
            if (is_string($current)) {
                $current = json_decode($current, true) ?? [];
            }
            $current[$subField] = $data;
            $profile->{$field} = $current;
        } else {
            $profile->{$field} = $data;
        }

        $profile->save();
    }
}
