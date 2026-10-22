# AI-powered Customization Requests for Magento 2

A storefront chat assistant for Magento 2, that answers product questions from a
vector index of the catalog. When a shopper asks for a change to a product
("I'd take these boots, but in green"), it turns that into a customization
request for an admin to review.

## How it works

1. The chat widget is shown on every storefront page and talks to Magento over GraphQL.
2. Each message runs through an agent loop on OpenRouter. The model can use three tools:
   - `search_catalog`: hybrid kNN + keyword search in OpenSearch, with a DB `LIKE` fallback
   - `get_product_context`: details for a single SKU
   - `create_customization_request`: saves the request for admin review
3. A new request shows up in the admin notification inbox and under
   **Content → Customization Requests**.
4. When an admin approves a request, the module creates a hidden simple product
   (`<base-sku>-CUSTOM-<id>`) and emails the customer a single-use restore link.
5. The restore link (`/ai-customization/restore/index/token/<token>/`) adds the
   custom product to the shopper's cart and redirects to the cart page.
6. When an admin declines a request, the customer gets an email with the reason.

The agent never quotes prices and never promises approval. Every request goes
through an admin.

## Requirements

- Magento 2.4.x with GraphQL modules enabled
- OpenSearch with the k-NN plugin. It can be the same cluster as catalog search.
- An OpenRouter API key, used for both chat and embeddings

## Install

```bash
bin/magento module:enable Perspective_AiCustomizationRequests
bin/magento setup:upgrade
bin/magento indexer:reindex perspective_ai_product_rag
```

## Configuration

Go to **Stores → Configuration → General → Perspective AI Customization**.

| Group | Notes |
|---|---|
| General | Master switch. Off by default. |
| Widget content / welcome / states / appearance | Every label and prompt, plus the colours and an optional logo. Empty fields use the defaults from `config.xml`. |
| OpenRouter | API key (stored encrypted), plus the optional site URL and title sent as attribution headers |
| Agent | Chat model (`openrouter/auto`), embedding model (`google/gemini-embedding-001`), max tool iterations, HTTP timeout, message length limit, session TTL |
| OpenSearch | Host, port and credentials. Leave them empty to reuse the catalog search connection. Also: index name prefix, vector dimensions (`3072`, must match the embedding model), batch size. |
| Email | Sender identity and the approve / decline templates |

You can't change the vector dimensions without a full reindex.

## Indexing

The `perspective_ai_product_rag` indexer builds one index per store view, named
`<index_name>_store_<id>`. Only enabled products that are visible on the
storefront get indexed. Each document holds the name, descriptions, category
names, and readable select/text attributes, and is embedded through OpenRouter.

"Update by schedule" is supported through mview subscriptions on the product
EAV, website, and category-link tables. Every change re-embeds the product, so
large catalogs should use scheduled mode.

## GraphQL API

| Operation | Purpose |
|---|---|
| `mutation startAiChatSession` | Creates a session and returns `session_uid` and a one-time `resume_token` |
| `query resumeAiChatSession(input)` | Loads the history of an existing session |
| `mutation sendAiChatMessage(input)` | Runs one chat turn. Accepts an optional `selected_product_sku`, `current_page_url` and `guest_email`. |
| `mutation restoreAiPreparedCart(token)` | Adds the approved product to the current cart |

The schema is in `etc/schema.graphqls`. Guests have to give an email before
their first message. The resume token is stored only as a sha256 hash.

## Admin

- **ACL:** `requests` (view), `approve`, `decline`, `config`
- The grid is a standard UI listing. The request page shows the requested
  changes, links to the customer and products, the approve/decline form, and the
  history.

## Data

| Table | Contents |
|---|---|
| `perspective_ai_chat_session` | Sessions, scoped by store and owner |
| `perspective_ai_chat_message` | Messages, product mentions, and the resolved page context |
| `perspective_ai_customization_request` | Requests, their status, the custom product, and restore token state |
| `perspective_ai_request_history` | Audit trail of submit, approve and decline |

## Code map

```
GraphQl/Resolver/        thin GraphQL entry points
Model/Agent/             agent loop, tool registry, tools
Model/OpenRouter/        chat + embeddings client
Model/OpenSearch/        index client, document builder, retriever
Model/Indexer/           RAG indexer
Model/Workflow/          approval, product creation, cart restore, admin notice
Model/Email/             customer notifications
Model/Config/            typed config access
view/frontend/web/js/    widget (support-chat.js) and GraphQL calls (chat-service.js)
```

To add a tool, implement `Model\Agent\Tool\ToolInterface` and register it in
the `tools` argument of `ToolExecutor` in `etc/di.xml`.
