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
    new Ajax.PeriodicalUpdater($('task-progress'),
                               '<?=Route::url('slowtask', array('task_id'=>$id,'action'=>'progress'))?>',
                               {frequency: 2});
</script>