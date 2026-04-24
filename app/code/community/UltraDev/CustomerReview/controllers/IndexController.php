<?php
/**
 * UltraDev_CustomerReview
 *
 * @category  UltraDev
 * @package   UltraDev_CustomerReview
 * @author    UltraDev
 * @license   MIT
 * @link      https://github.com/LuizSantos22/ultradev-customerreview
 */
class UltraDev_CustomerReview_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(): void
    {
        $this->loadLayout();

        $storeName = Mage::helper('ultradev_customerreview')->getStoreName();

        /** @var Mage_Page_Block_Html_Head $head */
        $head = $this->getLayout()->getBlock('head');
        if ($head) {
            $head->setTitle($storeName . ' é um site confiável?');
            $head->setDescription(
                'Veja avaliações reais de clientes da loja ' . $storeName . '. '
                . 'Opiniões de quem já comprou.'
            );
        }

        $this->renderLayout();
    }
}
