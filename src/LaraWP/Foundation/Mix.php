<?php

namespace LaraWP\Foundation;

use Exception;
use LaraWP\Support\HtmlString;
use LaraWP\Support\Str;

class Mix
{
    /**
     * Get the path to a versioned Mix file.
     *
     * @param string $path
     * @param string $manifestDirectory
     * @return \LaraWP\Support\HtmlString|string
     *
     * @throws \Exception
     */
    public function __invoke($path, $manifestDirectory = '')
    {
        static $manifests = [];

        if (!Str::startsWith($path, '/')) {
            $path = "/{$path}";
        }

        if ($manifestDirectory && !Str::startsWith($manifestDirectory, '/')) {
            $manifestDirectory = "/{$manifestDirectory}";
        }

        if (is_file(lp_public_path($manifestDirectory . '/hot'))) {
            $url = rtrim(file_get_contents(lp_public_path($manifestDirectory . '/hot')));

            $customUrl = lp_app('config')->get('app.mix_hot_proxy_url');

            if (!empty($customUrl)) {
                return new HtmlString("{$customUrl}{$path}");
            }

            if (Str::startsWith($url, ['http://', 'https://'])) {
                return new HtmlString(Str::after($url, ':') . $path);
            }

            return new HtmlString("//localhost:8080{$path}");
        }

        $manifestPath = lp_public_path($manifestDirectory . '/mix-manifest.json');

        if (!isset($manifests[$manifestPath])) {
            if (!is_file($manifestPath)) {
                throw new Exception('The Mix manifest does not exist.');
            }

            $manifests[$manifestPath] = json_decode(file_get_contents($manifestPath), true);
        }

        $manifest = $manifests[$manifestPath];

        if (!isset($manifest[$path])) {
            $exception = new Exception("Unable to locate Mix file: {$path}.");

            if (!lp_app('config')->get('app.debug')) {
                lp_report($exception);

                return $path;
            } else {
                throw $exception;
            }
        }

        return new HtmlString(lp_app('config')->get('app.mix_url') . $manifestDirectory . $manifest[$path]);
    }
}
