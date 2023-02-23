<?php

namespace LaraPress\Wordpress;

use LaraPress\Database\Connection;
use LaraPress\Routing\Redirector;
use LaraPress\Support\Facades\Artisan;
use LaraPress\Support\ServiceProvider;
use LaraPress\Wordpress\Admin\AdminServiceProvider;
use LaraPress\Wordpress\Auth\AuthServiceProvider;
use LaraPress\Wordpress\Console\Commands\Database\MigrationWipeCommand;
use LaraPress\Wordpress\Database\WpConnection;
use LaraPress\Wordpress\Database\WpConnector;
use LaraPress\Wordpress\Dependency\ResourceManager;
use LaraPress\Wordpress\Http\Response\Handler;
use LaraPress\Wordpress\Http\Response\PassThrough;
use LaraPress\Wordpress\Mail\Transport\WpTransport;
use LaraPress\Wordpress\Model\User;
use LaraPress\Wordpress\Routing\RoutingServiceProvider;
use LaraPress\Wordpress\Shortcode\ShortcodeManager;
use LaraPress\Wordpress\Translation\TranslationServiceProvider;

class WordpressServiceProvider extends ServiceProvider
{
    function register()
    {
        $this->configureDatabase();
        $this->registerResourceManager();
        $this->extendMigrationCommands();
        $this->registerMailerTransport();
        $this->registerResponse();
        $this->registerShortcodeManager();
        $this->registerChildServices();

    }

    protected function registerChildServices()
    {
        $this->app->register(RoutingServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(AdminServiceProvider::class);
        $this->app->register(TranslationServiceProvider::class);
    }

    function boot()
    {
        if (!is_wp()) {
            return;
        }
        User::setConnectionResolver($this->app['db']);
        User::setEventDispatcher($this->app['events']);
        $this->bootServices();
        $this->autoRestartQueue();
    }

    protected function bootServices()
    {
        $this->bootResourceManager();
        $this->bootShortcodeManager();
    }

    protected function autoRestartQueue()
    {
        add_action('activated_plugin', function () {
            Artisan::call('queue:restart');
        });
        add_action('deactivated_plugin', function () {
            Artisan::call('queue:restart');
        });
    }

    protected function registerResponse()
    {
        $this->app->singleton(Handler::class);
        Redirector::macro('pass', function () {
            return new PassThrough();
        });
    }

    protected function registerMailerTransport()
    {
        $this->app->resolving('mail.manager', function ($mailManager) {
            $mailManager->extend('wp', function ($config) {
                return new WpTransport($config);
            });
        });
    }

    protected function registerShortcodeManager()
    {
        $this->app->singleton(ShortcodeManager::class);
        $this->app->alias(ShortcodeManager::class, 'wp.shortcode');
    }

    protected function bootShortcodeManager()
    {
        $this->app->make(ShortcodeManager::class)->boot();
    }

    protected function configureDatabase()
    {
        $this->app->alias(WpConnector::class, 'db.connector.wp');
        Connection::resolverFor('wp', function ($connection, $database, $prefix, $config) {
            return new WpConnection($connection, $database, $prefix, $config);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function extendMigrationCommands()
    {
        //Custom wipe command then it wipe thing which create by LaraPress only
        $this->app->extend('command.db.wipe', function ($migrator, $app) {
            return new MigrationWipeCommand($app['migrator']);
        });
    }

    protected function registerResourceManager()
    {
        $this->app->singleton('resources', function () {
            return new ResourceManager($this->app);
        });
        $this->app->alias('resources', ResourceManager::class);
    }

    protected function bootResourceManager()
    {
        $this->app->make(ResourceManager::class)->boot();
    }
}