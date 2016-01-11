<?php

namespace light\xunsearch\events;

use ReflectionClass;
use ReflectionProperty;
use Yii;
use yii\base\Object;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

class MockIndex extends Object
{
    public $called_class;

    /**
     * Delete the index
     * @param  integer $id
     */
    public function del($id)
    {
        $msg = [
            date('Y-m-d H:i:s'),
            ': ',
            $this->called_class,
            '[deleted index]',
            $id
        ];
        $msg = implode($msg) . PHP_EOL;
        file_put_contents(Yii::getAlias('@app/runtime/xs_index_update.log'), $msg, FILE_APPEND);
    }
}

class MockDb extends \yii\base\Component
{

    private $called_class;

    public function __construct($called_class, $config = [])
    {
        parent::__construct($config);
        $this->called_class = $called_class;
    }

    /**
     * Return index object, mock \XSIndex
     * @return [type] [description]
     */
    public function getIndex()
    {
        return new MockIndex(['called_class' => $this->called_class]);
    }

    /**
     * Document insert function
     */
    public function add($data)
    {
        $this->log($data, 'add');
    }

    /**
     * Document update function
     */
    public function update($data)
    {
        $this->log($data, 'update');
    }

    protected function log($data, $type)
    {
        $msg = [
            date('Y-m-d H:i:s'),
            ': ',
            $this->called_class,
            PHP_EOL,
            print_r($data, true)
        ];
        $msg = implode($msg) . PHP_EOL;
        file_put_contents(Yii::getAlias("@app/runtime/xs_db_{$type}.log"), $msg, FILE_APPEND);
    }
}

/**
 * The abstract class of updater
 *
 * @package light\xunsearch\events
 */
abstract class Updater extends Object
{
    /**
     * 三种操作状态
     * 1. 插入操作
     * 2. 更新操作
     * 3. 删除操作
     */
    const TYPE_INSERT = 1;
    const TYPE_UPDATE = 2;
    const TYPE_DELETE = 3;

    /**
     * @var integer The update operation type
     */
    public $type;

    /**
     * Return the database name
     * @return string
     */
    public static function dbName()
    {
        return Inflector::camel2id(StringHelper::basename(get_called_class()), '_');
    }

    /**
     * Run the job
     * @return boolean if the task is successfully
     */
    public function run()
    {
        if ($this->isInsert) {
            return $this->handleInsert();
        } elseif ($this->isUpdate) {
            return $this->handleUpdate();
        } elseif ($this->isDelete) {
            return $this->handleDelete();
        } else {
            //todo:: throw exception?
            return false;
        }
    }

    /**
     * Handle index insert
     * @return boolean
     */
    abstract protected function handleInsert();

    /**
     * Handle index update
     * @return boolean
     */
    abstract protected function handleUpdate();

    /**
     * Handle index delete
     * @return boolean
     */
    abstract protected function handleDelete();

    /**
     * Return the queue name
     * @return string
     */
    public static function queueName()
    {
        return 'xunsearch:jobs';
    }

    /**
     * serialize playload array
     * @return integer
     */
    public function push()
    {
        $redis = Yii::$app->get('redis', false);
        if (null == $redis) {
            return null;
        }
        return $redis->sadd(static::queueName(), serialize($this));
    }

    /**
     * Return the jobs
     * @return mixed
     */
    public static function popJobs($redis = null)
    {
        if (null === $redis) {
            $redis = Yii::$app->get('redis', false);
            if (null == $redis) {
                return null;
            }
        }
        return $redis->spop(static::queueName());
    }

    /**
     * Get the database instance
     * @return mixed
     */
    public function getDb()
    {
        if (XS_ENABLED && YII_ENV_PROD) {
            return $this->getSearch()->getDatabase(static::dbName());
        }
        return new MockDb(get_called_class());
    }

    /**
     * Get the xunsearch instance
     * @return hightman\xunsearch\Connection
     */
    public function getSearch()
    {
        $xunsearch = Yii::$app->get('xunsearch', false);
        if (null === $xunsearch) {
            $xunsearch = Yii::createObject([
                'class' => 'hightman\xunsearch\Connection',
                'iniDirectory' => '@light/xunsearch/config',
                'charset' => 'utf-8',
            ]);
        }
        return $xunsearch;
    }

    /**
     * Determine if update operation
     * @return boolean
     */
    public function getIsUpdate()
    {
        return $this->type === self::TYPE_UPDATE;
    }

    /**
     * Determine if insert operation
     * @return boolean
     */
    public function getIsInsert()
    {
        return $this->type === self::TYPE_INSERT;
    }

    /**
     * Determine if delete operation
     * @return boolean
     */
    public function getIsDelete()
    {
        return $this->type === self::TYPE_DELETE;
    }

    /**
     * Set type of insert
     * @return $this
     */
    public function insert()
    {
        $this->type = self::TYPE_INSERT;
        return $this;
    }

    /**
     * Set type of update
     * @return $this
     */
    public function update()
    {
        $this->type = self::TYPE_UPDATE;
        return $this;
    }

    /**
     * Set type of delete
     * @return $this
     */
    public function delete()
    {
        $this->type = self::TYPE_DELETE;
        return $this;
    }
    /**
     * Magic method of sleep
     */
    public function __sleep()
    {
        $properties = (new ReflectionClass($this))->getProperties();

        return array_map(function ($property) {
            return $property->getName();
        }, $properties);
    }

    /**
     * Magic method of wake up
     */
    public function __wakeup()
    {
        foreach ((new ReflectionClass($this))->getProperties() as $property) {
            $property->setValue(
                $this,
                $this->getPropertyValue($property)
            );
        }
    }

    /**
     * Get the property value for the given property
     *
     * @param  ReflactionProperty $property
     * @return mixed
     */
    protected function getPropertyValue(ReflectionProperty $property)
    {
        $property->setAccessible(true);
        return $property->getValue($this);
    }
}
