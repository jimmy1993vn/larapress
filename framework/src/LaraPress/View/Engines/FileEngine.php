<?php

namespace LaraPress\View\Engines;

use LaraPress\Contracts\View\Engine;
use LaraPress\Filesystem\Filesystem;

class FileEngine implements Engine
{
    /**
     * The filesystem instance.
     *
     * @var \LaraPress\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new file engine instance.
     *
     * @param \LaraPress\Filesystem\Filesystem $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @param string $path
     * @param array $data
     * @return string
     */
    public function get($path, array $data = [])
    {
        return $this->files->get($path);
    }
}
