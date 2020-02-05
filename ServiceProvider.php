<?php

namespace Main;

use Admin\Classes\PermissionManager;
use Admin\Classes\Widgets;
use Event;
use Igniter\Flame\Foundation\Providers\AppServiceProvider;
use Illuminate\Support\Facades\View;
use Main\Classes\ThemeManager;
use Main\Template\Page;
use Setting;
use System\Libraries\Assets;

class ServiceProvider extends AppServiceProvider
{
    /**
     * Bootstrap the service provider.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot('main');

        View::share('site_name', Setting::get('site_name'));
        View::share('site_logo', Setting::get('site_logo'));

        ThemeManager::instance()->bootThemes();

        $this->bootMenuItemEvents();

        if (!$this->app->runningInAdmin()) {
            $this->resolveFlashSessionKey();
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        parent::register('main');

        if (!$this->app->runningInAdmin()) {
            $this->registerSingletons();
            $this->registerAssets();
            $this->registerCombinerEvent();
        }
        else {
            $this->registerFormWidgets();
            $this->registerPermissions();
        }
    }

    protected function registerSingletons()
    {
    }

    protected function registerAssets()
    {
        Assets::registerCallback(function (Assets $manager) {
            $manager->registerSourcePath($this->app->themesPath());

            ThemeManager::addAssetsFromActiveThemeManifest($manager);
        });
    }

    protected function registerCombinerEvent()
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        Event::listen('assets.combiner.beforePrepare', function (Assets $combiner, $assets) {
            ThemeManager::applyAssetVariablesOnCombinerFilters(
                array_flatten($combiner->getFilters())
            );
        });
    }

    protected function resolveFlashSessionKey()
    {
        $this->app->resolving('flash', function (\Igniter\Flame\Flash\FlashBag $flash) {
            $flash->setSessionKey('flash_data_main');
        });
    }

    /**
     * Registers events for menu items.
     */
    protected function bootMenuItemEvents()
    {
        Event::listen('pages.menuitem.listTypes', function () {
            return [
                'theme-page' => 'main::lang.pages.text_theme_page',
            ];
        });

        Event::listen('pages.menuitem.getTypeInfo', function ($type) {
            return Page::getMenuTypeInfo($type);
        });

        Event::listen('pages.menuitem.resolveItem', function ($item, $url, $theme) {
            if ($item->type == 'theme-page')
                return Page::resolveMenuItem($item, $url, $theme);
        });
    }

    protected function registerFormWidgets()
    {
        Widgets::instance()->registerFormWidgets(function (Widgets $manager) {
            $manager->registerFormWidget('Main\FormWidgets\Components', [
                'label' => 'Components',
                'code' => 'components',
            ]);

            $manager->registerFormWidget('Main\FormWidgets\TemplateEditor', [
                'label' => 'Template editor',
                'code' => 'templateeditor',
            ]);
        });
    }

    protected function registerPermissions()
    {
        PermissionManager::instance()->registerCallback(function ($manager) {
            $manager->registerPermissions('System', [
                'Admin.MediaManager' => [
                    'label' => 'main::lang.permissions.media_manager', 'group' => 'main::lang.permissions.name',
                ],
                'Site.Themes' => [
                    'label' => 'main::lang.permissions.themes', 'group' => 'main::lang.permissions.name',
                ],
            ]);
        });
    }
}