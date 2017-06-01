<?php

namespace ArvGoogleRemarketing\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs;
use Enlight_Controller_Request_Request;
use Enlight_View_Default;
use Exception;

class Frontend implements SubscriberInterface
{
    private $config;
    /* @var Enlight_Controller_Request_Request */
    private $request;
    /* @var Enlight_View_Default */
    private $view;

    /**
     * Frontend constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        $this->config = Shopware()->Container()->get('shopware.plugin.config_reader')->getByPluginName('ArvGoogleRemarketing');
    }

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend' => 'onPostDispatch',
        ];
    }

    /**
     * Event listener method
     *
     * @param Enlight_Controller_ActionEventArgs $args
     */
    public function onPostDispatch(Enlight_Controller_ActionEventArgs $args)
    {
        $this->request = $args->getSubject()->Request();
        $this->view = $args->getSubject()->View();

        if (empty($this->config['CONVERSION_ID']) || $this->request->isXmlHttpRequest()) {
            return;
        }
        $this->setView();
    }

    protected function getProductString($products)
    {
        if (!empty($products)) {
            return "['" . implode("','", $products) . "']";
        }

        return "''";
    }

    /**
     * @return string
     */
    private function getProdIdField()
    {
        $sArticle = $this->view->getAssign('sArticle');
        $sArticles = $this->view->getAssign('sArticles');
        $sBasket = $this->view->getAssign('sBasket');
        if (empty($this->config['articleField'])) {
            $this->config['articleField'] = 'articleID';
        }

        if (!empty($sArticle) && !empty($sArticle[$this->config['articleField']])) {
            return "'" . $sArticle[$this->config['articleField']] . "'";
        }

        if (!empty($sArticles)) {
            $products = [];
            foreach ($sArticles as $article) {
                if (!empty($article[$this->config['articleField']])) {
                    $products[] = $article[$this->config['articleField']];
                }
            }

            return $this->getProductString($products);
        }

        if (!empty($sBasket['content'])) {
            $products = [];

            foreach ($sBasket['content'] as $article) {
                if (0 == $article['modus'] && !empty($article[$this->config['articleField']])) {
                    $products[] = $article[$this->config['articleField']];
                }
            }

            return $this->getProductString($products);
        }

        return "''";
    }

    /**
     * @return string
     */
    private function getPageTypeField()
    {
        $controller = $this->request->getControllerName();
        $action = $this->request->getActionName();

        switch ($controller) {
            case 'index' && $action === 'index':
                return 'home';
                break;
            case 'search':
                return 'searchresults';
                break;
            case 'listing':
                return 'category';
                break;
            case 'detail':
                return 'product';
                break;
            case 'checkout' && ($action === 'confirm' || $action === 'cart'):
                return 'cart';
                break;
            case 'checkout' && $action === 'finish':
                return 'purchase';
                break;
            default:
                return 'other';
                break;
        }
    }

    /**
     * @return string
     */
    private function getTotalValueField()
    {
        $controller = $this->request->getControllerName();
        $action = $this->request->getActionName();
        $totalVal = 0;

        if ($controller === 'checkout' && ($action === 'confirm' || $action === 'cart' || $action === 'finish')) {
            $sAmount = $this->view->getAssign('sAmount');
            $sAmountWithTax = $this->view->getAssign('sAmountWithTax');
            $sUserData = $this->view->getAssign('sUserData');

            if ($sAmountWithTax && $sUserData['additional']['charge_vat']) {
                $totalVal = $sAmountWithTax;
            } else {
                $totalVal = $sAmount;
            }
        } elseif ($controller === 'detail') {
            $sArticle = $this->view->getAssign('sArticle');
            $totalVal = $sArticle['price_numeric'];
        }

        if (empty($totalVal)) {
            $totalVal = 0;
        }

        return round($totalVal, 2);
    }

    private function setView()
    {
        $this->view->addTemplateDir(__DIR__ . '/../Views/Common');

        $version = Shopware()->Shop()->getTemplate()->getVersion();
        if ($version >= 3) {
            $this->view->addTemplateDir(__DIR__ . '/../Views/Responsive');
        } else {
            $this->view->addTemplateDir(__DIR__ . '/../Views/Emotion');
            $this->view->extendsTemplate('frontend/index/index_google.tpl');
        }

        $this->view->assign('ARV_GR_ECOM_PRODID', $this->getProdIdField($this->view, $this->config['ARTICLE_FIELD']));
        $this->view->assign('ARV_GR_ECOM_PAGETYPE', $this->getPageTypeField());
        $this->view->assign('ARV_GR_ECOM_TOTALVALUE', $this->getTotalValueField());

        $this->view->assign('ARV_GR_CONVERSION_ID', $this->config['CONVERSION_ID']);
    }
}
