<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>邮箱验证码</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1890ff;
            margin-bottom: 10px;
        }
        .title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
        }
        .code-box {
            background-color: #f0f9ff;
            border: 2px dashed #1890ff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 30px 0;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            color: #1890ff;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
        }
        .info {
            color: #666;
            font-size: 14px;
            line-height: 1.8;
            margin: 20px 0;
        }
        .warning {
            background-color: #fff7e6;
            border-left: 4px solid #faad14;
            padding: 12px 16px;
            margin: 20px 0;
            font-size: 14px;
            color: #666;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e8e8e8;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🏋️ 玉珍健身</div>
            <div class="title">邮箱验证码</div>
        </div>

        <div class="info">
            您好！<br>
            您正在进行邮箱验证操作，您的验证码是：
        </div>

        <div class="code-box">
            <div class="code">{{ $code }}</div>
        </div>

        <div class="info">
            验证码有效期为 <strong>5分钟</strong>，请尽快完成验证。
        </div>

        <div class="warning">
            ⚠️ 如果这不是您本人的操作，请忽略此邮件。为了您的账户安全，请勿将验证码告知他人。
        </div>

        <div class="footer">
            <p>此邮件由系统自动发送，请勿直接回复。</p>
            <p>© {{ date('Y') }} 玉珍健身 版权所有</p>
        </div>
    </div>
</body>
</html>
