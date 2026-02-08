# Document & Extraction Logging

All document upload and extraction steps write to **Laravel’s log** (`storage/logs/laravel.log`). Every message uses a clear prefix so you can filter and trace the full flow.

---

## Quick reference

| Grep for | What you get |
|----------|----------------|
| `[Documents]` | Upload, storage, and dispatch (main app + backdoor) |
| `[Extraction]` | Extraction job and service (path, OpenAI, result) |
| `[Extraction] result:` | **Main outcome** of extraction (success / empty / failed + reason) |
| `[Extraction] job_result:` | **Job outcome** (success / empty / failed / skipped + reason) |

---

## Finding the main reason something failed

1. **Filter by document id** (replace `19` with your document id):
   ```bash
   grep "document_id\":19" storage/logs/laravel.log
   ```
2. **Or filter by extraction only**:
   ```bash
   grep "\[Extraction\]" storage/logs/laravel.log
   ```
3. **Look for the last `result:` or `job_result:` line** in that flow. That line is the **main reason**:
   - `[Extraction] result: success (...)` → extraction worked.
   - `[Extraction] result: empty (empty_extracted_text)` → OpenAI returned no usable text (e.g. image-only PDF).
   - `[Extraction] result: failed (file_not_found)` → path wrong or file missing (check `full_path` / `disk_root` in the same or previous log line).
   - `[Extraction] result: failed (file_not_readable)` → permissions (queue worker user can’t read the file).
   - `[Extraction] job_result: empty (...)` → job finished but content was empty; check the last `[Extraction] result:` above it for the exact cause.

---

## Document upload flow (main app)

| Log message | Meaning |
|-------------|--------|
| `[Documents] upload_started` | Upload request received (file name, mime, size). |
| `[Documents] file_stored` | File saved to disk. Includes `stored_path`, `full_path`, `disk_root`. |
| `[Documents] extraction_job_dispatched` | Document created and extraction job queued (approved PDF). |
| `[Documents] upload_failed (file_storage_failed)` | `storeAs` failed or file missing after save. |
| `[Documents] upload_failed (file_not_found_before_dispatch)` | File not found at `stored_path` before dispatching job (path mismatch or missing file). |

---

## Backdoor upload flow

| Log message | Meaning |
|-------------|--------|
| `[Documents] backdoor_upload_started` | Backdoor upload started. |
| `[Documents] backdoor_file_stored` | File saved (path is `documents/...`, same as main app). |
| `[Documents] backdoor_embedding_generated` | Embedding generated from provided content. |
| `[Documents] backdoor_indexed` | Document indexed to Meilisearch. |
| `[Documents] backdoor_upload_failed (...)` | Storage or other backdoor step failed. |

---

## Extraction job flow (queue worker)

| Log message | Meaning |
|-------------|--------|
| `[Extraction] job_result: skipped (document_not_found)` | No document for the given id. |
| `[Extraction] job_result: skipped (document_not_approved)` | Document not approved; extraction not run. |
| `[Extraction] job_started` | Job started for this document. |
| `[Extraction] step: generating_embedding` | Content extracted; generating embedding. |
| `[Extraction] step: embedding_saved` | Embedding saved. |
| `[Extraction] step: document_updated` | Document updated with content and embedding. |
| `[Extraction] job_result: success (indexed)` | Document indexed to Meilisearch; flow completed successfully. |
| `[Extraction] job_result: empty (content_extraction_returned_empty)` | No content to index; see last `[Extraction] result:` for cause. |
| `[Extraction] job_result: failed (exception)` | Job threw an exception; see `error` and `trace` in the log. |
| `[Extraction] job_result: failed (meilisearch_index_error)` | Meilisearch index update failed. |

---

## Extraction service steps (inside the job)

| Log message | Meaning |
|-------------|--------|
| `[Extraction] step: start` | Extraction started for this document. |
| `[Extraction] result: skipped (mime_type_not_supported)` | File type not supported for extraction. |
| `[Extraction] step: path_resolved` | Path built; includes `relative_path`, `full_path`, `disk_root`. **Check here if file is missing.** |
| `[Extraction] result: failed (file_not_found)` | File not found at resolved path (wrong path or missing file). |
| `[Extraction] result: failed (file_not_readable)` | File exists but not readable (permissions). |
| `[Extraction] step: open_local_file` | About to open local file. |
| `[Extraction] result: failed (fopen_failed)` | Could not open file (path or permissions). |
| `[Extraction] step: upload_to_openai` | Uploading file to OpenAI. |
| `[Extraction] result: failed (openai_upload_failed)` | OpenAI upload failed. |
| `[Extraction] step: openai_upload_ok` | File uploaded to OpenAI. |
| `[Extraction] step: poll_openai_status` | Waiting for OpenAI to process file. |
| `[Extraction] step: openai_status` | Current OpenAI processing status. |
| `[Extraction] result: failed (openai_file_status_error)` | OpenAI reported error for the file. |
| `[Extraction] result: failed (openai_poll_timeout)` | OpenAI did not finish processing in time. |
| `[Extraction] step: openai_processed_ok` | OpenAI processing done. |
| `[Extraction] step: chat_completion` | Calling OpenAI chat to extract text. |
| `[Extraction] step: chat_ok` | Chat completed (model used in context). |
| `[Extraction] result: failed (chat_completion_failed)` | Chat request failed. |
| `[Extraction] step: post_chat` | Chat done; checking extracted length. |
| `[Extraction] result: empty (empty_extracted_text)` | No usable text returned (e.g. image-only PDF). |
| `[Extraction] result: success (extraction_done)` | Text extracted successfully. |
| `[Extraction] step: openai_file_deleted` | OpenAI file deleted after use. |
| `[Extraction] result: failed (exception_in_extractTextFromFile)` | Unexpected exception in extraction (see trace). |

---

## Example: tail and filter on the server

```bash
# Follow all document/extraction logs
tail -f storage/logs/laravel.log | grep -E '\[Documents\]|\[Extraction\]'

# Only results (main reason)
tail -f storage/logs/laravel.log | grep -E '\[Extraction\] result:|\[Extraction\] job_result:'
```

---

## Paths and “double private”

- **Correct path:** file under `storage/app/private/documents/` → in DB `stored_path` should be `documents/filename.pdf` (no extra `private/`).
- **Wrong path:** `stored_path` = `private/documents/filename.pdf` → full path becomes `storage/app/private/private/documents/...`. The extraction job still resolves the path via the same disk; if the file was saved with the wrong `stored_path`, check `[Extraction] step: path_resolved` and compare `full_path` with where the file actually is. Main app and backdoor now both use `documents` only.
