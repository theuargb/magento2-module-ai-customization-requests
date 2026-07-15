define([], function () {
    'use strict';

    function request(endpoint, query, variables) {
        return fetch(endpoint, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({query: query, variables: variables || {}})
        }).then(function (response) {
            if (!response.ok) { throw new Error('The assistant is temporarily unavailable.'); }
            return response.json();
        }).then(function (payload) {
            if (payload.errors && payload.errors.length) { throw new Error(payload.errors[0].message); }
            if (!payload.data) { throw new Error('The assistant returned an invalid response.'); }
            return payload.data;
        });
    }

    return {
        startSession: function (endpoint) {
            return request(endpoint, 'mutation { startAiChatSession { session_uid resume_token request_uid messages { role content message_type created_at product_mentions { sku name url image_url selected } } } }').then(function (data) { return data.startAiChatSession; });
        },
        resumeSession: function (endpoint, credentials) {
            return request(endpoint, 'query Resume($input: ResumeAiChatSessionInput!) { resumeAiChatSession(input: $input) { session_uid request_uid messages { role content message_type created_at product_mentions { sku name url image_url selected } } } }', {input: credentials}).then(function (data) { return data.resumeAiChatSession; });
        },
        sendMessage: function (endpoint, credentials, message, selectedSku) {
            return request(endpoint, 'mutation Send($input: SendAiChatMessageInput!) { sendAiChatMessage(input: $input) { request_uid session { session_uid request_uid messages { role content message_type created_at product_mentions { sku name url image_url selected } } } assistant_message { role content message_type created_at product_mentions { sku name url image_url selected } } } }', {input: {
                session_uid: credentials.session_uid,
                resume_token: credentials.resume_token,
                client_message_id: String(Date.now()) + '-' + Math.random().toString(16).slice(2),
                message: message,
                selected_product_sku: selectedSku || null
            }}).then(function (data) { return data.sendAiChatMessage; });
        },
        restorePreparedCart: function (endpoint, token) {
            return request(endpoint, 'mutation Restore($token: String!) { restoreAiPreparedCart(token: $token) { success cart_url masked_cart_id } }', {token: token}).then(function (data) { return data.restoreAiPreparedCart; });
        }
    };
});
