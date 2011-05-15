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
    
    /**
     * Checks whether the current PHP thread is the parent (slow task) or child (progress reporting) 
     * thread for this task.
     * @return boolean
     */
    public static function is_parent_thread()
    {
        return self::$_parent_thread;
    }

    /**
     * Begin running a SlowTask - factory the task instance, setup the time limit, user abort
     * and session_write_close, and send the progress bar view back to the client.
     *
     * [!!] NOTE that once this is called you are disconnected from the client - headers have been
     * sent, response body is sent, and connection is closed. You must now capture output, log status
     * and communicate with the client through the SlowTask API calls.
     *
     * [!!] Also note that, to prevent deadlocking on native sessions, you also cannot write to the session
     * following this call.
     *
     * @param Request $request The request that will be completed and receive the progress bar
     * @param string $status_text The initial status message for the task
     * @param array $instance_config Any specific config to be merged with slowtask.instance
     * @return SlowTask
     */
    public static function begin(Request $request, $status_text, $instance_config = array())
    {
        $config = Kohana::config('slowtask.instance');
        SlowTask::$_parent_thread = true;

        // Create a SlowTask
        $task = new SlowTask($status_text, array_merge($config,$instance_config));

        // Render the progress view as the body of the passed in request
        $request->response = $task->status()->as_html();
        $request->headers['Content-Length'] = strlen($request->response);
        $request->headers['Connection'] = 'close';
        $request->send_headers();

        //@todo: Is there a better way to avoid running this code in PHPUnit?
        if ( ! ($request instanceof SlowTask_Test_Request_Mock))
        {
            // Get ready for the long haul
            set_time_limit(0);
            ignore_user_abort(true);
            session_write_close();

            echo $request->response;

            while (ob_get_level())
            {
                ob_end_flush();
            }
            flush();
        }
        return $task;
    }

    /**
     * Fetches the [SlowTask_Status] object for a given task id.
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

    /**
     * Signals a running task to abort, given its ID. This does not take effect immediately
     * but is detected whenever the running task calls [SlowTask::_yield()] - eg within
     * [SlowTask::progress()]
     * 
     * @param string $id The UUID of the task to signal
     */
    public static function abort($id)
    {
        Cache::instance()->set("slowtask-$id-abort", true);
    }

    /**
     * Constructor for a SlowTask - use [SlowTask::begin()] instead
     * @param string $status_text The initial status message
     * @param array $config Instance configuration
     * @uses [UUID::v4()]
     */
    public function __construct($status_text, $config)
    {
        $this->_status = new SlowTask_Status($this);
        $this->_status->text = $status_text;
        $this->_status->config = $config;
        $this->_id = UUID::v4();
        $this->_persist();
    }

    /**
     * Internal method that checks for user abort and throws an exception if set.
     * This method is called by [SlowTask::progress()]. Task processing code should
     * therefore catch and handle a [SlowTask_Abort_Exception] to accommodate client abort.
     * 
     * @throws SlowTask_Abort_Exception If the task has been signaled to abort
     */
    protected function _yield()
    {
        // Check for user abort and throw an exception if set
        if (Cache::instance()->get("slowtask-$this->_id-abort"))
        {
            $this->_status->text = "Aborting";
            $this->_persist();
            throw new SlowTask_Abort_Exception("User aborted");
        }
    }

    /**
     * Persists the task and its status into the Cache, also setting the 
     * task heartbeat that is used on the client side to detect a dead process.
     */
    protected function _persist()
    {
        $this->_status->heartbeat = time();
        Cache::instance()->set("slowtask-$this->_id-data",serialize($this));
    }

    /**
     * Gets the status object for this task
     * @return SlowTask_Status
     */
    public function status()
    {
        return $this->_status;
    }

    /**
     * Gets the UUID of this task
     * @return string 
     */
    public function id()
    {
        return $this->_id;
    }

    /**
     * Sets the range of progress values from which a percentage completion should be calculated.
     * This is useful when you have a linear process with a known number of elements to handle.
     * 
     *     $task = SlowTask::begin()
     *             ->progress_range(0, count($files));
     *     foreach ($files as $file)
     *     {
     *         // Do something with the file
     *         $task->progress();
     *     }
     *
     * @param int $from The low value of the range
     * @param int $to The high value of the range
     * @return SlowTask
     */
    public function progress_range($from,$to)
    {
        // Set the progress range
        $this->_status->progress_min = $from;
        $this->_status->progress_max = $to;
        $this->_persist();
        return $this;
    }

    /**
     * Updates the task heartbeat, used client side to detect stalled processes.
     * Useful when you have several slow-ish actions for each step, and want to
     * just note that the process is running without updating progress.
     *
     * @return SlowTask
     */
    public function heartbeat()
    {
        // Stop the timeout triggering
        $this->_persist();
        return $this;
    }

    /**
     * Updates the progress, optionally setting a status text. This also calls [SlowTask::_yield()] and 
     * may therefore throw an exception if the user has signaled the task to abort.
     * 
     * @throws SlowTask_Abort_Exception If the user has singaled the task to abort.
     * @param string $status_text An update to the status text shown to the user (eg filename currently processing)
     * @param int $step How many steps to advance the progress by.
     * @return SlowTask
     *
     */
    public function progress($status_text = null, $step = 1)
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
        return $this;
    }

    /**
     * Adds a message to the task's ativity log, optionally displayed client side
     * @param string $message The message
     * @param string $level A log level (eg warning/error/etc)
     * @return SlowTask
     */
    public function log($message, $level = null)
    {
        // Log a message
        $this->_status->add_log($level,$message);
        $this->_persist();
        return $this;
    }

    /**
     * Marks the task as complete and signals the client to take some action to 
     * show new content or redirect to a new page. Accepts a SlowTask_Complete handler
     * object which implements the relevant action.
     *
     *     $task->complete(new SlowTask_Complete_Redirect($_SERVER['HTTP_REFERRER']));
     *
     * @param SlowTask_Complete $complete_handler
     */
    public function complete(SlowTask_Complete $complete_handler)
    {
        $this->_status->complete = $complete_handler;
        $this->_persist(true);
        return $this;
    }

}