<?php
defined('SYSPATH') or die('No direct script access.');
abstract class AndrewC_SlowTask_Complete_HTML extends SlowTask_Complete
{
    public function __construct($html)
    {
        $this->_html = $html;
    }
}