<?php

namespace LaraPress\Wordpress\Contracts;

interface Shortcode
{
    public function getTag();

    public function render();
}