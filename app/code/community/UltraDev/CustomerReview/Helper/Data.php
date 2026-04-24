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
class UltraDev_CustomerReview_Helper_Data extends Mage_Core_Helper_Abstract
{
    /** Circumference for SVG circle with radius 40: 2 * π * 40 ≈ 251.3274 */
    const SVG_CIRCUMFERENCE = 251.327;

    /**
     * Check if reviews should be filtered by current store view
     *
     * @return bool
     */
    public function isStoreScopeEnabled(): bool
    {
        return (bool) Mage::getStoreConfigFlag('ultradev_customerreview/general/scope_store');
    }

    /**
     * Converts percentual 0-100 to stroke-dashoffset for SVG
     *
     * @param  float $percent
     * @return float
     */
    public function getDashOffset(float $percent): float
    {
        $percent = max(0.0, min(100.0, $percent));
        return round(self::SVG_CIRCUMFERENCE * (1 - $percent / 100), 4);
    }

    /**
     * Converts percentual 0-100 to star rating 0.0-5.0
     *
     * @param  float $percent
     * @return float
     */
    public function percentToStars(float $percent): float
    {
        return round($percent / 20, 1);
    }

    /**
     * Formats date to dd/mm/yyyy
     *
     * @param  string $date
     * @return string
     */
    public function formatDate(string $date): string
    {
        if (!$date) {
            return '';
        }
        return date('d/m/Y', strtotime($date));
    }

    /**
     * Returns current store frontend name
     *
     * @return string
     */
    public function getStoreName(): string
    {
        return Mage::app()->getStore()->getFrontendName();
    }
}
