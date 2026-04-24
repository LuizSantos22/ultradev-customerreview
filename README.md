# UltraDev_CustomerReview

OpenMage 20.x module that creates a `/siteconfiavel` page aggregating
all approved store reviews, displaying:

- Global average rating with animated SVG circle
- Star distribution (1-5) with clickable filter
- Paginated review list with individual rating
- Schema.org markup (Store + Review) for SEO
- 100% compatible with Ultimo 1.19.x

## Requirements

- OpenMage >= 20.0.0
- PHP >= 8.1
- Ultimo theme (Font Awesome and Bootstrap grid already included)
- Native `Mage_Review` module enabled
- Ratings configured in **Catalog → Reviews and Ratings → Manage Ratings**

## Installation

### Option 1 — Manual

1. Copy the files following the repository structure into your OpenMage root
2. Clear cache: **System → Cache Management → Flush All**
3. Visit `https://yourstore.com/siteconfiavel`

### Option 2 — modman

```bash
modman clone https://github.com/LuizSantos22/ultradev-customerreview
```

### Option 3 — Composer

Add the repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/LuizSantos22/ultradev-customerreview"
        }
    ]
}
```

Then require the package:

```bash
composer require ultradev/customerreview
```

Or add directly to `require` in your `composer.json`:

```json
{
    "require": {
        "ultradev/customerreview": "^0.1"
    }
}
```

After installing via Composer, clear the cache:

```bash
php -r "require 'app/Mage.php'; Mage::app()->getCacheInstance()->flush();"
```

Or via admin: **System → Cache Management → Flush All**

## Configuration

No configuration needed. The module reads directly from the native
`Mage_Review` and `Mage_Rating` tables.

To control which ratings appear, go to:
**Catalog → Reviews and Ratings → Manage Ratings**
and set the ratings you want as **Visible**.

## How it works

- No database tables created — reads 100% from native `Mage_Review`
- Aggregation query uses a single SQL JOIN, zero N+1 queries
- `addRateVotes()` loads all votes in one query per page load
- SVG circle uses `stroke-dashoffset` calculated server-side (no JS required for rendering)

## URL

The default frontend URL is `/siteconfiavel`.  
To change it, edit `<frontName>` in:
