/**
 * GraphQL transport for the chat widget and the cart-restore page (same-origin, Luma customer session).
 */
define([], function () {
    'use strict';

    var MESSAGE = 'role content created_at product_mentions { sku selected product { sku name small_image { url } url_rewrites { url } } }';
    var SESSION = 'session_uid request_uid email_required messages { ' + MESSAGE + ' }';

    /**
     * @param {String} endpoint
     * @param {String} storeCode
     * @constructor
     */
    function ChatService(endpoint, storeCode) {
        this.endpoint = endpoint;
        this.storeCode = storeCode;
    }

    /**
     * Rejects with an Error whose `category` is the GraphQL error category (e.g. graphql-no-such-entity).
     */
    ChatService.prototype.request = function (query, variables) {
        return fetch(this.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'Store': this.storeCode},
            body: JSON.stringify({query: query, variables: variables || {}})
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error(response.statusText || 'Invalid response');
            });
        }).then(function (payload) {
            var error;

            if (payload.errors && payload.errors.length) {
                error = new Error(payload.errors[0].message);
                error.category = (payload.errors[0].extensions || {}).category || '';
                throw error;
            }

            return payload.data;
        });
    };

    ChatService.prototype.startSession = function () {
        return this.request('mutation { startAiChatSession { resume_token ' + SESSION + ' } }')
            .then(function (data) { return data.startAiChatSession; });
    };

    ChatService.prototype.resumeSession = function (credentials) {
        return this.request(
            'query Resume($input: ResumeAiChatSessionInput!) { resumeAiChatSession(input: $input) { ' + SESSION + ' } }',
            {input: credentials}
        ).then(function (data) { return data.resumeAiChatSession; });
    };

    /**
     * @param {{session_uid: String, resume_token: String}} credentials
     * @param {{clientMessageId: String, message: String, selectedSku: ?String, pageUrl: String, guestEmail: ?String}} turn
     */
    ChatService.prototype.sendMessage = function (credentials, turn) {
        return this.request(
            'mutation Send($input: SendAiChatMessageInput!) { sendAiChatMessage(input: $input) { '
                + 'request_uid email_required user_message { ' + MESSAGE + ' } assistant_message { ' + MESSAGE + ' } } }',
            {input: {
                session_uid: credentials.session_uid,
                resume_token: credentials.resume_token,
                client_message_id: turn.clientMessageId,
                message: turn.message,
                selected_product_sku: turn.selectedSku,
                current_page_url: turn.pageUrl,
                guest_email: turn.guestEmail
            }}
        ).then(function (data) { return data.sendAiChatMessage; });
    };

    ChatService.prototype.restorePreparedCart = function (token) {
        return this.request(
            'mutation Restore($token: String!) { restoreAiPreparedCart(token: $token) { cart_url } }',
            {token: token}
        ).then(function (data) { return data.restoreAiPreparedCart; });
    };

    return ChatService;
});
