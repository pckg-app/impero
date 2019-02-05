import PckgPingComponent from "../../../../vendor/pckg/generic/src/Pckg/Generic/View/pckgPing.vue";
import VueComponentGmapsComponent from "../../../../vendor/pckg/helpers-js/vue/gmaps.vue";
import PckgDynamicPaginatorComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-dynamic-paginator.vue";
import PckgTabelizeFieldDatetimeComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-tabelize-field-datetime.vue";
import PckgTabelizeFieldOrderComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-tabelize-field-order.vue";
import PckgTabelizeFieldBooleanComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-tabelize-field-boolean.vue";
import PckgTabelizeFieldEditorComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-tabelize-field-editor.vue";
import PckgHtmlbuilderSelectComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-htmlbuilder-select.vue";
import PckgMaestroActionsComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/View/_pckg_maestro_actions.vue";
import PckgMaestroFieldComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/View/_pckg_maestro_field.vue";
import PckgBootstrapAlertComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-bootstrap-alert.vue";
import PckgBootstrapModalComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-bootstrap-modal.vue";
import PckgDispatcherNotificationsComponent from "../../../../vendor/pckg/generic/src/Pckg/Generic/View/dispatcherNotifications.vue";

import ImperoServersOne from "../../src/Impero/Servers/View/servers/one.vue";
import ImperoServersOneApplications from "../../src/Impero/Servers/View/servers/one_applications.vue";
import ServiceAutoinstall from "../../src/Impero/Servers/View/servers/service_autoinstall.vue";
import ServiceAutoinstallRequirements from "../../src/Impero/Servers/View/servers/service_autoinstall_requirements.vue";

import "../../../../vendor/pckg/helpers-js/webpack/orm.js";
import ImperoStore from "../../src/Pckg/Generic/public/store.impero";
import { Tasks, Services, Service } from "../../src/Pckg/Generic/public/impero.js";

Pckg.vue.stores.impero = ImperoStore;

Vue.component('pckg-ping', PckgPingComponent);
Vue.component('vue-component-gmaps', VueComponentGmapsComponent);
Vue.component('pckg-dynamic-paginator', PckgDynamicPaginatorComponent);
Vue.component('pckg-tabelize-field-datetime', PckgTabelizeFieldDatetimeComponent);
Vue.component('pckg-tabelize-field-order', PckgTabelizeFieldOrderComponent);
Vue.component('pckg-tabelize-field-boolean', PckgTabelizeFieldBooleanComponent);
Vue.component('pckg-tabelize-field-editor', PckgTabelizeFieldEditorComponent);
Vue.component('pckg-htmlbuilder-select', PckgHtmlbuilderSelectComponent);
window.pckgMaestroActionsComponent = Vue.component('pckg-maestro-actions', PckgMaestroActionsComponent);
Vue.component('pckg-maestro-field', PckgMaestroFieldComponent);
Vue.component('pckg-bootstrap-alert', PckgBootstrapAlertComponent);
Vue.component('pckg-bootstrap-modal', PckgBootstrapModalComponent);
Vue.component('pckg-dispatcher-notifications', PckgDispatcherNotificationsComponent);

Vue.component('impero-servers-one', ImperoServersOne);
Vue.component('impero-servers-one-applications', ImperoServersOneApplications);
Vue.component('service-autoinstall', ServiceAutoinstall);
Vue.component('service-autoinstall-requirements', ServiceAutoinstallRequirements);