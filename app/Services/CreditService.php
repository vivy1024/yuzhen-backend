<?php

namespace App\Services;

use App\Services\Credit\CreditCalculatorTrait;
use App\Services\Credit\CreditQueryTrait;
use App\Services\Credit\CreditMutationTrait;

/**
 * CreditService - 积分管理服务
 *
 * 通过 Trait 模式将方法分散到独立模块，保持 API 完全向后兼容。
 *
 * 拆分结构:
 * - CreditCalculatorTrait: 积分计算 + 会员等级查询 + 升级提示
 * - CreditQueryTrait: 余额查询 + 充足性检查 + 历史记录 + 系统统计
 * - CreditMutationTrait: 记录消耗 + 配额重置 + 添加/扣减额度
 *
 * 积分计算公式：credits = ceil(tokens × multiplier / 1000)
 * - Agent模式：multiplier = 1.5
 * - DAG模式：multiplier = 1.0
 * - 最小消耗：1积分
 *
 * 不变量：
 * - 积分余额永远不能为负数
 * - 所有积分变更必须记录流水
 * - 最小消耗为1积分
 *
 * @version v2.0.0
 * @date 2026-02-05
 * @author 薛小川
 */
class CreditService
{
    use CreditCalculatorTrait;
    use CreditQueryTrait;
    use CreditMutationTrait;

    /**
     * 积分计算常量
     */
    const TOKENS_PER_CREDIT = 1000;
    const AGENT_MULTIPLIER = 1.5;
    const DAG_MULTIPLIER = 1.0;
    const MIN_CREDITS = 1;

    /**
     * 每日配额（按会员等级）
     */
    const DAILY_QUOTAS = [
        'free' => 10,
        'warmheart' => 50,
        'energy' => 200,
    ];
}
