<?php
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base.php';

if($argc < 2){
    exit("Usage: php " . __FILE__ . " [list|run] <jobname>\n");
}
$command = $argv[1];

switch ($command) {
    case 'list':
        $jobs = \DF\Base\Cron::jobList();
        $keys = array_keys($jobs);
        echo "Job list:\n";
        $i = 0;
        foreach($jobs as $k => $v){
            echo $i ."\t". $k . "\n";
            ++$i;
        }
        echo "\n";
        break;
    case 'run':
        if($argc < 3){
            exit("Usage: php " . __FILE__ . " run <jobname>\n");
        }
        $jobname = strtolower(str_replace('_', '', $argv[2]));
        $jobs = \DF\Base\Cron::jobList();
        if(!isset($jobs[$jobname])){
            exit("job {$jobname} not found\n");
        }
        $cls = $jobs[$jobname];
        if(!class_exists($cls)){
            exit("class {$cls} not found\n");
        }
        \DF\Base\Cron::dispatch($cls);
        break;
    default:
        exit("Usage: php " . __FILE__ . "[list|run] <jobname>\n");
        break;
}