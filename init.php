<?php
defined('SYSPATH') or die('No direct script access.');

Route::set('slowtask','slowtask/<task_id>/<action>', array('task_id'=>'[0-9a-f\-]+'))
    ->defaults(array('controller'=>'slowtask'));