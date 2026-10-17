# DukaFlow v1 API collection

Covers all 60 requests of the mobile API (`routes/api.php`), organized to match the API itself.

- `dukaflow-api.postman_collection.json` — import into Postman (File → Import), or into Bruno (Import Collection).
- `bruno/` — native Bruno OpenCollection format. Open this folder directly in Bruno (Open Collection), or copy it into your Bruno workspace.

Both are generated from the same source list of endpoints, so they stay in sync.

## Using it

1. Set the `base_url` collection variable to your environment (defaults to the production URL).
2. Run **Auth → Login**, **Register**, or **Google sign-in** once — the returned token is saved automatically into the `token` variable and used as the bearer token for every other request.
3. Requests with a dynamic ID in the path (`item_id`, `invoice_id`, `receipt_id`, `sale_id`, `customer_id`, `supplier_id`, `barcode`) read those from collection variables — set them to a real ID after creating/listing a record.
4. The four file-upload requests (shop logo, product photo, KYC document, proof of payment) need their Body tab switched to Multipart Form in the client, since a file can't be pre-filled from a template — each has a note explaining which field(s) to add.
