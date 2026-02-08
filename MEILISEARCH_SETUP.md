# Meilisearch setup (new key/link)

## 1. Update environment

In your **`.env`** file set:

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=https://your-new-instance.meilisearch.io
MEILISEARCH_KEY=your_new_master_key
```

Use your new Meilisearch host URL and API key. Then clear config cache:

```bash
php artisan config:clear
php artisan config:cache
```

## 2. Sync index settings and re-import documents

These commands talk to the **new** Meilisearch (using the env above):

**Sync index settings** (creates/updates the `documents` index and filterable/sortable/embedders from `config/scout.php`):

```bash
php artisan scout:sync-index-settings
```

**Import all documents** from your database into Meilisearch (only models that are searchable and pass `shouldBeSearchable()`):

```bash
php artisan scout:import "App\Models\Document"
```

This pushes your saved documents (and their embeddings if stored) into the new Meilisearch. No database “upload” is needed; your app DB stays the source of truth and Scout copies the searchable data into Meilisearch.

## 3. Optional: flush then re-import

If the new instance already had an old `documents` index and you want a clean state:

```bash
php artisan scout:flush "App\Models\Document"
php artisan scout:sync-index-settings
php artisan scout:import "App\Models\Document"
```

## Summary

| Step | Command |
|------|---------|
| 1. Set new host/key in `.env` | `MEILISEARCH_HOST`, `MEILISEARCH_KEY`, `SCOUT_DRIVER=meilisearch` |
| 2. Clear config | `php artisan config:clear` then `php artisan config:cache` |
| 3. Sync index settings | `php artisan scout:sync-index-settings` |
| 4. Upload DB data into Meilisearch | `php artisan scout:import "App\Models\Document"` |

After this, your app uses the new Meilisearch key and link, and the search index is filled from your saved database.
