/**
 * Cart-restore page: redeems the emailed token through GraphQL and redirects to the cart.
 */
define(['Perspective_AiCustomizationRequests/js/chat-service'], function (ChatService) {
    'use strict';

    return function (config, mount) {
        var service = new ChatService(config.graphqlEndpoint, config.storeCode);

        mount.textContent = config.copy.progress;
        service.restorePreparedCart(config.token).then(function (result) {
            window.location.replace(result.cart_url);
        }).catch(function (error) {
            mount.dataset.state = 'error';
            mount.textContent = error.message || config.copy.error;
        });
    };
});
