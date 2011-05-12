<?php
defined('SYSPATH') or die('No direct script access.');
/**
 *
 *     $task = SlowTask::begin($request);
 *     // NOTE that from here on in you are disconnected from the original request
 *     // output will not be sent to the browser, except through $task->complete()
 *     $task->progress_range(0,10000);
 *     for ($i = 0; $i < 10000; $i++)
 *     {
 *         $task->progress();
 *         $task->log('i = '.$i);
 *         sleep(5);
 *     }
 *     $task->complete(SlowTask::SEND_FILE,$file);
 *     $task->complete(SlowTask::HTML,View::factory('someview')->render());
 *     $task->complete(SlowTask::REDIRECT, $uri);
 *
 * The task has two sets of storage in the cache:
 *
 * slowtask-{uuid}-progress
 * slowtask-{uuid}-abort
 *
 */
abstract class AndrewC_SlowTask
{
    const SEND_FILE = 1;
    const HTML = 2;
    const REDIRECT = 3;

    protected $_id = null;
    protected $_status_text = null;
    protected $_progress = null;
    protected $_progress_min = 0;
    protected $_progress_max = 100;
    protected $_log = array();
    protected $_last_update = null;
    protected $_heartbeat = null;
    protected $_complete = null;

    protected $_config = array();

    public static function begin(Request $request, $status_text, $instance_config = array())
    {
        $config = Kohana::config('slowtask.instance');
        // Create a SlowTask
        $task = new SlowTask($status_text, array_merge($config,$instance_config));

        // Get ready for the long haul
        set_time_limit(0);
        ignore_user_abort(true);
        session_write_close();

        // Render the progress view as the body of the passed in request
        ob_start();
        $task->render_progress();
        $request->headers['Content-Length'] = ob_get_length();
        $request->headers['Connection'] = 'close';
        $request->send_headers();
        while (ob_get_level())
        {
            ob_end_flush();
        }
        flush();
        return $task;
    }

    public static function query($id)
    {
        $data = Cache::instance()->get("slowtask-$id-data");
        if ($data)
        {
            return unserialize($data);
        }
        return null;
    }

    public static function abort($id)
    {
        Cache::instance()->set("slowtask-$id-abort", true);
    }

    public function __construct($status_text, $config)
    {
        $this->_status_text = $status_text;
        $this->_config = $config;
        $this->_id = UUID::v4();
        $this->_persist();
    }

    protected function _yield()
    {
        // Check for user abort and throw an exception if set
        if (Cache::instance()->get("slowtask-$this->_id-abort"))
        {
            $this->_status_text = "Aborting";
            $this->_persist();
            throw new SlowTask_Abort_Exception($message);
        }
    }

    protected function _persist($updated = true)
    {
        if ($updated)
        {
            $this->_last_update = time();
        }
        $this->_heartbeat = time();
        Cache::instance()->set("slowtask-$this->_id-data",serialize($this));
    }


    public function progress_range($from,$to)
    {
        // Set the progress range
        $this->_progress_min = $from;
        $this->_progress_max = $to;
        $this->_persist();
        return $this;
    }

    public function heartbeat()
    {
        // Stop the timeout triggering
        $this->_persist(false);
    }

    public function progress($step = 1, $status = null)
    {
        // Set the progress
        $this->_progress += $step;
        if (($status === null) AND ($this->_status_text === null))
        {
            $this->_status_text = 'Working';
        }
        else if ($status !== null)
        {
            $this->_status_text = $status;
        }
        $this->_persist();
        $this->_yield();
    }

    public function log($message, $level = null)
    {
        // Log a message
        $this->_log[] = array('level'=>$level,
                              'message'=>$message);
        $this->_persist();
    }

    public function complete($method, $data)
    {
        // Store the info ready for sending back to the browser
        switch ($method)
        {
            case SlowTask::SEND_FILE;
            break;
            case SlowTask::HTML;
            break;
            case SlowTask::REDIRECT;
            break;
        }
        $this->_complete = array('method'=>$method, 'data'=>$data);
        $this->_persist(true);
    }

    public function percent()
    {
        $range = $this->_progress_max - $this->_progress_min;
        if ($range == 0)
        {
            return "#Error#";
        }
        return round( 100 * ($this->_progress - $this->_progress_min) / $range);
    }

    public function render_progress($layout = true)
    {
        $progress = View::factory('slowtask/progress')
                            ->set('percent',$this->percent())
                            ->set('status_text', $this->_status_text)
                            ->set('id', $this->_id)
                            ->render();
        if ( ! $layout)
        {
            echo $progress;
        }
        else
        {
            $template = View::factory('templates/staff.edbookfest');
            $template->body =$progress;
            echo $template->render();
        }
    }
}