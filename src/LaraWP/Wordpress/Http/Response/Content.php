<?php

namespace LaraWP\Wordpress\Http\Response;

use LaraWP\Contracts\View\View;
use LaraWP\Wordpress\Contracts\HasPostTitle;
use LaraWP\Wordpress\Http\Response;
use LaraWP\Wordpress\View\Component;
use LaraWP\Wordpress\View\Factory;


/**
 * @mixin View
 */
class Content extends Response implements HasPostTitle
{
    use Response\Concerns\PostTitle;

    public function __construct($view = null, $data = [], $mergeData = [])
    {
        parent::__construct();
        if ($view) {
            $this->push($view, $data, $mergeData);
        }
    }


    function getContent($content = null)
    {
        $buffer = '';
        foreach ($this->components as $view) {
            $buffer .= Handler::renderView($view);
        }
        if ($buffer) {
            return $buffer;
        }
        return $content;
    }

    /**
     * @param $view
     * @param $data
     * @param $mergeData
     * @param $key
     * @return mixed|View|Content
     */
    function push($view, $data = [], $mergeData = [], $key = null)
    {
        $view = lp_app(Factory::class)->make($view, $data, $mergeData);
        $this->offsetSet($key, $view);
        return $view;
    }

    function append($content, $key = null)
    {
        $this->push($content, [], [], $key);
        return $this;
    }

    public static function make($view, $data = [], $mergeData = [])
    {
        return new static($view, $data, $mergeData);
    }

    public function __call($method, $parameters)
    {
        foreach ($this->components as $view) {
            if (is_object($view)) {
                call_user_func_array([$view, $method], $parameters);
            }
        }
        return $this;
    }


}