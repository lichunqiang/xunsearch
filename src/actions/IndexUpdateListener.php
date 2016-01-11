<?php

namespace light\xunsearch\actions;

use light\xunsearch\events\Updater;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\redis\Connection;

/**
 * The xunsearch update queue listener
 *
 * ~~~
 * public function actions()
 * {
 *     return [
 *         'listen' => 'light\xunsearch\actions\IndexUpdateListener'
 *     ];
 * }
 *
 * //command line
 * $ nohup yii xun/listen > /dev/null 2>&1 &
 *
 * ~~~
 */
class IndexUpdateListener extends Action
{
    private $_redis;

    /**
     * @inheritdoc
     */
    public function run()
    {
        set_time_limit(0);
        $redis = Yii::$app->get('redis');
        if (!($redis instanceof Connection)) {
            throw new InvalidConfigException('Please configure the redis component!');
        }
        $this->_redis = $redis;
        while ($this->runTask()) {

        }

        // while (true) {
        //     if (!$this->runTask()) {
        //         sleep(5);
        //     }
        // }
    }

    /**
     * Run task
     * @return boolean
     */
    protected function runTask()
    {
        $task = Updater::popJobs($this->_redis); // $this->_redis->rpop(Updater::queueName());
        if (null === $task) {
            //list is none
            return false;
        }
        $task = @unserialize($task);
        if (!$task) {
            return false;
        }
        try {
            $task->run();
            return true;
        } catch (\Exception $e) {
            //rollback
            $task->push();
            Yii::error($e->getMessage(), 'debug');
            return false;
        }
    }
}
