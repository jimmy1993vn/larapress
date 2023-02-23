<?php

namespace LaraPress\Wordpress\View;

use LaraPress\Wordpress\Contracts\Shortcode as ShortcodeContract;

abstract class Shortcode extends Component implements ShortcodeContract
{
    protected $tag;
    protected $attributes = [];
    protected $content = '';

    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    public function attribute($key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function getTag()
    {
        return $this->tag;
    }

    public function cleanup()
    {

    }
}