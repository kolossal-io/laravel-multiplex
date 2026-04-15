# Upgrade Multiplex

## 1.x to 2.x

### SQL Window Functions

Version 2.x replaces aggregate functions with SQL Window Functions. Your database must support Window Functions for this to work, such as:

- MySQL **8.0+**
- MariaDB **10.2+** (with limitations), best **10.4+**
- PostgreSQL **9.0+**
- SQLite **3.25+**
- SQL Server **2012+**

### Update your Index

It is recommended to remove any existing indexes on the `meta` table and create a new index optimized for the updated queries in version 2.x. For example, you can drop old indexes and add a new composite index:

```sql
CREATE INDEX meta_lookup_index ON meta (
    metable_type,
    metable_id,
    `key`,
    published_at,
    id
);
```

If you are installing to a new project, just use the migration.
