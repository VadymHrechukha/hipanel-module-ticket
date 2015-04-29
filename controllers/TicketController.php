<?php
/**
 * @link    http://hiqdev.com/hipanel-module-ticket
 * @license http://hiqdev.com/hipanel-module-ticket/license
 * @copyright Copyright (c) 2015 HiQDev
 */

namespace hipanel\modules\ticket\controllers;

use Yii;
use hipanel\modules\ticket\models\Thread;
use hipanel\modules\ticket\models\TicketSettings;
use common\models\File;
use hiqdev\hiar\HiResException;
use yii\helpers\ArrayHelper;

/**
 * Class TicketController
 * @package hipanel\modules\ticket\controllers
 */
class TicketController extends \hipanel\base\CrudController
{
    /**
     * @var array
     */
    private $_subscribeAction = ['subscribe' => 'add_watchers', 'unsubscribe' => 'del_watchers'];

    /**
     * @return mixed
     */
    static public function modelClassName()
    {
        return Thread::className();
    }

    /**
     * @return array
     */
    protected function prepareRefs()
    {
        return [
            'topic_data' => $this->getRefs('topic,ticket'),
            'state_data' => $this->GetClassRefs('state'),
            'priority_data' => $this->getPriorities(),
        ];
    }

    /**
     * @return string
     */
    public function actionIndex()
    {
        return parent::actionIndex($this->prepareRefs());
    }

    /**
     * @param int $id
     * @return string
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionView($id)
    {
        $model = $this->findModel(ArrayHelper::merge(compact('id'), ['with_answers' => 1, 'with_files' => 1]), ['scenario' => 'answer']);
        return $this->render('view', ArrayHelper::merge(compact('model'), $this->prepareRefs()));
    }

    /**
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new Thread();
        $model->scenario = 'insert';
        $model->load(Yii::$app->request->post());
        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', array_merge(compact('model'), $this->prepareRefs()));
    }

    /**
     * @param $id
     * @return \yii\web\Response
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $model->scenario = 'answer';
        $model->trigger($model::EVENT_BEFORE_UPDATE);
        $model->load(Yii::$app->request->post());
        $model->prepareSpentTime();
        $model->prepareTopic();
        if ($model->validate() && $this->_ticketChange($model->getAttributes(), 'Answer', false)) {
            return $this->redirect(['view', 'id' => $model->id]);
        }
        throw new \LogicException('An error has occurred');
    }

    /**
     * @param $id
     * @return bool|\yii\web\Response
     */
    public function actionSubscribe($id)
    {
        if (!in_array($this->action->id, array_keys($this->_subscribeAction)))
            return false;
        $options[$id] = [
            'id' => $id,
            $this->_subscribeAction[$this->action->id] => \Yii::$app->user->identity->username
        ];
        if ($this->_ticketChange($options))
            \Yii::$app->getSession()->setFlash('success', \Yii::t('app', 'You have successfully subscribed!'));
        else
            \Yii::$app->getSession()->setFlash('error', \Yii::t('app', 'Some error occurred. You have not been subscribed!'));
        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * @param $id
     * @return bool|\yii\web\Response
     */
    public function actionUnsubscribe($id)
    {
        if (!in_array($this->action->id, array_keys($this->_subscribeAction)))
            return false;
        $options[$id] = [
            'id' => $id,
            $this->_subscribeAction[$this->action->id] => \Yii::$app->user->identity->username
        ];
        if ($this->_ticketChange($options))
            \Yii::$app->getSession()->setFlash('success', \Yii::t('app', 'You have successfully subscribed!'));
        else
            \Yii::$app->getSession()->setFlash('error', \Yii::t('app', 'Some error occurred. You have not been subscribed!'));
        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * @param $id
     * @return \yii\web\Response
     */
    public function actionClose($id)
    {
        if ($this->_ticketChangeState($id, $this->action->id))
            \Yii::$app->getSession()->setFlash('success', \Yii::t('app', 'The ticket has been closed!'));
        else
            \Yii::$app->getSession()->setFlash('error', \Yii::t('app', 'Some error occurred. The ticket has not been closed.'));
        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * @param $id
     * @return \yii\web\Response
     */
    public function actionOpen($id)
    {
        if ($this->_ticketChangeState($id, $this->action->id))
            \Yii::$app->getSession()->setFlash('success', \Yii::t('app', 'The ticket has been opened!'));
        else
            \Yii::$app->getSession()->setFlash('error', \Yii::t('app', 'Some error occurred! The ticket has not been opened.'));
        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * @param $id
     * @param $action
     * @return bool
     */
    private function _ticketChangeState($id, $action)
    {
        $options[$id] = ['id' => $id, 'state' => $action];
        try {
            Thread::perform(ucfirst($action), $options, true);
        } catch (HiResException $e) {
            return false;
        }
        return true;
    }

    /**
     * @return string
     */
    public function actionSettings()
    {
        $model = new TicketSettings();
        $request = Yii::$app->request;
        if ($request->isAjax && $model->load($request->post()) && $model->validate()) {
            $model->setFormData();
            \Yii::$app->getSession()->setFlash('success', \Yii::t('app', 'Ticket settings saved!'));
        } else {
            $model->getFormData();
        }

        return $this->render('settings', [
            'model' => $model,
        ]);
    }

    /**
     * @param $id
     * @return \yii\web\Response
     */
    public function actionPriorityUp($id)
    {
        $options[$id] = ['id' => $id, 'priority' => 'high'];
        if ($this->_ticketChange($options))
            \Yii::$app->getSession()->setFlash('success', \Yii::t('app', 'Priority has been changed to high!'));
        else
            \Yii::$app->getSession()->setFlash('error', \Yii::t('app', 'Some error occurred! Priority has not been changed to high.'));
        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * @param $id
     * @return \yii\web\Response
     */
    public function actionPriorityDown($id)
    {
        $options[$id] = ['id' => $id, 'priority' => 'medium'];
        if ($this->_ticketChange($options))
            \Yii::$app->getSession()->setFlash('success', \Yii::t('app', 'Priority has been changed to medium!'));
        else
            \Yii::$app->getSession()->setFlash('error', \Yii::t('app', 'Something goes wrong!'));
        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * Numerous ticket changes in one method, like BladeRoot did :)
     * @param array $options
     * @param string $apiCall
     * @param bool $bulk
     * @return bool
     */
    private function _ticketChange($options = [], $apiCall = 'Answer', $bulk = true)
    {
        try {
            Thread::perform($apiCall, $options, $bulk);
        } catch (HiResException $e) {
            return false;
        }
        return true;
    }

    /**
     * @return string
     * @throws HiResException
     */
    public function actionGetQuotedAnswer()
    {
        $request = Yii::$app->request;
        if ($request->isAjax) {
            $id = $request->post('id');
            if ($id != null) {
                $answer = Thread::perform('GetAnswer', ['id' => $id]);
                if (isset($answer['message'])) {
                    return '> ' . str_replace("\n", "\n> ", $answer['message']);
                }
            }
        }
        Yii::$app->end();
    }

    /**
     * @return mixed|string
     */
    public function actionPreview()
    {
        $request = Yii::$app->request;
        if ($request->isAjax) {
            $text = $request->post('text');
            if ($text) {
                return Thread::parseMessage($text);
            }
        }
        Yii::$app->end();
    }

    /**
     * @param $id
     * @param $object_id
     * @return array|bool
     */
    public function actionFileView($id, $object_id)
    {
        return File::renderFile($id, $object_id, 'thread', true);
    }
}
