/**
 * Chat widget controller: session lifecycle, conversation state, sending with idempotent retries.
 * Credentials live in localStorage per store view; the open/closed state in sessionStorage so the panel
 * stays open while the shopper navigates to a recommended product.
 */
define([
    'Perspective_AiCustomizationRequests/js/chat-service',
    'Perspective_AiCustomizationRequests/js/chat-view'
], function (ChatService, createView) {
    'use strict';

    var SESSION_GONE = 'graphql-no-such-entity';

    function clientMessageId() {
        return window.crypto && window.crypto.randomUUID
            ? window.crypto.randomUUID()
            : Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }

    function storedJson(storage, key) {
        try {
            return JSON.parse(storage.getItem(key) || 'null');
        } catch (error) {
            storage.removeItem(key);
            return null;
        }
    }

    return function (config, mount) {
        var service = new ChatService(config.graphqlEndpoint, config.storeCode);
        var openKey = config.storageKey + ':open';
        var copy = config.copy;
        var state = {
            credentials: storedJson(window.localStorage, config.storageKey),
            messages: [],
            selection: null,
            emailRequired: false,
            initialized: false,
            sending: false
        };
        var view;

        function saveCredentials(credentials) {
            state.credentials = credentials;
            if (credentials) {
                window.localStorage.setItem(config.storageKey, JSON.stringify(credentials));
            } else {
                window.localStorage.removeItem(config.storageKey);
            }
        }

        function applySession(session) {
            if (session.resume_token) {
                saveCredentials({session_uid: session.session_uid, resume_token: session.resume_token});
            }
            state.messages = session.messages;
            state.emailRequired = session.email_required;
            state.initialized = true;
            view.renderMessages(state.messages);
            view.setComposer({ready: true, sending: false, emailRequired: state.emailRequired});
            view.setStatus(copy.ready, 'ready');
        }

        function startSession() {
            saveCredentials(null);
            return service.startSession().then(applySession);
        }

        function initialize() {
            view.setComposer({ready: false});
            view.setStatus(copy.connecting, 'loading');
            view.renderMessages(state.messages);

            var loading = state.credentials
                ? service.resumeSession(state.credentials).then(applySession, startSession)
                : startSession();

            return loading.catch(function (error) {
                view.renderError(error.message);
                view.setStatus(copy.unavailable, 'error');
            });
        }

        function setSelection(mention) {
            state.selection = mention;
            view.setSelection(mention);
        }

        function deliver(message, guestEmail) {
            var loader = {role: 'assistant', loading: true};

            message.failed = false;
            state.sending = true;
            state.messages.push(loader);
            view.renderMessages(state.messages);
            view.setComposer({sending: true});
            view.setStatus(copy.thinking, 'loading');

            service.sendMessage(state.credentials, {
                clientMessageId: message.clientMessageId,
                message: message.content,
                selectedSku: message.selectedSku,
                pageUrl: window.location.href,
                guestEmail: guestEmail
            }).then(function (turn) {
                state.messages.splice(state.messages.indexOf(message), 1, turn.user_message);
                state.messages.push(turn.assistant_message);
                state.emailRequired = turn.email_required;
                view.setStatus(copy.ready, 'ready');
            }).catch(function (error) {
                message.failed = true;
                message.error = error.message;
                message.guestEmail = guestEmail;
                view.setStatus(copy.messageFailed, 'error');
                if (error.category === SESSION_GONE) {
                    // The session expired or was cleaned up: continue in a fresh one, keeping the
                    // failed message so the shopper can retry it there.
                    return startSession().then(function () {
                        state.messages.push(message);
                    }, function () {
                        state.credentials = null;
                    });
                }
            }).finally(function () {
                state.messages = state.messages.filter(function (item) { return item !== loader; });
                state.sending = false;
                view.renderMessages(state.messages);
                view.setComposer({sending: false, emailRequired: state.emailRequired});
            });
        }

        view = createView(config, mount, {
            onOpen: function () {
                window.sessionStorage.setItem(openKey, '1');
                view.open();
                if (!state.initialized) {
                    initialize();
                }
            },
            onClose: function () {
                window.sessionStorage.removeItem(openKey);
                view.close();
            },
            onClear: function () {
                if (state.sending) {
                    return;
                }
                state.messages = [];
                setSelection(null);
                view.setComposer({ready: false});
                startSession().catch(function (error) {
                    view.renderError(error.message);
                    view.setStatus(copy.unavailable, 'error');
                });
            },
            onReload: initialize,
            onSubmit: function (text, guestEmail) {
                var message = {
                    role: 'user',
                    content: text,
                    clientMessageId: clientMessageId(),
                    selectedSku: state.selection ? state.selection.sku : null,
                    product_mentions: state.selection ? [state.selection] : []
                };

                setSelection(null);
                state.messages.push(message);
                deliver(message, guestEmail);
            },
            onRetry: function (message) {
                if (!state.sending && state.credentials) {
                    deliver(message, message.guestEmail);
                }
            },
            onSelectProduct: function (mention) {
                setSelection({sku: mention.sku, selected: true, product: mention.product});
            },
            onClearSelection: function () {
                setSelection(null);
            }
        });

        view.renderMessages(state.messages);
        if (window.sessionStorage.getItem(openKey)) {
            view.open();
            initialize();
        }
    };
});
