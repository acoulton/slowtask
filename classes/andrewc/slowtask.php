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

    protected $_id = null;
    protected $_status = null;
    protected static $_parent_thread = false;

    public static function is_parent_thread()
    {
        return self::$_parent_thread;
    }

    public static function begin(Request $request, $status_text, $instance_config = array())
    {
        $config = Kohana::config('slowtask.instance');
        SlowTask::$_parent_thread = true;
        // Create a SlowTask
        $task = new SlowTask($status_text, array_merge($config,$instance_config));


        // Get ready for the long haul
        set_time_limit(0);
        ignore_user_abort(true);
        session_write_close();

        // Render the progress view as the body of the passed in request
        ob_start();
        echo $task->status()->as_html();
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

    /**
     *
     * @param string $id
     * @return SlowTask_Status
     */
    public static function query_status($id)
    {
        $data = Cache::instance()->get("slowtask-$id-data");
        if ($data)
        {
            $task = unserialize($data);
            return $task->status();
        }
        return null;
    }

    public static function abort($id)
    {
        Cache::instance()->set("slowtask-$id-abort", true);
    }

    public function __construct($status_text, $config)
    {
        $this->_status = new SlowTask_Status($this);
        $this->_status->text = $status_text;
        $this->_status->config = $config;
        $this->_id = UUID::v4();
        $this->_persist();
    }

    protected function _yield()
    {
        // Check for user abort and throw an exception if set
        if (Cache::instance()->get("slowtask-$this->_id-abort"))
        {
            $this->_status->text = "Aborting";
            $this->_persist();
            throw new SlowTask_Abort_Exception($message);
        }
    }

    protected function _persist()
    {
        $this->_status->heartbeat = time();
        Cache::instance()->set("slowtask-$this->_id-data",serialize($this));
    }

    /**
     *
     * @return SlowTask_Status
     */
    public function status()
    {
        return $this->_status;
    }

    public function id()
    {
        return $this->_id;
    }

    public function progress_range($from,$to)
    {
        // Set the progress range
        $this->_status->progress_min = $from;
        $this->_status->progress_max = $to;
        $this->_persist();
        return $this;
    }

    public function heartbeat()
    {
        // Stop the timeout triggering
        $this->_persist();
    }

    public function progress($step = 1, $status_text = null)
    {
        // Set the progress
        $this->_status->progress += $step;
        if (($status_text === null) AND ($this->_status->text === null))
        {
            $this->_status->text = 'Working';
        }
        else if ($status_text !== null)
        {
            $this->_status->text = $status_text;
        }
        $this->_persist();
        $this->_yield();
    }

    public function log($message, $level = null)
    {
        // Log a message
        $this->_status->add_log($level,$message);
        $this->_persist();
    }

    public function complete(SlowTask_Complete $complete_handler)
    {
        $this->_status->complete = $complete_handler;
        $this->_persist(true);
    }

}