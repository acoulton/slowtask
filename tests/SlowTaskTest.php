<?php defined('SYSPATH') OR die('Kohana bootstrap needs to be included before tests run');

/**
 * Tests SlowTask "multi-threading" API
 *
 * @group slowtask
 *
 * @package    SlowTask
 * @author     Andrew Coulton
 * @copyright  (c) 2011 Andrew Coulton
 * @license    http://kohanaframework.org/license
 */
class SlowTask_Test extends Kohana_Unittest_TestCase
{

    protected static $main_task = null;


    public function setUp()
    {
        parent::setUp();
    }

    public function test_begin_creates_task()
    {
        // Setup the mock request
        $mock_request = $this->getMock('Response',array('send_headers'),
                array(null),'SlowTask_Test_Response_Mock',false);
        $mock_request->expects($this->once())
                ->method('send_headers');

        // Create the request
        $task = SlowTask::begin($mock_request, "working");

        // Check the response
        return $task;
    }

    /**
     * @depends test_begin_creates_task
     */
    public function test_main_is_parent_thread(SlowTask $task)
    {
        $this->assertEquals(true, SlowTask::is_parent_thread());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @depends test_begin_creates_task
     */
    public function test_client_is_child_thread()
    {
    }

}
