<?php
defined('SYSPATH') or die('No direct script access.');?>
<div id="task-progress">
    <div class="progressbar">
        <p class="percent"><?=$percent;?>%</p>
        <div class="bar" style="width: <?=$percent;?>%"></div>
    </div>
    <div class="status"><?=HTML::chars($status_text)?></div>
    <div class="abort"><?=HTML::anchor(Route::get('slowtask')->uri(array('task_id'=>$id,'action'=>'abort')),"Abort");?></div>
    <div class="log"></div>
</div>
<script type="text/javascript">
    var SlowTask = Class.create({
        container: null,
        percent_text_elem: null,
        percent_bar_elem: null,
        status_elem: null,
        abort_link: null,
        log_div: null,
        poll_url: '<?=Route::url('slowtask', array('task_id'=>$id, 'action'=>'progress'));?>',
        last_update: 0,
        failures: 0,

        initialize: function(container) {
            container = $(container);
            this.container = container;
            this.percent_text_elem = container.down('p.percent');
            this.percent_bar_elem = container.down('div.bar');
            this.status_elem = container.down('div.status');
            this.abort_link = container.down('div.abort a');
            this.abort_link.progress = this;
            this.log_div = container.down('div.log');
            $(document).observe('dom:loaded', this.on_dom_loaded.bind(this));
        },

        on_dom_loaded: function() {
            this.abort_link.observe('click', this.on_abort_click);
            this.update_status.defer(this.poll_url, this);
        },

        on_abort_click: function(event)
        {
            Event.stop(event);
            request = new Ajax.Request(this.href,{
                        method: 'get'
                        });
        },

        update_status: function(url, slowtask)
        {
            request = new Ajax.Request(url,{
                        method: 'get',
                        onSuccess: slowtask.on_new_status.bind(slowtask),
                        onFailure: slowtask.on_failure.bind(slowtask),
                        parameters: {
                            last_update: slowtask.last_update
                        }});
        },

        on_new_status: function(transport)
        {
            if ( ! transport.responseJSON)
            {
                this.log_div.insert({bottom: transport.responseText});
            }

            json = transport.responseJSON;
            failures = 0;

            if (json.running)
            {
                /*
                 * Format:
                 * running: {
                 *     percent: {percentage - no symbol}
                 *     status_text: {text}
                 *     last_update: {last_update}
                 *     is_alive: {is_alive}
                 */
                // Handle the response
                this.last_update = json.running.last_update;
                this.percent_text_elem.update(json.running.percent + "%");
                this.percent_bar_elem.style.width = json.running.percent + "%";
                this.status_elem.update(json.running.status_text);

                if ( ! json.running.is_alive)
                {
                    alert('The server task appears to have stalled');
                }

                // And straight away request status again
                this.update_status.defer(this.poll_url, this);
            }
            else if (json.complete)
            {
                if (json.complete.html)
                {
                    this.container.update(json.complete.html);
                }
                if (json.complete.uri)
                {
                    window.location = json.complete.uri;
                }
            }

        },

        on_failure: function(transport)
        {
            this.failures++;

            if (this.failures < 5)
            {
                this.update_status.delay(5, this.poll_url, this);
                return;
            }

            alert('Sorry, we\'ve lost communication with the server - the task might still be running');
        }

    });

    new SlowTask('task-progress');
</script>