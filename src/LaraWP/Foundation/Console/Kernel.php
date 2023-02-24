<?php

namespace LaraWP\Foundation\Console;

use Closure;
use LaraWP\Console\Application as Artisan;
use LaraWP\Console\Command;
use LaraWP\Console\Scheduling\Schedule;
use LaraWP\Contracts\Console\Kernel as KernelContract;
use LaraWP\Contracts\Debug\ExceptionHandler;
use LaraWP\Contracts\Events\Dispatcher;
use LaraWP\Contracts\Foundation\Application;
use LaraWP\Support\Arr;
use LaraWP\Support\Env;
use LaraWP\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Throwable;

class Kernel implements KernelContract
{
    /**
     * The application implementation.
     *
     * @var \LaraWP\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * The event dispatcher implementation.
     *
     * @var \LaraWP\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * The Artisan application instance.
     *
     * @var \LaraWP\Console\Application|null
     */
    protected $artisan;

    /**
     * The Artisan commands provided by the application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Indicates if the Closure commands have been loaded.
     *
     * @var bool
     */
    protected $commandsLoaded = false;

    /**
     * The bootstrap classes for the application.
     *
     * @var string[]
     */
    protected $bootstrappers = [
        \LaraWP\Foundation\Bootstrap\LoadEnvironmentVariables::class,
        \LaraWP\Foundation\Bootstrap\LoadConfiguration::class,
        \LaraWP\Foundation\Bootstrap\HandleExceptions::class,
        \LaraWP\Foundation\Bootstrap\RegisterFacades::class,
        \LaraWP\Foundation\Bootstrap\SetRequestForConsole::class,
        \LaraWP\Foundation\Bootstrap\RegisterProviders::class,
        \LaraWP\Foundation\Bootstrap\BootProviders::class,
    ];

    /**
     * Create a new console kernel instance.
     *
     * @param \LaraWP\Contracts\Foundation\Application $app
     * @param \LaraWP\Contracts\Events\Dispatcher $events
     * @return void
     */
    public function __construct(Application $app, Dispatcher $events)
    {
        if (!defined('ARTISAN_BINARY')) {
            define('ARTISAN_BINARY', 'artisan');
        }

        $this->app = $app;
        $this->events = $events;

        $this->app->booted(function () {
            $this->defineConsoleSchedule();
        });
    }

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function defineConsoleSchedule()
    {
        $this->app->singleton(Schedule::class, function ($app) {
            return lp_tap(new Schedule($this->scheduleTimezone()), function ($schedule) {
                $this->schedule($schedule->useCache($this->scheduleCache()));
            });
        });
    }

    /**
     * Get the name of the cache store that should manage scheduling mutexes.
     *
     * @return string
     */
    protected function scheduleCache()
    {
        return $this->app['config']->get('cache.schedule_store', Env::get('SCHEDULE_CACHE_DRIVER'));
    }

    /**
     * Run the console application.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     * @return int
     */
    public function handle($input, $output = null)
    {
        try {
            $this->bootstrap();

            return $this->getArtisan()->run($input, $output);
        } catch (Throwable $e) {
            $this->reportException($e);

            $this->renderException($output, $e);

            return 1;
        }
    }

    /**
     * Terminate the application.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param int $status
     * @return void
     */
    public function terminate($input, $status)
    {
        $this->app->terminate();
    }

    /**
     * Define the application's command schedule.
     *
     * @param \LaraWP\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     *
     * @return \DateTimeZone|string|null
     */
    protected function scheduleTimezone()
    {
        $config = $this->app['config'];

        return $config->get('app.schedule_timezone', $config->get('app.timezone'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        //
    }

    /**
     * Register a Closure based command with the application.
     *
     * @param string $signature
     * @param \Closure $callback
     * @return \LaraWP\Foundation\Console\ClosureCommand
     */
    public function command($signature, Closure $callback)
    {
        $command = new ClosureCommand($signature, $callback);

        Artisan::starting(function ($artisan) use ($command) {
            $artisan->add($command);
        });

        return $command;
    }

    /**
     * Register all of the commands in the given directory.
     *
     * @param array|string $paths
     * @return void
     */
    protected function load($paths)
    {
        $paths = array_unique(Arr::wrap($paths));

        $paths = array_filter($paths, function ($path) {
            return is_dir($path);
        });

        if (empty($paths)) {
            return;
        }

        $namespace = $this->app->getNamespace();

        foreach ((new Finder)->in($paths)->files() as $command) {
            $command = $namespace . str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    Str::after($command->getRealPath(), realpath(lp_app_path()) . DIRECTORY_SEPARATOR)
                );

            if (is_subclass_of($command, Command::class) &&
                !(new ReflectionClass($command))->isAbstract()) {
                Artisan::starting(function ($artisan) use ($command) {
                    $artisan->resolve($command);
                });
            }
        }
    }

    /**
     * Register the given command with the console application.
     *
     * @param \Symfony\Component\Console\Command\Command $command
     * @return void
     */
    public function registerCommand($command)
    {
        $this->getArtisan()->add($command);
    }

    /**
     * Run an Artisan console command by name.
     *
     * @param string $command
     * @param array $parameters
     * @param \Symfony\Component\Console\Output\OutputInterface|null $outputBuffer
     * @return int
     *
     * @throws \Symfony\Component\Console\Exception\CommandNotFoundException
     */
    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        $this->bootstrap();

        return $this->getArtisan()->call($command, $parameters, $outputBuffer);
    }

    /**
     * Queue the given console command.
     *
     * @param string $command
     * @param array $parameters
     * @return \LaraWP\Foundation\Bus\PendingDispatch
     */
    public function queue($command, array $parameters = [])
    {
        return QueuedCommand::dispatch(func_get_args());
    }

    /**
     * Get all of the commands registered with the console.
     *
     * @return array
     */
    public function all()
    {
        $this->bootstrap();

        return $this->getArtisan()->all();
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        $this->bootstrap();

        return $this->getArtisan()->output();
    }

    /**
     * Bootstrap the application for artisan commands.
     *
     * @return void
     */
    public function bootstrap()
    {
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }

        $this->app->loadDeferredProviders();

        if (!$this->commandsLoaded) {
            $this->commands();

            $this->commandsLoaded = true;
        }
    }

    /**
     * Get the Artisan application instance.
     *
     * @return \LaraWP\Console\Application
     */
    protected function getArtisan()
    {
        if (is_null($this->artisan)) {
            return $this->artisan = (new Artisan($this->app, $this->events, $this->app->version()))
                ->resolveCommands($this->commands);
        }

        return $this->artisan;
    }

    /**
     * Set the Artisan application instance.
     *
     * @param \LaraWP\Console\Application $artisan
     * @return void
     */
    public function setArtisan($artisan)
    {
        $this->artisan = $artisan;
    }

    /**
     * Get the bootstrap classes for the application.
     *
     * @return array
     */
    protected function bootstrappers()
    {
        return $this->bootstrappers;
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param \Throwable $e
     * @return void
     */
    protected function reportException(Throwable $e)
    {
        $this->app[ExceptionHandler::class]->report($e);
    }

    /**
     * Render the given exception.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Throwable $e
     * @return void
     */
    protected function renderException($output, Throwable $e)
    {
        $this->app[ExceptionHandler::class]->renderForConsole($output, $e);
    }
}
