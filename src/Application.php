<?php

namespace Mine;

use Monolog\Logger;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Composer;
use Monolog\Handler\StreamHandler;
use Illuminate\Container\Container;
use Monolog\Formatter\LineFormatter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use Zend\Diactoros\Response as PsrResponse;
use Illuminate\Config\Repository as ConfigRepository;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;

class Application extends Container
{
    //use Concerns\RoutesRequests,  //用简易路由器代替原有的
    //use Concerns\RegistersExceptionHandlers; //注册错误处理

    /**
     * 已注册的类别名
     * Indicates if the class aliases have been registered.
     *
     * @var bool
     */
    protected static $aliasesRegistered = false;

    /**
     * 网站项目基本路径
     * The base path of the application installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * 已加载的配置文件
     * All of the loaded configuration files.
     *
     * @var array
     */
    protected $loadedConfigurations = [];

    /**
     * 已加载的服务提供者
     * The loaded service providers.
     *
     * @var array
     */
    protected $loadedProviders = [];

    /**
     * 已运行的服务绑定方法
     * The service binding methods that have been executed.
     *
     * @var array
     */
    protected $ranServiceBinders = [];

    /**
     * 自定义日志回调
     * A custom callback used to configure Monolog.
     *
     * @var callable|null
     */
    protected $monologConfigurator;

    /**
     * 应用命名空间
     * The application namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * 创建应用容器实例
     * Create a new Lumen application instance.
     *
     * @param  string|null  $basePath
     * @return void
     */
    public function __construct($basePath = null)
    {
        //date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));        //时区设置

        $this->basePath = $basePath;

        $this->bootstrapContainer();

        //$this->registerErrorHandling();   //错误处理
    }

    /**
     * 引导应用容器
     * Bootstrap the application container.
     *
     * @return void
     */
    protected function bootstrapContainer()
    {
        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance('Mine\Application', $this);

        $this->instance('path', $this->path());

        //注册容器中的别名
        $this->registerContainerAliases();
    }

