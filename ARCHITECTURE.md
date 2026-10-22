# Perspective_AiCustomizationRequests — Architecture

Luma storefront chat assistant (RequireJS + LESS) backed by GraphQL, an OpenRouter agent with tools,
an OpenSearch hybrid product index, and an admin review workflow for customization requests.

## Layers

| Namespace | Responsibility |
|---|---|
| `GraphQl/Resolver` | Thin: read scope (`GraphQl/RequestScope`), call one service, format via `GraphQl/ChatOutputFormatter`. |
| `Model/Chat` | Chat sessions/messages: models, repositories, `ChatSessionService` (start/authorize), `ChatIdentity` (email owner), `ChatTurnService` (one turn, idempotent by `client_message_id`). |
| `Model/Agent` | `AgentRunner` tool loop → `AgentResult`; `PromptBuilder` owns all prompt text; `TranscriptMapper` replays persisted messages incl. tool calls/results; `ToolRegistry` + `Tool/*` return `ToolResult`; tool failures are fed back to the model. |
| `Model/Request` | Customization requests: models, `RequestStatus` enum state machine, repositories (atomic `transition()`), `RequestSubmitter` (agent create/update), `ApprovalService`, `CustomProductCreator`, `CustomSkuPolicy`, `CartRestorer`, notifiers. |
| `Model/Catalog` | `StorefrontProductGuard` (enabled + visible + in store website), `ProductSummaryMapper`, `PageContextResolver`. |
| `Model/Search` | `OpenSearchClient`, `IndexManager` (alias per store, versioned physical indices), `ProductDocumentBuilder`, `ProductRetriever` (kNN + BM25 via `_msearch`, reciprocal rank fusion, logged DB fallback). |
| `Model/Indexer` | `ProductIndexer`: collection batches per store, content-hash embedding reuse, batched embeddings, full reindex into new index + alias swap. |
| `Model/OpenRouter` | `OpenRouterClient`: chat completions, batched embeddings. |
| `Model/Infra` | `JsonHttpClient` (Guzzle; upstream errors logged, never shown), `JsonCodec`, `TokenService`. |
| `Model/Config` | `ConfigReader` + `AgentConfig`, `OpenRouterConfig`, `SearchConfig`, `WidgetConfig`, `EmailConfig`. Defaults live only in `etc/config.xml`. |
| `ViewModel` | Storefront widget init, restore page init, admin request view. |
| `Controller` | Admin grid/view/decision actions (shared `AbstractRequestAction`); storefront restore page shell. |
| `Cron` | Expired chat session cleanup. |

## Key rules

- **Email ownership**: the chat session's `customer_email` is the only source. Logged-in → customer account
  email; guest → `guest_email` input on send. No quote involvement.
- **Idempotent turns**: same `client_message_id` returns the stored reply; a stored user message without a
  reply is re-run.
- **Request lifecycle**: `pending_review → processing → approved`, `processing → pending_review` (failure
  rollback), `pending_review → declined`. Transitions are compare-and-set updates; concurrent decisions fail.
  One pending request per chat session; the agent updates it instead of duplicating.
- **Approval** (prepared cart option A): claim → create hidden custom simple product (idempotent by SKU) →
  approved + restore token → history. Customer email is sent after the state is committed and tracked by
  `notified_at`; failures leave the request approved and the admin can resend (new token).
- **Restore**: `/ai-customization/restore/index/token/<t>` renders a small page whose JS calls
  `restoreAiPreparedCart`, which adds the custom product to the visitor's own cart and returns the cart URL.
- **Search index**: one alias per store `<index_name>_store_<id>`; partial reindex writes to the alias, full
  reindex builds `<alias>_<timestamp>`, swaps the alias, drops old indices. Only storefront-available products
  are indexed.
- **Frontend**: `support-chat.js` (controller/state), `chat-view.js` (DOM), `chat-markdown.js`, `chat-service.js`
  (GraphQL + shared fragment), `restore-cart.js`. Styles only in `css/source/_module.less`, scoped by
  `#perspective-ai-chat-root`.
