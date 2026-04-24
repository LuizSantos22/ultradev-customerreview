# UltraDev_CustomerReview

OpenMage 20.x module that creates the `/siteconfiavel` page with
aggregation of all approved store reviews, displaying:

- Global average rating with animated SVG circle
- Star distribution (1-5) with clickable filter
- Paginated list of reviews with individual rating
- Schema.org (Store + Review) for SEO
- 100% compatible with Ultimo 1.19.x

## Requirements

- OpenMage >= 20.0.0
- PHP >= 8.1
- Ultimo theme (Font Awesome and Bootstrap grid already included)
- Native `Mage_Review` module enabled

## Manual Installation

1. Copy the files respecting the repository structure
2. Clear the cache: **System → Cache Management → Flush all**
3. Access `https://yourstore.com/siteconfiavel`

## Installation via modman

```bash
modman clone https://github.com/LuizSantos22/ultradev-customerreview

## Notes

- No database tables are created — reads 100% from native `Mage_Review`
- The aggregation query uses a single JOIN, no N+1
- The ratings used are those configured under **Catalog → Reviews and Ratings → Manage Ratings**

## License

MIT
