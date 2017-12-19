<?php
namespace app\command;

use app\crontab\CrontabInterface;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Log;

class Crontab extends Command
{

    protected function configure()
    {
        $this->setName('crontab')->setDescription('linux crontab for thinkphp5');

        $this->addArgument('job', Argument::REQUIRED, 'the job name');
    }

    protected function execute(Input $input, Output $output)
    {
        $job = $input->getArgument('job');

        // 限制名称
        if (!preg_match('/^[a-zA-z0-9]+$/', $job)) {
            $output->writeln('crontab name: ' . $job . ' not permission');
            exit;
        }

        // 检查定时任务文件是否存在
        $class ='\app\crontab\\'.$job;
        if (class_exists($class)) {
            Log::init(['path' => ROOT_PATH . "/logs/command/$job/"]);
            $cron = new $class();
            // 执行对应的定时任务
            $this->_execCrontab($cron);
            $output->writeln('crontab ' . $job . ' command end.');
        } else {
            $output->writeln('crontab ' . $job . ' not existed.');
        }

    }

    private function _execCrontab(CrontabInterface $crontab)
    {
        $crontab->exec();
    }
}