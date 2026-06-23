define([], function () {
    'use strict';

    function request(endpoint, query, variables) {
        return fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                query: query,
                variables: variables || {}
            })
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            if (payload.errors && payload.errors.length) {
                throw new Error(payload.errors[0].message);
            }

            return payload.data;
        });
    }

    return {
        startSession: function (endpoint, sessionUid) {
            return request(
                endpoint,
                'mutation StartAiChatSession($input: StartAiChatSessionInput) { startAiChatSession(input: $input) { session_uid customer_email request_uid messages { role content message_type created_at product_mentions { sku name url context_token } } } }',
                {input: {session_uid: sessionUid || null}}
            ).then(function (data) {
                return data.startAiChatSession;
            });
        },

        sendMessage: function (endpoint, sessionUid, message) {
            return request(
                endpoint,
                'mutation SendAiChatMessage($input: SendAiChatMessageInput!) { sendAiChatMessage(input: $input) { request_uid session { session_uid customer_email request_uid messages { role content message_type created_at product_mentions { sku name url context_token } } } assistant_message { role content message_type created_at product_mentions { sku name url context_token } } } }',
                {input: {session_uid: sessionUid, message: message}}
            ).then(function (data) {
                return data.sendAiChatMessage;
            });
        },

        restoreDraftQuote: function (endpoint, token) {
            return request(
                endpoint,
                'mutation RestoreAiDraftQuote($token: String!) { restoreAiDraftQuote(token: $token) { success cart_url masked_cart_id } }',
                {token: token}
            ).then(function (data) {
                return data.restoreAiDraftQuote;
            });
        }
    };
});
