#!/usr/bin/env python3
"""
从 exercises_v2 原始数据导入 correct_steps 和 correct_steps_zh 到数据库
"""
import json
import os
import mysql.connector
from pathlib import Path
import sys

# MySQL 配置
MYSQL_CONFIG = {
    'host': os.getenv('MYSQL_HOST', 'localhost'),
    'user': os.getenv('MYSQL_USER', 'fitness_user'),
    'password': os.getenv('MYSQL_PASSWORD', 'fitness_pass'),
    'database': os.getenv('MYSQL_DATABASE', 'fitness_app'),
    'port': int(os.getenv('MYSQL_PORT', 3306))
}

# 原始数据目录
EXERCISES_V2_PATH = Path(__file__).parent.parent / 'storage' / 'app' / 'public' / 'exercises_v2'

def connect_db():
    """连接到 MySQL 数据库"""
    try:
        conn = mysql.connector.connect(**MYSQL_CONFIG)
        return conn
    except mysql.connector.Error as err:
        print(f"❌ 数据库连接失败: {err}")
        sys.exit(1)

def load_exercise_data(json_path):
    """加载 JSON 文件中的动作数据"""
    try:
        with open(json_path, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        print(f"❌ 加载 JSON 失败 {json_path}: {e}")
        return None

def import_correct_steps():
    """导入 correct_steps 数据"""
    conn = connect_db()
    cursor = conn.cursor()
    
    imported_count = 0
    skipped_count = 0
    error_count = 0
    
    print(f"🔍 扫描数据目录: {EXERCISES_V2_PATH}")
    
    # 递归扫描所有 data.json 文件
    for json_file in EXERCISES_V2_PATH.rglob('data.json'):
        try:
            data = load_exercise_data(json_file)
            if not data:
                continue
            
            exercise_id = data.get('id')
            if not exercise_id:
                skipped_count += 1
                continue
            
            # 获取 correct_steps 和 correct_steps_zh
            correct_steps = data.get('correct_steps')
            correct_steps_zh = data.get('correct_steps_zh')
            
            # 如果没有 correct_steps，尝试从 description 中提取（如果和 description 重复就说明没有数据）
            if not correct_steps and not correct_steps_zh:
                # 检查 description 是否包含步骤信息
                description = data.get('description', '')
                if 'Correct Steps' in description or 'correct' in description.lower():
                    print(f"⚠️  Exercise {exercise_id}: description 包含步骤，但 correct_steps 为空（可能已复制）")
                skipped_count += 1
                continue
            
            # 转换为 JSON 字符串
            correct_steps_json = json.dumps(correct_steps) if correct_steps else None
            correct_steps_zh_json = json.dumps(correct_steps_zh) if correct_steps_zh else None
            
            # 更新数据库
            update_query = """
                UPDATE exercises 
                SET correct_steps = %s, correct_steps_zh = %s
                WHERE id = %s
            """
            
            cursor.execute(update_query, (correct_steps_json, correct_steps_zh_json, exercise_id))
            conn.commit()
            
            imported_count += 1
            if imported_count % 10 == 0:
                print(f"✅ 已导入 {imported_count} 条数据...")
        
        except Exception as e:
            print(f"❌ 处理失败 {json_file}: {e}")
            error_count += 1
            continue
    
    cursor.close()
    conn.close()
    
    # 输出统计
    print("\n" + "="*50)
    print(f"📊 导入统计结果:")
    print(f"✅ 成功导入: {imported_count} 条")
    print(f"⏭️  跳过: {skipped_count} 条")
    print(f"❌ 错误: {error_count} 条")
    print("="*50)

if __name__ == '__main__':
    print("🚀 开始导入 correct_steps 数据...")
    import_correct_steps()
    print("✅ 导入完成！")
