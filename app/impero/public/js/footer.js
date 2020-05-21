import store from "./footer/store.js";
import router from "./footer/router.js";

import "../../../../vendor/pckg/generic/src/Pckg/Generic/public/vue/filters.vue.js";

/**
 * Register main Vue event dispatcher.
 * Dispatcher is shared with parent window so we transmit all events between iframes and host.
 *
 * @type {Vue}
 */
window.$dispatcher = new Vue();
window.$router = router;

const data = data || {};
const props = props || {};
window.$store = store;

window.$vue = new Vue({
    el: '#vue-app',
    $store,
    router,
    data: function () {
        return {
            localBus: new Vue(),
            inIframe: window !== window.top
        };
    },
    mixins: [pckgDelimiters],
    methods: {
        openModal: function (data) {
            this.modals.push(data);
            $('.modal.in').modal('hide');
            Vue.nextTick(function () {
                $('#' + data.id).modal('show');
            });
        },
        emit: function (event) {
            $dispatcher.$emit(event);
        }
    },
    computed: {
        '$store': function () {
            return $store;
        },
        basket: function () {
            return this.$store.state.basket;
        }
    },
    updated: function () {
        this.$nextTick(function () {
            $dispatcher.$emit('vue:updated');
        });
    }
});
