<?php

namespace light\xunsearch\models;

use hightman\xunsearch\ActiveRecord;

/**
 * The demo.ini AR
 */
class Demo extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function projectName()
    {
        return 'demo';
    }
}
