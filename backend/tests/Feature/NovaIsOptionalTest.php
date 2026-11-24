<?php

namespace Tests\Feature;

use App\Providers\NovaServiceProvider;
use Laravel\Nova\Nova;
use Tests\TestCase;

class NovaIsOptionalTest extends TestCase
{
    public function test_app_boots_without_nova_and_does_not_load_the_nova_provider(): void
    {
        if (class_exists(Nova::class)) {
            $this->assertArrayHasKey(NovaServiceProvider::class, $this->app->getLoadedProviders());

            return;
        }

        $this->assertArrayNotHasKey(NovaServiceProvider::class, $this->app->getLoadedProviders());
        $this->get('/up')->assertOk();
    }

    public function test_every_nova_file_is_valid_php(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Nova')));
        $checked = 0;

        foreach ($files as $file) {
            if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            exec('php -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $code);
            $this->assertSame(0, $code, implode("\n", $output));
            $checked++;
        }

        $this->assertGreaterThan(10, $checked);
    }
}
