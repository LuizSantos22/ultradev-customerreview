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
    /** Circunferência do path SVG: 2 * π * r(40) */
    const SVG_CIRCUMFERENCE = 251.327;

    /**
     * Converte percentual 0-100 para stroke-dashoffset do SVG
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
     * Converte percentual 0-100 para escala 0.0-5.0
     *
     * @param  float $percent
     * @return float
     */
    public function percentToStars(float $percent): float
    {
        return round($percent / 20, 1);
    }

    /**
     * Formata data para dd/mm/yyyy
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
     * Nome do frontend da store atual
     *
     * @return string
     */
    public function getStoreName(): string
    {
        return Mage::app()->getStore()->getFrontendName();
    }
}
