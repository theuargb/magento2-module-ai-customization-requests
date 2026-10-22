/**
 * DOM of the chat widget. Holds no conversation state: the controller (support-chat.js) passes data in
 * and receives user intents through `handlers`.
 */
define([
    'Perspective_AiCustomizationRequests/js/chat-dom',
    'Perspective_AiCustomizationRequests/js/chat-markdown'
], function (dom, markdown) {
    'use strict';

    var B = 'perspective-ai-chat__';
    var el = dom.el;

    function brandMark(config) {
        var mark = el('span', B + 'mark', config.logoUrl ? null : config.icon);
        var logo;

        mark.setAttribute('aria-hidden', 'true');
        if (config.logoUrl) {
            logo = el('img');
            logo.src = config.logoUrl;
            logo.alt = '';
            mark.appendChild(logo);
        }

        return mark;
    }

    function productUrl(config, product) {
        var rewrite = product.url_rewrites && product.url_rewrites[0];

        return rewrite ? config.baseUrl + rewrite.url : null;
    }

    /**
     * @param {Object} config Widget init config (see ViewModel\ChatWidget)
     * @param {HTMLElement} mount
     * @param {{onOpen, onClose, onClear, onSubmit, onRetry, onSelectProduct, onClearSelection, onReload}} handlers
     */
    return function createView(config, mount, handlers) {
        var copy = config.copy;
        var composerState = {ready: false, sending: false, emailRequired: false};
        var root = el('div', 'perspective-ai-chat');
        var launcher = dom.button(B + 'launcher', null, handlers.onOpen);
        var panel = el('section', B + 'panel');
        var title = el('strong', B + 'title', config.title);
        var status = el('span', B + 'status', copy.connecting);
        var history = el('div', B + 'history');
        var contextBar = el('div', B + 'context');
        var form = el('form', B + 'form');
        var email = el('input', B + 'email');
        var input = el('textarea', B + 'input');
        var send = el('button', B + 'send', copy.sendLabel);
        var closeButton = dom.button(B + 'close', '×', handlers.onClose);

        Object.keys(config.colors).forEach(function (variable) {
            root.style.setProperty(variable, config.colors[variable]);
        });

        launcher.setAttribute('aria-expanded', 'false');
        dom.append(launcher, [brandMark(config), el('span', B + 'launcher-label', config.title)]);

        title.id = 'perspective-ai-chat-title';
        panel.hidden = true;
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-labelledby', title.id);
        closeButton.setAttribute('aria-label', copy.closeLabel);
        history.setAttribute('aria-live', 'polite');
        contextBar.hidden = true;

        email.type = 'email';
        email.autocomplete = 'email';
        email.placeholder = copy.emailPlaceholder;
        email.setAttribute('aria-label', copy.emailPlaceholder);
        input.rows = 1;
        input.maxLength = config.maxMessageLength;
        input.placeholder = copy.inputPlaceholder;
        input.setAttribute('aria-label', copy.inputPlaceholder);
        send.type = 'submit';

        dom.append(form, [email, input, send]);
        dom.append(panel, [
            dom.append(el('header', B + 'header'), [
                dom.append(el('div', B + 'identity'), [
                    brandMark(config),
                    dom.append(el('div', B + 'brand-copy'), [title, status])
                ]),
                dom.append(el('div', B + 'actions'), [dom.button(B + 'clear', copy.clearLabel, handlers.onClear), closeButton])
            ]),
            history,
            dom.append(el('div', B + 'composer'), [contextBar, form, el('p', B + 'helper', copy.helper)])
        ]);
        mount.appendChild(dom.append(root, [launcher, panel]));

        function emailValid() {
            return !composerState.emailRequired || email.value.trim() !== '' && email.validity.valid;
        }

        function refreshComposer() {
            var locked = !composerState.ready || composerState.sending;

            email.hidden = !composerState.emailRequired;
            email.required = composerState.emailRequired;
            email.disabled = locked;
            input.disabled = locked;
            send.disabled = locked || input.value.trim() === '' || !emailValid();
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 112) + 'px';
        }

        function submit() {
            var text = input.value.trim();

            if (send.disabled || text === '') {
                return;
            }
            input.value = '';
            refreshComposer();
            handlers.onSubmit(text, composerState.emailRequired ? email.value.trim() : null);
        }

        function setInput(text) {
            input.value = text;
            refreshComposer();
            input.focus();
        }

        function renderWelcome() {
            var prompts = el('div', B + 'prompts');

            config.prompts.forEach(function (prompt) {
                prompts.appendChild(dom.append(dom.button(B + 'prompt', null, function () { setInput(prompt.text); }), [
                    el('span', B + 'prompt-label', prompt.label),
                    el('span', B + 'prompt-text', prompt.text),
                    el('span', B + 'prompt-arrow', '→')
                ]));
            });

            history.appendChild(dom.append(el('div', B + 'welcome'), [
                dom.append(el('div', B + 'welcome-intro'), [
                    el('span', B + 'eyebrow', copy.welcomeEyebrow),
                    el('h2', B + 'heading', copy.welcomeTitle),
                    el('p', B + 'lead', copy.welcomeCopy)
                ]),
                prompts
            ]));
        }

        function renderProduct(mention) {
            var product = mention.product;
            var image = el('div', B + 'product-image', (product.name || product.sku).charAt(0).toUpperCase());
            var actions = el('div', B + 'product-actions');
            var url = productUrl(config, product);
            var picture;
            var link;

            if (product.small_image && product.small_image.url) {
                picture = el('img');
                picture.src = product.small_image.url;
                picture.alt = '';
                picture.loading = 'lazy';
                image.appendChild(picture);
            }
            actions.appendChild(dom.button(B + 'button', copy.productAsk, function () { handlers.onSelectProduct(mention); }));
            if (url) {
                link = el('a', B + 'product-link', copy.productView);
                link.href = url;
                actions.appendChild(link);
            }

            return dom.append(el('div', B + 'product'), [
                image,
                dom.append(el('div', B + 'product-details'), [
                    el('span', B + 'product-label', copy.productLabel),
                    el('strong', B + 'product-name', product.name)
                ]),
                actions
            ]);
        }

        function renderMessage(message) {
            var role = message.role === 'user' ? 'user' : 'assistant';
            var item = el('article', B + 'message ' + B + 'message--' + role + (message.failed ? ' ' + B + 'message--failed' : ''));
            var bubble;

            item.appendChild(el('span', B + 'author', role === 'user' ? copy.userLabel : config.title));
            if (message.loading) {
                item.appendChild(dom.append(el('div', B + 'typing'), [el('span'), el('span'), el('span')]));
                return item;
            }

            bubble = el('div', B + 'bubble');
            if (role === 'user') {
                bubble.appendChild(el('p', B + 'md-p', message.content));
            } else {
                markdown.render(bubble, message.content);
            }
            item.appendChild(bubble);

            (message.product_mentions || []).forEach(function (mention) {
                if (mention.product) {
                    item.appendChild(renderProduct(mention));
                }
            });
            if (message.failed) {
                item.appendChild(dom.append(el('div', B + 'failure'), [
                    el('span', '', message.error || copy.messageSendError),
                    dom.button(B + 'button', copy.errorRetry, function () { handlers.onRetry(message); })
                ]));
            }

            return item;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submit();
        });
        input.addEventListener('input', refreshComposer);
        email.addEventListener('input', refreshComposer);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                submit();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !panel.hidden) {
                handlers.onClose();
            }
        });

        return {
            open: function () {
                panel.hidden = false;
                launcher.hidden = true;
                launcher.setAttribute('aria-expanded', 'true');
                history.scrollTop = history.scrollHeight;
                input.focus();
            },
            close: function () {
                panel.hidden = true;
                launcher.hidden = false;
                launcher.setAttribute('aria-expanded', 'false');
                launcher.focus();
            },
            setStatus: function (label, state) {
                status.textContent = label;
                status.dataset.state = state;
            },
            /** @param {{ready?: Boolean, sending?: Boolean, emailRequired?: Boolean}} next */
            setComposer: function (next) {
                Object.assign(composerState, next);
                refreshComposer();
            },
            setSelection: function (mention) {
                contextBar.replaceChildren();
                contextBar.hidden = !mention;
                if (mention) {
                    dom.append(contextBar, [
                        el('span', B + 'context-label', copy.contextLabel),
                        el('strong', B + 'context-name', mention.product.name),
                        dom.button(B + 'context-remove', copy.contextRemove, handlers.onClearSelection)
                    ]);
                    input.focus();
                }
            },
            renderMessages: function (messages) {
                history.replaceChildren();
                if (!messages.length) {
                    renderWelcome();
                    return;
                }
                messages.forEach(function (message) {
                    history.appendChild(renderMessage(message));
                });
                history.scrollTop = history.scrollHeight;
            },
            renderError: function (text) {
                history.replaceChildren(dom.append(el('div', B + 'error'), [
                    el('span', B + 'eyebrow', copy.errorEyebrow),
                    el('h2', B + 'heading', copy.errorTitle),
                    el('p', B + 'lead', text || copy.errorCopy),
                    dom.button(B + 'button', copy.errorRetry, handlers.onReload)
                ]));
            }
        };
    };
});
