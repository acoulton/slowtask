<?php
defined('SYSPATH') or die('No direct script access.');
abstract class AndrewC_Controller_SlowTask extends Controller
{
    public function action_progress()
    {
        // Needs to support AJAX and HTML
        $id = $this->request->param('task_id');
        $status = SlowTask::query($id);
        ob_start();
        $status->render_progress(false);
        $this->request->response = ob_get_clean();
    }

    public function action_abort()
    {
        $id = $this->request->param('task_id');
        SlowTask::abort($id);
        $status = SlowTask::query($id);
        ob_start();
        $status->render_progress();
        $this->request->response = ob_get_clean();
    }

    public function action_complete()
    {

    }
}