    /**
     * 应用环境
     * Get or check the current application environment.
     *
     * @param  mixed
     * @return string
     */
    public function environment()
    {
        $env = env('APP_ENV', 'production');

        if (func_num_args() > 0) {
            $patterns = is_array(func_get_arg(0)) ? func_get_arg(0) : func_get_args();

            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $env)) {
                    return true;
                }
            }

            return false;
        }

        return $env;
    }

    /**
     * 服务注册器
     * Register a service provider with the application.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     * @param  array  $options
     * @param  bool   $force
     * @return \Illuminate\Support\ServiceProvider
     */
    public function register($provider, $options = [], $force = false)
    {
        if (! $provider instanceof ServiceProvider) {
            $provider = new $provider($this);
        }

        //已注册则返回
        if (array_key_exists($providerName = get_class($provider), $this->loadedProviders)) {
            return;
        }
//echo '[ServiceProviderName] ------ ' . $providerName . '<br>';

        //标记已注册
        $this->loadedProviders[$providerName] = true;

        //注册
        $provider->register();

        //注册后
        $provider->boot();
    }

    /**
     * 延迟加载服务注册器
     * Register a deferred provider and service.
     *
     * @param  string  $provider
     * @param  string|null  $service
     * @return void
     */
    public function registerDeferredProvider($provider, $service = null)
    {
        return $this->register($provider);
    }

    /**
     * 服务容器解析 或 启动
     * Resolve the given type from the container.
     *
     * @param  string  $abstract
     * @param  array   $parameters
     * @return mixed
     */
    public function make($abstract, array $parameters = [])
    {
        //获取服务别名
        $abstract = $this->getAlias($this->normalize($abstract));

//echo '[ make ] ------ '. $abstract .'<br>';

        // <要解析的服务> 在 <可绑定的服务数组中>  &&  <还没有执行该绑定>
        if (array_key_exists($abstract, $this->availableBindings) &&
            ! array_key_exists($this->availableBindings[$abstract], $this->ranServiceBinders)) {

            // 执行绑定服务
            $this->{$method = $this->availableBindings[$abstract]}();

            // 登记已经绑定
            $this->ranServiceBinders[$method] = true;
        }

        // 返回解析到的已注册服务
        return parent::make($abstract, $parameters);
    }

    /**
     * 绑定认证
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerAuthBindings()
    {
        $this->singleton('auth', function () {
            return $this->loadComponent('auth', 'Illuminate\Auth\AuthServiceProvider', 'auth');
        });

        $this->singleton('auth.driver', function () {
            return $this->loadComponent('auth', 'Illuminate\Auth\AuthServiceProvider', 'auth.driver');
        });

        $this->singleton('Illuminate\Contracts\Auth\Access\Gate', function () {
            return $this->loadComponent('auth', 'Illuminate\Auth\AuthServiceProvider', 'Illuminate\Contracts\Auth\Access\Gate');
        });
    }

    /**
     * 绑定广播
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerBroadcastingBindings()
    {
        $this->singleton('Illuminate\Contracts\Broadcasting\Broadcaster', function () {
            $this->configure('broadcasting');

            $this->register('Illuminate\Broadcasting\BroadcastServiceProvider');

            return $this->make('Illuminate\Contracts\Broadcasting\Broadcaster');
        });
    }

    /**
     * 绑定BUS
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerBusBindings()
    {
        $this->singleton('Illuminate\Contracts\Bus\Dispatcher', function () {
            $this->register('Illuminate\Bus\BusServiceProvider');

            return $this->make('Illuminate\Contracts\Bus\Dispatcher');
        });
    }

    /**
     * 绑定缓存
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerCacheBindings()
    {
        $this->singleton('cache', function () {
            return $this->loadComponent('cache', 'Illuminate\Cache\CacheServiceProvider');
        });
        $this->singleton('cache.store', function () {
            return $this->loadComponent('cache', 'Illuminate\Cache\CacheServiceProvider', 'cache.store');
        });
    }

    /**
     * 绑定依赖
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerComposerBindings()
    {
        $this->singleton('composer', function ($app) {
            return new Composer($app->make('files'), $this->basePath());
        });
    }

    /**
     * 绑定配置
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerConfigBindings()
    {
        $this->singleton('config', function () {
            return new ConfigRepository;
        });
    }

    /**
     * 绑定数据库
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerDatabaseBindings()
    {
        $this->singleton('db', function () {
            return $this->loadComponent(
                'database', [
                    'Illuminate\Database\DatabaseServiceProvider',
                    'Illuminate\Pagination\PaginationServiceProvider',
                ], 'db'
            );
        });
    }

    /**
     * 绑定加密
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerEncrypterBindings()
    {
        $this->singleton('encrypter', function () {
            return $this->loadComponent('app', 'Illuminate\Encryption\EncryptionServiceProvider', 'encrypter');
        });
    }

    /**
     * 绑定事件
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerEventBindings()
    {
        $this->singleton('events', function () {
            $this->register('Illuminate\Events\EventServiceProvider');

            return $this->make('events');
        });
    }

    /**
     * 绑定文件系统
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerFilesBindings()
    {
        $this->singleton('files', function () {
            return new Filesystem;
        });
    }

    /**
     * 绑定哈希
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerHashBindings()
    {
        $this->singleton('hash', function () {
            $this->register('Illuminate\Hashing\HashServiceProvider');

            return $this->make('hash');
        });
    }

    /**
     * 绑定日志
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerLogBindings()
    {
        $this->singleton('Psr\Log\LoggerInterface', function () {
            if ($this->monologConfigurator) {
                return call_user_func($this->monologConfigurator, new Logger('lumen'));
            } else {
                return new Logger('lumen', [$this->getMonologHandler()]);
            }
        });
    }

    /**
     * 绑定Monolog
     * Define a callback to be used to configure Monolog.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function configureMonologUsing(callable $callback)
    {
        $this->monologConfigurator = $callback;

        return $this;
    }

    /**
     * 绑定队列
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerQueueBindings()
    {
        $this->singleton('queue', function () {
            return $this->loadComponent('queue', 'Illuminate\Queue\QueueServiceProvider', 'queue');
        });
        $this->singleton('queue.connection', function () {
            return $this->loadComponent('queue', 'Illuminate\Queue\QueueServiceProvider', 'queue.connection');
        });
    }

    /**
     * Get the Monolog handler for the application.
     *
     * @return \Monolog\Handler\AbstractHandler
     */
    protected function getMonologHandler()
    {
        return (new StreamHandler(storage_path('logs/lumen.log'), Logger::DEBUG))
                            ->setFormatter(new LineFormatter(null, null, true, true));
    }

    /**
     * 绑定请求
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerRequestBindings()
    {
        $this->singleton('Illuminate\Http\Request', function () {
            return $this->prepareRequest(Request::capture());
        });
    }

    /**
     * 解析请求
     * Prepare the given request instance for use with the application.
     *
     * @param   \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Request
     */
    protected function prepareRequest(Request $request)
    {
        $request->setUserResolver(function () {
            return $this->make('auth')->user();
        })->setRouteResolver(function () {
            return $this->currentRoute;
        });

        return $request;
    }

    /**
     * Register container bindings for the PSR-7 request implementation.
     *
     * @return void
     */
    protected function registerPsrRequestBindings()
    {
        $this->singleton('Psr\Http\Message\ServerRequestInterface', function () {
            return (new DiactorosFactory)->createRequest($this->make('request'));
        });
    }

    /**
     * Register container bindings for the PSR-7 response implementation.
     *
     * @return void
     */
    protected function registerPsrResponseBindings()
    {
        $this->singleton('Psr\Http\Message\ResponseInterface', function () {
            return new PsrResponse();
        });
    }

    /**
     * 绑定本地化
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerTranslationBindings()
    {
        $this->singleton('translator', function () {
            $this->configure('app');

            $this->instance('path.lang', $this->getLanguagePath());

            $this->register('Illuminate\Translation\TranslationServiceProvider');

            return $this->make('translator');
        });
    }

    /**
     * 获取语言路径
     * Get the path to the application's language files.
     *
     * @return string
     */
    protected function getLanguagePath()
    {
        if (is_dir($langPath = $this->basePath().'/resources/lang')) {
            return $langPath;
        } else {
            return __DIR__.'/../resources/lang';
        }
    }

    /**
     * 绑定路由 url
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerUrlGeneratorBindings()
    {
        $this->singleton('url', function () {
            return new Routing\UrlGenerator($this);
        });
    }

    /**
     * 绑定验证
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerValidatorBindings()
    {
        $this->singleton('validator', function () {
            $this->register('Illuminate\Validation\ValidationServiceProvider');

            return $this->make('validator');
        });
    }

    /**
     * 绑定视图
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerViewBindings()
    {
        $this->singleton('view', function () {
            return $this->loadComponent('view', 'Illuminate\View\ViewServiceProvider');
        });
    }

    /**
     * 配置&&加载 服务
     * Configure and load the given component and provider.
     *
     * @param  string  $config
     * @param  array|string  $providers
     * @param  string|null  $return
     * @return mixed
     */
    public function loadComponent($config, $providers, $return = null)
    {
        $this->configure($config);

        foreach ((array) $providers as $provider) {
            //注册服务
            $this->register($provider);
        }

        return $this->make($return ?: $config);
    }

    /**
     * 加载配置（从项目/包）
     * Load a configuration file into the application.
     *
     * @param  string  $name
     * @return void
     */
    public function configure($name)
    {
        if (isset($this->loadedConfigurations[$name])) {
            return;
        }

        //标记已加载
        $this->loadedConfigurations[$name] = true;

        //获取配置文件
        $path = $this->getConfigurationPath($name);

        //解析 config 服务 并设置配置文件
        if ($path) {
//echo '[ config ] ------ ' . $path . '<br>';
            $this->make('config')->set($name, require $path); // ---> Illuminate\Config\Repository
        }
    }

    /**
     * 获取配置目录或文件（从项目/包）
     * Get the path to the given configuration file.
     *
     * If no name is provided, then we'll return the path to the config folder.
     *
     * @param  string|null  $name
     * @return string
     */
    public function getConfigurationPath($name = null)
    {
        //无文件名
        if (! $name) {
            $appConfigDir = $this->basePath('config').'/';
            if (file_exists($appConfigDir)) {
                return $appConfigDir;   //项目配置目录
            } elseif (file_exists($path = __DIR__.'/../config/')) {
                return $path;   //包配置目录
            }
        } else {

            $appConfigPath = $this->basePath('config').'/'.$name.'.php';

            if (file_exists($appConfigPath)) {
                return $appConfigPath; //项目配置文件
            } elseif (file_exists($path = __DIR__.'/../config/'.$name.'.php')) {
                return $path;   //包配置文件
            }
        }
    }

    /**
     * 注册门面(别名)
     * Register the facades for the application.
     *
     * @return void
     */
    public function withFacades()
    {
        Facade::setFacadeApplication($this);

        if (! static::$aliasesRegistered) {
            static::$aliasesRegistered = true;

            class_alias('Illuminate\Support\Facades\DB', 'MineDB');
            class_alias('Illuminate\Support\Facades\Cache', 'Cache');
            class_alias('Illuminate\Support\Facades\Validator', 'Validator');
            class_alias('Illuminate\Support\Facades\Auth', 'Auth');
            class_alias('Illuminate\Support\Facades\Event', 'Event');
            class_alias('Illuminate\Support\Facades\Gate', 'Gate');
            class_alias('Illuminate\Support\Facades\Log', 'Log');
            class_alias('Illuminate\Support\Facades\Queue', 'Queue');
            class_alias('Illuminate\Support\Facades\Schema', 'Schema');
            class_alias('Illuminate\Support\Facades\URL', 'URL');

        }
    }

    /**
     * 使用 ORM
     * Load the Eloquent library for the application.
     *
     * @return void
     */
    public function withEloquent()
    {
        $this->make('db');
    }

    /**
     * App 目录
     * Get the path to the application "app" directory.
     *
     * @return string
     */
    public function path()
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'app';
    }

    /**
     * 基本目录 （项目根目录）
     * Get the base path for the application.
     *
     * @param  string|null  $path
     * @return string
     */
    public function basePath($path = null)
    {
        if (isset($this->basePath)) {
            return $this->basePath.($path ? '/'.$path : $path);
        }

        if ($this->runningInConsole()) {
            $this->basePath = getcwd();
        } else {
            $this->basePath = realpath(getcwd().'/../');
        }

        return $this->basePath($path);
    }

    /**
     * 数据库目录
     * Get the database path for the application.
     *
     * @return string
     */
    public function databasePath()
    {
        return $this->basePath().'/database';
    }

    /**
     * 缓存目录
     * Get the storage path for the application.
     *
     * @param  string|null  $path
     * @return string
     */
    public function storagePath($path = null)
    {
        return $this->basePath().'/storage'.($path ? '/'.$path : $path);
    }

    /**
     * 命令行模式
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole()
    {
        return php_sapi_name() == 'cli';
    }

    /**
     * 测试
     * Determine if we are running unit tests.
     *
     * @return bool
     */
    public function runningUnitTests()
    {
        return $this->environment() == 'testing';
    }

    /**
     * 初始化命令行模式
     * Prepare the application to execute a console command.
     *
     * @return void
     */
    public function prepareForConsoleCommand()
    {
        $this->withFacades();

        $this->make('cache');
        $this->make('queue');

        $this->configure('database');

        $this->register('Illuminate\Database\MigrationServiceProvider');
        $this->register('Illuminate\Database\SeedServiceProvider');
        $this->register('Illuminate\Queue\ConsoleServiceProvider');
    }

    /**
     * 绑定别名
     * Register the core container aliases.
     *
     * @return void
     */
    protected function registerContainerAliases()
    {
        $this->aliases = [
            'Illuminate\Contracts\Foundation\Application' => 'app',
            'Illuminate\Contracts\Auth\Factory' => 'auth',
            'Illuminate\Contracts\Auth\Guard' => 'auth.driver',
            'Illuminate\Contracts\Cache\Factory' => 'cache',
            'Illuminate\Contracts\Cache\Repository' => 'cache.store',
            'Illuminate\Contracts\Config\Repository' => 'config',
            'Illuminate\Container\Container' => 'app',
            'Illuminate\Contracts\Container\Container' => 'app',
            'Illuminate\Database\ConnectionResolverInterface' => 'db',
            'Illuminate\Database\DatabaseManager' => 'db',
            'Illuminate\Contracts\Encryption\Encrypter' => 'encrypter',
            'Illuminate\Contracts\Events\Dispatcher' => 'events',
            'Illuminate\Contracts\Hashing\Hasher' => 'hash',
            'log' => 'Psr\Log\LoggerInterface',
            'Illuminate\Contracts\Queue\Factory' => 'queue',
            'Illuminate\Contracts\Queue\Queue' => 'queue.connection',
            'request' => 'Illuminate\Http\Request',
            'Mine\Routing\UrlGenerator' => 'url',
            'Illuminate\Contracts\Validation\Factory' => 'validator',
            'Illuminate\Contracts\View\Factory' => 'view',
        ];
    }

    /**
     * 可绑定服务
     * The available container bindings and their respective load methods.
     *
     * @var array
     */
    public $availableBindings = [
        'auth' => 'registerAuthBindings',
        'auth.driver' => 'registerAuthBindings',
        'Illuminate\Contracts\Auth\Guard' => 'registerAuthBindings',
        'Illuminate\Contracts\Auth\Access\Gate' => 'registerAuthBindings',
        'Illuminate\Contracts\Broadcasting\Broadcaster' => 'registerBroadcastingBindings',
        'Illuminate\Contracts\Bus\Dispatcher' => 'registerBusBindings',
        'cache' => 'registerCacheBindings',
        'cache.store' => 'registerCacheBindings',
        'Illuminate\Contracts\Cache\Factory' => 'registerCacheBindings',
        'Illuminate\Contracts\Cache\Repository' => 'registerCacheBindings',
        'composer' => 'registerComposerBindings',
        'config' => 'registerConfigBindings',
        'db' => 'registerDatabaseBindings',
        'Illuminate\Database\Eloquent\Factory' => 'registerDatabaseBindings',
        'encrypter' => 'registerEncrypterBindings',
        'Illuminate\Contracts\Encryption\Encrypter' => 'registerEncrypterBindings',
        'events' => 'registerEventBindings',
        'Illuminate\Contracts\Events\Dispatcher' => 'registerEventBindings',
        'files' => 'registerFilesBindings',
        'hash' => 'registerHashBindings',
        'Illuminate\Contracts\Hashing\Hasher' => 'registerHashBindings',
        'log' => 'registerLogBindings',
        'Psr\Log\LoggerInterface' => 'registerLogBindings',
        'queue' => 'registerQueueBindings',
        'queue.connection' => 'registerQueueBindings',
        'Illuminate\Contracts\Queue\Factory' => 'registerQueueBindings',
        'Illuminate\Contracts\Queue\Queue' => 'registerQueueBindings',
        'request' => 'registerRequestBindings',
        'Psr\Http\Message\ServerRequestInterface' => 'registerPsrRequestBindings',
        'Psr\Http\Message\ResponseInterface' => 'registerPsrResponseBindings',
        'Illuminate\Http\Request' => 'registerRequestBindings',
        'translator' => 'registerTranslationBindings',
        'url' => 'registerUrlGeneratorBindings',
        'validator' => 'registerValidatorBindings',
        'Illuminate\Contracts\Validation\Factory' => 'registerValidatorBindings',
        'view' => 'registerViewBindings',
        'Illuminate\Contracts\View\Factory' => 'registerViewBindings',
    ];
}
