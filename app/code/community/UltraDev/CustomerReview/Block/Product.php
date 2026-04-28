<?php
class UltraDev_CustomerReview_Block_Product extends Mage_Core_Block_Template
{
    const REVIEWS_PER_PAGE = 10;

    protected ?int   $_productId  = null;
    protected ?int   $_storeId    = null;
    protected ?array $_aggregated = null;
    protected ?array $_reviews    = null;
    protected ?int   $_lastPage   = null;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ultradev/customerreview/product.phtml');

        $product          = Mage::registry('current_product');
        $this->_productId = $product ? (int) $product->getId() : null;

        $helper           = $this->_getHelper();
        $this->_storeId   = $helper->isStoreScopeEnabled()
            ? (int) Mage::app()->getStore()->getId()
            : null;

        $this->addData([
            'cache_lifetime' => 3600,
            'cache_tags'     => ['ultradev_customerreview', 'review', 'catalog_product'],
            'cache_key'      => $this->getCacheKey(),
        ]);
    }

    public function getCacheKeyInfo()
    {
        return [
            'ultradev_customerreview_product',
            $this->_productId ?? 'none',
            $this->_storeId   ?? 'all',
            $this->getRequest()->getParam('rating', 'all'),
            $this->getRequest()->getParam('p', 1),
        ];
    }

    public function getCacheKey(): string
    {
        return implode('_', [
            'ultradev_customerreview_product',
            $this->_productId ?? 'none',
            $this->_storeId   ?? 'all',
            'r' . $this->getRequest()->getParam('rating', 'all'),
            'p' . $this->getRequest()->getParam('p', 1),
        ]);
    }

    public function getCurrentProduct(): ?Mage_Catalog_Model_Product
    {
        return Mage::registry('current_product');
    }

    public function getProductName(): string
    {
        $p = $this->getCurrentProduct();
        return $p ? (string) $p->getName() : '';
    }

    // -------------------------------
    // Rating filter
    // -------------------------------

    public function getCurrentRatingFilter(): string
    {
        return (string) $this->getRequest()->getParam('rating', '');
    }

    public function getRatingFilterUrl(int $stars): string
    {
        $product = $this->getCurrentProduct();
        if (!$product) return '#';

        return $product->getProductUrl() . '?rating=' . $stars . '#ultradev-customerreview';
    }

    public function getClearFilterUrl(): string
    {
        $product = $this->getCurrentProduct();
        if (!$product) return '#';

        return $product->getProductUrl() . '#ultradev-customerreview';
    }

    // -------------------------------
    // Aggregated data
    // -------------------------------

    public function getAggregatedData(): array
    {
        if ($this->_aggregated !== null) return $this->_aggregated;
        if (!$this->_productId) return $this->_aggregated = $this->_emptyAgg();

        $resource    = Mage::getSingleton('core/resource');
        $read        = $resource->getConnection('core_read');
        $reviewTable = $resource->getTableName('review/review');
        $storeTable  = $resource->getTableName('review/review_store');
        $voteTable   = $resource->getTableName('rating/rating_option_vote');

        $sql = "
            SELECT
                COALESCE(AVG(v.percent), 0) AS avg_percent,
                COUNT(DISTINCT r.review_id) AS total_reviews,
                SUM(CASE WHEN ROUND(v.percent / 20) = 5 THEN 1 ELSE 0 END) AS star5,
                SUM(CASE WHEN ROUND(v.percent / 20) = 4 THEN 1 ELSE 0 END) AS star4,
                SUM(CASE WHEN ROUND(v.percent / 20) = 3 THEN 1 ELSE 0 END) AS star3,
                SUM(CASE WHEN ROUND(v.percent / 20) = 2 THEN 1 ELSE 0 END) AS star2,
                SUM(CASE WHEN ROUND(v.percent / 20) = 1 THEN 1 ELSE 0 END) AS star1
            FROM {$reviewTable} r
            JOIN {$voteTable} v ON v.review_id = r.review_id
        ";

        $bind = [
            ':status'     => Mage_Review_Model_Review::STATUS_APPROVED,
            ':product_id' => $this->_productId,
        ];

        if ($this->_storeId !== null) {
            $sql .= " JOIN {$storeTable} rs ON rs.review_id = r.review_id AND rs.store_id = :store_id ";
            $bind[':store_id'] = $this->_storeId;
        }

        $sql .= " WHERE r.status_id = :status AND r.entity_pk_value = :product_id ";

        $row = $read->fetchRow($sql, $bind);

        $helper     = $this->_getHelper();
        $avgPercent = (float) ($row['avg_percent'] ?? 0);
        $total      = (int) ($row['total_reviews'] ?? 0);

        $dist = [5=>0,4=>0,3=>0,2=>0,1=>0];
        $votes = array_sum(array_map(fn($s)=> (int)($row['star'.$s] ?? 0), [1,2,3,4,5]));

        if ($votes > 0) {
            foreach ($dist as $s => $_) {
                $dist[$s] = round((int)($row['star'.$s] ?? 0) / $votes * 100, 2);
            }
        }

        return $this->_aggregated = [
            'avg_percent'  => round($avgPercent,1),
            'avg_stars'    => $helper->percentToStars($avgPercent),
            'total'        => $total,
            'distribution' => $dist,
            'dash_offset'  => $helper->getDashOffset($avgPercent),
        ];
    }

    protected function _emptyAgg(): array
    {
        return [
            'avg_percent'=>0,'avg_stars'=>0,'total'=>0,
            'distribution'=>[5=>0,4=>0,3=>0,2=>0,1=>0],
            'dash_offset'=>251.327
        ];
    }

    // -------------------------------
    // Reviews
    // -------------------------------

    public function getReviews(): array
    {
        if ($this->_reviews !== null) return $this->_reviews;
        if (!$this->_productId) return $this->_reviews = [];

        $page   = max(1,(int)$this->getRequest()->getParam('p',1));
        $filter = $this->getCurrentRatingFilter();

        $resource = Mage::getSingleton('core/resource');
        $read     = $resource->getConnection('core_read');

        $reviewTable = $resource->getTableName('review/review');
        $detailTable = $resource->getTableName('review/review_detail');
        $storeTable  = $resource->getTableName('review/review_store');
        $voteTable   = $resource->getTableName('rating/rating_option_vote');
        $metaTable   = $resource->getTableName('ultradev_customerreview_meta');

        $limit  = self::REVIEWS_PER_PAGE;
        $offset = ($page-1)*$limit;

        $sql = "
            SELECT r.review_id, rd.nickname, rd.title, rd.detail, r.created_at,
                   AVG(v.percent) AS avg_percent,
                   m.reviewer_city, m.reviewer_region
            FROM {$reviewTable} r
            JOIN {$detailTable} rd ON rd.review_id = r.review_id
            JOIN {$voteTable} v ON v.review_id = r.review_id
            LEFT JOIN {$metaTable} m ON m.review_id = r.review_id
        ";

        $bind = [
            ':status'=>Mage_Review_Model_Review::STATUS_APPROVED,
            ':product_id'=>$this->_productId
        ];

        if ($this->_storeId !== null) {
            $sql .= " JOIN {$storeTable} rs ON rs.review_id=r.review_id AND rs.store_id=:store_id ";
            $bind[':store_id']=$this->_storeId;
        }

        $sql .= " WHERE r.status_id=:status AND r.entity_pk_value=:product_id ";
        $sql .= " GROUP BY r.review_id, rd.nickname, rd.title, rd.detail, r.created_at, m.reviewer_city, m.reviewer_region ";

        if ($filter !== '') {
            $sql .= " HAVING ROUND(AVG(v.percent)/20) = " . (int)$filter;
        }

        $sql .= " ORDER BY r.created_at DESC LIMIT {$limit} OFFSET {$offset}";

        $rows = $read->fetchAll($sql,$bind);

        $helper = $this->_getHelper();
        $items=[];

        foreach($rows as $row){
            $avg=(float)$row['avg_percent'];
            $stars=$helper->percentToStars($avg);

            $items[]=[
                'review_id'=>(int)$row['review_id'],
                'nickname'=>$row['nickname'],
                'title'=>$row['title'],
                'detail'=>$row['detail'],
                'created_at'=>$row['created_at'],
                'avg_percent'=>round($avg,1),
                'avg_stars'=>$stars,
                'star_int'=>(int)round($stars),
                'dash_offset'=>$helper->getDashOffset($avg),
                'reviewer_city'=>$row['reviewer_city'] ?? '',
                'reviewer_region'=>$row['reviewer_region'] ?? ''
            ];
        }

        return $this->_reviews=$items;
    }

    // -------------------------------
    // Pagination
    // -------------------------------

    public function getCurrentPage(): int
    {
        return max(1,(int)$this->getRequest()->getParam('p',1));
    }

    public function getLastPage(): int
    {
        if ($this->_lastPage !== null) return $this->_lastPage;
        if (!$this->_productId) return 1;

        $filter = $this->getCurrentRatingFilter();

        $resource = Mage::getSingleton('core/resource');
        $read     = $resource->getConnection('core_read');

        $reviewTable = $resource->getTableName('review/review');
        $storeTable  = $resource->getTableName('review/review_store');
        $voteTable   = $resource->getTableName('rating/rating_option_vote');

        $bind = [
            ':status'=>Mage_Review_Model_Review::STATUS_APPROVED,
            ':product_id'=>$this->_productId
        ];

        if ($filter !== '') {
            $inner = "
                SELECT r.review_id
                FROM {$reviewTable} r
                JOIN {$voteTable} v ON v.review_id=r.review_id
            ";

            if ($this->_storeId !== null) {
                $inner .= " JOIN {$storeTable} rs ON rs.review_id=r.review_id AND rs.store_id=:store_id ";
                $bind[':store_id']=$this->_storeId;
            }

            $inner .= " WHERE r.status_id=:status AND r.entity_pk_value=:product_id ";
            $inner .= " GROUP BY r.review_id ";
            $inner .= " HAVING ROUND(AVG(v.percent)/20)=".(int)$filter;

            $sql="SELECT COUNT(*) FROM ({$inner}) t";
        } else {
            $sql="SELECT COUNT(DISTINCT r.review_id) FROM {$reviewTable} r";

            if ($this->_storeId !== null) {
                $sql.=" JOIN {$storeTable} rs ON rs.review_id=r.review_id AND rs.store_id=:store_id";
                $bind[':store_id']=$this->_storeId;
            }

            $sql.=" WHERE r.status_id=:status AND r.entity_pk_value=:product_id";
        }

        $total=(int)$read->fetchOne($sql,$bind);

        return $this->_lastPage=max(1,ceil($total/self::REVIEWS_PER_PAGE));
    }

    public function getPageUrl(int $page): string
    {
        $product = $this->getCurrentProduct();
        if (!$product) return '#';

        $params = ['p'=>$page];
        $rating = $this->getCurrentRatingFilter();

        if ($rating !== '') {
            $params['rating']=$rating;
        }

        return $product->getProductUrl() . '?' . http_build_query($params) . '#ultradev-customerreview';
    }

    // -------------------------------
    // Form
    // -------------------------------

    public function getReviewFormUrl(): string
    {
        return Mage::getUrl('review/product/post',['id'=>$this->_productId]);
    }

    public function getFormKey(): string
    {
        return Mage::getSingleton('core/session')->getFormKey();
    }

    // -------------------------------
    // Schema
    // -------------------------------

    public function getProductSchemaJson(): string
    {
        $agg=$this->getAggregatedData();
        $prod=$this->getCurrentProduct();
        if(!$prod||$agg['total']===0) return '';

        return json_encode([
            '@context'=>'http://schema.org/',
            '@type'=>'Product',
            'name'=>$prod->getName(),
            'aggregateRating'=>[
                '@type'=>'AggregateRating',
                'ratingValue'=>(string)$agg['avg_stars'],
                'bestRating'=>'5',
                'ratingCount'=>(string)$agg['total']
            ]
        ],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    public function getReviewSchemaJson(array $item): string
    {
        $prod=$this->getCurrentProduct();

        return json_encode([
            '@context'=>'http://schema.org/',
            '@type'=>'Review',
            'itemReviewed'=>[
                '@type'=>'Product',
                'name'=>$prod?$prod->getName():''
            ],
            'reviewRating'=>[
                '@type'=>'Rating',
                'ratingValue'=>(string)$item['avg_stars']
            ],
            'name'=>$item['title'],
            'author'=>['@type'=>'Person','name'=>$item['nickname']],
            'reviewBody'=>$item['detail']
        ],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    protected function _getHelper(): UltraDev_CustomerReview_Helper_Data
    {
        return Mage::helper('ultradev_customerreview');
    }
}