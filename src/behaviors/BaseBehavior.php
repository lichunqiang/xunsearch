<?php

namespace light\xunsearch\behaviors;

use yii\base\Behavior;
use yii\db\BaseActiveRecord;

/**
 * The abstract class of sync behaviors.
 * All sync behavior should extends from this.
 */
abstract class BaseBehavior extends Behavior
{
    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_AFTER_INSERT => 'handleInsert',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'handleUpdate',
            BaseActiveRecord::EVENT_AFTER_DELETE => 'handleDelete',
        ];
    }

    /**
     * The ActiveRecord insert event handler
     * @param yii\db\AfterSaveEvent $event
     */
    abstract public function handleInsert($event);

    /**
     * The ActiveRecord update event handler
     * @param yii\db\AfterSaveEvent $event
     */
    abstract public function handleUpdate($event);

    /**
     * The ActiveRecord deleted event handler
     * @param yii\base\Event $event
     */
    abstract public function handleDelete($event);
}
