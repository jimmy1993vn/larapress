<?php

namespace LaraWP\Wordpress\Http\Response;
/**
 * Full page response and terminate application after render
 */
class Page extends Content
{
    protected $hook;

    public function on($hook, $priority = 10)
    {
        $this->hook = [$hook, $priority];
        return $this;
    }

    public function onWpLoaded($priority = 10)
    {
        return $this->on('lp_loaded', $priority);
    }

    public function onWp($priority = 10)
    {
        return $this->on('wp', $priority);
    }

    public function onTemplateRedirect($priority = 10)
    {
        return $this->on('template_redirect', $priority);
    }

    public function getHook()
    {
        return $this->hook;
    }
}