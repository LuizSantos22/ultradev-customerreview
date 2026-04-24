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
class UltraDev_CustomerReview_Model_Observer
{
    /**
     * Flush block cache when configuration is saved
     *
     * @return void
     */
    public function flushCache(): void
    {
        Mage::app()->getCache()->clean(
            Zend_Cache::CLEANING_MODE_MATCHING_TAG,
            ['ultradev_customerreview']
        );
    }
}
