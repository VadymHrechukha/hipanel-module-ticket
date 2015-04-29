<?php
namespace hipanel\modules\ticket\widgets;

use yii\helpers\Html;
use yii\jui\Widget;
use common\components\Lang;

class Topic extends Widget
{
    public $topics;

   private function _getColor($item) {
       $colors = [
           'general' => 'label-default',
           'technical' => 'label-primary',
           'domain' => 'label-success',
           'financial' => 'label-warning',
       ];
       if (array_key_exists($item, $colors))
           return $colors[$item];
       else
           return reset($colors);
   }

    public function init() {
        parent::init();
        if ($this->topics) {
            $html = '';
            $html .= '<ul class="list-inline">';
                foreach ($this->topics as $item=>$label) {
                    $html .= Html::tag('li', '<span class="label '.$this->_getColor($item).'">'.Lang::l($label).'</span>');
                }
            $html .= '</ul>';
            print $html;
        }
    }
}