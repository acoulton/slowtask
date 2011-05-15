# About SlowTask

## Usage

    $task = SlowTask::begin($this->request, "Doing something slow");
    // NOTE that from here on in you are disconnected from the original request
    // output will not be sent to the browser, except through $task->complete()
    try
    {
        $task->progress_range(0,10);
        for ($i = 0; $i < 10; $i++)
	{
	    $task->progress();
	    $task->log('i = '.$i);
	    sleep(2);
	}
	$task->complete(SlowTask_Complete_SendFile::factory($task, DOCROOT . 'assets/logo.gif'));
    }
    catch (SlowTask_Abort_Exception $e)
    {
	$task->complete(new SlowTask_Complete_HTML('<div class="warning"><p>You aborted!.</p></div>'));
    }
    catch (Exception $e)
    {
	$task->complete(new SlowTask_Complete_HTML('<div class="formerror"><p>I died!</p></div>'));
	throw $e;
    }
    //$task->complete(SlowTask::HTML,View::factory('someview')->render());
    //$task->complete(new SlowTask_Complete_Redirect('/'));