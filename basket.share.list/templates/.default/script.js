$(() => {
    let shareBasket = {
        replaceBasket: () => {
            let data = {};

            data.basket = $('[name="share_basket"]').val();
            data.action = 'replace';

            shareBasket.ajax(data);
        },

        addBasket: () => {
            let data = {};
            data.basket = $('[name="share_basket"]').val();
            data.action = 'add';

            shareBasket.ajax(data);
        },

        ajax: function(data) {
            BX.ajax.runComponentAction("mk:basket.share", "send", {
                mode: "class",
                data: {
                    data:data
                }
            }).then(function (response) {
                if (response.status === 'success') {
                    document.location.href='/personal/cart/'
                } else {
                    alert(response.status.result)
                }
            });

        }
    };

    $(document).on('click', '[data-js="add_to_basket"]', () => {
        shareBasket.addBasket();
    });

    $(document).on('click', '[data-js="replace_basket"]', () => {
        shareBasket.replaceBasket();
    });
});