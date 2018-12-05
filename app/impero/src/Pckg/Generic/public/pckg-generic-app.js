Pckg.vue.stores.impero = {
    state: {
        services: []
    },
    getters: {
        services: function (state) {
            return state.services;
        }
    },
    mutations: {
        setServices: function (state, services) {
            state.services = Pckg.Collection.collect(services, Impero.Servers.Record.Service);
        }
    },
    actions: {
        prepareServices: function (state) {
            /**
             * This should be stored in store!
             */
            http.getJSON(utils.url('@api.services'), function (data) {
                $store.commit('setServices', data.services);
            });
        }
    }
};