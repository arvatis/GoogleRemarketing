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
        return '1.0.4';
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
            'copyright' => 'Copyright © 2015, arvatis media GmbH',
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

        if (version_compare($version, '1.0.3', '<')) {
            $this->createForm();
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

        $form->setElement('select', 'ARTICLE_FIELD', array(
            'label' => 'Welche Nummern sollen übertragen werden?',
            'store' => array(
                array('articleID', 'Interne Artikel-ID (DB: articleID)'),
                array('ordernumber', 'Artikelnummer (DB: ordernumber)'),
                array('ean', 'EAN (DB: ean)')
            ),
            'required' => true,
        ));

        $this->translateForm();
    }


    /**
     *
     */
    private function translateForm()
    {
        $translations = array(
            'en_GB' => array(
                'ARTICLE_FIELD' => array(
                    'label' => 'Which IDs should be used?'
                )
            )
        );

        $this->addFormTranslations($translations);
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
        $articleField = $config->ARTICLE_FIELD;

        $view->addTemplateDir(__DIR__ . '/Views/Common');

        $version = Shopware()->Shop()->getTemplate()->getVersion();
        if ($version >= 3) {
            $view->addTemplateDir(__DIR__ . '/Views/Responsive');
        } else {
            $view->addTemplateDir(__DIR__ . '/Views/Emotion');
            $view->extendsTemplate('frontend/index/index_google.tpl');
        }

        $view->assign('ARV_GR_ECOM_PRODID', $this->getProdIdField($request, $view, $articleField));
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
    private function getProdIdField(Enlight_Controller_Request_Request $request, Enlight_View_Default $view, $articleField)
    {
        $sArticle = $view->getAssign('sArticle');
        $sArticles = $view->getAssign('sArticles');
        $sBasket = $view->getAssign('sBasket');
        if (empty($articleField)) {
            $articleField = 'articleID';
        }

        if (!empty($sArticle) && !empty($sArticle[$articleField])) {
            return "'" . $sArticle[$articleField] . "'";
        } elseif (!empty($sArticles)) {
            $products = array();

            foreach ($sArticles as $article) {
                if(!empty($article[$articleField])) {
                    $products[] = $article[$articleField];
                }
            }

            return $this->getProductString($products);
        } elseif (!empty($sBasket['content'])) {
            $products = array();

            foreach ($sBasket['content'] as $article) {
                if ($article['modus'] == 0 && !empty($article[$articleField])){
                    $products[] = $article[$articleField];
                }
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
        $action = $request->getActionName();
        $totalVal = 0;

        if ($controller == 'checkout' && ($action == 'confirm' || $action == 'cart' || $action == 'finish')) {
            $sAmount = $view->getAssign('sAmount');
            $sAmountWithTax = $view->getAssign('sAmountWithTax');
            $sUserData = $view->getAssign('sUserData');

            if ($sAmountWithTax && $sUserData['additional']['charge_vat']) {
                $totalVal = $sAmountWithTax;
            } else {
                $totalVal = $sAmount;
            }
        } elseif ($controller == 'detail') {
            $sArticle = $view->getAssign('sArticle');
            $totalVal = $sArticle['price_numeric'];
        }

        if (empty($totalVal)) {
            $totalVal = 0;
        }

        return $totalVal;
    }
}
