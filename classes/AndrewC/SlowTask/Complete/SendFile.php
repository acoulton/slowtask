<?php
defined('SYSPATH') or die('No direct script access.');
abstract class AndrewC_SlowTask_Complete_SendFile extends SlowTask_Complete
{
    protected $_file = null;
    protected $_unlink_on_complete = false;

    public function __construct(SlowTask $task, $file)
    {
        $this->_file = $file;
        $this->_uri = Route::url('slowtask', array(
                            'task_id'=>$task->id(),
                            'action' => 'complete_file'));
        $this->_html = "<p>Thanks, your download will begin shortly</p>";
    }

    public static function factory(SlowTask $task, $file)
    {
        return new SlowTask_Complete_SendFile($task, $file);
    }

    public function completion_message($message)
    {
        $this->_html = $message;
        return $this;
    }

    public function unlink_on_complete($unlink = null)
    {
        $this->_unlink_on_complete = $unlink_on_complete;
        return $this;
    }

    public function send_file(Response $response)
    {
        $response->send_file($this->_file, false, array(
            'delete'=>$this->_unlink_on_complete
        ));
    }

}