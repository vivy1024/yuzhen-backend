<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

/**
 * 模块生成命令
 * 
 * 自动生成模块的完整目录结构和基础文件
 * 
 * @usage php artisan make:module {ModuleName}
 * @example php artisan make:module Product
 * 
 * @version 1.0.0
 * @date 2025-11-01
 */
class MakeModuleCommand extends Command
{
    /**
     * 命令签名
     */
    protected $signature = 'make:module {name : 模块名称（如：Product）}
                            {--force : 强制覆盖已存在的文件}';

    /**
     * 命令描述
     */
    protected $description = '创建一个新的模块，包含完整的目录结构和基础文件';

    /**
     * 模块名称
     */
    protected string $moduleName;

    /**
     * 模块路径
     */
    protected string $modulePath;

    /**
     * 执行命令
     */
    public function handle()
    {
        $this->moduleName = $this->argument('name');
        $this->modulePath = app_path("Modules/{$this->moduleName}");

        $this->info("开始创建模块: {$this->moduleName}");

        // 检查模块是否已存在
        if (File::exists($this->modulePath) && !$this->option('force')) {
            $this->error("模块 {$this->moduleName} 已存在！使用 --force 选项强制覆盖。");
            return 1;
        }

        try {
            // 创建目录结构
            $this->createDirectoryStructure();

            // 创建基础文件
            $this->createModel();
            $this->createRepository();
            $this->createRepositoryInterface();
            $this->createService();
            $this->createController();
            $this->createRequest();
            $this->createResource();
            $this->createEvent();
            $this->createServiceProvider();
            $this->createRouteFile();

            $this->info("✅ 模块 {$this->moduleName} 创建成功！");
            $this->info("📂 位置: {$this->modulePath}");
            $this->displayNextSteps();

            return 0;
        } catch (\Exception $e) {
            $this->error("创建模块失败: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * 创建目录结构
     */
    protected function createDirectoryStructure(): void
    {
        $directories = [
            'Controllers',
            'Services',
            'Repositories',
            'Repositories/Interfaces',
            'Models',
            'Requests',
            'Resources',
            'Events',
            'Middleware',
            'Providers',
        ];

        foreach ($directories as $dir) {
            $path = "{$this->modulePath}/{$dir}";
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
                $this->line("✓ 创建目录: {$dir}");
            }
        }
    }

    /**
     * 创建Model
     */
    protected function createModel(): void
    {
        $stub = $this->getStub('model');
        $content = $this->replaceVariables($stub);
        
        $path = "{$this->modulePath}/Models/{$this->moduleName}.php";
        File::put($path, $content);
        $this->line("✓ 创建Model: {$this->moduleName}.php");
    }

    /**
     * 创建Repository接口
     */
    protected function createRepositoryInterface(): void
    {
        $stub = $this->getStub('repository-interface');
        $content = $this->replaceVariables($stub);
        
        $path = "{$this->modulePath}/Repositories/Interfaces/{$this->moduleName}RepositoryInterface.php";
        File::put($path, $content);
        $this->line("✓ 创建Repository接口");
    }

    /**
     * 创建Repository
     */
    protected function createRepository(): void
    {
        $stub = $this->getStub('repository');
        $content = $this->replaceVariables($stub);
        
        $path = "{$this->modulePath}/Repositories/{$this->moduleName}Repository.php";
        File::put($path, $content);
        $this->line("✓ 创建Repository");
    }

    /**
     * 创建Service
     */
    protected function createService(): void
    {
        $stub = $this->getStub('service');
        $content = $this->replaceVariables($stub);
        
        $path = "{$this->modulePath}/Services/{$this->moduleName}Service.php";
        File::put($path, $content);
        $this->line("✓ 创建Service");
    }

    /**
     * 创建Controller
     */
    protected function createController(): void
    {
        $stub = $this->getStub('controller');
        $content = $this->replaceVariables($stub);
        
        $path = "{$this->modulePath}/Controllers/{$this->moduleName}Controller.php";
        File::put($path, $content);
        $this->line("✓ 创建Controller");
    }

    /**
     * 创建Request
     */
    protected function createRequest(): void
    {
        $storeStub = $this->getStub('store-request');
        $storeContent = $this->replaceVariables($storeStub);
        
        $storePath = "{$this->modulePath}/Requests/Store{$this->moduleName}Request.php";
        File::put($storePath, $storeContent);
        $this->line("✓ 创建StoreRequest");

        $updateStub = $this->getStub('update-request');
        $updateContent = $this->replaceVariables($updateStub);
        
        $updatePath = "{$this->modulePath}/Requests/Update{$this->moduleName}Request.php";
        File::put($updatePath, $updateContent);
        $this->line("✓ 创建UpdateRequest");
    }

    /**
     * 创建Resource
     */
    protected function createResource(): void
    {
        $stub = $this->getStub('resource');
        $content = $this->replaceVariables($stub);
        
        $path = "{$this->modulePath}/Resources/{$this->moduleName}Resource.php";
        File::put($path, $content);
        $this->line("✓ 创建Resource");
    }

    /**
     * 创建Event
     */
    protected function createEvent(): void
    {
        $stub = $this->getStub('event');
        $content = $this->replaceVariables($stub);
        
        $path = "{$this->modulePath}/Events/{$this->moduleName}Created.php";
        File::put($path, $content);
        $this->line("✓ 创建Event");
    }

    /**
     * 创建ServiceProvider
     */
    protected function createServiceProvider(): void
    {
        $stub = $this->getStub('service-provider');
        $content = $this->replaceVariables($stub);
        
        $path = "{$this->modulePath}/Providers/{$this->moduleName}ServiceProvider.php";
        File::put($path, $content);
        $this->line("✓ 创建ServiceProvider");
    }

    /**
     * 创建路由文件
     */
    protected function createRouteFile(): void
    {
        $stub = $this->getStub('routes');
        $content = $this->replaceVariables($stub);
        
        $path = base_path("routes/modules/" . Str::kebab($this->moduleName) . ".php");
        
        // 确保routes/modules目录存在
        $dir = dirname($path);
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        
        File::put($path, $content);
        $this->line("✓ 创建路由文件");
    }

    /**
     * 获取模板内容
     */
    protected function getStub(string $type): string
    {
        $stubs = [
            'model' => <<<'EOT'
<?php

namespace App\Modules\{{ModuleName}}\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * {{ModuleName}} Model
 * 
 * @version 1.0.0
 * @date {{Date}}
 */
class {{ModuleName}} extends Model
{
    protected $table = '{{table_name}}';

    protected $fillable = [
        // TODO: 添加可填充字段
    ];

    protected $casts = [
        // TODO: 添加类型转换
    ];
}
EOT,
            'repository-interface' => <<<'EOT'
<?php

namespace App\Modules\{{ModuleName}}\Repositories\Interfaces;

/**
 * {{ModuleName}} Repository Interface
 */
interface {{ModuleName}}RepositoryInterface
{
    public function findById(int $id): ?array;
    
    public function paginate(array $filters, int $page, int $perPage): array;
    
    public function create(array $data): array;
    
    public function update(int $id, array $data): array;
    
    public function delete(int $id): bool;
}
EOT,
            'repository' => <<<'EOT'
<?php

namespace App\Modules\{{ModuleName}}\Repositories;

use App\Modules\{{ModuleName}}\Repositories\Interfaces\{{ModuleName}}RepositoryInterface;
use App\Modules\{{ModuleName}}\Models\{{ModuleName}};
use App\Infrastructure\Database\Repositories\BaseRepository;

/**
 * {{ModuleName}} Repository
 * 
 * @version 1.0.0
 * @date {{Date}}
 */
class {{ModuleName}}Repository extends BaseRepository implements {{ModuleName}}RepositoryInterface
{
    protected $model = {{ModuleName}}::class;

    public function findById(int $id): ?array
    {
        $result = {{ModuleName}}::find($id);
        return $result ? $result->toArray() : null;
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $query = {{ModuleName}}::query();
        
        // TODO: 应用筛选条件
        
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        
        return [
            'rows' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    public function create(array $data): array
    {
        $model = {{ModuleName}}::create($data);
        return $model->toArray();
    }

    public function update(int $id, array $data): array
    {
        $model = {{ModuleName}}::findOrFail($id);
        $model->update($data);
        return $model->fresh()->toArray();
    }

    public function delete(int $id): bool
    {
        return {{ModuleName}}::destroy($id) > 0;
    }
}
EOT,
            'service' => <<<'EOT'
<?php

namespace App\Modules\{{ModuleName}}\Services;

use App\Modules\{{ModuleName}}\Repositories\Interfaces\{{ModuleName}}RepositoryInterface;

/**
 * {{ModuleName}} Service
 * 
 * @version 1.0.0
 * @date {{Date}}
 */
class {{ModuleName}}Service
{
    protected {{ModuleName}}RepositoryInterface $repository;
    
    public function __construct({{ModuleName}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getList(array $filters, int $page = 1, int $perPage = 20): array
    {
        return $this->repository->paginate($filters, $page, $perPage);
    }

    public function getDetail(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): array
    {
        // TODO: 添加业务逻辑
        return $this->repository->create($data);
    }

    public function update(int $id, array $data): array
    {
        // TODO: 添加业务逻辑
        return $this->repository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
EOT,
            'controller' => <<<'EOT'
<?php

namespace App\Modules\{{ModuleName}}\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\{{ModuleName}}\Services\{{ModuleName}}Service;
use App\Modules\{{ModuleName}}\Requests\Store{{ModuleName}}Request;
use App\Modules\{{ModuleName}}\Requests\Update{{ModuleName}}Request;
use App\Modules\{{ModuleName}}\Resources\{{ModuleName}}Resource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * {{ModuleName}} Controller
 * 
 * @version 1.0.0
 * @date {{Date}}
 */
class {{ModuleName}}Controller extends BaseController
{
    protected {{ModuleName}}Service $service;
    
    public function __construct({{ModuleName}}Service $service)
    {
        $this->service = $service;
    }

    /**
     * 获取列表
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->getList(
            $request->all(),
            $request->get('page', 1),
            $request->get('per_page', 20)
        );
        
        return $this->success($data, '获取成功');
    }

    /**
     * 获取详情
     */
    public function show(int $id): JsonResponse
    {
        $data = $this->service->getDetail($id);
        
        if (!$data) {
            return $this->fail('资源不存在', 404);
        }
        
        return $this->success($data, '获取成功');
    }

    /**
     * 创建资源
     */
    public function store(Store{{ModuleName}}Request $request): JsonResponse
    {
        $data = $this->service->create($request->validated());
        return $this->success($data, '创建成功', 201);
    }

    /**
     * 更新资源
     */
    public function update(int $id, Update{{ModuleName}}Request $request): JsonResponse
    {
        $data = $this->service->update($id, $request->validated());
        return $this->success($data, '更新成功');
    }

    /**
     * 删除资源
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return $this->success(null, '删除成功');
    }
}
EOT,
            'store-request' => <<<'EOT'
<?php

namespace App\Modules\{{ModuleName}}\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Store{{ModuleName}}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // TODO: 添加验证规则
        ];
    }

    public function messages(): array
    {
        return [
            // TODO: 添加错误消息
        ];
    }
}
EOT,
            'update-request' => <<<'EOT'
<?php

namespace App\Modules\{{ModuleName}}\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Update{{ModuleName}}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // TODO: 添加验证规则
        ];
    }

    public function messages(): array
    {
        return [
            // TODO: 添加错误消息
        ];
    }
}
EOT,
            'resource' => <<<'EOT'
<?php

namespace App\Modules\{{ModuleName}}\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class {{ModuleName}}Resource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this['id'],
            // TODO: 添加其他字段
            'created_at' => $this['created_at'] ?? null,
            'updated_at' => $this['updated_at'] ?? null,
        ];
    }
}
EOT,
            'event' => <<<'EOT'
<?php

namespace App\Modules\{{ModuleName}}\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class {{ModuleName}}Created
{
    use Dispatchable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }
}
EOT,
            'service-provider' => <<<'EOT'
<?php

namespace App\Modules\{{ModuleName}}\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\{{ModuleName}}\Repositories\Interfaces\{{ModuleName}}RepositoryInterface;
use App\Modules\{{ModuleName}}\Repositories\{{ModuleName}}Repository;

class {{ModuleName}}ServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(
            {{ModuleName}}RepositoryInterface::class,
            {{ModuleName}}Repository::class
        );
    }

    public function boot()
    {
        //
    }
}
EOT,
            'routes' => <<<'EOT'
<?php

use Illuminate\Support\Facades\Route;
use App\Modules\{{ModuleName}}\Controllers\{{ModuleName}}Controller;

/**
 * {{ModuleName}} Routes
 * 
 * 前缀: /api/{{route_prefix}}
 */

Route::prefix('{{route_prefix}}')->group(function () {
    // 公开路由
    Route::get('/', [{{ModuleName}}Controller::class, 'index']);
    Route::get('/{id}', [{{ModuleName}}Controller::class, 'show']);
    
    // 需要认证的路由
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/', [{{ModuleName}}Controller::class, 'store']);
        Route::put('/{id}', [{{ModuleName}}Controller::class, 'update']);
        Route::delete('/{id}', [{{ModuleName}}Controller::class, 'destroy']);
    });
});
EOT,
        ];

        return $stubs[$type] ?? '';
    }

    /**
     * 替换模板变量
     */
    protected function replaceVariables(string $stub): string
    {
        $variables = [
            '{{ModuleName}}' => $this->moduleName,
            '{{table_name}}' => Str::snake(Str::pluralStudly($this->moduleName)),
            '{{route_prefix}}' => Str::kebab(Str::pluralStudly($this->moduleName)),
            '{{Date}}' => date('Y-m-d'),
        ];

        return str_replace(array_keys($variables), array_values($variables), $stub);
    }

    /**
     * 显示后续步骤
     */
    protected function displayNextSteps(): void
    {
        $moduleLower = Str::kebab($this->moduleName);
        
        $this->newLine();
        $this->info("📝 后续步骤:");
        $this->line("1. 注册ServiceProvider到 config/app.php:");
        $this->line("   App\Modules\\{$this->moduleName}\Providers\\{$this->moduleName}ServiceProvider::class");
        $this->newLine();
        $this->line("2. 在routes/api.php中引入路由:");
        $this->line("   require __DIR__.'/modules/{$moduleLower}.php';");
        $this->newLine();
        $this->line("3. 创建数据库迁移文件:");
        $this->line("   php artisan make:migration create_{$this->getTableName()}_table");
        $this->newLine();
        $this->line("4. 完善业务逻辑和验证规则");
        $this->newLine();
        $this->info("🎉 开始开发吧！");
    }

    /**
     * 获取表名
     */
    protected function getTableName(): string
    {
        return Str::snake(Str::pluralStudly($this->moduleName));
    }
}












