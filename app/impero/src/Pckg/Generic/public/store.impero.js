import {Services} from "./impero";

export default {
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
            // state.services = Pckg.Collection.collect(services, Impero.Servers.Record.Service);
            state.services = services;
        }
    },
    actions: {
        prepareServices: function (state) {
            console.log('preparing services');
            (new Services()).all().then(function (services) {
                $store.commit('setServices', services);
            });
            /**
             * This should be stored in store!
             */
            return;
            http.getJSON(utils.url('@api.services'), function (data) {
                $store.commit('setServices', data.services);
            });
        }
    }
}