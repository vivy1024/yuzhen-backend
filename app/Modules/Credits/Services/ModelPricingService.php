<?php

namespace App\Modules\Credits\Services;

use App\Modules\Credits\Models\ModelPricing;
use Illuminate\Support\Facades\Cache;

/**
 * ModelPricingService - 模型定价服务
 *
 * 负责计算 AI 模型调用的积分消耗和实际 USD 成本
 */
class ModelPricingService
{
    /**
     * 缓存 TTL（秒）
     */
    private const CACHE_TTL = 3600;

    /**
     * 计算 credits 消耗
     *
     * @param string $model 模型名称
     * @param int $inputTokens 输入 token 数
     * @param int $outputTokens 输出 token 数
     * @return string credits 消耗量（使用 bcmath 精确计算）
     */
    public function calculateCreditsCost(string $model, int $inputTokens, int $outputTokens): string
    {
        $pricing = $this->getPricing($model);

        if (!$pricing) {
            // 未知模型按 baseline 层级计费
            return $this->calculateWithRates('0.050000', '0.150000', $inputTokens, $outputTokens);
        }

        return $this->calculateWithRates(
            $pricing->input_price_per_ktoken,
            $pricing->output_price_per_ktoken,
            $inputTokens,
            $outputTokens
        );
    }

    /**
     * 计算实际 USD 成本
     *
     * @param string $model 模型名称
     * @param int $inputTokens 输入 token 数
     * @param int $outputTokens 输出 token 数
     * @return string USD 成本
     */
    public function calculateRealCostUsd(string $model, int $inputTokens, int $outputTokens): string
    {
        $pricing = $this->getPricing($model);

        if (!$pricing) {
            return '0.00000000';
        }

        $inputCost = bcmul(
            bcdiv((string) $inputTokens, '1000', 8),
            $pricing->real_input_cost_usd,
            8
        );

        $outputCost = bcmul(
            bcdiv((string) $outputTokens, '1000', 8),
            $pricing->real_output_cost_usd,
            8
        );

        return bcadd($inputCost, $outputCost, 8);
    }

    /**
     * 获取定价表
     *
     * @return array
     */
    public function getPricingTable(): array
    {
        return Cache::remember('model_pricing_table', self::CACHE_TTL, function () {
            return ModelPricing::enabled()
                ->orderBy('cost_tier')
                ->orderBy('model_name')
                ->get()
                ->map(fn(ModelPricing $p) => [
                    'model_name' => $p->model_name,
                    'input_price_per_ktoken' => $p->input_price_per_ktoken,
                    'output_price_per_ktoken' => $p->output_price_per_ktoken,
                    'cost_tier' => $p->cost_tier,
                ])
                ->toArray();
        });
    }

    /**
     * 获取模型层级
     *
     * @param string $model 模型名称
     * @return string 层级：free|low|free_quota|baseline
     */
    public function getModelTier(string $model): string
    {
        $pricing = $this->getPricing($model);

        return $pricing ? $pricing->cost_tier : ModelPricing::TIER_BASELINE;
    }

    /**
     * 获取模型定价（带缓存）
     */
    private function getPricing(string $model): ?ModelPricing
    {
        return Cache::remember("model_pricing:{$model}", self::CACHE_TTL, function () use ($model) {
            return ModelPricing::findByModel($model);
        });
    }

    /**
     * 按费率计算 credits 消耗
     *
     * 公式: (inputTokens / 1000) * inputRate + (outputTokens / 1000) * outputRate
     */
    private function calculateWithRates(string $inputRate, string $outputRate, int $inputTokens, int $outputTokens): string
    {
        $inputCost = bcmul(
            bcdiv((string) $inputTokens, '1000', 6),
            $inputRate,
            6
        );

        $outputCost = bcmul(
            bcdiv((string) $outputTokens, '1000', 6),
            $outputRate,
            6
        );

        return bcadd($inputCost, $outputCost, 6);
    }
}
