<?php
defined('SYSPATH') or die('No direct script access.');
abstract class AndrewC_SlowTask_Complete_Redirect extends SlowTask_Complete
{    
    public function __construct($uri)
    {
        $this->_uri = $uri;
    }
}