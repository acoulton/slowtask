<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf8">
<style type="text/css">
#task-progress
{
    width: 80%;
    margin: 2em auto;
    border: 1px solid #69bc50;
    padding: 1em;
    text-align: center;
}

#task-progress div.progressbar
{
    position: relative;
    border: 1px solid #69bc50;
    height: 2.5em;
}

#task-progress p.percent
{
    position: absolute;
    top: 0.4em;
    height: 2em;
    width: 100%;
    z-index: 5;
}

#task-progress div.bar
{
    background-color: #c2dfaf;
    top: 0;
    height: 100%;
    width: 0%;
}

#task-progress div.status
{
    font-style: italic;
}

#task-progress div.log
{
    text-align: left;
}
</style>
<?=HTML::script('assets/js/lib/prototype/prototype.js')?>
<title>Task progress</title>
</head>
<body>
      <?php echo $body;?>
</body>
</html>