<?php
defined('SYSPATH') or die('No direct script access.');
/**
 *
 * @property string $text
 * @property int $progress
 * @property int $progress_min
 * @property int $progress_max
 * @property array $log
 * @property int $last_update
 * @property int $heartbeat
 * @property SlowTask_Complete $complete
 * @property array $config
 */
class AndrewC_SlowTask_Status
{
    protected $_text = null;
    protected $_progress = null;
    protected $_progress_min = 0;
    protected $_progress_max = 100;
    protected $_log = array();
    protected $_last_update = null;
    protected $_heartbeat = null;
    protected $_complete = null;
    protected $_config = array();

    protected $_parent = null;

    public function __get($name)
    {
        switch ($name)
        {
            case 'text':
            case 'progress':
            case 'progress_min':
            case 'progress_max':
            case 'log':
            case 'last_update':
            case 'heartbeat':
            case 'complete':
            case 'config':
                $name = '_'.$name;
                return $this->$name;

            default:
                throw new InvalidArgumentException("Unknown Property - $name");
        }
    }

    public function __set($name, $value)
    {
        static $is_parent = null;
        if ($is_parent === null)
        {
            $is_parent = SlowTask::is_parent_thread();
        }

        if ( ! $is_parent)
        {
            throw new InvalidArgumentException("Cannot set SlowTask status properties from a child request");
        }

        $name = '_' . $name;
        $this->$name = $value;
        $this->_last_update = time();
        $this->_heartbeat = time();
    }

    public function __construct(SlowTask $parent)
    {
        $this->_parent = $parent;
    }

    public function add_log($level, $message)
    {
        $this->_last_update = time();
        $this->_heartbeat = time();
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

    public function is_alive()
    {
        return time() < ($this->_heartbeat + $this->config['heartbeat_life']);
    }


    public function as_html()
    {
        $progress = View::factory('slowtask/progress')
                            ->set('percent',$this->percent())
                            ->set('status_text', $this->_text)
                            ->set('id', $this->_parent->id())
                            ->render();
        $template = View::factory($this->_config['controller_layout']);
        $template->body = $progress;

        return $template->render();
    }

    public function as_json()
    {
        if ($this->complete)
        {
            return $this->complete->as_json();
        }

        return json_encode(array(
            'running' => array(
                'percent'     => $this->percent(),
                'status_text' => $this->_text,
                'last_update' => $this->last_update,
                'heartbeat'   => $this->heartbeat,
                'is_alive'    => $this->is_alive()
                )));
    }


}