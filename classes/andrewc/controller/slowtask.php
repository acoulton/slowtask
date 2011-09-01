<?php
defined('SYSPATH') or die('No direct script access.');
abstract class AndrewC_Controller_SlowTask extends Controller
{
    public function action_progress()
    {
        $id = $this->request->param('task_id');

        //@todo: Implement a non-ajax fallback
        if ( ! Request::$is_ajax)
        {
            throw new Kohana_Exception('not ajax');
        }

        // Get the current status
        $status = SlowTask::query_status($id);
        $polling_end = $_SERVER['REQUEST_TIME'] + $status->config['server_max_poll'];
        set_time_limit($status->config['server_max_poll'] + 5);
        $last_update = Arr::get($_GET, 'last_update', 0);
        $poll_interval = $status->config['server_poll_interval'];

        // Poll for changes until there is something new or we time out
        while (($status->last_update <= $last_update) AND (time() < $polling_end))
        {
            sleep($poll_interval);
            $status = SlowTask::query_status($id);
        }

        // Render the result back to the client
        $this->response->headers('Content-type', 'application/json');
        $this->response->body($status->as_json());
    }

    public function action_abort()
    {
        $id = $this->request->param('task_id');
        SlowTask::abort($id);
        $status = SlowTask::query_status($id);

        $this->response->headers('Content-type','application/json');
        $this->response->body($status->as_json());
    }

    public function action_complete_file()
    {
        $id = $this->request->param('task_id');
        $status = SlowTask::query_status($id);

        if ($status->complete instanceof SlowTask_Complete_SendFile)
        {
            $status->complete->send_file($this->response);
        }
    }
}