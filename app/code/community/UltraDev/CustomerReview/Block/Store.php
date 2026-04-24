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
class UltraDev_CustomerReview_Block_Store extends Mage_Core_Block_Template
{
    const REVIEWS_PER_PAGE = 20;

    protected int $_storeId;
    protected ?array $_aggregated = null;
    protected ?array $_reviews    = null;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ultradev/customerreview/store.phtml');
        $this->_storeId = (int) Mage::app()->getStore()->getId();
    }

    // ------------------------------------------------------------------
    // Dados agregados — única query SQL, zero N+1
    // ------------------------------------------------------------------

    /**
     * Retorna dados agregados de todos os reviews aprovados da store:
     * avg_percent, avg_stars, total, distribution[5..1], dash_offset
     *
     * @return array
     */
    public function getAggregatedData(): array
    {
        if ($this->_aggregated !== null) {
            return $this->_aggregated;
        }

        $resource    = Mage::getSingleton('core/resource');
        $read        = $resource->getConnection('core_read');
        $reviewTable = $resource->getTableName('review/review');
        $storeTable  = $resource->getTableName('review/review_store');
        $voteTable   = $resource->getTableName('rating/rating_option_vote');

        $sql = "
            SELECT
                COALESCE(AVG(v.percent), 0)                                     AS avg_percent,
                COUNT(DISTINCT r.review_id)                                      AS total_reviews,
                SUM(CASE WHEN ROUND(v.percent / 20) = 5 THEN 1 ELSE 0 END)     AS star5,
                SUM(CASE WHEN ROUND(v.percent / 20) = 4 THEN 1 ELSE 0 END)     AS star4,
                SUM(CASE WHEN ROUND(v.percent / 20) = 3 THEN 1 ELSE 0 END)     AS star3,
                SUM(CASE WHEN ROUND(v.percent / 20) = 2 THEN 1 ELSE 0 END)     AS star2,
                SUM(CASE WHEN ROUND(v.percent / 20) = 1 THEN 1 ELSE 0 END)     AS star1
            FROM   {$reviewTable}  r
            JOIN   {$storeTable}   rs ON rs.review_id = r.review_id
                                      AND rs.store_id  = :store_id
            JOIN   {$voteTable}    v  ON v.review_id  = r.review_id
                                      AND v.store_id   = :store_id
            WHERE  r.status_id = :status_approved
        ";

        $row = $read->fetchRow($sql, [
            ':store_id'        => $this->_storeId,
            ':status_approved' => Mage_Review_Model_Review::STATUS_APPROVED,
        ]);

        $helper     = $this->_getHelper();
        $avgPercent = (float) ($row['avg_percent'] ?? 0);
        $total      = (int)   ($row['total_reviews'] ?? 0);

        $totalVotes = (int) (($row['star5'] ?? 0)
            + ($row['star4'] ?? 0)
            + ($row['star3'] ?? 0)
            + ($row['star2'] ?? 0)
            + ($row['star1'] ?? 0));

        $dist = [5 => 0.0, 4 => 0.0, 3 => 0.0, 2 => 0.0, 1 => 0.0];
        if ($totalVotes > 0) {
            foreach (array_keys($dist) as $star) {
                $dist[$star] = round((int)($row['star' . $star] ?? 0) / $totalVotes * 100, 2);
            }
        }

        $this->_aggregated = [
            'avg_percent'  => round($avgPercent, 1),
            'avg_stars'    => $helper->percentToStars($avgPercent),
            'total'        => $total,
            'distribution' => $dist,
            'dash_offset'  => $helper->getDashOffset($avgPercent),
        ];

        return $this->_aggregated;
    }

    // ------------------------------------------------------------------
    // Lista paginada — addRateVotes() evita N+1 nos votos
    // ------------------------------------------------------------------

    /**
     * Retorna array de reviews enriquecidos para o template
     *
     * @return array
     */
    public function getReviews(): array
    {
        if ($this->_reviews !== null) {
            return $this->_reviews;
        }

        $page   = max(1, (int) $this->getRequest()->getParam('p', 1));
        $filter = $this->getRequest()->getParam('rating');
        $helper = $this->_getHelper();

        /** @var Mage_Review_Model_Resource_Review_Collection $collection */
        $collection = Mage::getModel('review/review')
            ->getCollection()
            ->addStoreFilter($this->_storeId)
            ->addStatusFilter(Mage_Review_Model_Review::STATUS_APPROVED)
            ->setDateOrder()
            ->addRateVotes();

        $collection->getSelect()->order('main_table.review_id DESC');
        $collection->setPageSize(self::REVIEWS_PER_PAGE);
        $collection->setCurPage($page);

        $items = [];

        foreach ($collection as $review) {
            $votes      = $review->getRatingVotes();
            $sumPercent = 0.0;
            $countVotes = 0;

            foreach ($votes as $vote) {
                $sumPercent += (float) $vote->getPercent();
                $countVotes++;
            }

            $avgPercent = $countVotes > 0 ? $sumPercent / $countVotes : 0.0;
            $avgStars   = $helper->percentToStars($avgPercent);
            $starInt    = (int) round($avgStars);

            // filtro por estrelas (inteiro)
            if ($filter !== null && $filter !== '' && $starInt !== (int) $filter) {
                continue;
            }

            $items[] = [
                'review_id'   => (int) $review->getId(),
                'nickname'    => (string) $review->getNickname(),
                'title'       => (string) $review->getTitle(),
                'detail'      => (string) $review->getDetail(),
                'created_at'  => (string) $review->getCreatedAt(),
                'avg_percent' => round($avgPercent, 1),
                'avg_stars'   => $avgStars,
                'star_int'    => $starInt,
                'dash_offset' => $helper->getDashOffset($avgPercent),
            ];
        }

        $this->_reviews = $items;
        return $this->_reviews;
    }

    // ------------------------------------------------------------------
    // Paginação
    // ------------------------------------------------------------------

    public function getCurrentPage(): int
    {
        return max(1, (int) $this->getRequest()->getParam('p', 1));
    }

    public function getLastPage(): int
    {
        $total = Mage::getModel('review/review')
            ->getCollection()
            ->addStoreFilter($this->_storeId)
            ->addStatusFilter(Mage_Review_Model_Review::STATUS_APPROVED)
            ->getSize();

        return max(1, (int) ceil($total / self::REVIEWS_PER_PAGE));
    }

    public function getPageUrl(int $page): string
    {
        $params = ['p' => $page];
        $rating = $this->getRequest()->getParam('rating');
        if ($rating !== null && $rating !== '') {
            $params['rating'] = $rating;
        }
        return $this->getUrl('siteconfiavel', $params);
    }

    public function getRatingFilterUrl(int $stars): string
    {
        return $this->getUrl('siteconfiavel', ['rating' => $stars, 'p' => 1]);
    }

    public function getClearFilterUrl(): string
    {
        return $this->getUrl('siteconfiavel');
    }

    public function getCurrentRatingFilter(): string
    {
        return (string) $this->getRequest()->getParam('rating', '');
    }

    // ------------------------------------------------------------------
    // Schema.org
    // ------------------------------------------------------------------

    public function getStoreSchemaJson(): string
    {
        $agg = $this->getAggregatedData();
        return json_encode([
            '@context'        => 'http://schema.org/',
            '@type'           => 'Store',
            'name'            => $this->getStoreName(),
            'description'     => $this->getStoreName() . ' super confiável',
            'aggregateRating' => [
                '@type'       => 'AggregateRating',
                'ratingValue' => (string) $agg['avg_stars'],
                'bestRating'  => '5',
                'ratingCount' => (string) $agg['total'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function getReviewSchemaJson(array $item): string
    {
        return json_encode([
            '@context'     => 'http://schema.org/',
            '@type'        => 'Review',
            'itemReviewed' => [
                '@type' => 'Store',
                'name'  => $this->getStoreName() . ' é um site confiável?',
            ],
            'reviewRating' => [
                '@type'       => 'Rating',
                'ratingValue' => (string) $item['avg_stars'],
            ],
            'name'         => $item['title'],
            'author'       => [
                '@type' => 'Person',
                'name'  => $item['nickname'],
            ],
            'reviewBody'   => $item['detail'],
            'publisher'    => [
                '@type' => 'Organization',
                'name'  => $this->getStoreName(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    public function getStoreName(): string
    {
        return $this->_getHelper()->getStoreName();
    }

    protected function _getHelper(): UltraDev_CustomerReview_Helper_Data
    {
        return Mage::helper('ultradev_customerreview');
    }
}
