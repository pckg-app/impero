import Vuex from 'vuex'

export default new Vuex.Store({
    state: {
        router: {
            urls: Pckg.router.urls || {}
        },
        translations: Pckg.translations || {},
    },
    modules: Object.assign(Pckg.vue.stores, {}),
});