<?php

use Doctrine\Common\Collections\ArrayCollection;
use Shopware\Components\Theme\LessDefinition;
use Shopware\Models\Category\Repository;

/**
 * Class Shopware_Plugins_Frontend_ArvGoogleRemarketing_Bootstrap
 */
class Shopware_Plugins_Frontend_ArvGoogleRemarketing_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * @return string
     */
    public function getVersion()
    {
        return '1.0.1';
    }

    /**
     * Get (nice) name for plugin manager list
     * @return string
     */
    public function getLabel()
    {
        return 'Google Remarketing';
    }

    /**
     * Get version tag of this plugin to display in manager
     * @return string
     */
    public function getInfo()
    {
        return array(
            'version' => $this->getVersion(),
            'autor' => 'arvatis media GmbH',
            'label' => $this->getLabel(),
            'source' => "Community",
            'description' => '',
            'license' => 'commercial',
            'copyright' => 'Copyright © 2014, arvatis media GmbH',
            'support' => '',
            'link' => 'http://www.arvatis.com/'
        );
    }

    /**
     * Install plugin method
     *
     * @return bool
     */
    public function install()
    {
        $this->subscribeEvents();
        $this->createForm();

        return true;
    }

    /**
     * Update plugin method
     *
     * @param string $version
     *
     * @return bool
     */
    public function update($version)
    {
        // Remove update zip if it exists
        $updateFile = dirname(__FILE__) . "/ArvGoogleRemarketing.zip";
        if (file_exists($updateFile)) {
            unlink($updateFile);
        }

        return true;
    }

    /**
     * Register Events
     */
    private function subscribeEvents()
    {
        $this->subscribeEvent('Enlight_Controller_Action_PostDispatch_Frontend', 'onPostDispatch');
    }

    /**
     * Create the Plugin Settings Form
     */
    public function createForm()
    {
        $form = $this->Form();

        /**
         * @var \Shopware\Models\Config\Form $parent
         */
        $parent = $this->Forms()->findOneBy(array('name' => 'Interface'));
        $form->setParent($parent);

        $form->setElement('text', 'CONVERSION_ID', array(
            'label' => 'Conversion ID / Tracking ID',
            'value' => null,
            'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP
        ));
    }

    /**
     * Event listener method
     *
     * @param Enlight_Controller_ActionEventArgs $args
     */
    public function onPostDispatch(Enlight_Controller_ActionEventArgs $args)
    {
        $request = $args->getSubject()->Request();
        $view = $args->getSubject()->View();

        if ($request->isXmlHttpRequest()) {
            return;
        }

        $config = $this->Config();

        if (empty($config->CONVERSION_ID)) {
            return;
        }

        $view->addTemplateDir(__DIR__ . '/Views/Common');

        $version = Shopware()->Shop()->getTemplate()->getVersion();
        if ($version >= 3) {
            $view->addTemplateDir(__DIR__ . '/Views/Responsive');
        } else {
            $view->addTemplateDir(__DIR__ . '/Views/Emotion');
            $view->extendsTemplate('frontend/checkout/index_google.tpl');
        }

        $view->assign('ARV_GR_ECOM_PRODID', $this->getProdIdField($request, $view));
        $view->assign('ARV_GR_ECOM_PAGETYPE', $this->getPageTypeField($request, $view));
        $view->assign('ARV_GR_ECOM_TOTALVALUE', $this->getTotalValueField($request, $view));

        $view->assign('ARV_GR_CONVERSION_ID', $config->CONVERSION_ID);
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    protected function getProductString($products)
    {
        if (!empty($products)) {
            return "['" . implode("','", $products) . "']";
        }

        return "''";
    }

    /**
     * @param Enlight_Controller_Request_Request $request
     * @param Enlight_View_Default $view
     *
     * @return string
     */
    private function getProdIdField(Enlight_Controller_Request_Request $request, Enlight_View_Default $view)
    {
        $sArticle = $view->getAssign('sArticle');
        $sArticles = $view->getAssign('sArticles');
        $sBasket = $view->getAssign('sBasket');

        if (!empty($sArticle) && !empty($sArticle['articleID'])) {
            return "'" . $sArticle['articleID'] . "'";
        } elseif (!empty($sArticles)) {
            $products = array();

            foreach ($sArticles as $article) {
                $products[] = $article['articleID'];
            }

            return $this->getProductString($products);
        } elseif (!empty($sBasket['content'])) {
            $products = array();

            foreach ($sBasket['content'] as $article) {
                if ($article['modus'] == 0 && $article['articleID'] > 0)
                    $products[] = $article['articleID'];
            }

            return $this->getProductString($products);
        }

        return "''";
    }

    /**
     * @param Enlight_Controller_Request_Request $request
     * @param Enlight_View_Default $view
     *
     * @return string
     */
    private function getPageTypeField($request, $view)
    {
        $controller = $request->getControllerName();
        $action = $request->getActionName();

        if ($controller == 'index' && $action == 'index') {
            return 'home';
        } elseif ($controller == 'search') {
            return 'searchresults';
        } elseif ($controller == 'listing') {
            return 'category';
        } elseif ($controller == 'detail') {
            return 'product';
        } elseif ($controller == 'checkout' && ($action == 'confirm' || $action == 'cart')) {
            return 'cart';
        } elseif ($controller == 'checkout' && $action == 'finish') {
            return 'purchase';
        } elseif ($controller == 'search') {
            return 'searchresults';
        }

        return 'other';
    }

    /**
     * @param Enlight_Controller_Request_Request $request
     * @param Enlight_View_Default $view
     *
     * @return string
     */
    private function getTotalValueField($request, $view)
    {
        $controller = $request->getControllerName();

        if ($controller == 'checkout') {
            $sAmount = $view->getAssign('sAmount');
            $sAmountWithTax = $view->getAssign('sAmountWithTax');
            $sUserData = $view->getAssign('sUserData');

            if ($sAmountWithTax && $sUserData['additional']['charge_vat']) {
                return $sAmountWithTax;
            } else {
                return $sAmount;
            }
        }

        return '';
    }
}
