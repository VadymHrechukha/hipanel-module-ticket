<?php

/*
 * HiPanel tickets module
 *
 * @link      https://github.com/hiqdev/hipanel-module-ticket
 * @package   hipanel-module-ticket
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2015-2016, HiQDev (http://hiqdev.com/)
 */

namespace hipanel\modules\ticket\grid;

use hipanel\grid\ActionColumn;
use hipanel\grid\MainColumn;
use hipanel\grid\SwitchColumn;
use hipanel\modules\client\grid\ClientColumn;
use Yii;

class TemplateGridView extends \hipanel\grid\BoxedGridView
{
    public static function defaultColumns()
    {
        return [
            'author_id' => [
                'class' => ClientColumn::class,
                'idAttribute' => 'author_id',
                'attribute' => 'author_id',
                'nameAttribute' => 'author',
            ],
            'post_date' => [
                'value' => function ($model) {
                    return Yii::$app->formatter->asDatetime($model->post_date);
                },
            ],
            'actions' => [
                'class' => ActionColumn::class,
                'template' => '{view}{delete}',
                'header' => Yii::t('hipanel', 'Actions'),
            ],
            'is_published' => [
                'class' => SwitchColumn::class,
                'switchInputOptions' => [
                    'disabled' => true,
                ],
            ],
            'name' => [
                'class' => MainColumn::class,
                'filterAttribute' => 'name_like',
            ],
        ];
    }
}
