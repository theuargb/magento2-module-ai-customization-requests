define(["Perspective_AiCustomizationRequests/js/chat-service"], function (
    chatService,
) {
    "use strict";

    function normalizeLine(line) {
        var trimmed = String(line || "").trim();
        if (/^\|?[-:\s|]+\|?$/.test(trimmed)) {
            return "";
        }

        if (trimmed.indexOf("|") !== -1) {
            var cells = trimmed
                .split("|")
                .map(function (cell) {
                    return cell.trim();
                })
                .filter(function (cell) {
                    return cell !== "";
                });

            if (cells.length > 1) {
                return cells.join(" · ");
            }
        }

        return String(line || "");
    }

    function appendFormattedLine(container, line) {
        var linkPattern = /\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[^)\s]+)\)/g;
        var cursor = 0;
        var match;

        while ((match = linkPattern.exec(line)) !== null) {
            if (match.index > cursor) {
                container.appendChild(
                    document.createTextNode(line.slice(cursor, match.index)),
                );
            }

            var anchor = document.createElement("a");
            anchor.href = match[2];
            anchor.textContent = match[1];
            anchor.target = "_blank";
            anchor.rel = "noopener";
            container.appendChild(anchor);
            cursor = match.index + match[0].length;
        }

        if (cursor < line.length) {
            container.appendChild(document.createTextNode(line.slice(cursor)));
        }
    }

    function renderBubbleContent(bubble, content) {
        var lines = String(content || "").split(/\r?\n/);
        var hasRenderedContent = false;

        lines.forEach(function (rawLine) {
            var line = normalizeLine(rawLine);
            if (line === "") {
                if (hasRenderedContent) {
                    bubble.appendChild(document.createElement("br"));
                }
                return;
            }

            if (hasRenderedContent) {
                bubble.appendChild(document.createElement("br"));
            }

            appendFormattedLine(bubble, line);
            hasRenderedContent = true;
        });

        if (!hasRenderedContent) {
            bubble.textContent = content || "";
        }
    }

    function createTypingIndicator() {
        var indicator = document.createElement("span");
        indicator.className = "perspective-ai-chat__typing";

        for (var i = 0; i < 3; i++) {
            var dot = document.createElement("span");
            dot.className = "perspective-ai-chat__typing-dot";
            indicator.appendChild(dot);
        }

        return indicator;
    }

    function renderMessages(container, messages) {
        container.innerHTML = "";
        messages.forEach(function (message) {
            var item = document.createElement("div");
            item.className =
                "perspective-ai-chat__message perspective-ai-chat__message--" +
                (message.role || "assistant");

            if (message.isLoading) {
                item.className += " perspective-ai-chat__message--loading";
            }

            var stack = document.createElement("div");
            stack.className = "perspective-ai-chat__message-stack";

            var bubble = document.createElement("div");
            bubble.className = "perspective-ai-chat__bubble";
            if (message.isLoading) {
                bubble.className += " perspective-ai-chat__bubble--loading";
                bubble.appendChild(createTypingIndicator());
            } else {
                renderBubbleContent(bubble, message.content || "");
            }

            stack.appendChild(bubble);

            if (
                !message.isLoading &&
                message.product_mentions &&
                message.product_mentions.length
            ) {
                var mentions = document.createElement("div");
                mentions.className = "perspective-ai-chat__mentions";
                message.product_mentions.forEach(function (mention) {
                    var chip = document.createElement("a");
                    chip.className = "perspective-ai-chat__mention";
                    chip.textContent = "#" + mention.name;
                    chip.href = mention.url || "#";
                    chip.target = "_blank";
                    chip.rel = "noopener";
                    chip.dataset.contextToken = mention.context_token;
                    mentions.appendChild(chip);
                });
                stack.appendChild(mentions);
            }

            item.appendChild(stack);
            container.appendChild(item);
        });
        container.scrollTop = container.scrollHeight;
    }

    return function (config, element) {
        var sessionKey = "perspective_ai_chat_session_uid";
        var wrapper = document.createElement("div");
        wrapper.className = "perspective-ai-chat";
        wrapper.innerHTML =
            '<button type="button" class="perspective-ai-chat__launcher">' +
            config.widgetTitle +
            "</button>" +
            '<section class="perspective-ai-chat__panel" hidden>' +
            '<header class="perspective-ai-chat__header">' +
            "<strong>" +
            config.widgetTitle +
            "</strong>" +
            '<div class="perspective-ai-chat__actions">' +
            '<button type="button" class="perspective-ai-chat__clear">Clear</button>' +
            '<button type="button" class="perspective-ai-chat__close" aria-label="Close">×</button>' +
            "</div>" +
            "</header>" +
            '<div class="perspective-ai-chat__history"></div>' +
            '<form class="perspective-ai-chat__form">' +
            '<textarea class="perspective-ai-chat__input" rows="3" placeholder="Ask for a recommendation or customization"></textarea>' +
            '<button type="submit" class="perspective-ai-chat__send">Send</button>' +
            "</form>" +
            "</section>";

        element.appendChild(wrapper);

        var launcher = wrapper.querySelector(".perspective-ai-chat__launcher");
        var panel = wrapper.querySelector(".perspective-ai-chat__panel");
        var clearButton = wrapper.querySelector(".perspective-ai-chat__clear");
        var closeButton = wrapper.querySelector(".perspective-ai-chat__close");
        var form = wrapper.querySelector(".perspective-ai-chat__form");
        var input = wrapper.querySelector(".perspective-ai-chat__input");
        var history = wrapper.querySelector(".perspective-ai-chat__history");
        var sendButton = wrapper.querySelector(".perspective-ai-chat__send");
        var currentSessionUid = window.localStorage.getItem(sessionKey);
        var sessionLoaded = false;
        var isAwaitingResponse = false;
        var messages = [];
        var sessionRequestId = 0;

        function persistSessionUid(sessionUid) {
            currentSessionUid = sessionUid || null;
            if (currentSessionUid) {
                window.localStorage.setItem(sessionKey, currentSessionUid);
            } else {
                window.localStorage.removeItem(sessionKey);
            }
        }

        function updateMessages(nextMessages) {
            messages = Array.isArray(nextMessages) ? nextMessages.slice() : [];
            renderMessages(history, messages);
        }

        function setAwaitingResponse(flag) {
            isAwaitingResponse = flag;
            input.disabled = flag;
            clearButton.disabled = flag;
            sendButton.disabled = flag;
            sendButton.textContent = flag ? "Working..." : "Send";
        }

        function syncSession(preserveMessages) {
            sessionRequestId += 1;
            var requestId = sessionRequestId;

            return chatService
                .startSession(config.graphqlEndpoint, currentSessionUid)
                .then(function (session) {
                    if (requestId !== sessionRequestId) {
                        return session;
                    }

                    currentSessionUid = session.session_uid;
                    persistSessionUid(session.session_uid);
                    sessionLoaded = true;
                    if (!preserveMessages || !messages.length) {
                        updateMessages(session.messages || []);
                    }
                    return session;
                })
                .catch(function (error) {
                    if (requestId !== sessionRequestId) {
                        throw error;
                    }

                    if (!preserveMessages || !messages.length) {
                        updateMessages([
                            {
                                role: "assistant",
                                content:
                                    error.message ||
                                    "Unable to start the AI assistant.",
                                product_mentions: [],
                            },
                        ]);
                    }
                    throw error;
                });
        }

        function openPanel() {
            wrapper.classList.add("perspective-ai-chat--open");
            panel.hidden = false;
            launcher.hidden = true;
            if (!sessionLoaded) {
                syncSession(false);
            }
            input.focus();
        }

        function closePanel() {
            wrapper.classList.remove("perspective-ai-chat--open");
            panel.hidden = true;
            launcher.hidden = false;
        }

        function resetChat() {
            sessionRequestId += 1;
            persistSessionUid(null);
            sessionLoaded = false;
            updateMessages([]);
            input.value = "";
            input.disabled = false;
            clearButton.disabled = false;
            sendButton.disabled = false;
            sendButton.textContent = "Send";
            input.focus();
        }

        function showPendingConversation(messageText) {
            var nextMessages = messages.filter(function (message) {
                return !message.isLoading;
            });

            nextMessages.push({
                role: "user",
                content: messageText,
                product_mentions: [],
            });
            nextMessages.push({
                role: "assistant",
                content: "",
                product_mentions: [],
                isLoading: true,
            });
            updateMessages(nextMessages);
        }

        function showErrorResponse(error) {
            var nextMessages = messages.filter(function (message) {
                return !message.isLoading;
            });

            nextMessages.push({
                role: "assistant",
                content: error.message || "Unable to send the message.",
                product_mentions: [],
            });
            updateMessages(nextMessages);
        }

        function submitMessage() {
            var message = input.value.trim();
            if (!message || isAwaitingResponse) {
                return;
            }

            input.value = "";
            showPendingConversation(message);
            setAwaitingResponse(true);

            syncSession(true)
                .then(function () {
                    return chatService.sendMessage(
                        config.graphqlEndpoint,
                        currentSessionUid,
                        message,
                    );
                })
                .then(function (payload) {
                    updateMessages(payload.session.messages || []);
                })
                .catch(function (error) {
                    showErrorResponse(error);
                })
                .finally(function () {
                    setAwaitingResponse(false);
                    input.focus();
                });
        }

        launcher.addEventListener("click", openPanel);

        closeButton.addEventListener("click", function () {
            closePanel();
        });

        clearButton.addEventListener("click", function () {
            resetChat();
        });

        input.addEventListener("keydown", function (event) {
            if (
                event.key === "Enter" &&
                !event.shiftKey &&
                !event.isComposing
            ) {
                event.preventDefault();
                submitMessage();
            }
        });

        form.addEventListener("submit", function (event) {
            event.preventDefault();
            submitMessage();
        });

        if (config.restoreToken) {
            chatService
                .restoreDraftQuote(config.graphqlEndpoint, config.restoreToken)
                .then(function (response) {
                    if (response.success && response.cart_url) {
                        window.location.href = response.cart_url;
                    }
                });
        }
    };
});
