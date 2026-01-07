#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
合并英文备份和中文爬取数据
基于 enhanced_exercises_v2_crawler.py 的异步爬取能力
"""
import asyncio
import aiohttp
import json
import time
from pathlib import Path
from bs4 import BeautifulSoup
from typing import Dict, List, Optional
import logging
import re

# 配置
BACKUP_DIR = Path('F:/docs/exercises_v2_backup_1758197142')
OUTPUT_DIR = Path('storage/app/public/exercises_v2')
ZH_BASE_URL = 'https://musclewiki.com/zh-cn'

# 日志
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('merge_crawler.log', encoding='utf-8'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

class MergeDataCrawler:
    def __init__(self):
        self.session_config = {
            'timeout': aiohttp.ClientTimeout(total=30),
            'headers': {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept-Language': 'zh-CN,zh;q=0.9,en;q=0.8'
            }
        }
        
        self.stats = {
            'total': 0,
            'merged': 0,
            'zh_crawled': 0,
            'failed': 0,
            'garbled_removed': 0  # 删除的乱码数量
        }
        
        # 中文站URL映射缓存: {name_en: url}
        self.zh_url_cache = {}
        self.url_cache_loaded = False
    
    def load_english_backup(self, exercise_id: int) -> Optional[Dict]:
        """从英文备份加载数据"""
        for data_file in BACKUP_DIR.rglob('data.json'):
            try:
                with open(data_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    if data.get('id') == exercise_id:
                        return data
            except:
                pass
        return None
    
    def get_slug_from_name(self, name: str) -> str:
        """从动作名称生成URL slug"""
        slug = name.lower()
        # 替换常见字符
        slug = slug.replace(' ', '-')
        slug = slug.replace('(', '').replace(')', '')
        slug = slug.replace("'", '-')
        slug = slug.replace('&', 'and')
        slug = slug.replace('/', '-')
        # 移除多余的连字符
        slug = re.sub(r'-+', '-', slug)
        slug = slug.strip('-')
        return slug
    
    def is_garbled_text(self, text: str) -> bool:
        """
        检测文本是否为乱码
        乱码特征：包含大量非中文、非英文、非标点的异常字符
        """
        if not text:
            return False
        
        # 常见的UTF-8编码错误字符（这些字符通常表示编码问题）
        garbled_chars = ['å', 'æ', 'ç', 'é', 'è', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', '÷', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'þ', 'ÿ']
        
        # 检查是否包含乱码字符（只要包含就认为是乱码，即使只有少量）
        garbled_count = sum(1 for char in text if char in garbled_chars)
        if garbled_count > 0:
            return True  # 只要包含乱码字符就认为是乱码
        
        # 检查异常字符比例（非正常字符）
        # 正常字符：中文、英文、数字、常见标点
        normal_pattern = r'[\u4e00-\u9fff\w\s\.,;:!?()\[\]{}"\'-/]'
        normal_chars = re.findall(normal_pattern, text)
        abnormal_ratio = 1 - (len(normal_chars) / len(text)) if text else 0
        
        # 如果异常字符超过30%，可能是乱码
        if abnormal_ratio > 0.3:
            return True
        
        # 检查中英文混合但结构异常的情况
        # 如果包含中文，但异常字符太多，可能是乱码
        chinese_chars = re.findall(r'[\u4e00-\u9fff]', text)
        if chinese_chars:
            # 有中文但异常字符比例过高
            if abnormal_ratio > 0.2:
                return True
        
        return False
    
    def is_garbled_steps(self, steps: List[str]) -> bool:
        """检测步骤列表是否包含乱码"""
        if not steps:
            return False
        
        # 检查每个步骤
        garbled_count = 0
        for step in steps:
            if self.is_garbled_text(step):
                garbled_count += 1
        
        # 如果超过一半的步骤是乱码，认为是乱码数据
        return garbled_count > len(steps) / 2
    
    def extract_nextjs_data(self, html: str) -> Dict:
        """从Next.js HTML中提取JSON数据"""
        try:
            # Next.js通常在script标签中存储数据
            # 查找包含exercises数据的script标签
            soup = BeautifulSoup(html, 'html.parser')
            scripts = soup.find_all('script')
            
            for script in scripts:
                if not script.string:
                    continue
                
                text = script.string
                # 查找包含exercises数组的数据
                if 'exercises' in text and 'id' in text:
                    # 尝试提取JSON数据
                    # Next.js格式通常是: self.__next_f.push([1,"...json..."])
                    json_match = re.search(r'"exercises":\s*\[(.*?)\]', text, re.DOTALL)
                    if json_match:
                        try:
                            # 尝试解析完整的JSON
                            # 可能需要提取更大的JSON块
                            exercises_match = re.search(r'\{"exercises":\s*\[(.*?)\]\}', text, re.DOTALL)
                            if exercises_match:
                                json_str = '{"exercises":[' + exercises_match.group(1) + ']}'
                                data = json.loads(json_str)
                                return data
                        except:
                            pass
            
            return {}
        except Exception as e:
            logger.debug(f"提取Next.js数据失败: {e}")
            return {}
    
    async def load_zh_directory_map(self, session: aiohttp.ClientSession) -> Dict[str, str]:
        """
        从中文站目录页爬取所有动作URL映射
        由于是Next.js应用，可能需要直接使用API或解析script数据
        目前先跳过，直接在爬取时构建URL
        """
        if self.url_cache_loaded:
            return self.zh_url_cache
        
        # 暂时跳过目录页解析，因为Next.js渲染复杂
        # 直接在爬取时尝试URL构建
        logger.info("📋 跳过目录页解析（Next.js动态渲染），将使用URL构建方式")
        self.url_cache_loaded = True
        return self.zh_url_cache
    
    async def crawl_zh_from_musclewiki(self, session: aiohttp.ClientSession, slug: str, exercise_id: int) -> Optional[Dict]:
        """
        从 MuscleWiki 中文站爬取数据
        优先使用目录页映射的URL，否则尝试构建
        """
        try:
            # 确保已加载URL映射
            if not self.url_cache_loaded:
                await self.load_zh_directory_map(session)
            
            # 使用slug构建URL（slug已经在调用时准备好）
            url = f'{ZH_BASE_URL}/exercises/{slug}'
            
            logger.info(f"  爬取中文: {url}")
            
            html = None
            
            # 第一次尝试
            async with session.get(url) as response:
                if response.status == 200:
                    html = await response.text()
                elif response.status == 404:
                    # 尝试其他可能的slug格式（移除常见后缀或前缀）
                    alt_slug = slug.replace('-exercise', '').replace('-workout', '')
                    if alt_slug != slug:
                        alt_url = f'{ZH_BASE_URL}/exercises/{alt_slug}'
                        logger.info(f"  尝试备用URL: {alt_url}")
                        async with session.get(alt_url) as alt_response:
                            if alt_response.status == 200:
                                html = await alt_response.text()
                            else:
                                logger.warning(f"  ⚠️ HTTP {alt_response.status} (备用URL也失败)")
                                return None
                    else:
                        logger.warning(f"  ⚠️ HTTP 404")
                        return None
                else:
                    logger.warning(f"  ⚠️ HTTP {response.status}")
                    return None
            
            # 如果没有获取到HTML，返回None
            if not html:
                return None
            
            soup = BeautifulSoup(html, 'html.parser')
            
            # Next.js应用，需要从script标签中提取数据
            name_zh = None
            description_zh = ""
            correct_steps_zh = []
            
            # 方法1: 尝试从script标签提取JSON数据
            scripts = soup.find_all('script')
            exercise_data = None
            
            for script in scripts:
                if not script.string:
                    continue
                
                text = script.string
                
                # 查找包含exercise数据的script
                # Next.js格式可能是: self.__next_f.push([1,"...json..."])
                # 或者直接包含exercise对象的JSON
                if 'name_zh' in text or 'description_zh' in text:
                    # 尝试提取exercise对象
                    # 查找类似 {"name_zh":"...", "description_zh":"..."} 的结构
                    exercise_match = re.search(r'\{"name_zh"[^}]*"description_zh"[^}]*\}', text, re.DOTALL)
                    if exercise_match:
                        try:
                            json_str = exercise_match.group(0)
                            exercise_data = json.loads(json_str)
                            break
                        except:
                            pass
                    
                    # 另一种格式：可能在更大的JSON结构中
                    json_match = re.search(r'\{"exercises":\s*\[.*?"id":\s*' + str(exercise_id) + r'.*?\}', text, re.DOTALL)
                    if json_match:
                        try:
                            # 尝试解析包含该exercise的JSON块
                            # 这可能需要更复杂的解析
                            pass
                        except:
                            pass
            
            # 如果从script提取到了数据，使用它
            if exercise_data:
                name_zh = exercise_data.get('name_zh')
                description_zh = exercise_data.get('description_zh', '')
                # correct_steps_zh 可能需要单独提取
            
            # 方法2: 如果script提取失败，尝试从页面文本提取（如果已渲染）
            if not exercise_data or not (name_zh or description_zh):
                # 尝试查找h1标题
                h1 = soup.find('h1')
                if h1:
                    name_zh = h1.get_text().strip()
                
                # 尝试查找描述内容
                # Next.js可能将内容放在特定div中
                content_selectors = [
                    'div[class*="description"]',
                    'div[class*="content"]',
                    'div[class*="exercise"]',
                    'article',
                    'main'
                ]
                
                for selector in content_selectors:
                    elements = soup.select(selector)
                    for elem in elements:
                        text_content = elem.get_text()
                        # 检查是否包含中文
                        if any('\u4e00' <= char <= '\u9fff' for char in text_content):
                            # 提取段落
                            paragraphs = elem.find_all('p')
                            desc_parts = []
                            for p in paragraphs:
                                p_text = p.get_text().strip()
                                if p_text and len(p_text) > 10:
                                    # 检查是否包含中文
                                    if any('\u4e00' <= char <= '\u9fff' for char in p_text):
                                        desc_parts.append(f'<p>{p_text}</p>')
                            if desc_parts:
                                description_zh = '\n'.join(desc_parts)
                                break
                    if description_zh:
                        break
            
            # 如果还是没找到，尝试从整个页面文本提取中文段落
            if not description_zh:
                all_text = soup.get_text()
                lines = all_text.split('\n')
                desc_parts = []
                for line in lines:
                    line = line.strip()
                    # 检查是否包含中文且足够长
                    if line and len(line) > 20 and any('\u4e00' <= char <= '\u9fff' for char in line):
                        # 跳过步骤行
                        if not re.match(r'^\d+[\.\。]', line):
                            desc_parts.append(f'<p>{line}</p>')
                description_zh = '\n'.join(desc_parts[:5])  # 最多5段
            
            # 提取正确步骤
            page_text = soup.get_text()
            if '正确步骤' in page_text or 'Correct Steps' in page_text:
                lines = page_text.split('\n')
                in_steps = False
                
                for line in lines:
                    line = line.strip()
                    
                    if '正确步骤' in line or 'Correct Steps' in line:
                        in_steps = True
                        continue
                    
                    if in_steps:
                        # 匹配编号步骤
                        match = re.match(r'^[\d\s\.。]+(.+)', line)
                        if match:
                            step_text = match.group(1).strip()
                            if len(step_text) >= 5 and any('\u4e00' <= char <= '\u9fff' for char in step_text):
                                correct_steps_zh.append(step_text)
                        elif line and len(correct_steps_zh) > 0:
                            # 遇到非步骤内容
                            if not line[0].isdigit() and '正确' not in line:
                                break
            
            # 如果提取到任何中文数据，返回
            if name_zh or description_zh or (correct_steps_zh and len(correct_steps_zh) >= 2):
                return {
                    'name_zh': name_zh or slug.replace('-', ' ').title(),  # 如果没有提取到，用slug生成默认名称
                    'description_zh': description_zh,
                    'correct_steps_zh': correct_steps_zh if len(correct_steps_zh) >= 2 else None
                }
            
            # 如果什么都没找到，返回None
            logger.warning(f"  ⚠️ 未找到中文数据")
            return None
        
        except Exception as e:
            logger.error(f"  ❌ 爬取失败: {e}")
            return None
    
    async def merge_single_exercise(self, session: aiohttp.ClientSession, output_file: Path) -> bool:
        """合并单个动作的英文和中文数据"""
        try:
            # 读取当前文件
            with open(output_file, 'r', encoding='utf-8') as f:
                current_data = json.load(f)
            
            exercise_id = current_data.get('id')
            
            # 加载英文备份
            english_backup = self.load_english_backup(exercise_id)
            
            if not english_backup:
                logger.warning(f"  ⚠️ 未找到英文备份")
                return False
            
            # 使用英文备份的纯英文数据
            name_en = english_backup.get('name', 'Unknown')
            # 获取slug（优先从备份中获取，否则从name生成）
            slug = english_backup.get('slug') or self.get_slug_from_name(name_en)
            
            # 优先使用英文备份中的中文数据（最可靠）
            backup_name_zh = english_backup.get('name_zh')
            backup_description_zh = english_backup.get('description_zh', '')
            backup_correct_steps_zh = english_backup.get('correct_steps_zh')
            
            # 更新英文数据
            current_data['name'] = name_en
            current_data['description'] = english_backup.get('description', '')
            current_data['correct_steps'] = english_backup.get('correct_steps', [])
            
            # 优先使用备份中的中文数据
            if backup_name_zh:
                current_data['name_zh'] = backup_name_zh
            else:
                # 如果备份中没有，尝试爬取
                zh_data = await self.crawl_zh_from_musclewiki(session, slug, exercise_id)
                if zh_data and zh_data.get('name_zh'):
                    current_data['name_zh'] = zh_data['name_zh']
                    self.stats['zh_crawled'] += 1
                else:
                    # 爬取也失败，保持原有（如果存在）或使用英文名
                    if 'name_zh' not in current_data or not current_data.get('name_zh'):
                        current_data['name_zh'] = name_en
            
            # 处理description_zh
            if backup_description_zh:
                current_data['description_zh'] = backup_description_zh
            else:
                # 如果备份中没有，尝试爬取
                if 'zh_data' not in locals():
                    zh_data = await self.crawl_zh_from_musclewiki(session, slug, exercise_id)
                if zh_data and zh_data.get('description_zh'):
                    current_data['description_zh'] = zh_data['description_zh']
                    if 'zh_crawled' not in locals() or not zh_data.get('name_zh'):
                        self.stats['zh_crawled'] += 1
                else:
                    # 爬取失败，清空或保持原有
                    if 'description_zh' not in current_data:
                        current_data['description_zh'] = ""
            
            # 处理correct_steps_zh（需要检测乱码）
            if backup_correct_steps_zh and not self.is_garbled_steps(backup_correct_steps_zh):
                # 备份中有且不是乱码，使用备份的
                current_data['correct_steps_zh'] = backup_correct_steps_zh
            else:
                # 备份中没有或是乱码，尝试爬取
                if 'zh_data' not in locals():
                    zh_data = await self.crawl_zh_from_musclewiki(session, slug, exercise_id)
                
                if zh_data and zh_data.get('correct_steps_zh') and not self.is_garbled_steps(zh_data['correct_steps_zh']):
                    # 爬取成功且不是乱码
                    current_data['correct_steps_zh'] = zh_data['correct_steps_zh']
                    if 'zh_crawled' not in locals():
                        self.stats['zh_crawled'] += 1
                else:
                    # 如果当前有correct_steps_zh，检查是否是乱码
                    if 'correct_steps_zh' in current_data:
                        if self.is_garbled_steps(current_data['correct_steps_zh']):
                            # 当前数据是乱码，删除
                            logger.info(f"  ⚠️ 检测到乱码correct_steps_zh，已删除")
                            del current_data['correct_steps_zh']
                            self.stats['garbled_removed'] += 1
                        # 如果不是乱码，保留现有数据
            
            # 写回文件
            with open(output_file, 'w', encoding='utf-8') as f:
                json.dump(current_data, f, ensure_ascii=False, indent=2)
            
            self.stats['merged'] += 1
            return True
        
        except Exception as e:
            logger.error(f"  ❌ 合并失败: {e}")
            self.stats['failed'] += 1
            return False
    
    async def run_merge(self, max_exercises: int = None):
        """运行合并流程"""
        logger.info("=" * 70)
        logger.info("开始合并英文备份和中文爬取数据")
        logger.info("=" * 70)
        
        # 收集所有输出文件
        output_files = list(OUTPUT_DIR.rglob('data.json'))
        self.stats['total'] = len(output_files)
        
        logger.info(f"\n总共 {len(output_files)} 个动作需要合并\n")
        
        if max_exercises:
            output_files = output_files[:max_exercises]
            logger.info(f"测试模式：只处理前 {max_exercises} 个\n")
        
        async with aiohttp.ClientSession(**self.session_config) as session:
            # 预先加载中文站URL映射
            await self.load_zh_directory_map(session)
            
            for i, output_file in enumerate(output_files, 1):
                try:
                    with open(output_file, 'r', encoding='utf-8') as f:
                        data = json.load(f)
                    
                    exercise_id = data.get('id')
                    name = data.get('name', data.get('name_zh', 'Unknown'))
                    
                    logger.info(f"[{i}/{len(output_files)}] ID={exercise_id} {name}")
                    
                    await self.merge_single_exercise(session, output_file)
                    
                    # 延迟避免被封
                    await asyncio.sleep(1.5)
                    
                    if i % 10 == 0:
                        logger.info(f"\n进度: {i}/{len(output_files)} ({i/len(output_files)*100:.1f}%)")
                        logger.info(f"统计: 合并={self.stats['merged']}, 爬取={self.stats['zh_crawled']}, 失败={self.stats['failed']}\n")
                
                except Exception as e:
                    logger.error(f"处理文件失败 {output_file}: {e}")
        
        # 输出最终统计
        logger.info("\n" + "=" * 70)
        logger.info("合并完成!")
        logger.info("=" * 70)
        logger.info(f"总数: {self.stats['total']}")
        logger.info(f"成功合并: {self.stats['merged']}")
        logger.info(f"爬取中文: {self.stats['zh_crawled']}")
        logger.info(f"删除乱码: {self.stats['garbled_removed']}")
        logger.info(f"失败: {self.stats['failed']}")

async def main():
    """主函数"""
    import sys
    
    crawler = MergeDataCrawler()
    
    # 检查参数
    if len(sys.argv) > 1:
        if sys.argv[1] == 'test':
            # 测试模式：只处理前5个
            test_count = int(sys.argv[2]) if len(sys.argv) > 2 else 5
            logger.info(f"🧪 测试模式：处理前 {test_count} 个")
            await crawler.run_merge(max_exercises=test_count)
        elif sys.argv[1] == 'full':
            # 全量模式
            logger.info("🌍 全量模式：处理所有动作")
            await crawler.run_merge()
    else:
        # 默认测试模式
        logger.info("🧪 默认测试模式（前5个）")
        logger.info("使用方法:")
        logger.info("  python merge_en_zh_data.py test 10  # 测试前10个")
        logger.info("  python merge_en_zh_data.py full     # 全量处理")
        await crawler.run_merge(max_exercises=5)

if __name__ == '__main__':
    asyncio.run(main())

