#!/usr/bin/env php
<?php

/**
 * Script to fix InternalChatController API response compliance
 * 
 * This script replaces all response()->json() calls with BaseController methods
 */

$file = __DIR__ . '/../app/Modules/Chat/Controllers/InternalChatController.php';
$content = file_get_contents($file);

// Pattern 1: Success responses with 200 code
$content = preg_replace(
    '/return response\(\)->json\(\[\s*\'code\' => 200,\s*\'msg\' => ([^,]+),\s*\'data\' => ([^\]]+)\s*\]\);/s',
    'return $this->success($2, $1);',
    $content
);

// Pattern 2: Error responses with 404 code
$content = preg_replace(
    '/return response\(\)->json\(\[\s*\'code\' => 404,\s*\'msg\' => ([^,]+),\s*\'data\' => null\s*\], 404\);/s',
    'return $this->fail($1, 404);',
    $content
);

// Pattern 3: Error responses with 422 code (validation)
$content = preg_replace(
    '/return response\(\)->json\(\[\s*\'code\' => 422,\s*\'msg\' => ([^,]+),\s*\'data\' => \[\s*\'errors\' => ([^\]]+)\s*\]\s*\], 422\);/s',
    'return $this->fail($1, 422, [\'errors\' => $2]);',
    $content
);

// Pattern 4: Error responses with 500 code
$content = preg_replace(
    '/return response\(\)->json\(\[\s*\'code\' => 500,\s*\'msg\' => ([^,]+),\s*\'data\' => null\s*\], 500\);/s',
    'return $this->fail($1, 500);',
    $content
);

// Replace catch blocks with handleException
$content = preg_replace(
    '/} catch \(\\\\Exception \$e\) \{\s*Log::error\([^;]+;\s*return response\(\)->json\(\[\s*\'code\' => 500,\s*\'msg\' => [^;]+;\s*\}/s',
    '} catch (\Exception $e) {
            return $this->handleException($e, \'操作\');
        }',
    $content
);

file_put_contents($file, $content);

echo "Fixed InternalChatController.php\n";
