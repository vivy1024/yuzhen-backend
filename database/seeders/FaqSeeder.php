<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            // 账号相关
            [
                'category' => 'account',
                'question' => '如何注册账号？',
                'answer' => "注册账号非常简单：\n\n1. 点击首页的「注册」按钮\n2. 输入您的邮箱地址\n3. 设置密码（至少8位，包含字母和数字）\n4. 输入邮箱收到的验证码\n5. 完成注册\n\n注册成功后，建议您完善个人档案，以便获得更精准的健身建议。",
                'order' => 1,
            ],
            [
                'category' => 'account',
                'question' => '忘记密码怎么办？',
                'answer' => "如果您忘记了密码，可以通过以下步骤重置：\n\n1. 在登录页面点击「忘记密码」\n2. 输入您注册时使用的邮箱\n3. 查收邮件中的验证码\n4. 输入验证码并设置新密码\n\n如果您没有收到邮件，请检查垃圾邮件文件夹，或联系客服。",
                'order' => 2,
            ],
            [
                'category' => 'account',
                'question' => '如何修改个人信息？',
                'answer' => "修改个人信息的步骤：\n\n1. 点击底部导航的「我的」\n2. 进入「个人档案」\n3. 点击「编辑」按钮\n4. 修改您需要更新的信息\n5. 点击「保存」完成修改\n\n您可以修改的信息包括：昵称、头像、身高、体重、训练目标等。",
                'order' => 3,
            ],
            [
                'category' => 'account',
                'question' => '如何注销账号？',
                'answer' => "如果您确定要注销账号，请按以下步骤操作：\n\n1. 进入「设置」页面\n2. 滑动到底部找到「注销账号」\n3. 输入密码确认身份\n4. 确认注销\n\n⚠️ 注意：账号注销后，您的所有数据将被永久删除，无法恢复。请谨慎操作。",
                'order' => 4,
            ],
            
            // 训练相关
            [
                'category' => 'training',
                'question' => '如何生成训练计划？',
                'answer' => "生成个性化训练计划的方法：\n\n1. 进入「AI助手」页面\n2. 告诉AI您的训练目标，例如：「帮我制定一个增肌训练计划」\n3. AI会根据您的档案信息生成专属计划\n4. 点击训练计划卡片上的「导入」按钮保存\n\n💡 提示：完善个人档案（身高、体重、训练经验等）可以获得更精准的计划。",
                'order' => 5,
            ],
            [
                'category' => 'training',
                'question' => '如何记录训练？',
                'answer' => "记录训练的步骤：\n\n1. 进入「训练」页面\n2. 选择「开始训练」或从计划中选择今天的训练\n3. 按照动作列表完成训练\n4. 记录每组的重量、次数和RPE（主观疲劳度）\n5. 完成后点击「结束训练」\n\n系统会自动计算您的训练量和进步趋势。",
                'order' => 6,
            ],
            [
                'category' => 'training',
                'question' => '什么是RPE？',
                'answer' => "RPE（Rating of Perceived Exertion）是主观疲劳度评分，用于衡量训练强度：\n\n- RPE 6-7：轻松，还能做很多次\n- RPE 8：有挑战，还能做2-3次\n- RPE 9：很累，只能再做1次\n- RPE 10：力竭，无法再做\n\n建议大多数训练保持在RPE 7-8，偶尔冲击RPE 9-10。",
                'order' => 7,
            ],
            [
                'category' => 'training',
                'question' => '动作库有多少动作？',
                'answer' => "我们的动作库包含 1,790+ 个专业健身动作，涵盖：\n\n- 40个肌肉群分类\n- 17种器械类型\n- 4个难度等级\n- 详细的动作要领和安全提示\n\n每个动作都有专业的图片演示和训练参数建议。",
                'order' => 8,
            ],
            
            // 会员相关
            [
                'category' => 'membership',
                'question' => '会员有什么权益？',
                'answer' => "会员权益包括：\n\n**暖心会员（¥6/月）**\n- 解锁全部13个AI场景\n- 每日3次AI对话\n- 动作要点智能提醒\n- 训练数据分析报告\n\n**能量会员（开发中）**\n- 无限AI对话\n- 个性化营养方案\n- 高级数据分析\n- 优先客服支持",
                'order' => 9,
            ],
            [
                'category' => 'membership',
                'question' => '如何购买会员？',
                'answer' => "购买会员的步骤：\n\n1. 进入「会员中心」\n2. 选择适合您的会员套餐\n3. 选择支付方式（微信/支付宝）\n4. 扫码完成支付\n5. 上传支付截图\n6. 等待审核（通常1小时内）\n\n审核通过后，会员权益立即生效。",
                'order' => 10,
            ],
            [
                'category' => 'membership',
                'question' => '会员可以退款吗？',
                'answer' => "关于退款政策：\n\n- 购买后7天内，如未使用会员权益，可申请全额退款\n- 超过7天或已使用权益，不支持退款\n- 退款申请请联系客服处理\n\n如有特殊情况，请通过「意见反馈」联系我们。",
                'order' => 11,
            ],
            
            // 技术问题
            [
                'category' => 'technical',
                'question' => 'AI回复很慢怎么办？',
                'answer' => "如果AI回复较慢，可能是以下原因：\n\n1. **网络问题**：检查网络连接是否稳定\n2. **服务器繁忙**：高峰期可能需要等待\n3. **复杂问题**：涉及训练计划生成等复杂任务需要更多时间\n\n💡 建议：\n- 确保网络稳定\n- 避开高峰时段（晚8-10点）\n- 如持续异常，请反馈给我们",
                'order' => 12,
            ],
            [
                'category' => 'technical',
                'question' => '数据会丢失吗？',
                'answer' => "您的数据安全有保障：\n\n- 所有数据存储在云端服务器\n- 定期自动备份\n- 采用加密传输和存储\n\n即使更换设备，只要登录同一账号，所有数据都会同步。\n\n⚠️ 注意：注销账号会永久删除所有数据。",
                'order' => 13,
            ],
            [
                'category' => 'technical',
                'question' => '如何清除缓存？',
                'answer' => "清除缓存的步骤：\n\n1. 进入「设置」页面\n2. 找到「离线数据管理」\n3. 点击「清除缓存」\n4. 确认清除\n\n清除缓存后，部分数据需要重新加载，但不会影响您的账号数据。",
                'order' => 14,
            ],
            [
                'category' => 'technical',
                'question' => '支持哪些浏览器？',
                'answer' => "我们支持以下浏览器的最新版本：\n\n- Chrome（推荐）\n- Safari\n- Firefox\n- Edge\n\n为获得最佳体验，建议使用Chrome浏览器，并保持浏览器更新到最新版本。",
                'order' => 15,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                $faq
            );
        }

        $this->command->info('FAQ数据填充完成，共 ' . count($faqs) . ' 条');
    }
}
