<?php
defined('SYSPATH') or die('No direct script access.');
abstract class AndrewC_SlowTask_Complete
{
    protected $_uri = null;
    protected $_html = null;

    public function as_json()
    {
        $complete = array();
        if ($this->_uri)
        {
            $complete['uri'] = $this->_uri;
        }
        if ($this->_html)
        {
            $complete['html'] = $this->_html;
        }
        return json_encode(array('complete'=>$complete));
    }
}