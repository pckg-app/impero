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
        prepareServices: function (state) {
            /**
             * This should be stored in store!
             */
            http.getJSON(utils.url('@api.services'), function (data) {
                state.services = Pckg.Collection.collect(data.services, Impero.Servers.Record.Service);
            });
        }
    }
};