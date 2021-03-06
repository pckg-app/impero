import {PckgApp} from "../../../../vendor/pckg/helpers-js/webpack/app.full.js";

import store from "./footer/store.js";
import router from "./footer/router.js";

import "../../../../vendor/pckg/generic/src/Pckg/Generic/public/vue/filters.vue.js";

import './backend.js';
import './generic.js';
import impero from './impero.js';

(new PckgApp()).dev()
    .store(store)
    .router(router)
    .use(impero)
    .register((Vue) => {
        return {};
    });
